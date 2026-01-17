<?php
if (function_exists('session_status') && session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

require_once __DIR__ . '/../../admin/includes/db.php';
require_once __DIR__ . '/../../includes/rbac.php';
require_once __DIR__ . '/../../includes/operator_portal.php';
require_once __DIR__ . '/../../includes/otp.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

function pr_send(bool $ok, string $message, $data = null, int $httpCode = 200): void {
  http_response_code($httpCode);
  echo json_encode([
    'ok' => $ok,
    'message' => $message,
    'data' => $data,
    'timestamp' => date('Y-m-d H:i:s'),
  ]);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  pr_send(false, 'Method not allowed', null, 405);
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) pr_send(false, 'Invalid JSON input', null, 400);

$action = trim((string)($input['action'] ?? ''));
$email = strtolower(trim((string)($input['email'] ?? '')));
$userType = trim((string)($input['user_type'] ?? ''));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) pr_send(false, 'Invalid email.', null, 400);
if (!in_array($userType, ['commuter', 'operator'], true)) pr_send(false, 'Invalid user type.', null, 400);
if (!in_array($action, ['request', 'confirm'], true)) pr_send(false, 'Invalid action.', null, 400);

$db = db();
rbac_ensure_schema($db);

$purpose = $userType === 'operator' ? 'password_reset_operator' : 'password_reset_commuter';

$passwordIsValid = function (string $pwd): bool {
  return strlen($pwd) >= 10
    && preg_match('/[A-Z]/', $pwd)
    && preg_match('/[a-z]/', $pwd)
    && preg_match('/\d/', $pwd)
    && preg_match('/[^A-Za-z0-9]/', $pwd);
};

if ($action === 'request') {
  if ($userType === 'operator') {
    $stmt = $db->prepare("SELECT id, status FROM operator_portal_users WHERE email=? LIMIT 1");
    if (!$stmt) pr_send(false, 'Request failed.', null, 500);
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) pr_send(false, 'Account not found.', null, 404);
    if ((string)($row['status'] ?? '') !== 'Active') pr_send(false, 'Account is not active.', null, 403);
  } else {
    $user = rbac_get_user_by_email($db, $email);
    if (!$user) pr_send(false, 'Account not found.', null, 404);
    if ((string)($user['status'] ?? '') !== 'Active') pr_send(false, 'Account is not active.', null, 403);
    $roles = rbac_get_user_roles($db, (int)$user['id']);
    if (!in_array('Commuter', $roles, true)) pr_send(false, 'Account not found.', null, 404);
  }

  $sent = otp_send($db, $email, $purpose, 180);
  if (!($sent['ok'] ?? false)) pr_send(false, (string)($sent['message'] ?? 'Failed to send OTP.'), $sent['data'] ?? null, 500);
  pr_send(true, (string)($sent['message'] ?? 'OTP sent.'), [
    'expires_in' => (int)(($sent['data']['expires_in'] ?? 180)),
  ]);
}

$code = preg_replace('/\D+/', '', (string)($input['code'] ?? ''));
$newPassword = (string)($input['new_password'] ?? '');
$confirmPassword = (string)($input['confirm_password'] ?? '');

if (strlen($code) !== 6) pr_send(false, 'Invalid OTP.', null, 400);
if ($newPassword === '' || $confirmPassword === '') pr_send(false, 'Please enter new password.', null, 400);
if ($newPassword !== $confirmPassword) pr_send(false, 'Passwords do not match.', null, 400);
if (!$passwordIsValid($newPassword)) pr_send(false, 'Password does not meet requirements.', null, 400);

$ver = otp_verify($db, $email, $purpose, $code);
if (!($ver['ok'] ?? false)) pr_send(false, (string)($ver['message'] ?? 'Invalid or expired OTP.'), null, 401);

$hash = password_hash($newPassword, PASSWORD_DEFAULT);
if ($hash === false) pr_send(false, 'Request failed.', null, 500);

if ($userType === 'operator') {
  $stmt = $db->prepare("UPDATE operator_portal_users SET password_hash=?, status='Active' WHERE email=?");
  if (!$stmt) pr_send(false, 'Request failed.', null, 500);
  $stmt->bind_param('ss', $hash, $email);
  $stmt->execute();
  $stmt->close();
  pr_send(true, 'Password updated.');
}

$user = rbac_get_user_by_email($db, $email);
if (!$user) pr_send(false, 'Account not found.', null, 404);
$roles = rbac_get_user_roles($db, (int)$user['id']);
if (!in_array('Commuter', $roles, true)) pr_send(false, 'Account not found.', null, 404);

$stmt = $db->prepare("UPDATE rbac_users SET password_hash=?, status='Active' WHERE id=?");
if (!$stmt) pr_send(false, 'Request failed.', null, 500);
$userId = (int)$user['id'];
$stmt->bind_param('si', $hash, $userId);
$stmt->execute();
$stmt->close();
pr_send(true, 'Password updated.');

