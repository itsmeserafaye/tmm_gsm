<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
header('Content-Type: application/json');

$hasTicketNoCol = false;
$resCol = $db->query("SHOW COLUMNS FROM parking_violations LIKE 'traffic_ticket_number'");
if ($resCol && $resCol->num_rows > 0) {
  $hasTicketNoCol = true;
} else {
  $db->query("ALTER TABLE parking_violations ADD COLUMN traffic_ticket_number VARCHAR(32) NULL AFTER status");
  $db->query("ALTER TABLE parking_violations ADD INDEX idx_traffic_ticket_number (traffic_ticket_number)");
  $resCol2 = $db->query("SHOW COLUMNS FROM parking_violations LIKE 'traffic_ticket_number'");
  $hasTicketNoCol = ($resCol2 && $resCol2->num_rows > 0);
}

function tmm_current_issued_by_label() {
  $role = function_exists('current_user_role') ? current_user_role() : 'Admin';
  $role = is_string($role) ? trim($role) : 'Admin';
  if ($role === '') $role = 'Admin';
  return "Parking Enforcement ($role)";
}

function tmm_create_traffic_ticket_from_parking(mysqli $db, string $plate, ?int $parkingAreaId): array {
  $plate = strtoupper(trim($plate));
  if ($plate === '') return ['ok' => false, 'error' => 'missing_plate'];

  $areaName = '';
  if ($parkingAreaId !== null && $parkingAreaId > 0) {
    $stmtA = $db->prepare("SELECT name FROM parking_areas WHERE id=? LIMIT 1");
    if ($stmtA) {
      $stmtA->bind_param('i', $parkingAreaId);
      $stmtA->execute();
      $rowA = $stmtA->get_result()->fetch_assoc();
      $stmtA->close();
      $areaName = (string)($rowA['name'] ?? '');
    }
  }
  if ($areaName === '') $areaName = 'Parking Area';

  $violationCode = 'IP';
  $issuedAt = date('Y-m-d H:i:s');
  $issuedBy = $db->real_escape_string(tmm_current_issued_by_label());
  $location = $db->real_escape_string($areaName);
  $plateSql = $db->real_escape_string($plate);

  $franchiseId = null;
  $coopId = null;
  $status = 'Pending';
  $vehRes = $db->query("SELECT franchise_id, coop_name FROM vehicles WHERE plate_number='$plateSql' LIMIT 1");
  if ($vehRes && $vehRes->num_rows > 0) {
    $veh = $vehRes->fetch_assoc();
    $franchiseId = $veh['franchise_id'] ?? null;
    $coopName = $veh['coop_name'] ?? null;
    if ($coopName) {
      $coopNameSql = $db->real_escape_string((string)$coopName);
      $coopRes = $db->query("SELECT id FROM coops WHERE coop_name = '$coopNameSql' LIMIT 1");
      if ($coopRes && $coopRes->num_rows > 0) $coopId = (int)($coopRes->fetch_assoc()['id'] ?? 0);
    }
    $status = 'Validated';
  }

  $fine = 0.0;
  $fineRes = $db->query("SELECT fine_amount FROM violation_types WHERE violation_code='$violationCode' LIMIT 1");
  if ($fineRes && $fineRes->num_rows > 0) $fine = (float)($fineRes->fetch_assoc()['fine_amount'] ?? 0);

  $year = (int)date('Y');
  $month = date('m');
  $countRes = $db->query("SELECT COUNT(*) AS c FROM tickets WHERE YEAR(date_issued) = $year");
  $count = 1;
  if ($countRes) $count = ((int)($countRes->fetch_assoc()['c'] ?? 0)) + 1;
  $ticketNumber = sprintf("TCK-%s-%s-%04d", $year, $month, $count);

  $franchiseSqlPart = $franchiseId ? ("'" . $db->real_escape_string((string)$franchiseId) . "'") : "NULL";
  $coopSqlPart = $coopId ? (string)$coopId : "NULL";
  $statusSql = $db->real_escape_string($status);

  $ins = "INSERT INTO tickets (ticket_number, violation_code, vehicle_plate, franchise_id, coop_id, driver_name, location, fine_amount, date_issued, issued_by, issued_by_badge, status)
          VALUES ('" . $db->real_escape_string($ticketNumber) . "', '$violationCode', '$plateSql', $franchiseSqlPart, $coopSqlPart, NULL, '$location', $fine, '$issuedAt', '$issuedBy', NULL, '$statusSql')";
  $ok = $db->query($ins);
  return ['ok' => (bool)$ok, 'ticket_number' => $ticketNumber, 'status' => $status];
}

function tmm_find_existing_traffic_ticket(mysqli $db, string $plate, string $location): ?array {
  $plateSql = $db->real_escape_string($plate);
  $locSql = $db->real_escape_string($location);
  $q = "SELECT ticket_number, status FROM tickets
        WHERE vehicle_plate='$plateSql' AND violation_code='IP' AND DATE(date_issued)=CURDATE()
          AND location='$locSql' AND issued_by LIKE 'Parking Enforcement%'
        ORDER BY date_issued DESC LIMIT 1";
  $res = $db->query($q);
  if ($res && $res->num_rows > 0) {
    $r = $res->fetch_assoc();
    return ['ok' => true, 'ticket_number' => (string)($r['ticket_number'] ?? ''), 'status' => (string)($r['status'] ?? '')];
  }
  return null;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}

$parkingAreaId = isset($_POST['parking_area_id']) && $_POST['parking_area_id'] !== '' ? (int)$_POST['parking_area_id'] : null;
$plate = strtoupper(trim((string)($_POST['vehicle_plate'] ?? '')));
$penalty = (float)($_POST['penalty_amount'] ?? 0);

if ($plate === '' || $penalty <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'invalid_fields']);
  exit;
}

$violationType = 'Illegal Parking';

$areaName = '';
if ($parkingAreaId !== null && $parkingAreaId > 0) {
  $stmtA = $db->prepare("SELECT name FROM parking_areas WHERE id=? LIMIT 1");
  if ($stmtA) {
    $stmtA->bind_param('i', $parkingAreaId);
    $stmtA->execute();
    $rowA = $stmtA->get_result()->fetch_assoc();
    $stmtA->close();
    $areaName = (string)($rowA['name'] ?? '');
  }
}
if ($areaName === '') $areaName = 'Parking Area';

$stmtDup = $db->prepare("SELECT id, traffic_ticket_number FROM parking_violations
  WHERE vehicle_plate=? AND (parking_area_id <=> ?) AND violation_type=? AND created_at >= CURDATE() AND created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
  ORDER BY created_at DESC, id DESC LIMIT 1");
if ($stmtDup) {
  $stmtDup->bind_param('sis', $plate, $parkingAreaId, $violationType);
  $stmtDup->execute();
  $dup = $stmtDup->get_result()->fetch_assoc();
  $stmtDup->close();
  if ($dup && isset($dup['id'])) {
    $existingTicketNo = (string)($dup['traffic_ticket_number'] ?? '');
    $traffic = null;
    if ($existingTicketNo !== '') {
      $traffic = ['ok' => true, 'ticket_number' => $existingTicketNo];
    } else {
      $traffic = tmm_find_existing_traffic_ticket($db, $plate, $areaName);
    }
    echo json_encode(['ok' => true, 'duplicate' => true, 'violation_id' => (int)$dup['id'], 'traffic_ticket' => $traffic]);
    exit;
  }
}

$sql = "INSERT INTO parking_violations (parking_area_id, vehicle_plate, violation_type, penalty_amount, status)
        VALUES (?, ?, ?, ?, 'Unpaid')";
$stmt = $db->prepare($sql);
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmt->bind_param('issd', $parkingAreaId, $plate, $violationType, $penalty);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_insert_failed']);
  exit;
}

$violationId = (int)$db->insert_id;
$traffic = tmm_create_traffic_ticket_from_parking($db, $plate, $parkingAreaId);
if ($hasTicketNoCol && ($traffic['ok'] ?? false) && !empty($traffic['ticket_number'])) {
  $tno = (string)$traffic['ticket_number'];
  $stmtU = $db->prepare("UPDATE parking_violations SET traffic_ticket_number=? WHERE id=?");
  if ($stmtU) {
    $stmtU->bind_param('si', $tno, $violationId);
    $stmtU->execute();
    $stmtU->close();
  }
}
echo json_encode(['ok' => true, 'duplicate' => false, 'violation_id' => $violationId, 'traffic_ticket' => $traffic]);
