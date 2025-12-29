<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();

$period = strtolower(trim($_GET['period'] ?? ''));
$status = trim($_GET['status'] ?? '');
$q = trim($_GET['q'] ?? '');
$officer_id = isset($_GET['officer_id']) ? (int)$_GET['officer_id'] : 0;

$sql = "SELECT ticket_number, violation_code, vehicle_plate, status, fine_amount, date_issued, issued_by, issued_by_badge, due_date, payment_ref FROM tickets";
$conds = [];
if ($status !== '' && in_array($status, ['Pending','Validated','Settled','Escalated'])) { $conds[] = "status='".$db->real_escape_string($status)."'"; }
if ($period === '30d') { $conds[] = "date_issued >= DATE_SUB(NOW(), INTERVAL 30 DAY)"; }
if ($period === '90d') { $conds[] = "date_issued >= DATE_SUB(NOW(), INTERVAL 90 DAY)"; }
if ($period === 'year') { $conds[] = "YEAR(date_issued) = YEAR(NOW())"; }
if ($q !== '') { $qv = $db->real_escape_string($q); $conds[] = "(vehicle_plate LIKE '%$qv%' OR ticket_number LIKE '%$qv%')"; }
if ($officer_id > 0) { $conds[] = "officer_id=".$officer_id; }
if ($conds) { $sql .= " WHERE " . implode(" AND ", $conds); }
$sql .= " ORDER BY date_issued DESC LIMIT 1000";

$res = $db->query($sql);

header('Content-Type: text/csv; charset=utf-8');
$fname = 'tickets_export_' . date('Ymd_His') . '.csv';
header('Content-Disposition: attachment; filename="'.$fname.'"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Ticket #','Violation','Plate','Status','Fine','Issued Date','Issued By','Badge','Due Date','Receipt Ref']);
if ($res && $res->num_rows > 0) {
  while ($r = $res->fetch_assoc()) {
    fputcsv($out, [
      $r['ticket_number'],
      $r['violation_code'],
      $r['vehicle_plate'],
      $r['status'],
      number_format((float)$r['fine_amount'],2),
      $r['date_issued'],
      $r['issued_by'],
      $r['issued_by_badge'],
      $r['due_date'],
      $r['payment_ref'],
    ]);
  }
}
fclose($out);
?> 
