<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
require_permission('module4.inspections.manage');

$repQ = trim((string)($_GET['rep_q'] ?? ''));
$repPeriod = trim((string)($_GET['rep_period'] ?? '90d'));
$repStatus = trim((string)($_GET['rep_status'] ?? ''));
$repCoop = isset($_GET['rep_coop']) ? (int)$_GET['rep_coop'] : 0;
$repRoute = trim((string)($_GET['rep_route'] ?? ''));
$repTerminal = trim((string)($_GET['rep_terminal'] ?? ''));

$periodStartSql = '';
if ($repPeriod === '30d') $periodStartSql = "NOW() - INTERVAL 30 DAY";
elseif ($repPeriod === '90d') $periodStartSql = "NOW() - INTERVAL 90 DAY";
elseif ($repPeriod === 'year') $periodStartSql = "NOW() - INTERVAL 365 DAY";
elseif ($repPeriod === 'all') $periodStartSql = '';
else $periodStartSql = "NOW() - INTERVAL 90 DAY";

$latestSubqueryWhere = [];
if ($periodStartSql !== '') {
  $latestSubqueryWhere[] = "ir2.submitted_at >= $periodStartSql";
}
$latestSubqueryWhereSql = $latestSubqueryWhere ? ('WHERE ' . implode(' AND ', $latestSubqueryWhere)) : '';
$latestSql = "SELECT s2.plate_number, MAX(ir2.submitted_at) AS max_submitted
  FROM inspection_schedules s2
  JOIN inspection_results ir2 ON ir2.schedule_id=s2.schedule_id
  $latestSubqueryWhereSql
  GROUP BY s2.plate_number";

$whereParts = [];
if ($repCoop > 0) $whereParts[] = "v.coop_id = " . $repCoop;
if ($repRoute !== '') {
  $esc = $db->real_escape_string($repRoute);
  $whereParts[] = "(COALESCE(ta.route_id, v.route_id) = '$esc')";
}
if ($repTerminal !== '') {
  $esc = $db->real_escape_string($repTerminal);
  $whereParts[] = "(ta.terminal_name = '$esc')";
}
if ($repQ !== '') {
  $esc = $db->real_escape_string($repQ);
  $like = '%' . $esc . '%';
  $whereParts[] = "(v.plate_number LIKE '$like' OR v.operator_name LIKE '$like' OR v.coop_name LIKE '$like' OR r.route_name LIKE '$like' OR r.route_id LIKE '$like' OR ta.terminal_name LIKE '$like')";
}
if ($repStatus !== '') {
  $stKey = strtolower(trim($repStatus));
  if ($stKey === 'passed') {
    $whereParts[] = "LOWER(TRIM(COALESCE(last.overall_status, v.inspection_status, ''))) IN ('passed','pass')";
  } elseif ($stKey === 'failed') {
    $whereParts[] = "LOWER(TRIM(COALESCE(last.overall_status, v.inspection_status, ''))) IN ('failed','fail')";
  } elseif ($stKey === 'pending') {
    $whereParts[] = "LOWER(TRIM(COALESCE(last.overall_status, v.inspection_status, ''))) IN ('pending','for reinspection','reinspection')";
  } elseif ($stKey === 'no_result') {
    $whereParts[] = "TRIM(COALESCE(last.overall_status, v.inspection_status, '')) = ''";
  }
}
$reportWhereSql = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';

$sqlExport = "SELECT v.plate_number,
  COALESCE(r.route_id, '') AS route_id,
  COALESCE(r.route_name, '') AS route_name,
  COALESCE(ta.terminal_name, '') AS terminal_name,
  COALESCE(v.coop_name, '') AS coop_name,
  COALESCE(last.overall_status, v.inspection_status, '') AS inspection_status,
  COALESCE(DATE_FORMAT(last.submitted_at, '%Y-%m-%d %H:%i:%s'), '') AS last_inspected_at,
  COALESCE(v.inspection_cert_ref, '') AS certificate_ref
FROM vehicles v
LEFT JOIN terminal_assignments ta ON ta.plate_number=v.plate_number AND ta.status='Authorized'
LEFT JOIN routes r ON r.route_id=COALESCE(ta.route_id, v.route_id)
LEFT JOIN (
  SELECT s.plate_number, ir.overall_status, ir.submitted_at
  FROM inspection_schedules s
  JOIN inspection_results ir ON ir.schedule_id=s.schedule_id
  JOIN ($latestSql) l ON l.plate_number=s.plate_number AND l.max_submitted=ir.submitted_at
) last ON last.plate_number=v.plate_number
$reportWhereSql
ORDER BY v.plate_number ASC
LIMIT 2000";

$res = $db->query($sqlExport);

$fileLabel = date('Ymd_His');
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="inspection_compliance_' . $fileLabel . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

echo "\xEF\xBB\xBF";
echo "plate_number,route_id,route_name,terminal,coop,inspection_status,last_inspected_at,certificate_ref\n";
if ($res) {
  while ($r = $res->fetch_assoc()) {
    $rowOut = [
      (string)($r['plate_number'] ?? ''),
      (string)($r['route_id'] ?? ''),
      (string)($r['route_name'] ?? ''),
      (string)($r['terminal_name'] ?? ''),
      (string)($r['coop_name'] ?? ''),
      (string)($r['inspection_status'] ?? ''),
      (string)($r['last_inspected_at'] ?? ''),
      (string)($r['certificate_ref'] ?? '')
    ];
    $escaped = array_map(function ($v) { return '"' . str_replace('"', '""', $v) . '"'; }, $rowOut);
    echo implode(',', $escaped) . "\n";
  }
}
exit;

