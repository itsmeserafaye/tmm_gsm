<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('module4.schedule');

require_once __DIR__ . '/../../includes/db.php';
$db = db();

$prefillVehicleId = (int)($_GET['vehicle_id'] ?? 0);

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';
?>

<div class="mx-auto max-w-3xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-6">
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between border-b border-slate-200 dark:border-slate-700 pb-6">
    <div>
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Register Vehicle</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">Vehicle must already exist in PUV DB and must be linked to an operator.</p>
    </div>
    <div class="flex items-center gap-3">
      <a href="?page=module4/submodule1" class="inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
        <i data-lucide="list" class="w-4 h-4"></i>
        Back to List
      </a>
    </div>
  </div>

  <div id="toast-container" class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 z-[100] flex flex-col gap-3 pointer-events-none"></div>

  <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
    <div class="p-6 space-y-5">
      <form id="formRegister" class="space-y-6" novalidate enctype="multipart/form-data">
        <input type="hidden" name="vehicle_id" id="vehicleId" value="<?php echo (int)$prefillVehicleId; ?>">

        <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700">
          <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Step 1: Search Vehicle (PUV Database)</div>
          <div class="mt-3 flex flex-col sm:flex-row gap-2">
            <input id="vehSearch" class="flex-1 px-4 py-2.5 rounded-md bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="Search plate / engine / chassis / operator">
            <button type="button" id="btnVehSearch" class="px-4 py-2.5 rounded-md bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 dark:hover:bg-slate-600 text-white text-sm font-bold">Search</button>
          </div>
          <div id="vehResults" class="mt-3 hidden"></div>
        </div>

        <div id="vehInfo" class="hidden p-4 rounded-xl bg-white dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700">
          <div class="flex items-start justify-between gap-4">
            <div>
              <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Vehicle Info (Read-only)</div>
              <div id="vehTitle" class="mt-1 text-lg font-black text-slate-900 dark:text-white"></div>
              <div id="vehSub" class="mt-1 text-sm font-semibold text-slate-600 dark:text-slate-300"></div>
            </div>
            <a id="vehCrLink" href="#" target="_blank" class="hidden px-4 py-2.5 rounded-md bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 text-sm font-bold">View CR</a>
          </div>
          <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
            <div class="p-3 rounded-lg bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700">
              <div class="text-xs font-black text-slate-500 dark:text-slate-400">Engine No</div>
              <div id="vehEngine" class="mt-1 font-black text-slate-900 dark:text-white"></div>
            </div>
            <div class="p-3 rounded-lg bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700">
              <div class="text-xs font-black text-slate-500 dark:text-slate-400">Chassis No</div>
              <div id="vehChassis" class="mt-1 font-black text-slate-900 dark:text-white"></div>
            </div>
          </div>
          <div class="mt-4 p-3 rounded-lg bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700">
            <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">CR Metadata</div>
            <div class="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
              <div>
                <div class="text-xs font-black text-slate-500 dark:text-slate-400">CR Number</div>
                <div id="vehCrNo" class="mt-1 font-black text-slate-900 dark:text-white"></div>
              </div>
              <div>
                <div class="text-xs font-black text-slate-500 dark:text-slate-400">CR Issue Date</div>
                <div id="vehCrDate" class="mt-1 font-black text-slate-900 dark:text-white"></div>
              </div>
              <div>
                <div class="text-xs font-black text-slate-500 dark:text-slate-400">Registered Owner</div>
                <div id="vehOwner" class="mt-1 font-black text-slate-900 dark:text-white"></div>
              </div>
            </div>
          </div>
        </div>

        <div id="regWrap" class="hidden p-4 rounded-xl bg-white dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700">
          <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Step 2: Registration (OR)</div>
          <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">OR Number</label>
              <input name="or_number" id="orNumber" minlength="3" maxlength="64" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., OR-2026-0001">
            </div>
            <div>
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">OR Date</label>
              <input name="or_date" id="orDate" type="date" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
            </div>
            <div>
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">OR Expiry Date</label>
              <input name="or_expiry_date" id="orExpiry" type="date" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
            </div>
            <div>
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Registration Year</label>
              <input name="registration_year" id="regYear" inputmode="numeric" maxlength="4" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., 2026">
            </div>
            <div class="sm:col-span-2">
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Upload OR (required for activation)</label>
              <input name="or_file" id="orFile" type="file" accept=".pdf,.jpg,.jpeg,.png" class="w-full text-sm">
              <div class="mt-1 text-[11px] font-semibold text-slate-500 dark:text-slate-400">OR missing → Registered but INACTIVE. OR expired → Registration EXPIRED.</div>
            </div>
          </div>
          <div class="mt-4 p-3 rounded-lg bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700">
            <div class="text-xs font-black text-slate-500 dark:text-slate-400">System-controlled Status</div>
            <div id="regStatusHint" class="mt-1 text-sm font-black text-slate-900 dark:text-white">Pending</div>
          </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2">
          <button id="btnRegister" class="px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  (function(){
    const rootUrl = <?php echo json_encode($rootUrl); ?>;
    const form = document.getElementById('formRegister');
    const btn = document.getElementById('btnRegister');
    const vehIdEl = document.getElementById('vehicleId');
    const vehSearch = document.getElementById('vehSearch');
    const btnVehSearch = document.getElementById('btnVehSearch');
    const vehResults = document.getElementById('vehResults');
    const vehInfo = document.getElementById('vehInfo');
    const regWrap = document.getElementById('regWrap');
    const vehTitle = document.getElementById('vehTitle');
    const vehSub = document.getElementById('vehSub');
    const vehEngine = document.getElementById('vehEngine');
    const vehChassis = document.getElementById('vehChassis');
    const vehCrNo = document.getElementById('vehCrNo');
    const vehCrDate = document.getElementById('vehCrDate');
    const vehOwner = document.getElementById('vehOwner');
    const vehCrLink = document.getElementById('vehCrLink');
    const orNumber = document.getElementById('orNumber');
    const orDate = document.getElementById('orDate');
    const orExpiry = document.getElementById('orExpiry');
    const regYear = document.getElementById('regYear');
    const orFile = document.getElementById('orFile');
    const regStatusHint = document.getElementById('regStatusHint');

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

    const esc = (s) => (s === null || s === undefined) ? '' : String(s).replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

    function computeStatus() {
      const exp = (orExpiry && orExpiry.value) ? String(orExpiry.value) : '';
      const hasOrFile = !!(orFile && orFile.files && orFile.files.length > 0);
      const hasMeta = !!((orNumber && orNumber.value && orNumber.value.trim() !== '') || (orDate && orDate.value && orDate.value.trim() !== ''));
      const today = new Date();
      const todayYmd = today.toISOString().slice(0, 10);
      if (exp && exp < todayYmd) return 'Expired';
      if (hasOrFile || hasMeta) return 'Registered';
      return 'Pending';
    }

    function updateStatusHint() {
      if (!regStatusHint) return;
      const st = computeStatus();
      regStatusHint.textContent = st;
    }

    if (orNumber) orNumber.addEventListener('input', updateStatusHint);
    if (orDate) orDate.addEventListener('change', updateStatusHint);
    if (orExpiry) orExpiry.addEventListener('change', updateStatusHint);
    if (orFile) orFile.addEventListener('change', updateStatusHint);
    if (regYear) regYear.addEventListener('input', () => { regYear.value = String(regYear.value || '').replace(/\D+/g,'').slice(0,4); });

    async function loadVehicleInfo(vehicleId) {
      const res = await fetch(rootUrl + '/admin/api/module4/vehicle_info.php?vehicle_id=' + encodeURIComponent(String(vehicleId)));
      const data = await res.json().catch(() => null);
      if (!data || !data.ok || !data.data) throw new Error((data && data.error) ? data.error : 'load_failed');
      const v = data.data.vehicle || {};
      const r = data.data.registration || {};
      if (vehTitle) vehTitle.textContent = String(v.plate_number || '-') + (v.vehicle_type ? (' • ' + String(v.vehicle_type)) : '');
      if (vehSub) vehSub.textContent = String(v.operator_name || '-');
      if (vehEngine) vehEngine.textContent = String(v.engine_no || '-');
      if (vehChassis) vehChassis.textContent = String(v.chassis_no || '-');
      if (vehCrNo) vehCrNo.textContent = String(v.cr_number || '-');
      if (vehCrDate) vehCrDate.textContent = String(v.cr_issue_date || '-');
      if (vehOwner) vehOwner.textContent = String(v.registered_owner || '-');
      if (vehCrLink) {
        const fp = String(v.cr_file_path || '');
        if (fp) {
          vehCrLink.href = rootUrl + '/admin/uploads/' + encodeURIComponent(fp);
          vehCrLink.classList.remove('hidden');
        } else {
          vehCrLink.classList.add('hidden');
        }
      }
      if (orNumber) orNumber.value = String(r.or_number || '');
      if (orDate) orDate.value = String(r.or_date || '');
      if (orExpiry) orExpiry.value = String(r.or_expiry_date || '');
      if (regYear) regYear.value = String(r.registration_year || '');
      if (vehInfo) vehInfo.classList.remove('hidden');
      if (regWrap) regWrap.classList.remove('hidden');
      updateStatusHint();
    }

    async function doSearch() {
      const q = (vehSearch && vehSearch.value) ? String(vehSearch.value).trim() : '';
      if (!vehResults) return;
      vehResults.classList.remove('hidden');
      vehResults.innerHTML = '<div class="text-sm text-slate-500 italic">Loading...</div>';
      const res = await fetch(rootUrl + '/admin/api/module4/search_vehicles.php?q=' + encodeURIComponent(q));
      const data = await res.json().catch(() => null);
      const rows = data && data.ok && Array.isArray(data.data) ? data.data : [];
      if (!rows.length) {
        vehResults.innerHTML = '<div class="text-sm text-slate-500 italic">No matches.</div>';
        return;
      }
      vehResults.innerHTML = rows.map((r) => {
        const id = Number(r.id || 0);
        const plate = esc(r.plate_number || '-');
        const engine = esc(r.engine_no || '');
        const op = esc(r.operator_name || '-');
        return `<button type="button" class="w-full text-left p-3 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:border-blue-300 dark:hover:border-blue-700 hover:bg-blue-50/30 dark:hover:bg-blue-900/10 transition-all mb-2" data-pick-id="${id}">
          <div class="flex items-center justify-between gap-2">
            <div class="font-black text-slate-900 dark:text-white">${plate}</div>
            <div class="text-xs font-bold text-slate-500 dark:text-slate-400">#${id}</div>
          </div>
          <div class="mt-1 text-xs text-slate-600 dark:text-slate-300 font-semibold">${op}</div>
          <div class="mt-1 text-[11px] text-slate-500 dark:text-slate-400 font-semibold">${engine ? ('Engine: ' + engine) : ''}</div>
        </button>`;
      }).join('');
      vehResults.querySelectorAll('[data-pick-id]').forEach((b) => {
        b.addEventListener('click', async () => {
          const id = Number(b.getAttribute('data-pick-id') || 0);
          if (!id) return;
          if (vehIdEl) vehIdEl.value = String(id);
          try {
            await loadVehicleInfo(id);
            showToast('Vehicle selected.', 'success');
          } catch (e) {
            showToast((e && e.message) ? String(e.message) : 'Failed', 'error');
          }
        });
      });
    }

    if (btnVehSearch) btnVehSearch.addEventListener('click', () => { doSearch().catch(() => {}); });
    if (vehSearch) {
      vehSearch.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); doSearch().catch(() => {}); }
      });
    }

    const preId = Number(vehIdEl && vehIdEl.value ? vehIdEl.value : 0);
    if (preId > 0) {
      loadVehicleInfo(preId).catch(() => {});
    }

    if (form && btn) {
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const vehicleId = Number(vehIdEl && vehIdEl.value ? vehIdEl.value : 0);
        if (!vehicleId) { showToast('Select a vehicle from PUV Database.', 'error'); return; }
        if (orFile && orFile.files && orFile.files.length > 0) {
          if (!orExpiry || !orExpiry.value) { showToast('OR expiry date is required when uploading OR.', 'error'); return; }
        }
        if (!form.checkValidity()) { form.reportValidity(); return; }
        const post = new FormData(form);
        post.set('vehicle_id', String(vehicleId));
        btn.disabled = true;
        btn.textContent = 'Saving...';
        try {
          const res = await fetch(rootUrl + '/admin/api/module4/register_vehicle.php', { method: 'POST', body: post });
          const data = await res.json();
          if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'save_failed');
          showToast('Registration saved. Status: ' + String(data.registration_status || 'OK'));
          setTimeout(() => { window.location.href = '?page=module4/submodule1'; }, 400);
        } catch (err) {
          showToast(err.message || 'Failed', 'error');
          btn.disabled = false;
          btn.textContent = 'Save';
        }
      });
    }
  })();
</script>
