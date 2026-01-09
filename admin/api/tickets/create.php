<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
header('Content-Type: application/json');

$violation = trim($_POST['violation_code'] ?? '');
$plate = strtoupper(trim($_POST['vehicle_plate'] ?? ''));
$driver = trim($_POST['driver_name'] ?? '');
$location = trim($_POST['location'] ?? '');
$officer_id = isset($_POST['officer_id']) ? (int)$_POST['officer_id'] : null;
$issued_by = 'Officer';
$issued_by_badge = null;
$officer_id_safe = null;
$stmtO = null;
if ($officer_id) {
  $stmtO = $db->prepare("SELECT name, badge_no FROM officers WHERE officer_id = ?");
  $stmtO->bind_param('i', $officer_id);
  $stmtO->execute();
  $resO = $stmtO->get_result();
  if ($o = $resO->fetch_assoc()) {
    $issued_by = $o['name'] ?? 'Officer';
    $issued_by_badge = $o['badge_no'] ?? null;
    $officer_id_safe = $officer_id;
  }
}
$date_issued = trim($_POST['date_issued'] ?? '');

if ($violation === '' || $plate === '') {
  echo json_encode(['error' => 'Violation code and vehicle plate are required']);
  exit;
}

$fine = 0.00;
$stmtV = $db->prepare("SELECT fine_amount FROM violation_types WHERE violation_code = ?");
$stmtV->bind_param('s', $violation);
$stmtV->execute();
$resV = $stmtV->get_result();
if ($rowV = $resV->fetch_assoc()) { $fine = (float)$rowV['fine_amount']; }

$due = date('Y-m-d', strtotime('+7 days'));
if ($date_issued !== '') {
  $due = date('Y-m-d', strtotime($date_issued . ' +7 days'));
}

$tmpTicketNo = 'TMP-' . uniqid();
$stmtIns = $db->prepare("INSERT INTO tickets (ticket_number, violation_code, vehicle_plate, driver_name, issued_by, issued_by_badge, officer_id, fine_amount, due_date, location, status, date_issued) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?)");
$stmtIns->bind_param('ssssssidsss', $tmpTicketNo, $violation, $plate, $driver, $issued_by, $issued_by_badge, $officer_id_safe, $fine, $due, $location, $date_issued === '' ? date('Y-m-d H:i:s') : $date_issued);

if ($stmtIns->execute()) {
  $id = $db->insert_id;
  $ticketNo = 'TCK-' . date('Y') . '-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT);
  $db->query("UPDATE tickets SET ticket_number = '" . $db->real_escape_string($ticketNo) . "' WHERE ticket_id = " . (int)$id);
  $franchiseId = null;
  $stmtVeh = $db->prepare("SELECT franchise_id, status FROM vehicles WHERE plate_number=?");
  $stmtVeh->bind_param('s', $plate);
  $stmtVeh->execute();
  $veh = $stmtVeh->get_result()->fetch_assoc();
  if ($veh) { $franchiseId = $veh['franchise_id'] ?? null; }
  $stmtCS = $db->prepare("INSERT INTO compliance_summary(vehicle_plate, franchise_id, violation_count, last_violation_date, compliance_status) VALUES (?, ?, 1, CURDATE(), 'Normal') ON DUPLICATE KEY UPDATE violation_count=violation_count+1, last_violation_date=CURDATE()");
  $stmtCS->bind_param('ss', $plate, $franchiseId);
  $stmtCS->execute();
  $stmtUC = $db->prepare("SELECT COUNT(*) AS c FROM tickets WHERE vehicle_plate=? AND status IN ('Pending','Validated','Escalated')");
  $stmtUC->bind_param('s', $plate);
  $stmtUC->execute();
  $cnt = (int)($stmtUC->get_result()->fetch_assoc()['c'] ?? 0);
  if ($cnt >= 3) {
    $stmtSV = $db->prepare("UPDATE vehicles SET status='Suspended' WHERE plate_number=? AND status<>'Suspended'");
    $stmtSV->bind_param('s', $plate);
    $stmtSV->execute();
    $stmtSC = $db->prepare("UPDATE compliance_summary SET compliance_status='Suspended' WHERE vehicle_plate=?");
    $stmtSC->bind_param('s', $plate);
    $stmtSC->execute();
  } else {
    $stmtNC = $db->prepare("UPDATE compliance_summary SET compliance_status='Normal' WHERE vehicle_plate=? AND compliance_status<>'Normal'");
    $stmtNC->bind_param('s', $plate);
    $stmtNC->execute();
  }
  echo json_encode(['ok' => true, 'ticket_number' => $ticketNo, 'ticket_id' => $id]);
} else {
  echo json_encode(['error' => 'Failed to create ticket']);
}
?> 
