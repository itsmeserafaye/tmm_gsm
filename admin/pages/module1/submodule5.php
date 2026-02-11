<?php
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module1.write','module1.vehicles.write']);

require_once __DIR__ . '/../../includes/db.php';
$db = db();

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';

$canReview = has_permission('module1.write');
$canCreate = has_permission('module1.vehicles.write');

$vehicles = [];
$resV = $db->query("SELECT v.id, UPPER(v.plate_number) AS plate_number, v.operator_id, COALESCE(NULLIF(o.registered_name,''), NULLIF(o.name,''), o.full_name, v.operator_name) AS operator_name
                    FROM vehicles v
                    LEFT JOIN operators o ON o.id=v.operator_id
                    WHERE v.operator_id IS NOT NULL AND v.operator_id>0 AND COALESCE(v.plate_number,'')<>''
                    ORDER BY v.created_at DESC LIMIT 1500");
if ($resV) while ($r = $resV->fetch_assoc()) $vehicles[] = $r;

$operators = [];
$resO = $db->query("SELECT id, operator_type, COALESCE(NULLIF(registered_name,''), NULLIF(name,''), full_name) AS display_name, workflow_status
                    FROM operators
                    WHERE COALESCE(NULLIF(workflow_status,''),'Draft') <> 'Inactive'
                    ORDER BY created_at DESC LIMIT 1500");
if ($resO) while ($r = $resO->fetch_assoc()) $operators[] = $r;
?>

<div class="mx-auto max-w-7xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-6">
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between border-b border-slate-200 dark:border-slate-700 pb-6">
    <div>
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Ownership Transfer</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-3xl">Record transfer requests for operational tracking. Legal ownership remains under LTO.</p>
    </div>
    <div class="flex flex-col sm:flex-row sm:items-center gap-3 w-full md:w-auto">
      <a href="?page=puv-database/vehicle-encoding" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
        <i data-lucide="bus" class="w-4 h-4"></i>
        Vehicle Encoding
      </a>
      <a href="?page=puv-database/link-vehicle-to-operator" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
        <i data-lucide="link-2" class="w-4 h-4"></i>
        Vehicle–Operator Linking
      </a>
    </div>
  </div>

  <div id="toast-container" class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 z-[100] flex flex-col gap-3 pointer-events-none"></div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
      <div class="p-5 border-b border-slate-200 dark:border-slate-700">
        <div class="text-sm font-black text-slate-900 dark:text-white">Create Transfer Request</div>
        <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">Upload deed/authorization and choose the new operator.</div>
      </div>
      <div class="p-5">
        <?php if ($canCreate): ?>
          <form id="formCreateTransfer" class="space-y-4" enctype="multipart/form-data" novalidate>
            <div>
              <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">Vehicle</label>
              <div class="relative" data-combobox="1" data-kind="vehicle">
                <input type="hidden" name="vehicle_id" data-combo-id="1" required>
                <div class="flex items-center gap-2">
                  <input type="text" data-combo-display="1" readonly
                    class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sm font-semibold focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all cursor-pointer"
                    placeholder="Select vehicle…">
                  <button type="button" data-combo-toggle="1" class="shrink-0 px-3 py-2.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                    <i data-lucide="chevron-down" class="w-4 h-4 text-slate-600 dark:text-slate-300"></i>
                  </button>
                </div>
                <div data-combo-panel="1" class="hidden absolute z-20 mt-2 w-full rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-xl overflow-hidden">
                  <div class="p-3 border-b border-slate-200 dark:border-slate-700">
                    <input type="text" data-combo-search="1"
                      class="w-full px-3 py-2 rounded-lg bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sm font-semibold"
                      placeholder="Search plate/operator…">
                  </div>
                  <div data-combo-list="1" class="max-h-64 overflow-y-auto"></div>
                </div>
              </div>
            </div>

            <div>
              <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">New Operator</label>
              <div class="relative" data-combobox="1" data-kind="operator">
                <input type="hidden" name="to_operator_id" data-combo-id="1" required>
                <div class="flex items-center gap-2">
                  <input type="text" data-combo-display="1" readonly
                    class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sm font-semibold focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all cursor-pointer"
                    placeholder="Select operator…">
                  <button type="button" data-combo-toggle="1" class="shrink-0 px-3 py-2.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                    <i data-lucide="chevron-down" class="w-4 h-4 text-slate-600 dark:text-slate-300"></i>
                  </button>
                </div>
                <div data-combo-panel="1" class="hidden absolute z-20 mt-2 w-full rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-xl overflow-hidden">
                  <div class="p-3 border-b border-slate-200 dark:border-slate-700">
                    <input type="text" data-combo-search="1"
                      class="w-full px-3 py-2 rounded-lg bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sm font-semibold"
                      placeholder="Search operator…">
                  </div>
                  <div data-combo-list="1" class="max-h-64 overflow-y-auto"></div>
                </div>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">Transfer Type</label>
                <select name="transfer_type" class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sm font-semibold focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all">
                  <option value="Sale">Sale</option>
                  <option value="Donation">Donation</option>
                  <option value="Inheritance">Inheritance</option>
                  <option value="Reassignment" selected>Reassignment</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">LTO Reference No (optional)</label>
                <input type="text" name="lto_reference_no" maxlength="64"
                  class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sm font-semibold focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                  placeholder="e.g., OR/CR / reference">
              </div>
            </div>

            <div>
              <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">Effective Date (optional)</label>
              <input type="date" name="effective_date"
                class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sm font-semibold focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">Deed/Authorization (required)</label>
                <input type="file" name="deed_doc" accept=".pdf,.jpg,.jpeg,.png" required
                  class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-slate-700 dark:file:text-slate-100">
              </div>
              <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">OR/CR (optional)</label>
                <input type="file" name="orcr_doc" accept=".pdf,.jpg,.jpeg,.png"
                  class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200 dark:file:bg-slate-700 dark:file:text-slate-100">
              </div>
            </div>

            <button id="btnCreateTransfer" type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-slate-900 hover:bg-black text-white px-4 py-3 text-sm font-bold shadow-lg transition-all active:scale-[0.98]">
              <i data-lucide="send" class="w-4 h-4"></i>
              Submit Transfer Request
            </button>
          </form>
        <?php else: ?>
          <div class="rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700 p-4">
            <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Note</div>
            <div class="mt-1 text-sm font-semibold text-slate-700 dark:text-slate-200">Transfer requests are created in the Operator Portal. This screen is for approval and decision-making.</div>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
      <div class="p-5 border-b border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between">
        <div>
          <div class="text-sm font-black text-slate-900 dark:text-white">Transfer Requests</div>
          <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">Review, approve, or reject requests.</div>
        </div>
        <div class="flex flex-col sm:flex-row sm:items-center gap-2 w-full sm:w-auto">
          <input id="qInput" class="w-full sm:w-56 px-3 py-2 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="Search plate/operator">
          <select id="statusFilter" class="w-full sm:w-auto px-3 py-2 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
            <option value="">All</option>
            <option value="Pending">Pending</option>
            <option value="Approved">Approved</option>
            <option value="Rejected">Rejected</option>
          </select>
          <button type="button" id="btnReload" class="w-full sm:w-auto px-3 py-2 rounded-md bg-slate-900 dark:bg-slate-700 text-white text-sm font-semibold">Reload</button>
        </div>
      </div>
      <div class="p-5">
        <div id="transferList" class="space-y-3"></div>
      </div>
    </div>
  </div>
</div>

<!-- Approve Transfer Modal -->
<div id="modalApproveTransfer" class="fixed inset-0 z-[200] hidden items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
  <div class="w-full max-w-md bg-white dark:bg-slate-900 rounded-2xl shadow-2xl overflow-hidden ring-1 ring-slate-900/5">
    <div class="p-6 border-b border-slate-100 dark:border-slate-800">
      <h3 class="text-lg font-bold text-slate-900 dark:text-white">Approve Transfer</h3>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Confirm approval and set effective date.</p>
    </div>
    <form id="formApproveTransfer" class="p-6 space-y-4">
      <input type="hidden" name="transfer_id">
      <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">Effective Date</label>
        <input type="date" name="effective_date" required class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sm font-semibold focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all">
        <p class="text-xs text-slate-400 mt-1.5">Date when the transfer becomes legally active.</p>
      </div>
      <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">New Operator</label>
        <div id="approveRequestedTo" class="text-xs text-slate-500 dark:text-slate-400 mb-2"></div>
        <select id="approveToOperatorSelect" name="to_operator_id" class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sm font-semibold focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all">
          <option value="">Select operator…</option>
        </select>
        <p class="text-xs text-slate-400 mt-1.5">Required if the request was submitted with text-only new owner.</p>
      </div>
      <div class="flex items-center justify-end gap-3 pt-2">
        <button type="button" id="btnCancelApprove" class="px-4 py-2.5 rounded-xl text-sm font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">Cancel</button>
        <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-bold text-white bg-emerald-600 hover:bg-emerald-700 shadow-lg shadow-emerald-500/20 transition-all transform active:scale-95">Confirm Approval</button>
      </div>
    </form>
  </div>
</div>

<script>
  (function(){
    const rootUrl = <?php echo json_encode($rootUrl); ?>;
    const canReview = <?php echo json_encode($canReview); ?>;
    const vehicles = <?php echo json_encode(array_map(function($v){
      return [
        'id' => (int)($v['id'] ?? 0),
        'plate_number' => (string)($v['plate_number'] ?? ''),
        'operator_id' => (int)($v['operator_id'] ?? 0),
        'operator_name' => (string)($v['operator_name'] ?? ''),
      ];
    }, $vehicles)); ?>;
    const operators = <?php echo json_encode(array_map(function($o){
      return [
        'id' => (int)($o['id'] ?? 0),
        'display_name' => (string)($o['display_name'] ?? ''),
        'operator_type' => (string)($o['operator_type'] ?? ''),
        'workflow_status' => (string)($o['workflow_status'] ?? ''),
      ];
    }, $operators)); ?>;

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

    function escapeHtml(s) {
      return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\"/g,'&quot;');
    }

    function setupCombo(box, items, renderText) {
      const idInput = box.querySelector('[data-combo-id="1"]');
      const displayInput = box.querySelector('[data-combo-display="1"]');
      const toggleBtn = box.querySelector('[data-combo-toggle="1"]');
      const panel = box.querySelector('[data-combo-panel="1"]');
      const search = box.querySelector('[data-combo-search="1"]');
      const list = box.querySelector('[data-combo-list="1"]');
      if (!idInput || !displayInput || !panel || !search || !list) return;

      const open = () => {
        panel.classList.remove('hidden');
        render(search.value);
        setTimeout(() => { search.focus(); }, 0);
        if (window.lucide) window.lucide.createIcons();
      };
      const close = () => { panel.classList.add('hidden'); };
      const render = (q) => {
        const query = (q || '').toString().trim().toUpperCase();
        const filtered = items.filter((it) => {
          const label = renderText(it);
          return !query || label.toUpperCase().includes(query);
        });
        if (!filtered.length) {
          list.innerHTML = '<div class="p-3 text-sm text-slate-500 dark:text-slate-400 italic">No matches.</div>';
          return;
        }
        list.innerHTML = filtered.map((it) => {
          const id = String(it.id || '');
          const label = renderText(it);
          return `<button type="button" class="w-full text-left px-4 py-2.5 text-sm font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors" data-item="1" data-id="${escapeHtml(id)}">${escapeHtml(label)}</button>`;
        }).join('');
        list.querySelectorAll('[data-item="1"]').forEach((btn) => {
          btn.addEventListener('click', () => {
            idInput.value = btn.getAttribute('data-id') || '';
            displayInput.value = (btn.textContent || '').trim();
            close();
            search.value = '';
          });
        });
      };

      displayInput.addEventListener('click', () => { panel.classList.contains('hidden') ? open() : close(); });
      if (toggleBtn) toggleBtn.addEventListener('click', () => { panel.classList.contains('hidden') ? open() : close(); });
      search.addEventListener('input', () => render(search.value));
      document.addEventListener('click', (e) => { if (!box.contains(e.target)) close(); });
      document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
    }

    document.querySelectorAll('[data-combobox="1"]').forEach((box) => {
      const kind = box.getAttribute('data-kind');
      if (kind === 'vehicle') {
        setupCombo(box, vehicles, (v) => {
          const op = v.operator_name ? (' • ' + v.operator_name) : '';
          return (v.plate_number || '') + op;
        });
      } else if (kind === 'operator') {
        setupCombo(box, operators, (o) => {
          const t = o.operator_type ? (' • ' + o.operator_type) : '';
          return (o.display_name || ('Operator #' + (o.id || ''))) + t;
        });
      }
    });

    async function loadTransfers() {
      const q = (document.getElementById('qInput')?.value || '').toString();
      const status = (document.getElementById('statusFilter')?.value || '').toString();
      const params = new URLSearchParams();
      if (q) params.set('q', q);
      if (status) params.set('status', status);
      const res = await fetch(rootUrl + '/admin/api/module1/ownership_transfer_list.php?' + params.toString());
      const data = await res.json();
      if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'load_failed');
      return Array.isArray(data.data) ? data.data : [];
    }

    function badge(status) {
      const s = String(status || '');
      if (s === 'Approved') return 'bg-emerald-100 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-400 dark:ring-emerald-500/20';
      if (s === 'Rejected') return 'bg-rose-100 text-rose-700 ring-rose-600/20 dark:bg-rose-900/30 dark:text-rose-400 dark:ring-rose-500/20';
      return 'bg-amber-100 text-amber-700 ring-amber-600/20 dark:bg-amber-900/30 dark:text-amber-400 dark:ring-amber-500/20';
    }

    function renderTransfers(rows) {
      const el = document.getElementById('transferList');
      if (!el) return;
      if (!rows.length) {
        el.innerHTML = '<div class="text-sm text-slate-500 dark:text-slate-400 italic">No transfer requests.</div>';
        return;
      }
      el.innerHTML = rows.map((r) => {
        const id = String(r.transfer_id || '');
        const plate = String(r.plate_number || '');
        const from = String(r.from_operator_name || ('#' + (r.from_operator_id || '')));
        const to = String(r.to_operator_name || ('#' + (r.to_operator_id || '')));
        const type = String(r.transfer_type || '');
        const st = String(r.status || 'Pending');
        const dt = r.created_at ? new Date(r.created_at) : null;
        const when = dt && !isNaN(dt.getTime()) ? dt.toLocaleString() : '';
        const deed = r.deed_of_sale_path ? (rootUrl + '/admin/uploads/' + encodeURIComponent(String(r.deed_of_sale_path))) : '';
        const orcr = r.orcr_path ? (rootUrl + '/admin/uploads/' + encodeURIComponent(String(r.orcr_path))) : '';
        const actions = (canReview && st === 'Pending') ? `
          <div class="flex items-center gap-2">
            <button type="button" class="px-3 py-2 rounded-lg text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white transition-colors" data-act="approve" data-id="${escapeHtml(id)}" data-toid="${escapeHtml(String(r.to_operator_id || ''))}" data-toname="${escapeHtml(String(r.to_operator_name || ''))}">Approve</button>
            <button type="button" class="px-3 py-2 rounded-lg text-xs font-bold bg-rose-600 hover:bg-rose-700 text-white transition-colors" data-act="reject" data-id="${escapeHtml(id)}">Reject</button>
          </div>
        ` : '';
        return `
          <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50/40 dark:bg-slate-900/20">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <div class="text-sm font-black text-slate-900 dark:text-white">${escapeHtml(plate)}</div>
                  <span class="px-2.5 py-1 rounded-lg text-xs font-bold ring-1 ring-inset ${badge(st)}">${escapeHtml(st)}</span>
                  <span class="inline-flex items-center rounded-lg bg-white dark:bg-slate-900 px-2.5 py-1 text-xs font-bold text-slate-500 dark:text-slate-400 ring-1 ring-inset ring-slate-200 dark:ring-slate-700">${escapeHtml(type)}</span>
                </div>
                <div class="mt-2 text-sm text-slate-600 dark:text-slate-300 font-semibold">From: ${escapeHtml(from)} → To: ${escapeHtml(to)}</div>
                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">${escapeHtml(when)}${r.requested_by_name ? (' • Requested by ' + escapeHtml(String(r.requested_by_name))) : ''}</div>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                  ${deed ? `<a class="px-3 py-2 rounded-lg text-xs font-bold bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors" href="${escapeHtml(deed)}" target="_blank">Deed</a>` : ``}
                  ${orcr ? `<a class="px-3 py-2 rounded-lg text-xs font-bold bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors" href="${escapeHtml(orcr)}" target="_blank">OR/CR</a>` : ``}
                </div>
              </div>
              ${actions}
            </div>
          </div>
        `;
      }).join('');

      el.querySelectorAll('button[data-act]').forEach((btn) => {
        btn.addEventListener('click', async () => {
          const action = btn.getAttribute('data-act');
          const id = btn.getAttribute('data-id');
          if (!id) return;

          if (action === 'approve') {
            // Open Modal
            const modal = document.getElementById('modalApproveTransfer');
            const form = document.getElementById('formApproveTransfer');
            if (modal && form) {
              form.reset();
              form.querySelector('[name="transfer_id"]').value = id;
              // Set default date to today
              form.querySelector('[name="effective_date"]').value = new Date().toISOString().split('T')[0];
              const sel = document.getElementById('approveToOperatorSelect');
              const reqTo = document.getElementById('approveRequestedTo');
              const toIdRaw = btn.getAttribute('data-toid') || '';
              const toId = parseInt(toIdRaw, 10) || 0;
              const toName = btn.getAttribute('data-toname') || '';
              if (reqTo) {
                reqTo.textContent = toName ? ('Requested new owner: ' + toName) : '';
              }
              if (sel) {
                if (!sel.dataset.loaded) {
                  const opts = operators.map((o) => `<option value="${escapeHtml(String(o.id || ''))}">${escapeHtml(String(o.display_name || ''))}</option>`).join('');
                  sel.insertAdjacentHTML('beforeend', opts);
                  sel.dataset.loaded = '1';
                }
                sel.value = toId > 0 ? String(toId) : '';
                sel.required = toId <= 0;
              }
              modal.classList.remove('hidden');
              modal.classList.add('flex');
            }
            return;
          }

          // Reject Logic
          if (action === 'reject') {
            const remarks = (prompt('Reject remarks (required):') || '').trim();
            if (!remarks) { showToast('Remarks required.', 'error'); return; }
            
            const fd = new FormData();
            fd.append('transfer_id', id);
            fd.append('action', action);
            fd.append('remarks', remarks);
            submitReview(fd, action);
          }
        });
      });
    }

    async function submitReview(fd, action) {
      try {
        const res = await fetch(rootUrl + '/admin/api/module1/ownership_transfer_review.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'review_failed');
        showToast(action === 'approve' ? 'Transfer approved.' : 'Transfer rejected.');
        await refresh();
      } catch (err) {
        const raw = (err && err.message) ? String(err.message) : '';
        const msg = raw === 'active_violations' ? 'Cannot approve: vehicle has active violations.'
          : raw === 'orcr_not_valid' ? 'Cannot approve: OR/CR is not valid.'
          : raw === 'missing_to_operator_id' ? 'Select the new operator before approving.'
          : (raw || 'Failed');
        showToast(msg, 'error');
      }
    }

    // Modal Logic
    const modalApprove = document.getElementById('modalApproveTransfer');
    const formApprove = document.getElementById('formApproveTransfer');
    const btnCancelApprove = document.getElementById('btnCancelApprove');

    if (btnCancelApprove && modalApprove) {
      btnCancelApprove.addEventListener('click', () => {
        modalApprove.classList.add('hidden');
        modalApprove.classList.remove('flex');
      });
    }

    if (formApprove && modalApprove) {
      formApprove.addEventListener('submit', (e) => {
        e.preventDefault();
        const fd = new FormData(formApprove);
        fd.append('action', 'approve');
        submitReview(fd, 'approve');
        modalApprove.classList.add('hidden');
        modalApprove.classList.remove('flex');
      });
    }

    async function refresh() {
      try {
        const rows = await loadTransfers();
        renderTransfers(rows);
      } catch (e) {
        const el = document.getElementById('transferList');
        if (el) el.innerHTML = '<div class="text-sm text-rose-600">Failed to load requests.</div>';
      }
    }

    const btnReload = document.getElementById('btnReload');
    if (btnReload) btnReload.addEventListener('click', refresh);
    refresh();

    const form = document.getElementById('formCreateTransfer');
    const btn = document.getElementById('btnCreateTransfer');
    if (form && btn) {
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!form.checkValidity()) { form.reportValidity(); return; }
        const orig = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Submitting...';
        try {
          const fd = new FormData(form);
          const res = await fetch(rootUrl + '/admin/api/module1/ownership_transfer_create.php', { method: 'POST', body: fd });
          const data = await res.json();
          if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'create_failed');
          showToast('Transfer request submitted.');
          form.reset();
          form.querySelectorAll('[data-combo-id="1"]').forEach((inp) => { inp.value = ''; });
          form.querySelectorAll('[data-combo-display="1"]').forEach((inp) => { inp.value = ''; });
          await refresh();
        } catch (err) {
          const raw = (err && err.message) ? String(err.message) : '';
          const msg = raw === 'active_violations' ? 'Cannot create: vehicle has active violations.'
            : raw === 'orcr_not_valid' ? 'Cannot create: OR/CR is not valid.'
            : raw === 'franchise_active' ? 'Cannot create: franchise is still active under current operator.'
            : raw === 'missing_deed_doc' ? 'Deed/authorization document is required.'
            : (raw || 'Failed');
          showToast(msg, 'error');
        } finally {
          btn.disabled = false;
          btn.textContent = orig;
        }
      });
    }
  })();
</script>
