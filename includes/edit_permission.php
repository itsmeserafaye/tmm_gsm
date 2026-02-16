<?php
if (!defined('TMM_EDIT_PERMISSION_INCLUDED')) {
  define('TMM_EDIT_PERMISSION_INCLUDED', true);
}

function ep_ensure_schema(mysqli $db): bool {
  static $done = false;
  if ($done) return true;
  $done = true;
  $ok = $db->query("CREATE TABLE IF NOT EXISTS user_edit_permissions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    target_user_id INT NOT NULL,
    editor_user_id INT NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    granted_at DATETIME DEFAULT NULL,
    last_active_at DATETIME DEFAULT NULL,
    status ENUM('Pending','Granted','Revoked','Expired') NOT NULL DEFAULT 'Pending',
    request_ip VARCHAR(64) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pair (target_user_id, editor_user_id),
    INDEX idx_status (status),
    INDEX idx_expires (expires_at),
    CONSTRAINT fk_uep_target FOREIGN KEY (target_user_id) REFERENCES rbac_users(id) ON DELETE CASCADE,
    CONSTRAINT fk_uep_editor FOREIGN KEY (editor_user_id) REFERENCES rbac_users(id) ON DELETE CASCADE
  ) ENGINE=InnoDB");
  if (!$ok) return false;
  $ok2 = $db->query("CREATE TABLE IF NOT EXISTS user_edit_permission_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    request_id BIGINT NOT NULL,
    event VARCHAR(32) NOT NULL,
    info TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_req (request_id),
    CONSTRAINT fk_uepe_req FOREIGN KEY (request_id) REFERENCES user_edit_permissions(id) ON DELETE CASCADE
  ) ENGINE=InnoDB");
  return (bool)$ok2;
}

function ep_log(mysqli $db, int $requestId, string $event, ?string $info = null): void {
  $stmt = $db->prepare("INSERT INTO user_edit_permission_events(request_id, event, info) VALUES(?,?,?)");
  if ($stmt) {
    $event = substr($event, 0, 32);
    $info = $info !== null ? substr($info, 0, 4000) : null;
    $stmt->bind_param('iss', $requestId, $event, $info);
    $stmt->execute();
    $stmt->close();
  }
}

function ep_cleanup(mysqli $db): void {
  $db->query("UPDATE user_edit_permissions SET status='Expired' WHERE status='Pending' AND expires_at < NOW()");
  $db->query("UPDATE user_edit_permissions SET status='Revoked' WHERE status='Granted' AND (last_active_at IS NULL OR last_active_at < (NOW() - INTERVAL 30 MINUTE))");
}

function ep_can_request(mysqli $db, int $editorId, int $targetUserId, int $perDay = 3, int $cooldownMin = 5): array {
  $stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM user_edit_permissions WHERE editor_user_id=? AND target_user_id=? AND DATE(created_at)=CURDATE()");
  if ($stmt) {
    $stmt->bind_param('ii', $editorId, $targetUserId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $cnt = (int)($row['cnt'] ?? 0);
    if ($cnt >= $perDay) {
      return ['ok' => false, 'error' => 'rate_limit'];
    }
  }
  $stmt2 = $db->prepare("SELECT created_at FROM user_edit_permissions WHERE editor_user_id=? AND target_user_id=? ORDER BY id DESC LIMIT 1");
  if ($stmt2) {
    $stmt2->bind_param('ii', $editorId, $targetUserId);
    $stmt2->execute();
    $r = $stmt2->get_result()->fetch_assoc();
    $stmt2->close();
    if ($r) {
      $lastTs = strtotime((string)$r['created_at']);
      if ($lastTs !== false && (time() - $lastTs) < ($cooldownMin * 60)) {
        $wait = ($cooldownMin * 60) - (time() - $lastTs);
        return ['ok' => false, 'error' => 'cooldown', 'wait_seconds' => $wait];
      }
    }
  }
  return ['ok' => true];
}

function ep_request(mysqli $db, int $editorId, int $targetUserId, int $ttlHours = 24): array {
  if (!ep_ensure_schema($db)) return ['ok' => false, 'error' => 'schema_failed'];
  $can = ep_can_request($db, $editorId, $targetUserId);
  if (!($can['ok'] ?? false)) return $can;

  $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
  $hash = password_hash($token, PASSWORD_DEFAULT);
  if ($hash === false) return ['ok' => false, 'error' => 'token_failed'];

  $expiresAt = date('Y-m-d H:i:s', time() + (max(1, $ttlHours) * 3600));
  $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64);
  $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

  $stmt = $db->prepare("INSERT INTO user_edit_permissions(target_user_id, editor_user_id, token_hash, expires_at, request_ip, user_agent) VALUES(?,?,?,?,?,?)");
  if (!$stmt) return ['ok' => false, 'error' => 'db_prepare_failed'];
  $stmt->bind_param('iissss', $targetUserId, $editorId, $hash, $expiresAt, $ip, $ua);
  $ok = $stmt->execute();
  $reqId = (int)$stmt->insert_id;
  $stmt->close();
  if (!$ok || $reqId <= 0) return ['ok' => false, 'error' => 'insert_failed'];
  ep_log($db, $reqId, 'request');
  return ['ok' => true, 'request_id' => $reqId, 'token' => $token, 'expires_at' => $expiresAt];
}

function ep_validate(mysqli $db, int $requestId, string $token): array {
  if (!ep_ensure_schema($db)) return ['ok' => false, 'error' => 'schema_failed'];
  ep_cleanup($db);
  $stmt = $db->prepare("SELECT id, token_hash, expires_at, status FROM user_edit_permissions WHERE id=? LIMIT 1");
  if (!$stmt) return ['ok' => false, 'error' => 'db_prepare_failed'];
  $stmt->bind_param('i', $requestId);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!$row) return ['ok' => false, 'error' => 'not_found'];
  if (($row['status'] ?? '') !== 'Pending') return ['ok' => false, 'error' => 'already_processed'];
  $exp = strtotime((string)$row['expires_at']);
  if ($exp !== false && $exp < time()) {
    $db->query("UPDATE user_edit_permissions SET status='Expired' WHERE id=" . (int)$requestId);
    ep_log($db, $requestId, 'expired');
    return ['ok' => false, 'error' => 'expired'];
  }
  $hash = (string)($row['token_hash'] ?? '');
  if ($hash === '' || !password_verify($token, $hash)) {
    ep_log($db, $requestId, 'invalid');
    return ['ok' => false, 'error' => 'invalid'];
  }
  $db->query("UPDATE user_edit_permissions SET status='Granted', granted_at=NOW(), last_active_at=NOW() WHERE id=" . (int)$requestId);
  ep_log($db, $requestId, 'granted');
  return ['ok' => true];
}

function ep_authorized(mysqli $db, int $editorId, int $targetUserId): array {
  if (!ep_ensure_schema($db)) return ['ok' => false, 'authorized' => false];
  ep_cleanup($db);
  $stmt = $db->prepare("SELECT id, granted_at, last_active_at FROM user_edit_permissions
    WHERE editor_user_id=? AND target_user_id=? AND status='Granted'
    ORDER BY id DESC LIMIT 1");
  if (!$stmt) return ['ok' => false, 'authorized' => false];
  $stmt->bind_param('ii', $editorId, $targetUserId);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!$row) return ['ok' => true, 'authorized' => false];
  $last = strtotime((string)($row['last_active_at'] ?? ''));
  if ($last === false || $last < (time() - 1800)) {
    return ['ok' => true, 'authorized' => false];
  }
  return ['ok' => true, 'authorized' => true, 'granted_at' => (string)($row['granted_at'] ?? ''), 'request_id' => (int)$row['id']];
}

function ep_ping(mysqli $db, int $editorId, int $targetUserId): array {
  if (!ep_ensure_schema($db)) return ['ok' => false];
  $stmt = $db->prepare("SELECT id FROM user_edit_permissions WHERE editor_user_id=? AND target_user_id=? AND status='Granted' ORDER BY id DESC LIMIT 1");
  if (!$stmt) return ['ok' => false];
  $stmt->bind_param('ii', $editorId, $targetUserId);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!$row) return ['ok' => false];
  $id = (int)$row['id'];
  $db->query("UPDATE user_edit_permissions SET last_active_at=NOW() WHERE id=" . $id);
  ep_log($db, $id, 'ping');
  return ['ok' => true, 'request_id' => $id];
}

