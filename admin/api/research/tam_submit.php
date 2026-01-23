<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/util.php';

$db = db();
header('Content-Type: application/json');
require_login();
require_any_permission(['dashboard.view','module1.read','module2.read','module3.read','module4.read','module5.read']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  error_response(405, 'method_not_allowed');
}

$respondentRole = trim((string)($_POST['respondent_role'] ?? (string)($_SESSION['role'] ?? '')));
$respondentType = trim((string)($_POST['respondent_type'] ?? ''));
$moduleUsed = trim((string)($_POST['module_used'] ?? ''));
$comments = trim((string)($_POST['comments'] ?? ''));

$toInt = function ($k) {
  $v = isset($_POST[$k]) ? (int)$_POST[$k] : 0;
  return $v;
};

$fields = ['pu_1','pu_2','pu_3','pu_4','peou_1','peou_2','peou_3','peou_4'];
$vals = [];
foreach ($fields as $f) {
  $v = $toInt($f);
  if ($v < 1 || $v > 5) error_response(400, 'invalid_score', ['field' => $f]);
  $vals[$f] = $v;
}

if (strlen($comments) > 2000) $comments = substr($comments, 0, 2000);
if (strlen($respondentRole) > 64) $respondentRole = substr($respondentRole, 0, 64);
if (strlen($respondentType) > 64) $respondentType = substr($respondentType, 0, 64);
if (strlen($moduleUsed) > 64) $moduleUsed = substr($moduleUsed, 0, 64);

$stmt = $db->prepare("INSERT INTO tam_survey_responses
  (submitted_at, respondent_role, respondent_type, module_used, pu_1, pu_2, pu_3, pu_4, peou_1, peou_2, peou_3, peou_4, comments)
  VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
if (!$stmt) error_response(500, 'db_prepare_failed');
$stmt->bind_param(
  'sssiiiiiiiis',
  $respondentRole,
  $respondentType,
  $moduleUsed,
  $vals['pu_1'],
  $vals['pu_2'],
  $vals['pu_3'],
  $vals['pu_4'],
  $vals['peou_1'],
  $vals['peou_2'],
  $vals['peou_3'],
  $vals['peou_4'],
  $comments
);
$ok = $stmt->execute();
$id = (int)$db->insert_id;
$stmt->close();

if ($ok) {
  tmm_audit_event($db, 'tam.submit', 'tam_survey', (string)$id, ['module_used' => $moduleUsed, 'respondent_type' => $respondentType]);
  json_response(['ok' => true, 'id' => $id]);
}
error_response(500, 'db_error');
