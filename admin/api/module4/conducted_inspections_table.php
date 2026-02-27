<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module4.inspect','module4.certify']);
header('Content-Type: application/json');

$db = db();
$q = trim((string)($_GET['q'] ?? ''));
$resultFilter = trim((string)($_GET['result'] ?? ''));
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));

$sqlC = "SELECT r.result_id, r.schedule_id, r.overall_status AS result, r.remarks, r.submitted_at AS inspected_at,
                s.plate_number, s.status AS schedule_status, s.location, s.vehicle_id, COALESCE(s.schedule_date, s.scheduled_at) AS sched_dt
         FROM inspection_results r
         JOIN inspection_schedules s ON s.schedule_id=r.schedule_id
         WHERE 1=1";
if ($q !== '') {
  $qv = $db->real_escape_string($q);
  $sqlC .= " AND (s.plate_number LIKE '%$qv%' OR s.location LIKE '%$qv%')";
}
if ($resultFilter !== '' && in_array($resultFilter, ['Passed','Failed'], true)) {
  $rv = $db->real_escape_string($resultFilter);
  $sqlC .= " AND r.overall_status='$rv'";
}
if ($from !== '') {
  $fv = $db->real_escape_string($from);
  $sqlC .= " AND DATE(r.submitted_at) >= '$fv'";
}
if ($to !== '') {
  $tv = $db->real_escape_string($to);
  $sqlC .= " AND DATE(r.submitted_at) <= '$tv'";
}
$sqlC .= " ORDER BY r.submitted_at DESC, r.result_id DESC LIMIT 500";

$resC = $db->query($sqlC);
if (!$resC) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_query_failed']);
  exit;
}

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';

$html = '';
if ($resC->num_rows <= 0) {
  $html = '<tr><td colspan="7" class="py-10 text-center text-slate-500 font-medium italic">No inspections recorded yet.</td></tr>';
} else {
  while ($r = $resC->fetch_assoc()) {
    $sid = (int)($r['schedule_id'] ?? 0);
    $pid = (string)($r['plate_number'] ?? '');
    $dt = (string)($r['sched_dt'] ?? '');
    $loc = (string)($r['location'] ?? '');
    $res = (string)($r['result'] ?? '');
    $insAt = (string)($r['inspected_at'] ?? '');
    $vid = (int)($r['vehicle_id'] ?? 0);
    $badge = $res === 'Passed'
      ? 'bg-emerald-100 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-400 dark:ring-emerald-500/20'
      : 'bg-rose-100 text-rose-700 ring-rose-600/20 dark:bg-rose-900/30 dark:text-rose-400 dark:ring-rose-500/20';
    $schedText = $dt !== '' ? date('M d, Y H:i', strtotime($dt)) : '-';
    $insText = $insAt !== '' ? date('M d, Y H:i', strtotime($insAt)) : '-';
    $html .= '<tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">';
    $html .= '<td class="py-3 px-4 font-black text-slate-900 dark:text-white">SCH-' . $sid . '</td>';
    $html .= '<td class="py-3 px-4 font-black text-slate-900 dark:text-white">' . htmlspecialchars($pid) . '</td>';
    $html .= '<td class="py-3 px-4 hidden sm:table-cell text-slate-600 dark:text-slate-300 font-semibold">' . htmlspecialchars($schedText) . '</td>';
    $html .= '<td class="py-3 px-4 hidden md:table-cell text-slate-600 dark:text-slate-300 font-semibold">' . htmlspecialchars($loc !== '' ? $loc : '-') . '</td>';
    $html .= '<td class="py-3 px-4"><span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold ring-1 ring-inset ' . $badge . '">' . htmlspecialchars($res !== '' ? $res : '-') . '</span></td>';
    $html .= '<td class="py-3 px-4 hidden sm:table-cell text-slate-600 dark:text-slate-300 font-semibold">' . htmlspecialchars($insText) . '</td>';
    $html .= '<td class="py-3 px-4 text-right">';
    $html .= '<button type="button" data-inspection-view="1" data-schedule-id="' . $sid . '" class="inline-flex items-center justify-center gap-2 px-3 py-2 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800/40 text-xs font-bold">';
    $html .= '<i data-lucide="eye" class="w-4 h-4"></i> View';
    $html .= '</button>';
    $html .= '</td>';
    $html .= '</tr>';
  }
}

echo json_encode(['ok' => true, 'html' => $html]);
