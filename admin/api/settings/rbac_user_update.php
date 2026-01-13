<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

function json_out($code, $payload) {
  http_response_code($code);
  echo json_encode($payload);
  exit;
}

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(405, ['ok' => false, 'error' => 'method_not_allowed']);
  $db = db();
  require_role(['SuperAdmin']);

  $id = (int)($_POST['id'] ?? 0);
  if ($id <= 0) json_out(400, ['ok' => false, 'error' => 'missing_id']);

  $employeeNo = trim((string)($_POST['employee_no'] ?? ''));
  $dept = trim((string)($_POST['department'] ?? ''));
  $pos = trim((string)($_POST['position_title'] ?? ''));
  $status = trim((string)($_POST['status'] ?? ''));
  if ($status !== '' && !in_array($status, ['Active','Inactive','Locked'], true)) $status = '';

  $fields = [];
  $types = '';
  $params = [];

  if (array_key_exists('employee_no', $_POST)) { $fields[] = "employee_no=?"; $types .= 's'; $params[] = $employeeNo; }
  if (array_key_exists('department', $_POST)) { $fields[] = "department=?"; $types .= 's'; $params[] = $dept; }
  if (array_key_exists('position_title', $_POST)) { $fields[] = "position_title=?"; $types .= 's'; $params[] = $pos; }
  if ($status !== '' && array_key_exists('status', $_POST)) { $fields[] = "status=?"; $types .= 's'; $params[] = $status; }

  if (!$fields) json_out(400, ['ok' => false, 'error' => 'no_fields']);

  $sql = "UPDATE rbac_users SET " . implode(', ', $fields) . " WHERE id=?";
  $types .= 'i';
  $params[] = $id;

  $stmt = $db->prepare($sql);
  if (!$stmt) json_out(500, ['ok' => false, 'error' => 'db_prepare_failed']);
  $stmt->bind_param($types, ...$params);
  $ok = $stmt->execute();
  $stmt->close();

  json_out(200, ['ok' => (bool)$ok]);
} catch (Exception $e) {
  if (defined('TMM_TEST')) throw $e;
  json_out(400, ['ok' => false, 'error' => $e->getMessage()]);
}

