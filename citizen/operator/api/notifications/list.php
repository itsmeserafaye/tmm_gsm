<?php
require_once __DIR__ . '/../common.php';
$operator = trim($_GET['operator_name'] ?? '');
$plate = strtoupper(trim($_GET['plate_number'] ?? ''));
$items = [];
if ($plate !== '') {
  $stmtS = $db->prepare("SELECT plate_number, status FROM inspection_schedules WHERE plate_number=? AND status='Pending Verification' ORDER BY scheduled_at DESC LIMIT 5");
  $stmtS->bind_param('s', $plate);
  $stmtS->execute();
  $resS = $stmtS->get_result();
  while ($s = $resS->fetch_assoc()) { $items[] = ['type' => 'inspection', 'message' => 'Upload OR/CR for ' . $s['plate_number']]; }
  $stmtT = $db->prepare("SELECT ticket_number FROM tickets WHERE vehicle_plate=? AND status='Pending' AND date_issued <= DATE_SUB(NOW(), INTERVAL 14 DAY) ORDER BY date_issued DESC LIMIT 10");
  $stmtT->bind_param('s', $plate);
  $stmtT->execute();
  $resT = $stmtT->get_result();
  while ($t = $resT->fetch_assoc()) { $items[] = ['type' => 'violation', 'message' => 'Settle ticket ' . $t['ticket_number']]; }
}
if ($operator !== '') {
  $stmtF = $db->prepare("SELECT franchise_ref_number FROM franchise_applications fa JOIN operators o ON fa.operator_id=o.id WHERE o.full_name LIKE ? AND fa.status='Pending' AND fa.submitted_at <= DATE_SUB(NOW(), INTERVAL 7 DAY) ORDER BY fa.submitted_at DESC LIMIT 10");
  $op = "%$operator%";
  $stmtF->bind_param('s', $op);
  $stmtF->execute();
  $resF = $stmtF->get_result();
  while ($f = $resF->fetch_assoc()) { $items[] = ['type' => 'franchise', 'message' => 'Follow up franchise ' . $f['franchise_ref_number']]; }
}
$stmtP = $db->prepare("SELECT application_no, expiry_date FROM terminal_permits WHERE status IN ('Approved','Pending') AND expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) ORDER BY expiry_date ASC LIMIT 20");
$stmtP->execute();
$resP = $stmtP->get_result();
while ($p = $resP->fetch_assoc()) { $items[] = ['type' => 'renewal', 'message' => 'Renew permit ' . $p['application_no']]; }
json_ok(['items' => $items]);
