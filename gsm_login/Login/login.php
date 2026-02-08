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
require_once __DIR__ . '/../../includes/recaptcha.php';

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
$gsmRecaptchaCfg = recaptcha_config($db);
$gsmRecaptchaSiteKey = (string)($gsmRecaptchaCfg['site_key'] ?? '');
$gsmRecaptchaSecretKey = (string)($gsmRecaptchaCfg['secret_key'] ?? '');

function gsm_verify_recaptcha_or_fail(string $siteKey, string $secretKey, array $input): void {
  $siteKey = trim($siteKey);
  $secretKey = trim($secretKey);
  if ($siteKey === '') return;
  $token = trim((string)($input['recaptcha_token'] ?? ''));
  if ($token === '') {
    gsm_send(false, 'Please complete the reCAPTCHA.', null, 400);
  }
  if ($secretKey === '') {
    gsm_send(false, 'reCAPTCHA not configured', null, 500);
  }
  $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
  $v = recaptcha_verify($secretKey, $token, $ip);
  if (empty($v['ok'])) {
    gsm_send(false, 'reCAPTCHA verification failed.', null, 400);
  }
}

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

function gsm_setting(mysqli $db, string $key, string $default = ''): string
{
  $stmt = $db->prepare("SELECT setting_value FROM app_settings WHERE setting_key=? LIMIT 1");
  if (!$stmt)
    return $default;
  $stmt->bind_param('s', $key);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  $v = $row ? trim((string) ($row['setting_value'] ?? '')) : '';
  return $v !== '' ? $v : $default;
}

function gsm_setting_int(mysqli $db, string $key, int $default, int $min, int $max): int
{
  $raw = gsm_setting($db, $key, (string)$default);
  $v = (int) trim((string)$raw);
  if ($v < $min)
    $v = $min;
  if ($v > $max)
    $v = $max;
  return $v;
}

function gsm_input_bool($v): ?bool
{
  if ($v === null) return null;
  if (is_bool($v)) return $v;
  if (is_int($v) || is_float($v)) return ((int)$v) !== 0;
  $s = strtolower(trim((string)$v));
  if ($s === '') return null;
  if (in_array($s, ['1', 'true', 'yes', 'on'], true)) return true;
  if (in_array($s, ['0', 'false', 'no', 'off'], true)) return false;
  return null;
}

function gsm_cookie_bool(string $name): ?bool
{
  if (!isset($_COOKIE[$name])) return null;
  return gsm_input_bool($_COOKIE[$name]);
}

function gsm_set_cookie(string $name, string $value, int $ttlSeconds): void
{
  $exp = time() + max(60, $ttlSeconds);
  $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
  $cookie = rawurlencode($name) . '=' . rawurlencode($value)
    . '; Expires=' . gmdate('D, d M Y H:i:s', $exp) . ' GMT'
    . '; Path=/; SameSite=Lax; HttpOnly';
  if ($secure) $cookie .= '; Secure';
  header('Set-Cookie: ' . $cookie, false);
}

function gsm_effective_trust_days(int $settingDays, ?bool $trustChoice): int
{
  $settingDays = max(0, min(30, $settingDays));
  if ($settingDays <= 0) return 0;
  if ($trustChoice === true) return $settingDays;
  return 0;
}

function gsm_require_mfa(mysqli $db): bool
{
  $v = strtolower(trim(gsm_setting($db, 'require_mfa', '0')));
  return $v === '1' || $v === 'true' || $v === 'yes' || $v === 'on';
}

function gsm_require_operator_mfa(mysqli $db): bool
{
  $v = strtolower(trim(gsm_setting($db, 'require_operator_mfa', '1')));
  return $v === '1' || $v === 'true' || $v === 'yes' || $v === 'on';
}

function td_ensure_schema(mysqli $db): void
{
  $db->query("CREATE TABLE IF NOT EXISTS trusted_devices (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_type VARCHAR(20) NOT NULL,
    user_id BIGINT NOT NULL,
    device_hash VARCHAR(64) NOT NULL,
    user_agent_hash VARCHAR(64) DEFAULT NULL,
    expires_at DATETIME NOT NULL,
    last_used_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_device (user_type, user_id, device_hash),
    INDEX idx_expires (expires_at)
  ) ENGINE=InnoDB");

  $cols = $db->query("SHOW COLUMNS FROM trusted_devices LIKE 'user_agent_hash'");
  if (!$cols || $cols->num_rows === 0) {
    $db->query("ALTER TABLE trusted_devices ADD COLUMN user_agent_hash VARCHAR(64) DEFAULT NULL AFTER device_hash");
  }
}

function td_hash_device(string $deviceId): string
{
  $deviceId = trim($deviceId);
  return hash('sha256', $deviceId);
}

function td_hash_user_agent(): string
{
  $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
  return hash('sha256', $ua);
}

function td_is_trusted(mysqli $db, string $userType, int $userId, string $deviceHash, int $extendDays = 10): bool
{
  td_ensure_schema($db);
  $stmt = $db->prepare("SELECT expires_at, user_agent_hash FROM trusted_devices WHERE user_type=? AND user_id=? AND device_hash=? AND expires_at>NOW() LIMIT 1");
  if (!$stmt)
    return false;
  $stmt->bind_param('sis', $userType, $userId, $deviceHash);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!$row)
    return false;
  
  // Extend expiration and update last_used_at when device is trusted
  $stmtU = $db->prepare("UPDATE trusted_devices SET expires_at=DATE_ADD(NOW(), INTERVAL ? DAY), last_used_at=NOW() WHERE user_type=? AND user_id=? AND device_hash=? LIMIT 1");
  if ($stmtU) {
    $stmtU->bind_param('isis', $extendDays, $userType, $userId, $deviceHash);
    $stmtU->execute();
    $stmtU->close();
  }
  return true;
}

function td_trust(mysqli $db, string $userType, int $userId, string $deviceHash, int $days = 10): void
{
  td_ensure_schema($db);
  $days = max(1, $days);
  $uaHash = td_hash_user_agent();
  $stmt = $db->prepare("INSERT INTO trusted_devices(user_type, user_id, device_hash, user_agent_hash, expires_at, last_used_at)
    VALUES(?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? DAY), NOW())
    ON DUPLICATE KEY UPDATE user_agent_hash=VALUES(user_agent_hash), expires_at=DATE_ADD(NOW(), INTERVAL ? DAY), last_used_at=NOW()");
  if (!$stmt)
    return;
  $stmt->bind_param('sissii', $userType, $userId, $deviceHash, $uaHash, $days, $days);
  $stmt->execute();
  $stmt->close();
}

function td_forget(mysqli $db, string $userType, int $userId, string $deviceHash): void
{
  td_ensure_schema($db);
  $stmt = $db->prepare("DELETE FROM trusted_devices WHERE user_type=? AND user_id=? AND device_hash=?");
  if (!$stmt) return;
  $stmt->bind_param('sis', $userType, $userId, $deviceHash);
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

$trustChoice = array_key_exists('trust_device', $input) ? gsm_input_bool($input['trust_device']) : null;
$cookieTrust = gsm_cookie_bool('gsm_trust_device');
if ($trustChoice === null && $cookieTrust !== null) $trustChoice = $cookieTrust;

$action = isset($input['action']) ? (string) $input['action'] : 'login';
$email = strtolower(trim((string) ($input['email'] ?? '')));
$deviceId = trim((string) ($input['device_id'] ?? ''));
$cookieDeviceId = trim((string)($_COOKIE['gsm_device_id'] ?? ''));
if ($cookieDeviceId !== '' && strlen($cookieDeviceId) >= 12) {
  $deviceId = $cookieDeviceId;
} else if ($deviceId !== '' && strlen($deviceId) >= 12) {
  gsm_set_cookie('gsm_device_id', $deviceId, 315360000);
}

if ($action !== 'login_otp_verify' && $action !== 'login_otp_resend' && $action !== 'check_email') {
  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    gsm_send(false, 'Invalid email', null, 400);
  }
}

if ($action === 'login' || $action === 'operator_login') {
  gsm_verify_recaptcha_or_fail($gsmRecaptchaSiteKey, $gsmRecaptchaSecretKey, $input);
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
  $ttl = (int)($pending['otp_ttl'] ?? 0);
  if ($ttl <= 0) $ttl = gsm_setting_int($db, 'otp_ttl_seconds', 120, 60, 900);
  $sent = otp_send($db, $pEmail, $purpose, $ttl);
  if (!($sent['ok'] ?? false))
    gsm_send(false, (string) ($sent['message'] ?? 'Failed to send OTP.'), $sent['data'] ?? null, 500);
  gsm_send(true, (string) ($sent['message'] ?? 'OTP sent.'), [
    'expires_in' => (int) (($sent['data']['expires_in'] ?? 120)),
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

  if ($userType === 'operator_register') {
    $opUserId = (int) ($pending['operator_user_id'] ?? 0);
    if ($opUserId <= 0)
      gsm_send(false, 'Invalid OTP request.', null, 400);

    $stmtU = $db->prepare("UPDATE operator_portal_users SET status='Active', email_verified=1, email_verified_at=NOW() WHERE id=? AND email=? LIMIT 1");
    if ($stmtU) {
      $stmtU->bind_param('is', $opUserId, $pEmail);
      $stmtU->execute();
      $stmtU->close();
    }

    $trustDays = (int)($pending['trust_days'] ?? 10);
    gsm_set_cookie('gsm_trust_device', $trustDays > 0 ? '1' : '0', 31536000);
    if ($trustDays > 0) td_trust($db, 'operator', $opUserId, $deviceHash, $trustDays);

    $plate = strtoupper(trim((string) ($pending['plate_number'] ?? '')));
    if ($plate === '') {
      $stmtP = $db->prepare("SELECT plate_number FROM operator_portal_user_plates WHERE user_id=? ORDER BY plate_number ASC LIMIT 1");
      if ($stmtP) {
        $stmtP->bind_param('i', $opUserId);
        $stmtP->execute();
        $rP = $stmtP->get_result();
        $rowP = $rP ? $rP->fetch_assoc() : null;
        $stmtP->close();
        $plate = strtoupper(trim((string)($rowP['plate_number'] ?? '')));
      }
    }

    session_regenerate_id(true);
    unset($_SESSION['pending_login']);
    operator_portal_clear_session();
    $_SESSION['operator_user_id'] = $opUserId;
    $_SESSION['operator_email'] = $pEmail;
    $_SESSION['operator_plate'] = $plate;

    gsm_send(true, 'Account verified. Redirecting...', [
      'redirect' => $gsm_root_url . '/citizen/operator/index.php',
      'otp_trust_days' => $trustDays > 0 ? $trustDays : 0,
    ]);
  }

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

    $trustDays = (int)($pending['trust_days'] ?? 10);
    gsm_set_cookie('gsm_trust_device', $trustDays > 0 ? '1' : '0', 31536000);
    if ($trustDays > 0) td_trust($db, 'rbac', $userId, $deviceHash, $trustDays);

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
      'otp_trust_days' => $trustDays > 0 ? $trustDays : 0,
    ]);
  }

  if ($userType === 'operator') {
    $opUserId = (int) ($pending['operator_user_id'] ?? 0);
    $plate = strtoupper(trim((string) ($pending['plate_number'] ?? '')));
    if ($opUserId <= 0)
      gsm_send(false, 'Invalid OTP request.', null, 400);

    $trustDays = (int)($pending['trust_days'] ?? 10);
    gsm_set_cookie('gsm_trust_device', $trustDays > 0 ? '1' : '0', 31536000);
    if ($trustDays > 0) td_trust($db, 'operator', $opUserId, $deviceHash, $trustDays);

    if ($plate === '') {
      $stmtP = $db->prepare("SELECT plate_number FROM operator_portal_user_plates WHERE user_id=? ORDER BY plate_number ASC LIMIT 1");
      if ($stmtP) {
        $stmtP->bind_param('i', $opUserId);
        $stmtP->execute();
        $rP = $stmtP->get_result();
        $rowP = $rP ? $rP->fetch_assoc() : null;
        $stmtP->close();
        $plate = strtoupper(trim((string)($rowP['plate_number'] ?? '')));
      }
    }

    session_regenerate_id(true);
    unset($_SESSION['pending_login']);
    operator_portal_clear_session();
    $_SESSION['operator_user_id'] = $opUserId;
    $_SESSION['operator_email'] = $pEmail;
    $_SESSION['operator_plate'] = $plate;

    gsm_send(true, 'Login successful', [
      'redirect' => $gsm_root_url . '/citizen/operator/index.php',
      'otp_trust_days' => $trustDays > 0 ? $trustDays : 0,
    ]);
  }

  gsm_send(false, 'Invalid OTP request.', null, 400);
}

if ($action === 'operator_login') {
  $plateNumber = strtoupper(trim((string) ($input['plate_number'] ?? '')));
  $password = (string) ($input['password'] ?? '');
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
  $plateForOtp = (string)($_SESSION['operator_plate'] ?? '');
  $deviceHash = td_hash_device($deviceId);
  $mustOtp = true;
  if ($trustChoice !== null) gsm_set_cookie('gsm_trust_device', $trustChoice ? '1' : '0', 31536000);
  $trustDaysSetting = gsm_setting_int($db, 'mfa_trust_days', 10, 0, 30);
  $trustDays = gsm_effective_trust_days($trustDaysSetting, $trustChoice);
  if ($trustChoice === false && $opUserId > 0) td_forget($db, 'operator', $opUserId, $deviceHash);
  if ($trustDays > 0 && $opUserId > 0 && td_is_trusted($db, 'operator', $opUserId, $deviceHash, $trustDays)) {
    session_regenerate_id(true);
    gsm_send(true, 'Login successful', [
      'user' => [
        'email' => $email,
        'plate_number' => (string)($_SESSION['operator_plate'] ?? ''),
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
    'plate_number' => $plateForOtp,
    'device_hash' => $deviceHash,
    'trust_days' => $trustDays,
  ];
  $ttl = gsm_setting_int($db, 'otp_ttl_seconds', 120, 60, 900);
  $_SESSION['pending_login']['otp_ttl'] = $ttl;
  $sent = otp_send($db, $email, 'login_operator', $ttl);
  if (!($sent['ok'] ?? false)) {
    unset($_SESSION['pending_login']);
    gsm_send(false, (string) ($sent['message'] ?? 'Failed to send OTP.'), null, 500);
  }
  gsm_send(true, 'OTP required', [
    'otp_required' => true,
    'expires_in' => (int) (($sent['data']['expires_in'] ?? 120)),
    'otp_trust_days' => $trustDays,
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
if ($user && (($user['status'] ?? '') === 'Locked')) {
  $luRaw = trim((string)($user['locked_until'] ?? ''));
  if ($luRaw === '') {
    rbac_write_login_audit($db, (int)($user['id'] ?? 0) ?: null, $email, false);
    gsm_send(false, 'Account is locked. Please contact an administrator.', null, 423);
  }
  $lu = strtotime($luRaw);
  if ($lu !== false && $lu > time()) {
    rbac_write_login_audit($db, (int)($user['id'] ?? 0) ?: null, $email, false);
    gsm_send(false, 'Account is temporarily locked. Please try again later.', ['locked_until' => $luRaw], 423);
  }
  $uid = (int)($user['id'] ?? 0);
  if ($uid > 0) {
    $stmtU = $db->prepare("UPDATE rbac_users SET status='Active', locked_until=NULL WHERE id=?");
    if ($stmtU) {
      $stmtU->bind_param('i', $uid);
      $stmtU->execute();
      $stmtU->close();
    }
  }
  $user = rbac_get_user_by_email($db, $email);
}

if (!$user || (($user['status'] ?? '') !== 'Active') || !password_verify($password, (string) ($user['password_hash'] ?? ''))) {
  $uid = $user ? (int)($user['id'] ?? 0) : null;
  rbac_write_login_audit($db, $uid ?: null, $email, false);
  if ($user && $uid) {
    $maxAttempts = gsm_setting_int($db, 'max_login_attempts', 5, 3, 10);
    $lockMinutes = gsm_setting_int($db, 'lockout_minutes', 15, 1, 240);
    $stmtC = $db->prepare("SELECT COUNT(*) AS c FROM rbac_login_audit WHERE email=? AND ok=0 AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)");
    if ($stmtC) {
      $stmtC->bind_param('si', $email, $lockMinutes);
      $stmtC->execute();
      $row = $stmtC->get_result()->fetch_assoc();
      $stmtC->close();
      $c = (int)($row['c'] ?? 0);
      if ($c >= $maxAttempts) {
        $stmtL = $db->prepare("UPDATE rbac_users SET status='Locked', locked_until=DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id=?");
        if ($stmtL) {
          $stmtL->bind_param('ii', $lockMinutes, $uid);
          $stmtL->execute();
          $stmtL->close();
        }
        gsm_send(false, 'Too many failed attempts. Account temporarily locked.', null, 423);
      }
    }
  }
  gsm_send(false, 'Invalid email or password', null, 401);
}

$userId = (int) $user['id'];
$roles = rbac_get_user_roles($db, $userId);
$perms = rbac_get_user_permissions($db, $userId);
$primaryRole = rbac_primary_role($roles);

$deviceHash = td_hash_device($deviceId);
$mustOtp = gsm_require_mfa($db);
if ($trustChoice !== null) gsm_set_cookie('gsm_trust_device', $trustChoice ? '1' : '0', 31536000);
$trustDaysSetting = gsm_setting_int($db, 'mfa_trust_days', 10, 0, 30);
$trustDays = gsm_effective_trust_days($trustDaysSetting, $trustChoice);
  if ($trustDays > 0) $mustOtp = true;
if ($trustChoice === false) td_forget($db, 'rbac', $userId, $deviceHash);

if (!$mustOtp) {
  session_regenerate_id(true);
  unset($_SESSION['pending_login']);
  $_SESSION['user_id'] = $userId;
  $_SESSION['email'] = $user['email'];
  $_SESSION['name'] = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));
  $_SESSION['role'] = $primaryRole;
  $_SESSION['roles'] = $roles;
  $_SESSION['permissions'] = $perms;

  $stmt = $db->prepare("UPDATE rbac_users SET last_login_at=NOW(), status='Active', locked_until=NULL WHERE id=?");
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
}

if ($trustDays > 0 && td_is_trusted($db, 'rbac', $userId, $deviceHash, $trustDays)) {
  session_regenerate_id(true);
  unset($_SESSION['pending_login']);
  $_SESSION['user_id'] = $userId;
  $_SESSION['email'] = $user['email'];
  $_SESSION['name'] = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));
  $_SESSION['role'] = $primaryRole;
  $_SESSION['roles'] = $roles;
  $_SESSION['permissions'] = $perms;

  $stmt = $db->prepare("UPDATE rbac_users SET last_login_at=NOW(), status='Active', locked_until=NULL WHERE id=?");
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
}

$_SESSION['pending_login'] = [
  'created_at' => time(),
  'user_type' => 'rbac',
  'purpose' => 'login_rbac',
  'email' => $email,
  'user_id' => $userId,
  'device_hash' => $deviceHash,
  'trust_days' => $trustDays,
];
$ttl = gsm_setting_int($db, 'otp_ttl_seconds', 120, 60, 900);
$_SESSION['pending_login']['otp_ttl'] = $ttl;
$sent = otp_send($db, $email, 'login_rbac', $ttl);
if (!($sent['ok'] ?? false)) {
  unset($_SESSION['pending_login']);
  gsm_send(false, (string) ($sent['message'] ?? 'Failed to send OTP.'), null, 500);
}
gsm_send(true, 'OTP required', [
  'otp_required' => true,
  'expires_in' => (int) (($sent['data']['expires_in'] ?? 120)),
  'otp_trust_days' => $trustDays,
]);
?>
