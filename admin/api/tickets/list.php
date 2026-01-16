<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
header('Content-Type: application/json');

$status = trim($_GET['status'] ?? '');
$q = trim($_GET['q'] ?? '');
$period = trim($_GET['period'] ?? '');

$sql = "SELECT t.ticket_id, t.ticket_number, t.date_issued, t.violation_code, t.sts_violation_code, t.is_sts_violation, t.demerit_points, t.vehicle_plate, t.issued_by, t.status, t.fine_amount, t.sts_ticket_no FROM tickets t";
$conds = [];
$params = [];
$types = '';

if ($status !== '' && in_array($status, ['Pending','Validated','Settled','Escalated'])) {
  $conds[] = "t.status = ?";
  $params[] = $status;
  $types .= 's';
}
if ($q !== '') {
  $conds[] = "(t.vehicle_plate LIKE ? OR t.ticket_number LIKE ? OR t.sts_ticket_no LIKE ? OR t.sts_violation_code LIKE ? OR t.violation_code LIKE ?)";
  $params[] = "%$q%";
  $params[] = "%$q%";
  $params[] = "%$q%";
  $params[] = "%$q%";
  $params[] = "%$q%";
  $types .= 'sssss';
}
if ($period === '30d') { $conds[] = "t.date_issued >= DATE_SUB(NOW(), INTERVAL 30 DAY)"; }
if ($period === '90d') { $conds[] = "t.date_issued >= DATE_SUB(NOW(), INTERVAL 90 DAY)"; }
if ($period === 'ytd') { $conds[] = "YEAR(t.date_issued) = YEAR(NOW())"; }

if ($conds) { $sql .= " WHERE " . implode(" AND ", $conds); }
$sql .= " ORDER BY t.date_issued DESC LIMIT 100";

if ($params) {
  $stmt = $db->prepare($sql);
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();
} else {
  $res = $db->query($sql);
}

$rows = [];
while ($r = $res->fetch_assoc()) { $rows[] = $r; }

$officers = [];
if ($q !== '') {
  $like = '%' . $db->real_escape_string($q) . '%';
  $sqlO = "SELECT officer_id, name, badge_no FROM officers WHERE active_status=1 AND (name LIKE '$like' OR badge_no LIKE '$like') ORDER BY name LIMIT 10";
  $resO = $db->query($sqlO);
  if ($resO) {
    while ($o = $resO->fetch_assoc()) {
      $officers[] = [
        'officer_id' => (int)$o['officer_id'],
        'name' => $o['name'],
        'badge_no' => $o['badge_no'],
      ];
    }
  }
}

echo json_encode(['items' => $rows, 'officers' => $officers]);
?> 
