<?php
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module1.read','module1.write']);

require_once __DIR__ . '/../../includes/db.php';
$db = db();

$prefillPlate = strtoupper(trim((string)($_GET['plate'] ?? '')));

$vehicles = [];
$resV = $db->query("SELECT id, plate_number, status, created_at FROM vehicles ORDER BY created_at DESC LIMIT 500");
if ($resV) {
  while ($r = $resV->fetch_assoc()) {
    $p = strtoupper(trim((string)($r['plate_number'] ?? '')));
    if ($p === '') continue;
    $vehicles[] = [
      'vehicle_id' => (int)($r['id'] ?? 0),
      'plate_number' => $p,
      'status' => (string)($r['status'] ?? ''),
    ];
  }
}

$operators = [];
$resO = $db->query("SELECT id, COALESCE(NULLIF(name,''), full_name) AS display_name, operator_type, status FROM operators ORDER BY created_at DESC LIMIT 500");
if ($resO) {
  while ($r = $resO->fetch_assoc()) {
    $id = (int)($r['id'] ?? 0);
    $nm = trim((string)($r['display_name'] ?? ''));
    if ($id <= 0 || $nm === '') continue;
    $operators[] = [
      'operator_id' => $id,
      'display_name' => $nm,
      'operator_type' => (string)($r['operator_type'] ?? ''),
      'status' => (string)($r['status'] ?? ''),
    ];
  }
}

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';
?>

<div class="mx-auto max-w-4xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-6">
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between border-b border-slate-200 dark:border-slate-700 pb-6">
    <div>
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Link Vehicle to Operator</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">Attach an existing vehicle record to an operator record. This is the Module 1 linking step.</p>
    </div>
    <div class="flex items-center gap-3">
      <a href="?page=module1/submodule2" class="inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
        <i data-lucide="bus" class="w-4 h-4"></i>
        Vehicles
      </a>
      <a href="?page=module1/submodule1" class="inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
        <i data-lucide="users" class="w-4 h-4"></i>
        Operators
      </a>
    </div>
  </div>

  <div id="toast-container" class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 z-[100] flex flex-col gap-3 pointer-events-none"></div>

  <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
    <div class="p-6">
      <form id="formLink" class="space-y-5" novalidate>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Vehicle (Plate)</label>
            <input name="plate_number" list="vehiclePlateList" value="<?php echo htmlspecialchars($prefillPlate); ?>" required minlength="5" maxlength="12" pattern="^[A-Za-z0-9\\-\\s]{5,12}$" autocapitalize="characters" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold uppercase" placeholder="e.g., ABC-1234">
            <datalist id="vehiclePlateList">
              <?php foreach ($vehicles as $v): ?>
                <option value="<?php echo htmlspecialchars($v['plate_number'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($v['plate_number'] . ' • ' . $v['status']); ?></option>
              <?php endforeach; ?>
            </datalist>
          </div>
          <div>
            <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Operator</label>
            <input name="operator_pick" list="operatorPickList" required minlength="3" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., 123 - Juan Dela Cruz">
            <datalist id="operatorPickList">
              <?php foreach ($operators as $o): ?>
                <option value="<?php echo htmlspecialchars($o['operator_id'] . ' - ' . $o['display_name'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($o['operator_type'] . ' • ' . $o['status']); ?></option>
              <?php endforeach; ?>
            </datalist>
          </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2">
          <button type="submit" id="btnLink" class="px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold">Link</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  (function(){
    const rootUrl = <?php echo json_encode($rootUrl); ?>;
    const form = document.getElementById('formLink');
    const btn = document.getElementById('btnLink');
    const plateInput = form ? form.querySelector('input[name=\"plate_number\"]') : null;
    const normalizePlate = (value) => {
      const v = (value || '').toString().toUpperCase().replace(/\\s+/g, '');
      if (v.includes('-')) return v;
      if (v.length >= 6) return v.slice(0, 3) + '-' + v.slice(3);
      return v;
    };
    if (plateInput) {
      plateInput.addEventListener('input', () => { plateInput.value = normalizePlate(plateInput.value); });
      plateInput.addEventListener('blur', () => { plateInput.value = normalizePlate(plateInput.value); });
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

    function parseOperatorId(s) {
      const m = (s || '').toString().trim().match(/^(\d+)\s*-/);
      if (!m) return 0;
      return Number(m[1] || 0);
    }

    if (form && btn) {
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!form.checkValidity()) { form.reportValidity(); return; }

        const fd = new FormData(form);
        const plate = (fd.get('plate_number') || '').toString().trim().toUpperCase();
        const opPick = (fd.get('operator_pick') || '').toString().trim();
        const operatorId = parseOperatorId(opPick);
        const operatorName = operatorId > 0 ? '' : opPick;
        if (!plate) { showToast('Select a vehicle plate.', 'error'); return; }
        if (!operatorId && !operatorName) { showToast('Select an operator.', 'error'); return; }

        btn.disabled = true;
        btn.textContent = 'Linking...';

        try {
          const post = new FormData();
          post.append('plate_number', plate);
          if (operatorId > 0) post.append('operator_id', String(operatorId));
          if (operatorName) post.append('operator_name', operatorName);

          const res = await fetch(rootUrl + '/admin/api/module1/link_vehicle_operator.php', { method: 'POST', body: post });
          const data = await res.json();
          if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'link_failed');

          showToast('Vehicle linked successfully.');
          const params = new URLSearchParams();
          params.set('page', 'module1/submodule2');
          params.set('highlight_plate', plate);
          window.location.href = '?' + params.toString();
        } catch (err) {
          showToast(err.message || 'Failed to link', 'error');
          btn.disabled = false;
          btn.textContent = 'Link';
        }
      });
    }
  })();
</script>
