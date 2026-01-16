<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
header('Content-Type: application/json');

$ticket = trim($_POST['ticket_number'] ?? '');
$plate = strtoupper(trim($_POST['vehicle_plate'] ?? ''));

if ($ticket === '' && $plate === '') {
  echo json_encode(['error' => 'Ticket number or plate required']);
  exit;
}

$stmt = $db->prepare("SELECT ticket_id, vehicle_plate, status FROM tickets WHERE ticket_number = ? OR sts_ticket_no = ? OR vehicle_plate = ? ORDER BY date_issued DESC LIMIT 1");
$stmt->bind_param('sss', $ticket, $ticket, $plate);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
  $tid = (int)$row['ticket_id'];
  $vehPlate = $row['vehicle_plate'];

  $stmtV = $db->prepare("SELECT franchise_id, coop_name FROM vehicles WHERE plate_number = ?");
  $stmtV->bind_param('s', $vehPlate);
  $stmtV->execute();
  $resV = $stmtV->get_result();
  if ($v = $resV->fetch_assoc()) {
    $coopId = null;
    if (!empty($v['coop_name'])) {
      $stmtC = $db->prepare("SELECT id FROM coops WHERE coop_name = ?");
      $stmtC->bind_param('s', $v['coop_name']);
      $stmtC->execute();
      $resC = $stmtC->get_result();
      if ($c = $resC->fetch_assoc()) { $coopId = (int)$c['id']; }
    }
    $db->query("UPDATE tickets SET status='Validated', franchise_id='" . $db->real_escape_string($v['franchise_id'] ?? '') . "', coop_id " . ($coopId ? "= $coopId" : "= NULL") . " WHERE ticket_id = $tid");
    echo json_encode(['ok' => true, 'ticket_id' => $tid, 'status' => 'Validated']);
  } else {
    $db->query("UPDATE tickets SET status='Validated' WHERE ticket_id = $tid");
    echo json_encode(['ok' => true, 'ticket_id' => $tid, 'status' => 'Validated']);
  }
} else {
  echo json_encode(['error' => 'Ticket not found']);
}
?> 
