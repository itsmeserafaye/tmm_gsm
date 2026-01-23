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
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">Select an operator and proposed route, set the requested vehicle count, and the system will use the operator’s verified documents from the PUV Database.</p>
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
            <input name="operator_pick" list="operatorPickList" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="Select from list (e.g., 123 - Juan Dela Cruz)">
            <datalist id="operatorPickList">
              <?php foreach ($operators as $o): ?>
                <option value="<?php echo htmlspecialchars($o['operator_id'] . ' - ' . $o['display_name'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($o['operator_type'] . ' • ' . $o['status']); ?></option>
              <?php endforeach; ?>
            </datalist>
          </div>
          <div>
            <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Route</label>
            <input name="route_pick" list="routePickList" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="Select from list (e.g., 45 - R_001 • Origin → Destination)">
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
          <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-3">Supporting Documents (from Operator)</div>
          <div id="opDocsBox" class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900/30 border border-slate-200 dark:border-slate-700">
            <div class="text-sm text-slate-500 dark:text-slate-400 italic">Select an operator to view verified documents.</div>
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
      const opEl = form.querySelector('input[name="operator_pick"]');
      const rtEl = form.querySelector('input[name="route_pick"]');
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
      if (rtEl) {
        rtEl.addEventListener('input', () => { rtEl.setCustomValidity(''); });
        rtEl.addEventListener('blur', () => { setPickValidity(rtEl); });
      }

      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(form);
        const operatorId = parseId(fd.get('operator_pick'));
        const routeId = parseId(fd.get('route_pick'));
        const vehicleCount = Number(fd.get('vehicle_count') || 0);
        if (opEl) setPickValidity(opEl);
        if (rtEl) setPickValidity(rtEl);
        if (!form.checkValidity()) { form.reportValidity(); return; }
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
        return `
          <a href="${href}" target="_blank" class="flex items-center justify-between p-3 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 hover:border-blue-200 dark:hover:border-blue-800 hover:bg-blue-50/40 dark:hover:bg-blue-900/10 transition-all mb-2">
            <div>
              <div class="text-sm font-black text-slate-800 dark:text-white">${String(d.doc_type || '')}</div>
              <div class="text-xs text-slate-500 dark:text-slate-400">${date}</div>
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
