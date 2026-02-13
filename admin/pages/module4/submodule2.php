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
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">To operate as a PUV: CR (ownership) + CMVI passed inspection + active franchise + OR + CTPL insurance.</p>
    </div>
    <div class="flex items-center gap-3">
      <a href="?page=module4/submodule1" class="inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
        <i data-lucide="list" class="w-4 h-4"></i>
        Back to List
      </a>
    </div>
  </div>

  <div id="toast-container" class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 z-[100] flex flex-col gap-3 pointer-events-none"></div>

  <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-visible">
    <div class="p-6 space-y-5">
      <form id="formRegister" class="space-y-6" novalidate enctype="multipart/form-data">
        <input type="hidden" name="vehicle_id" id="vehicleId" value="<?php echo (int)$prefillVehicleId; ?>">

        <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700">
          <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Step 1: Select Vehicle (PUV Database)</div>
          <div class="mt-3 relative">
            <button type="button" id="vehDropdownBtn" class="w-full px-4 py-2.5 rounded-md bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold text-left flex items-center justify-between gap-3">
              <span id="vehDropdownLabel" class="truncate">Select vehicle</span>
              <i data-lucide="chevron-down" class="w-4 h-4 text-slate-500"></i>
            </button>
            <div id="vehDropdownPanel" class="hidden absolute z-50 mt-2 w-full rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-xl overflow-hidden">
              <div class="p-3 border-b border-slate-200 dark:border-slate-700">
                <div class="flex items-center gap-2">
                  <input id="vehDropdownSearch" class="flex-1 px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="Search plate / engine / chassis / operator">
                  <button type="button" id="btnVehSearch" class="px-4 py-2.5 rounded-md bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 dark:hover:bg-slate-600 text-white text-sm font-bold">Search</button>
                </div>
              </div>
              <div id="vehDropdownList" class="max-h-80 overflow-auto p-2"></div>
            </div>
          </div>
        </div>

        <div id="vehInfo" class="hidden p-4 rounded-xl bg-white dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700">
          <div class="flex items-start justify-between gap-4">
            <div>
              <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Vehicle Info (Read-only)</div>
              <div id="vehTitle" class="mt-1 text-lg font-black text-slate-900 dark:text-white"></div>
              <div id="vehSub" class="mt-1 text-sm font-semibold text-slate-600 dark:text-slate-300"></div>
            </div>
            <div class="flex items-center gap-2">
              <div id="vehCrLoaded" class="hidden px-3 py-2 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300 text-xs font-black flex items-center gap-2">
                <i data-lucide="file-check" class="w-4 h-4"></i>
                <span>CR file loaded</span>
              </div>
            </div>
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
          <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Step 2: LTO Registration (OR) + CTPL Insurance</div>
          <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">OR Number</label>
              <input name="or_number" id="orNumber" inputmode="numeric" minlength="6" maxlength="12" pattern="^[0-9]{6,12}$" data-tmm-filter="digits" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" readonly>
            </div>
            <div>
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">OR Date</label>
              <input name="or_date" id="orDate" type="date" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" disabled>
            </div>
            <div>
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">OR Expiry Date</label>
              <input name="or_expiry_date" id="orExpiry" type="date" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" disabled>
            </div>
            <div>
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Registration Year</label>
              <input name="registration_year" id="regYear" inputmode="numeric" maxlength="4" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" readonly>
            </div>
            <div class="sm:col-span-2">
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">OR Document</label>
              <div id="orExistingBox" class="hidden mt-2 p-3 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800">
                <div class="flex items-center gap-2 text-emerald-700 dark:text-emerald-300 text-sm font-bold">
                  <i data-lucide="file-check" class="w-4 h-4"></i>
                  <span>OR file loaded</span>
                </div>
              </div>
              <div class="mt-1 text-[11px] font-semibold text-slate-500 dark:text-slate-400">OR missing/expired → cannot activate for PUV operations.</div>
            </div>
            <div>
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Insurance Expiry Date (CTPL)</label>
              <input name="insurance_expiry_date" id="insExpiry" type="date" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" disabled>
            </div>
            <div class="sm:col-span-1">
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Insurance Document (CTPL)</label>
              <div id="insExistingBox" class="hidden mt-2 p-3 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800">
                <div class="flex items-center gap-2 text-emerald-700 dark:text-emerald-300 text-sm font-bold">
                  <i data-lucide="file-check" class="w-4 h-4"></i>
                  <span>Insurance file loaded</span>
                </div>
              </div>
              <div class="mt-1 text-[11px] font-semibold text-slate-500 dark:text-slate-400">Insurance missing/expired → cannot activate for PUV operations.</div>
            </div>
          </div>
          <div class="mt-4 p-3 rounded-lg bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700">
            <div class="text-xs font-black text-slate-500 dark:text-slate-400">System-controlled Status</div>
            <div id="regStatusHint" class="mt-1 text-sm font-black text-slate-900 dark:text-white">Pending</div>
            <div id="opStatusHint" class="mt-1 text-xs font-bold text-slate-500 dark:text-slate-400">Activation requires: CR + CMVI Passed + Active Franchise + OR + CTPL Insurance.</div>
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
    const vehDropdownBtn = document.getElementById('vehDropdownBtn');
    const vehDropdownLabel = document.getElementById('vehDropdownLabel');
    const vehDropdownPanel = document.getElementById('vehDropdownPanel');
    const vehDropdownSearch = document.getElementById('vehDropdownSearch');
    const btnVehSearch = document.getElementById('btnVehSearch');
    const vehDropdownList = document.getElementById('vehDropdownList');
    const vehInfo = document.getElementById('vehInfo');
    const regWrap = document.getElementById('regWrap');
    const vehTitle = document.getElementById('vehTitle');
    const vehSub = document.getElementById('vehSub');
    const vehEngine = document.getElementById('vehEngine');
    const vehChassis = document.getElementById('vehChassis');
    const vehCrNo = document.getElementById('vehCrNo');
    const vehCrDate = document.getElementById('vehCrDate');
    const vehOwner = document.getElementById('vehOwner');
    const vehCrLoaded = document.getElementById('vehCrLoaded');
    const orNumber = document.getElementById('orNumber');
    const orDate = document.getElementById('orDate');
    const orExpiry = document.getElementById('orExpiry');
    const regYear = document.getElementById('regYear');
    const orFile = document.getElementById('orFile');
    const insExpiry = document.getElementById('insExpiry');
    const insFile = document.getElementById('insFile');
    const orExistingBox = document.getElementById('orExistingBox');
    const insExistingBox = document.getElementById('insExistingBox');
    const regStatusHint = document.getElementById('regStatusHint');
    const opStatusHint = document.getElementById('opStatusHint');

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

    function openVehDropdown() {
      if (!vehDropdownPanel) return;
      vehDropdownPanel.classList.remove('hidden');
      if (vehDropdownSearch) vehDropdownSearch.focus();
    }

    function closeVehDropdown() {
      if (!vehDropdownPanel) return;
      vehDropdownPanel.classList.add('hidden');
    }

    function isVehDropdownOpen() {
      return vehDropdownPanel && !vehDropdownPanel.classList.contains('hidden');
    }

    let existingOrPath = '';
    let existingInsPath = '';

    function computeStatus() {
      const exp = (orExpiry && orExpiry.value) ? String(orExpiry.value) : '';
      const hasOrFile = !!(orFile && orFile.files && orFile.files.length > 0) || !!existingOrPath;
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
    if (insExpiry) insExpiry.addEventListener('change', updateStatusHint);
    if (insFile) insFile.addEventListener('change', updateStatusHint);
    if (regYear) regYear.addEventListener('input', () => { regYear.value = String(regYear.value || '').replace(/\D+/g,'').slice(0,4); });

    async function loadVehicleInfo(vehicleId) {
      existingOrPath = '';
      existingInsPath = '';
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
      const crFp = String(v.cr_file_path || '');
      if (vehCrLoaded) vehCrLoaded.classList.toggle('hidden', !crFp);

      existingOrPath = String(r.or_file_path || '');
      existingInsPath = String(r.insurance_file_path || '');

      if (orExistingBox) orExistingBox.classList.toggle('hidden', !existingOrPath);
      if (insExistingBox) insExistingBox.classList.toggle('hidden', !existingInsPath);

      if (orNumber) orNumber.value = String(r.or_number || '');
      if (orDate) orDate.value = String(r.or_date || '');
      if (orExpiry) orExpiry.value = String(r.or_expiry_date || '');
      if (regYear) regYear.value = String(r.registration_year || '');
      if (insExpiry) insExpiry.value = String(r.insurance_expiry_date || '');
      if (vehInfo) vehInfo.classList.remove('hidden');
      if (regWrap) regWrap.classList.remove('hidden');
      updateStatusHint();
      if (opStatusHint) {
        const vs = String(v.status || '');
        opStatusHint.textContent = vs ? ('Operation status: ' + vs) : '';
      }
      if (vehDropdownLabel) {
        const label = String(v.plate_number || '-') + ' • ' + String(v.operator_name || '-');
        vehDropdownLabel.textContent = label;
      }
    }

    async function doSearch() {
      const q = (vehDropdownSearch && vehDropdownSearch.value) ? String(vehDropdownSearch.value).trim() : '';
      if (!vehDropdownList) return;
      vehDropdownList.innerHTML = '<div class="px-3 py-2 text-sm text-slate-500 italic">Loading...</div>';
      const res = await fetch(rootUrl + '/admin/api/module4/search_vehicles.php?q=' + encodeURIComponent(q));
      const data = await res.json().catch(() => null);
      const rows = data && data.ok && Array.isArray(data.data) ? data.data : [];
      if (!rows.length) {
        vehDropdownList.innerHTML = '<div class="px-3 py-2 text-sm text-slate-500 italic">No matches.</div>';
        return;
      }
      vehDropdownList.innerHTML = rows.map((r) => {
        const id = Number(r.id || 0);
        const plate = esc(r.plate_number || '-');
        const engine = esc(r.engine_no || '');
        const op = esc(r.operator_name || '-');
        return `<button type="button" class="w-full text-left p-3 rounded-xl bg-white dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700 hover:border-blue-300 dark:hover:border-blue-700 hover:bg-blue-50/30 dark:hover:bg-blue-900/10 transition-all mb-2" data-pick-id="${id}">
          <div class="flex items-center justify-between gap-2">
            <div class="font-black text-slate-900 dark:text-white">${plate}</div>
            <div class="text-xs font-bold text-slate-500 dark:text-slate-400">#${id}</div>
          </div>
          <div class="mt-1 text-xs text-slate-600 dark:text-slate-300 font-semibold">${op}</div>
          <div class="mt-1 text-[11px] text-slate-500 dark:text-slate-400 font-semibold">${engine ? ('Engine: ' + engine) : ''}</div>
        </button>`;
      }).join('');
      vehDropdownList.querySelectorAll('[data-pick-id]').forEach((b) => {
        b.addEventListener('click', async () => {
          const id = Number(b.getAttribute('data-pick-id') || 0);
          if (!id) return;
          if (vehIdEl) vehIdEl.value = String(id);
          try {
            await loadVehicleInfo(id);
            showToast('Vehicle selected.', 'success');
            closeVehDropdown();
          } catch (e) {
            showToast((e && e.message) ? String(e.message) : 'Failed', 'error');
          }
        });
      });
    }

    if (vehDropdownBtn) {
      vehDropdownBtn.addEventListener('click', () => {
        if (isVehDropdownOpen()) closeVehDropdown();
        else {
          openVehDropdown();
          doSearch().catch(() => {});
        }
      });
    }

    document.addEventListener('click', (e) => {
      if (!vehDropdownPanel || !vehDropdownBtn) return;
      const t = e.target;
      if (t && (vehDropdownPanel.contains(t) || vehDropdownBtn.contains(t))) return;
      closeVehDropdown();
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && isVehDropdownOpen()) closeVehDropdown();
    });

    if (btnVehSearch) btnVehSearch.addEventListener('click', () => { doSearch().catch(() => {}); });
    if (vehDropdownSearch) {
      vehDropdownSearch.addEventListener('keydown', (e) => {
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
