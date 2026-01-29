<?php
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module5.manage_terminal','module5.parking_fees']);

require_once __DIR__ . '/../../includes/db.php';
$db = db();

$terminalId = (int)($_GET['terminal_id'] ?? 0);
$tab = trim((string)($_GET['tab'] ?? 'slots'));
if (!in_array($tab, ['slots','payments'], true)) $tab = 'slots';
$canSlots = has_permission('module5.manage_terminal');
$canPay = has_permission('module5.parking_fees');

$terminals = [];
$resT = $db->query("SELECT id, name FROM terminals WHERE type='Parking' ORDER BY name ASC LIMIT 500");
if ($resT) while ($r = $resT->fetch_assoc()) $terminals[] = $r;

if ($terminalId <= 0 && $terminals) $terminalId = (int)($terminals[0]['id'] ?? 0);
$terminalName = '';
if ($terminalId > 0) {
  $stmtTN = $db->prepare("SELECT name FROM terminals WHERE id=? LIMIT 1");
  if ($stmtTN) {
    $stmtTN->bind_param('i', $terminalId);
    $stmtTN->execute();
    $rowTN = $stmtTN->get_result()->fetch_assoc();
    $stmtTN->close();
    $terminalName = (string)($rowTN['name'] ?? '');
  }
}

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';
?>

<div class="mx-auto max-w-5xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-6">
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between border-b border-slate-200 dark:border-slate-700 pb-6">
    <div>
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Parking Slots & Payments</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Manage parking slots and record parking fees.</p>
    </div>
    <div class="flex items-center gap-3">
      <a href="?page=module5/submodule4" class="inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
        <i data-lucide="home" class="w-4 h-4"></i>
        Terminals
      </a>
      <a href="?page=module5/submodule1" class="inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>
        Terminal List
      </a>
    </div>
  </div>

  <div id="toast-container" class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 z-[100] flex flex-col gap-3 pointer-events-none"></div>

  <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
    <div class="p-6 space-y-4">
      <?php if (has_permission('reports.export')): ?>
        <?php tmm_render_export_toolbar([
          [
            'href' => $rootUrl . '/admin/api/module5/export_slots.php?' . http_build_query(['terminal_id' => $terminalId, 'format' => 'csv']),
            'label' => 'CSV',
            'icon' => 'download'
          ],
          [
            'href' => $rootUrl . '/admin/api/module5/export_slots.php?' . http_build_query(['terminal_id' => $terminalId, 'format' => 'excel']),
            'label' => 'Excel',
            'icon' => 'file-spreadsheet'
          ]
        ], ['mb' => 'mb-0']); ?>
      <?php endif; ?>
      <form class="flex flex-col sm:flex-row gap-3 items-end" method="GET">
        <input type="hidden" name="page" value="module5/submodule3">
        <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
        <div class="flex-1">
          <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Parking</label>
          <select name="terminal_id" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
            <?php foreach ($terminals as $t): ?>
              <option value="<?php echo (int)$t['id']; ?>" <?php echo (int)$t['id'] === $terminalId ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$t['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="px-4 py-2.5 rounded-md bg-slate-900 dark:bg-slate-700 text-white font-semibold">Load</button>
      </form>

      <div class="flex items-center gap-2 border-t border-slate-200 dark:border-slate-700 pt-4">
        <a href="?page=module5/submodule3&<?php echo http_build_query(['terminal_id'=>$terminalId,'tab'=>'slots']); ?>"
          class="px-4 py-2.5 rounded-md text-sm font-semibold border <?php echo $tab === 'slots' ? 'bg-blue-700 text-white border-blue-700' : 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-600'; ?>">
          Slots
        </a>
        <a href="?page=module5/submodule3&<?php echo http_build_query(['terminal_id'=>$terminalId,'tab'=>'payments']); ?>"
          class="px-4 py-2.5 rounded-md text-sm font-semibold border <?php echo $tab === 'payments' ? 'bg-blue-700 text-white border-blue-700' : 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-600'; ?>">
          Payments
        </a>
        <div class="flex-1 text-right text-xs text-slate-500 dark:text-slate-400 font-semibold">
          <?php echo htmlspecialchars($terminalName !== '' ? $terminalName : ''); ?>
        </div>
      </div>
    </div>
  </div>

  <div id="panelSlots" class="<?php echo $tab === 'slots' ? '' : 'hidden'; ?>">
    <?php if (!$canSlots): ?>
      <div class="bg-white dark:bg-slate-800 p-6 rounded-lg border border-slate-200 dark:border-slate-700 text-sm text-slate-600 dark:text-slate-300 font-semibold">
        You do not have permission to manage terminal slots.
      </div>
    <?php else: ?>
      <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="p-6">
          <form id="formAddSlot" class="flex flex-col sm:flex-row gap-3 items-end" novalidate>
            <input type="hidden" name="terminal_id" value="<?php echo (int)$terminalId; ?>">
            <div class="flex-1">
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Slot No</label>
              <input name="slot_no" required minlength="2" maxlength="10" pattern="^(?:[0-9A-Za-z]|-){2,10}$" autocapitalize="characters" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold uppercase" placeholder="e.g., A-01">
            </div>
            <button id="btnAdd" class="px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold">Add Slot</button>
          </form>
        </div>
      </div>

      <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-700 border-b border-slate-200 dark:border-slate-700">
              <tr class="text-left text-slate-500 dark:text-slate-400">
                <th class="py-4 px-6 font-black uppercase tracking-widest text-xs">Slot</th>
                <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">Status</th>
                <th class="py-4 px-4 font-black uppercase tracking-widest text-xs text-right">Action</th>
              </tr>
            </thead>
            <tbody id="slotsBody" class="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-800">
              <tr><td colspan="3" class="py-10 text-center text-slate-500 font-medium italic">Loading...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <div id="panelPayments" class="<?php echo $tab === 'payments' ? '' : 'hidden'; ?>">
    <?php if (!$canPay): ?>
      <div class="bg-white dark:bg-slate-800 p-6 rounded-lg border border-slate-200 dark:border-slate-700 text-sm text-slate-600 dark:text-slate-300 font-semibold">
        You do not have permission to record payments.
      </div>
    <?php else: ?>
      <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="p-6 space-y-5">
          <form id="formPay" class="space-y-5" novalidate>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Slot</label>
                <select id="slotSelect" name="slot_id" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
                  <option value="">Select slot</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Amount</label>
                <input id="amountInput" name="amount" type="number" min="0.01" step="0.01" value="20.00" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., 50.00">
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Plate No</label>
                <input id="plateInput" name="plate_no" required minlength="4" maxlength="16" pattern="^(?:[0-9A-Za-z]|-){4,16}$" autocapitalize="characters" data-tmm-mask="plate_any" data-tmm-uppercase="1" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold uppercase" placeholder="Type plate">
              </div>
              <div>
                <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">OR No</label>
                <input id="orInput" name="or_no" required minlength="3" maxlength="40" pattern="^(?:[0-9A-Za-z/]|-){3,40}$" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold uppercase" placeholder="e.g., OR-2026-000123">
              </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
              <a id="btnTreasuryFeed" href="#" target="_blank" class="px-4 py-2.5 rounded-md bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 font-semibold">
                Treasury Feed
              </a>
              <button type="button" id="btnGenerate" class="px-4 py-2.5 rounded-md bg-slate-900 dark:bg-slate-700 text-white font-semibold">Generate Fee</button>
              <button id="btnPay" class="px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold">Save Payment</button>
            </div>
          </form>
        </div>
      </div>

      <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="p-6 flex items-center justify-between gap-3 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30">
          <div class="font-black text-slate-900 dark:text-white">Payments History</div>
          <button type="button" id="btnRefreshPayments" class="px-4 py-2 rounded-md bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 font-semibold">
            Refresh
          </button>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700">
              <tr class="text-left text-slate-500 dark:text-slate-400">
                <th class="py-4 px-6 font-black uppercase tracking-widest text-xs">Paid</th>
                <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">Plate</th>
                <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">Slot</th>
                <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">OR No</th>
                <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">Amount</th>
                <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">Treasury</th>
                <th class="py-4 px-4 font-black uppercase tracking-widest text-xs text-right">Action</th>
              </tr>
            </thead>
            <tbody id="paymentsBody" class="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-800">
              <tr><td colspan="7" class="py-10 text-center text-slate-500 font-medium italic">Loading...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
  (function(){
    const rootUrl = <?php echo json_encode($rootUrl); ?>;
    const terminalId = <?php echo (int)$terminalId; ?>;
    const activeTab = <?php echo json_encode($tab); ?>;
    const canSlots = <?php echo json_encode($canSlots); ?>;
    const canPay = <?php echo json_encode($canPay); ?>;

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

    async function loadSlots() {
      const body = document.getElementById('slotsBody');
      if (!body) return;
      const res = await fetch(rootUrl + '/admin/api/module5/slots_list.php?terminal_id=' + encodeURIComponent(String(terminalId)));
      const data = await res.json();
      if (!data || !data.ok) throw new Error('load_failed');
      const rows = (data.data || []);
      if (!rows.length) {
        body.innerHTML = '<tr><td colspan="3" class="py-10 text-center text-slate-500 font-medium italic">No slots yet.</td></tr>';
        return;
      }
      body.innerHTML = rows.map(r => {
        const badge = r.status === 'Occupied' ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700';
        return `
          <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
            <td class="py-4 px-6 font-black text-slate-900 dark:text-white">${(r.slot_no || '')}</td>
            <td class="py-4 px-4"><span class="px-2.5 py-1 rounded-lg text-xs font-bold ${badge}">${r.status}</span></td>
            <td class="py-4 px-4 text-right">
              <button data-slot="${r.slot_id}" class="btnToggle px-3 py-2 rounded-md bg-slate-100 dark:bg-slate-700/50 text-slate-700 dark:text-slate-200 hover:bg-blue-50 dark:hover:bg-blue-900/20">Toggle</button>
            </td>
          </tr>
        `;
      }).join('');

      Array.from(document.querySelectorAll('.btnToggle')).forEach(btn => {
        btn.addEventListener('click', async () => {
          const slotId = btn.getAttribute('data-slot');
          const fd = new FormData();
          fd.append('slot_id', slotId);
          try {
            const res2 = await fetch(rootUrl + '/admin/api/module5/slot_toggle.php', { method: 'POST', body: fd });
            const d2 = await res2.json();
            if (!d2 || !d2.ok) throw new Error((d2 && d2.error) ? d2.error : 'toggle_failed');
            showToast('Updated.');
            await loadSlots();
          } catch (e) {
            showToast(e.message || 'Failed', 'error');
          }
        });
      });
    }

    async function loadPaySlots() {
      const slotSelect = document.getElementById('slotSelect');
      if (!slotSelect) return;
      slotSelect.innerHTML = '<option value="">Loading...</option>';
      const res = await fetch(rootUrl + '/admin/api/module5/slots_list.php?terminal_id=' + encodeURIComponent(String(terminalId)));
      const data = await res.json();
      if (!data || !data.ok) throw new Error('load_failed');
      const slots = (data.data || []).filter(s => (s.status || '') === 'Free');
      if (!slots.length) {
        slotSelect.innerHTML = '<option value="">No free slots</option>';
        return;
      }
      slotSelect.innerHTML = '<option value="">Select slot</option>' + slots.map(s => `<option value="${s.slot_id}">${s.slot_no}</option>`).join('');
    }

    function fmtDate(v) {
      if (!v) return '-';
      const d = new Date(v);
      if (isNaN(d.getTime())) return String(v);
      return d.toLocaleString();
    }

    async function loadPayments() {
      const body = document.getElementById('paymentsBody');
      if (!body) return;
      body.innerHTML = '<tr><td colspan="7" class="py-10 text-center text-slate-500 font-medium italic">Loading...</td></tr>';
      const res = await fetch(rootUrl + '/admin/api/module5/list_payments.php?terminal_id=' + encodeURIComponent(String(terminalId)) + '&limit=50&offset=0');
      const data = await res.json();
      if (!data || !data.ok) throw new Error('load_failed');
      const rows = Array.isArray(data.data) ? data.data : [];
      if (!rows.length) {
        body.innerHTML = '<tr><td colspan="7" class="py-10 text-center text-slate-500 font-medium italic">No payments yet.</td></tr>';
        return;
      }
      body.innerHTML = rows.map(r => {
        const exported = Number(r.exported_to_treasury || 0) === 1;
        const badge = exported ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700';
        const bText = exported ? ('Exported ' + (r.exported_at ? fmtDate(r.exported_at) : '')) : 'Pending';
        const action = exported ? '' : `<button data-mark-exported="${r.payment_id}" class="btnMarkExported px-3 py-2 rounded-md bg-slate-100 dark:bg-slate-700/50 text-slate-700 dark:text-slate-200 hover:bg-blue-50 dark:hover:bg-blue-900/20">Mark Exported</button>`;
        return `
          <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
            <td class="py-4 px-6 text-slate-700 dark:text-slate-200 font-semibold">${fmtDate(r.paid_at)}</td>
            <td class="py-4 px-4 font-black text-slate-900 dark:text-white">${(r.plate_number || '-')}</td>
            <td class="py-4 px-4 text-slate-700 dark:text-slate-200 font-semibold">${(r.slot_no || '-')}</td>
            <td class="py-4 px-4 text-slate-700 dark:text-slate-200 font-semibold">${(r.or_no || '-')}</td>
            <td class="py-4 px-4 text-slate-700 dark:text-slate-200 font-bold">₱${Number(r.amount || 0).toFixed(2)}</td>
            <td class="py-4 px-4"><span class="px-2.5 py-1 rounded-lg text-xs font-bold ${badge}">${bText.trim() || (exported ? 'Exported' : 'Pending')}</span></td>
            <td class="py-4 px-4 text-right">${action}</td>
          </tr>
        `;
      }).join('');

      Array.from(document.querySelectorAll('.btnMarkExported')).forEach((btn) => {
        btn.addEventListener('click', async () => {
          const id = Number(btn.getAttribute('data-mark-exported') || 0);
          if (!id) return;
          btn.disabled = true;
          btn.textContent = 'Marking...';
          try {
            const res2 = await fetch(rootUrl + '/admin/api/integration/treasury/parking_mark_exported.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ ids: [id] }),
            });
            const d2 = await res2.json();
            if (!d2 || !d2.ok) throw new Error((d2 && d2.error) ? d2.error : 'mark_failed');
            showToast('Marked as exported.');
            await loadPayments();
          } catch (e) {
            showToast(e.message || 'Failed', 'error');
            btn.disabled = false;
            btn.textContent = 'Mark Exported';
          }
        });
      });
    }

    const formAdd = document.getElementById('formAddSlot');
    const btnAdd = document.getElementById('btnAdd');
    if (formAdd && btnAdd) {
      const slotInput = formAdd.querySelector('input[name="slot_no"]');
      const normalizeSlot = (value) => {
        let v = (value || '').toString().toUpperCase().trim().replace(/\s+/g, '');
        const m1 = v.match(/^([A-Z])\-?(\d{1,2})$/);
        if (m1) return m1[1] + '-' + String(m1[2]).padStart(2, '0');
        const m2 = v.match(/^([A-Z]{1,3})(\d{1,3})$/);
        if (m2 && m2[2].length <= 2) return m2[1] + '-' + String(m2[2]).padStart(2, '0');
        return v;
      };
      if (slotInput) {
        slotInput.addEventListener('input', () => { slotInput.value = normalizeSlot(slotInput.value); });
        slotInput.addEventListener('blur', () => { slotInput.value = normalizeSlot(slotInput.value); });
      }
      formAdd.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!formAdd.checkValidity()) { formAdd.reportValidity(); return; }
        btnAdd.disabled = true;
        btnAdd.textContent = 'Adding...';
        try {
          const res = await fetch(rootUrl + '/admin/api/module5/slot_create.php', { method: 'POST', body: new FormData(formAdd) });
          const d = await res.json();
          if (!d || !d.ok) throw new Error((d && d.error) ? d.error : 'add_failed');
          showToast('Slot added.');
          formAdd.reset();
          await loadSlots();
          if (activeTab === 'payments') await loadPaySlots();
        } catch (err) {
          showToast(err.message || 'Failed', 'error');
        } finally {
          btnAdd.disabled = false;
          btnAdd.textContent = 'Add Slot';
        }
      });
    }

    const formPay = document.getElementById('formPay');
    const btnPay = document.getElementById('btnPay');
    const btnGenerate = document.getElementById('btnGenerate');
    const amountInput = document.getElementById('amountInput');
    const plateInput = document.getElementById('plateInput');
    const orInput = document.getElementById('orInput');

    function normalizePlate(value) {
      let v = (value || '').toString().toUpperCase().replace(/\s+/g, '');
      v = v.replace(/[^A-Z0-9-]/g, '');
      v = v.replace(/-+/g, '-');
      if (v.indexOf('-') !== -1) return v;
      const m4 = v.match(/^([A-Z0-9]+)(\d{4})$/);
      if (m4) return m4[1] + '-' + m4[2];
      const m3 = v.match(/^([A-Z0-9]+)(\d{3})$/);
      if (m3) return m3[1] + '-' + m3[2];
      return v;
    }
    if (plateInput) plateInput.addEventListener('blur', () => { plateInput.value = normalizePlate(plateInput.value); });
    if (orInput) orInput.addEventListener('input', () => { orInput.value = (orInput.value || '').toString().toUpperCase().replace(/\s+/g, ''); });

    if (btnGenerate && amountInput) {
      btnGenerate.addEventListener('click', () => {
        if (!amountInput.value || Number(amountInput.value) <= 0) amountInput.value = '20.00';
        showToast('Fee generated.');
      });
    }

    if (formPay && btnPay) {
      formPay.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!formPay.checkValidity()) { formPay.reportValidity(); return; }
        btnPay.disabled = true;
        btnPay.textContent = 'Saving...';
        try {
          const res = await fetch(rootUrl + '/admin/api/module5/parking_payment_record.php', { method: 'POST', body: new FormData(formPay) });
          const data = await res.json();
          if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'save_failed');
          showToast('Payment saved.');
          if (plateInput) plateInput.value = '';
          if (orInput) orInput.value = '';
          await loadSlots();
          await loadPaySlots();
          await loadPayments();
        } catch (err) {
          showToast(err.message || 'Failed', 'error');
        } finally {
          btnPay.disabled = false;
          btnPay.textContent = 'Save Payment';
        }
      });
    }

    if (activeTab === 'slots' && canSlots) {
      loadSlots().catch(() => {
        const body = document.getElementById('slotsBody');
        if (body) body.innerHTML = '<tr><td colspan="3" class="py-10 text-center text-rose-600 font-semibold">Failed to load slots.</td></tr>';
      });
    }
    if (activeTab === 'payments' && canPay) {
      loadPaySlots().catch(() => {
        const slotSelect = document.getElementById('slotSelect');
        if (slotSelect) slotSelect.innerHTML = '<option value="">Failed to load</option>';
      });
      loadPayments().catch(() => {});
      if (canSlots) {
        loadSlots().catch(() => {});
      }
    }

    const btnRefreshPayments = document.getElementById('btnRefreshPayments');
    if (btnRefreshPayments) btnRefreshPayments.addEventListener('click', () => { loadPayments().catch(() => {}); });

    const btnTreasuryFeed = document.getElementById('btnTreasuryFeed');
    if (btnTreasuryFeed) {
      btnTreasuryFeed.href = rootUrl + '/admin/api/integration/treasury/parking_payments.php?unexported=1&limit=1000';
    }
  })();
</script>
