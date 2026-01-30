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
$resT = $db->query("SELECT id, name FROM terminals WHERE type <> 'Parking' ORDER BY name ASC LIMIT 500");
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
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Terminal Slots & Payments</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">Manage queue/bay slots and record terminal fees.</p>
    </div>
    <div class="flex items-center gap-3">
      <a href="?page=module5/submodule1" class="inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
        <i data-lucide="home" class="w-4 h-4"></i>
        Terminal List
      </a>
    </div>
  </div>

  <div id="toast-container" class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 z-[100] flex flex-col gap-3 pointer-events-none"></div>
  <div id="slotOccupantModal" class="fixed inset-0 z-[200] hidden">
    <div data-modal-backdrop class="absolute inset-0 bg-black/40"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
      <div class="w-full max-w-lg rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-xl overflow-hidden flex flex-col">
        <div class="p-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
          <div>
            <div class="text-sm font-black text-slate-900 dark:text-white">Slot Occupant</div>
            <div id="slotOccupantModalSub" class="text-xs text-slate-500 dark:text-slate-400 font-semibold"></div>
          </div>
          <button type="button" data-modal-close class="p-2 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-200">
            <i data-lucide="x" class="w-4 h-4"></i>
          </button>
        </div>
        <div class="p-4">
          <div id="slotOccupantModalBody" class="text-sm text-slate-700 dark:text-slate-200"></div>
        </div>
      </div>
    </div>
  </div>

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
        <input type="hidden" name="page" value="module5/submodule4">
        <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
        <div class="flex-1">
          <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Terminal</label>
          <select name="terminal_id" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
            <?php foreach ($terminals as $t): ?>
              <option value="<?php echo (int)$t['id']; ?>" <?php echo (int)$t['id'] === $terminalId ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$t['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="px-4 py-2.5 rounded-md bg-slate-900 dark:bg-slate-700 text-white font-semibold">Load</button>
      </form>

      <div class="flex items-center gap-2 border-t border-slate-200 dark:border-slate-700 pt-4">
        <a href="?page=module5/submodule4&<?php echo http_build_query(['terminal_id'=>$terminalId,'tab'=>'slots']); ?>"
          class="px-4 py-2.5 rounded-md text-sm font-semibold border <?php echo $tab === 'slots' ? 'bg-blue-700 text-white border-blue-700' : 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-600'; ?>">
          Slots
        </a>
        <a href="?page=module5/submodule4&<?php echo http_build_query(['terminal_id'=>$terminalId,'tab'=>'payments']); ?>"
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
              <input name="slot_no" required minlength="2" maxlength="10" pattern="^(?:[0-9A-Za-z]|-){2,10}$" autocapitalize="characters" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold uppercase" placeholder="e.g., P-01">
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
            <input type="hidden" name="terminal_id" value="<?php echo (int)$terminalId; ?>">
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
                <select id="plateSelect" name="plate_no" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold uppercase">
                  <option value="">Select plate</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">OR No</label>
                <input id="orInput" name="or_no" required minlength="3" maxlength="40" pattern="^(?:[0-9A-Za-z/]|-){3,40}$" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold uppercase" placeholder="e.g., OR-2026-000123">
                <input id="paidAtInput" type="hidden" name="paid_at" value="">
                <input id="exportedToTreasuryInput" type="hidden" name="exported_to_treasury" value="0">
                <input id="exportedAtInput" type="hidden" name="exported_at" value="">
              </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
              <button type="button" id="btnPayTreasury" class="px-4 py-2.5 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white font-semibold">Pay via Treasury</button>
              <button id="btnPay" class="px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold">Record Payment</button>
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

    const formAdd = document.getElementById('formAddSlot');
    const btnAdd = document.getElementById('btnAdd');
    const formPay = document.getElementById('formPay');
    const slotSelect = document.getElementById('slotSelect');
    const btnPay = document.getElementById('btnPay');
    const amountInput = document.getElementById('amountInput');
    const plateSelect = document.getElementById('plateSelect');
    const orInput = document.getElementById('orInput');
    const btnPayTreasury = document.getElementById('btnPayTreasury');
    const paidAtInput = document.getElementById('paidAtInput');
    const exportedToTreasuryInput = document.getElementById('exportedToTreasuryInput');
    const exportedAtInput = document.getElementById('exportedAtInput');
    let assignedVehicles = [];

    if (orInput) orInput.addEventListener('input', () => { orInput.value = (orInput.value || '').toString().toUpperCase().replace(/\s+/g, ''); });
    if (orInput) {
      orInput.addEventListener('input', () => {
        try { orInput.dataset.manual = '1'; orInput.dataset.autofilled = '0'; } catch (_) {}
        setPaymentButtons('record');
      });
    }

    function setPaymentButtons(mode) {
      if (!btnPay || !btnPayTreasury) return;
      const m = (mode || 'treasury').toString();
      if (m === 'record') {
        btnPay.classList.remove('hidden');
        btnPayTreasury.classList.add('hidden');
        return;
      }
      btnPay.classList.add('hidden');
      btnPayTreasury.classList.remove('hidden');
    }

    function getTreasuryPendingParkingTx() {
      try { return (window.sessionStorage && sessionStorage.getItem('tmm_treasury_pending_parking_tx')) || ''; } catch (_) { return ''; }
    }
    function setTreasuryPendingParkingTx(id) {
      try { if (window.sessionStorage) sessionStorage.setItem('tmm_treasury_pending_parking_tx', (id || '').toString()); } catch (_) {}
    }
    function clearTreasuryPendingParkingTx() {
      try { if (window.sessionStorage) sessionStorage.removeItem('tmm_treasury_pending_parking_tx'); } catch (_) {}
    }

    function fetchParkingPaymentStatus(transactionId) {
      return fetch(rootUrl + '/admin/api/module5/parking_payment_status.php?transaction_id=' + encodeURIComponent(String(transactionId)))
        .then(r => r.json());
    }

    function applyTreasuryStatusToPaymentForm(t) {
      if (!t) return;
      const receipt = (t.receipt_ref || '').toString();
      if (orInput && receipt) {
        const wasManual = orInput.dataset && orInput.dataset.manual === '1';
        if (!wasManual || orInput.value === '' || orInput.dataset.autofilled === '1') {
          orInput.value = receipt;
          try { orInput.dataset.autofilled = '1'; } catch (_) {}
        }
      }
      if (amountInput && (!amountInput.value || Number(amountInput.value) <= 0) && t.amount) {
        amountInput.value = String(t.amount);
      }
      if (plateSelect && (!plateSelect.value || plateSelect.value.trim() === '') && t.vehicle_plate) {
        const plate = String(t.vehicle_plate);
        const has = Array.from(plateSelect.options).some(o => (o.value || '') === plate);
        if (!has) {
          const opt = document.createElement('option');
          opt.value = plate;
          opt.textContent = plate;
          plateSelect.appendChild(opt);
        }
        plateSelect.value = plate;
      }
      if (paidAtInput && t.paid_at) paidAtInput.value = String(t.paid_at);
      if (exportedToTreasuryInput) exportedToTreasuryInput.value = receipt ? '1' : '0';
      if (exportedAtInput) exportedAtInput.value = t.paid_at ? String(t.paid_at) : '';
    }

    const activeTreasuryPolls = new Set();
    async function pollTreasuryReceipt(transactionId) {
      const tx = (transactionId || '').toString().trim();
      if (!tx) return;
      if (activeTreasuryPolls.has(tx)) return;
      activeTreasuryPolls.add(tx);
      const maxTries = 15;
      try {
        for (let i = 0; i < maxTries; i++) {
          try {
            const d = await fetchParkingPaymentStatus(tx);
            if (d && d.ok && d.transaction) {
              const t = d.transaction;
              const receipt = (t.receipt_ref || '').toString();
              if (receipt) {
                applyTreasuryStatusToPaymentForm(t);
                setPaymentButtons('record');
                clearTreasuryPendingParkingTx();
                showToast('Treasury receipt received: ' + receipt);
                return;
              }
            }
          } catch (_) {}
          await new Promise((r) => setTimeout(r, 1500));
        }
      } finally {
        activeTreasuryPolls.delete(tx);
      }
    }

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

    async function loadSlotsTable() {
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
        const isOccupied = (r.status || '') === 'Occupied';
        const actions = isOccupied
          ? `
              <button data-view-slot="${r.slot_id}" class="btnViewSlot px-3 py-2 rounded-md bg-slate-100 dark:bg-slate-700/50 text-slate-700 dark:text-slate-200 hover:bg-blue-50 dark:hover:bg-blue-900/20">View</button>
              ${canSlots ? `<button data-release-slot="${r.slot_id}" class="btnReleaseSlot px-3 py-2 rounded-md bg-rose-600 hover:bg-rose-700 text-white">Release</button>` : ''}
            `
          : '';
        return `
          <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
            <td class="py-4 px-6 font-black text-slate-900 dark:text-white">${(r.slot_no || '')}</td>
            <td class="py-4 px-4"><span class="px-2.5 py-1 rounded-lg text-xs font-bold ${badge}">${r.status}</span></td>
            <td class="py-4 px-4 text-right flex items-center justify-end gap-2">${actions}</td>
          </tr>
        `;
      }).join('');

      Array.from(document.querySelectorAll('.btnReleaseSlot')).forEach(btn => {
        btn.addEventListener('click', async () => {
          const slotId = btn.getAttribute('data-release-slot');
          const fd = new FormData();
          fd.append('slot_id', slotId);
          try {
            const res2 = await fetch(rootUrl + '/admin/api/module5/slot_toggle.php', { method: 'POST', body: fd });
            const d2 = await res2.json();
            if (!d2 || !d2.ok) throw new Error((d2 && d2.error) ? d2.error : 'toggle_failed');
            showToast('Updated.');
            await loadSlotsTable();
            if (activeTab === 'payments') await loadPaySlots();
          } catch (e) {
            showToast(e.message || 'Failed', 'error');
          }
        });
      });

      Array.from(document.querySelectorAll('.btnViewSlot')).forEach(btn => {
        btn.addEventListener('click', async () => {
          const slotId = btn.getAttribute('data-view-slot');
          if (!slotId) return;
          try {
            await openSlotOccupantModal(Number(slotId));
          } catch (e) {
            showToast(e.message || 'Failed', 'error');
          }
        });
      });
    }

    const slotOccModal = document.getElementById('slotOccupantModal');
    const slotOccModalBody = document.getElementById('slotOccupantModalBody');
    const slotOccModalSub = document.getElementById('slotOccupantModalSub');
    function openOccModal() { if (slotOccModal) slotOccModal.classList.remove('hidden'); }
    function closeOccModal() { if (slotOccModal) slotOccModal.classList.add('hidden'); }
    if (slotOccModal) {
      const closeBtn = slotOccModal.querySelector('[data-modal-close]');
      const backdrop = slotOccModal.querySelector('[data-modal-backdrop]');
      if (closeBtn) closeBtn.addEventListener('click', closeOccModal);
      if (backdrop) backdrop.addEventListener('click', closeOccModal);
    }

    async function openSlotOccupantModal(slotId) {
      if (!slotId) return;
      if (slotOccModalSub) slotOccModalSub.textContent = 'Loading...';
      if (slotOccModalBody) slotOccModalBody.textContent = '';
      openOccModal();
      const res = await fetch(rootUrl + '/admin/api/module5/slot_occupant.php?slot_id=' + encodeURIComponent(String(slotId)));
      const data = await res.json();
      if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'load_failed');
      const slot = data.slot || {};
      const occ = data.occupant || null;
      if (slotOccModalSub) slotOccModalSub.textContent = 'Slot ' + (slot.slot_no || '') + ' • ' + (slot.status || '');
      if (!slotOccModalBody) return;
      if (!occ) {
        slotOccModalBody.innerHTML = '<div class="text-slate-500 dark:text-slate-400 font-semibold">No payment found for this slot.</div>';
        return;
      }
      slotOccModalBody.innerHTML = `
        <div class="grid grid-cols-1 gap-2">
          <div class="flex items-center justify-between gap-3"><div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Plate</div><div class="font-black">${(occ.plate_number || '-')}</div></div>
          <div class="flex items-center justify-between gap-3"><div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Operator</div><div class="font-semibold text-right">${(occ.operator_name || '-')}</div></div>
          <div class="flex items-center justify-between gap-3"><div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Type</div><div class="font-semibold">${(occ.vehicle_type || '-')}</div></div>
          <div class="flex items-center justify-between gap-3"><div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Paid</div><div class="font-semibold">${fmtDate(occ.paid_at)}</div></div>
          <div class="flex items-center justify-between gap-3"><div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">OR</div><div class="font-semibold">${(occ.or_no || '-')}</div></div>
          <div class="flex items-center justify-between gap-3"><div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Amount</div><div class="font-black">₱${Number(occ.amount || 0).toFixed(2)}</div></div>
        </div>
      `;
      if (window.lucide) window.lucide.createIcons();
    }

    async function loadPaySlots() {
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
      if (!slotSelect.value && slotSelect.options.length > 1) {
        slotSelect.selectedIndex = 1;
      }
    }

    function ensureSlotSelected() {
      if (!slotSelect) return 0;
      let v = Number(slotSelect.value || 0);
      if (v > 0) return v;
      const opt = Array.from(slotSelect.options).find(o => (o.value || '').toString().trim() !== '');
      if (opt) {
        slotSelect.value = opt.value;
        v = Number(slotSelect.value || 0);
      }
      return v > 0 ? v : 0;
    }

    async function loadAssignedVehicles() {
      if (!plateSelect || !terminalId) return;
      plateSelect.innerHTML = '<option value=\"\">Select plate</option>';
      assignedVehicles = [];
      try {
        const res = await fetch(rootUrl + '/admin/api/module5/terminal_assignments.php?terminal_id=' + encodeURIComponent(String(terminalId)));
        const data = await res.json();
        if (!data || !data.ok) return;
        const rows = Array.isArray(data.data) ? data.data : [];
        assignedVehicles = rows
          .map(r => ({
            plate: (r.plate_number || '').toString(),
            label: [
              (r.plate_number || '').toString(),
              (r.vehicle_type || '').toString(),
              (r.operator_name || '').toString(),
            ].filter(Boolean).join(' • ')
          }))
          .filter(v => v.plate !== '');
        if (!assignedVehicles.length) {
          plateSelect.innerHTML = '<option value=\"\">No assigned vehicles</option>';
          return;
        }
        plateSelect.innerHTML = '<option value=\"\">Select plate</option>' + assignedVehicles
          .map(v => `<option value="${v.plate}">${v.label}</option>`)
          .join('');
      } catch (_) {}
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

    if (formPay && btnPay) {
      formPay.addEventListener('submit', async (e) => {
        e.preventDefault();
        ensureSlotSelected();
        if (!formPay.checkValidity()) { formPay.reportValidity(); return; }
        btnPay.disabled = true;
        btnPay.textContent = 'Saving...';
        try {
          const res = await fetch(rootUrl + '/admin/api/module5/parking_payment_record.php', { method: 'POST', body: new FormData(formPay) });
          const data = await res.json();
          if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'save_failed');
          showToast('Payment saved.');
          if (plateSelect) plateSelect.value = '';
          if (orInput) orInput.value = '';
          if (paidAtInput) paidAtInput.value = '';
          if (exportedToTreasuryInput) exportedToTreasuryInput.value = '0';
          if (exportedAtInput) exportedAtInput.value = '';
          try { if (orInput) { orInput.dataset.manual = '0'; orInput.dataset.autofilled = '0'; } } catch (_) {}
          await loadSlotsTable();
          await loadPaySlots();
          await loadPayments();
          setPaymentButtons('treasury');
        } catch (err) {
          showToast(err.message || 'Failed', 'error');
        } finally {
          btnPay.disabled = false;
          btnPay.textContent = 'Record Payment';
        }
      });
    }

    if (btnPayTreasury) {
      btnPayTreasury.addEventListener('click', async () => {
        const slotId = ensureSlotSelected();
        const plate = plateSelect ? (plateSelect.value || '').trim() : '';
        const amt = amountInput ? Number(amountInput.value || 0) : 0;
        if (!slotId) { showToast('No free slots available.', 'error'); return; }
        if (!plate) { showToast('Enter plate number first.', 'error'); return; }
        if (!amt || amt <= 0) { showToast('Enter a valid amount first.', 'error'); return; }

        btnPayTreasury.disabled = true;
        const originalText = btnPayTreasury.textContent;
        btnPayTreasury.textContent = 'Opening...';
        try {
          const fd = new FormData();
          fd.append('terminal_id', String(terminalId));
          fd.append('amount', String(amt));
          fd.append('vehicle_plate', plate);
          fd.append('charge_type', 'Terminal Fee');
          fd.append('payment_method', 'GCash');
          const res = await fetch(rootUrl + '/admin/api/module5/parking_create_pending.php', { method: 'POST', body: fd });
          const d = await res.json();
          if (!d || !d.ok || !d.transaction_id) throw new Error((d && d.error) ? d.error : 'create_pending_failed');
          const txId = String(d.transaction_id);
          const url = (window.TMM_ADMIN_BASE_URL || '') + `/treasury/pay.php?kind=parking&transaction_id=${encodeURIComponent(txId)}`;
          window.open(url, '_blank', 'noopener');
          setTreasuryPendingParkingTx(txId);
          setPaymentButtons('record');
          pollTreasuryReceipt(txId);
          showToast('Opening Treasury payment...');
        } catch (e) {
          showToast(e.message || 'Failed', 'error');
        } finally {
          btnPayTreasury.disabled = false;
          btnPayTreasury.textContent = originalText;
        }
      });
    }

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
          await loadSlotsTable();
          if (activeTab === 'payments') await loadPaySlots();
        } catch (err) {
          showToast(err.message || 'Failed', 'error');
        } finally {
          btnAdd.disabled = false;
          btnAdd.textContent = 'Add Slot';
        }
      });
    }

    if (activeTab === 'slots' && canSlots) {
      loadSlotsTable().catch(() => {
        const body = document.getElementById('slotsBody');
        if (body) body.innerHTML = '<tr><td colspan="3" class="py-10 text-center text-rose-600 font-semibold">Failed to load slots.</td></tr>';
      });
    }
    if (activeTab === 'payments' && canPay) {
      loadPaySlots().catch(() => {
        if (slotSelect) slotSelect.innerHTML = '<option value="">Failed to load</option>';
      });
      loadAssignedVehicles().catch(() => {});
      loadPayments().catch(() => {});
      if (canSlots) loadSlotsTable().catch(() => {});
      setPaymentButtons((orInput && (orInput.value || '').trim() !== '') ? 'record' : 'treasury');
    }

    const btnRefreshPayments = document.getElementById('btnRefreshPayments');
    if (btnRefreshPayments) btnRefreshPayments.addEventListener('click', () => { loadPayments().catch(() => {}); });

    const pendingTx = getTreasuryPendingParkingTx();
    if (pendingTx) pollTreasuryReceipt(pendingTx);
  })();
</script>
