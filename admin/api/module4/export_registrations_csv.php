<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/export.php';
$db = db();
require_permission('reports.export');

$format = tmm_export_format();
tmm_send_export_headers($format, 'vehicle_registrations');

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
$headers = ['vehicle_id','plate_number','operator_id','vehicle_status','registration_status','orcr_no','orcr_date','created_at'];
tmm_export_from_result($format, $headers, $res, function ($r) {
  return [
    'vehicle_id' => $r['vehicle_id'] ?? '',
    'plate_number' => $r['plate_number'] ?? '',
    'operator_id' => $r['operator_id'] ?? '',
    'vehicle_status' => $r['vehicle_status'] ?? '',
    'registration_status' => $r['registration_status'] ?? '',
    'orcr_no' => $r['orcr_no'] ?? '',
    'orcr_date' => $r['orcr_date'] ?? '',
    'created_at' => $r['created_at'] ?? '',
  ];
});
