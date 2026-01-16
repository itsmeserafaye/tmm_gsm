<?php
function operator_portal_clear_session(): void {
  unset($_SESSION['operator_user_id'], $_SESSION['operator_email'], $_SESSION['operator_plate']);
}

function operator_portal_login(mysqli $db, string $plateNumber, string $email, string $password): array {
  $plateNumber = strtoupper(trim($plateNumber));
  $email = strtolower(trim($email));

  if ($plateNumber === '' || $email === '' || $password === '') {
    return ['ok' => false, 'message' => 'Please enter plate number, email, and password.'];
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

  $stmt = $db->prepare("SELECT 1 FROM vehicles WHERE plate_number=? LIMIT 1");
  if (!$stmt) return ['ok' => false, 'message' => 'Login failed.'];
  $stmt->bind_param('s', $plateNumber);
  $stmt->execute();
  $res = $stmt->get_result();
  $exists = (bool)($res && $res->fetch_row());
  $stmt->close();
  if (!$exists) return ['ok' => false, 'message' => 'Plate number not found in the PUV database.'];

  $userId = (int)$user['id'];
  $stmt = $db->prepare("SELECT 1 FROM operator_portal_user_plates WHERE user_id=? AND plate_number=? LIMIT 1");
  if (!$stmt) return ['ok' => false, 'message' => 'Login failed.'];
  $stmt->bind_param('is', $userId, $plateNumber);
  $stmt->execute();
  $res = $stmt->get_result();
  $allowed = (bool)($res && $res->fetch_row());
  $stmt->close();

  if (!$allowed) return ['ok' => false, 'message' => 'This plate number is not assigned to your operator account.'];

  $_SESSION['operator_user_id'] = $userId;
  $_SESSION['operator_email'] = $email;
  $_SESSION['operator_plate'] = $plateNumber;
  return ['ok' => true, 'message' => 'Login successful.'];
}

function operator_portal_require_login(string $redirectTo): void {
  if (function_exists('session_status') && session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
  if (empty($_SESSION['operator_user_id'])) {
    header('Location: ' . $redirectTo);
    exit;
  }
}

