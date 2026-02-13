<?php
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module3.issue','module3.read','module3.analytics']);

require_once __DIR__ . '/../../includes/db.php';
$db = db();

$pendingPay = (int)($db->query("SELECT COUNT(*) AS c FROM sts_tickets WHERE status='Pending Payment'")->fetch_assoc()['c'] ?? 0);
$paid = (int)($db->query("SELECT COUNT(*) AS c FROM sts_tickets WHERE status='Paid'")->fetch_assoc()['c'] ?? 0);
$closed = (int)($db->query("SELECT COUNT(*) AS c FROM sts_tickets WHERE status='Closed'")->fetch_assoc()['c'] ?? 0);
?>

<div class="mx-auto max-w-7xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-8">
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between border-b border-slate-200 dark:border-slate-700 pb-6">
    <div>
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Ticket Tracking (Official STS Reference)</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Record official STS tickets and link them to recorded violations for monitoring and reporting.</p>
    </div>
    <div class="flex items-center gap-2">
      <button type="button" id="btnOpenStsModal" class="inline-flex items-center justify-center gap-2 rounded-md bg-blue-700 hover:bg-blue-800 px-4 py-2.5 text-sm font-semibold text-white transition-colors">
        <i data-lucide="plus" class="w-4 h-4"></i>
        Record STS Ticket
      </button>
    </div>
  </div>

  <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Pending Payment</div>
      <div class="mt-2 text-2xl font-bold text-amber-600 dark:text-amber-400"><?php echo number_format($pendingPay); ?></div>
    </div>
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Paid</div>
      <div class="mt-2 text-2xl font-bold text-emerald-600 dark:text-emerald-400"><?php echo number_format($paid); ?></div>
    </div>
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Closed</div>
      <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-white"><?php echo number_format($closed); ?></div>
    </div>
  </div>

  <div id="toast-container" class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 z-[100] flex flex-col gap-2 pointer-events-none"></div>

  <div id="modalStsTicket" class="fixed inset-0 z-[220] hidden">
    <div data-modal-backdrop class="absolute inset-0 bg-black/40"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
      <div class="w-full max-w-5xl rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-xl overflow-hidden flex flex-col max-h-[90vh]">
        <div class="p-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
          <div>
            <div class="text-sm font-black text-slate-900 dark:text-white">Record STS Ticket</div>
            <div class="text-xs text-slate-500 dark:text-slate-400 font-semibold">Manual entry or upload scan/photo of official ticket.</div>
          </div>
          <button type="button" data-modal-close class="p-2 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-200">
            <i data-lucide="x" class="w-4 h-4"></i>
          </button>
        </div>
        <div class="p-6 overflow-auto">
          <form id="formCreateStsTicket" class="grid grid-cols-1 md:grid-cols-12 gap-6" enctype="multipart/form-data" novalidate>
            <div class="md:col-span-4 space-y-4">
              <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Ticket No.</label>
                <input name="sts_ticket_no" required minlength="3" maxlength="64" pattern="^(?:[0-9A-Za-z/]|-){3,64}$" class="w-full px-4 py-2.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md text-sm font-semibold" placeholder="STS-2026-000123">
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Issued By</label>
                <input name="issued_by" maxlength="128" class="w-full px-4 py-2.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md text-sm font-semibold" placeholder="Officer / STS Reference">
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Date Issued</label>
                <input name="date_issued" type="date" class="w-full px-4 py-2.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md text-sm font-semibold">
              </div>
            </div>
            <div class="md:col-span-5 space-y-4">
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Fine Amount</label>
                  <input name="fine_amount" type="number" min="0" step="0.01" class="w-full px-4 py-2.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md text-sm font-semibold" placeholder="0.00">
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Status</label>
                  <select name="status" class="w-full px-4 py-2.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md text-sm font-semibold">
                    <option value="Pending Payment">Pending Payment</option>
                    <option value="Paid">Paid</option>
                    <option value="Closed">Closed</option>
                  </select>
                </div>
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Linked Violation (optional)</label>
                <input id="linkedViolationSearch" class="w-full px-4 py-2.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md text-sm font-semibold mb-2" placeholder="Search violations (plate/type/location)…">
                <select id="linkedViolationSelect" name="linked_violation_id" class="w-full px-4 py-2.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md text-sm font-semibold">
                  <option value="">None</option>
                </select>
                <div class="mt-1 text-xs font-semibold text-slate-500 dark:text-slate-400">Only violations not yet recorded in STS are listed. Selecting one auto-fills Fine Amount and Date Issued.</div>
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Verification Notes</label>
                <textarea name="verification_notes" rows="3" class="w-full px-4 py-2.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md text-sm font-semibold"></textarea>
              </div>
            </div>
            <div class="md:col-span-3 space-y-4">
              <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Upload Ticket (optional)</label>
                <input name="ticket_scan" type="file" accept=".jpg,.jpeg,.png,.pdf" class="w-full text-sm">
              </div>
              <button id="btnCreateStsTicket" class="w-full py-3 rounded-lg bg-blue-700 hover:bg-blue-800 text-white font-black">Save</button>
              <button type="button" id="btnCloseStsModal" class="w-full py-3 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 font-black">Cancel</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <div class="p-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <div class="text-base font-black text-slate-900 dark:text-white">STS Tickets</div>
        <div class="text-sm text-slate-500 dark:text-slate-400">Track payment and closure status.</div>
      </div>
      <div class="flex flex-col sm:flex-row sm:items-center gap-2 w-full sm:w-auto">
        <select id="filterStatus" class="w-full sm:w-auto px-3 py-2 rounded-md bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
          <option value="">All</option>
          <option value="Pending Payment">Pending Payment</option>
          <option value="Paid">Paid</option>
          <option value="Closed">Closed</option>
        </select>
        <input id="filterFrom" type="date" class="w-full sm:w-auto px-3 py-2 rounded-md bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
        <input id="filterTo" type="date" class="w-full sm:w-auto px-3 py-2 rounded-md bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
        <input id="filterQ" class="w-full sm:w-72 px-3 py-2 rounded-md bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="Search ticket/plate…">
        <button id="btnReload" class="w-full sm:w-auto px-4 py-2 rounded-md bg-slate-900 hover:bg-black text-white text-sm font-semibold">Reload</button>
      </div>
    </div>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-white dark:bg-slate-800">
          <tr class="text-left text-xs font-black uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
            <th class="px-5 py-3">Date</th>
            <th class="px-5 py-3">Ticket No</th>
            <th class="px-5 py-3">Plate</th>
            <th class="px-5 py-3">Fine</th>
            <th class="px-5 py-3">Status</th>
            <th class="px-5 py-3">Action</th>
          </tr>
        </thead>
        <tbody id="ticketsTbody" class="divide-y divide-slate-200 dark:divide-slate-700"></tbody>
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

    const formCreate = document.getElementById('formCreateStsTicket');
    const btnCreate = document.getElementById('btnCreateStsTicket');
    const modal = document.getElementById('modalStsTicket');
    const btnOpenModal = document.getElementById('btnOpenStsModal');
    const btnCloseModal = document.getElementById('btnCloseStsModal');
    const tbody = document.getElementById('ticketsTbody');
    const filterStatus = document.getElementById('filterStatus');
    const filterFrom = document.getElementById('filterFrom');
    const filterTo = document.getElementById('filterTo');
    const filterQ = document.getElementById('filterQ');
    const btnReload = document.getElementById('btnReload');
    const linkedViolationSearch = document.getElementById('linkedViolationSearch');
    const linkedViolationSelect = document.getElementById('linkedViolationSelect');
    const fineAmountEl = formCreate ? formCreate.querySelector('input[name="fine_amount"]') : null;
    const dateIssuedEl = formCreate ? formCreate.querySelector('input[name="date_issued"]') : null;
 
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

    async function loadTickets() {
      if (!tbody) return;
      tbody.innerHTML = `<tr><td colspan="6" class="px-5 py-6 text-center text-slate-500">Loading…</td></tr>`;
      const qs = new URLSearchParams();
      if (filterStatus && filterStatus.value) qs.set('status', filterStatus.value);
      if (filterFrom && filterFrom.value) qs.set('from', filterFrom.value);
      if (filterTo && filterTo.value) qs.set('to', filterTo.value);
      if (filterQ && filterQ.value.trim() !== '') qs.set('q', filterQ.value.trim());
      const res = await fetch(rootUrl + '/admin/api/module3/sts_tickets_list.php?' + qs.toString());
      const data = await res.json().catch(() => null);
      if (!data || !data.ok) { tbody.innerHTML = `<tr><td colspan="6" class="px-5 py-6 text-center text-rose-600 font-semibold">Failed to load.</td></tr>`; return; }
      const rows = Array.isArray(data.data) ? data.data : [];
      if (!rows.length) { tbody.innerHTML = `<tr><td colspan="6" class="px-5 py-6 text-center text-slate-500">No records.</td></tr>`; return; }
      tbody.innerHTML = rows.map((r) => {
        const id = r.sts_ticket_id;
        const dt = (r.date_issued || '').toString();
        const no = (r.sts_ticket_no || '').toString();
        const plate = (r.plate_number || '').toString();
        const fine = Number(r.fine_amount || 0) || 0;
        const st = (r.status || '').toString();
        const scan = (r.ticket_scan_path || '').toString();
        const scanLink = scan ? `<a class="text-blue-700 hover:underline font-bold" target="_blank" href="${esc(rootUrl + '/admin/uploads/' + encodeURIComponent(scan))}">Scan</a>` : `<span class="text-slate-400">—</span>`;
        return `<tr>
          <td class="px-5 py-3 text-xs font-semibold text-slate-600 dark:text-slate-300">${esc(dt)}</td>
          <td class="px-5 py-3 font-black">${esc(no)}</td>
          <td class="px-5 py-3 text-xs font-semibold text-slate-600 dark:text-slate-300">${esc(plate || '-')}</td>
          <td class="px-5 py-3 text-xs font-black">₱${esc(fine.toFixed(2))}</td>
          <td class="px-5 py-3 text-xs font-black">${esc(st)}</td>
          <td class="px-5 py-3">
            <div class="flex flex-wrap items-center gap-2">
              ${scanLink}
              <button data-st="Pending Payment" data-id="${esc(id)}" class="px-3 py-2 rounded-md bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-bold">Pending</button>
              <button data-st="Paid" data-id="${esc(id)}" class="px-3 py-2 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold">Paid</button>
              <button data-st="Closed" data-id="${esc(id)}" class="px-3 py-2 rounded-md bg-slate-900 hover:bg-black text-white text-xs font-bold">Close</button>
            </div>
          </td>
        </tr>`;
      }).join('');
      tbody.querySelectorAll('button[data-st][data-id]').forEach((b) => {
        b.addEventListener('click', async () => {
          const st = b.getAttribute('data-st');
          const id = b.getAttribute('data-id');
          const notes = prompt('Verification notes (optional):', '');
          const fd = new FormData();
          fd.append('sts_ticket_id', String(id));
          fd.append('status', String(st));
          fd.append('verification_notes', String(notes || ''));
          const res2 = await fetch(rootUrl + '/admin/api/module3/sts_tickets_update_status.php', { method: 'POST', body: fd });
          const d2 = await res2.json().catch(() => null);
          if (!d2 || !d2.ok) { showToast('Failed to update.', 'error'); return; }
          showToast('Updated.');
          loadTickets();
        });
      });
    }

    function formatViolationOption(v) {
      const id = Number(v.id || 0) || 0;
      const plate = (v.plate_number || '').toString().toUpperCase();
      const code = (v.violation_type || '').toString();
      const desc = (v.violation_desc || '').toString();
      const loc = (v.location || '').toString();
      const rawDt = (v.violation_date || '').toString();
      const date = rawDt ? rawDt.slice(0, 10) : '';
      const parts = [];
      if (plate) parts.push(plate);
      if (code) parts.push(code);
      if (desc) parts.push(desc);
      if (loc) parts.push(loc);
      if (date) parts.push(date);
      const label = parts.join(' • ');
      return { id, label, fine: Number(v.fine_amount || 0) || 0, date };
    }

    async function loadUnlinkedViolations(q) {
      if (!linkedViolationSelect) return;
      const qs = new URLSearchParams();
      if ((q || '').toString().trim() !== '') qs.set('q', (q || '').toString().trim());
      qs.set('limit', '200');
      const res = await fetch(rootUrl + '/admin/api/module3/unlinked_violations.php?' + qs.toString());
      const data = await res.json().catch(() => null);
      if (!data || !data.ok) throw new Error((data && data.error) ? String(data.error) : 'violations_load_failed');
      const rows = Array.isArray(data.data) ? data.data : [];
      const current = (linkedViolationSelect.value || '').toString();
      const opts = ['<option value="">None</option>'].concat(rows.map((v) => {
        const o = formatViolationOption(v);
        const fine = o.fine ? o.fine.toFixed(2) : '0.00';
        return `<option value="${esc(o.id)}" data-fine="${esc(fine)}" data-date="${esc(o.date)}">${esc(o.id + ' • ' + o.label)}</option>`;
      }));
      linkedViolationSelect.innerHTML = opts.join('');
      if (current) linkedViolationSelect.value = current;
    }

    function applySelectedViolationMeta() {
      if (!linkedViolationSelect) return;
      const opt = linkedViolationSelect.options[linkedViolationSelect.selectedIndex];
      if (!opt) return;
      const val = (linkedViolationSelect.value || '').toString().trim();
      if (val === '') return;
      const fine = (opt.getAttribute('data-fine') || '').toString();
      const date = (opt.getAttribute('data-date') || '').toString();
      if (fineAmountEl && fine) fineAmountEl.value = String(fine);
      if (dateIssuedEl && date) dateIssuedEl.value = String(date);
    }

    if (btnReload) btnReload.addEventListener('click', loadTickets);
    if (filterStatus) filterStatus.addEventListener('change', loadTickets);
    if (filterFrom) filterFrom.addEventListener('change', loadTickets);
    if (filterTo) filterTo.addEventListener('change', loadTickets);
    if (filterQ) filterQ.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); loadTickets(); } });

    if (formCreate) {
      formCreate.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!formCreate.checkValidity()) { formCreate.reportValidity(); return; }
        if (btnCreate) { btnCreate.disabled = true; btnCreate.textContent = 'Saving...'; }
        try {
          const fd = new FormData(formCreate);
          const res = await fetch(rootUrl + '/admin/api/module3/sts_tickets_create.php', { method: 'POST', body: fd });
          const data = await res.json().catch(() => null);
          if (!data || !data.ok) throw new Error((data && data.error) ? String(data.error) : 'save_failed');
          showToast('Saved.');
          formCreate.reset();
          if (linkedViolationSelect) linkedViolationSelect.value = '';
          if (linkedViolationSearch) linkedViolationSearch.value = '';
          loadUnlinkedViolations('').catch(() => {});
          loadTickets();
          closeModal();
        } catch (err) {
          showToast((err && err.message) ? err.message : 'Failed.', 'error');
        } finally {
          if (btnCreate) { btnCreate.disabled = false; btnCreate.textContent = 'Save'; }
        }
      });
    }

    let vioDebounce = null;
    if (linkedViolationSearch) {
      linkedViolationSearch.addEventListener('input', () => {
        if (vioDebounce) clearTimeout(vioDebounce);
        vioDebounce = setTimeout(() => {
          loadUnlinkedViolations(linkedViolationSearch.value || '').catch(() => {});
        }, 220);
      });
    }
    if (linkedViolationSelect) {
      linkedViolationSelect.addEventListener('change', () => {
        applySelectedViolationMeta();
      });
    }

    Promise.resolve()
      .then(loadTickets)
      .then(() => loadUnlinkedViolations(''))
      .catch(() => { showToast('Failed to initialize.', 'error'); });
  })();
</script>
