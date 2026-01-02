<?php
require_once __DIR__ . '/../common.php';
$plate = strtoupper(trim($_GET['plate_number'] ?? ''));
$operator = trim($_GET['operator_name'] ?? '');
$params = [];
$types = '';
$sql = "SELECT schedule_id, plate_number, scheduled_at, location, inspector_id, status, cr_verified, or_verified FROM inspection_schedules";
if ($plate !== '') { $sql .= " WHERE plate_number=?"; $params[] = $plate; $types .= 's'; }
if ($operator !== '') {
  $sql = "SELECT s.schedule_id, s.plate_number, s.scheduled_at, s.location, s.inspector_id, s.status, s.cr_verified, s.or_verified 
          FROM inspection_schedules s 
          JOIN vehicles v ON s.plate_number=v.plate_number 
          WHERE v.operator_name LIKE ?";
  $params[] = "%$operator%"; $types .= 's';
}
$sql .= " ORDER BY scheduled_at DESC LIMIT 50";
if ($params) { $stmt = $db->prepare($sql); $stmt->bind_param($types, ...$params); $stmt->execute(); $res = $stmt->get_result(); }
else { $res = $db->query($sql); }
$rows = [];
while ($r = $res->fetch_assoc()) { $rows[] = $r; }
json_ok(['items' => $rows]);
