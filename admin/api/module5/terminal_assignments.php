<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');
require_any_permission(['module5.manage_terminal','module5.read','module5.parking_fees']);

$terminalId = (int)($_GET['terminal_id'] ?? 0);
if ($terminalId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_terminal_id']);
  exit;
}

$colRes = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='terminal_assignments'");
$cols = [];
if ($colRes) {
  while ($c = $colRes->fetch_assoc()) {
    $cols[(string)($c['COLUMN_NAME'] ?? '')] = true;
  }
}
$plateCol = isset($cols['plate_number']) ? 'plate_number' : (isset($cols['plate_no']) ? 'plate_no' : (isset($cols['plate']) ? 'plate' : ''));
$terminalIdCol = isset($cols['terminal_id']) ? 'terminal_id' : '';
$terminalNameCol = isset($cols['terminal_name']) ? 'terminal_name' : (isset($cols['terminal']) ? 'terminal' : '');
$vehicleIdCol = isset($cols['vehicle_id']) ? 'vehicle_id' : '';
$statusCol = isset($cols['status']) ? 'status' : (isset($cols['assignment_status']) ? 'assignment_status' : '');
$assignedAtCol = isset($cols['assigned_at']) ? 'assigned_at' : (isset($cols['created_at']) ? 'created_at' : '');

if ($plateCol === '' || ($terminalIdCol === '' && $terminalNameCol === '')) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'terminal_assignments_schema_not_supported']);
  exit;
}

$stmtT = $db->prepare("SELECT id, name FROM terminals WHERE id=? LIMIT 1");
if (!$stmtT) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmtT->bind_param('i', $terminalId);
$stmtT->execute();
$term = $stmtT->get_result()->fetch_assoc();
$stmtT->close();
if (!$term) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'error' => 'terminal_not_found']);
  exit;
}
$termName = (string)($term['name'] ?? '');

$joinVeh = $vehicleIdCol !== ''
  ? "LEFT JOIN vehicles v ON v.id=ta.$vehicleIdCol"
  : "LEFT JOIN vehicles v ON v.plate_number=ta.$plateCol";

$selectAssignedAt = $assignedAtCol !== '' ? "ta.$assignedAtCol" : "NULL";
$selectStatus = $statusCol !== '' ? "ta.$statusCol" : "''";

$where = $terminalIdCol !== '' ? "ta.$terminalIdCol=?" : "ta.$terminalNameCol=?";
$orderBy = $assignedAtCol !== '' ? "ta.$assignedAtCol DESC" : "ta.$plateCol ASC";

$sql = "SELECT
  ? AS terminal_id,
  ? AS terminal_name,
  ta.$plateCol AS plate_number,
  COALESCE(v.vehicle_type, '') AS vehicle_type,
  COALESCE(NULLIF(o.name,''), NULLIF(o.full_name,''), NULLIF(v.operator_name,''), '') AS operator_name,
  $selectStatus AS status,
  $selectAssignedAt AS assigned_at
FROM terminal_assignments ta
$joinVeh
LEFT JOIN operators o ON o.id=v.operator_id
WHERE $where
ORDER BY $orderBy
LIMIT 500";

$stmt = $db->prepare($sql);
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}

if ($terminalIdCol !== '') {
  $stmt->bind_param('isi', $terminalId, $termName, $terminalId);
} else {
  $stmt->bind_param('iss', $terminalId, $termName, $termName);
}

$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($res && ($r = $res->fetch_assoc())) {
  $rows[] = $r;
}
$stmt->close();

echo json_encode(['ok' => true, 'data' => $rows]);
