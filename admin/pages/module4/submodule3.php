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

$vehicles = [];
$resV = $db->query("SELECT v.id, v.plate_number
                    FROM vehicles v
                    JOIN vehicle_registrations vr ON vr.vehicle_id=v.id AND vr.registration_status IN ('Registered','Recorded')
                    ORDER BY v.plate_number ASC LIMIT 1200");
if ($resV) while ($r = $resV->fetch_assoc()) $vehicles[] = $r;

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
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">Only vehicles with recorded OR/CR can be scheduled. Reinspection is used after corrections from a failed result.</p>
    </div>
    <div class="flex items-center gap-3">
      <a href="?page=module4/submodule4" class="inline-flex items-center justify-center gap-2 rounded-md bg-blue-700 hover:bg-blue-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-all active:scale-[0.98]">
        <i data-lucide="clipboard-check" class="w-4 h-4"></i>
        Conduct Inspection
      </a>
      <a href="?page=module4/submodule1" class="inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
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
          <div class="text-sm font-black text-slate-900 dark:text-white">Overdue / No-Show (Needs Action)</div>
          <div class="text-xs text-slate-500 dark:text-slate-400 font-semibold mt-1">Reschedule or cancel overdue schedules so they don’t stay stuck.</div>
        </div>
        <form method="GET" class="flex items-center gap-2">
          <input type="hidden" name="page" value="module4/submodule3">
          <input name="q" value="<?php echo htmlspecialchars($q); ?>" class="w-56 px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold uppercase" placeholder="Search plate...">
          <button class="px-4 py-2.5 rounded-md bg-slate-900 dark:bg-slate-700 text-white font-semibold text-sm">Search</button>
          <a href="?page=module4/submodule3" class="px-4 py-2.5 rounded-md bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 font-semibold text-sm">Reset</a>
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
              <th class="py-3 px-4 font-black uppercase tracking-widest text-xs hidden lg:table-cell">Remarks</th>
              <th class="py-3 px-4 font-black uppercase tracking-widest text-xs text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
            <?php if ($overdueRows): ?>
              <?php foreach ($overdueRows as $r): ?>
                <?php
                  $sid = (int)($r['schedule_id'] ?? 0);
                  $plate = (string)($r['plate_number'] ?? '');
                  $dt = (string)($r['sched_dt'] ?? '');
                  $loc = (string)($r['location'] ?? '');
                  $rem = $schHasRemarks ? (string)($r['status_remarks'] ?? '') : '';
                ?>
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                  <td class="py-3 px-4 font-black text-slate-900 dark:text-white">SCH-<?php echo $sid; ?></td>
                  <td class="py-3 px-4 font-black text-slate-900 dark:text-white"><?php echo htmlspecialchars($plate); ?></td>
                  <td class="py-3 px-4 hidden sm:table-cell text-slate-600 dark:text-slate-300 font-semibold">
                    <?php echo htmlspecialchars($dt !== '' ? date('M d, Y H:i', strtotime($dt)) : '-'); ?>
                  </td>
                  <td class="py-3 px-4 hidden md:table-cell text-slate-600 dark:text-slate-300 font-semibold"><?php echo htmlspecialchars($loc !== '' ? $loc : '-'); ?></td>
                  <td class="py-3 px-4 hidden lg:table-cell text-slate-600 dark:text-slate-300 font-semibold"><?php echo htmlspecialchars($rem !== '' ? $rem : '-'); ?></td>
                  <td class="py-3 px-4 text-right">
                    <div class="flex items-center justify-end gap-2">
                      <a href="?<?php echo http_build_query(['page' => 'module4/submodule3', 'schedule_id' => $sid]); ?>" class="px-3 py-2 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold text-xs">Reschedule</a>
                      <?php if (has_permission('module4.inspections.manage')): ?>
                        <button type="button" data-cancel-sid="<?php echo $sid; ?>" class="px-3 py-2 rounded-md bg-rose-600 hover:bg-rose-700 text-white font-semibold text-xs">Cancel</button>
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

  <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
    <div class="p-6 space-y-5">
      <form id="formSchedule" class="space-y-5" novalidate>
        <?php if ($scheduleId > 0): ?>
          <input type="hidden" id="scheduleId" name="schedule_id" value="<?php echo (int)$scheduleId; ?>">
        <?php endif; ?>
        <div>
          <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Vehicle</label>
          <input name="vehicle_pick" list="vehiclePickList" required minlength="1" value="<?php echo htmlspecialchars(($editVehiclePick !== '' ? $editVehiclePick : ($reinspectPrefillText !== '' ? $reinspectPrefillText : ($prefillVehicleText !== '' ? $prefillVehicleText : '')))); ?>" data-tmm-mask="plate_any" data-tmm-uppercase="1" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold uppercase" placeholder="Type plate or select from list">
          <datalist id="vehiclePickList">
            <?php foreach ($vehicles as $v): ?>
              <option value="<?php echo htmlspecialchars($v['id'] . ' - ' . $v['plate_number'], ENT_QUOTES); ?>"></option>
            <?php endforeach; ?>
          </datalist>
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

<script>
  (function(){
    const rootUrl = <?php echo json_encode($rootUrl); ?>;
    const form = document.getElementById('formSchedule');
    const btn = document.getElementById('btnSchedule');
    const scheduleDate = document.getElementById('scheduleDate');
    const inspectionType = document.getElementById('inspectionType');
    const correctionWrap = document.getElementById('correctionWrap');
    const correctionDue = document.getElementById('correctionDue');
    const scheduleIdEl = document.getElementById('scheduleId');

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

    function parseId(s) {
      const m = (s || '').toString().trim().match(/^(\d+)\s*-/);
      if (m) return Number(m[1] || 0);
      if (/^\d+$/.test((s || '').toString().trim())) return Number((s || '').toString().trim());
      return 0;
    }

    if (form && btn) {
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!form.checkValidity()) { form.reportValidity(); return; }
        const fd = new FormData(form);
        const pick = (fd.get('vehicle_pick') || '').toString().trim();
        const vehicleId = parseId(pick);
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
        post.append('cr_verified', '1');
        post.append('or_verified', '1');
        btn.disabled = true;
        btn.textContent = 'Saving...';
        try {
          const res = await fetch(rootUrl + '/admin/api/module4/schedule_inspection.php', { method: 'POST', body: post });
          const data = await res.json();
          if (!data || !data.ok || !data.schedule_id) throw new Error((data && data.error) ? data.error : 'save_failed');
          if (data.updated) {
            showToast('Inspection rescheduled.');
            setTimeout(() => { window.location.href = '?page=module4/submodule3'; }, 500);
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

    document.querySelectorAll('[data-cancel-sid]').forEach((b) => {
      b.addEventListener('click', async () => {
        const sid = String(b.getAttribute('data-cancel-sid') || '').trim();
        if (!sid) return;
        const remarks = window.prompt('Cancellation remarks (optional):', 'No-show / missed inspection') || '';
        const post = new FormData();
        post.append('schedule_id', sid);
        post.append('remarks', remarks);
        b.disabled = true;
        try {
          const res = await fetch(rootUrl + '/admin/api/module4/cancel_schedule.php', { method: 'POST', body: post });
          const data = await res.json();
          if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'cancel_failed');
          showToast('Schedule cancelled.');
          setTimeout(() => { window.location.reload(); }, 400);
        } catch (err) {
          showToast(err.message || 'Failed', 'error');
          b.disabled = false;
        }
      });
    });
  })();
</script>
