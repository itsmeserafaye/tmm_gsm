<?php
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module3.settle','module3.read']);
?>
<div class="mx-auto max-w-7xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-8">
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between border-b border-slate-200 dark:border-slate-700 pb-6">
    <div>
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Payment</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Search by ticket or plate, input OR number, and mark tickets as paid (Settled).</p>
    </div>
    <div class="text-xs font-semibold text-slate-500 dark:text-slate-300 bg-slate-50 dark:bg-slate-700/30 px-3 py-2 rounded-md border border-slate-200 dark:border-slate-700">
      Data window: Last 30 days
    </div>
  </div>

  <!-- Toast Container -->
  <div id="toast-container" class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 z-[100] flex flex-col gap-2 pointer-events-none"></div>

  <?php
    require_once __DIR__ . '/../../includes/db.php';
    $db = db();
    
    $prefillTicket = trim((string)($_GET['ticket_number'] ?? ($_GET['ticket'] ?? '')));
    $prefillPlate = trim((string)($_GET['plate'] ?? ''));
    $start30 = date('Y-m-d H:i:s', strtotime('-30 days'));

    // Stats
    $total30 = $db->query("SELECT COUNT(*) AS c FROM tickets WHERE date_issued >= '$start30'")->fetch_assoc()['c'] ?? 0;
    $repeat = $db->query("SELECT COUNT(*) AS c FROM (SELECT vehicle_plate, COUNT(*) AS cnt FROM tickets WHERE date_issued >= '$start30' AND vehicle_plate IS NOT NULL AND vehicle_plate <> '' GROUP BY vehicle_plate HAVING cnt > 1) t")->fetch_assoc()['c'] ?? 0;
    $escalations = $db->query("SELECT COUNT(*) AS c FROM tickets WHERE date_issued >= '$start30' AND status='Escalated'")->fetch_assoc()['c'] ?? 0;

    // Recent Lists
    $recentPayments = [];
    $resPay = $db->query("SELECT t.ticket_number, t.external_ticket_number, t.vehicle_plate, t.status, p.amount_paid, p.paid_at, p.or_no FROM ticket_payments p JOIN tickets t ON p.ticket_id = t.ticket_id ORDER BY p.paid_at DESC LIMIT 8");
    if($resPay) while($p = $resPay->fetch_assoc()) $recentPayments[] = $p;
  ?>

  <!-- Main Action Grid -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    
    <!-- Ticket Lookup Card -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden flex flex-col">
      <div class="p-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30 flex items-center gap-3">
        <div class="p-1.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
          <i data-lucide="search-check" class="w-5 h-5"></i>
        </div>
        <h2 class="text-base font-bold text-slate-900 dark:text-white">Ticket Lookup</h2>
      </div>
      
      <div class="p-6 flex-1">
        <form id="ticket-validate-form" class="space-y-4" novalidate>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="relative">
              <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Ticket / STS Ticket Number</label>
              <button type="button" id="valTicketDropdownBtn"
                class="w-full flex items-center justify-between gap-3 px-4 py-2.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all text-sm font-semibold text-slate-900 dark:text-white">
                <span id="valTicketDropdownBtnText" class="truncate text-slate-500 dark:text-slate-400">Select ticket</span>
                <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400"></i>
              </button>
              <div id="valTicketDropdownPanel"
                class="hidden absolute left-0 right-0 mt-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-xl z-[120] overflow-hidden">
                <div class="p-3 border-b border-slate-200 dark:border-slate-700">
                  <input id="valTicketDropdownSearch" type="text" autocomplete="off"
                    class="w-full px-3 py-2 rounded-lg bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold placeholder:text-slate-400"
                    placeholder="Search ticket...">
                </div>
                <div id="valTicketDropdownList" class="max-h-64 overflow-auto"></div>
              </div>
              <input id="val-ticket-number" name="ticket_number" value="<?php echo htmlspecialchars($prefillTicket); ?>" type="hidden">
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Vehicle Plate</label>
              <input id="val-vehicle-plate" name="vehicle_plate" value="<?php echo htmlspecialchars($prefillPlate); ?>" maxlength="32" autocapitalize="characters" data-tmm-mask="plate_any" data-tmm-uppercase="1" class="w-full px-4 py-2.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all uppercase text-sm font-semibold text-slate-900 dark:text-white" placeholder="e.g., ABC-1234">
            </div>
          </div>
          
          <button type="submit" id="btnValidate" class="w-full py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold shadow-sm transition-all active:scale-[0.98] flex items-center justify-center gap-2 text-sm">
            <i data-lucide="scan-line" class="w-4 h-4"></i>
            <span>Load Ticket</span>
          </button>
          
          <div id="ticket-validate-result" class="hidden p-3 rounded-md bg-slate-50 dark:bg-slate-900/30 border border-slate-200 dark:border-slate-700 text-xs text-center"></div>
        </form>

        <div class="mt-6 pt-6 border-t border-slate-200 dark:border-slate-700">
          <div class="text-xs text-slate-500 dark:text-slate-400">Lookup helps auto-fill the payment form; validation is handled by the system when plate/operator is found.</div>
        </div>
      </div>
    </div>

    <!-- Payment Processing Card -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden flex flex-col">
      <div class="p-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30 flex items-center gap-3">
        <div class="p-1.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
          <i data-lucide="credit-card" class="w-5 h-5"></i>
        </div>
        <h2 class="text-base font-bold text-slate-900 dark:text-white">Payment Processing</h2>
      </div>
      
      <div class="p-6 flex-1">
        <?php if (has_permission('module3.settle')): ?>
        <form id="ticket-payment-form" class="space-y-4" novalidate>
          <div class="relative">
            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Ticket Number</label>
            <button type="button" id="payTicketDropdownBtn"
              class="w-full flex items-center justify-between gap-3 px-4 py-2.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all text-sm font-semibold text-slate-900 dark:text-white">
              <span id="payTicketDropdownBtnText" class="truncate text-slate-500 dark:text-slate-400">Select ticket</span>
              <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400"></i>
            </button>
            <div id="payTicketDropdownPanel"
              class="hidden absolute left-0 right-0 mt-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-xl z-[120] overflow-hidden">
              <div class="p-3 border-b border-slate-200 dark:border-slate-700">
                <input id="payTicketDropdownSearch" type="text" autocomplete="off"
                  class="w-full px-3 py-2 rounded-lg bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold placeholder:text-slate-400"
                  placeholder="Search ticket...">
              </div>
              <div id="payTicketDropdownList" class="max-h-64 overflow-auto"></div>
            </div>
            <input id="pay-ticket-number" name="ticket_number" value="<?php echo htmlspecialchars($prefillTicket); ?>" type="hidden">
            <div id="pay-ticket-context" class="mt-1 text-xs text-emerald-600 font-medium h-4"></div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Vehicle Plate</label>
            <input id="pay-vehicle-plate" type="text" readonly class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md outline-none transition-all uppercase text-sm font-semibold text-slate-900 dark:text-white" placeholder="Auto-filled from ticket">
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Amount (₱)</label>
              <input id="pay-amount" name="amount_paid" type="number" step="0.01" min="0.01" required class="w-full px-4 py-2.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all text-sm font-semibold text-slate-900 dark:text-white" placeholder="e.g., 500.00">
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">OR No</label>
              <input id="pay-receipt" name="or_no" required minlength="3" maxlength="40" pattern="^[0-9A-Za-z/-]{3,40}$" class="w-full px-4 py-2.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all text-sm font-semibold text-slate-900 dark:text-white" placeholder="e.g., OR-2026-000123">
            </div>
          </div>
          
          <button type="submit" id="btnPay" class="w-full py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold shadow-sm transition-all active:scale-[0.98] flex items-center justify-center gap-2 text-sm">
            <i data-lucide="check" class="w-4 h-4"></i>
            <span>Record Treasury Payment</span>
          </button>

          <button type="button" id="btnPayTreasury" class="w-full py-2.5 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white font-semibold shadow-sm transition-all active:scale-[0.98] flex items-center justify-center gap-2 text-sm">
            <i data-lucide="banknote" class="w-4 h-4"></i>
            <span>Pay via Treasury (Digital)</span>
          </button>
        </form>
        <?php else: ?>
          <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900/30 border border-slate-200 dark:border-slate-700 text-sm text-slate-600 dark:text-slate-300">
            Payments are processed through the City Treasury. Please coordinate with the Treasurer to settle tickets and issue the official receipt (OR).
          </div>
        <?php endif; ?>

        <div class="mt-6 pt-6 border-t border-slate-200 dark:border-slate-700">
          <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200 mb-3">Recent Payments</h3>
          <div class="space-y-2 max-h-[200px] overflow-y-auto">
             <?php if(empty($recentPayments)): ?>
              <div class="text-xs text-slate-400 text-center py-4">No payments recorded.</div>
            <?php else: ?>
              <?php foreach($recentPayments as $p): ?>
                <div class="flex items-center justify-between p-3 rounded-md bg-slate-50 dark:bg-slate-900/30 border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                  <div>
                    <div class="font-semibold text-slate-900 dark:text-white text-sm"><?php echo htmlspecialchars($p['ticket_number']); ?></div>
                    <div class="text-[10px] text-slate-500">
                      OR: <?php echo htmlspecialchars($p['or_no'] ?: 'N/A'); ?>
                      <?php if (!empty($p['external_ticket_number'])): ?>
                        • STS: <?php echo htmlspecialchars($p['external_ticket_number']); ?>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div class="text-right">
                    <div class="font-bold text-emerald-600 text-sm">₱<?php echo number_format($p['amount_paid'], 2); ?></div>
                    <div class="text-[10px] text-slate-400"><?php echo date('M d', strtotime($p['paid_at'])); ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Compliance Stats -->
  <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <div class="p-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30 flex items-center gap-3">
      <div class="p-1.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
        <i data-lucide="alert-triangle" class="w-5 h-5"></i>
      </div>
      <h2 class="text-base font-bold text-slate-900 dark:text-white">Compliance Snapshot</h2>
    </div>
    
    <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
      <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 flex flex-col items-center justify-center text-center">
        <div class="text-3xl font-bold text-slate-800 mb-1"><?php echo $total30; ?></div>
        <div class="text-xs font-bold text-slate-400 uppercase tracking-wide">Total Violations (30d)</div>
      </div>
      <div class="p-4 rounded-2xl bg-amber-50 border border-amber-100 flex flex-col items-center justify-center text-center">
        <div class="text-3xl font-bold text-amber-600 mb-1"><?php echo $repeat; ?></div>
        <div class="text-xs font-bold text-amber-400 uppercase tracking-wide">Repeat Offenders</div>
      </div>
      <div class="p-4 rounded-2xl bg-rose-50 border border-rose-100 flex flex-col items-center justify-center text-center">
        <div class="text-3xl font-bold text-rose-600 mb-1"><?php echo $escalations; ?></div>
        <div class="text-xs font-bold text-rose-400 uppercase tracking-wide">Escalated Cases</div>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  if (window.lucide) window.lucide.createIcons();

  function showToast(msg, type = 'success') {
    const container = document.getElementById('toast-container');
    if(!container) return;
    const toast = document.createElement('div');
    const colors = type === 'success' ? 'bg-emerald-500' : (type === 'error' ? 'bg-rose-500' : 'bg-amber-500');
    const icon = type === 'success' ? 'check-circle' : 'alert-circle';
    
    toast.className = `${colors} text-white px-4 py-3 rounded-xl shadow-lg shadow-black/5 flex items-center gap-3 transform transition-all duration-300 translate-y-10 opacity-0 min-w-[300px] backdrop-blur-md`;
    toast.innerHTML = `
      <i data-lucide="${icon}" class="w-5 h-5"></i>
      <span class="font-medium text-sm">${msg}</span>
    `;
    
    container.appendChild(toast);
    if (window.lucide) window.lucide.createIcons();
    requestAnimationFrame(() => toast.classList.remove('translate-y-10', 'opacity-0'));
    setTimeout(() => { toast.classList.add('opacity-0', 'translate-x-full'); setTimeout(() => toast.remove(), 300); }, 3000);
  }

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

  function initTicketDropdown(opts) {
    const btn = document.getElementById(opts.btnId);
    const btnText = document.getElementById(opts.btnTextId);
    const panel = document.getElementById(opts.panelId);
    const search = document.getElementById(opts.searchId);
    const list = document.getElementById(opts.listId);
    const hidden = document.getElementById(opts.hiddenInputId);
    const plateEl = opts.plateInputId ? document.getElementById(opts.plateInputId) : null;
    const onSelect = typeof opts.onSelect === 'function' ? opts.onSelect : null;
    const excludePaid = opts.excludePaid ? '1' : '0';
    let debounceId = null;

    if (!btn || !btnText || !panel || !search || !list || !hidden) return;

    function setLabel(text) {
      const t = (text || '').toString().trim();
      btnText.textContent = t || 'Select ticket';
      btnText.classList.toggle('text-slate-500', !t);
      btnText.classList.toggle('dark:text-slate-400', !t);
      btnText.classList.toggle('text-slate-900', !!t);
      btnText.classList.toggle('dark:text-white', !!t);
    }

    function open() {
      panel.classList.remove('hidden');
      try { search.focus(); } catch (e) {}
      load(true);
    }
    function close() { panel.classList.add('hidden'); }
    function isOpen() { return !panel.classList.contains('hidden'); }

    function fetchTickets(q) {
      const qq = (q || '').toString().trim();
      const limit = qq ? 100 : 200;
      return fetch('api/tickets/list.php?q=' + encodeURIComponent(qq) + '&exclude_paid=' + encodeURIComponent(excludePaid) + '&limit=' + encodeURIComponent(String(limit)))
        .then(r => r.json())
        .then(data => (data && Array.isArray(data.items)) ? data.items : [])
        .catch(() => []);
    }

    function render(items) {
      list.innerHTML = '';
      if (!items || !items.length) {
        const empty = document.createElement('div');
        empty.className = 'px-4 py-3 text-sm text-slate-500 italic';
        empty.textContent = 'No matches.';
        list.appendChild(empty);
        return;
      }
      items.slice(0, 50).forEach((item) => {
        const primary = (item && (item.external_ticket_number || item.ticket_number) || '').toString();
        if (!primary) return;
        const alt = (item.external_ticket_number && item.ticket_number) ? ('TMM: ' + item.ticket_number) : '';
        const row = document.createElement('button');
        row.type = 'button';
        row.className = 'w-full text-left px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-800/60 border-b border-slate-100 dark:border-slate-800';
        row.innerHTML = '<div class="font-bold text-slate-800 dark:text-white text-sm">' + primary + '</div>' +
          '<div class="text-xs text-slate-500">' + String(item.vehicle_plate || 'No Plate') + ' • ' + String(item.status || '') + (alt ? (' • ' + alt) : '') + '</div>';
        row.addEventListener('click', () => {
          hidden.value = primary;
          setLabel(primary);
          if (plateEl) {
            plateEl.value = normalizePlate(item.vehicle_plate || '');
            try { plateEl.setCustomValidity(''); } catch (e) {}
            try { plateEl.dispatchEvent(new Event('input', { bubbles: true })); } catch (e) {}
            try { plateEl.dispatchEvent(new Event('blur', { bubbles: true })); } catch (e) {}
          }
          close();
          if (onSelect) onSelect(item);
        });
        list.appendChild(row);
      });
      const tail = list.lastElementChild;
      if (tail) tail.classList.add('border-b-0');
    }

    function load(reset) {
      list.innerHTML = '<div class="px-4 py-3 text-sm text-slate-500 italic">Loading…</div>';
      fetchTickets(search.value || '').then(render);
    }

    btn.addEventListener('click', () => { isOpen() ? close() : open(); });
    search.addEventListener('input', () => {
      if (debounceId) clearTimeout(debounceId);
      debounceId = setTimeout(() => load(false), 180);
    });
    search.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') { e.preventDefault(); close(); }
    });
    document.addEventListener('click', (e) => {
      if (!isOpen()) return;
      const t = e.target;
      if (!t) return;
      if (panel.contains(t) || btn.contains(t)) return;
      close();
    });

    if (hidden.value) setLabel(hidden.value);
  }

  initTicketDropdown({
    btnId: 'valTicketDropdownBtn',
    btnTextId: 'valTicketDropdownBtnText',
    panelId: 'valTicketDropdownPanel',
    searchId: 'valTicketDropdownSearch',
    listId: 'valTicketDropdownList',
    hiddenInputId: 'val-ticket-number',
    plateInputId: 'val-vehicle-plate',
    excludePaid: true,
  });

  const valForm = document.getElementById('ticket-validate-form');
  if(valForm) {
    valForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const ticketVal = (document.getElementById('val-ticket-number')?.value || '').toString().trim();
        const plateVal = (document.getElementById('val-vehicle-plate')?.value || '').toString().trim();
        if (!ticketVal && !plateVal) {
          showToast('Select a ticket or enter a plate.', 'error');
          return;
        }
        const btn = document.getElementById('btnValidate');
        const resDiv = document.getElementById('ticket-validate-result');
        const originalHtml = btn.innerHTML;
        
        btn.disabled = true;
        btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Validating...';
        if(window.lucide) window.lucide.createIcons();

        const fd = new FormData(valForm);
        fetch('api/tickets/validate.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                resDiv.classList.remove('hidden');
                if(d.ok) {
                    resDiv.className = 'p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-xs text-emerald-700 font-medium text-center';
                    resDiv.textContent = 'Validation Successful! Status updated.';
                    showToast('Ticket validated successfully', 'success');
                } else {
                    resDiv.className = 'p-3 rounded-xl bg-rose-50 border border-rose-100 text-xs text-rose-700 font-medium text-center';
                    resDiv.textContent = d.error || 'Validation failed';
                }
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
                if(window.lucide) window.lucide.createIcons();
            });
    });
  }

  // Payment Form
  function setPaymentButtons(mode) {
    const btnPay = document.getElementById('btnPay');
    const btnPayTreasury = document.getElementById('btnPayTreasury');
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

  function fetchPaymentStatus(ticketNumber) {
    return fetch('api/tickets/payment_status.php?ticket_number=' + encodeURIComponent(ticketNumber))
      .then(r => r.json());
  }

  function getTreasuryPendingTicket() {
    try { return (window.sessionStorage && sessionStorage.getItem('tmm_treasury_pending_ticket')) || ''; } catch (_) { return ''; }
  }
  function setTreasuryPendingTicket(ticketNumber) {
    try { if (window.sessionStorage) sessionStorage.setItem('tmm_treasury_pending_ticket', (ticketNumber || '').toString()); } catch (_) {}
  }
  function clearTreasuryPendingTicket() {
    try { if (window.sessionStorage) sessionStorage.removeItem('tmm_treasury_pending_ticket'); } catch (_) {}
  }

  initTicketDropdown({
    btnId: 'payTicketDropdownBtn',
    btnTextId: 'payTicketDropdownBtnText',
    panelId: 'payTicketDropdownPanel',
    searchId: 'payTicketDropdownSearch',
    listId: 'payTicketDropdownList',
    hiddenInputId: 'pay-ticket-number',
    excludePaid: true,
    onSelect: (item) => {
    const ctx = document.getElementById('pay-ticket-context');
    const payPlate = document.getElementById('pay-vehicle-plate');
    if (payPlate) payPlate.value = (item.vehicle_plate || '').toString();
    if(ctx) ctx.textContent = `Plate: ${item.vehicle_plate} • Fine: ₱${item.fine_amount || '0.00'}`;
    const amt = document.getElementById('pay-amount');
    if(amt && !amt.value && item.fine_amount) amt.value = item.fine_amount;
    const receiptInput = document.getElementById('pay-receipt');

    const ticketNumber = (item.external_ticket_number || item.ticket_number || '').toString();
    if (ticketNumber) {
      fetchPaymentStatus(ticketNumber).then(d => {
        if (d && d.ok && d.ticket) {
          const t = d.ticket;
          const orNo = (t.or_no || t.receipt_ref || '').toString();
          if (receiptInput && !receiptInput.value && orNo) receiptInput.value = orNo;
          const pending = getTreasuryPendingTicket();
          if (t.is_paid || pending === ticketNumber) {
            setPaymentButtons('record');
          } else {
            setPaymentButtons('treasury');
          }
        }
      }).catch(() => {});
    } else {
      setPaymentButtons('treasury');
    }
    }
  });

  setPaymentButtons('treasury');

  async function pollTreasuryReceipt(ticketNumber) {
    const tno = (ticketNumber || '').toString().trim();
    if (!tno) return;
    const receiptInput = document.getElementById('pay-receipt');
    const maxTries = 15;
    for (let i = 0; i < maxTries; i++) {
      try {
        const d = await fetchPaymentStatus(tno);
        if (d && d.ok && d.ticket) {
          const t = d.ticket;
          const orNo = (t.or_no || t.receipt_ref || '').toString();
          if (orNo) {
            if (receiptInput) receiptInput.value = orNo;
            setPaymentButtons('record');
            clearTreasuryPendingTicket();
            return;
          }
        }
      } catch (_) {}
      await new Promise((r) => setTimeout(r, 1500));
    }
  }

  const pendingOnLoad = getTreasuryPendingTicket();
  if (pendingOnLoad) {
    pollTreasuryReceipt(pendingOnLoad);
  }

  const payForm = document.getElementById('ticket-payment-form');
  if(payForm) {
    payForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const tno = (document.getElementById('pay-ticket-number')?.value || '').toString().trim();
        if (!tno) { showToast('Select a ticket.', 'error'); return; }
        const btn = document.getElementById('btnPay');
        const originalHtml = btn.innerHTML;
        
        btn.disabled = true;
        btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Processing...';
        if(window.lucide) window.lucide.createIcons();

        const fd = new FormData(payForm);
        fetch('api/tickets/settle.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if(d.ok) {
                    showToast('Payment recorded successfully', 'success');
                    clearTreasuryPendingTicket();
                    payForm.reset();
                    document.getElementById('pay-ticket-context').textContent = '';
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showToast(d.error || 'Payment failed', 'error');
                }
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
                if(window.lucide) window.lucide.createIcons();
            });
    });
  }

  const btnPayTreasury = document.getElementById('btnPayTreasury');
  if (btnPayTreasury) {
    btnPayTreasury.addEventListener('click', () => {
      const inp = document.getElementById('pay-ticket-number');
      const ticket = inp ? (inp.value || '').trim() : '';
      if (!ticket) { showToast('Enter a ticket number first', 'error'); return; }
      const url = `treasury/pay.php?kind=ticket&transaction_id=${encodeURIComponent(ticket)}`;
      window.open(url, '_blank', 'noopener');
      showToast('Opening Treasury payment...', 'success');
      setTreasuryPendingTicket(ticket);
      setPaymentButtons('record');
    });
  }
})();
</script>
