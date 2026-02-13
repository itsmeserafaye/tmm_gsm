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
    $scheduledHtml .= '<td class="py-3 px-4"><div class="font-black text-slate-900 dark:text-white">SCH-' . (int)$sid . '</div><div class="mt-1 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase">' . $esc($plate !== '' ? $plate : '-') . '</div></td>';
    $scheduledHtml .= '<td class="py-3 px-4"><div class="text-sm font-semibold text-slate-700 dark:text-slate-200">' . $esc($dt !== '' ? date('M d, Y H:i', strtotime($dt)) : '-') . '</div><div class="mt-1 text-xs font-semibold text-slate-500 dark:text-slate-400">' . $esc($loc !== '' ? $loc : '-') . ($insp !== '' ? (' • ' . $esc($insp)) : '') . '</div></td>';
    $scheduledHtml .= '<td class="py-3 px-4"><span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold ring-1 ring-inset ' . $esc($stBadge) . '">' . $esc($st !== '' ? $st : '-') . '</span><div class="mt-1 text-xs font-semibold text-slate-500 dark:text-slate-400">' . ($isReady ? 'Ready' : 'Upcoming') . '</div></td>';
    $scheduledHtml .= '<td class="py-3 px-4 text-right"><div class="flex items-center justify-end gap-2">';
    if ($isOverdue) {
      $scheduledHtml .= '<button type="button" data-open-overdue="1" data-sid="' . (int)$sid . '" class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-rose-600 hover:bg-rose-700 text-white" title="Needs action"><i data-lucide="alert-triangle" class="w-4 h-4"></i></button>';
    }
    if ($isReady && in_array($st, ['Scheduled','Rescheduled'], true)) {
      $scheduledHtml .= '<a href="?page=module4/submodule4&schedule_id=' . rawurlencode((string)$sid) . '" class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white" title="Conduct"><i data-lucide="check-square" class="w-4 h-4"></i></a>';
    }
    $scheduledHtml .= '<a href="?page=module4/submodule3&schedule_id=' . rawurlencode((string)$sid) . '" class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-blue-700 hover:bg-blue-800 text-white" title="Reschedule"><i data-lucide="calendar-clock" class="w-4 h-4"></i></a>';
    if ($canManage) {
      $scheduledHtml .= '<button type="button" data-delete-sid="' . (int)$sid . '" data-delete-plate="' . $esc($plate) . '" data-delete-status="' . $esc($st) . '" class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-white dark:bg-slate-900 border border-rose-200 dark:border-rose-700/50 text-rose-700 dark:text-rose-300 hover:bg-rose-50 dark:hover:bg-rose-900/20" title="Delete"><i data-lucide="trash-2" class="w-4 h-4"></i></button>';
    }
    $scheduledHtml .= '</div></td>';
    $scheduledHtml .= '</tr>';
  }
} else {
  $scheduledHtml = '<tr><td colspan="4" class="py-10 text-center text-slate-500 font-medium italic">No schedules found.</td></tr>';
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
    $overdueHtml .= '<td class="py-3 px-4"><div class="font-black text-slate-900 dark:text-white">SCH-' . (int)$sid . '</div><div class="mt-1 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase">' . $esc($plate !== '' ? $plate : '-') . '</div></td>';
    $overdueHtml .= '<td class="py-3 px-4"><div class="text-sm font-semibold text-slate-700 dark:text-slate-200">' . $esc($dt !== '' ? date('M d, Y H:i', strtotime($dt)) : '-') . '</div><div class="mt-1 text-xs font-semibold text-slate-500 dark:text-slate-400">' . $esc($loc !== '' ? $loc : '-') . '</div>' . ($schHasRemarks && $rem !== '' ? ('<div class="mt-1 text-xs font-semibold text-rose-700 dark:text-rose-300">' . $esc($rem) . '</div>') : '') . '</td>';
    $overdueHtml .= '<td class="py-3 px-4 text-right"><div class="flex items-center justify-end gap-2">';
    $overdueHtml .= '<a href="?page=module4/submodule3&schedule_id=' . rawurlencode((string)$sid) . '" class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-blue-700 hover:bg-blue-800 text-white" title="Reschedule"><i data-lucide="calendar-clock" class="w-4 h-4"></i></a>';
    if ($canManage) {
      $overdueHtml .= '<button type="button" data-cancel-sid="' . (int)$sid . '" class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-rose-600 hover:bg-rose-700 text-white" title="Cancel"><i data-lucide="x-circle" class="w-4 h-4"></i></button>';
      $overdueHtml .= '<button type="button" data-delete-sid="' . (int)$sid . '" data-delete-plate="' . $esc($plate) . '" data-delete-status="' . $esc($st) . '" class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-white dark:bg-slate-900 border border-rose-200 dark:border-rose-700/50 text-rose-700 dark:text-rose-300 hover:bg-rose-50 dark:hover:bg-rose-900/20" title="Delete"><i data-lucide="trash-2" class="w-4 h-4"></i></button>';
    }
    $overdueHtml .= '</div></td>';
    $overdueHtml .= '</tr>';
  }
} else {
  $overdueHtml = '<tr><td colspan="3" class="py-10 text-center text-slate-500 font-medium italic">No overdue schedules found.</td></tr>';
}

echo json_encode(['ok' => true, 'scheduled_html' => $scheduledHtml, 'overdue_html' => $overdueHtml, 'has_remarks' => $schHasRemarks ? 1 : 0]);
?>
