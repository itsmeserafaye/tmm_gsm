<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');
require_permission('module4.schedule');

$q = trim((string)($_GET['q'] ?? ''));
$listStatus = trim((string)($_GET['list_status'] ?? ''));

$hasCol = function (string $table, string $col) use ($db): bool {
  $t = $db->real_escape_string($table);
  $c = $db->real_escape_string($col);
  $r = $db->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
  return $r && $r->num_rows > 0;
};
$schHasRemarks = $hasCol('inspection_schedules', 'status_remarks');

$scheduleRows = [];
$sqlL = "SELECT s.schedule_id, s.plate_number, s.location, s.status, COALESCE(s.schedule_date, s.scheduled_at) AS sched_dt,
                COALESCE(NULLIF(s.inspector_label,''), COALESCE(NULLIF(o.name,''), NULLIF(o.full_name,''))) AS inspector_name
         FROM inspection_schedules s
         LEFT JOIN officers o ON o.officer_id=s.inspector_id
         WHERE 1=1";
if ($q !== '') {
  $qv = $db->real_escape_string($q);
  $sqlL .= " AND s.plate_number LIKE '%$qv%'";
}
if ($listStatus !== '') {
  $sv = $db->real_escape_string($listStatus);
  $sqlL .= " AND s.status='$sv'";
}
$sqlL .= " ORDER BY COALESCE(s.schedule_date, s.scheduled_at) DESC, s.schedule_id DESC LIMIT 500";
$resL = $db->query($sqlL);
if ($resL) while ($r = $resL->fetch_assoc()) $scheduleRows[] = $r;

$overdueRows = [];
$sqlOCols = [
  "s.schedule_id",
  "s.plate_number",
  "s.location",
  "s.status",
  "COALESCE(s.schedule_date, s.scheduled_at) AS sched_dt",
];
if ($schHasRemarks) $sqlOCols[] = "s.status_remarks";
$sqlO = "SELECT " . implode(", ", $sqlOCols) . " FROM inspection_schedules s
         WHERE s.status IN ('Overdue / No-Show','Overdue')";
if ($q !== '') {
  $qv = $db->real_escape_string($q);
  $sqlO .= " AND s.plate_number LIKE '%$qv%'";
}
$sqlO .= " ORDER BY COALESCE(s.overdue_marked_at, s.schedule_date, s.scheduled_at) DESC LIMIT 200";
$resO = $db->query($sqlO);
if ($resO) while ($r = $resO->fetch_assoc()) $overdueRows[] = $r;

$esc = function ($s): string {
  return htmlspecialchars((string)($s ?? ''), ENT_QUOTES);
};

$canManage = has_permission('module4.inspections.manage');

$scheduledHtml = '';
if ($scheduleRows) {
  foreach ($scheduleRows as $r) {
    $sid = (int)($r['schedule_id'] ?? 0);
    $plate = (string)($r['plate_number'] ?? '');
    $dt = (string)($r['sched_dt'] ?? '');
    $loc = (string)($r['location'] ?? '');
    $insp = (string)($r['inspector_name'] ?? '');
    $st = (string)($r['status'] ?? '');
    $isOverdue = in_array($st, ['Overdue / No-Show','Overdue'], true);
    $isReady = false;
    if ($dt !== '') {
      $ts = strtotime($dt);
      if ($ts) $isReady = $ts <= time();
    }
    $stBadge = $isOverdue
      ? 'bg-rose-100 text-rose-700 ring-rose-600/20 dark:bg-rose-900/30 dark:text-rose-400 dark:ring-rose-500/20'
      : (in_array($st, ['Scheduled','Rescheduled'], true)
        ? 'bg-indigo-100 text-indigo-700 ring-indigo-600/20 dark:bg-indigo-900/30 dark:text-indigo-400 dark:ring-indigo-500/20'
        : (in_array($st, ['Completed'], true)
          ? 'bg-emerald-100 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-400 dark:ring-emerald-500/20'
          : 'bg-slate-100 text-slate-700 ring-slate-600/20 dark:bg-slate-800 dark:text-slate-400'));

    $scheduledHtml .= '<tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">';
    $scheduledHtml .= '<td class="py-3 px-4 font-black text-slate-900 dark:text-white">SCH-' . (int)$sid . '</td>';
    $scheduledHtml .= '<td class="py-3 px-4 font-black text-slate-900 dark:text-white">' . $esc($plate) . '</td>';
    $scheduledHtml .= '<td class="py-3 px-4 hidden sm:table-cell text-slate-600 dark:text-slate-300 font-semibold">' . $esc($dt !== '' ? date('M d, Y H:i', strtotime($dt)) : '-') . '</td>';
    $scheduledHtml .= '<td class="py-3 px-4 hidden md:table-cell text-slate-600 dark:text-slate-300 font-semibold">' . $esc($loc !== '' ? $loc : '-') . '</td>';
    $scheduledHtml .= '<td class="py-3 px-4 hidden md:table-cell text-slate-600 dark:text-slate-300 font-semibold">' . $esc($insp !== '' ? $insp : '-') . '</td>';
    $scheduledHtml .= '<td class="py-3 px-4"><span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold ring-1 ring-inset ' . $esc($stBadge) . '">' . $esc($st !== '' ? $st : '-') . '</span></td>';
    $scheduledHtml .= '<td class="py-3 px-4 text-right"><div class="flex flex-wrap items-center justify-end gap-2">';
    if ($isOverdue) {
      $scheduledHtml .= '<button type="button" data-open-overdue="1" data-sid="' . (int)$sid . '" class="px-3 py-2 rounded-md bg-rose-600 hover:bg-rose-700 text-white font-semibold text-xs">Needs Action</button>';
    }
    if ($isReady && in_array($st, ['Scheduled','Rescheduled'], true)) {
      $scheduledHtml .= '<a href="?page=module4/submodule4&schedule_id=' . rawurlencode((string)$sid) . '" class="px-3 py-2 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs">Conduct</a>';
    }
    $scheduledHtml .= '<a href="?page=module4/submodule3&schedule_id=' . rawurlencode((string)$sid) . '" class="px-3 py-2 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold text-xs">Reschedule</a>';
    if ($canManage) {
      $scheduledHtml .= '<button type="button" data-delete-sid="' . (int)$sid . '" data-delete-plate="' . $esc($plate) . '" data-delete-status="' . $esc($st) . '" class="px-3 py-2 rounded-md bg-white dark:bg-slate-900 border border-rose-200 dark:border-rose-700/50 text-rose-700 dark:text-rose-300 hover:bg-rose-50 dark:hover:bg-rose-900/20 font-semibold text-xs">Delete</button>';
    }
    $scheduledHtml .= '</div></td>';
    $scheduledHtml .= '</tr>';
  }
} else {
  $scheduledHtml = '<tr><td colspan="7" class="py-10 text-center text-slate-500 font-medium italic">No schedules found.</td></tr>';
}

$overdueHtml = '';
if ($overdueRows) {
  foreach ($overdueRows as $r) {
    $sid = (int)($r['schedule_id'] ?? 0);
    $plate = (string)($r['plate_number'] ?? '');
    $dt = (string)($r['sched_dt'] ?? '');
    $loc = (string)($r['location'] ?? '');
    $rem = $schHasRemarks ? (string)($r['status_remarks'] ?? '') : '';
    $st = (string)($r['status'] ?? '');
    $overdueHtml .= '<tr data-overdue-row="' . (int)$sid . '" class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">';
    $overdueHtml .= '<td class="py-3 px-4 font-black text-slate-900 dark:text-white">SCH-' . (int)$sid . '</td>';
    $overdueHtml .= '<td class="py-3 px-4 font-black text-slate-900 dark:text-white">' . $esc($plate) . '</td>';
    $overdueHtml .= '<td class="py-3 px-4 hidden sm:table-cell text-slate-600 dark:text-slate-300 font-semibold">' . $esc($dt !== '' ? date('M d, Y H:i', strtotime($dt)) : '-') . '</td>';
    $overdueHtml .= '<td class="py-3 px-4 hidden md:table-cell text-slate-600 dark:text-slate-300 font-semibold">' . $esc($loc !== '' ? $loc : '-') . '</td>';
    if ($schHasRemarks) {
      $overdueHtml .= '<td class="py-3 px-4 hidden lg:table-cell text-slate-600 dark:text-slate-300 font-semibold">' . $esc($rem !== '' ? $rem : '-') . '</td>';
    }
    $overdueHtml .= '<td class="py-3 px-4 text-right"><div class="flex flex-wrap items-center justify-end gap-2">';
    $overdueHtml .= '<a href="?page=module4/submodule3&schedule_id=' . rawurlencode((string)$sid) . '" class="px-3 py-2 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold text-xs">Reschedule</a>';
    if ($canManage) {
      $overdueHtml .= '<button type="button" data-cancel-sid="' . (int)$sid . '" class="px-3 py-2 rounded-md bg-rose-600 hover:bg-rose-700 text-white font-semibold text-xs">Cancel</button>';
      $overdueHtml .= '<button type="button" data-delete-sid="' . (int)$sid . '" data-delete-plate="' . $esc($plate) . '" data-delete-status="' . $esc($st) . '" class="px-3 py-2 rounded-md bg-white dark:bg-slate-900 border border-rose-200 dark:border-rose-700/50 text-rose-700 dark:text-rose-300 hover:bg-rose-50 dark:hover:bg-rose-900/20 font-semibold text-xs">Delete</button>';
    }
    $overdueHtml .= '</div></td>';
    $overdueHtml .= '</tr>';
  }
} else {
  $cols = $schHasRemarks ? 6 : 5;
  $overdueHtml = '<tr><td colspan="' . (int)$cols . '" class="py-10 text-center text-slate-500 font-medium italic">No overdue schedules found.</td></tr>';
}

echo json_encode(['ok' => true, 'scheduled_html' => $scheduledHtml, 'overdue_html' => $overdueHtml, 'has_remarks' => $schHasRemarks ? 1 : 0]);
?>

