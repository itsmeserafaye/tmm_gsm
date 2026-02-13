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
    <div class="flex items-center gap-2">
      <button type="button" id="btnOpenViolationModal" class="inline-flex items-center justify-center gap-2 rounded-md bg-blue-700 hover:bg-blue-800 px-4 py-2.5 text-sm font-semibold text-white transition-colors">
        <i data-lucide="plus" class="w-4 h-4"></i>
        Record Violation
      </button>
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

  <div id="modalViolation" class="fixed inset-0 z-[220] hidden">
    <div data-modal-backdrop class="absolute inset-0 bg-black/40"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
      <div class="w-full max-w-4xl rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-xl overflow-hidden flex flex-col max-h-[90vh]">
        <div class="p-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
          <div>
            <div class="text-sm font-black text-slate-900 dark:text-white">Record Violation</div>
            <div class="text-xs text-slate-500 dark:text-slate-400 font-semibold">Creates a violation record with workflow status.</div>
          </div>
          <button type="button" data-modal-close class="p-2 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-200">
            <i data-lucide="x" class="w-4 h-4"></i>
          </button>
        </div>
        <div class="p-6 overflow-auto">
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
              <button type="button" id="btnCloseViolationModal" class="w-full py-3 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 font-black">Cancel</button>
            </div>
          </form>
        </div>
      </div>
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
        <input id="filterFrom" type="date" class="w-full sm:w-auto px-3 py-2 rounded-md bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
        <input id="filterTo" type="date" class="w-full sm:w-auto px-3 py-2 rounded-md bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
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
            <th class="px-5 py-3 text-right">Actions</th>
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
    const modal = document.getElementById('modalViolation');
    const btnOpenModal = document.getElementById('btnOpenViolationModal');
    const btnCloseModal = document.getElementById('btnCloseViolationModal');
    const tbody = document.getElementById('violationsTbody');
    const filterWorkflow = document.getElementById('filterWorkflow');
    const filterFrom = document.getElementById('filterFrom');
    const filterTo = document.getElementById('filterTo');
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

    function openModal() {
      if (!modal) return;
      modal.classList.remove('hidden');
      try { if (window.lucide && window.lucide.createIcons) window.lucide.createIcons(); } catch (_) {}
    }
    function closeModal() {
      if (!modal) return;
      modal.classList.add('hidden');
    }
    if (btnOpenModal) btnOpenModal.addEventListener('click', openModal);
    if (btnCloseModal) btnCloseModal.addEventListener('click', closeModal);
    if (modal) {
      const b = modal.querySelector('[data-modal-backdrop]');
      const c = modal.querySelector('[data-modal-close]');
      if (b) b.addEventListener('click', closeModal);
      if (c) c.addEventListener('click', closeModal);
      document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal(); });
    }

    async function loadViolations() {
      if (!tbody) return;
      tbody.innerHTML = `<tr><td colspan="7" class="px-5 py-6 text-center text-slate-500">Loading…</td></tr>`;
      const qs = new URLSearchParams();
      if (filterWorkflow && filterWorkflow.value) qs.set('workflow_status', filterWorkflow.value);
      if (filterFrom && filterFrom.value) qs.set('from', filterFrom.value);
      if (filterTo && filterTo.value) qs.set('to', filterTo.value);
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
        const evLink = ev
          ? `<a class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-600 hover:text-blue-700 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-all" title="View evidence" target="_blank" href="${esc(rootUrl + '/admin/uploads/' + encodeURIComponent(ev))}">
               <i data-lucide="paperclip" class="w-4 h-4"></i>
             </a>`
          : `<span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700 text-slate-400" title="No evidence">
               <i data-lucide="paperclip" class="w-4 h-4"></i>
             </span>`;
        return `<tr>
          <td class="px-5 py-3 text-xs font-semibold text-slate-600 dark:text-slate-300">${esc(dt)}</td>
          <td class="px-5 py-3 font-black">${esc(plate)}</td>
          <td class="px-5 py-3 text-xs font-semibold text-slate-600 dark:text-slate-300">${esc(opName || '-')}</td>
          <td class="px-5 py-3 text-xs font-semibold">${esc(type)}${desc ? (' • ' + esc(desc)) : ''}</td>
          <td class="px-5 py-3 text-xs font-semibold text-slate-600 dark:text-slate-300">${esc(loc || '-')}</td>
          <td class="px-5 py-3 text-xs font-black">${esc(wf)}</td>
          <td class="px-5 py-3">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-2">
              <div class="flex items-center gap-2">
                ${evLink}
              </div>
              <div class="flex items-center gap-2">
                <select data-wf-select="1" data-id="${esc(id)}" class="w-full sm:w-44 px-3 py-2 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-bold">
                  <option value="Pending" ${wf === 'Pending' ? 'selected' : ''}>Pending</option>
                  <option value="Verified" ${wf === 'Verified' ? 'selected' : ''}>Verified</option>
                  <option value="Closed" ${wf === 'Closed' ? 'selected' : ''}>Closed</option>
                </select>
                <button data-wf-save="1" data-id="${esc(id)}" class="px-3 py-2 rounded-lg bg-slate-900 hover:bg-black text-white text-xs font-bold whitespace-nowrap">Update</button>
              </div>
            </div>
          </td>
        </tr>`;
      }).join('');
      if (window.lucide) window.lucide.createIcons();
      tbody.querySelectorAll('button[data-wf-save="1"][data-id]').forEach((b) => {
        b.addEventListener('click', async () => {
          const id = b.getAttribute('data-id');
          const sel = tbody.querySelector(`select[data-wf-select="1"][data-id="${CSS && CSS.escape ? CSS.escape(String(id)) : String(id)}"]`);
          const wf = sel ? (sel.value || '') : '';
          if (!wf) return;
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
    if (filterFrom) filterFrom.addEventListener('change', loadViolations);
    if (filterTo) filterTo.addEventListener('change', loadViolations);
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
          closeModal();
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
