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
               COALESCE(sa.area_name,'') AS area_name,
               fa.vehicle_type,
               fa.submitted_channel
        FROM franchise_applications fa
        LEFT JOIN operators o ON o.id=fa.operator_id
        LEFT JOIN tricycle_service_areas sa ON sa.id=COALESCE(fa.approved_service_area_id, fa.service_area_id)
        WHERE fa.status='Approved'
          AND (fa.vehicle_type IS NULL OR fa.vehicle_type='Tricycle')
          AND COALESCE(NULLIF(fa.submitted_channel,''),'')<>'PUV_LOCAL_ENDORSEMENT'
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
                <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Requested Units</div>
                <div id="requestedUnits" class="mt-1 font-bold text-slate-900 dark:text-white">-</div>
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
                <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1">Approved Route / Service Area</label>
                <input id="approvedServicePick" name="approved_service_pick" list="approvedServicePickList" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="Select Service Area / TODA Zone">
                <datalist id="approvedServicePickList"></datalist>
                <div id="areaHint" class="mt-1 text-[11px] text-slate-500 dark:text-slate-400 font-semibold"></div>
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1">Approved Units</label>
                <input name="approved_units" type="number" min="1" max="500" step="1" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1">Issue Date</label>
                <input name="issue_date" type="date" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1">Validity (years)</label>
                <input name="validity_years" type="number" min="1" max="5" step="1" value="1" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1">Remarks</label>
                <textarea name="remarks" rows="4" maxlength="1500" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="Notes about approved units/service area decision"></textarea>
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
    const approvedSvcEl = document.getElementById('approvedServicePick');
    const approvedSvcList = document.getElementById('approvedServicePickList');
    const areaHint = document.getElementById('areaHint');
 
    let currentAppId = 0;
    let servicesCache = null;
    let currentRequestedAreaId = 0;
 
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

    async function loadServices() {
      if (servicesCache) return servicesCache;
      const res = await fetch(rootUrl + '/admin/api/module2/routes_list.php');
      const data = await res.json().catch(() => null);
      if (!data || !data.ok) throw new Error('routes_load_failed');
      servicesCache = Array.isArray(data.data) ? data.data : [];
      return servicesCache;
    }

    function rebuildServiceList() {
      if (!approvedSvcList || !approvedSvcEl) return;
      approvedSvcList.innerHTML = '';
      const rows = Array.isArray(servicesCache) ? servicesCache : [];
      const filtered = rows.filter((r) => (r && String(r.kind || '') === 'service_area'));
      const seen = new Set();
      filtered.forEach((r) => {
        const id = Number(r.service_area_id || 0);
        if (!id || seen.has(id)) return;
        seen.add(id);
        const code = String(r.area_code || '');
        const name = String(r.area_name || '');
        const pts = String(r.points || '');
        const cap = Number(r.authorized_units || 0);
        const used = Number(r.used_units || 0);
        const rem = Number(r.remaining_units || 0);
        const label = `${code} • ${name}${pts ? ' • ' + pts : ''} • Slots ${used}/${cap} (Remaining ${rem})`;
        const opt = document.createElement('option');
        opt.value = `${id} - ${label}`;
        approvedSvcList.appendChild(opt);
      });
    }

    function parsePickId(s) {
      const m = (s || '').toString().trim().match(/^(\d+)\s*-/);
      if (!m) return 0;
      return Number(m[1] || 0);
    }

    function updateAreaHint() {
      if (!areaHint || !approvedSvcEl) return;
      const id = parsePickId(approvedSvcEl.value);
      if (!id) { areaHint.textContent = ''; return; }
      const rows = Array.isArray(servicesCache) ? servicesCache : [];
      const r = rows.find(x => String(x.kind || '') === 'service_area' && Number(x.service_area_id || 0) === id);
      if (!r) { areaHint.textContent = ''; return; }
      const cap = Number(r.authorized_units || 0);
      const used = Number(r.used_units || 0);
      const rem = Number(r.remaining_units || 0);
      areaHint.textContent = `Available slot capacity: ${rem} • Existing authorized units: ${cap} • Used: ${used}`;
    }
 
    async function render(app) {
      const id = Number(app.application_id || 0);
      currentAppId = id;
      currentRequestedAreaId = Number(app.service_area_id || 0);
      document.getElementById('appTitle').textContent = 'APP-' + id + ' • ' + (app.franchise_ref_number || '');
      document.getElementById('appSub').textContent = (app.reviewed_at ? ('Reviewed: ' + String(app.reviewed_at).slice(0, 19).replace('T', ' ')) : '');
      document.getElementById('opName').textContent = app.operator_name || '-';
      const area = (app.route_code || '') + (app.origin ? (' • ' + app.origin) : '');
      document.getElementById('areaLabel').textContent = area || '-';
      document.getElementById('requestedUnits').textContent = String(app.vehicle_count || 0);
      document.getElementById('units').textContent = String(app.approved_vehicle_count || app.vehicle_count || 0);
      document.getElementById('status').textContent = app.status || '-';

      try {
        await loadServices();
        rebuildServiceList();
        if (approvedSvcEl) {
          const approvedAreaId = Number(app.approved_service_area_id || 0) || Number(app.service_area_id || 0);
          if (approvedAreaId > 0) {
            const rows = Array.isArray(servicesCache) ? servicesCache : [];
            const r = rows.find(x => String(x.kind || '') === 'service_area' && Number(x.service_area_id || 0) === approvedAreaId);
            if (r) {
              const code = String(r.area_code || '');
              const name = String(r.area_name || '');
              const pts = String(r.points || '');
              const cap = Number(r.authorized_units || 0);
              const used = Number(r.used_units || 0);
              const rem = Number(r.remaining_units || 0);
              const label = `${code} • ${name}${pts ? ' • ' + pts : ''} • Slots ${used}/${cap} (Remaining ${rem})`;
              approvedSvcEl.value = `${approvedAreaId} - ${label}`;
            } else {
              approvedSvcEl.value = `${approvedAreaId} - Service Area`;
            }
          }
          updateAreaHint();
        }
      } catch (_) {}
 
      const today = new Date();
      const iso = today.toISOString().slice(0, 10);
      const issueInput = formIssue ? formIssue.querySelector('input[name="issue_date"]') : null;
      if (issueInput && !issueInput.value) issueInput.value = iso;
      const unitsInput = formIssue ? formIssue.querySelector('input[name="approved_units"]') : null;
      if (unitsInput) unitsInput.value = String(app.approved_vehicle_count || app.vehicle_count || 1);
 
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
        const approvedAreaId = parsePickId(fd.get('approved_service_pick'));
        const approvedUnits = Number(fd.get('approved_units') || 0);
        btnIssue.disabled = true;
        btnIssue.textContent = 'Issuing...';
        try {
          const post = new FormData();
          post.append('application_id', String(currentAppId));
          post.append('approved_service_area_id', String(approvedAreaId || 0));
          post.append('approved_units', String(approvedUnits || 0));
          post.append('remarks', (fd.get('remarks') || '').toString());
          post.append('issue_date', (fd.get('issue_date') || '').toString());
          post.append('validity_years', (fd.get('validity_years') || '1').toString());
          const res = await fetch(rootUrl + '/admin/api/module2/issue_franchise.php', { method: 'POST', body: post });
          const data = await res.json().catch(() => null);
          if (!res.ok || !data || !data.ok) {
            const err = (data && data.error) ? String(data.error) : 'issue_failed';
            let msg;
            if (err === 'already_issued') {
              msg = 'This application is already issued.';
            } else if (err === 'invalid_status') {
              msg = 'Only Approved applications can be issued.';
            } else if (err === 'service_area_over_capacity') {
              msg = 'No available slots in the selected service area.';
            } else if (err === 'missing_service_area') {
              msg = 'Please select an approved service area.';
            } else if (err === 'service_area_inactive') {
              msg = 'The selected service area is inactive. Pick an active service area.';
            } else if (err === 'service_area_not_found') {
              msg = 'Service area record was not found. Check the configured service area.';
            } else if (err === 'tricycle_only') {
              msg = 'Issuance applies only to tricycle applications.';
            } else if (err === 'application_not_found') {
              msg = 'Application record was not found. Reload the page and try again.';
            } else if (err === 'invalid_issue_date') {
              msg = 'Issue date is invalid. Please pick a valid date.';
            } else {
              msg = 'Failed to issue franchise (' + err + ').';
            }
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

    if (approvedSvcEl) {
      approvedSvcEl.addEventListener('change', updateAreaHint);
      approvedSvcEl.addEventListener('blur', updateAreaHint);
      approvedSvcEl.addEventListener('input', () => { if (areaHint) areaHint.textContent = ''; });
    }
 
    const pre = Number(prefillId ? prefillId.value : 0);
    if (pre > 0 && appPick) {
      appPick.value = pre + ' - APP-' + pre;
      doLoad(pre);
    }
  })();
 </script>
