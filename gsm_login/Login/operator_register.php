<?php
if (function_exists('session_status') && session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

require_once __DIR__ . '/../../admin/includes/db.php';
require_once __DIR__ . '/../../includes/recaptcha.php';
require_once __DIR__ . '/../../includes/operator_portal.php';
require_once __DIR__ . '/../../includes/otp.php';

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

// Support both JSON and multipart/form-data
$ctype = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
$input = [];
if (strpos($ctype, 'application/json') !== false) {
  $raw = file_get_contents('php://input');
  $input = json_decode($raw, true);
  if (!is_array($input)) $input = [];
}

// Prefer POST when multipart is used
$operatorType = trim((string)($_POST['operator_type'] ?? ($input['operator_type'] ?? '')));
$fullName = trim((string)($_POST['operator_name'] ?? ($input['operator_name'] ?? ($input['full_name'] ?? ''))));
$email = strtolower(trim((string)($_POST['email'] ?? ($input['email'] ?? ''))));
$password = (string)($_POST['password'] ?? ($input['password'] ?? ''));
$confirmPassword = (string)($_POST['confirm_password'] ?? ($input['confirm_password'] ?? ($input['confirmPassword'] ?? '')));
$contactInfo = trim((string)($_POST['contact_number'] ?? ($input['contact_number'] ?? ($input['contact_info'] ?? ''))));
$association = trim((string)($_POST['association_name'] ?? ($input['association_name'] ?? '')));
$agreeTerms = (isset($_POST['agree_terms']) ? true : (bool)($input['agree_terms'] ?? false));
$deviceId = trim((string)($_POST['device_id'] ?? ($input['device_id'] ?? '')));

if ($email === '' || $password === '' || $confirmPassword === '') {
  opreg_send(false, 'Please complete email and password.', null, 400);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  opreg_send(false, 'Invalid email format.', null, 400);
}
if ($password !== $confirmPassword) {
  opreg_send(false, 'Passwords do not match.', null, 400);
}
if (!$agreeTerms) {
  opreg_send(false, 'You must agree to the Terms to register.', null, 400);
}
$allowedTypes = ['Individual', 'Coop', 'Corp'];
if ($operatorType === '') $operatorType = 'Individual';
if (!in_array($operatorType, $allowedTypes, true)) {
  opreg_send(false, 'Invalid operator type.', null, 400);
}
$minLen = (int)$setting('password_min_length', '10');
if ($minLen < 6) $minLen = 6;
if ($minLen > 32) $minLen = 32;
if (strlen($password) < $minLen) {
  opreg_send(false, 'Password must be at least ' . $minLen . ' characters.', null, 400);
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
  opreg_send(false, 'Password does not meet requirements: ' . implode(', ', $missing) . '.', null, 400);
}

$cfg = recaptcha_config($db);
$siteKey = (string)($cfg['site_key'] ?? '');
$secretKey = (string)($cfg['secret_key'] ?? '');
$recaptchaConfigured = ($siteKey !== '');
if ($recaptchaConfigured) {
  if ($secretKey === '') opreg_send(false, 'reCAPTCHA is not fully configured on the server.', null, 500);
$token = trim((string)($_POST['recaptcha_token'] ?? ($input['recaptcha_token'] ?? '')));
  if ($token === '') opreg_send(false, 'Please complete the reCAPTCHA.', null, 400);
  $verify = recaptcha_verify($secretKey, $token, (string)($_SERVER['REMOTE_ADDR'] ?? ''));
  if (!($verify['ok'] ?? false)) opreg_send(false, 'reCAPTCHA verification failed.', ['recaptcha' => $verify], 400);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
if ($hash === false) opreg_send(false, 'Registration failed.', null, 500);

$status = 'Inactive';
$approvalStatus = 'Pending';
$termsAcceptedAt = date('Y-m-d H:i:s');
$stmt = $db->prepare("INSERT INTO operator_portal_users(email, password_hash, full_name, contact_info, association_name, operator_type, approval_status, terms_accepted_at, status) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?)");
if (!$stmt) opreg_send(false, 'Registration failed.', null, 500);
$stmt->bind_param('sssssssss', $email, $hash, $fullName, $contactInfo, $association, $operatorType, $approvalStatus, $termsAcceptedAt, $status);
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

// Optional: handle document uploads during registration
try {
  $allowedDocKeysByType = [
    'Individual' => ['valid_id','declared_fleet','proof_of_address','nbi_clearance','authorization_letter'],
    'Coop' => ['cda_registration','cda_good_standing','board_resolution','declared_fleet','list_of_members','articles_of_cooperation'],
    'Corp' => ['sec_registration','articles_incorporation','board_resolution','declared_fleet','mayors_permit','business_permit'],
  ];
  $allowedKeys = $allowedDocKeysByType[$operatorType] ?? ['valid_id'];
  $targetDir = __DIR__ . '/../../citizen/operator/uploads/';
  if (!is_dir($targetDir)) @mkdir($targetDir, 0777, true);
  $saved = [];
  foreach ($_FILES as $docKey => $file) {
    $docKey = (string)$docKey;
    if (!in_array($docKey, $allowedKeys, true)) continue;
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
    if ((int)($file['size'] ?? 0) > (5 * 1024 * 1024)) continue;
    $original = (string)($file['name'] ?? '');
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $allowedExt = ['jpg','jpeg','png','pdf','csv','xlsx','xls'];
    if ($ext === '' || !in_array($ext, $allowedExt, true)) continue;
    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) continue;
    $filename = 'reg_' . $docKey . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = $targetDir . $filename;
    if (!@move_uploaded_file($tmp, $dest)) continue;
    // optional security scan
    if (function_exists('tmm_scan_file_for_viruses') && !tmm_scan_file_for_viruses($dest)) {
      @unlink($dest);
      continue;
    }
    $relPath = 'uploads/' . $filename;
    $stmt = $db->prepare("INSERT INTO operator_portal_documents(user_id, doc_key, file_path, status, remarks, reviewed_at, reviewed_by)
                          VALUES(?, ?, ?, 'Pending', NULL, NULL, NULL)
                          ON DUPLICATE KEY UPDATE file_path=VALUES(file_path), status='Pending', remarks=NULL, reviewed_at=NULL, reviewed_by=NULL");
    if ($stmt) {
      $stmt->bind_param('iss', $userId, $docKey, $relPath);
      $stmt->execute();
      $stmt->close();
      $saved[] = $docKey;
    }
  }
  if ($saved) {
    $now = date('Y-m-d H:i:s');
    $stmtU = $db->prepare("UPDATE operator_portal_users SET verification_submitted_at=? WHERE id=?");
    if ($stmtU) {
      $stmtU->bind_param('si', $now, $userId);
      $stmtU->execute();
      $stmtU->close();
    }
  }
} catch (Throwable $e) {
  // ignore upload errors during registration
}

opreg_send(true, 'Registration submitted. An admin will review and activate your account. You will receive an email once approved.', [
  'registered' => true,
  'otp_required' => false,
  'recaptcha_configured' => $recaptchaConfigured,
  'redirect' => (string)(rtrim((string)dirname(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/gsm_login/Login/operator_register.php'))), '/') . '/gsm_login/index.php?mode=operator')
]);
