<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
header('Content-Type: application/json');
require_permission('module4.inspections.manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}

$scheduleId = (int)($_POST['schedule_id'] ?? 0);
$remarks = trim((string)($_POST['remarks'] ?? ''));
$remarks = substr($remarks, 0, 255);
if ($scheduleId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_schedule_id']);
  exit;
}

$hasCol = function (string $table, string $col) use ($db): bool {
  $t = $db->real_escape_string($table);
  $c = $db->real_escape_string($col);
  $r = $db->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
  return $r && $r->num_rows > 0;
};
if (!$hasCol('inspection_schedules', 'status_remarks')) {
  @$db->query("ALTER TABLE inspection_schedules ADD COLUMN status_remarks VARCHAR(255) NULL");
}

$stmt = $db->prepare("SELECT status FROM inspection_schedules WHERE schedule_id=? LIMIT 1");
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmt->bind_param('i', $scheduleId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$row) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'error' => 'schedule_not_found']);
  exit;
}
$st = (string)($row['status'] ?? '');
if ($st === 'Completed') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'cannot_cancel_completed']);
  exit;
}

$stmtU = $db->prepare("UPDATE inspection_schedules SET status='Cancelled', status_remarks=? WHERE schedule_id=?");
if (!$stmtU) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmtU->bind_param('si', $remarks, $scheduleId);
$ok = $stmtU->execute();
$stmtU->close();

if (!$ok) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'update_failed']);
  exit;
}

echo json_encode(['ok' => true, 'status' => 'Cancelled']);

