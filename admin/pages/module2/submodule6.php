<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('module2.franchises.manage');
 
require_once __DIR__ . '/../../includes/db.php';
$db = db();
 
$prefillApp = (int)($_GET['application_id'] ?? 0);
 
$apps = [];
$sql = "SELECT fa.application_id, fa.franchise_ref_number, fa.status, fa.submitted_at,
               COALESCE(NULLIF(o.name,''), o.full_name) AS operator_name,
               COALESCE(sa.area_code,'') AS area_code,
               COALESCE(sa.area_name,'') AS area_name
        FROM franchise_applications fa
        LEFT JOIN operators o ON o.id=fa.operator_id
        LEFT JOIN tricycle_service_areas sa ON sa.id=fa.service_area_id
        WHERE fa.status='Approved'
        ORDER BY fa.reviewed_at DESC, fa.submitted_at DESC, fa.application_id DESC
        LIMIT 600";
$res = $db->query($sql);
if ($res) while ($r = $res->fetch_assoc()) $apps[] = $r;
 
$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';
?>
 
<div class="mx-auto max-w-5xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-6">
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between border-b border-slate-200 dark:border-slate-700 pb-6">
    <div>
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Franchise Issuance</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">Issue tricycle franchises for approved applications. Issuance sets Status → Active.</p>
    </div>
    <div class="flex items-center gap-3">
      <a href="?page=module2/submodule1" class="inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
        <i data-lucide="folder-open" class="w-4 h-4"></i>
        Applications
      </a>
      <a href="?page=module2/submodule4" class="inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
        <i data-lucide="clipboard-check" class="w-4 h-4"></i>
        Staff Evaluation
      </a>
    </div>
  </div>
 
  <div id="toast-container" class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 z-[100] flex flex-col gap-3 pointer-events-none"></div>
 
  <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
    <div class="p-6 space-y-6">
      <form id="formPick" class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-end" novalidate>
        <div class="flex-1">
          <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Approved Applications</label>
          <input id="appPick" list="appPickList" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="Select from list (e.g., 123 - APP-123 • AREA • Operator)">
          <datalist id="appPickList">
            <?php foreach ($apps as $a): ?>
              <?php
                $id = (int)($a['application_id'] ?? 0);
                $ref = (string)($a['franchise_ref_number'] ?? '');
                $op = (string)($a['operator_name'] ?? '');
                $code = (string)($a['area_code'] ?? '');
                $name = (string)($a['area_name'] ?? '');
                $label = 'APP-' . $id . ' • ' . ($ref !== '' ? $ref : '-') . ' • ' . ($code !== '' ? $code : '-') . ' • ' . ($name !== '' ? $name : '-') . ' • ' . ($op !== '' ? $op : '-');
              ?>
              <option value="<?php echo htmlspecialchars($id . ' - ' . $label, ENT_QUOTES); ?>"></option>
            <?php endforeach; ?>
          </datalist>
          <input id="prefillId" type="hidden" value="<?php echo (int)$prefillApp; ?>">
        </div>
        <button id="btnLoad" class="px-4 py-2.5 rounded-md bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 dark:hover:bg-slate-600 text-white font-semibold">Load</button>
      </form>
 
      <div id="emptyState" class="text-sm text-slate-500 dark:text-slate-400 italic">Load an application to proceed.</div>
 
      <div id="appWrap" class="hidden grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-4">
          <div class="p-5 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700">
            <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Application</div>
            <div id="appTitle" class="mt-2 text-lg font-black text-slate-900 dark:text-white">-</div>
            <div id="appSub" class="mt-1 text-sm text-slate-600 dark:text-slate-300">-</div>
          </div>
          <div class="p-5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Operator</div>
                <div id="opName" class="mt-1 font-bold text-slate-900 dark:text-white">-</div>
              </div>
              <div>
                <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Service Area / TODA Zone</div>
                <div id="areaLabel" class="mt-1 font-bold text-slate-900 dark:text-white">-</div>
              </div>
              <div>
                <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Approved Units</div>
                <div id="units" class="mt-1 font-bold text-slate-900 dark:text-white">-</div>
              </div>
              <div>
                <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Status</div>
                <div id="status" class="mt-1 font-bold text-slate-900 dark:text-white">-</div>
              </div>
            </div>
          </div>
        </div>
 
        <div class="space-y-4">
          <div class="p-5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
            <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Issuance</div>
            <form id="formIssue" class="space-y-4 mt-4" novalidate>
              <div>
                <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1">Issue Date</label>
                <input name="issue_date" type="date" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1">Validity (years)</label>
                <input name="validity_years" type="number" min="1" max="5" step="1" value="1" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
              </div>
              <button id="btnIssue" class="w-full px-4 py-2.5 rounded-md bg-emerald-700 hover:bg-emerald-800 text-white font-semibold">Issue Franchise</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
 
<script>
  (function(){
    const rootUrl = <?php echo json_encode($rootUrl); ?>;
    const formPick = document.getElementById('formPick');
    const btnLoad = document.getElementById('btnLoad');
    const appPick = document.getElementById('appPick');
    const prefillId = document.getElementById('prefillId');
    const emptyState = document.getElementById('emptyState');
    const appWrap = document.getElementById('appWrap');
    const formIssue = document.getElementById('formIssue');
    const btnIssue = document.getElementById('btnIssue');
 
    let currentAppId = 0;
 
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
 
    async function loadApp(appId) {
      const res = await fetch(rootUrl + '/admin/api/module2/get_application.php?application_id=' + encodeURIComponent(String(appId || '')));
      const data = await res.json();
      if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'load_failed');
      return data.data;
    }
 
    async function render(app) {
      const id = Number(app.application_id || 0);
      currentAppId = id;
      document.getElementById('appTitle').textContent = 'APP-' + id + ' • ' + (app.franchise_ref_number || '');
      document.getElementById('appSub').textContent = (app.reviewed_at ? ('Reviewed: ' + String(app.reviewed_at).slice(0, 19).replace('T', ' ')) : '');
      document.getElementById('opName').textContent = app.operator_name || '-';
      const area = (app.route_code || '') + (app.origin ? (' • ' + app.origin) : '');
      document.getElementById('areaLabel').textContent = area || '-';
      document.getElementById('units').textContent = String(app.approved_vehicle_count || app.vehicle_count || 0);
      document.getElementById('status').textContent = app.status || '-';
 
      const today = new Date();
      const iso = today.toISOString().slice(0, 10);
      const issueInput = formIssue ? formIssue.querySelector('input[name="issue_date"]') : null;
      if (issueInput && !issueInput.value) issueInput.value = iso;
 
      emptyState.classList.add('hidden');
      appWrap.classList.remove('hidden');
    }
 
    async function doLoad(appId) {
      const id = Number(appId || 0);
      if (!id) { showToast('Please select a valid application from the list.', 'error'); return; }
      btnLoad.disabled = true;
      btnLoad.textContent = 'Loading...';
      try {
        const app = await loadApp(id);
        await render(app);
      } catch (e) {
        showToast('Failed to load application.', 'error');
      } finally {
        btnLoad.disabled = false;
        btnLoad.textContent = 'Load';
      }
    }
 
    if (formPick) {
      formPick.addEventListener('submit', (e) => {
        e.preventDefault();
        doLoad(parseId(appPick ? appPick.value : ''));
      });
    }
 
    if (formIssue) {
      formIssue.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!currentAppId) { showToast('Load an application first.', 'error'); return; }
        const fd = new FormData(formIssue);
        btnIssue.disabled = true;
        btnIssue.textContent = 'Issuing...';
        try {
          const post = new FormData();
          post.append('application_id', String(currentAppId));
          post.append('issue_date', (fd.get('issue_date') || '').toString());
          post.append('validity_years', (fd.get('validity_years') || '1').toString());
          const res = await fetch(rootUrl + '/admin/api/module2/issue_franchise.php', { method: 'POST', body: post });
          const data = await res.json().catch(() => null);
          if (!res.ok || !data || !data.ok) {
            const err = (data && data.error) ? String(data.error) : 'issue_failed';
            const msg = err === 'already_issued'
              ? 'This application is already issued.'
              : err === 'invalid_status'
                ? 'Only Approved applications can be issued.'
                : 'Failed to issue franchise.';
            throw new Error(msg);
          }
          showToast('Franchise issued. Certificate: ' + String(data.certificate_no || '-'));
          const params = new URLSearchParams();
          params.set('page', 'module2/submodule1');
          params.set('highlight_application_id', String(currentAppId));
          window.location.href = '?' + params.toString();
        } catch (e) {
          showToast(e.message || 'Failed', 'error');
        } finally {
          btnIssue.disabled = false;
          btnIssue.textContent = 'Issue Franchise';
        }
      });
    }
 
    const pre = Number(prefillId ? prefillId.value : 0);
    if (pre > 0 && appPick) {
      appPick.value = pre + ' - APP-' + pre;
      doLoad(pre);
    }
  })();
 </script>
