<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('module2.apply');

require_once __DIR__ . '/../../includes/db.php';
$db = db();

require_once __DIR__ . '/../../includes/vehicle_types.php';
$typesList = vehicle_types();

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
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">Encode a franchise application on behalf of an operator (assisted walk-in) or review submissions from the Operator Portal.</p>
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
        <div class="rounded-xl bg-slate-50 dark:bg-slate-900/30 border border-slate-200 dark:border-slate-700 p-4">
          <label class="flex items-start gap-3">
            <input id="assistedWalkin" type="checkbox" class="mt-1 w-4 h-4">
            <div class="text-sm font-semibold text-slate-700 dark:text-slate-200">
              Assisted encoding (walk-in)
              <div class="text-xs font-medium text-slate-500 dark:text-slate-400 mt-1">Enable when staff is encoding for a walk-in operator without device access.</div>
            </div>
          </label>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Operator</label>
            <input name="operator_pick" list="operatorPickList" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="Select from list (e.g., 123 - Juan Dela Cruz)">
            <datalist id="operatorPickList">
              <?php foreach ($operators as $o): ?>
                <option value="<?php echo htmlspecialchars($o['operator_id'] . ' - ' . $o['display_name'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($o['operator_type'] . ' • ' . $o['status']); ?></option>
              <?php endforeach; ?>
            </datalist>
          </div>
          <div>
            <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Vehicle Type</label>
            <select name="vehicle_type" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
              <option value="">Select</option>
              <?php foreach ($typesList as $t): ?>
                <option value="<?php echo htmlspecialchars($t); ?>"><?php echo htmlspecialchars($t); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div>
          <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Route / Service Area</label>
          <input id="servicePick" name="service_pick" list="servicePickList" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="Select a vehicle type first">
          <datalist id="servicePickList"></datalist>
          <div class="mt-1 text-xs text-slate-500 dark:text-slate-400 font-semibold">For Tricycles: choose a TODA service area (coverage points). For Jeepney/UV/Bus: choose a corridor route.</div>
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
          <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-3">Supporting Documents (from Operator)</div>
          <div id="opDocsBox" class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900/30 border border-slate-200 dark:border-slate-700">
            <div class="text-sm text-slate-500 dark:text-slate-400 italic">Select an operator to view documents in the PUV Database.</div>
          </div>
        </div>

        <div class="border-t border-slate-200 dark:border-slate-700 pt-5">
          <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-3">Declared Fleet (Planned / Owned Vehicles)</div>
          <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900/30 border border-slate-200 dark:border-slate-700">
            <div class="text-sm text-slate-600 dark:text-slate-300 font-semibold mb-2">Upload a fleet list file (PDF / Excel / CSV). This will be attached to the application.</div>
            <input name="declared_fleet_doc" type="file" accept=".pdf,.xlsx,.xls,.csv,application/pdf,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv" class="block w-full text-sm font-semibold text-slate-700 dark:text-slate-200 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:bg-white dark:file:bg-slate-800 file:text-slate-700 dark:file:text-slate-200 file:font-semibold file:ring-1 file:ring-inset file:ring-slate-200 dark:file:ring-slate-600 hover:file:bg-slate-50 dark:hover:file:bg-slate-700/40">
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

    function normalizeVehicleType(v) {
      const s = (v || '').toString().trim();
      if (!s) return '';
      if (['Tricycle','Jeepney','UV','Bus'].includes(s)) return s;
      const l = s.toLowerCase();
      if (l.includes('tricycle') || l.includes('e-trike') || l.includes('pedicab')) return 'Tricycle';
      if (l.includes('jeepney')) return 'Jeepney';
      if (l.includes('bus') || l.includes('mini-bus')) return 'Bus';
      return 'UV';
    }

    if (form && btn) {
      const opEl = form.querySelector('input[name="operator_pick"]');
      const vtEl = form.querySelector('select[name="vehicle_type"]');
      const svcEl = document.getElementById('servicePick');
      const svcList = document.getElementById('servicePickList');
      let routesCache = null;

      async function loadServices() {
        if (routesCache) return routesCache;
        const res = await fetch(rootUrl + '/admin/api/module2/routes_list.php');
        const data = await res.json().catch(() => null);
        if (!data || !data.ok) throw new Error('routes_load_failed');
        routesCache = Array.isArray(data.data) ? data.data : [];
        return routesCache;
      }

      function rebuildServiceList(vehicleType) {
        if (!svcList || !svcEl) return;
        const vtRaw = (vehicleType || '').toString();
        const vt = normalizeVehicleType(vtRaw);
        svcList.innerHTML = '';
        svcEl.value = '';
        svcEl.placeholder = vt ? (vt === 'Tricycle' ? 'Select a service area (e.g., 12 - TODA-BAGUMBONG • Bagumbong TODA Zone)' : 'Select a route (e.g., 45 - JEEP-...)') : 'Select a vehicle type first';
        if (!vt) return;
        const rows = Array.isArray(routesCache) ? routesCache : [];
        const filtered = rows.filter((r) => {
          const kind = (r && r.kind) ? String(r.kind) : 'route';
          const rv = (r && r.vehicle_type) ? String(r.vehicle_type) : '';
          if (vt === 'Tricycle') return kind === 'service_area';
          return kind === 'route' && rv === vt;
        });
        filtered.forEach((r) => {
          const kind = (r && r.kind) ? String(r.kind) : 'route';
          const opt = document.createElement('option');
          if (kind === 'service_area') {
            const id = Number(r.service_area_id || 0);
            const label = `${String(r.area_code || '')} • ${String(r.area_name || '')}${r.points ? ' • ' + String(r.points) : ''}`;
            opt.value = `${id} - ${label}`;
          } else {
            const id = Number(r.route_db_id || 0);
            const label = `${String(r.route_code || '')}${r.route_name ? ' • ' + String(r.route_name) : ''} • ${(r.origin || '')} → ${(r.destination || '')}`;
            opt.value = `${id} - ${label}`;
          }
          svcList.appendChild(opt);
        });
      }

      const setPickValidity = (el) => {
        if (!el) return;
        const v = (el.value || '').toString();
        const ok = parseId(v) > 0;
        el.setCustomValidity(ok ? '' : 'Please select a valid option from the list.');
      };
      if (opEl) {
        opEl.addEventListener('input', () => { opEl.setCustomValidity(''); });
        opEl.addEventListener('blur', () => { setPickValidity(opEl); });
      }
      if (svcEl) {
        svcEl.addEventListener('input', () => { svcEl.setCustomValidity(''); });
        svcEl.addEventListener('blur', () => { setPickValidity(svcEl); });
      }
      if (vtEl) {
        vtEl.addEventListener('change', async () => {
          try {
            await loadServices();
            rebuildServiceList(vtEl.value || '');
          } catch (e) {
            showToast('Failed to load routes/service areas.', 'error');
          }
        });
      }
      (async () => {
        try {
          await loadServices();
          if (vtEl) rebuildServiceList(vtEl.value || '');
        } catch (_) {}
      })();

      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(form);
        const operatorId = parseId(fd.get('operator_pick'));
        const vehicleTypeRaw = (fd.get('vehicle_type') || '').toString();
        const vehicleType = normalizeVehicleType(vehicleTypeRaw);
        const pickId = parseId(fd.get('service_pick'));
        const vehicleCount = Number(fd.get('vehicle_count') || 0);
        if (opEl) setPickValidity(opEl);
        if (svcEl) setPickValidity(svcEl);
        if (!form.checkValidity()) { form.reportValidity(); return; }
        if (!operatorId || !vehicleType || !pickId || !vehicleCount) { showToast('Missing required fields.', 'error'); return; }

        btn.disabled = true;
        btn.textContent = 'Submitting...';

        try {
          const post = new FormData();
          post.append('operator_id', String(operatorId));
          post.append('vehicle_type', vehicleType);
          if (vehicleType === 'Tricycle') post.append('service_area_id', String(pickId));
          else post.append('route_id', String(pickId));
          post.append('vehicle_count', String(vehicleCount));
          post.append('representative_name', (fd.get('representative_name') || '').toString());
          const assisted = document.getElementById('assistedWalkin');
          post.append('assisted', assisted && assisted.checked ? '1' : '0');
          const fleetFile = form.querySelector('input[name="declared_fleet_doc"]');
          if (fleetFile && fleetFile.files && fleetFile.files[0]) {
            post.append('declared_fleet_doc', fleetFile.files[0]);
          }

          const res = await fetch(rootUrl + '/admin/api/module2/save_application.php', { method: 'POST', body: post });
          const data = await res.json();
          if (!data || !data.ok || !data.application_id) {
            const raw = (data && data.error) ? String(data.error) : 'submit_failed';
            const msg = raw === 'operator_inactive'
              ? 'Cannot submit: operator is inactive.'
              : raw;
            throw new Error(msg);
          }

          const appId = Number(data.application_id);
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

    const opDocsBox = document.getElementById('opDocsBox');
    async function loadOperatorVerifiedDocs(operatorId) {
      const res = await fetch(rootUrl + '/admin/api/module2/list_operator_verified_docs.php?operator_id=' + encodeURIComponent(String(operatorId || '')));
      const data = await res.json();
      if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'load_failed');
      return data;
    }
    function operatorDocLabel(d) {
      const remarks = (d && d.remarks) ? String(d.remarks) : '';
      const labelPart = remarks.split('|')[0].trim();
      if (labelPart) return labelPart;
      const dt = (d && d.doc_type) ? String(d.doc_type) : '';
      const map = {
        GovID: 'Valid Government ID',
        CDA: 'CDA Document',
        SEC: 'SEC Document',
        BarangayCert: 'Proof of Address',
        Others: 'Supporting Document',
      };
      return map[dt] || dt || 'Document';
    }
    function renderOperatorDocs(payload) {
      if (!opDocsBox) return;
      const op = payload && payload.operator ? payload.operator : null;
      const rows = (payload && Array.isArray(payload.data)) ? payload.data : [];
      const wf = op ? String(op.workflow_status || '') : '';
      const vs = op ? String(op.verification_status || '') : '';
      const header = op ? `
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
          <div class="text-sm font-black text-slate-900 dark:text-white">${String(op.display_name || '')}</div>
          <div class="text-xs font-bold text-slate-500 dark:text-slate-400">Workflow: ${wf || '-'} • Verification: ${vs || '-'}</div>
        </div>
      ` : '';
      if (!rows.length) {
        opDocsBox.innerHTML = header + '<div class="text-sm text-slate-500 dark:text-slate-400 italic">No verified operator documents found.</div>';
        return;
      }
      opDocsBox.innerHTML = header + rows.map((d) => {
        const href = rootUrl + '/admin/uploads/' + encodeURIComponent(String(d.file_path || ''));
        const dt = d.uploaded_at ? new Date(d.uploaded_at) : null;
        const date = dt && !isNaN(dt.getTime()) ? dt.toLocaleString() : '';
        const vdt = d.verified_at ? new Date(d.verified_at) : null;
        const vdate = vdt && !isNaN(vdt.getTime()) ? vdt.toLocaleString() : '';
        const vby = (d.verified_by_name || '').toString().trim();
        const name = operatorDocLabel(d);
        return `
          <a href="${href}" target="_blank" class="flex items-center justify-between p-3 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 hover:border-blue-200 dark:hover:border-blue-800 hover:bg-blue-50/40 dark:hover:bg-blue-900/10 transition-all mb-2">
            <div>
              <div class="text-sm font-black text-slate-800 dark:text-white">${name}</div>
              <div class="text-xs text-slate-500 dark:text-slate-400">${date}</div>
              ${(vby || vdate) ? `<div class="text-[11px] text-slate-500 dark:text-slate-400 font-semibold">Verified by ${vby || '-'} • ${vdate || '-'}</div>` : ``}
            </div>
            <div class="text-slate-400 hover:text-blue-600"><i data-lucide="external-link" class="w-4 h-4"></i></div>
          </a>
        `;
      }).join('');
      if (window.lucide) window.lucide.createIcons();
    }
    async function refreshOperatorDocs() {
      const opEl = form ? form.querySelector('input[name="operator_pick"]') : null;
      const operatorId = parseId(opEl ? opEl.value : '');
      if (!opDocsBox) return;
      if (!operatorId) {
        opDocsBox.innerHTML = '<div class="text-sm text-slate-500 dark:text-slate-400 italic">Select an operator to view verified documents.</div>';
        return;
      }
      opDocsBox.innerHTML = '<div class="text-sm text-slate-500 dark:text-slate-400">Loading verified documents...</div>';
      try {
        const payload = await loadOperatorVerifiedDocs(operatorId);
        renderOperatorDocs(payload);
        const vtEl = document.querySelector('#formSubmitApp select[name="vehicle_type"]');
        if (vtEl && (!vtEl.value || vtEl.value === '')) {
          const opType = (payload && payload.operator && payload.operator.operator_type) ? String(payload.operator.operator_type) : '';
          const t = opType.toLowerCase();
          let guess = '';
          if (t.includes('toda') || t.includes('tricycle')) guess = 'Tricycle';
          else if (t.includes('jeep')) guess = 'Jeepney';
          else if (t.includes('uv')) guess = 'UV Express';
          else if (t.includes('bus')) guess = 'Bus';
          if (guess) {
            vtEl.value = guess;
            vtEl.dispatchEvent(new Event('change'));
          }
        }
      } catch (e) {
        opDocsBox.innerHTML = '<div class="text-sm text-rose-600">Failed to load operator documents.</div>';
      }
    }
    if (form) {
      const opEl = form.querySelector('input[name="operator_pick"]');
      if (opEl) {
        opEl.addEventListener('change', refreshOperatorDocs);
        opEl.addEventListener('blur', refreshOperatorDocs);
      }
    }
  })();
</script>
