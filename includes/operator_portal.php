<?php
function operator_portal_clear_session(): void {
  unset($_SESSION['operator_user_id'], $_SESSION['operator_email'], $_SESSION['operator_plate']);
}

function operator_portal_get_setting(mysqli $db, string $key, string $default = ''): string {
  $stmt = $db->prepare("SELECT setting_value FROM app_settings WHERE setting_key=? LIMIT 1");
  if (!$stmt) return $default;
  $stmt->bind_param('s', $key);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  $v = $row ? trim((string)($row['setting_value'] ?? '')) : '';
  return $v !== '' ? $v : $default;
}

function operator_portal_session_timeout_seconds(mysqli $db): int {
  $min = (int)trim(operator_portal_get_setting($db, 'session_timeout', '30'));
  if ($min <= 0) $min = 30;
  if ($min > 1440) $min = 1440;
  return $min * 60;
}

function operator_portal_login(mysqli $db, string $plateNumber, string $email, string $password): array {
  $email = strtolower(trim($email));
  $plateNumber = strtoupper(trim($plateNumber));

  if ($email === '' || $password === '') {
    return ['ok' => false, 'message' => 'Please enter email and password.'];
  }
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    return ['ok' => false, 'message' => 'Invalid email format.'];
  }

  $stmt = $db->prepare("SELECT id, email, password_hash, status FROM operator_portal_users WHERE email=? LIMIT 1");
  if (!$stmt) return ['ok' => false, 'message' => 'Login failed.'];
  $stmt->bind_param('s', $email);
  $stmt->execute();
  $res = $stmt->get_result();
  $user = $res ? $res->fetch_assoc() : null;
  $stmt->close();

  if (!$user) return ['ok' => false, 'message' => 'Invalid operator credentials.'];
  if (($user['status'] ?? '') !== 'Active') return ['ok' => false, 'message' => 'Operator account is not active.'];
  if (!password_verify($password, (string)($user['password_hash'] ?? ''))) return ['ok' => false, 'message' => 'Invalid operator credentials.'];

  $userId = (int)$user['id'];
  $plates = [];
  $stmt = $db->prepare("SELECT plate_number FROM operator_portal_user_plates WHERE user_id=? ORDER BY plate_number ASC");
  if ($stmt) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($row = $res->fetch_assoc())) {
      $plates[] = (string)($row['plate_number'] ?? '');
    }
    $stmt->close();
  }

  if (!$plates) {
    $opId = 0;
    $stmtO = $db->prepare("SELECT id FROM operators WHERE email=? LIMIT 1");
    if ($stmtO) {
      $stmtO->bind_param('s', $email);
      $stmtO->execute();
      $rowO = $stmtO->get_result()->fetch_assoc();
      $stmtO->close();
      $opId = (int)($rowO['id'] ?? 0);
    }
    if ($opId > 0) {
      $stmtV = $db->prepare("SELECT plate_number FROM vehicles WHERE record_status='Linked' AND (current_operator_id=? OR operator_id=?) AND plate_number IS NOT NULL AND plate_number<>'' ORDER BY plate_number ASC");
      if ($stmtV) {
        $stmtV->bind_param('ii', $opId, $opId);
        $stmtV->execute();
        $resV = $stmtV->get_result();
        while ($resV && ($rowV = $resV->fetch_assoc())) {
          $p = strtoupper(trim((string)($rowV['plate_number'] ?? '')));
          if ($p === '') continue;
          $plates[] = $p;
          $stmtUp = $db->prepare("INSERT INTO operator_portal_user_plates (user_id, plate_number) VALUES (?, ?) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id)");
          if ($stmtUp) {
            $stmtUp->bind_param('is', $userId, $p);
            $stmtUp->execute();
            $stmtUp->close();
          }
        }
        $stmtV->close();
      }
    }
  }

  $selectedPlate = '';
  if ($plateNumber !== '') {
    if (!in_array($plateNumber, $plates, true)) return ['ok' => false, 'message' => 'This plate number is not assigned to your operator account.'];
    $selectedPlate = $plateNumber;
  } else if (!empty($plates[0])) {
    $selectedPlate = (string)$plates[0];
  }

  $_SESSION['operator_user_id'] = $userId;
  $_SESSION['operator_email'] = $email;
  $_SESSION['operator_plate'] = $selectedPlate;
  $_SESSION['operator_last_activity'] = time();
  return ['ok' => true, 'message' => 'Login successful.'];
}

function operator_portal_require_login(string $redirectTo): void {
  if (function_exists('session_status') && session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
  if (empty($_SESSION['operator_user_id'])) {
    header('Location: ' . $redirectTo);
    exit;
  }
  require_once __DIR__ . '/db.php';
  $db = db();
  $ttl = operator_portal_session_timeout_seconds($db);
  $now = time();
  $last = (int)($_SESSION['operator_last_activity'] ?? 0);
  if ($last > 0 && ($now - $last) > $ttl) {
    operator_portal_clear_session();
    @session_unset();
    @session_destroy();
    header('Location: ' . $redirectTo);
    exit;
  }
  $_SESSION['operator_last_activity'] = $now;
}
