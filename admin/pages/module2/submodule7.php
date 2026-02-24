<?php
if (function_exists('opcache_invalidate')) { @opcache_invalidate(__FILE__, true); }
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

require_once __DIR__ . '/../../includes/vehicle_types.php';
$typesList = array_values(array_filter(vehicle_types(), function ($t) {
  $t = (string)$t;
  return strcasecmp($t, 'Tricycle') !== 0 && strcasecmp($t, 'E-trike') !== 0 && strcasecmp($t, 'Motorized Pedicab') !== 0;
}));

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';
?>

<div class="mx-auto max-w-4xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-6">
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between border-b border-slate-200 dark:border-slate-700 pb-6">
    <div>
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">PUV Local Endorsement / Permit Application</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">
        For non‑tricycle PUVs (jeepney, UV, bus). Encode a local endorsement / permit application using LTFRB franchise proof, LTO OR/CR, and insurance.
        This does not create a local franchise; it feeds into PUV Local Endorsement / Permit review.
      </p>
    </div>
    <div class="flex items-center gap-3">
      <a href="?page=module2/submodule3" class="inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
        <i data-lucide="clipboard-list" class="w-4 h-4"></i>
        PUV Endorsement List
      </a>
    </div>
  </div>

  <div id="toast-container" class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 z-[100] flex flex-col gap-3 pointer-events-none"></div>

  <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
    <div class="p-6 space-y-6">
      <form id="formPuvApp" class="space-y-6" enctype="multipart/form-data" novalidate>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">
              <span class="text-rose-600">*</span> Operator
            </label>
            <input name="operator_pick" list="operatorPickList" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="Select from list (e.g., 123 - Juan Dela Cruz)">
            <datalist id="operatorPickList">
              <?php foreach ($operators as $o): ?>
                <option value="<?php echo htmlspecialchars($o['operator_id'] . ' - ' . $o['display_name'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($o['operator_type'] . ' • ' . $o['status']); ?></option>
              <?php endforeach; ?>
            </datalist>
          </div>
          <div>
            <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">
              <span class="text-rose-600">*</span> Vehicle Type
            </label>
            <select name="vehicle_type" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
              <option value="">Select vehicle type</option>
              <?php foreach ($typesList as $t): ?>
                <option value="<?php echo htmlspecialchars($t); ?>"><?php echo htmlspecialchars($t); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div>
          <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">
            <span class="text-rose-600">*</span> Route / Terminal Assignment
          </label>
          <input id="routePick" name="route_pick" list="routePickList" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="Select route (e.g., JEEP-10 • Route Name • Origin → Destination)">
          <datalist id="routePickList"></datalist>
          <div id="routeHint" class="mt-1 text-[11px] text-slate-500 dark:text-slate-400 font-semibold"></div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">
              <span class="text-rose-600">*</span> Units requested for endorsement
            </label>
            <input name="vehicle_count" type="number" min="1" max="500" step="1" value="1" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., 5">
          </div>
          <div>
            <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">
              Vehicle details summary (optional)
            </label>
            <input name="vehicle_notes" maxlength="200" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., Fleet size, unit IDs, special notes">
          </div>
        </div>

        <div class="border-t border-slate-200 dark:border-slate-700 pt-5 space-y-4">
          <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Upload Requirements</div>
          <div class="grid grid-cols-1 gap-4">
            <div>
              <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1">LTFRB Franchise proof (CPC / PA) <span class="text-rose-600">*</span></label>
              <input type="file" name="doc_ltfrb_proof" accept=".pdf,.jpg,.jpeg,.png" required class="block w-full text-xs text-slate-600 dark:text-slate-300">
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1">LTO OR/CR <span class="text-rose-600">*</span></label>
              <input type="file" name="doc_orcr" accept=".pdf,.jpg,.jpeg,.png" required class="block w-full text-xs text-slate-600 dark:text-slate-300">
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1">Insurance (CTPL / Comprehensive) <span class="text-rose-600">*</span></label>
              <input type="file" name="doc_insurance" accept=".pdf,.jpg,.jpeg,.png" required class="block w-full text-xs text-slate-600 dark:text-slate-300">
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1">Other supporting document (optional)</label>
              <input type="file" name="doc_other" accept=".pdf,.jpg,.jpeg,.png,.xlsx,.xls,.csv" class="block w-full text-xs text-slate-600 dark:text-slate-300">
            </div>
          </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2">
          <button id="btnPuvSubmit" class="px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold">Submit PUV Endorsement Application</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  (function(){
    const rootUrl = <?php echo json_encode($rootUrl); ?>;
    const form = document.getElementById('formPuvApp');
    const btn = document.getElementById('btnPuvSubmit');
    const routePick = document.getElementById('routePick');
    const routePickList = document.getElementById('routePickList');
    const routeHint = document.getElementById('routeHint');

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

    function parseIdFromPick(s) {
      const m = (s || '').toString().trim().match(/^(\d+)\s*-/);
      if (!m) return 0;
      return Number(m[1] || 0);
    }

    function setPickValidity(el) {
      if (!el) return;
      const v = (el.value || '').toString();
      const ok = parseIdFromPick(v) > 0;
      el.setCustomValidity(ok ? '' : 'Please select a valid option from the list.');
    }

    let routesCache = null;
    async function loadRoutes() {
      if (routesCache) return routesCache;
      const res = await fetch(rootUrl + '/admin/api/module2/routes_list.php');
      const data = await res.json().catch(() => null);
      if (!data || !data.ok) throw new Error('routes_load_failed');
      routesCache = Array.isArray(data.data) ? data.data : [];
      return routesCache;
    }

    function normalizeVehicleCategory(v) {
      const s = (v || '').toString().trim();
      if (!s) return '';
      if (['Tricycle','Jeepney','UV','Bus'].includes(s)) return s;
      const l = s.toLowerCase();
      if (l.includes('tricycle') || l.includes('e-trike') || l.includes('pedicab')) return 'Tricycle';
      if (l.includes('jeepney')) return 'Jeepney';
      if (l.includes('bus') || l.includes('mini-bus')) return 'Bus';
      if (l.includes('uv') || l.includes('van') || l.includes('shuttle')) return 'UV';
      return '';
    }

    function rebuildRouteList(vehicleType) {
      if (!routePickList || !routePick) return;
      const vt = normalizeVehicleCategory(vehicleType);
      routePickList.innerHTML = '';
      routePick.value = '';
      if (!vt || vt === 'Tricycle') return;
      const rows = Array.isArray(routesCache) ? routesCache : [];
      const filtered = rows.filter((r) => {
        const kind = (r && r.kind) ? String(r.kind) : 'route';
        const rv = (r && r.vehicle_type) ? String(r.vehicle_type) : '';
        if (kind !== 'route') return false;
        const rc = normalizeVehicleCategory(rv);
        return rc === vt;
      });
      const seen = new Set();
      filtered.forEach((r) => {
        const id = Number(r.route_db_id || r.id || 0);
        if (!id || seen.has(id)) return;
        seen.add(id);
        const code = (r.route_code || r.route_id || '').toString();
        const name = (r.route_name || '').toString();
        const origin = (r.origin || '').toString();
        const dest = (r.destination || '').toString();
        const cap = Number(r.authorized_units || 0);
        const used = Number(r.used_units || 0);
        const rem = Number(r.remaining_units || 0);
        const labelParts = [];
        labelParts.push(code || ('ID ' + id));
        if (name) labelParts.push(name);
        const od = [origin, dest].filter(Boolean).join(' → ');
        if (od) labelParts.push(od);
        labelParts.push(`Slots ${used}/${cap}${cap ? ' (Remaining ' + rem + ')' : ''}`);
        const label = labelParts.join(' • ');
        const opt = document.createElement('option');
        opt.value = `${id} - ${label}`;
        routePickList.appendChild(opt);
      });
    }

    function updateRouteHint() {
      if (!routeHint || !routePick) return;
      const id = parseIdFromPick(routePick.value);
      if (!id) { routeHint.textContent = ''; return; }
      const rows = Array.isArray(routesCache) ? routesCache : [];
      const r = rows.find((x) => Number(x.route_db_id || x.id || 0) === id);
      if (!r) { routeHint.textContent = ''; return; }
      const cap = Number(r.authorized_units || 0);
      const used = Number(r.used_units || 0);
      const rem = Number(r.remaining_units || 0);
      routeHint.textContent = `Available slot capacity: ${rem} • Existing authorized units: ${cap} • Used: ${used}`;
    }

    if (form && btn) {
      const opEl = form.querySelector('input[name="operator_pick"]');
      const vtEl = form.querySelector('select[name="vehicle_type"]');

      if (opEl) {
        opEl.addEventListener('input', () => { opEl.setCustomValidity(''); });
        opEl.addEventListener('blur', () => { setPickValidity(opEl); });
      }
      if (routePick) {
        routePick.addEventListener('input', () => { routePick.setCustomValidity(''); });
        routePick.addEventListener('blur', () => { setPickValidity(routePick); updateRouteHint(); });
      }

      if (vtEl) {
        vtEl.addEventListener('change', async () => {
          try {
            await loadRoutes();
            rebuildRouteList(vtEl.value || '');
            if (routeHint) routeHint.textContent = '';
          } catch (e) {
            showToast('Failed to load routes.', 'error');
          }
        });
      }

      (async () => {
        try {
          await loadRoutes();
        } catch (_) {}
      })();

      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(form);
        const operatorId = parseIdFromPick(fd.get('operator_pick'));
        const vehicleType = (fd.get('vehicle_type') || '').toString().trim();
        const routeId = parseIdFromPick(fd.get('route_pick'));
        const vehicleCount = Number(fd.get('vehicle_count') || 0);
        if (opEl) setPickValidity(opEl);
        if (routePick) setPickValidity(routePick);
        if (!form.checkValidity()) { form.reportValidity(); return; }
        if (!operatorId || !vehicleType || !routeId || !vehicleCount) { showToast('Missing required fields.', 'error'); return; }

        btn.disabled = true;
        btn.textContent = 'Submitting...';

        try {
          const post = new FormData();
          post.append('operator_id', String(operatorId));
          post.append('vehicle_type', vehicleType);
          post.append('route_id', String(routeId));
          post.append('vehicle_count', String(vehicleCount));
          post.append('vehicle_notes', (fd.get('vehicle_notes') || '').toString());

          const fLtfrb = form.querySelector('input[name="doc_ltfrb_proof"]');
          const fOrcr = form.querySelector('input[name="doc_orcr"]');
          const fIns = form.querySelector('input[name="doc_insurance"]');
          const fOther = form.querySelector('input[name="doc_other"]');
          if (fLtfrb && fLtfrb.files && fLtfrb.files[0]) post.append('doc_ltfrb_proof', fLtfrb.files[0]);
          if (fOrcr && fOrcr.files && fOrcr.files[0]) post.append('doc_orcr', fOrcr.files[0]);
          if (fIns && fIns.files && fIns.files[0]) post.append('doc_insurance', fIns.files[0]);
          if (fOther && fOther.files && fOther.files[0]) post.append('doc_other', fOther.files[0]);

          const res = await fetch(rootUrl + '/admin/api/module2/save_puv_endorsement_application.php', { method: 'POST', body: post });
          const ct = (res.headers.get('content-type') || '').toLowerCase();
          let data = null;
          if (ct.includes('application/json')) {
            data = await res.json().catch(() => null);
          } else {
            const txt = await res.text().catch(() => '');
            const hint = txt ? 'non_json_response' : 'empty_response';
            throw new Error(hint + (res.status ? (' (HTTP ' + res.status + ')') : ''));
          }
          if (!res.ok || !data || !data.ok || !data.application_id) {
            const raw = (data && data.error) ? String(data.error) : (res.status ? ('http_' + res.status) : 'submit_failed');
            const msg = raw === 'invalid_vehicle_type'
              ? 'Vehicle type must be Jeepney, UV, or Bus.'
              : raw === 'tricycle_only'
                ? 'This screen is only for non‑tricycle PUVs.'
                : raw === 'operator_not_found'
                  ? 'Operator not found.'
                  : raw === 'route_not_found'
                    ? 'Selected route not found or inactive.'
                    : raw === 'file_invalid'
                      ? 'One or more uploaded files are invalid.'
                      : raw;
            throw new Error(msg);
          }

          const appId = Number(data.application_id);
          showToast('PUV endorsement application submitted.', 'success');
          const params = new URLSearchParams();
          params.set('page', 'module2/submodule3');
          params.set('application_id', String(appId));
          window.location.href = '?' + params.toString();
        } catch (err) {
          showToast(err.message || 'Failed', 'error');
          btn.disabled = false;
          btn.textContent = 'Submit PUV Endorsement Application';
        }
      });
    }
  })();
</script>
