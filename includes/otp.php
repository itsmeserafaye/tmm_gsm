<?php
require_once __DIR__ . '/mailer.php';

function otp_ensure_schema(mysqli $db): bool {
  $ok = $db->query("CREATE TABLE IF NOT EXISTS email_otps (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    purpose VARCHAR(40) NOT NULL,
    otp_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    attempts INT NOT NULL DEFAULT 0,
    consumed_at DATETIME DEFAULT NULL,
    request_ip VARCHAR(64) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_purpose (email, purpose),
    INDEX idx_expires (expires_at),
    INDEX idx_consumed (consumed_at)
  ) ENGINE=InnoDB");
  if ($ok) return true;
  $chk = $db->query("SHOW TABLES LIKE 'email_otps'");
  if ($chk && $chk->num_rows > 0) return true;
  return false;
}

function otp_generate_code(): string {
  return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function otp_str_limit(?string $v, int $max): string {
  $s = (string)($v ?? '');
  if ($max <= 0) return '';
  if (strlen($s) <= $max) return $s;
  return substr($s, 0, $max);
}

function otp_log_db_error(mysqli $db, string $context): void {
  $errno = (int)($db->errno ?? 0);
  $err = (string)($db->error ?? '');
  @error_log('[TMM][OTP][' . $context . '] mysql_errno=' . $errno . ' mysql_error=' . $err);
}

function otp_debug_enabled(): bool {
  $v = strtolower(trim((string)getenv('TMM_DEBUG')));
  return $v === '1' || $v === 'true' || $v === 'yes';
}

function otp_debug_data(mysqli $db, string $context): array {
  return [
    'context' => $context,
    'mysql_errno' => (int)($db->errno ?? 0),
    'mysql_error' => otp_str_limit((string)($db->error ?? ''), 240),
  ];
}

function otp_send(mysqli $db, string $email, string $purpose, int $ttlSeconds = 180): array {
  if (!otp_ensure_schema($db)) {
    otp_log_db_error($db, 'ensure_schema');
    $data = otp_debug_enabled() ? otp_debug_data($db, 'ensure_schema') : null;
    $msg = 'OTP storage is not available.';
    if (otp_debug_enabled() && !empty($data['mysql_errno'])) $msg .= ' (errno ' . (int)$data['mysql_errno'] . ')';
    return ['ok' => false, 'message' => $msg, 'data' => $data];
  }

  $email = strtolower(trim($email));
  $purpose = trim($purpose);
  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    return ['ok' => false, 'message' => 'Invalid email.'];
  }
  if ($purpose === '') {
    return ['ok' => false, 'message' => 'Invalid request.'];
  }
  $email = otp_str_limit($email, 190);
  $purpose = otp_str_limit($purpose, 40);

  $code = otp_generate_code();
  $hash = password_hash($code, PASSWORD_DEFAULT);
  if ($hash === false) {
    return ['ok' => false, 'message' => 'OTP generation failed.'];
  }

  $expiresAt = date('Y-m-d H:i:s', time() + max(30, $ttlSeconds));
  $ip = otp_str_limit((string)($_SERVER['REMOTE_ADDR'] ?? ''), 64);
  $ua = otp_str_limit((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 255);

  $stmt = $db->prepare("INSERT INTO email_otps(email, purpose, otp_hash, expires_at, request_ip, user_agent) VALUES(?,?,?,?,?,?)");
  if (!$stmt) {
    otp_log_db_error($db, 'insert_prepare');
    $data = otp_debug_enabled() ? otp_debug_data($db, 'insert_prepare') : null;
    $msg = 'OTP storage failed.';
    if (otp_debug_enabled() && !empty($data['mysql_errno'])) $msg .= ' (errno ' . (int)$data['mysql_errno'] . ')';
    return ['ok' => false, 'message' => $msg, 'data' => $data];
  }
  $stmt->bind_param('ssssss', $email, $purpose, $hash, $expiresAt, $ip, $ua);
  $ok = $stmt->execute();
  $otpId = (int)$stmt->insert_id;
  $stmt->close();
  if (!$ok) {
    otp_log_db_error($db, 'insert_execute');
    $data = otp_debug_enabled() ? otp_debug_data($db, 'insert_execute') : null;
    $msg = 'OTP storage failed.';
    if (otp_debug_enabled() && !empty($data['mysql_errno'])) $msg .= ' (errno ' . (int)$data['mysql_errno'] . ')';
    return ['ok' => false, 'message' => $msg, 'data' => $data];
  }

  $subject = 'Your GoServePH OTP Code';
  $html = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#111">'
    . '<p>Your One-Time Password (OTP) is:</p>'
    . '<p style="font-size:24px;font-weight:700;letter-spacing:4px;margin:10px 0">' . htmlspecialchars($code) . '</p>'
    . '<p>This code expires in ' . (int)floor(max(30, $ttlSeconds) / 60) . ' minutes.</p>'
    . '<p>If you did not request this, you can ignore this email.</p>'
    . '</div>';
  $text = "Your OTP is: {$code}\nThis code expires in {$ttlSeconds} seconds.\n";

  try {
    $mail = tmm_mailer($db);
    $mail->clearAllRecipients();
    $mail->Subject = $subject;
    $mail->Body = $html;
    $mail->AltBody = $text;
    $mail->addAddress($email);
    $mail->send();
  } catch (Throwable $e) {
    @error_log('[TMM][OTP][mail_send] ' . $e->getMessage());
    return ['ok' => false, 'message' => 'Failed to send OTP email.', 'data' => ['otp_id' => $otpId, 'expires_in' => max(30, $ttlSeconds)]];
  }

  return ['ok' => true, 'message' => 'OTP sent.', 'data' => ['otp_id' => $otpId, 'expires_in' => max(30, $ttlSeconds)]];
}

function otp_verify(mysqli $db, string $email, string $purpose, string $code): array {
  if (!otp_ensure_schema($db)) {
    otp_log_db_error($db, 'ensure_schema');
    return ['ok' => false, 'message' => 'Verification failed.'];
  }

  $email = strtolower(trim($email));
  $purpose = trim($purpose);
  $code = preg_replace('/\D+/', '', $code);
  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    return ['ok' => false, 'message' => 'Invalid email.'];
  }
  if ($purpose === '' || strlen($code) !== 6) {
    return ['ok' => false, 'message' => 'Invalid OTP.'];
  }
  $email = otp_str_limit($email, 190);
  $purpose = otp_str_limit($purpose, 40);

  $stmt = $db->prepare("SELECT id, otp_hash, expires_at, attempts FROM email_otps WHERE email=? AND purpose=? AND consumed_at IS NULL ORDER BY id DESC LIMIT 1");
  if (!$stmt) {
    otp_log_db_error($db, 'verify_prepare');
    return ['ok' => false, 'message' => 'Verification failed.'];
  }
  $stmt->bind_param('ss', $email, $purpose);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$row) {
    return ['ok' => false, 'message' => 'OTP expired. Please resend a new OTP.'];
  }

  $otpId = (int)$row['id'];
  $expiresAt = strtotime((string)$row['expires_at']);
  $attempts = (int)($row['attempts'] ?? 0);

  if ($expiresAt !== false && $expiresAt < time()) {
    $db->query("UPDATE email_otps SET consumed_at=NOW() WHERE id=" . (int)$otpId);
    return ['ok' => false, 'message' => 'OTP expired. Please resend a new OTP.'];
  }

  $hash = (string)($row['otp_hash'] ?? '');
  $valid = ($hash !== '') && password_verify($code, $hash);

  if (!$valid) {
    $attempts++;
    $stmtU = $db->prepare("UPDATE email_otps SET attempts=?, consumed_at=IF(?>=5, NOW(), consumed_at) WHERE id=?");
    if ($stmtU) {
      $stmtU->bind_param('iii', $attempts, $attempts, $otpId);
      $stmtU->execute();
      $stmtU->close();
    }
    return ['ok' => false, 'message' => 'Invalid or expired OTP.'];
  }

  $db->query("UPDATE email_otps SET consumed_at=NOW() WHERE id=" . (int)$otpId);
  return ['ok' => true, 'message' => 'OTP verified.', 'data' => ['otp_id' => $otpId]];
}

