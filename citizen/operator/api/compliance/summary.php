<?php
require_once __DIR__ . '/../common.php';
$operator = trim($_GET['operator_name'] ?? '');
$coop = trim($_GET['coop_name'] ?? '');
$vehicles = [];
$params = [];
$types = '';
$sql = "SELECT plate_number, vehicle_type, operator_name, coop_name, status FROM vehicles";
if ($operator !== '' || $coop !== '') {
  $conds = [];
  if ($operator !== '') { $conds[] = "operator_name LIKE ?"; $params[] = "%$operator%"; $types .= 's'; }
  if ($coop !== '') { $conds[] = "coop_name LIKE ?"; $params[] = "%$coop%"; $types .= 's'; }
  if ($conds) { $sql .= " WHERE " . implode(' AND ', $conds); }
}
$sql .= " ORDER BY updated_at DESC LIMIT 200";
if ($params) { $stmt = $db->prepare($sql); $stmt->bind_param($types, ...$params); $stmt->execute(); $vres = $stmt->get_result(); }
else { $vres = $db->query($sql); }
while ($v = $vres->fetch_assoc()) { $vehicles[] = $v; }
$plates = array_map(function($x){ return $x['plate_number']; }, $vehicles);
$violations = [];
$renewals = [];
$expiredInspections = [];
if ($plates) {
  $in = implode(',', array_fill(0, count($plates), '?'));
  $typesIn = str_repeat('s', count($plates));
  $stmtT = $db->prepare("SELECT ticket_id, vehicle_plate, violation_code, status, date_issued FROM tickets WHERE vehicle_plate IN ($in) AND status IN ('Pending','Validated') ORDER BY date_issued DESC");
  $stmtT->bind_param($typesIn, ...$plates);
  $stmtT->execute();
  $resT = $stmtT->get_result();
  while ($t = $resT->fetch_assoc()) { $violations[] = $t; }
  $stmtR = $db->prepare("SELECT p.id, p.terminal_id, p.application_no, p.applicant_name, p.status, p.expiry_date FROM terminal_permits p WHERE p.status IN ('Approved','Pending') AND p.expiry_date IS NOT NULL AND p.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) ORDER BY p.expiry_date ASC");
  $stmtR->execute();
  $resR = $stmtR->get_result();
  while ($r = $resR->fetch_assoc()) { $renewals[] = $r; }
  $stmtI = $db->prepare("SELECT s.plate_number, MAX(s.scheduled_at) AS last_schedule FROM inspection_schedules s WHERE s.plate_number IN ($in) GROUP BY s.plate_number");
  $stmtI->bind_param($typesIn, ...$plates);
  $stmtI->execute();
  $resI = $stmtI->get_result();
  while ($row = $resI->fetch_assoc()) {
    $dt = strtotime($row['last_schedule'] ?? '');
    if ($dt && $dt < strtotime('-365 days')) { $expiredInspections[] = $row['plate_number']; }
  }
}
$summary = [
  'vehicle_count' => count($vehicles),
  'active_violations' => count($violations),
  'upcoming_renewals' => count($renewals),
  'expired_inspections' => $expiredInspections
];
json_ok(['summary' => $summary, 'vehicles' => $vehicles, 'violations' => $violations, 'renewals' => $renewals]);
