<?php
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module3.issue','module3.read','module3.analytics']);

require_once __DIR__ . '/../../includes/db.php';
$db = db();

$pending = (int)($db->query("SELECT COUNT(*) AS c FROM violations WHERE workflow_status='Pending'")->fetch_assoc()['c'] ?? 0);
$verified = (int)($db->query("SELECT COUNT(*) AS c FROM violations WHERE workflow_status='Verified'")->fetch_assoc()['c'] ?? 0);
$closed = (int)($db->query("SELECT COUNT(*) AS c FROM violations WHERE workflow_status='Closed'")->fetch_assoc()['c'] ?? 0);
?>

<div class="mx-auto max-w-7xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-8">
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between border-b border-slate-200 dark:border-slate-700 pb-6">
    <div>
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Violation Recording</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Record observed violations with evidence, then verify or close for LGU monitoring.</p>
    </div>
  </div>

  <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Pending</div>
      <div class="mt-2 text-2xl font-bold text-amber-600 dark:text-amber-400"><?php echo number_format($pending); ?></div>
    </div>
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Verified</div>
      <div class="mt-2 text-2xl font-bold text-emerald-600 dark:text-emerald-400"><?php echo number_format($verified); ?></div>
    </div>
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Closed</div>
      <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-white"><?php echo number_format($closed); ?></div>
    </div>
  </div>

  <div id="toast-container" class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 z-[100] flex flex-col gap-2 pointer-events-none"></div>

  <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <div class="p-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30">
      <div class="text-base font-black text-slate-900 dark:text-white">Record Violation</div>
      <div class="text-sm text-slate-500 dark:text-slate-400">Creates a violation record with workflow status.</div>
    </div>
    <div class="p-6">
      <form id="formCreateViolation" class="grid grid-cols-1 md:grid-cols-12 gap-6" enctype="multipart/form-data" novalidate>
        <div class="md:col-span-4 space-y-4">
          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Plate Number</label>
            <input id="plateNumberInput" name="plate_number" list="plateNumberList" required minlength="7" maxlength="8" pattern="^[A-Za-z]{3}-[0-9]{3,4}$" class="w-full px-4 py-2.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md text-sm font-semibold uppercase" placeholder="ABC-1234">
            <datalist id="plateNumberList"></datalist>
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Violation Type</label>
            <select id="violationTypeSelect" name="violation_type" required class="w-full px-4 py-2.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md text-sm font-semibold"></select>
            <div id="violationFinePreview" class="mt-1 text-xs font-bold text-rose-600"></div>
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Location</label>
            <input name="location" maxlength="255" class="w-full px-4 py-2.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md text-sm font-semibold" placeholder="Street / route / area">
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Date & Time</label>
            <input name="violation_date" type="datetime-local" class="w-full px-4 py-2.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md text-sm font-semibold">
          </div>
        </div>

        <div class="md:col-span-5 space-y-4">
          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Remarks</label>
            <textarea name="remarks" rows="6" class="w-full px-4 py-2.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md text-sm font-semibold" placeholder="Optional notes by LGU officer"></textarea>
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Evidence (optional)</label>
            <input name="evidence" type="file" accept=".jpg,.jpeg,.png,.pdf" class="w-full text-sm">
          </div>
        </div>

        <div class="md:col-span-3 space-y-4">
          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Status</label>
            <select name="workflow_status" class="w-full px-4 py-2.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md text-sm font-semibold">
              <option value="Pending">Pending</option>
              <option value="Verified">Verified</option>
              <option value="Closed">Closed</option>
            </select>
          </div>
          <button id="btnCreateViolation" class="w-full py-3 rounded-lg bg-blue-700 hover:bg-blue-800 text-white font-black">Save</button>
        </div>
      </form>
    </div>
  </div>

  <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <div class="p-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <div class="text-base font-black text-slate-900 dark:text-white">Recent Violations</div>
        <div class="text-sm text-slate-500 dark:text-slate-400">Monitor and update workflow status.</div>
      </div>
      <div class="flex flex-col sm:flex-row sm:items-center gap-2 w-full sm:w-auto">
        <select id="filterWorkflow" class="w-full sm:w-auto px-3 py-2 rounded-md bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
          <option value="">All</option>
          <option value="Pending">Pending</option>
          <option value="Verified">Verified</option>
          <option value="Closed">Closed</option>
        </select>
        <input id="filterQ" class="w-full sm:w-72 px-3 py-2 rounded-md bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="Search plate/type/location…">
        <button id="btnReload" class="w-full sm:w-auto px-4 py-2 rounded-md bg-slate-900 hover:bg-black text-white text-sm font-semibold">Reload</button>
      </div>
    </div>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-white dark:bg-slate-800">
          <tr class="text-left text-xs font-black uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
            <th class="px-5 py-3">Date</th>
            <th class="px-5 py-3">Plate</th>
            <th class="px-5 py-3">Operator</th>
            <th class="px-5 py-3">Type</th>
            <th class="px-5 py-3">Location</th>
            <th class="px-5 py-3">Status</th>
            <th class="px-5 py-3">Action</th>
          </tr>
        </thead>
        <tbody id="violationsTbody" class="divide-y divide-slate-200 dark:divide-slate-700"></tbody>
      </table>
    </div>
  </div>
</div>

<script>
  (function(){
    const rootUrl = <?php echo json_encode((string)$rootUrl); ?>;

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

    const esc = (v) => String(v ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/\"/g, '&quot;');

    const violationTypeSelect = document.getElementById('violationTypeSelect');
    const violationFinePreview = document.getElementById('violationFinePreview');
    const formCreate = document.getElementById('formCreateViolation');
    const btnCreate = document.getElementById('btnCreateViolation');
    const tbody = document.getElementById('violationsTbody');
    const filterWorkflow = document.getElementById('filterWorkflow');
    const filterQ = document.getElementById('filterQ');
    const btnReload = document.getElementById('btnReload');
    const plateInput = document.getElementById('plateNumberInput');
    const plateDatalist = document.getElementById('plateNumberList');

    let violationTypes = [];
    let plates = [];

    async function loadPlates() {
      if (!plateDatalist) return;
      const res = await fetch(rootUrl + '/admin/api/module3/vehicle_plates.php');
      const data = await res.json().catch(() => null);
      if (!data || !data.ok) return;
      plates = Array.isArray(data.data) ? data.data : [];
      plateDatalist.innerHTML = plates.map((p) => `<option value="${esc(String(p || '').toUpperCase())}"></option>`).join('');
    }

    async function loadViolationTypes() {
      const res = await fetch(rootUrl + '/admin/api/tickets/violation_types.php');
      const data = await res.json().catch(() => null);
      if (!data || !data.ok) throw new Error('violation_types_load_failed');
      violationTypes = Array.isArray(data.data) ? data.data : [];
      if (violationTypeSelect) {
        const opts = [`<option value="">Select</option>`].concat(violationTypes.map((v) => {
          const code = (v.violation_code || '').toString();
          const desc = (v.description || '').toString();
          const fine = Number(v.fine_amount || 0) || 0;
          return `<option value="${esc(code)}" data-fine="${esc(fine)}">${esc(code)} • ${esc(desc)}</option>`;
        }));
        violationTypeSelect.innerHTML = opts.join('');
      }
    }

    function updateFinePreview() {
      if (!violationTypeSelect || !violationFinePreview) return;
      const opt = violationTypeSelect.options[violationTypeSelect.selectedIndex];
      if (!opt) { violationFinePreview.textContent = ''; return; }
      const fine = opt.getAttribute('data-fine');
      violationFinePreview.textContent = fine ? ('Fine: ₱' + Number(fine).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})) : '';
    }

    if (plateInput) {
      plateInput.addEventListener('input', () => {
        plateInput.value = plateInput.value.toUpperCase();
      });
    }

    async function loadViolations() {
      if (!tbody) return;
      tbody.innerHTML = `<tr><td colspan="7" class="px-5 py-6 text-center text-slate-500">Loading…</td></tr>`;
      const qs = new URLSearchParams();
      if (filterWorkflow && filterWorkflow.value) qs.set('workflow_status', filterWorkflow.value);
      if (filterQ && filterQ.value.trim() !== '') qs.set('q', filterQ.value.trim());
      const res = await fetch(rootUrl + '/admin/api/module3/violations_list.php?' + qs.toString());
      const data = await res.json().catch(() => null);
      if (!data || !data.ok) { tbody.innerHTML = `<tr><td colspan="7" class="px-5 py-6 text-center text-rose-600 font-semibold">Failed to load.</td></tr>`; return; }
      const rows = Array.isArray(data.data) ? data.data : [];
      if (!rows.length) { tbody.innerHTML = `<tr><td colspan="7" class="px-5 py-6 text-center text-slate-500">No records.</td></tr>`; return; }
      tbody.innerHTML = rows.map((r) => {
        const id = r.id;
        const dt = (r.violation_date || '').toString();
        const plate = (r.plate_number || '').toString();
        const opName = (r.operator_name || '').toString();
        const type = (r.violation_type || '').toString();
        const desc = (r.violation_desc || '').toString();
        const loc = (r.location || '').toString();
        const wf = (r.workflow_status || '').toString();
        const ev = (r.evidence_path || '').toString();
        const evLink = ev ? `<a class="text-blue-700 hover:underline font-bold" target="_blank" href="${esc(rootUrl + '/admin/uploads/' + encodeURIComponent(ev))}">Evidence</a>` : `<span class="text-slate-400">—</span>`;
        return `<tr>
          <td class="px-5 py-3 text-xs font-semibold text-slate-600 dark:text-slate-300">${esc(dt)}</td>
          <td class="px-5 py-3 font-black">${esc(plate)}</td>
          <td class="px-5 py-3 text-xs font-semibold text-slate-600 dark:text-slate-300">${esc(opName || '-')}</td>
          <td class="px-5 py-3 text-xs font-semibold">${esc(type)}${desc ? (' • ' + esc(desc)) : ''}</td>
          <td class="px-5 py-3 text-xs font-semibold text-slate-600 dark:text-slate-300">${esc(loc || '-')}</td>
          <td class="px-5 py-3 text-xs font-black">${esc(wf)}</td>
          <td class="px-5 py-3">
            <div class="flex flex-wrap items-center gap-2">
              ${evLink}
              <button data-wf="Pending" data-id="${esc(id)}" class="px-3 py-2 rounded-md bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-bold">Pending</button>
              <button data-wf="Verified" data-id="${esc(id)}" class="px-3 py-2 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold">Verify</button>
              <button data-wf="Closed" data-id="${esc(id)}" class="px-3 py-2 rounded-md bg-slate-900 hover:bg-black text-white text-xs font-bold">Close</button>
            </div>
          </td>
        </tr>`;
      }).join('');
      tbody.querySelectorAll('button[data-wf][data-id]').forEach((b) => {
        b.addEventListener('click', async () => {
          const wf = b.getAttribute('data-wf');
          const id = b.getAttribute('data-id');
          const remarks = prompt('Remarks (optional):', '');
          const fd = new FormData();
          fd.append('violation_id', String(id));
          fd.append('workflow_status', String(wf));
          fd.append('remarks', String(remarks || ''));
          const res2 = await fetch(rootUrl + '/admin/api/module3/violations_update_status.php', { method: 'POST', body: fd });
          const d2 = await res2.json().catch(() => null);
          if (!d2 || !d2.ok) { showToast('Failed to update.', 'error'); return; }
          showToast('Updated.');
          loadViolations();
        });
      });
    }

    if (violationTypeSelect) violationTypeSelect.addEventListener('change', updateFinePreview);
    if (btnReload) btnReload.addEventListener('click', loadViolations);
    if (filterWorkflow) filterWorkflow.addEventListener('change', loadViolations);
    if (filterQ) filterQ.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); loadViolations(); } });

    if (formCreate) {
      formCreate.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!formCreate.checkValidity()) { formCreate.reportValidity(); return; }
        if (btnCreate) { btnCreate.disabled = true; btnCreate.textContent = 'Saving...'; }
        try {
          const fd = new FormData(formCreate);
          const res = await fetch(rootUrl + '/admin/api/module3/violations_create.php', { method: 'POST', body: fd });
          const data = await res.json().catch(() => null);
          if (!data || !data.ok) throw new Error((data && data.error) ? String(data.error) : 'save_failed');
          showToast('Saved.');
          formCreate.reset();
          updateFinePreview();
          loadViolations();
        } catch (err) {
          showToast((err && err.message) ? err.message : 'Failed.', 'error');
        } finally {
          if (btnCreate) { btnCreate.disabled = false; btnCreate.textContent = 'Save'; }
        }
      });
    }

    Promise.resolve()
      .then(loadViolationTypes)
      .then(loadPlates)
      .then(updateFinePreview)
      .then(loadViolations)
      .catch(() => { showToast('Failed to initialize.', 'error'); });
  })();
</script>
