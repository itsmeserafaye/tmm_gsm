<?php
if (function_exists('session_status') && session_status() !== PHP_SESSION_ACTIVE) {
  @session_start();
}

if (isset($_GET['logout']) && $_GET['logout'] === 'true') {
  $_SESSION = [];
  if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
      session_name(),
      '',
      time() - 42000,
      $params["path"],
      $params["domain"],
      $params["secure"],
      $params["httponly"]
    );
  }
  session_destroy();
  header("Location: ../../index.php");
  exit;
}

require_once __DIR__ . '/../../admin/includes/db.php';
require_once __DIR__ . '/../../includes/rbac.php';
require_once __DIR__ . '/../../includes/operator_portal.php';
require_once __DIR__ . '/../../includes/otp.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

$gsm_root_url = (function (): string{
  $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
  if ($script === '')
    return '';
  $root = str_replace('\\', '/', (string) dirname(dirname(dirname($script))));
  if ($root === '/' || $root === '\\' || $root === '.')
    return '';
  return rtrim($root, '/');
})();

$db = db();
rbac_ensure_schema($db);

function gsm_send($ok, $message, $data = null, $httpCode = 200)
{
  http_response_code($httpCode);
  echo json_encode([
    'ok' => $ok,
    'message' => $message,
    'data' => $data,
    'timestamp' => date('Y-m-d H:i:s')
  ]);
  exit;
}

function td_ensure_schema(mysqli $db): void
{
  $db->query("CREATE TABLE IF NOT EXISTS trusted_devices (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_type VARCHAR(20) NOT NULL,
    user_id BIGINT NOT NULL,
    device_hash VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    last_used_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_device (user_type, user_id, device_hash),
    INDEX idx_expires (expires_at)
  ) ENGINE=InnoDB");
}

function td_hash_device(string $deviceId): string
{
  $deviceId = trim($deviceId);
  return hash('sha256', $deviceId);
}

function td_is_trusted(mysqli $db, string $userType, int $userId, string $deviceHash): bool
{
  td_ensure_schema($db);
  $stmt = $db->prepare("SELECT expires_at FROM trusted_devices WHERE user_type=? AND user_id=? AND device_hash=? AND expires_at>NOW() LIMIT 1");
  if (!$stmt)
    return false;
  $stmt->bind_param('sis', $userType, $userId, $deviceHash);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!$row)
    return false;
  $stmtU = $db->prepare("UPDATE trusted_devices SET last_used_at=NOW() WHERE user_type=? AND user_id=? AND device_hash=? LIMIT 1");
  if ($stmtU) {
    $stmtU->bind_param('sis', $userType, $userId, $deviceHash);
    $stmtU->execute();
    $stmtU->close();
  }
  return true;
}

function td_trust(mysqli $db, string $userType, int $userId, string $deviceHash, int $days = 10): void
{
  td_ensure_schema($db);
  $days = max(1, $days);
  $stmt = $db->prepare("INSERT INTO trusted_devices(user_type, user_id, device_hash, expires_at, last_used_at)
    VALUES(?, ?, ?, DATE_ADD(NOW(), INTERVAL ? DAY), NOW())
    ON DUPLICATE KEY UPDATE expires_at=DATE_ADD(NOW(), INTERVAL ? DAY), last_used_at=NOW()");
  if (!$stmt)
    return;
  $stmt->bind_param('sisii', $userType, $userId, $deviceHash, $days, $days);
  $stmt->execute();
  $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  gsm_send(false, 'Method not allowed', null, 405);
}

$inputRaw = file_get_contents('php://input');
$input = json_decode($inputRaw, true);
if (!is_array($input)) {
  gsm_send(false, 'Invalid JSON input', null, 400);
}

$action = isset($input['action']) ? (string) $input['action'] : 'login';
$email = strtolower(trim((string) ($input['email'] ?? '')));
$deviceId = trim((string) ($input['device_id'] ?? ''));

if ($action !== 'login_otp_verify' && $action !== 'login_otp_resend' && $action !== 'check_email') {
  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    gsm_send(false, 'Invalid email', null, 400);
  }
}

if ($action === 'check_email') {
  $user = rbac_get_user_by_email($db, $email);
  $exists = (bool) ($user && (($user['status'] ?? '') === 'Active'));
  gsm_send(true, 'Email check completed', ['exists' => $exists]);
}

if ($action === 'login_otp_resend') {
  $pending = isset($_SESSION['pending_login']) && is_array($_SESSION['pending_login']) ? $_SESSION['pending_login'] : null;
  if (!$pending)
    gsm_send(false, 'No pending OTP request.', null, 400);
  $createdAt = (int) ($pending['created_at'] ?? 0);
  if ($createdAt <= 0 || (time() - $createdAt) > 900) {
    unset($_SESSION['pending_login']);
    gsm_send(false, 'OTP session expired. Please login again.', null, 400);
  }
  $pEmail = strtolower(trim((string) ($pending['email'] ?? '')));
  $purpose = (string) ($pending['purpose'] ?? '');
  if ($pEmail === '' || $purpose === '')
    gsm_send(false, 'No pending OTP request.', null, 400);
  $sent = otp_send($db, $pEmail, $purpose, 180);
  if (!($sent['ok'] ?? false))
    gsm_send(false, (string) ($sent['message'] ?? 'Failed to send OTP.'), $sent['data'] ?? null, 500);
  gsm_send(true, (string) ($sent['message'] ?? 'OTP sent.'), [
    'expires_in' => (int) (($sent['data']['expires_in'] ?? 180)),
  ]);
}

if ($action === 'login_otp_verify') {
  $pending = isset($_SESSION['pending_login']) && is_array($_SESSION['pending_login']) ? $_SESSION['pending_login'] : null;
  if (!$pending)
    gsm_send(false, 'No pending OTP request.', null, 400);
  $createdAt = (int) ($pending['created_at'] ?? 0);
  if ($createdAt <= 0 || (time() - $createdAt) > 900) {
    unset($_SESSION['pending_login']);
    gsm_send(false, 'OTP session expired. Please login again.', null, 400);
  }
  $pEmail = strtolower(trim((string) ($pending['email'] ?? '')));
  $purpose = (string) ($pending['purpose'] ?? '');
  $deviceHash = (string) ($pending['device_hash'] ?? '');
  $userType = (string) ($pending['user_type'] ?? '');
  $code = preg_replace('/\D+/', '', (string) ($input['code'] ?? ''));
  if ($pEmail === '' || $purpose === '' || $deviceHash === '' || $userType === '')
    gsm_send(false, 'Invalid OTP request.', null, 400);
  if (strlen($code) !== 6)
    gsm_send(false, 'Invalid OTP.', null, 400);

  $ver = otp_verify($db, $pEmail, $purpose, $code);
  if (!($ver['ok'] ?? false))
    gsm_send(false, (string) ($ver['message'] ?? 'Invalid or expired OTP.'), null, 401);

  if ($userType === 'rbac') {
    $userId = (int) ($pending['user_id'] ?? 0);
    if ($userId <= 0)
      gsm_send(false, 'Invalid OTP request.', null, 400);
    $user = rbac_get_user_by_email($db, $pEmail);
    if (!$user || (int) ($user['id'] ?? 0) !== $userId)
      gsm_send(false, 'Invalid OTP request.', null, 400);

    $roles = rbac_get_user_roles($db, $userId);
    $perms = rbac_get_user_permissions($db, $userId);
    $primaryRole = rbac_primary_role($roles);

    td_trust($db, 'rbac', $userId, $deviceHash, 10);

    session_regenerate_id(true);
    unset($_SESSION['pending_login']);
    $_SESSION['user_id'] = $userId;
    $_SESSION['email'] = $user['email'];
    $_SESSION['name'] = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));
    $_SESSION['role'] = $primaryRole;
    $_SESSION['roles'] = $roles;
    $_SESSION['permissions'] = $perms;

    $stmt = $db->prepare("UPDATE rbac_users SET last_login_at=NOW() WHERE id=?");
    if ($stmt) {
      $stmt->bind_param('i', $userId);
      $stmt->execute();
      $stmt->close();
    }
    rbac_write_login_audit($db, $userId, $pEmail, true);

    $redirect = $primaryRole === 'Commuter'
      ? ($gsm_root_url . '/citizen/commuter/index.php')
      : ($gsm_root_url . '/admin/index.php');

    gsm_send(true, 'Login successful', [
      'redirect' => $redirect,
      'otp_trust_days' => 10,
    ]);
  }

  if ($userType === 'operator') {
    $opUserId = (int) ($pending['operator_user_id'] ?? 0);
    $plate = strtoupper(trim((string) ($pending['plate_number'] ?? '')));
    if ($opUserId <= 0 || $plate === '')
      gsm_send(false, 'Invalid OTP request.', null, 400);

    td_trust($db, 'operator', $opUserId, $deviceHash, 10);

    session_regenerate_id(true);
    unset($_SESSION['pending_login']);
    operator_portal_clear_session();
    $_SESSION['operator_user_id'] = $opUserId;
    $_SESSION['operator_email'] = $pEmail;
    $_SESSION['operator_plate'] = $plate;

    gsm_send(true, 'Login successful', [
      'redirect' => $gsm_root_url . '/citizen/operator/index.php',
      'otp_trust_days' => 10,
    ]);
  }

  gsm_send(false, 'Invalid OTP request.', null, 400);
}

if ($action === 'operator_login') {
  $plateNumber = strtoupper(trim((string) ($input['plate_number'] ?? '')));
  $password = (string) ($input['password'] ?? '');
  if ($plateNumber === '')
    gsm_send(false, 'Plate number is required', null, 400);
  if ($password === '')
    gsm_send(false, 'Password is required', null, 400);
  if ($deviceId === '')
    gsm_send(false, 'Device verification failed. Please refresh and try again.', null, 400);

  unset($_SESSION['user_id'], $_SESSION['email'], $_SESSION['name'], $_SESSION['role'], $_SESSION['roles'], $_SESSION['permissions']);
  operator_portal_clear_session();
  $res = operator_portal_login($db, $plateNumber, $email, $password);
  if (!($res['ok'] ?? false)) {
    gsm_send(false, (string) ($res['message'] ?? 'Invalid operator credentials'), null, 401);
  }
  $opUserId = (int) ($_SESSION['operator_user_id'] ?? 0);
  $deviceHash = td_hash_device($deviceId);
  if ($opUserId > 0 && td_is_trusted($db, 'operator', $opUserId, $deviceHash)) {
    session_regenerate_id(true);
    gsm_send(true, 'Login successful', [
      'user' => [
        'email' => $email,
        'plate_number' => $plateNumber,
        'type' => 'operator',
      ],
      'redirect' => $gsm_root_url . '/citizen/operator/index.php'
    ]);
  }

  operator_portal_clear_session();
  $_SESSION['pending_login'] = [
    'created_at' => time(),
    'user_type' => 'operator',
    'purpose' => 'login_operator',
    'email' => $email,
    'operator_user_id' => $opUserId,
    'plate_number' => $plateNumber,
    'device_hash' => $deviceHash,
  ];
  $sent = otp_send($db, $email, 'login_operator', 180);
  if (!($sent['ok'] ?? false)) {
    unset($_SESSION['pending_login']);
    gsm_send(false, (string) ($sent['message'] ?? 'Failed to send OTP.'), null, 500);
  }
  gsm_send(true, 'OTP required', [
    'otp_required' => true,
    'expires_in' => (int) (($sent['data']['expires_in'] ?? 180)),
    'otp_trust_days' => 10,
  ]);
}

if ($action !== 'login') {
  gsm_send(false, 'Invalid action', null, 400);
}

$password = (string) ($input['password'] ?? '');
if ($password === '') {
  gsm_send(false, 'Password is required', null, 400);
}
if ($deviceId === '') {
  gsm_send(false, 'Device verification failed. Please refresh and try again.', null, 400);
}

$user = rbac_get_user_by_email($db, $email);
if (!$user || (($user['status'] ?? '') !== 'Active') || !password_verify($password, (string) ($user['password_hash'] ?? ''))) {
  rbac_write_login_audit($db, $user ? (int) $user['id'] : null, $email, false);
  gsm_send(false, 'Invalid email or password', null, 401);
}

$userId = (int) $user['id'];
$roles = rbac_get_user_roles($db, $userId);
$perms = rbac_get_user_permissions($db, $userId);
$primaryRole = rbac_primary_role($roles);

$deviceHash = td_hash_device($deviceId);
if (!td_is_trusted($db, 'rbac', $userId, $deviceHash)) {
  $_SESSION['pending_login'] = [
    'created_at' => time(),
    'user_type' => 'rbac',
    'purpose' => 'login_rbac',
    'email' => $email,
    'user_id' => $userId,
    'device_hash' => $deviceHash,
  ];
  $sent = otp_send($db, $email, 'login_rbac', 180);
  if (!($sent['ok'] ?? false)) {
    unset($_SESSION['pending_login']);
    gsm_send(false, (string) ($sent['message'] ?? 'Failed to send OTP.'), null, 500);
  }
  gsm_send(true, 'OTP required', [
    'otp_required' => true,
    'expires_in' => (int) (($sent['data']['expires_in'] ?? 180)),
    'otp_trust_days' => 10,
  ]);
}

session_regenerate_id(true);
unset($_SESSION['pending_login']);
$_SESSION['user_id'] = $userId;
$_SESSION['email'] = $user['email'];
$_SESSION['name'] = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));
$_SESSION['role'] = $primaryRole;
$_SESSION['roles'] = $roles;
$_SESSION['permissions'] = $perms;

$stmt = $db->prepare("UPDATE rbac_users SET last_login_at=NOW() WHERE id=?");
if ($stmt) {
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $stmt->close();
}

rbac_write_login_audit($db, $userId, $email, true);

$redirect = $primaryRole === 'Commuter'
  ? ($gsm_root_url . '/citizen/commuter/index.php')
  : ($gsm_root_url . '/admin/index.php');

gsm_send(true, 'Login successful', [
  'user' => [
    'id' => $userId,
    'email' => $user['email'],
    'name' => $_SESSION['name'],
    'role' => $primaryRole,
    'roles' => $roles,
    'permissions' => $perms,
  ],
  'redirect' => $redirect
]);
?>
