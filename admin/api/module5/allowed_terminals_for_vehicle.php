<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');
require_permission('module5.assign_vehicle');

$vehicleId = (int)($_GET['vehicle_id'] ?? 0);
if ($vehicleId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_vehicle_id']);
  exit;
}

$stmtV = $db->prepare("SELECT id, operator_id FROM vehicles WHERE id=? LIMIT 1");
if (!$stmtV) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmtV->bind_param('i', $vehicleId);
$stmtV->execute();
$veh = $stmtV->get_result()->fetch_assoc();
$stmtV->close();
if (!$veh) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'error' => 'vehicle_not_found']);
  exit;
}

$operatorId = (int)($veh['operator_id'] ?? 0);
if ($operatorId <= 0) {
  echo json_encode(['ok' => true, 'restricted' => false, 'terminal_ids' => []]);
  exit;
}

$hasTable = function (string $table) use ($db): bool {
  $stmt = $db->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1");
  if (!$stmt) return false;
  $stmt->bind_param('s', $table);
  $stmt->execute();
  $res = $stmt->get_result();
  $ok = (bool)($res && $res->fetch_row());
  $stmt->close();
  return $ok;
};

if (!$hasTable('franchise_applications') || !$hasTable('routes') || !$hasTable('terminal_routes') || !$hasTable('terminals')) {
  echo json_encode(['ok' => true, 'restricted' => false, 'terminal_ids' => []]);
  exit;
}

$routeDbIds = [];
$stmtF = $db->prepare("SELECT DISTINCT route_id FROM franchise_applications WHERE operator_id=? AND route_id IS NOT NULL AND route_id>0 AND status IN ('Approved','LTFRB-Approved')");
if ($stmtF) {
  $stmtF->bind_param('i', $operatorId);
  $stmtF->execute();
  $resF = $stmtF->get_result();
  while ($resF && ($r = $resF->fetch_assoc())) {
    $rid = (int)($r['route_id'] ?? 0);
    if ($rid > 0) $routeDbIds[] = $rid;
  }
  $stmtF->close();
}
if (!$routeDbIds) {
  echo json_encode(['ok' => true, 'restricted' => false, 'terminal_ids' => []]);
  exit;
}

$routeDbIds = array_values(array_unique($routeDbIds));
$idList = implode(',', array_map('intval', $routeDbIds));
$routeRefs = [];
$resR = $db->query("SELECT id, route_id, route_code FROM routes WHERE id IN ($idList)");
if ($resR) {
  while ($r = $resR->fetch_assoc()) {
    $rid = trim((string)($r['route_id'] ?? ''));
    $rcode = trim((string)($r['route_code'] ?? ''));
    if ($rid !== '') $routeRefs[] = $rid;
    if ($rcode !== '') $routeRefs[] = $rcode;
  }
}
$routeRefs = array_values(array_unique(array_filter($routeRefs, fn($x) => $x !== '')));
if (!$routeRefs) {
  echo json_encode(['ok' => true, 'restricted' => false, 'terminal_ids' => []]);
  exit;
}

$in = implode(',', array_map(function ($s) use ($db) {
  return "'" . $db->real_escape_string($s) . "'";
}, $routeRefs));

$terminalIds = [];
$resT = $db->query("SELECT DISTINCT tr.terminal_id
                    FROM terminal_routes tr
                    JOIN terminals t ON t.id=tr.terminal_id
                    WHERE tr.route_id IN ($in) AND t.type<>'Parking'
                    ORDER BY tr.terminal_id ASC
                    LIMIT 1000");
if ($resT) {
  while ($r = $resT->fetch_assoc()) {
    $tid = (int)($r['terminal_id'] ?? 0);
    if ($tid > 0) $terminalIds[] = $tid;
  }
}
$terminalIds = array_values(array_unique($terminalIds));

echo json_encode(['ok' => true, 'restricted' => true, 'terminal_ids' => $terminalIds]);
