<div class="mx-auto max-w-7xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-8">
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between border-b border-slate-200 dark:border-slate-700 pb-6">
    <div>
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Validation & Settlement (STS-Aligned)</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Cross-validate ticket data, process payments, and monitor repeat offenders following STS standards.</p>
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
    $recentValidated = [];
    $resVal = $db->query("SELECT ticket_number, vehicle_plate, status, date_issued FROM tickets WHERE status='Validated' ORDER BY date_issued DESC LIMIT 8");
    if($resVal) while($r = $resVal->fetch_assoc()) $recentValidated[] = $r;

    $recentPayments = [];
    $resPay = $db->query("SELECT t.ticket_number, t.vehicle_plate, t.status, p.amount_paid, p.date_paid, p.receipt_ref FROM payment_records p JOIN tickets t ON p.ticket_id = t.ticket_id ORDER BY p.date_paid DESC LIMIT 8");
    if($resPay) while($p = $resPay->fetch_assoc()) $recentPayments[] = $p;
  ?>

  <!-- Main Action Grid -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    
    <!-- Validate Ticket Card -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden flex flex-col">
      <div class="p-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30 flex items-center gap-3">
        <div class="p-1.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
          <i data-lucide="search-check" class="w-5 h-5"></i>
        </div>
        <h2 class="text-base font-bold text-slate-900 dark:text-white">Validate Ticket</h2>
      </div>
      
      <div class="p-6 flex-1">
        <form id="ticket-validate-form" class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="relative">
              <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Ticket Number</label>
              <input id="val-ticket-number" name="ticket_number" value="<?php echo htmlspecialchars($prefillTicket); ?>" class="w-full px-4 py-2.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all uppercase text-sm font-semibold text-slate-900 dark:text-white" placeholder="TCK-2026-XXXX">
              <div id="val-ticket-suggestions" class="absolute z-10 mt-1 w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-md shadow-xl max-h-48 overflow-y-auto hidden"></div>
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Vehicle Plate</label>
              <input id="val-vehicle-plate" name="vehicle_plate" value="<?php echo htmlspecialchars($prefillPlate); ?>" class="w-full px-4 py-2.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all uppercase text-sm font-semibold text-slate-900 dark:text-white" placeholder="ABC-1234">
            </div>
          </div>
          
          <button type="submit" id="btnValidate" class="w-full py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold shadow-sm transition-all active:scale-[0.98] flex items-center justify-center gap-2 text-sm">
            <i data-lucide="scan-line" class="w-4 h-4"></i>
            <span>Validate Record</span>
          </button>
          
          <div id="ticket-validate-result" class="hidden p-3 rounded-md bg-slate-50 dark:bg-slate-900/30 border border-slate-200 dark:border-slate-700 text-xs text-center"></div>
        </form>

        <div class="mt-6 pt-6 border-t border-slate-200 dark:border-slate-700">
          <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200 mb-3">Recent Validations</h3>
          <div class="space-y-2 max-h-[200px] overflow-y-auto">
            <?php if(empty($recentValidated)): ?>
              <div class="text-xs text-slate-400 text-center py-4">No recent validations.</div>
            <?php else: ?>
              <?php foreach($recentValidated as $v): ?>
                <div class="flex items-center justify-between p-3 rounded-md bg-slate-50 dark:bg-slate-900/30 border border-slate-200 dark:border-slate-700 cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" onclick="document.getElementById('val-ticket-number').value='<?php echo $v['ticket_number']; ?>'; document.getElementById('val-vehicle-plate').value='<?php echo $v['vehicle_plate']; ?>';">
                  <div>
                    <div class="font-semibold text-slate-900 dark:text-white text-sm"><?php echo htmlspecialchars($v['ticket_number']); ?></div>
                    <div class="text-xs text-slate-500 dark:text-slate-400"><?php echo htmlspecialchars($v['vehicle_plate']); ?></div>
                  </div>
                  <span class="text-[10px] font-bold px-2 py-1 rounded bg-blue-100 text-blue-700">Validated</span>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
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
        <form id="ticket-payment-form" class="space-y-4">
          <div class="relative">
            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Ticket Number</label>
            <input id="pay-ticket-number" name="ticket_number" value="<?php echo htmlspecialchars($prefillTicket); ?>" required class="w-full px-4 py-2.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all uppercase text-sm font-semibold text-slate-900 dark:text-white" placeholder="Search Ticket...">
            <div id="pay-ticket-suggestions" class="absolute z-10 mt-1 w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-md shadow-xl max-h-48 overflow-y-auto hidden"></div>
            <div id="pay-ticket-context" class="mt-1 text-xs text-emerald-600 font-medium h-4"></div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Amount (₱)</label>
              <input id="pay-amount" name="amount_paid" type="number" step="0.01" min="0" required class="w-full px-4 py-2.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all text-sm font-semibold text-slate-900 dark:text-white" placeholder="0.00">
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Receipt Ref</label>
              <input id="pay-receipt" name="receipt_ref" class="w-full px-4 py-2.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all text-sm font-semibold text-slate-900 dark:text-white" placeholder="OR Number">
            </div>
          </div>

          <label class="flex items-center gap-2 p-3 rounded-xl bg-slate-50 border border-slate-200 cursor-pointer hover:bg-slate-100 transition-colors">
            <input type="checkbox" name="verified_by_treasury" value="1" checked class="w-4 h-4 text-emerald-600 rounded focus:ring-emerald-500 border-gray-300">
            <span class="text-sm text-slate-600 font-medium">Verified by Treasury</span>
          </label>
          
          <button type="submit" id="btnPay" class="w-full py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold shadow-sm transition-all active:scale-[0.98] flex items-center justify-center gap-2 text-sm">
            <i data-lucide="check" class="w-4 h-4"></i>
            <span>Record Payment</span>
          </button>
        </form>

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
                    <div class="text-[10px] text-slate-500">OR: <?php echo htmlspecialchars($p['receipt_ref'] ?: 'N/A'); ?></div>
                  </div>
                  <div class="text-right">
                    <div class="font-bold text-emerald-600 text-sm">₱<?php echo number_format($p['amount_paid'], 2); ?></div>
                    <div class="text-[10px] text-slate-400"><?php echo date('M d', strtotime($p['date_paid'])); ?></div>
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

  // --- Auto-suggestion logic helper ---
  function setupSuggestions(inputId, suggestId, onSelect) {
    const input = document.getElementById(inputId);
    const box = document.getElementById(suggestId);
    let timer = null;
    
    if(!input || !box) return;

    input.addEventListener('input', () => {
        const q = input.value.trim();
        if(timer) clearTimeout(timer);
        if(q.length < 2) { box.classList.add('hidden'); return; }

        timer = setTimeout(() => {
            fetch('api/tickets/list.php?q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(data => {
                    if(data && data.items && data.items.length > 0) {
                        box.innerHTML = '';
                        data.items.slice(0, 5).forEach(item => {
                            const div = document.createElement('div');
                            div.className = 'px-4 py-3 hover:bg-slate-50 cursor-pointer border-b border-slate-50 last:border-0';
                            const displayTicket = item.sts_ticket_no ? `${item.ticket_number} <span class="text-[10px] bg-blue-100 text-blue-700 px-1 rounded ml-1 font-mono">STS: ${item.sts_ticket_no}</span>` : item.ticket_number;
                            div.innerHTML = `
                                <div class="font-bold text-slate-800 text-sm flex items-center flex-wrap gap-1">${displayTicket}</div>
                                <div class="text-xs text-slate-500">${item.vehicle_plate || 'No Plate'} • ${item.status}</div>
                            `;
                            div.addEventListener('click', () => {
                                input.value = item.ticket_number;
                                box.classList.add('hidden');
                                if(onSelect) onSelect(item);
                            });
                            box.appendChild(div);
                        });
                        box.classList.remove('hidden');
                    } else {
                        box.classList.add('hidden');
                    }
                });
        }, 300);
    });
  }

  // Validate Form
  setupSuggestions('val-ticket-number', 'val-ticket-suggestions', (item) => {
    document.getElementById('val-vehicle-plate').value = item.vehicle_plate || '';
  });

  const valForm = document.getElementById('ticket-validate-form');
  if(valForm) {
    valForm.addEventListener('submit', (e) => {
        e.preventDefault();
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
  setupSuggestions('pay-ticket-number', 'pay-ticket-suggestions', (item) => {
    const ctx = document.getElementById('pay-ticket-context');
    if(ctx) ctx.textContent = `Plate: ${item.vehicle_plate} • Fine: ₱${item.fine_amount || '0.00'}`;
    const amt = document.getElementById('pay-amount');
    if(amt && !amt.value && item.fine_amount) amt.value = item.fine_amount;
  });

  const payForm = document.getElementById('ticket-payment-form');
  if(payForm) {
    payForm.addEventListener('submit', (e) => {
        e.preventDefault();
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
})();
</script>
