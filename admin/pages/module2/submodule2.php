<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('module2.apply');

require_once __DIR__ . '/../../includes/db.php';
$db = db();

$operators = [];
$resO = $db->query("SELECT id, COALESCE(NULLIF(name,''), full_name) AS display_name, operator_type, status FROM operators ORDER BY created_at DESC LIMIT 800");
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

$routes = [];
$resR = $db->query("SELECT id, route_id, origin, destination, status FROM routes WHERE status='Active' ORDER BY route_id ASC LIMIT 800");
if ($resR) {
  while ($r = $resR->fetch_assoc()) {
    $id = (int)($r['id'] ?? 0);
    $code = trim((string)($r['route_id'] ?? ''));
    if ($id <= 0 || $code === '') continue;
    $routes[] = [
      'route_id' => $id,
      'route_code' => $code,
      'origin' => (string)($r['origin'] ?? ''),
      'destination' => (string)($r['destination'] ?? ''),
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
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Submit Franchise Application</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">Select an operator and proposed route, set the requested vehicle count, and attach supporting documents.</p>
    </div>
    <div class="flex items-center gap-3">
      <a href="?page=module2/submodule1" class="inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
        <i data-lucide="folder-open" class="w-4 h-4"></i>
        Back to List
      </a>
    </div>
  </div>

  <div id="toast-container" class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 z-[100] flex flex-col gap-3 pointer-events-none"></div>

  <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
    <div class="p-6 space-y-6">
      <form id="formSubmitApp" class="space-y-5" novalidate>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Operator</label>
            <input name="operator_pick" list="operatorPickList" required minlength="3" pattern="^\\d+\\s*-\\s*.+$" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., 123 - Juan Dela Cruz">
            <datalist id="operatorPickList">
              <?php foreach ($operators as $o): ?>
                <option value="<?php echo htmlspecialchars($o['operator_id'] . ' - ' . $o['display_name'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($o['operator_type'] . ' • ' . $o['status']); ?></option>
              <?php endforeach; ?>
            </datalist>
          </div>
          <div>
            <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Route</label>
            <input name="route_pick" list="routePickList" required minlength="3" pattern="^\\d+\\s*-\\s*.+$" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., 45 - R_001 • Origin → Destination">
            <datalist id="routePickList">
              <?php foreach ($routes as $r): ?>
                <?php $label = $r['route_code'] . ' • ' . trim($r['origin'] . ' → ' . $r['destination']); ?>
                <option value="<?php echo htmlspecialchars($r['route_id'] . ' - ' . $label, ENT_QUOTES); ?>"></option>
              <?php endforeach; ?>
            </datalist>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Vehicle Count</label>
            <input name="vehicle_count" type="number" min="1" max="500" step="1" value="1" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., 10">
          </div>
          <div>
            <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Representative Name (optional)</label>
            <input name="representative_name" maxlength="120" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., Juan Dela Cruz (authorized representative)">
          </div>
        </div>

        <div class="border-t border-slate-200 dark:border-slate-700 pt-5">
          <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-3">Supporting Documents (optional)</div>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Doc 1</label>
              <input name="doc_ltfrb" type="file" accept=".pdf,.jpg,.jpeg,.png,.xlsx,.xls,.csv" class="w-full text-sm">
            </div>
            <div>
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Doc 2</label>
              <input name="doc_coop" type="file" accept=".pdf,.jpg,.jpeg,.png,.xlsx,.xls,.csv" class="w-full text-sm">
            </div>
            <div>
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Doc 3</label>
              <input name="doc_members" type="file" accept=".pdf,.jpg,.jpeg,.png,.xlsx,.xls,.csv" class="w-full text-sm">
            </div>
          </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2">
          <button id="btnSubmitApp" class="px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold">Submit</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  (function(){
    const rootUrl = <?php echo json_encode($rootUrl); ?>;
    const form = document.getElementById('formSubmitApp');
    const btn = document.getElementById('btnSubmitApp');

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
      if (!m) return 0;
      return Number(m[1] || 0);
    }

    if (form && btn) {
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!form.checkValidity()) { form.reportValidity(); return; }
        const fd = new FormData(form);
        const operatorId = parseId(fd.get('operator_pick'));
        const routeId = parseId(fd.get('route_pick'));
        const vehicleCount = Number(fd.get('vehicle_count') || 0);
        if (!operatorId || !routeId || !vehicleCount) { showToast('Missing required fields.', 'error'); return; }

        btn.disabled = true;
        btn.textContent = 'Submitting...';

        try {
          const post = new FormData();
          post.append('operator_id', String(operatorId));
          post.append('route_id', String(routeId));
          post.append('vehicle_count', String(vehicleCount));
          post.append('representative_name', (fd.get('representative_name') || '').toString());

          const res = await fetch(rootUrl + '/admin/api/module2/save_application.php', { method: 'POST', body: post });
          const data = await res.json();
          if (!data || !data.ok || !data.application_id) throw new Error((data && data.error) ? data.error : 'submit_failed');

          const appId = Number(data.application_id);
          const hasDocs = ['doc_ltfrb','doc_coop','doc_members'].some((k) => fd.get(k) && fd.get(k).name);
          if (hasDocs) {
            const docs = new FormData();
            docs.append('application_id', String(appId));
            ['doc_ltfrb','doc_coop','doc_members'].forEach((k) => {
              const f = fd.get(k);
              if (f && f.name) docs.append(k, f);
            });
            const res2 = await fetch(rootUrl + '/admin/api/module2/upload_app_docs.php', { method: 'POST', body: docs });
            const data2 = await res2.json();
            if (!data2 || !data2.ok) throw new Error((data2 && data2.error) ? data2.error : 'upload_failed');
          }

          showToast('Application submitted.');
          const params = new URLSearchParams();
          params.set('page', 'module2/submodule1');
          params.set('highlight_application_id', String(appId));
          window.location.href = '?' + params.toString();
        } catch (err) {
          showToast(err.message || 'Failed', 'error');
          btn.disabled = false;
          btn.textContent = 'Submit';
        }
      });
    }
  })();
</script>
