<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/simple_pdf.php';

$db = db();
require_any_permission(['module3.read', 'module3.issue', 'reports.export']);

$plateRaw = (string)($_GET['plate'] ?? $_GET['plate_number'] ?? '');
$plate = strtoupper(preg_replace('/\s+/', '', trim($plateRaw)));
$plate = preg_replace('/[^A-Z0-9\-]/', '', $plate);
if ($plate !== '' && strpos($plate, '-') === false) {
  $letters = substr(preg_replace('/[^A-Z]/', '', $plate), 0, 3);
  $digits = substr(preg_replace('/[^0-9]/', '', $plate), 0, 4);
  if ($letters !== '' && $digits !== '') $plate = $letters . '-' . $digits;
}

$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));

if ($plate === '' || !preg_match('/^[A-Z]{3}\-[0-9]{3,4}$/', $plate)) {
  http_response_code(400);
  header('Content-Type: text/plain; charset=utf-8');
  echo 'invalid_plate';
  exit;
}

$conds = ["v.plate_number=?"];
$params = [$plate];
$types = "s";

if ($from !== '' && preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $from)) {
  $conds[] = "COALESCE(v.violation_date, v.created_at) >= ?";
  $params[] = $from . ' 00:00:00';
  $types .= "s";
}
if ($to !== '' && preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $to)) {
  $conds[] = "COALESCE(v.violation_date, v.created_at) <= ?";
  $params[] = $to . ' 23:59:59';
  $types .= "s";
}

$where = "WHERE " . implode(" AND ", $conds);

$sql = "SELECT
  v.id,
  v.violation_type,
  vt.description AS violation_desc,
  v.amount,
  v.status,
  v.workflow_status,
  v.location,
  COALESCE(v.violation_date, v.created_at) AS observed_at,
  (SELECT GROUP_CONCAT(CONCAT(t.sts_ticket_no,' [',t.status,']') SEPARATOR '; ')
   FROM sts_tickets t WHERE t.linked_violation_id=v.id) AS sts_tickets
FROM violations v
LEFT JOIN violation_types vt ON vt.violation_code=v.violation_type
$where
ORDER BY COALESCE(v.violation_date, v.created_at) DESC, v.id DESC
LIMIT 600";

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
$rows = [];
while ($res && ($r = $res->fetch_assoc())) $rows[] = $r;
$stmt->close();

$vehMeta = null;
$stmtV = $db->prepare("SELECT id, vehicle_type, COALESCE(NULLIF(risk_level,''),'Low') AS risk_level, COALESCE(NULLIF(compliance_status,''),'Active') AS compliance_status FROM vehicles WHERE plate_number=? LIMIT 1");
if ($stmtV) {
  $stmtV->bind_param('s', $plate);
  $stmtV->execute();
  $vehMeta = $stmtV->get_result()->fetch_assoc();
  $stmtV->close();
}

$total = 0;
$unpaid = 0;
$sum = 0.0;
foreach ($rows as $r) {
  $total++;
  $sum += (float)($r['amount'] ?? 0);
  if ((string)($r['status'] ?? '') === 'Unpaid') $unpaid++;
}

$generated = date('Y-m-d H:i');
$period = (($from !== '' ? $from : 'All') . ' to ' . ($to !== '' ? $to : 'All'));
$vehType = $vehMeta ? (string)($vehMeta['vehicle_type'] ?? '') : '';
$vehRisk = $vehMeta ? (string)($vehMeta['risk_level'] ?? 'Low') : 'Low';
$vehComp = $vehMeta ? (string)($vehMeta['compliance_status'] ?? 'Active') : 'Active';

$lines = [];
$lines[] = 'VIOLATION HISTORY';
$lines[] = 'Generated: ' . $generated;
$lines[] = 'Plate: ' . $plate . ($vehType !== '' ? ('   Type: ' . $vehType) : '');
$lines[] = 'Risk: ' . $vehRisk . '   Compliance: ' . $vehComp;
$lines[] = 'Period: ' . $period;
$lines[] = 'Total Violations: ' . $total . '   Unpaid: ' . $unpaid . '   Total Amount: ' . number_format($sum, 2);
$lines[] = str_repeat('-', 108);
$lines[] = 'DATE             CODE       STATUS   WF       AMOUNT     LOCATION                    STS TICKETS';
$lines[] = str_repeat('-', 108);

if (!$rows) {
  $lines[] = 'No records.';
} else {
  foreach ($rows as $r) {
    $dt = substr((string)($r['observed_at'] ?? ''), 0, 16);
    $code = substr((string)($r['violation_type'] ?? ''), 0, 10);
    $st = substr((string)($r['status'] ?? ''), 0, 7);
    $wf = substr((string)($r['workflow_status'] ?? ''), 0, 8);
    $amt = number_format((float)($r['amount'] ?? 0), 2);
    $loc = substr((string)($r['location'] ?? ''), 0, 26);
    $sts = substr((string)($r['sts_tickets'] ?? ''), 0, 34);
    $lines[] = sprintf("%-16s %-10s %-7s %-8s %10s  %-26s  %-34s", $dt, $code, $st, $wf, $amt, $loc, $sts);
    $desc = trim((string)($r['violation_desc'] ?? ''));
    if ($desc !== '') $lines[] = '  ' . substr($desc, 0, 100);
  }
}

$fname = 'violation_history_' . $plate . '_' . date('Ymd_His') . '.pdf';
tmm_simple_pdf_download($lines, $fname, ['page_width' => 612, 'page_height' => 792, 'font_size' => 8, 'leading' => 9, 'max_lines' => 78]);

