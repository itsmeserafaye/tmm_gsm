<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/export.php';
$db = db();
require_permission('reports.export');

$period = strtolower(trim($_GET['period'] ?? ''));
$status = trim($_GET['status'] ?? '');
$q = trim($_GET['q'] ?? '');
$officer_id = isset($_GET['officer_id']) ? (int)$_GET['officer_id'] : 0;

$sql = "SELECT ticket_number, external_ticket_number, ticket_source, violation_code, sts_violation_code, vehicle_plate, status, fine_amount, date_issued, issued_by, issued_by_badge, due_date, payment_ref FROM tickets";
$conds = [];
if ($status !== '' && in_array($status, ['Pending','Validated','Settled','Escalated'])) { $conds[] = "status='".$db->real_escape_string($status)."'"; }
if ($period === '30d') { $conds[] = "date_issued >= DATE_SUB(NOW(), INTERVAL 30 DAY)"; }
if ($period === '90d') { $conds[] = "date_issued >= DATE_SUB(NOW(), INTERVAL 90 DAY)"; }
if ($period === 'year') { $conds[] = "YEAR(date_issued) = YEAR(NOW())"; }
if ($q !== '') { $qv = $db->real_escape_string($q); $conds[] = "(vehicle_plate LIKE '%$qv%' OR ticket_number LIKE '%$qv%' OR external_ticket_number LIKE '%$qv%')"; }
if ($officer_id > 0) { $conds[] = "officer_id=".$officer_id; }
if ($conds) { $sql .= " WHERE " . implode(" AND ", $conds); }
$sql .= " ORDER BY date_issued DESC LIMIT 1000";

$res = $db->query($sql);

$format = tmm_export_format();
$basename = 'tickets_export_' . date('Ymd_His');
tmm_send_export_headers($format, $basename);
$headers = ['ticket_number','external_ticket_number','ticket_source','violation_code','sts_violation_code','vehicle_plate','status','fine_amount','date_issued','issued_by','issued_by_badge','due_date','payment_ref'];
tmm_export_from_result($format, $headers, $res, function ($r) {
  return [
    'ticket_number' => $r['ticket_number'] ?? '',
    'external_ticket_number' => $r['external_ticket_number'] ?? '',
    'ticket_source' => $r['ticket_source'] ?? '',
    'violation_code' => $r['violation_code'] ?? '',
    'sts_violation_code' => $r['sts_violation_code'] ?? '',
    'vehicle_plate' => $r['vehicle_plate'] ?? '',
    'status' => $r['status'] ?? '',
    'fine_amount' => $r['fine_amount'] ?? '',
    'date_issued' => $r['date_issued'] ?? '',
    'issued_by' => $r['issued_by'] ?? '',
    'issued_by_badge' => $r['issued_by_badge'] ?? '',
    'due_date' => $r['due_date'] ?? '',
    'payment_ref' => $r['payment_ref'] ?? '',
  ];
});
