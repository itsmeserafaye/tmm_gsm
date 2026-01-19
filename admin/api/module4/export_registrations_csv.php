<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
require_permission('reports.export');

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="vehicle_registrations.csv"');

$q = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));

$sql = "SELECT v.id AS vehicle_id,
               v.plate_number,
               v.operator_id,
               v.status AS vehicle_status,
               vr.registration_status,
               vr.orcr_no,
               vr.orcr_date,
               vr.created_at
        FROM vehicles v
        LEFT JOIN vehicle_registrations vr ON vr.vehicle_id=v.id";

$conds = [];
if ($q !== '') {
  $qv = $db->real_escape_string($q);
  $conds[] = "(v.plate_number LIKE '%$qv%')";
}
if ($status !== '' && in_array($status, ['Registered','Pending','Expired'], true)) {
  $sv = $db->real_escape_string($status);
  $conds[] = "vr.registration_status='$sv'";
}
if ($conds) $sql .= " WHERE " . implode(" AND ", $conds);
$sql .= " ORDER BY COALESCE(vr.created_at, v.created_at) DESC";

$res = $db->query($sql);
$out = fopen('php://output', 'w');
fputcsv($out, ['vehicle_id','plate_number','operator_id','vehicle_status','registration_status','orcr_no','orcr_date','created_at']);
while ($res && ($row = $res->fetch_assoc())) {
  fputcsv($out, [
    $row['vehicle_id'],
    $row['plate_number'],
    $row['operator_id'],
    $row['vehicle_status'],
    $row['registration_status'],
    $row['orcr_no'],
    $row['orcr_date'],
    $row['created_at'],
  ]);
}
fclose($out);

