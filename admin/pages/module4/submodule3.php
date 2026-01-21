<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('module4.schedule');

require_once __DIR__ . '/../../includes/db.php';
$db = db();

$prefillVehicleId = (int)($_GET['vehicle_id'] ?? 0);

$vehicles = [];
$resV = $db->query("SELECT v.id, v.plate_number
                    FROM vehicles v
                    JOIN vehicle_registrations vr ON vr.vehicle_id=v.id AND vr.registration_status IN ('Registered','Recorded')
                    ORDER BY v.plate_number ASC LIMIT 1200");
if ($resV) while ($r = $resV->fetch_assoc()) $vehicles[] = $r;

$inspectors = [];
$resI = $db->query("SELECT officer_id, name, badge_no FROM officers WHERE active_status=1 ORDER BY name ASC LIMIT 500");
if ($resI) while ($r = $resI->fetch_assoc()) $inspectors[] = $r;

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';
?>

<div class="mx-auto max-w-4xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-6">
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between border-b border-slate-200 dark:border-slate-700 pb-6">
    <div>
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Schedule Inspection</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">Only vehicles with recorded OR/CR can be scheduled.</p>
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
    <div class="p-6 space-y-5">
      <form id="formSchedule" class="space-y-5" novalidate>
        <div>
          <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Vehicle</label>
          <input name="vehicle_pick" list="vehiclePickList" required minlength="3" pattern="^\\d+\\s*-\\s*.+$" value="<?php echo $prefillVehicleId > 0 ? htmlspecialchars((string)$prefillVehicleId) : ''; ?>" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., 123 - ABC-1234">
          <datalist id="vehiclePickList">
            <?php foreach ($vehicles as $v): ?>
              <option value="<?php echo htmlspecialchars($v['id'] . ' - ' . $v['plate_number'], ENT_QUOTES); ?>"></option>
            <?php endforeach; ?>
          </datalist>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Inspector</label>
            <select name="inspector_id" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
              <option value="">Select inspector</option>
              <?php foreach ($inspectors as $i): ?>
                <?php $label = (string)$i['name']; if (!empty($i['badge_no'])) $label .= ' (' . $i['badge_no'] . ')'; ?>
                <option value="<?php echo (int)$i['officer_id']; ?>"><?php echo htmlspecialchars($label); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Schedule Date/Time</label>
            <input id="scheduleDate" name="schedule_date" type="datetime-local" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
          </div>
        </div>

        <div>
          <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Location</label>
          <input name="location" required maxlength="180" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., Main Terminal Inspection Bay">
        </div>

        <div class="flex items-center justify-end gap-2 pt-2">
          <button id="btnSchedule" class="px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold">Save</button>
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

    if (scheduleDate && !scheduleDate.value) {
      const d = new Date();
      d.setMinutes(0, 0, 0);
      d.setHours(d.getHours() + 1);
      const pad = (n) => String(n).padStart(2, '0');
      scheduleDate.value = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
    }

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
        const vehicleId = parseId(fd.get('vehicle_pick'));
        if (!vehicleId) { showToast('Select a valid vehicle.', 'error'); return; }
        const post = new FormData();
        post.append('vehicle_id', String(vehicleId));
        post.append('schedule_date', (fd.get('schedule_date') || '').toString());
        post.append('location', (fd.get('location') || '').toString());
        post.append('inspector_id', (fd.get('inspector_id') || '').toString());
        post.append('cr_verified', '1');
        post.append('or_verified', '1');
        btn.disabled = true;
        btn.textContent = 'Saving...';
        try {
          const res = await fetch(rootUrl + '/admin/api/module4/schedule_inspection.php', { method: 'POST', body: post });
          const data = await res.json();
          if (!data || !data.ok || !data.schedule_id) throw new Error((data && data.error) ? data.error : 'save_failed');
          showToast('Inspection scheduled.');
          setTimeout(() => { window.location.href = '?page=module4/submodule4&schedule_id=' + encodeURIComponent(data.schedule_id); }, 500);
        } catch (err) {
          showToast(err.message || 'Failed', 'error');
          btn.disabled = false;
          btn.textContent = 'Save';
        }
      });
    }
  })();
</script>
