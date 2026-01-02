<?php
require_once __DIR__ . '/../common.php';
$ref = trim($_GET['franchise_ref'] ?? '');
$operator = trim($_GET['operator_name'] ?? '');
$conds = [];
$params = [];
$types = '';
$sql = "SELECT application_id, franchise_ref_number, operator_id, coop_id, vehicle_count, status, submitted_at FROM franchise_applications";
if ($ref !== '') { $conds[] = "franchise_ref_number=?"; $params[] = $ref; $types .= 's'; }
if ($operator !== '') { 
  $sql = "SELECT fa.application_id, fa.franchise_ref_number, fa.operator_id, fa.coop_id, fa.vehicle_count, fa.status, fa.submitted_at, o.full_name AS operator_name, c.coop_name 
          FROM franchise_applications fa 
          LEFT JOIN operators o ON fa.operator_id=o.id 
          LEFT JOIN coops c ON fa.coop_id=c.id";
  $conds[] = "o.full_name LIKE ?";
  $params[] = "%$operator%";
  $types .= 's';
}
if ($conds) { $sql .= " WHERE " . implode(' AND ', $conds); }
$sql .= " ORDER BY submitted_at DESC LIMIT 50";
if ($params) { $stmt = $db->prepare($sql); $stmt->bind_param($types, ...$params); $stmt->execute(); $res = $stmt->get_result(); }
else { $res = $db->query($sql); }
$out = [];
while ($r = $res->fetch_assoc()) { $out[] = $r; }
json_ok(['items' => $out]);
