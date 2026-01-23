<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/export.php';
$db = db();
require_permission('reports.export');
$format = tmm_export_format();
tmm_send_export_headers($format, 'franchise_applications');

$q = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));

$sql = "SELECT fa.application_id,
               fa.franchise_ref_number,
               fa.operator_id,
               COALESCE(NULLIF(o.name,''), o.full_name) AS operator_name,
               r.route_id AS route_code,
               r.origin, r.destination,
               fa.vehicle_count,
               fa.representative_name,
               fa.status,
               fa.submitted_at,
               fa.endorsed_at,
               fa.approved_at
        FROM franchise_applications fa
        LEFT JOIN operators o ON o.id=fa.operator_id
        LEFT JOIN routes r ON r.id=fa.route_id";

$conds = [];
$params = [];
$types = '';

if ($q !== '') {
  $conds[] = "(fa.franchise_ref_number LIKE ? OR COALESCE(NULLIF(o.name,''), o.full_name) LIKE ? OR r.route_id LIKE ? OR r.origin LIKE ? OR r.destination LIKE ?)";
  $params[] = "%$q%";
  $params[] = "%$q%";
  $params[] = "%$q%";
  $params[] = "%$q%";
  $params[] = "%$q%";
  $types .= 'sssss';
}
if ($status !== '') {
  $conds[] = "fa.status=?";
  $params[] = $status;
  $types .= 's';
}
if ($conds) $sql .= " WHERE " . implode(" AND ", $conds);
$sql .= " ORDER BY fa.submitted_at DESC";

if ($params) {
  $stmt = $db->prepare($sql);
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();
} else {
  $res = $db->query($sql);
}

$headers = ['application_id','franchise_ref_number','operator_id','operator_name','route_code','origin','destination','vehicle_count','representative_name','status','submitted_at','endorsed_at','approved_at'];
tmm_export_from_result($format, $headers, $res, function ($r) {
  return [
    'application_id' => $r['application_id'] ?? '',
    'franchise_ref_number' => $r['franchise_ref_number'] ?? '',
    'operator_id' => $r['operator_id'] ?? '',
    'operator_name' => $r['operator_name'] ?? '',
    'route_code' => $r['route_code'] ?? '',
    'origin' => $r['origin'] ?? '',
    'destination' => $r['destination'] ?? '',
    'vehicle_count' => $r['vehicle_count'] ?? '',
    'representative_name' => $r['representative_name'] ?? '',
    'status' => $r['status'] ?? '',
    'submitted_at' => $r['submitted_at'] ?? '',
    'endorsed_at' => $r['endorsed_at'] ?? '',
    'approved_at' => $r['approved_at'] ?? '',
  ];
});
