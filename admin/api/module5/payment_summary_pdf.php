<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/simple_pdf.php';

$db = db();
require_any_permission(['module5.parking_fees', 'reports.export']);

$terminalId = (int)($_GET['terminal_id'] ?? 0);
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));

$conds = [];
$params = [];
$types = '';

if ($terminalId > 0) {
  $conds[] = "ps.terminal_id=?";
  $params[] = $terminalId;
  $types .= 'i';
}
if ($from !== '' && preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $from)) {
  $conds[] = "pp.paid_at >= ?";
  $params[] = $from . ' 00:00:00';
  $types .= 's';
}
if ($to !== '' && preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $to)) {
  $conds[] = "pp.paid_at <= ?";
  $params[] = $to . ' 23:59:59';
  $types .= 's';
}

$where = $conds ? ("WHERE " . implode(" AND ", $conds)) : "";

$termName = 'All Terminals';
if ($terminalId > 0) {
  $stmtT = $db->prepare("SELECT name FROM terminals WHERE id=? LIMIT 1");
  if ($stmtT) {
    $stmtT->bind_param('i', $terminalId);
    $stmtT->execute();
    $rowT = $stmtT->get_result()->fetch_assoc();
    $stmtT->close();
    if ($rowT) $termName = (string)($rowT['name'] ?? $termName);
  }
}

$sql = "SELECT
  pp.payment_id,
  pp.amount,
  pp.or_no,
  pp.paid_at,
  COALESCE(pp.exported_to_treasury,0) AS exported_to_treasury,
  pp.exported_at,
  v.plate_number,
  ps.slot_no,
  t.name AS terminal_name
FROM parking_payments pp
JOIN vehicles v ON v.id=pp.vehicle_id
JOIN parking_slots ps ON ps.slot_id=pp.slot_id
JOIN terminals t ON t.id=ps.terminal_id
$where
ORDER BY pp.paid_at DESC
LIMIT 500";

$rows = [];
if ($params) {
  $stmt = $db->prepare($sql);
  if (!$stmt) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'db_prepare_failed';
    exit;
  }
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($res && ($r = $res->fetch_assoc())) $rows[] = $r;
  $stmt->close();
} else {
  $res = $db->query($sql);
  if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
}

$totalAmount = 0.0;
$totalCount = 0;
$totalsByTerminal = [];
foreach ($rows as $r) {
  $amt = (float)($r['amount'] ?? 0);
  $totalAmount += $amt;
  $totalCount++;
  $tn = (string)($r['terminal_name'] ?? '');
  if (!isset($totalsByTerminal[$tn])) $totalsByTerminal[$tn] = ['count' => 0, 'amount' => 0.0];
  $totalsByTerminal[$tn]['count'] += 1;
  $totalsByTerminal[$tn]['amount'] += $amt;
}

uksort($totalsByTerminal, function ($a, $b) use ($totalsByTerminal) {
  $av = (float)($totalsByTerminal[$a]['amount'] ?? 0);
  $bv = (float)($totalsByTerminal[$b]['amount'] ?? 0);
  if ($bv !== $av) return ($bv > $av) ? 1 : -1;
  return strcmp((string)$a, (string)$b);
});

$generated = date('Y-m-d H:i');
$period = (($from !== '' ? $from : 'All') . ' to ' . ($to !== '' ? $to : 'All'));

$lines = [];
$lines[] = 'PAYMENT SUMMARY';
$lines[] = 'Generated: ' . $generated;
$lines[] = 'Terminal: ' . $termName;
$lines[] = 'Period: ' . $period;
$lines[] = 'Total Payments: ' . $totalCount . '   Total Amount: ' . number_format($totalAmount, 2);
$lines[] = str_repeat('-', 94);
if ($terminalId <= 0 && $totalsByTerminal) {
  $lines[] = 'TOTALS BY TERMINAL';
  foreach ($totalsByTerminal as $tn => $trow) {
    $lines[] = ' - ' . substr((string)$tn, 0, 55) . '  ' . str_pad((string)((int)($trow['count'] ?? 0)), 6, ' ', STR_PAD_LEFT) . '  ' . str_pad(number_format((float)($trow['amount'] ?? 0), 2), 12, ' ', STR_PAD_LEFT);
  }
  $lines[] = str_repeat('-', 94);
}

$lines[] = 'PAID              PLATE      SLOT     OR NO              AMOUNT    TREASURY';
$lines[] = str_repeat('-', 94);
if (!$rows) {
  $lines[] = 'No records.';
} else {
  foreach ($rows as $r) {
    $paidAt = substr((string)($r['paid_at'] ?? ''), 0, 16);
    $plate = substr((string)($r['plate_number'] ?? ''), 0, 10);
    $slot = substr((string)($r['slot_no'] ?? ''), 0, 8);
    $orNo = substr((string)($r['or_no'] ?? ''), 0, 18);
    $amt = number_format((float)($r['amount'] ?? 0), 2);
    $treasury = ((int)($r['exported_to_treasury'] ?? 0) === 1) ? 'Exported' : 'Pending';
    $lines[] = sprintf("%-16s %-10s %-8s %-18s %10s  %-8s", $paidAt, $plate, $slot, $orNo, $amt, $treasury);
  }
}

$fname = 'payment_summary_' . date('Ymd_His') . '.pdf';
tmm_simple_pdf_download($lines, $fname, ['font_size' => 9, 'leading' => 10, 'max_lines' => 70]);

