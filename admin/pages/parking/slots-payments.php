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
      <a href="?page=parking/list" class="inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
        <i data-lucide="home" class="w-4 h-4"></i>
        Parking List
      </a>
      <a href="?page=module5/submodule4" class="inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>
        Terminals
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
        <input type="hidden" name="page" value="parking/slots-payments">
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

      <div class="flex flex-col sm:flex-row sm:items-center gap-2 border-t border-slate-200 dark:border-slate-700 pt-4">
        <a href="?page=parking/slots-payments&<?php echo http_build_query(['terminal_id'=>$terminalId,'tab'=>'slots']); ?>"
          class="w-full sm:w-auto px-4 py-2.5 rounded-md text-sm font-semibold border text-center <?php echo $tab === 'slots' ? 'bg-blue-700 text-white border-blue-700' : 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-600'; ?>">
          Slots
        </a>
        <a href="?page=parking/slots-payments&<?php echo http_build_query(['terminal_id'=>$terminalId,'tab'=>'payments']); ?>"
          class="w-full sm:w-auto px-4 py-2.5 rounded-md text-sm font-semibold border text-center <?php echo $tab === 'payments' ? 'bg-blue-700 text-white border-blue-700' : 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-600'; ?>">
          Payments
        </a>
        <div class="flex-1 sm:text-right text-xs text-slate-500 dark:text-slate-400 font-semibold">
          <?php echo htmlspecialchars($terminalName !== '' ? $terminalName : ''); ?>
        </div>
      </div>
    </div>
  </div>

  <div id="panelSlots" class="<?php echo $tab === 'slots' ? '' : 'hidden'; ?>">
    <?php if (!$canSlots): ?>
      <div class="bg-white dark:bg-slate-800 p-6 rounded-lg border border-slate-200 dark:border-slate-700 text-sm text-slate-600 dark:text-slate-300 font-semibold">
        You do not have permission to manage parking slots.
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
        <div class="p-6">
          <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-3">Slots</div>
          <div id="slotsWrap" class="text-sm text-slate-500 dark:text-slate-400">Loading...</div>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <div id="panelPayments" class="<?php echo $tab === 'payments' ? '' : 'hidden'; ?>">
    <?php if (!$canPay): ?>
      <div class="bg-white dark:bg-slate-800 p-6 rounded-lg border border-slate-200 dark:border-slate-700 text-sm text-slate-600 dark:text-slate-300 font-semibold">
        You do not have permission to record parking payments.
      </div>
    <?php else: ?>
      <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="p-6">
          <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-3">Payments</div>
          <div id="paymentsWrap" class="text-sm text-slate-500 dark:text-slate-400">Loading...</div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
  (function () {
    const rootUrl = <?php echo json_encode($rootUrl); ?>;
    const terminalId = <?php echo json_encode((int)$terminalId); ?>;
    const tab = <?php echo json_encode($tab); ?>;
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
      const wrap = document.getElementById('slotsWrap');
      if (!wrap) return;
      wrap.textContent = 'Loading...';
      try {
        const res = await fetch(rootUrl + '/admin/api/module5/slots_list.php?terminal_id=' + encodeURIComponent(String(terminalId)));
        const data = await res.json().catch(() => null);
        if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'load_failed');
        const rows = Array.isArray(data.data) ? data.data : [];
        if (!rows.length) { wrap.innerHTML = '<div class="italic">No slots yet.</div>'; return; }
        wrap.innerHTML = rows.map((s) => {
          const st = (s.status || '').toString();
          const badge = st === 'Free' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700';
          const isOccupied = st === 'Occupied';
          const actions = isOccupied
            ? `
                <button type="button" data-view="${String(s.slot_id || '')}"
                  class="px-3 py-2 rounded-lg text-xs font-black bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition-colors">
                  View
                </button>
                ${canSlots ? `<button type="button" data-release="${String(s.slot_id || '')}"
                  class="px-3 py-2 rounded-lg text-xs font-black bg-rose-600 hover:bg-rose-700 text-white">
                  Release
                </button>` : ''}
              `
            : '';
          return `
            <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700 mb-2">
              <div class="font-black text-slate-800 dark:text-white">${(s.slot_no || '').toString()}</div>
              <div class="flex flex-wrap items-center justify-end gap-2">
                <span class="px-2.5 py-1 rounded-lg text-xs font-black ${badge}">${st}</span>
                ${actions}
              </div>
            </div>
          `;
        }).join('');

        wrap.querySelectorAll('[data-release]').forEach((btn) => {
          btn.addEventListener('click', async () => {
            const slotId = btn.getAttribute('data-release') || '';
            if (!slotId) return;
            const fd = new FormData();
            fd.append('slot_id', slotId);
            try {
              const rr = await fetch(rootUrl + '/admin/api/module5/slot_toggle.php', { method: 'POST', body: fd });
              const dd = await rr.json().catch(() => null);
              if (!dd || !dd.ok) throw new Error((dd && dd.error) ? dd.error : 'toggle_failed');
              showToast('Slot released.');
              loadSlots();
            } catch (e) {
              showToast((e && e.message) ? e.message : 'Failed', 'error');
            }
          });
        });

        wrap.querySelectorAll('[data-view]').forEach((btn) => {
          btn.addEventListener('click', async () => {
            const slotId = btn.getAttribute('data-view') || '';
            if (!slotId) return;
            try {
              await openSlotOccupantModal(Number(slotId));
            } catch (e) {
              showToast((e && e.message) ? e.message : 'Failed', 'error');
            }
          });
        });
      } catch (e) {
        wrap.innerHTML = '<div class="text-rose-600 font-semibold">Failed to load slots.</div>';
      }
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

    function fmtDate(v) {
      if (!v) return '-';
      const d = new Date(v);
      if (isNaN(d.getTime())) return String(v);
      return d.toLocaleString();
    }

    async function openSlotOccupantModal(slotId) {
      if (!slotId) return;
      if (slotOccModalSub) slotOccModalSub.textContent = 'Loading...';
      if (slotOccModalBody) slotOccModalBody.textContent = '';
      openOccModal();
      const res = await fetch(rootUrl + '/admin/api/module5/slot_occupant.php?slot_id=' + encodeURIComponent(String(slotId)));
      const data = await res.json().catch(() => null);
      if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'load_failed');
      const slot = data.slot || {};
      const occ = data.occupant || null;
      if (slotOccModalSub) slotOccModalSub.textContent = 'Slot ' + (slot.slot_no || '') + ' • ' + (slot.status || '');
      if (!slotOccModalBody) return;
      if (!occ) {
        slotOccModalBody.innerHTML = '<div class="text-slate-500 dark:text-slate-400 font-semibold">No payment found for this slot.</div>';
        if (window.lucide) window.lucide.createIcons();
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

    async function loadPayments() {
      const wrap = document.getElementById('paymentsWrap');
      if (!wrap) return;
      wrap.textContent = 'Loading...';
      try {
        const res = await fetch(rootUrl + '/admin/api/module5/list_payments.php?terminal_id=' + encodeURIComponent(String(terminalId)));
        const data = await res.json().catch(() => null);
        if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'load_failed');
        const rows = Array.isArray(data.data) ? data.data : [];
        if (!rows.length) { wrap.innerHTML = '<div class="italic">No payments yet.</div>'; return; }
        wrap.innerHTML = rows.map((p) => {
          const when = p.paid_at ? new Date(p.paid_at).toLocaleString() : '';
          const amt = p.amount ? String(p.amount) : '';
          const plate = (p.plate_number || '').toString();
          const slot = (p.slot_no || '').toString();
          return `
            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700 mb-2">
              <div class="flex items-center justify-between gap-3">
                <div class="font-black text-slate-800 dark:text-white">${plate}</div>
                <div class="font-black text-emerald-700">${amt}</div>
              </div>
              <div class="mt-1 text-xs text-slate-500 font-semibold">${when}${slot ? (' • Slot ' + slot) : ''}</div>
            </div>
          `;
        }).join('');
      } catch (e) {
        wrap.innerHTML = '<div class="text-rose-600 font-semibold">Failed to load payments.</div>';
      }
    }

    const formAdd = document.getElementById('formAddSlot');
    const btnAdd = document.getElementById('btnAdd');
    if (formAdd && btnAdd && canSlots) {
      formAdd.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!formAdd.checkValidity()) { formAdd.reportValidity(); return; }
        btnAdd.disabled = true;
        const original = btnAdd.textContent;
        btnAdd.textContent = 'Adding...';
        try {
          const fd = new FormData(formAdd);
          const res = await fetch(rootUrl + '/admin/api/module5/slot_create.php', { method: 'POST', body: fd });
          const data = await res.json().catch(() => null);
          if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'create_failed');
          showToast('Slot added.');
          formAdd.reset();
          loadSlots();
        } catch (e) {
          showToast((e && e.message) ? e.message : 'Failed', 'error');
        } finally {
          btnAdd.disabled = false;
          btnAdd.textContent = original;
        }
      });
    }

    if (tab === 'slots' && canSlots) loadSlots();
    if (tab === 'payments' && canPay) loadPayments();
    if (window.lucide && window.lucide.createIcons) window.lucide.createIcons();
  })();
</script>

