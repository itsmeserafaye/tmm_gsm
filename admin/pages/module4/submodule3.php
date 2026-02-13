<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('module4.schedule');

require_once __DIR__ . '/../../includes/db.php';
$db = db();

$prefillVehicleId = (int)($_GET['vehicle_id'] ?? 0);
$reinspectOf = (int)($_GET['reinspect_of'] ?? 0);
$scheduleId = (int)($_GET['schedule_id'] ?? 0);
$q = trim((string)($_GET['q'] ?? ''));

$hasCol = function (string $table, string $col) use ($db): bool {
  $t = $db->real_escape_string($table);
  $c = $db->real_escape_string($col);
  $r = $db->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
  return $r && $r->num_rows > 0;
};

$schHasCorrection = $hasCol('inspection_schedules', 'correction_due_date');
$schHasRemarks = $hasCol('inspection_schedules', 'status_remarks');
$schHasOverdueAt = $hasCol('inspection_schedules', 'overdue_marked_at');

$edit = null;
$editVehiclePick = '';
$editScheduleLocal = '';
$editLocation = '';
$editType = '';
$editCorrectionDue = '';
$editRemarks = '';

if ($scheduleId > 0) {
  $sel = [
    's.schedule_id',
    's.vehicle_id',
    's.plate_number',
    's.schedule_date',
    's.scheduled_at',
    's.location',
    's.inspection_type',
    's.status',
  ];
  if ($schHasCorrection) $sel[] = 's.correction_due_date';
  if ($schHasRemarks) $sel[] = 's.status_remarks';
  $sqlS = "SELECT " . implode(', ', $sel) . " FROM inspection_schedules s WHERE s.schedule_id=? LIMIT 1";
  $stmtE = $db->prepare($sqlS);
  if ($stmtE) {
    $stmtE->bind_param('i', $scheduleId);
    $stmtE->execute();
    $edit = $stmtE->get_result()->fetch_assoc();
    $stmtE->close();
  }
  if ($edit) {
    $vid = (int)($edit['vehicle_id'] ?? 0);
    $plate = (string)($edit['plate_number'] ?? '');
    if ($vid > 0) {
      $editVehiclePick = (string)$vid . ' - ' . $plate;
    } else {
      $editVehiclePick = $plate;
    }
    $dt = (string)($edit['schedule_date'] ?? $edit['scheduled_at'] ?? '');
    if ($dt !== '') {
      $ts = strtotime($dt);
      if ($ts) $editScheduleLocal = date('Y-m-d\\TH:i', $ts);
    }
    $editLocation = (string)($edit['location'] ?? '');
    $editType = (string)($edit['inspection_type'] ?? '');
    if ($schHasCorrection) $editCorrectionDue = (string)($edit['correction_due_date'] ?? '');
    if ($schHasRemarks) $editRemarks = (string)($edit['status_remarks'] ?? '');
  }
}

$prefillVehicleText = '';
if ($prefillVehicleId > 0) {
  $stmtPV = $db->prepare("SELECT id, plate_number FROM vehicles WHERE id=? LIMIT 1");
  if ($stmtPV) {
    $stmtPV->bind_param('i', $prefillVehicleId);
    $stmtPV->execute();
    if ($pv = $stmtPV->get_result()->fetch_assoc()) {
      $prefillVehicleText = (string)$pv['id'] . ' - ' . (string)$pv['plate_number'];
    } else {
      $prefillVehicleText = (string)$prefillVehicleId;
    }
    $stmtPV->close();
  } else {
    $prefillVehicleText = (string)$prefillVehicleId;
  }
}
$reinspectPrefillVehicleId = 0;
$reinspectPrefillText = '';
if ($reinspectOf > 0) {
  $stmtRS = $db->prepare("SELECT s.vehicle_id, s.plate_number FROM inspection_schedules s WHERE s.schedule_id=? LIMIT 1");
  if ($stmtRS) {
    $stmtRS->bind_param('i', $reinspectOf);
    $stmtRS->execute();
    $rs = $stmtRS->get_result()->fetch_assoc();
    $stmtRS->close();
    if ($rs) {
      $reinspectPrefillVehicleId = (int)($rs['vehicle_id'] ?? 0);
      $p = (string)($rs['plate_number'] ?? '');
      if ($reinspectPrefillVehicleId > 0 && $p !== '') $reinspectPrefillText = (string)$reinspectPrefillVehicleId . ' - ' . $p;
      else if ($p !== '') $reinspectPrefillText = $p;
    }
  }
}

$inspectors = [];
$resI = $db->query("SELECT officer_id, COALESCE(NULLIF(name,''), NULLIF(full_name,'')) AS name, badge_no FROM officers WHERE active_status=1 ORDER BY COALESCE(NULLIF(name,''), NULLIF(full_name,'')) ASC LIMIT 500");
if ($resI) while ($r = $resI->fetch_assoc()) $inspectors[] = $r;

$listStatus = trim((string)($_GET['list_status'] ?? ''));
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
if ($schHasOverdueAt) $sqlOCols[] = "s.overdue_marked_at";
$sqlO = "SELECT " . implode(", ", $sqlOCols) . " FROM inspection_schedules s
         WHERE s.status IN ('Overdue / No-Show','Overdue')";
if ($q !== '') {
  $qv = $db->real_escape_string($q);
  $sqlO .= " AND s.plate_number LIKE '%$qv%'";
}
$sqlO .= " ORDER BY COALESCE(s.overdue_marked_at, s.schedule_date, s.scheduled_at) DESC LIMIT 200";
$resO = $db->query($sqlO);
if ($resO) while ($r = $resO->fetch_assoc()) $overdueRows[] = $r;

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';
?>

<div class="mx-auto max-w-4xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-6">
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between border-b border-slate-200 dark:border-slate-700 pb-6">
    <div>
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight"><?php echo $scheduleId > 0 ? 'Reschedule Inspection' : 'Schedule Inspection'; ?></h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">Schedule inspections for vehicles pending inspection. If OR/CR details are missing, the schedule stays under verification until documents are completed.</p>
    </div>
    <div class="flex flex-col sm:flex-row sm:items-center gap-3 w-full md:w-auto">
      <a href="?page=module4/submodule4" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-md bg-blue-700 hover:bg-blue-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-all active:scale-[0.98]">
        <i data-lucide="clipboard-check" class="w-4 h-4"></i>
        Conduct Inspection
      </a>
      <a href="?page=module4/submodule1" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
        <i data-lucide="list" class="w-4 h-4"></i>
        Back to List
      </a>
    </div>
  </div>

  <div id="toast-container" class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 z-[100] flex flex-col gap-3 pointer-events-none"></div>

  <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
    <div class="p-6 space-y-4">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <div class="text-sm font-black text-slate-900 dark:text-white">Scheduled Inspections</div>
          <div class="text-xs text-slate-500 dark:text-slate-400 font-semibold mt-1">View schedule assignments, overdue items, and inspection readiness.</div>
        </div>
        <div class="flex flex-col sm:flex-row sm:items-center gap-2 w-full sm:w-auto">
          <button type="button" id="btnOpenScheduleModal" class="w-full sm:w-auto px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold text-sm">
            <?php echo $scheduleId > 0 ? 'Reschedule' : 'Schedule'; ?>
          </button>
        </div>
        <form id="scheduleFilterForm" data-tmm-no-auto-filter="1" method="GET" class="flex flex-col sm:flex-row sm:items-center gap-2 w-full sm:w-auto">
          <input type="hidden" name="page" value="module4/submodule3">
          <input name="q" value="<?php echo htmlspecialchars($q); ?>" class="w-full sm:w-56 px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold uppercase" placeholder="Search plate...">
          <select name="list_status" class="w-full sm:w-auto px-4 py-2.5 rounded-md bg-white dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
            <?php $ls = trim((string)($_GET['list_status'] ?? '')); ?>
            <option value="" <?php echo $ls === '' ? 'selected' : ''; ?>>All Status</option>
            <?php foreach (['Scheduled','Rescheduled','Completed','Overdue / No-Show','Overdue','Cancelled'] as $st): ?>
              <option value="<?php echo htmlspecialchars($st); ?>" <?php echo $ls === $st ? 'selected' : ''; ?>><?php echo htmlspecialchars($st); ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="w-full sm:w-auto px-4 py-2.5 rounded-md bg-slate-900 dark:bg-slate-700 text-white font-semibold text-sm">Apply</button>
          <a href="?page=module4/submodule3" class="w-full sm:w-auto px-4 py-2.5 rounded-md bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 font-semibold text-sm text-center">Reset</a>
        </form>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50 dark:bg-slate-700 border-b border-slate-200 dark:border-slate-700">
            <tr class="text-left text-slate-500 dark:text-slate-400">
              <th class="py-3 px-4 font-black uppercase tracking-widest text-xs">Schedule</th>
              <th class="py-3 px-4 font-black uppercase tracking-widest text-xs">Plate</th>
              <th class="py-3 px-4 font-black uppercase tracking-widest text-xs hidden sm:table-cell">Date/Time</th>
              <th class="py-3 px-4 font-black uppercase tracking-widest text-xs hidden md:table-cell">Location</th>
              <th class="py-3 px-4 font-black uppercase tracking-widest text-xs hidden md:table-cell">Inspector</th>
              <th class="py-3 px-4 font-black uppercase tracking-widest text-xs">Status</th>
              <th class="py-3 px-4 font-black uppercase tracking-widest text-xs text-right">Actions</th>
            </tr>
          </thead>
          <tbody id="scheduledTbody" class="divide-y divide-slate-200 dark:divide-slate-700">
            <?php if (!empty($scheduleRows)): ?>
              <?php foreach ($scheduleRows as $r): ?>
                <?php
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
                    if ($ts) {
                      $isReady = $ts <= time();
                    }
                  }
                  $stBadge = $isOverdue
                    ? 'bg-rose-100 text-rose-700 ring-rose-600/20 dark:bg-rose-900/30 dark:text-rose-400 dark:ring-rose-500/20'
                    : (in_array($st, ['Scheduled','Rescheduled'], true)
                      ? 'bg-indigo-100 text-indigo-700 ring-indigo-600/20 dark:bg-indigo-900/30 dark:text-indigo-400 dark:ring-indigo-500/20'
                      : (in_array($st, ['Completed'], true)
                        ? 'bg-emerald-100 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-400 dark:ring-emerald-500/20'
                        : 'bg-slate-100 text-slate-700 ring-slate-600/20 dark:bg-slate-800 dark:text-slate-400'));
                ?>
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                  <td class="py-3 px-4 font-black text-slate-900 dark:text-white">SCH-<?php echo $sid; ?></td>
                  <td class="py-3 px-4 font-black text-slate-900 dark:text-white"><?php echo htmlspecialchars($plate); ?></td>
                  <td class="py-3 px-4 hidden sm:table-cell text-slate-600 dark:text-slate-300 font-semibold">
                    <?php echo htmlspecialchars($dt !== '' ? date('M d, Y H:i', strtotime($dt)) : '-'); ?>
                  </td>
                  <td class="py-3 px-4 hidden md:table-cell text-slate-600 dark:text-slate-300 font-semibold"><?php echo htmlspecialchars($loc !== '' ? $loc : '-'); ?></td>
                  <td class="py-3 px-4 hidden md:table-cell text-slate-600 dark:text-slate-300 font-semibold"><?php echo htmlspecialchars($insp !== '' ? $insp : '-'); ?></td>
                  <td class="py-3 px-4">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold ring-1 ring-inset <?php echo $stBadge; ?>"><?php echo htmlspecialchars($st !== '' ? $st : '-'); ?></span>
                  </td>
                  <td class="py-3 px-4 text-right">
                    <div class="flex flex-wrap items-center justify-end gap-2">
                      <?php if ($isOverdue): ?>
                        <button type="button" data-open-overdue="1" data-sid="<?php echo $sid; ?>" class="px-3 py-2 rounded-md bg-rose-600 hover:bg-rose-700 text-white font-semibold text-xs">Needs Action</button>
                      <?php endif; ?>
                      <?php if ($isReady && in_array($st, ['Scheduled','Rescheduled'], true)): ?>
                        <a href="?<?php echo http_build_query(['page' => 'module4/submodule4', 'schedule_id' => $sid]); ?>" class="px-3 py-2 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs">Conduct</a>
                      <?php endif; ?>
                      <a href="?<?php echo http_build_query(['page' => 'module4/submodule3', 'schedule_id' => $sid]); ?>" class="px-3 py-2 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold text-xs">Reschedule</a>
                      <?php if (has_permission('module4.inspections.manage')): ?>
                        <button type="button" data-delete-sid="<?php echo $sid; ?>" data-delete-plate="<?php echo htmlspecialchars($plate, ENT_QUOTES); ?>" data-delete-status="<?php echo htmlspecialchars($st, ENT_QUOTES); ?>" class="px-3 py-2 rounded-md bg-white dark:bg-slate-900 border border-rose-200 dark:border-rose-700/50 text-rose-700 dark:text-rose-300 hover:bg-rose-50 dark:hover:bg-rose-900/20 font-semibold text-xs">Delete</button>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="7" class="py-10 text-center text-slate-500 font-medium italic">No schedules found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div id="modalOverdue" class="fixed inset-0 z-[220] hidden items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
    <div class="w-full max-w-4xl bg-white dark:bg-slate-900 rounded-2xl shadow-2xl overflow-hidden ring-1 ring-slate-900/5">
      <div class="p-6 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between gap-3">
        <div>
          <div class="text-lg font-black text-slate-900 dark:text-white">Overdue / No-Show (Needs Action)</div>
          <div class="mt-1 text-xs font-semibold text-slate-500 dark:text-slate-400">These schedules require reschedule or cancellation.</div>
        </div>
        <button type="button" data-modal-close class="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>
      <div class="p-6">
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700">
              <tr class="text-left text-slate-500 dark:text-slate-400">
                <th class="py-3 px-4 font-black uppercase tracking-widest text-xs">Schedule</th>
                <th class="py-3 px-4 font-black uppercase tracking-widest text-xs">Plate</th>
                <th class="py-3 px-4 font-black uppercase tracking-widest text-xs hidden sm:table-cell">Date/Time</th>
                <th class="py-3 px-4 font-black uppercase tracking-widest text-xs hidden md:table-cell">Location</th>
                <th class="py-3 px-4 font-black uppercase tracking-widest text-xs hidden lg:table-cell">Remarks</th>
                <th class="py-3 px-4 font-black uppercase tracking-widest text-xs text-right">Actions</th>
              </tr>
            </thead>
            <tbody id="overdueTbody" class="divide-y divide-slate-200 dark:divide-slate-700">
              <?php if (!empty($overdueRows)): ?>
                <?php foreach ($overdueRows as $r): ?>
                  <?php
                    $sid = (int)($r['schedule_id'] ?? 0);
                    $plate = (string)($r['plate_number'] ?? '');
                    $dt = (string)($r['sched_dt'] ?? '');
                    $loc = (string)($r['location'] ?? '');
                    $rem = $schHasRemarks ? (string)($r['status_remarks'] ?? '') : '';
                  ?>
                  <tr data-overdue-row="<?php echo $sid; ?>" class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                    <td class="py-3 px-4 font-black text-slate-900 dark:text-white">SCH-<?php echo $sid; ?></td>
                    <td class="py-3 px-4 font-black text-slate-900 dark:text-white"><?php echo htmlspecialchars($plate); ?></td>
                    <td class="py-3 px-4 hidden sm:table-cell text-slate-600 dark:text-slate-300 font-semibold">
                      <?php echo htmlspecialchars($dt !== '' ? date('M d, Y H:i', strtotime($dt)) : '-'); ?>
                    </td>
                    <td class="py-3 px-4 hidden md:table-cell text-slate-600 dark:text-slate-300 font-semibold"><?php echo htmlspecialchars($loc !== '' ? $loc : '-'); ?></td>
                    <td class="py-3 px-4 hidden lg:table-cell text-slate-600 dark:text-slate-300 font-semibold"><?php echo htmlspecialchars($rem !== '' ? $rem : '-'); ?></td>
                    <td class="py-3 px-4 text-right">
                      <div class="flex flex-wrap items-center justify-end gap-2">
                        <a href="?<?php echo http_build_query(['page' => 'module4/submodule3', 'schedule_id' => $sid]); ?>" class="px-3 py-2 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold text-xs">Reschedule</a>
                        <?php if (has_permission('module4.inspections.manage')): ?>
                          <button type="button" data-cancel-sid="<?php echo $sid; ?>" class="px-3 py-2 rounded-md bg-rose-600 hover:bg-rose-700 text-white font-semibold text-xs">Cancel</button>
                          <button type="button" data-delete-sid="<?php echo $sid; ?>" data-delete-plate="<?php echo htmlspecialchars($plate, ENT_QUOTES); ?>" data-delete-status="<?php echo htmlspecialchars((string)($r['status'] ?? ''), ENT_QUOTES); ?>" class="px-3 py-2 rounded-md bg-white dark:bg-slate-900 border border-rose-200 dark:border-rose-700/50 text-rose-700 dark:text-rose-300 hover:bg-rose-50 dark:hover:bg-rose-900/20 font-semibold text-xs">Delete</button>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="6" class="py-10 text-center text-slate-500 font-medium italic">No overdue schedules found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div id="modalSchedule" class="fixed inset-0 z-[230] hidden items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
    <div class="w-full max-w-2xl bg-white dark:bg-slate-900 rounded-2xl shadow-2xl overflow-hidden ring-1 ring-slate-900/5">
      <div class="p-6 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between gap-3">
        <div>
          <div class="text-lg font-black text-slate-900 dark:text-white"><?php echo $scheduleId > 0 ? 'Reschedule Inspection' : 'Schedule Inspection'; ?></div>
          <div class="mt-1 text-xs font-semibold text-slate-500 dark:text-slate-400">Select a pending-inspection vehicle and set the schedule details.</div>
        </div>
        <button type="button" id="btnCloseScheduleModal" class="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>
      <div class="p-6">
      <form id="formSchedule" class="space-y-5" novalidate>
        <?php if ($scheduleId > 0): ?>
          <input type="hidden" id="scheduleId" name="schedule_id" value="<?php echo (int)$scheduleId; ?>">
        <?php endif; ?>
        <div>
          <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Vehicle</label>
          <input type="hidden" name="vehicle_id" id="vehicleIdHidden" value="">
          <div class="relative">
            <input id="vehiclePick" name="vehicle_pick" required minlength="1" readonly value="<?php echo htmlspecialchars(($editVehiclePick !== '' ? $editVehiclePick : ($reinspectPrefillText !== '' ? $reinspectPrefillText : ($prefillVehicleText !== '' ? $prefillVehicleText : '')))); ?>" data-tmm-uppercase="1" class="w-full px-4 py-2.5 pr-10 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold uppercase cursor-pointer" placeholder="Select a vehicle (uninspected)">
            <button type="button" id="vehiclePickToggle" class="absolute inset-y-0 right-0 px-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
              <i data-lucide="chevron-down" class="w-4 h-4"></i>
            </button>
            <div id="vehiclePickPanel" class="hidden absolute z-[120] mt-2 w-full rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-xl overflow-hidden">
              <div class="p-3 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/40">
                <input id="vehiclePickSearch" class="w-full px-3 py-2 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-sm font-semibold" placeholder="Search plate/type...">
              </div>
              <div id="vehiclePickList" class="max-h-72 overflow-auto"></div>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Inspection Type</label>
            <select id="inspectionType" name="inspection_type" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
              <?php $selType = $editType !== '' ? $editType : ($reinspectOf > 0 ? 'Reinspection' : 'Annual'); ?>
              <option value="Annual" <?php echo $selType === 'Annual' ? 'selected' : ''; ?>>Annual</option>
              <option value="Reinspection" <?php echo $selType === 'Reinspection' ? 'selected' : ''; ?>>Reinspection</option>
              <option value="Compliance">Compliance</option>
              <option value="Special">Special</option>
            </select>
            <?php if ($reinspectOf > 0): ?>
              <div class="mt-1 text-[11px] font-semibold text-slate-500 dark:text-slate-400">Reinspection of SCH-<?php echo (int)$reinspectOf; ?>.</div>
            <?php endif; ?>
          </div>
          <div>
            <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Inspector</label>
            <input type="text" disabled class="w-full px-4 py-2.5 rounded-md bg-slate-100 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="Assigned by inspection office">
          </div>
          <div>
            <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Schedule Date/Time</label>
            <input id="scheduleDate" name="schedule_date" type="datetime-local" required value="<?php echo htmlspecialchars($editScheduleLocal); ?>" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
          </div>
          <div id="correctionWrap" class="<?php echo $reinspectOf > 0 ? '' : 'hidden'; ?>">
            <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Correction Due Date</label>
            <input id="correctionDue" name="correction_due_date" type="date" value="<?php echo htmlspecialchars($editCorrectionDue); ?>" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
          </div>
        </div>

        <div>
          <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Location</label>
          <input name="location" required maxlength="180" value="<?php echo htmlspecialchars($editLocation); ?>" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., Main Terminal Inspection Bay">
        </div>

        <div>
          <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Remarks (optional)</label>
          <textarea name="status_remarks" rows="3" maxlength="255" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., Vehicle unavailable / operator no-show"><?php echo htmlspecialchars($editRemarks); ?></textarea>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2">
          <button id="btnSchedule" class="px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold"><?php echo $scheduleId > 0 ? 'Reschedule' : 'Save'; ?></button>
        </div>
      </form>
      </div>
    </div>
  </div>
</div>

<script>
  (function(){
    const rootUrl = <?php echo json_encode($rootUrl); ?>;
    const form = document.getElementById('formSchedule');
    const btn = document.getElementById('btnSchedule');
    const modalSchedule = document.getElementById('modalSchedule');
    const btnOpenScheduleModal = document.getElementById('btnOpenScheduleModal');
    const btnCloseScheduleModal = document.getElementById('btnCloseScheduleModal');
    const filterForm = document.getElementById('scheduleFilterForm');
    const scheduledTbody = document.getElementById('scheduledTbody');
    const overdueTbody = document.getElementById('overdueTbody');
    const scheduleDate = document.getElementById('scheduleDate');
    const inspectionType = document.getElementById('inspectionType');
    const correctionWrap = document.getElementById('correctionWrap');
    const correctionDue = document.getElementById('correctionDue');
    const scheduleIdEl = document.getElementById('scheduleId');
    const vehiclePick = document.getElementById('vehiclePick');
    const vehiclePickToggle = document.getElementById('vehiclePickToggle');
    const vehiclePickPanel = document.getElementById('vehiclePickPanel');
    const vehiclePickSearch = document.getElementById('vehiclePickSearch');
    const vehiclePickList = document.getElementById('vehiclePickList');
    const vehicleIdHidden = document.getElementById('vehicleIdHidden');

    if (scheduleDate && !scheduleDate.value) {
      const d = new Date();
      d.setMinutes(0, 0, 0);
      d.setHours(d.getHours() + 1);
      const pad = (n) => String(n).padStart(2, '0');
      scheduleDate.value = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
    }

    if (correctionDue && !correctionDue.value) {
      const d = new Date();
      d.setDate(d.getDate() + 7);
      correctionDue.value = d.toISOString().slice(0, 10);
    }

    function syncCorrection() {
      const t = (inspectionType && inspectionType.value) ? String(inspectionType.value) : '';
      if (!correctionWrap) return;
      if (t === 'Reinspection') correctionWrap.classList.remove('hidden');
      else correctionWrap.classList.add('hidden');
    }
    if (inspectionType) inspectionType.addEventListener('change', syncCorrection);
    syncCorrection();

    function showToast(message, type) {
      const container = document.getElementById('toast-container');
      if (!container) return;
      const t = (type || 'success').toString();
      const color = t === 'error' ? 'bg-rose-600' : 'bg-emerald-600';
      const el = document.createElement('div');
      el.className = `pointer-events-auto px-4 py-3 rounded-xl shadow-lg text-white text-sm font-semibold ${color}`;
      el.textContent = message;
      container.appendChild(el);
      setTimeout(() => { el.classList.add('opacity-0'); el.style.transition = 'opacity 250ms'; }, 2600);
      setTimeout(() => { el.remove(); }, 3000);
    }

    const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c] || c));

    let vehiclePickOpen = false;
    let vehiclePickTimer = null;
    let vehiclePickInflight = null;

    function openVehiclePick() {
      if (!vehiclePickPanel) return;
      vehiclePickPanel.classList.remove('hidden');
      vehiclePickOpen = true;
      if (vehiclePickSearch) {
        try { vehiclePickSearch.focus(); } catch (_) {}
      }
      loadVehiclePick((vehiclePickSearch && vehiclePickSearch.value) ? vehiclePickSearch.value : '');
    }

    function closeVehiclePick() {
      if (!vehiclePickPanel) return;
      vehiclePickPanel.classList.add('hidden');
      vehiclePickOpen = false;
    }

    async function loadVehiclePick(query) {
      if (!vehiclePickList) return;
      if (vehiclePickInflight) try { vehiclePickInflight.abort(); } catch (_) {}
      vehiclePickInflight = new AbortController();
      const qs = new URLSearchParams();
      if (query && query.trim() !== '') qs.set('q', query.trim());
      qs.set('limit', '200');
      vehiclePickList.innerHTML = `<div class="p-3 text-sm text-slate-500 dark:text-slate-400">Loading...</div>`;
      try {
        const res = await fetch(rootUrl + '/admin/api/module4/search_uninspected_vehicles.php?' + qs.toString(), { signal: vehiclePickInflight.signal });
        const data = await res.json().catch(() => null);
        if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'load_failed');
        const rows = Array.isArray(data.data) ? data.data : [];
        if (!rows.length) {
          vehiclePickList.innerHTML = `<div class="p-3 text-sm text-slate-500 dark:text-slate-400 italic">No vehicles found.</div>`;
          return;
        }
        vehiclePickList.innerHTML = rows.map((r) => {
          const id = Number(r.id) || 0;
          const plate = (r.plate_number || '').toString().trim().toUpperCase();
          const type = (r.vehicle_type || '').toString().trim();
          const sub = type ? ('<div class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">' + esc(type) + '</div>') : '';
          return `
            <button type="button" class="w-full text-left px-3 py-2 hover:bg-slate-50 dark:hover:bg-slate-800/60 transition-colors border-b border-slate-100 dark:border-slate-800" data-pick-veh="1" data-veh-id="${esc(id)}" data-veh-plate="${esc(plate)}">
              <div class="text-sm font-bold text-slate-800 dark:text-slate-100">${esc(plate)}</div>
              ${sub}
            </button>
          `;
        }).join('');
        vehiclePickList.querySelectorAll('[data-pick-veh="1"]').forEach((btn) => {
          btn.addEventListener('click', () => {
            const id = Number(btn.getAttribute('data-veh-id') || 0);
            const plate = (btn.getAttribute('data-veh-plate') || '').toString().trim();
            if (vehicleIdHidden) vehicleIdHidden.value = id ? String(id) : '';
            if (vehiclePick) vehiclePick.value = (id ? (String(id) + ' - ') : '') + plate;
            if (vehiclePickSearch) vehiclePickSearch.value = '';
            closeVehiclePick();
          });
        });
      } catch (e) {
        if (e && e.name === 'AbortError') return;
        vehiclePickList.innerHTML = `<div class="p-3 text-sm text-rose-600 font-semibold">Failed to load vehicles.</div>`;
      }
    }

    function closeOverlay(id) {
      const el = document.getElementById(id);
      if (!el) return;
      el.classList.add('opacity-0');
      el.style.transition = 'opacity 150ms';
      setTimeout(() => { try { el.remove(); } catch (e) {} }, 170);
    }

    function showPromptOverlay(opts) {
      const id = 'tmmPromptOverlay';
      const existing = document.getElementById(id);
      if (existing) existing.remove();
      const title = esc(opts && opts.title ? opts.title : '');
      const label = esc(opts && opts.label ? opts.label : '');
      const placeholder = esc(opts && opts.placeholder ? opts.placeholder : '');
      const confirmText = esc(opts && opts.confirmText ? opts.confirmText : 'Confirm');
      const defaultValue = esc(opts && typeof opts.defaultValue === 'string' ? opts.defaultValue : '');

      const html = `
        <div id="${id}" class="fixed inset-0 z-[240] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
          <div class="w-full max-w-md bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="p-5">
              <div class="text-base font-black text-slate-900 dark:text-white">${title}</div>
              <div class="mt-3">
                <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">${label}</div>
                <textarea id="tmmPromptInput" rows="3" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="${placeholder}">${defaultValue}</textarea>
              </div>
              <div class="mt-4 flex items-center justify-end gap-2">
                <button type="button" data-tmm-prompt-cancel class="px-4 py-2.5 rounded-md bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 font-semibold text-sm">Cancel</button>
                <button type="button" data-tmm-prompt-ok class="px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold text-sm">${confirmText}</button>
              </div>
            </div>
          </div>
        </div>
      `;
      document.body.insertAdjacentHTML('beforeend', html);

      const overlay = document.getElementById(id);
      const input = document.getElementById('tmmPromptInput');
      const ok = overlay ? overlay.querySelector('[data-tmm-prompt-ok]') : null;
      const cancel = overlay ? overlay.querySelector('[data-tmm-prompt-cancel]') : null;

      requestAnimationFrame(() => { if (input) input.focus(); });

      const done = (val) => {
        closeOverlay(id);
        if (opts && typeof opts.onConfirm === 'function') opts.onConfirm(val);
      };

      if (ok) ok.addEventListener('click', () => done((input ? input.value : '').toString().trim()));
      if (cancel) cancel.addEventListener('click', () => closeOverlay(id));
      if (overlay) overlay.addEventListener('click', (e) => { if (e && e.target === overlay) closeOverlay(id); });
      document.addEventListener('keydown', function onKey(e) {
        const o = document.getElementById(id);
        if (!o) { document.removeEventListener('keydown', onKey); return; }
        if (e && e.key === 'Escape') closeOverlay(id);
      });
    }

    function showConfirmOverlay(opts) {
      const id = 'tmmConfirmOverlay';
      const existing = document.getElementById(id);
      if (existing) existing.remove();
      const title = esc(opts && opts.title ? opts.title : '');
      const message = esc(opts && opts.message ? opts.message : '');
      const confirmText = esc(opts && opts.confirmText ? opts.confirmText : 'Confirm');
      const confirmClass = (opts && opts.confirmClass) ? String(opts.confirmClass) : 'bg-rose-600 hover:bg-rose-700 text-white';

      const html = `
        <div id="${id}" class="fixed inset-0 z-[240] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
          <div class="w-full max-w-md bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="p-5">
              <div class="text-base font-black text-slate-900 dark:text-white">${title}</div>
              <div class="mt-2 text-sm font-semibold text-slate-600 dark:text-slate-300">${message}</div>
              <div class="mt-4 flex items-center justify-end gap-2">
                <button type="button" data-tmm-confirm-cancel class="px-4 py-2.5 rounded-md bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 font-semibold text-sm">Cancel</button>
                <button type="button" data-tmm-confirm-ok class="px-4 py-2.5 rounded-md font-semibold text-sm ${confirmClass}">${confirmText}</button>
              </div>
            </div>
          </div>
        </div>
      `;
      document.body.insertAdjacentHTML('beforeend', html);

      const overlay = document.getElementById(id);
      const ok = overlay ? overlay.querySelector('[data-tmm-confirm-ok]') : null;
      const cancel = overlay ? overlay.querySelector('[data-tmm-confirm-cancel]') : null;

      if (ok) ok.addEventListener('click', () => {
        closeOverlay(id);
        if (opts && typeof opts.onConfirm === 'function') opts.onConfirm();
      });
      if (cancel) cancel.addEventListener('click', () => closeOverlay(id));
      if (overlay) overlay.addEventListener('click', (e) => { if (e && e.target === overlay) closeOverlay(id); });
      document.addEventListener('keydown', function onKey(e) {
        const o = document.getElementById(id);
        if (!o) { document.removeEventListener('keydown', onKey); return; }
        if (e && e.key === 'Escape') closeOverlay(id);
      });
    }

    function parseId(s) {
      const m = (s || '').toString().trim().match(/^(\d+)\s*-/);
      if (m) return Number(m[1] || 0);
      if (/^\d+$/.test((s || '').toString().trim())) return Number((s || '').toString().trim());
      return 0;
    }

    if (vehiclePickToggle) vehiclePickToggle.addEventListener('click', () => { vehiclePickOpen ? closeVehiclePick() : openVehiclePick(); });
    if (vehiclePick) vehiclePick.addEventListener('click', () => { vehiclePickOpen ? closeVehiclePick() : openVehiclePick(); });
    if (vehiclePickSearch) {
      vehiclePickSearch.addEventListener('input', () => {
        if (vehiclePickTimer) clearTimeout(vehiclePickTimer);
        vehiclePickTimer = setTimeout(() => {
          loadVehiclePick(vehiclePickSearch.value || '');
        }, 150);
      });
    }
    document.addEventListener('click', (e) => {
      if (!vehiclePickOpen) return;
      const t = e && e.target ? e.target : null;
      if (!t) return;
      if (vehiclePickPanel && vehiclePickPanel.contains(t)) return;
      if (vehiclePick && vehiclePick.contains(t)) return;
      if (vehiclePickToggle && vehiclePickToggle.contains(t)) return;
      closeVehiclePick();
    });
    document.addEventListener('keydown', (e) => {
      if (!vehiclePickOpen) return;
      if (e && e.key === 'Escape') closeVehiclePick();
    });
    if (vehiclePick && vehicleIdHidden) {
      const parsed = parseId(vehiclePick.value || '');
      if (parsed) vehicleIdHidden.value = String(parsed);
    }

    function openScheduleModal() {
      if (!modalSchedule) return;
      modalSchedule.classList.remove('hidden');
      modalSchedule.classList.add('flex');
      try { document.body.style.overflow = 'hidden'; } catch (e) { }
    }
    function closeScheduleModal() {
      if (!modalSchedule) return;
      modalSchedule.classList.add('hidden');
      modalSchedule.classList.remove('flex');
      try { document.body.style.overflow = ''; } catch (e) { }
      closeVehiclePick();
    }
    if (btnOpenScheduleModal) btnOpenScheduleModal.addEventListener('click', openScheduleModal);
    if (btnCloseScheduleModal) btnCloseScheduleModal.addEventListener('click', closeScheduleModal);
    if (modalSchedule) {
      modalSchedule.addEventListener('click', (e) => { if (e && e.target === modalSchedule) closeScheduleModal(); });
      document.addEventListener('keydown', (e) => {
        if (e && e.key === 'Escape' && !modalSchedule.classList.contains('hidden')) closeScheduleModal();
      });
    }

    async function reloadSchedulesTable(pushState) {
      if (!filterForm || !scheduledTbody || !overdueTbody) return;
      const fd = new FormData(filterForm);
      const qs = new URLSearchParams();
      const q = (fd.get('q') || '').toString().trim();
      const st = (fd.get('list_status') || '').toString().trim();
      if (q) qs.set('q', q);
      if (st) qs.set('list_status', st);
      scheduledTbody.innerHTML = `<tr><td colspan="7" class="py-10 text-center text-slate-500 font-medium italic">Loading...</td></tr>`;
      overdueTbody.innerHTML = `<tr><td colspan="6" class="py-10 text-center text-slate-500 font-medium italic">Loading...</td></tr>`;
      try {
        const res = await fetch(rootUrl + '/admin/api/module4/schedules_table.php?' + qs.toString());
        const data = await res.json().catch(() => null);
        if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'load_failed');
        scheduledTbody.innerHTML = (data.scheduled_html || '').toString();
        overdueTbody.innerHTML = (data.overdue_html || '').toString();
        if (window.lucide) window.lucide.createIcons();
        if (pushState) {
          const urlParams = new URLSearchParams();
          urlParams.set('page', 'module4/submodule3');
          if (q) urlParams.set('q', q);
          if (st) urlParams.set('list_status', st);
          history.replaceState(null, '', '?' + urlParams.toString());
        }
      } catch (e) {
        scheduledTbody.innerHTML = `<tr><td colspan="7" class="py-10 text-center text-rose-600 font-semibold">Failed to load schedules.</td></tr>`;
      }
    }

    if (filterForm) {
      filterForm.addEventListener('submit', (e) => {
        e.preventDefault();
        reloadSchedulesTable(true);
      });
    }

    if (form && btn) {
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!form.checkValidity()) { form.reportValidity(); return; }
        const fd = new FormData(form);
        const pick = (fd.get('vehicle_pick') || '').toString().trim();
        const hid = Number((fd.get('vehicle_id') || '').toString().trim() || 0);
        const vehicleId = hid > 0 ? hid : parseId(pick);
        const plate = pick;
        if (!vehicleId && !plate) { showToast('Select a vehicle or type a plate.', 'error'); return; }
        const post = new FormData();
        if (vehicleId) {
          post.append('vehicle_id', String(vehicleId));
        } else {
          post.append('plate_number', plate);
        }
        if (scheduleIdEl && scheduleIdEl.value) {
          post.append('schedule_id', String(scheduleIdEl.value));
        }
        post.append('schedule_date', (fd.get('schedule_date') || '').toString());
        post.append('location', (fd.get('location') || '').toString());
        post.append('inspection_type', (fd.get('inspection_type') || 'Annual').toString());
        const remarks = (fd.get('status_remarks') || '').toString();
        if (remarks) post.append('status_remarks', remarks);
        <?php if ($reinspectOf > 0): ?>
          post.append('reinspect_of_schedule_id', '<?php echo (int)$reinspectOf; ?>');
        <?php endif; ?>
        if ((fd.get('inspection_type') || '').toString() === 'Reinspection') {
          const cd = (fd.get('correction_due_date') || '').toString();
          if (cd) post.append('correction_due_date', cd);
        }
        btn.disabled = true;
        btn.textContent = 'Saving...';
        try {
          const res = await fetch(rootUrl + '/admin/api/module4/schedule_inspection.php', { method: 'POST', body: post });
          const data = await res.json();
          if (!data || !data.ok || !data.schedule_id) {
            if (data && data.error === 'duplicate_schedule' && data.schedule_id) {
              throw new Error('Duplicate schedule detected. Open SCH-' + String(data.schedule_id) + ' instead.');
            }
            throw new Error((data && data.error) ? data.error : 'save_failed');
          }
          if (data.updated) {
            showToast('Inspection rescheduled.');
            closeScheduleModal();
            await reloadSchedulesTable(false);
            btn.disabled = false;
            btn.textContent = 'Reschedule';
          } else {
            showToast('Inspection scheduled.');
            setTimeout(() => { window.location.href = '?page=module4/submodule4&schedule_id=' + encodeURIComponent(data.schedule_id); }, 500);
          }
        } catch (err) {
          showToast(err.message || 'Failed', 'error');
          btn.disabled = false;
          btn.textContent = (scheduleIdEl && scheduleIdEl.value) ? 'Reschedule' : 'Save';
        }
      });
    }

    const modal = document.getElementById('modalOverdue');
    function openOverdueModal(focusSid) {
      if (!modal) return;
      modal.classList.remove('hidden');
      modal.classList.add('flex');
      try { document.body.style.overflow = 'hidden'; } catch (e) { }
      const sid = String(focusSid || '').trim();
      if (sid) {
        const row = modal.querySelector('[data-overdue-row="' + sid.replace(/"/g, '') + '"]');
        if (row && typeof row.scrollIntoView === 'function') {
          row.scrollIntoView({ block: 'center' });
        }
      }
    }
    function closeOverdueModal() {
      if (!modal) return;
      modal.classList.add('hidden');
      modal.classList.remove('flex');
      try { document.body.style.overflow = ''; } catch (e) { }
    }
    if (modal) {
      modal.querySelectorAll('[data-modal-close]').forEach((b) => b.addEventListener('click', closeOverdueModal));
      modal.addEventListener('click', (e) => {
        if (e && e.target === modal) closeOverdueModal();
      });
      document.addEventListener('keydown', (e) => {
        if (e && e.key === 'Escape' && !modal.classList.contains('hidden')) closeOverdueModal();
      });
    }
    const overdueCount = <?php echo json_encode(count($overdueRows)); ?>;
    if (overdueCount > 0) {
      setTimeout(() => { openOverdueModal(''); }, 50);
    }

    document.addEventListener('click', (e) => {
      const t = e && e.target ? e.target : null;
      if (!t) return;

      const openBtn = t.closest ? t.closest('[data-open-overdue="1"]') : null;
      if (openBtn) {
        const sid = String(openBtn.getAttribute('data-sid') || '').trim();
        openOverdueModal(sid);
        return;
      }

      const cancelBtn = t.closest ? t.closest('[data-cancel-sid]') : null;
      if (cancelBtn) {
        const sid = String(cancelBtn.getAttribute('data-cancel-sid') || '').trim();
        if (!sid) return;
        showPromptOverlay({
          title: 'Cancel Schedule',
          label: 'Cancellation Remarks (Optional)',
          placeholder: 'e.g., No-show / missed inspection',
          defaultValue: 'No-show / missed inspection',
          confirmText: 'Cancel Schedule',
          onConfirm: async (remarks) => {
            const post = new FormData();
            post.append('schedule_id', sid);
            post.append('remarks', String(remarks || ''));
            cancelBtn.disabled = true;
            try {
              const res = await fetch(rootUrl + '/admin/api/module4/cancel_schedule.php', { method: 'POST', body: post });
              const data = await res.json();
              if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'cancel_failed');
              showToast('Schedule cancelled.');
              await reloadSchedulesTable(false);
              cancelBtn.disabled = false;
            } catch (err) {
              showToast(err.message || 'Failed', 'error');
              cancelBtn.disabled = false;
            }
          }
        });
        return;
      }

      const deleteBtn = t.closest ? t.closest('[data-delete-sid]') : null;
      if (deleteBtn) {
        const sid = String(deleteBtn.getAttribute('data-delete-sid') || '').trim();
        if (!sid) return;
        const plate = String(deleteBtn.getAttribute('data-delete-plate') || '').trim();
        const status = String(deleteBtn.getAttribute('data-delete-status') || '').trim();
        const isCompleted = status === 'Completed';
        showConfirmOverlay({
          title: isCompleted ? 'Delete Completed Schedule' : 'Delete Schedule',
          message: `This will permanently delete SCH-${sid}${plate ? ' • ' + plate : ''}.`,
          confirmText: 'Delete',
          confirmClass: 'bg-rose-600 hover:bg-rose-700 text-white',
          onConfirm: async () => {
            const post = new FormData();
            post.append('schedule_id', sid);
            if (isCompleted) post.append('force', '1');
            deleteBtn.disabled = true;
            try {
              const res = await fetch(rootUrl + '/admin/api/module4/delete_schedule.php', { method: 'POST', body: post });
              const data = await res.json();
              if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'delete_failed');
              showToast('Schedule deleted.');
              await reloadSchedulesTable(false);
            } catch (err) {
              if (err && String(err.message || '') === 'cannot_delete_completed') {
                showToast('Cannot delete a completed schedule without force.', 'error');
              } else {
                showToast(err.message || 'Failed', 'error');
              }
            } finally {
              deleteBtn.disabled = false;
            }
          }
        });
        return;
      }
    });

    try {
      const sp = new URLSearchParams(window.location.search || '');
      if (sp.get('schedule_id') || sp.get('vehicle_id') || sp.get('reinspect_of')) openScheduleModal();
    } catch (_) {}
  })();
</script>
