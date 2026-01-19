<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('module5.assign_vehicle');

require_once __DIR__ . '/../../includes/db.php';
$db = db();

$prefillTerminalId = (int)($_GET['terminal_id'] ?? 0);

$terminals = [];
$resT = $db->query("SELECT id, name, capacity FROM terminals WHERE type <> 'Parking' ORDER BY name ASC LIMIT 500");
if ($resT) while ($r = $resT->fetch_assoc()) $terminals[] = $r;

$vehicles = [];
$resV = $db->query("SELECT id, plate_number, operator_id, inspection_status FROM vehicles WHERE plate_number IS NOT NULL AND plate_number <> '' ORDER BY plate_number ASC LIMIT 1500");
if ($resV) while ($r = $resV->fetch_assoc()) $vehicles[] = $r;

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';
?>

<div class="mx-auto max-w-4xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-6">
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between border-b border-slate-200 dark:border-slate-700 pb-6">
    <div>
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Assign Vehicle to Terminal</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">System checks: franchise status, inspection passed, and OR/CR valid.</p>
    </div>
    <div class="flex items-center gap-3">
      <a href="?page=module5/submodule1" class="inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>
        Terminal List
      </a>
    </div>
  </div>

  <div id="toast-container" class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 z-[100] flex flex-col gap-3 pointer-events-none"></div>

  <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
    <div class="p-6 space-y-5">
      <form id="formAssign" class="space-y-5" novalidate>
        <div>
          <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Terminal</label>
          <select name="terminal_id" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
            <option value="">Select terminal</option>
            <?php foreach ($terminals as $t): ?>
              <option value="<?php echo (int)$t['id']; ?>" <?php echo ($prefillTerminalId > 0 && (int)$t['id'] === $prefillTerminalId) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars((string)$t['name']); ?><?php if ((int)($t['capacity'] ?? 0) > 0) echo ' (cap ' . (int)$t['capacity'] . ')'; ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Vehicle</label>
          <input id="vehicleFilter" type="text" class="mb-2 w-full px-4 py-2.5 rounded-md bg-white dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="Quick filter (type plate)...">
          <select name="vehicle_id" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
            <option value="">Select vehicle</option>
            <?php foreach ($vehicles as $v): ?>
              <?php $label = (string)$v['plate_number']; ?>
              <option value="<?php echo (int)$v['id']; ?>"><?php echo htmlspecialchars($label); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="flex items-center justify-end gap-2 pt-2">
          <button id="btnAssign" class="px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold">Assign</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  (function(){
    const rootUrl = <?php echo json_encode($rootUrl); ?>;
    const form = document.getElementById('formAssign');
    const btn = document.getElementById('btnAssign');
    const vehicleFilter = document.getElementById('vehicleFilter');
    const vehicleSelect = form ? form.querySelector('select[name="vehicle_id"]') : null;

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

    if (form && btn) {
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!form.checkValidity()) { form.reportValidity(); return; }
        btn.disabled = true;
        btn.textContent = 'Assigning...';
        try {
          const res = await fetch(rootUrl + '/admin/api/module5/assign_terminal.php', { method: 'POST', body: new FormData(form) });
          const data = await res.json();
          if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'assign_failed');
          showToast('Vehicle assigned to terminal.');
          setTimeout(() => { window.location.href = '?page=module5/submodule1'; }, 600);
        } catch (err) {
          showToast(err.message || 'Failed', 'error');
          btn.disabled = false;
          btn.textContent = 'Assign';
        }
      });
    }

    if (vehicleFilter && vehicleSelect) {
      const allOptions = Array.from(vehicleSelect.querySelectorAll('option')).map((o) => ({ value: o.value, text: o.textContent }));
      vehicleFilter.addEventListener('input', () => {
        const q = (vehicleFilter.value || '').toLowerCase().trim();
        vehicleSelect.innerHTML = '';
        allOptions.forEach((o) => {
          if (o.value === '' || q === '' || (o.text || '').toLowerCase().includes(q)) {
            const opt = document.createElement('option');
            opt.value = o.value;
            opt.textContent = o.text;
            vehicleSelect.appendChild(opt);
          }
        });
      });
    }
  })();
</script>
