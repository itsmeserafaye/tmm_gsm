<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
$q = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$sql = "SELECT plate_number, vehicle_type, operator_name, coop_name, franchise_id, route_id, status FROM vehicles";
$conds = [];
$params = [];
$types = '';
if ($q !== '') { $conds[] = "(plate_number LIKE ? OR operator_name LIKE ?)"; $params[] = "%$q%"; $params[] = "%$q%"; $types .= 'ss'; }
if ($status !== '') { $conds[] = "status=?"; $params[] = $status; $types .= 's'; }
if ($conds) { $sql .= " WHERE " . implode(" AND ", $conds); }
$sql .= " ORDER BY created_at DESC";
if ($params) {
  $stmt = $db->prepare($sql);
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();
} else {
  $res = $db->query($sql);
}
$rows = [];
while ($row = $res->fetch_assoc()) { $rows[] = $row; }
header('Content-Type: application/json');
echo json_encode(['ok'=>true, 'data'=>$rows]);
?> 
