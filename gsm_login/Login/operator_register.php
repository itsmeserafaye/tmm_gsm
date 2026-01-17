<?php
if (function_exists('session_status') && session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

require_once __DIR__ . '/../../admin/includes/db.php';
require_once __DIR__ . '/../../includes/recaptcha.php';
require_once __DIR__ . '/../../includes/operator_portal.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

function opreg_send(bool $ok, string $message, $data = null, int $httpCode = 200): void {
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
  opreg_send(false, 'Method not allowed', null, 405);
}

$db = db();

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
  opreg_send(false, 'Invalid JSON input', null, 400);
}

$plateNumber = strtoupper(trim((string)($input['plate_number'] ?? '')));
$fullName = trim((string)($input['full_name'] ?? ''));
$email = strtolower(trim((string)($input['email'] ?? '')));
$password = (string)($input['password'] ?? '');
$confirmPassword = (string)($input['confirm_password'] ?? ($input['confirmPassword'] ?? ''));
$contactInfo = trim((string)($input['contact_info'] ?? ''));
$association = trim((string)($input['association_name'] ?? ''));

if ($plateNumber === '' || $email === '' || $password === '' || $confirmPassword === '') {
  opreg_send(false, 'Please complete plate number, email, and password.', null, 400);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  opreg_send(false, 'Invalid email format.', null, 400);
}
if ($password !== $confirmPassword) {
  opreg_send(false, 'Passwords do not match.', null, 400);
}
if (strlen($password) < 10) {
  opreg_send(false, 'Password must be at least 10 characters.', null, 400);
}
if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/\d/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
  opreg_send(false, 'Password does not meet requirements.', null, 400);
}

$cfg = recaptcha_config($db);
$siteKey = (string)($cfg['site_key'] ?? '');
$secretKey = (string)($cfg['secret_key'] ?? '');
$recaptchaConfigured = ($siteKey !== '');
if ($recaptchaConfigured) {
  if ($secretKey === '') opreg_send(false, 'reCAPTCHA is not fully configured on the server.', null, 500);
  $token = trim((string)($input['recaptcha_token'] ?? ''));
  if ($token === '') opreg_send(false, 'Please complete the reCAPTCHA.', null, 400);
  $verify = recaptcha_verify($secretKey, $token, (string)($_SERVER['REMOTE_ADDR'] ?? ''));
  if (!($verify['ok'] ?? false)) opreg_send(false, 'reCAPTCHA verification failed.', ['recaptcha' => $verify], 400);
}

$stmt = $db->prepare("SELECT 1 FROM vehicles WHERE plate_number=? LIMIT 1");
if (!$stmt) opreg_send(false, 'Registration failed.', null, 500);
$stmt->bind_param('s', $plateNumber);
$stmt->execute();
$res = $stmt->get_result();
$exists = (bool)($res && $res->fetch_row());
$stmt->close();
if (!$exists) opreg_send(false, 'Plate number not found in the PUV database.', null, 400);

$hash = password_hash($password, PASSWORD_DEFAULT);
if ($hash === false) opreg_send(false, 'Registration failed.', null, 500);

$status = 'Active';
$stmt = $db->prepare("INSERT INTO operator_portal_users(email, password_hash, full_name, contact_info, association_name, status) VALUES(?, ?, ?, ?, ?, ?)");
if (!$stmt) opreg_send(false, 'Registration failed.', null, 500);
$stmt->bind_param('ssssss', $email, $hash, $fullName, $contactInfo, $association, $status);
$ok = false;
$errno = 0;
try {
  $ok = $stmt->execute();
  $errno = (int)$stmt->errno;
} catch (mysqli_sql_exception $e) {
  $ok = false;
  $errno = (int)$e->getCode();
}
$stmt->close();

if (!$ok) {
  if ($errno === 1062) opreg_send(false, 'Email is already registered.', null, 409);
  opreg_send(false, 'Registration failed.', null, 500);
}

$userId = (int)$db->insert_id;

$stmt = $db->prepare("INSERT INTO operator_portal_user_plates(user_id, plate_number) VALUES(?, ?)");
if (!$stmt) opreg_send(false, 'Registration failed.', null, 500);
$stmt->bind_param('is', $userId, $plateNumber);
$ok = false;
$errno = 0;
try {
  $ok = $stmt->execute();
  $errno = (int)$stmt->errno;
} catch (mysqli_sql_exception $e) {
  $ok = false;
  $errno = (int)$e->getCode();
}
$stmt->close();

if (!$ok) {
  if ($errno === 1062) opreg_send(false, 'This plate number is already registered to an operator.', null, 409);
  opreg_send(false, 'Registration failed.', null, 500);
}

operator_portal_clear_session();
unset($_SESSION['user_id'], $_SESSION['email'], $_SESSION['name'], $_SESSION['role'], $_SESSION['roles'], $_SESSION['permissions']);
$login = operator_portal_login($db, $plateNumber, $email, $password);
if (!($login['ok'] ?? false)) {
  opreg_send(true, 'Registration successful. Please login as operator.', [
    'registered' => true,
    'recaptcha_configured' => $recaptchaConfigured,
    'redirect' => null
  ]);
}

opreg_send(true, 'Registration successful. Redirecting...', [
  'registered' => true,
  'recaptcha_configured' => $recaptchaConfigured,
  'redirect' => '../../citizen/operator/index.php'
]);

