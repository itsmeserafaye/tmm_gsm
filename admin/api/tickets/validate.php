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

$stmt = $db->prepare("SELECT ticket_id, vehicle_plate, status FROM tickets WHERE ticket_number = ? OR vehicle_plate = ? ORDER BY date_issued DESC LIMIT 1");
$stmt->bind_param('ss', $ticket, $plate);
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
    $frId = $v['franchise_id'] ?? null;
    $stmtCS = $db->prepare("INSERT INTO compliance_summary(vehicle_plate, franchise_id, violation_count, last_violation_date, compliance_status) VALUES (?, ?, 0, CURDATE(), 'Normal') ON DUPLICATE KEY UPDATE franchise_id=VALUES(franchise_id), last_violation_date=CURDATE()");
    $stmtCS->bind_param('ss', $vehPlate, $frId);
    $stmtCS->execute();
    $stmtUC = $db->prepare("SELECT COUNT(*) AS c FROM tickets WHERE vehicle_plate=? AND status IN ('Pending','Validated','Escalated')");
    $stmtUC->bind_param('s', $vehPlate);
    $stmtUC->execute();
    $cnt = (int)($stmtUC->get_result()->fetch_assoc()['c'] ?? 0);
    if ($cnt >= 3) {
      $stmtSV = $db->prepare("UPDATE vehicles SET status='Suspended' WHERE plate_number=? AND status<>'Suspended'");
      $stmtSV->bind_param('s', $vehPlate);
      $stmtSV->execute();
      $stmtSC = $db->prepare("UPDATE compliance_summary SET compliance_status='Suspended' WHERE vehicle_plate=?");
      $stmtSC->bind_param('s', $vehPlate);
      $stmtSC->execute();
    } else {
      $stmtNC = $db->prepare("UPDATE compliance_summary SET compliance_status='Normal' WHERE vehicle_plate=? AND compliance_status<>'Normal'");
      $stmtNC->bind_param('s', $vehPlate);
      $stmtNC->execute();
    }
    echo json_encode(['ok' => true, 'ticket_id' => $tid, 'status' => 'Validated']);
  } else {
    $db->query("UPDATE tickets SET status='Validated' WHERE ticket_id = $tid");
    $stmtUC = $db->prepare("SELECT vehicle_plate FROM tickets WHERE ticket_id=?");
    $stmtUC->bind_param('i', $tid);
    $stmtUC->execute();
    $rp = $stmtUC->get_result()->fetch_assoc();
    $p = $rp ? $rp['vehicle_plate'] : $vehPlate;
    $stmtCS = $db->prepare("INSERT INTO compliance_summary(vehicle_plate, franchise_id, violation_count, last_violation_date, compliance_status) VALUES (?, NULL, 0, CURDATE(), 'Normal') ON DUPLICATE KEY UPDATE last_violation_date=CURDATE()");
    $stmtCS->bind_param('s', $p);
    $stmtCS->execute();
    $stmtC2 = $db->prepare("SELECT COUNT(*) AS c FROM tickets WHERE vehicle_plate=? AND status IN ('Pending','Validated','Escalated')");
    $stmtC2->bind_param('s', $p);
    $stmtC2->execute();
    $cnt2 = (int)($stmtC2->get_result()->fetch_assoc()['c'] ?? 0);
    if ($cnt2 >= 3) {
      $stmtSV2 = $db->prepare("UPDATE vehicles SET status='Suspended' WHERE plate_number=? AND status<>'Suspended'");
      $stmtSV2->bind_param('s', $p);
      $stmtSV2->execute();
      $stmtSC2 = $db->prepare("UPDATE compliance_summary SET compliance_status='Suspended' WHERE vehicle_plate=?");
      $stmtSC2->bind_param('s', $p);
      $stmtSC2->execute();
    } else {
      $stmtNC2 = $db->prepare("UPDATE compliance_summary SET compliance_status='Normal' WHERE vehicle_plate=? AND compliance_status<>'Normal'");
      $stmtNC2->bind_param('s', $p);
      $stmtNC2->execute();
    }
    echo json_encode(['ok' => true, 'ticket_id' => $tid, 'status' => 'Validated']);
  }
} else {
  echo json_encode(['error' => 'Ticket not found']);
}
?> 
