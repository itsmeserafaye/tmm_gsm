<?php
if (function_exists('session_status') && session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

require_once __DIR__ . '/../../admin/includes/db.php';
require_once __DIR__ . '/../../includes/rbac.php';
require_once __DIR__ . '/../../includes/recaptcha.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

function reg_send(bool $ok, string $message, $data = null, int $httpCode = 200): void {
  http_response_code($httpCode);
  echo json_encode([
    'ok' => $ok,
    'message' => $message,
    'data' => $data,
    'timestamp' => date('Y-m-d H:i:s')
  ]);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  reg_send(false, 'Method not allowed', null, 405);
}

$db = db();
rbac_ensure_schema($db);

$db->query("CREATE TABLE IF NOT EXISTS user_profiles (
  user_id INT NOT NULL PRIMARY KEY,
  birthdate DATE DEFAULT NULL,
  mobile VARCHAR(32) DEFAULT NULL,
  address_line VARCHAR(255) DEFAULT NULL,
  house_number VARCHAR(64) DEFAULT NULL,
  street VARCHAR(128) DEFAULT NULL,
  barangay VARCHAR(128) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_user_profiles_user FOREIGN KEY (user_id) REFERENCES rbac_users(id) ON DELETE CASCADE
) ENGINE=InnoDB");

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
  reg_send(false, 'Invalid JSON input', null, 400);
}

$setting = function(string $key, string $default = '') use ($db): string {
  $stmt = $db->prepare("SELECT setting_value FROM app_settings WHERE setting_key=? LIMIT 1");
  if (!$stmt) return $default;
  $stmt->bind_param('s', $key);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  $v = $row ? trim((string)($row['setting_value'] ?? '')) : '';
  return $v !== '' ? $v : $default;
};

$firstName = trim((string)($input['firstName'] ?? ''));
$lastName = trim((string)($input['lastName'] ?? ''));
$middleName = trim((string)($input['middleName'] ?? ''));
$suffix = trim((string)($input['suffix'] ?? ''));
$birthdate = trim((string)($input['birthdate'] ?? ''));

$email = strtolower(trim((string)($input['email'] ?? ($input['regEmail'] ?? ''))));
$mobile = trim((string)($input['mobile'] ?? ''));
$addressLine = trim((string)($input['address'] ?? ''));
$houseNumber = trim((string)($input['houseNumber'] ?? ''));
$street = trim((string)($input['street'] ?? ''));
$barangay = trim((string)($input['barangay'] ?? ''));

$password = (string)($input['password'] ?? ($input['regPassword'] ?? ''));
$confirmPassword = (string)($input['confirmPassword'] ?? '');

if ($firstName === '' || $lastName === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  reg_send(false, 'Please complete the required fields.', null, 400);
}

$minLen = (int)$setting('password_min_length', '10');
if ($minLen < 6) $minLen = 6;
if ($minLen > 32) $minLen = 32;
if ($password === '' || strlen($password) < $minLen) {
  reg_send(false, 'Password must be at least ' . $minLen . ' characters.', null, 400);
}
$reqUpper = $setting('password_require_upper', '1') === '1';
$reqLower = $setting('password_require_lower', '1') === '1';
$reqNumber = $setting('password_require_number', '1') === '1';
$reqSymbol = $setting('password_require_symbol', '1') === '1';
$missing = [];
if ($reqUpper && !preg_match('/[A-Z]/', $password)) $missing[] = 'uppercase';
if ($reqLower && !preg_match('/[a-z]/', $password)) $missing[] = 'lowercase';
if ($reqNumber && !preg_match('/\d/', $password)) $missing[] = 'number';
if ($reqSymbol && !preg_match('/[^A-Za-z0-9]/', $password)) $missing[] = 'symbol';
if ($missing) {
  reg_send(false, 'Password does not meet requirements: ' . implode(', ', $missing) . '.', null, 400);
}
if ($confirmPassword !== '' && $confirmPassword !== $password) {
  reg_send(false, 'Passwords do not match.', null, 400);
}

$recaptchaToken = trim((string)($input['recaptcha_token'] ?? ''));
$cfg = recaptcha_config($db);
$siteKey = (string)($cfg['site_key'] ?? '');
$secretKey = (string)($cfg['secret_key'] ?? '');
$recaptchaConfigured = ($siteKey !== '');

if ($recaptchaConfigured && $secretKey === '') {
  reg_send(false, 'reCAPTCHA is not fully configured on the server.', ['recaptcha_configured' => false], 500);
}

if ($recaptchaConfigured) {
  if ($recaptchaToken === '') {
    reg_send(false, 'Please complete the reCAPTCHA.', null, 400);
  }
  $verify = recaptcha_verify($secretKey, $recaptchaToken, (string)($_SERVER['REMOTE_ADDR'] ?? ''));
  if (!($verify['ok'] ?? false)) {
    reg_send(false, 'reCAPTCHA verification failed.', ['recaptcha' => $verify], 400);
  }
}

$hash = password_hash($password, PASSWORD_DEFAULT);
if ($hash === false) {
  reg_send(false, 'Failed to process password.', null, 500);
}

$status = 'Inactive';
$stmt = $db->prepare("INSERT INTO rbac_users(email, password_hash, first_name, last_name, middle_name, suffix, status) VALUES(?, ?, ?, ?, ?, ?, ?)");
if (!$stmt) {
  reg_send(false, 'Registration failed.', null, 500);
}

$middle = $middleName !== '' ? $middleName : null;
$suf = $suffix !== '' ? $suffix : null;
$stmt->bind_param('sssssss', $email, $hash, $firstName, $lastName, $middle, $suf, $status);
$ok = false;
$errno = 0;
try {
  $ok = $stmt->execute();
  $errno = (int)$stmt->errno;
} catch (mysqli_sql_exception $e) {
  $ok = false;
  $errno = (int)($e->getCode());
}
$stmt->close();

if (!$ok) {
  if ($errno === 1062) {
    reg_send(false, 'Email is already registered.', null, 409);
  }
  reg_send(false, 'Registration failed.', null, 500);
}

$userId = (int)$db->insert_id;

$commuterRoleId = rbac_role_id($db, 'Commuter');
if (!$commuterRoleId) {
  rbac_seed_roles_permissions($db);
  $commuterRoleId = rbac_role_id($db, 'Commuter');
}
if ($userId > 0 && $commuterRoleId) {
  $stmtR = $db->prepare("INSERT IGNORE INTO rbac_user_roles(user_id, role_id) VALUES(?, ?)");
  if ($stmtR) {
    $stmtR->bind_param('ii', $userId, $commuterRoleId);
    $stmtR->execute();
    $stmtR->close();
  }
}

$stmtP = $db->prepare("INSERT INTO user_profiles(user_id, birthdate, mobile, address_line, house_number, street, barangay) VALUES(?, ?, ?, ?, ?, ?, ?)");
if ($stmtP) {
  $bd = $birthdate !== '' ? $birthdate : null;
  $m = $mobile !== '' ? $mobile : null;
  $al = $addressLine !== '' ? $addressLine : null;
  $hn = $houseNumber !== '' ? $houseNumber : null;
  $st = $street !== '' ? $street : null;
  $br = $barangay !== '' ? $barangay : null;
  $stmtP->bind_param('issssss', $userId, $bd, $m, $al, $hn, $st, $br);
  $stmtP->execute();
  $stmtP->close();
}

reg_send(true, 'Registration submitted. Please wait for account activation.', [
  'user_id' => $userId,
  'email' => $email,
  'status' => $status,
  'requires_activation' => true,
  'recaptcha_configured' => $recaptchaConfigured
]);
