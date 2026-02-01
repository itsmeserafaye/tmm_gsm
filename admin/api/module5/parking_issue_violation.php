<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
header('Content-Type: application/json');
require_any_permission(['module5.manage_terminal','module5.parking_fees','module3.issue']);

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
  $issuedBy = tmm_current_issued_by_label();
  $location = $areaName;

  $franchiseId = null;
  $coopId = null;
  $status = 'Pending';
  $stmtVeh = $db->prepare("SELECT franchise_id, coop_name FROM vehicles WHERE plate_number=? LIMIT 1");
  if ($stmtVeh) {
    $stmtVeh->bind_param('s', $plate);
    $stmtVeh->execute();
    $veh = $stmtVeh->get_result()->fetch_assoc();
    $stmtVeh->close();
    if ($veh) {
      $franchiseId = $veh['franchise_id'] ?? null;
      $coopName = $veh['coop_name'] ?? null;
      if ($coopName) {
        $stmtCoop = $db->prepare("SELECT id FROM coops WHERE coop_name=? LIMIT 1");
        if ($stmtCoop) {
          $stmtCoop->bind_param('s', $coopName);
          $stmtCoop->execute();
          $rowCoop = $stmtCoop->get_result()->fetch_assoc();
          $stmtCoop->close();
          if ($rowCoop && isset($rowCoop['id'])) $coopId = (int)$rowCoop['id'];
        }
      }
      $status = 'Validated';
    }
  }

  $fine = 0.0;
  $stmtFine = $db->prepare("SELECT fine_amount FROM violation_types WHERE violation_code=? LIMIT 1");
  if ($stmtFine) {
    $stmtFine->bind_param('s', $violationCode);
    $stmtFine->execute();
    $rowFine = $stmtFine->get_result()->fetch_assoc();
    $stmtFine->close();
    if ($rowFine) $fine = (float)($rowFine['fine_amount'] ?? 0);
  }

  $year = (int)date('Y');
  $month = date('m');
  $count = 1;
  $stmtCount = $db->prepare("SELECT COUNT(*) AS c FROM tickets WHERE YEAR(date_issued) = ?");
  if ($stmtCount) {
    $stmtCount->bind_param('i', $year);
    $stmtCount->execute();
    $rowC = $stmtCount->get_result()->fetch_assoc();
    $stmtCount->close();
    $count = ((int)($rowC['c'] ?? 0)) + 1;
  }
  $ticketNumber = sprintf("TCK-%s-%s-%04d", $year, $month, $count);

  $stmtIns = $db->prepare("INSERT INTO tickets (ticket_number, violation_code, vehicle_plate, franchise_id, coop_id, driver_name, location, fine_amount, date_issued, issued_by, issued_by_badge, status)
          VALUES (?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, NULL, ?)");
  $ok = false;
  if ($stmtIns) {
    $fr = $franchiseId !== null ? (string)$franchiseId : null;
    $ci = $coopId !== null ? (int)$coopId : null;
    $stmtIns->bind_param('ssssisdsss', $ticketNumber, $violationCode, $plate, $fr, $ci, $location, $fine, $issuedAt, $issuedBy, $status);
    $ok = $stmtIns->execute();
    $stmtIns->close();
  }
  return ['ok' => (bool)$ok, 'ticket_number' => $ticketNumber, 'status' => $status];
}

function tmm_find_existing_traffic_ticket(mysqli $db, string $plate, string $location): ?array {
  $like = 'Parking Enforcement%';
  $stmt = $db->prepare("SELECT ticket_number, status FROM tickets
        WHERE vehicle_plate=? AND violation_code='IP' AND DATE(date_issued)=CURDATE()
          AND location=? AND issued_by LIKE ?
        ORDER BY date_issued DESC LIMIT 1");
  if ($stmt) {
    $stmt->bind_param('sss', $plate, $location, $like);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($r) {
      return ['ok' => true, 'ticket_number' => (string)($r['ticket_number'] ?? ''), 'status' => (string)($r['status'] ?? '')];
    }
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
