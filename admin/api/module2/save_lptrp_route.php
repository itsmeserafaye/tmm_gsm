<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/lptrp.php';
header('Content-Type: application/json');

$db = db();
require_permission('module2.franchises.manage');

function tmm_has_col(mysqli $db, string $table, string $col): bool {
  $stmt = $db->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1");
  if (!$stmt) return false;
  $stmt->bind_param('ss', $table, $col);
  $stmt->execute();
  $ok = (bool)$stmt->get_result()->fetch_row();
  $stmt->close();
  return $ok;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}

$routeCode = trim((string)($_POST['route_code'] ?? ''));
$routeName = trim((string)($_POST['route_name'] ?? ''));
$description = trim((string)($_POST['description'] ?? ($_POST['route_description'] ?? '')));
$descValue = $description !== '' ? $description : $routeName;
$startPoint = trim((string)($_POST['start_point'] ?? ''));
$endPoint = trim((string)($_POST['end_point'] ?? ''));
$maxCap = (int)($_POST['max_vehicle_capacity'] ?? 0);
$approval = trim((string)($_POST['approval_status'] ?? ($_POST['status'] ?? 'Approved')));
if ($maxCap < 0) $maxCap = 0;
if ($routeCode === '' || strlen($routeCode) < 3) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_route_code']);
  exit;
}
if ($approval === '') $approval = 'Approved';

$hasRouteName = tmm_has_col($db, 'lptrp_routes', 'route_name');
$hasDescription = tmm_has_col($db, 'lptrp_routes', 'description');
$hasApproval = tmm_has_col($db, 'lptrp_routes', 'approval_status');
$hasStatus = tmm_has_col($db, 'lptrp_routes', 'status');

$stmt = $db->prepare("SELECT id FROM lptrp_routes WHERE route_code=? LIMIT 1");
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmt->bind_param('s', $routeCode);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$existingId = (int)($row['id'] ?? 0);

if ($existingId > 0) {
  $sets = [];
  $types = '';
  $params = [];
  if ($hasRouteName) { $sets[] = "route_name=?"; $types .= 's'; $params[] = $routeName; }
  if ($hasDescription) { $sets[] = "description=?"; $types .= 's'; $params[] = $descValue; }
  $sets[] = "start_point=?"; $types .= 's'; $params[] = $startPoint;
  $sets[] = "end_point=?"; $types .= 's'; $params[] = $endPoint;
  $sets[] = "max_vehicle_capacity=?"; $types .= 'i'; $params[] = $maxCap;
  if ($hasApproval) { $sets[] = "approval_status=?"; $types .= 's'; $params[] = $approval; }
  if ($hasStatus) { $sets[] = "status=?"; $types .= 's'; $params[] = $approval; }

  $types .= 'i';
  $params[] = $existingId;
  $stmtU = $db->prepare("UPDATE lptrp_routes SET " . implode(', ', $sets) . " WHERE id=?");
  if (!$stmtU) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
    exit;
  }
  $stmtU->bind_param($types, ...$params);
  $ok = $stmtU->execute();
  $stmtU->close();
  if ($ok) {
    tmm_sync_routes_from_lptrp($db, $routeCode);
  }
  echo json_encode(['ok' => (bool)$ok, 'id' => $existingId, 'mode' => 'updated']);
  exit;
}

$cols = ['route_code'];
$vals = ['?'];
$types = 's';
$params = [$routeCode];
if ($hasRouteName) { $cols[] = 'route_name'; $vals[] = '?'; $types .= 's'; $params[] = $routeName; }
if ($hasDescription) { $cols[] = 'description'; $vals[] = '?'; $types .= 's'; $params[] = $descValue; }
$cols[] = 'start_point'; $vals[] = '?'; $types .= 's'; $params[] = $startPoint;
$cols[] = 'end_point'; $vals[] = '?'; $types .= 's'; $params[] = $endPoint;
$cols[] = 'max_vehicle_capacity'; $vals[] = '?'; $types .= 'i'; $params[] = $maxCap;
if (tmm_has_col($db, 'lptrp_routes', 'current_vehicle_count')) { $cols[] = 'current_vehicle_count'; $vals[] = '0'; }
if ($hasApproval) { $cols[] = 'approval_status'; $vals[] = '?'; $types .= 's'; $params[] = $approval; }
if ($hasStatus) { $cols[] = 'status'; $vals[] = '?'; $types .= 's'; $params[] = $approval; }

$stmtI = $db->prepare("INSERT INTO lptrp_routes (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")");
if (!$stmtI) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmtI->bind_param($types, ...$params);
$ok = $stmtI->execute();
$id = (int)$db->insert_id;
$stmtI->close();
echo json_encode(['ok' => (bool)$ok, 'id' => $id, 'mode' => 'inserted']);

if ($ok) {
  tmm_sync_routes_from_lptrp($db, $routeCode);
}
