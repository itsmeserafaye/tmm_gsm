<?php
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module2.endorse','module2.approve','module2.history']);

require_once __DIR__ . '/../../includes/db.php';
$db = db();

$prefillApp = (int)($_GET['application_id'] ?? 0);

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';
?>

<div class="mx-auto max-w-5xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-6">
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between border-b border-slate-200 dark:border-slate-700 pb-6">
    <div>
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Endorsement & LTFRB Approval</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">Endorse Submitted applications and record LTFRB approval to mark them as LTFRB-Approved.</p>
    </div>
    <div class="flex items-center gap-3">
      <a href="?page=module2/submodule1" class="inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
        <i data-lucide="folder-open" class="w-4 h-4"></i>
        Applications
      </a>
    </div>
  </div>

  <div id="toast-container" class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 z-[100] flex flex-col gap-3 pointer-events-none"></div>

  <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
    <div class="p-6 space-y-6">
      <form id="formLoad" class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-end" novalidate>
        <div class="flex-1 relative">
          <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Submitted Applications</label>
          <input id="appPickInput" type="text" autocomplete="off" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="Search application ref / operator / route…">
          <input id="appIdInput" type="hidden" value="<?php echo (int)$prefillApp; ?>">
          <div id="appPickSuggestions" class="absolute z-10 mt-1 w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-md shadow-xl max-h-64 overflow-y-auto hidden"></div>
        </div>
        <button id="btnLoad" class="px-4 py-2.5 rounded-md bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 dark:hover:bg-slate-600 text-white font-semibold">Load</button>
      </form>

      <div id="appDetails" class="hidden">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
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
                  <div id="opMeta" class="mt-1 text-xs text-slate-500 dark:text-slate-400">-</div>
                </div>
                <div>
                  <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Route</div>
                  <div id="routeLabel" class="mt-1 font-bold text-slate-900 dark:text-white">-</div>
                  <div id="routeMeta" class="mt-1 text-xs text-slate-500 dark:text-slate-400">-</div>
                </div>
                <div>
                  <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Vehicle Count</div>
                  <div id="vehCount" class="mt-1 font-bold text-slate-900 dark:text-white">-</div>
                </div>
                <div>
                  <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Status</div>
                  <div id="appStatus" class="mt-1 font-bold text-slate-900 dark:text-white">-</div>
                </div>
              </div>
            </div>
          </div>
          <div class="space-y-4">
            <div class="p-5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
              <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Endorse</div>
              <form id="formEndorse" class="space-y-4 mt-4" novalidate>
                <textarea name="notes" rows="4" maxlength="500" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., Verified documents; ready for approval."></textarea>
                <button id="btnEndorse" class="w-full px-4 py-2.5 rounded-md bg-violet-700 hover:bg-violet-800 text-white font-semibold">Endorse</button>
              </form>
            </div>
            <div class="p-5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
              <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">LTFRB Approval Entry</div>
              <form id="formApprove" class="space-y-4 mt-4" novalidate>
                <input name="ltfrb_ref_no" required maxlength="40" pattern="^[0-9][0-9\\-\\/]{2,39}$" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., 2026-0001">
                <input name="decision_order_no" required maxlength="40" pattern="^[0-9]{3,40}$" inputmode="numeric" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., 1002003">
                <input name="expiry_date" type="date" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
                <input name="remarks" maxlength="200" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., Valid until expiry date">
                <button id="btnApprove" class="w-full px-4 py-2.5 rounded-md bg-emerald-700 hover:bg-emerald-800 text-white font-semibold">Save Approval</button>
              </form>
            </div>
          </div>
        </div>
      </div>

      <div id="emptyState" class="text-sm text-slate-500 dark:text-slate-400 italic">Load an application to proceed.</div>
    </div>
  </div>
</div>

<script>
  (function(){
    const rootUrl = <?php echo json_encode($rootUrl); ?>;
    const appPickInput = document.getElementById('appPickInput');
    const appIdInput = document.getElementById('appIdInput');
    const appPickSuggestions = document.getElementById('appPickSuggestions');
    const formLoad = document.getElementById('formLoad');
    const btnLoad = document.getElementById('btnLoad');
    const appDetails = document.getElementById('appDetails');
    const emptyState = document.getElementById('emptyState');
    const formEndorse = document.getElementById('formEndorse');
    const formApprove = document.getElementById('formApprove');
    const btnEndorse = document.getElementById('btnEndorse');
    const btnApprove = document.getElementById('btnApprove');

    let currentAppId = 0;
    let currentStatus = '';
    let pickTimer = null;

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

    function setEnabled() {
      btnEndorse.disabled = currentStatus !== 'Submitted';
      btnApprove.disabled = !(['Endorsed','LGU-Endorsed','Approved','LTFRB-Approved'].includes(currentStatus));
    }

    async function loadApp(appId) {
      const res = await fetch(rootUrl + '/admin/api/module2/get_application.php?application_id=' + encodeURIComponent(appId));
      const data = await res.json();
      if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'load_failed');
      return data.data;
    }

    function hidePickSuggestions() {
      if (!appPickSuggestions) return;
      appPickSuggestions.classList.add('hidden');
      appPickSuggestions.innerHTML = '';
    }

    function showPickSuggestions(items) {
      if (!appPickSuggestions) return;
      if (!Array.isArray(items) || items.length === 0) {
        hidePickSuggestions();
        return;
      }
      appPickSuggestions.innerHTML = '';
      items.slice(0, 20).forEach((row) => {
        const id = Number(row.application_id || 0);
        const ref = (row.franchise_ref_number || '').toString();
        const op = (row.operator_name || '').toString();
        const route = (row.route_code || row.route_id || '').toString();
        const od = ((row.origin || row.destination) ? ((row.origin || '') + ' → ' + (row.destination || '')) : '').toString();
        const line1 = `APP-${id} • ${ref || 'No Ref'} • ${route || 'No Route'}`;
        const line2 = `${op || 'Unknown Operator'}${od ? ' • ' + od : ''}`;

        const div = document.createElement('div');
        div.className = 'px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer border-b border-slate-100 dark:border-slate-800 last:border-0';
        div.innerHTML = `
          <div class="font-bold text-slate-800 dark:text-slate-100 text-sm">${line1}</div>
          <div class="text-xs text-slate-500 dark:text-slate-300">${line2}</div>
        `;
        div.addEventListener('click', async () => {
          if (appIdInput) appIdInput.value = String(id);
          if (appPickInput) appPickInput.value = line1;
          hidePickSuggestions();
          btnLoad.click();
        });
        appPickSuggestions.appendChild(div);
      });
      appPickSuggestions.classList.remove('hidden');
    }

    async function fetchSubmittedApps(q) {
      const url = rootUrl + '/admin/api/module2/list_applications.php?status=' + encodeURIComponent('Submitted') + '&limit=50&q=' + encodeURIComponent(q || '');
      const res = await fetch(url);
      const data = await res.json();
      if (!data || !data.ok) return [];
      return Array.isArray(data.data) ? data.data : [];
    }

    if (appPickInput) {
      appPickInput.addEventListener('focus', () => {
        if (pickTimer) clearTimeout(pickTimer);
        pickTimer = setTimeout(async () => {
          const items = await fetchSubmittedApps((appPickInput.value || '').toString().trim());
          showPickSuggestions(items);
        }, 50);
      });
      appPickInput.addEventListener('input', () => {
        if (appIdInput) appIdInput.value = '';
        if (pickTimer) clearTimeout(pickTimer);
        pickTimer = setTimeout(async () => {
          const items = await fetchSubmittedApps((appPickInput.value || '').toString().trim());
          showPickSuggestions(items);
        }, 200);
      });
    }

    document.addEventListener('click', (e) => {
      const t = e && e.target;
      if (!t) return;
      if (appPickSuggestions && (appPickSuggestions === t || appPickSuggestions.contains(t))) return;
      if (appPickInput && (appPickInput === t || appPickInput.contains(t))) return;
      hidePickSuggestions();
    });

    function render(a) {
      currentAppId = Number(a.application_id || 0);
      currentStatus = (a.status || '').toString().trim();
      if (appIdInput) appIdInput.value = String(currentAppId);
      if (appPickInput) appPickInput.value = 'APP-' + currentAppId;
      document.getElementById('appTitle').textContent = 'APP-' + currentAppId;
      document.getElementById('appSub').textContent = (a.franchise_ref_number || '').toString();
      document.getElementById('opName').textContent = (a.operator_name || '').toString();
      document.getElementById('opMeta').textContent = 'Type: ' + (a.operator_type || '-') + ' • Status: ' + (a.operator_status || '-');
      const routeLabel = (a.route_code || '-') + ((a.origin || a.destination) ? (' • ' + (a.origin || '') + ' → ' + (a.destination || '')) : '');
      document.getElementById('routeLabel').textContent = routeLabel;
      document.getElementById('routeMeta').textContent = 'Route status: ' + (a.route_status || '-');
      document.getElementById('vehCount').textContent = String(Number(a.vehicle_count || 0));
      document.getElementById('appStatus').textContent = currentStatus || '-';
      appDetails.classList.remove('hidden');
      emptyState.classList.add('hidden');
      setEnabled();
    }

    if (formLoad) {
      formLoad.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = Number((appIdInput && appIdInput.value) ? appIdInput.value : 0);
        if (!id) { showToast('Enter a valid application ID.', 'error'); return; }
        btnLoad.disabled = true;
        btnLoad.textContent = 'Loading...';
        try {
          const a = await loadApp(id);
          render(a);
        } catch (err) {
          showToast(err.message || 'Failed', 'error');
        } finally {
          btnLoad.disabled = false;
          btnLoad.textContent = 'Load';
        }
      });
    }

    if (formEndorse) {
      formEndorse.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!currentAppId) return;
        if (currentStatus !== 'Submitted') { showToast('Only Submitted applications can be endorsed.', 'error'); return; }
        btnEndorse.disabled = true;
        btnEndorse.textContent = 'Endorsing...';
        try {
          const fd = new FormData(formEndorse);
          fd.append('application_id', String(currentAppId));
          const res = await fetch(rootUrl + '/admin/api/module2/endorse_app.php', { method: 'POST', body: fd });
          const data = await res.json();
          if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'endorse_failed');
          showToast('Application endorsed.');
          const a = await loadApp(currentAppId);
          render(a);
        } catch (err) {
          showToast(err.message || 'Failed', 'error');
        } finally {
          btnEndorse.textContent = 'Endorse';
          setEnabled();
        }
      });
    }

    if (formApprove) {
      const ltfrbEl = formApprove.querySelector('input[name="ltfrb_ref_no"]');
      const doEl = formApprove.querySelector('input[name="decision_order_no"]');
      if (ltfrbEl) {
        ltfrbEl.addEventListener('input', () => {
          ltfrbEl.value = (ltfrbEl.value || '').toString().replace(/[^0-9\/-]+/g, '').slice(0, 40);
        });
      }
      if (doEl) {
        doEl.addEventListener('input', () => {
          doEl.value = (doEl.value || '').toString().replace(/\D+/g, '').slice(0, 40);
        });
      }
      formApprove.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!currentAppId) return;
        if (!(['Endorsed','LGU-Endorsed','Approved','LTFRB-Approved'].includes(currentStatus))) { showToast('Endorse the application first.', 'error'); return; }
        if (!formApprove.checkValidity()) { formApprove.reportValidity(); return; }
        btnApprove.disabled = true;
        btnApprove.textContent = 'Saving...';
        try {
          const fd = new FormData(formApprove);
          fd.append('application_id', String(currentAppId));
          const res = await fetch(rootUrl + '/admin/api/module2/approve_application.php', { method: 'POST', body: fd });
          const data = await res.json();
          if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'approve_failed');
          showToast('Application approved.');
          const a = await loadApp(currentAppId);
          render(a);
        } catch (err) {
          const code = (err && err.message) ? String(err.message) : '';
          const msg = (function(){
            if (code === 'orcr_required_for_approval') return 'Approval requires enough verified OR/CR documents for the requested vehicle count.';
            if (code === 'no_linked_vehicles') return 'Operator has no linked vehicles. Link vehicles first.';
            if (code === 'duplicate_ltfrb_ref_no') return 'LTFRB Ref No already exists.';
            if (code === 'invalid_status') return 'Application status is not eligible for approval.';
            if (code === 'invalid_ltfrb_ref_no') return 'Invalid LTFRB Ref No format.';
            if (code === 'invalid_decision_order_no') return 'Decision Order No must be numeric.';
            return code || 'Failed';
          })();
          showToast(msg, 'error');
        } finally {
          btnApprove.textContent = 'Save Approval';
          setEnabled();
        }
      });
    }

    if (<?php echo json_encode($prefillApp > 0); ?>) {
      formLoad.dispatchEvent(new Event('submit'));
    }
  })();
</script>
