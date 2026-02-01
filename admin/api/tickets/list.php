<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
header('Content-Type: application/json');
require_any_permission(['module3.read','module3.issue','module3.settle']);

$status = trim($_GET['status'] ?? '');
$q = trim($_GET['q'] ?? '');
$period = trim($_GET['period'] ?? '');
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
if ($limit <= 0) $limit = 100;
if ($limit > 1000) $limit = 1000;
$excludePaid = isset($_GET['exclude_paid']) && (string)$_GET['exclude_paid'] !== '' && (string)$_GET['exclude_paid'] !== '0';

$sql = "SELECT t.ticket_id, t.ticket_number, t.external_ticket_number, t.ticket_source, t.date_issued, t.violation_code, t.sts_violation_code, t.vehicle_plate, t.issued_by, t.status, t.fine_amount FROM tickets t";
$conds = [];
$params = [];
$types = '';

if ($status !== '' && in_array($status, ['Pending','Validated','Settled','Escalated'])) {
  $conds[] = "t.status = ?";
  $params[] = $status;
  $types .= 's';
}
if ($excludePaid) {
  $conds[] = "LOWER(t.status) <> 'settled'";
}
if ($q !== '') {
  $qNoDash = preg_replace('/[^A-Za-z0-9]/', '', $q);
  $conds[] = "(t.vehicle_plate LIKE ? OR REPLACE(t.vehicle_plate,'-','') LIKE ? OR t.ticket_number LIKE ? OR t.external_ticket_number LIKE ?)";
  $params[] = "%$q%";
  $params[] = "%$qNoDash%";
  $params[] = "%$q%";
  $params[] = "%$q%";
  $types .= 'ssss';
}
if ($period === '30d') { $conds[] = "t.date_issued >= DATE_SUB(NOW(), INTERVAL 30 DAY)"; }
if ($period === '90d') { $conds[] = "t.date_issued >= DATE_SUB(NOW(), INTERVAL 90 DAY)"; }
if ($period === 'ytd') { $conds[] = "YEAR(t.date_issued) = YEAR(NOW())"; }

if ($conds) { $sql .= " WHERE " . implode(" AND ", $conds); }
$sql .= " ORDER BY t.date_issued DESC LIMIT " . (int)$limit;

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
  $like = '%' . $q . '%';
  $stmtO = $db->prepare("SELECT officer_id, name, badge_no FROM officers WHERE active_status=1 AND (name LIKE ? OR badge_no LIKE ?) ORDER BY name LIMIT 10");
  if ($stmtO) {
    $stmtO->bind_param('ss', $like, $like);
    $stmtO->execute();
    $resO = $stmtO->get_result();
    if ($resO) {
      while ($o = $resO->fetch_assoc()) {
        $officers[] = [
          'officer_id' => (int)$o['officer_id'],
          'name' => $o['name'],
          'badge_no' => $o['badge_no'],
        ];
      }
    }
    $stmtO->close();
  }
}

echo json_encode(['items' => $rows, 'officers' => $officers]);
?> 
