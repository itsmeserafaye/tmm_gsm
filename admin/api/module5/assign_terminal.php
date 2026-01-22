<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');
require_permission('module5.assign_vehicle');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}

$terminalId = (int)($_POST['terminal_id'] ?? 0);
$vehicleId = (int)($_POST['vehicle_id'] ?? 0);

if ($terminalId <= 0 || $vehicleId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_fields']);
  exit;
}

$stmtT = $db->prepare("SELECT id, name, capacity FROM terminals WHERE id=? LIMIT 1");
if (!$stmtT) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_prepare_failed']); exit; }
$stmtT->bind_param('i', $terminalId);
$stmtT->execute();
$term = $stmtT->get_result()->fetch_assoc();
$stmtT->close();
if (!$term) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'terminal_not_found']); exit; }

$stmtV = $db->prepare("SELECT id, plate_number, operator_id, inspection_status FROM vehicles WHERE id=? LIMIT 1");
if (!$stmtV) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_prepare_failed']); exit; }
$stmtV->bind_param('i', $vehicleId);
$stmtV->execute();
$veh = $stmtV->get_result()->fetch_assoc();
$stmtV->close();
if (!$veh) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'vehicle_not_found']); exit; }

$plate = (string)($veh['plate_number'] ?? '');
$operatorId = (int)($veh['operator_id'] ?? 0);
$inspectionStatus = (string)($veh['inspection_status'] ?? '');

if ($operatorId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'vehicle_not_linked_to_operator']);
  exit;
}

$inspOk = strcasecmp($inspectionStatus, 'Passed') === 0;
if (!$inspOk) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'inspection_not_passed']);
  exit;
}

$orcrOk = false;
$hasReg = $db->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vehicle_registrations' LIMIT 1");
if ($hasReg && $hasReg->fetch_row()) {
  $stmtR = $db->prepare("SELECT registration_status, orcr_no, orcr_date FROM vehicle_registrations WHERE vehicle_id=? LIMIT 1");
  if ($stmtR) {
    $stmtR->bind_param('i', $vehicleId);
    $stmtR->execute();
    $r = $stmtR->get_result()->fetch_assoc();
    $stmtR->close();
    $rs = (string)($r['registration_status'] ?? '');
    $orcrOk = ($r && in_array($rs, ['Registered','Recorded'], true) && trim((string)($r['orcr_no'] ?? '')) !== '' && !empty($r['orcr_date']));
  }
}
if (!$orcrOk) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'orcr_not_valid']);
  exit;
}

$frOk = false;
$hasFranchises = $db->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='franchises' LIMIT 1");
$hasFa = $db->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='franchise_applications' LIMIT 1");
if ($hasFranchises && $hasFranchises->fetch_row() && $hasFa && $hasFa->fetch_row()) {
  $stmtF = $db->prepare("SELECT f.franchise_id
                         FROM franchises f
                         JOIN franchise_applications a ON a.application_id=f.application_id
                         WHERE a.operator_id=? AND a.status IN ('Approved','LTFRB-Approved')
                           AND f.status='Active'
                           AND (f.expiry_date IS NULL OR f.expiry_date >= CURDATE())
                         LIMIT 1");
  if ($stmtF) {
    $stmtF->bind_param('i', $operatorId);
    $stmtF->execute();
    $row = $stmtF->get_result()->fetch_assoc();
    $stmtF->close();
    $frOk = (bool)$row;
  }
} else {
  $frOk = true;
}
if (!$frOk) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'franchise_not_active']);
  exit;
}

$capacity = (int)($term['capacity'] ?? 0);
if ($capacity > 0) {
  $stmtC = $db->prepare("SELECT COUNT(*) AS c FROM terminal_assignments WHERE terminal_id=?");
  if ($stmtC) {
    $stmtC->bind_param('i', $terminalId);
    $stmtC->execute();
    $c = (int)($stmtC->get_result()->fetch_assoc()['c'] ?? 0);
    $stmtC->close();
    if ($c >= $capacity) {
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'terminal_capacity_full']);
      exit;
    }
  }
}

$routeId = null;
$stmtRoute = $db->prepare("SELECT route_id FROM vehicles WHERE id=? LIMIT 1");
if ($stmtRoute) {
  $stmtRoute->bind_param('i', $vehicleId);
  $stmtRoute->execute();
  $rowR = $stmtRoute->get_result()->fetch_assoc();
  $stmtRoute->close();
  $routeId = isset($rowR['route_id']) && $rowR['route_id'] !== '' ? (string)$rowR['route_id'] : null;
}

$colTA = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='terminal_assignments'");
$taCols = [];
if ($colTA) {
  while ($c = $colTA->fetch_assoc()) {
    $taCols[(string)($c['COLUMN_NAME'] ?? '')] = true;
  }
}
$hasTerminalId = isset($taCols['terminal_id']);
$hasVehicleId = isset($taCols['vehicle_id']);
$hasRouteId = isset($taCols['route_id']);

$db->begin_transaction();
try {
  $termName = (string)($term['name'] ?? '');
  $cols = ['plate_number', 'terminal_name', 'status', 'assigned_at'];
  $vals = ['?', '?', "'Authorized'", 'NOW()'];
  $types = 'ss';
  $bind = [$plate, $termName];

  if ($hasRouteId) {
    $cols[] = 'route_id';
    $vals[] = '?';
    $types .= 's';
    $bind[] = $routeId !== null ? (string)$routeId : '';
  }
  if ($hasTerminalId) {
    $cols[] = 'terminal_id';
    $vals[] = '?';
    $types .= 'i';
    $bind[] = $terminalId;
  }
  if ($hasVehicleId) {
    $cols[] = 'vehicle_id';
    $vals[] = '?';
    $types .= 'i';
    $bind[] = $vehicleId;
  }

  $setParts = [];
  if ($hasTerminalId) $setParts[] = "terminal_id=VALUES(terminal_id)";
  $setParts[] = "terminal_name=VALUES(terminal_name)";
  if ($hasVehicleId) $setParts[] = "vehicle_id=VALUES(vehicle_id)";
  if ($hasRouteId) $setParts[] = "route_id=VALUES(route_id)";
  $setParts[] = "status='Authorized'";
  $setParts[] = "assigned_at=NOW()";

  $sql = "INSERT INTO terminal_assignments (" . implode(',', $cols) . ")
          VALUES (" . implode(',', $vals) . ")
          ON DUPLICATE KEY UPDATE " . implode(', ', $setParts);

  $stmtUp = $db->prepare($sql);
  if (!$stmtUp) throw new Exception('db_prepare_failed');
  $stmtUp->bind_param($types, ...$bind);
  if (!$stmtUp->execute()) throw new Exception('insert_failed');
  $stmtUp->close();

  $db->commit();
  echo json_encode(['ok' => true]);
} catch (Throwable $e) {
  $db->rollback();
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_error']);
}
