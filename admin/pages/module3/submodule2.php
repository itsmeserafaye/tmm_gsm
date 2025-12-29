<?php require_once __DIR__ . '/../../includes/db.php'; $db = db(); ?>
<div class="mx-1 mt-1 p-4 md:p-6 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 rounded-lg">
  <h1 class="text-2xl font-bold mb-2">Validation, Payment & Compliance</h1>
  <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">Cross-validate ticket data with PUV and Franchise records, monitor payments, and aggregate repeat violations.</p>
  <div id="toast-container" class="fixed bottom-4 right-4 z-50 flex flex-col gap-2 pointer-events-none"></div>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="p-6 border rounded-lg ring-1 ring-slate-200 dark:ring-slate-700 bg-white dark:bg-slate-900 border-t-4 border-t-blue-500 shadow-sm">
      <h2 class="text-lg font-semibold mb-3 flex items-center gap-2"><i data-lucide="search-check" class="w-5 h-5 text-blue-500"></i> Validate Ticket</h2>
      <form id="validateTicketForm" class="space-y-3">
        <input name="ticket_number" value="<?php echo htmlspecialchars($_GET['ticket'] ?? ''); ?>" class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all" placeholder="Ticket #">
        <input name="vehicle_plate" class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700 uppercase focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all" placeholder="Vehicle plate">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          <button id="btnCheckPUV" type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors">Validate</button>
        </div>
      </form>
      <div id="valStatus" class="mt-3 text-sm">Status: <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700 ring-1 ring-amber-600/20">Pending</span></div>
    </div>
    <div class="p-6 border rounded-lg ring-1 ring-slate-200 dark:ring-slate-700 bg-white dark:bg-slate-900 border-t-4 border-t-emerald-500 shadow-sm">
      <h2 class="text-lg font-semibold mb-3 flex items-center gap-2"><i data-lucide="credit-card" class="w-5 h-5 text-emerald-500"></i> Payment Processing</h2>
      <form id="paymentForm" class="space-y-3">
        <input name="ticket_number" value="<?php echo htmlspecialchars($_GET['ticket'] ?? ''); ?>" class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all" placeholder="Ticket #">
        <input name="amount_paid" class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all" placeholder="Amount">
        <input name="receipt_ref" class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all" placeholder="Receipt ref">
        <button id="btnMarkPaid" type="submit" class="px-6 py-2.5 bg-emerald-500 hover:bg-emerald-600 text-white font-medium rounded-lg transition-colors">Mark Paid</button>
      </form>
      <div id="payStatus" class="mt-3 text-sm">Treasury: <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-700 ring-1 ring-slate-400/30">Awaiting</span></div>
    </div>
  </div>
  <div class="p-6 border rounded-lg ring-1 ring-slate-200 dark:ring-slate-700 bg-white dark:bg-slate-900 mt-6">
    <h2 class="text-lg font-semibold mb-3">Compliance Summary</h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <?php
        $violations30d = $db->query("SELECT COUNT(*) AS c FROM tickets WHERE date_issued >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch_assoc()['c'] ?? 0;
        $repeatOffenders = $db->query("SELECT COUNT(*) AS c FROM (SELECT vehicle_plate, COUNT(*) AS cnt FROM tickets GROUP BY vehicle_plate HAVING cnt >= 3) x")->fetch_assoc()['c'] ?? 0;
        $escalations = $db->query("SELECT COUNT(*) AS c FROM tickets WHERE status='Escalated'")->fetch_assoc()['c'] ?? 0;
      ?>
      <div class="p-3 border rounded ring-1 ring-slate-200 dark:ring-slate-700 bg-white dark:bg-slate-900">
        <div class="text-sm text-slate-500">Violations (30d)</div>
        <div class="text-2xl font-bold"><?php echo (int)$violations30d; ?></div>
      </div>
      <div class="p-3 border rounded ring-1 ring-slate-200 dark:ring-slate-700 bg-white dark:bg-slate-900">
        <div class="text-sm text-slate-500">Repeat Offenders</div>
        <div class="text-2xl font-bold"><?php echo (int)$repeatOffenders; ?></div>
      </div>
      <div class="p-3 border rounded ring-1 ring-slate-200 dark:ring-slate-700 bg-white dark:bg-slate-900">
        <div class="text-sm text-slate-500">Escalations</div>
        <div class="text-2xl font-bold"><?php echo (int)$escalations; ?></div>
      </div>
    </div>
    <div class="mt-4 flex items-center gap-2">
      <a class="px-3 py-1.5 text-xs font-medium text-blue-600 bg-blue-50 dark:bg-blue-900/30 dark:text-blue-400 rounded-full hover:bg-blue-100 dark:hover:bg-blue-900/50 transition-colors" href="?page=module2/submodule2">Notify Franchise</a>
      <a class="px-3 py-1.5 text-xs font-medium text-red-600 bg-red-50 dark:bg-red-900/30 dark:text-red-400 rounded-full hover:bg-red-100 dark:hover:bg-red-900/50 transition-colors" href="?page=module2/submodule2">Create Case</a>
    </div>
  </div>
</div>
<script>
(function(){
  function showToast(msg, type='success'){
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    const colors = type === 'success' ? 'bg-green-500' : (type === 'error' ? 'bg-red-500' : 'bg-blue-500');
    toast.className = colors + " text-white px-4 py-3 rounded-lg shadow-lg flex items-center gap-3 transform transition-all duration-300 translate-y-10 opacity-0 min-w-[300px] z-50";
    toast.innerHTML = '<span class="font-medium text-sm">'+msg+'</span>';
    container.appendChild(toast);
    requestAnimationFrame(()=>toast.classList.remove('translate-y-10','opacity-0'));
    setTimeout(()=>toast.remove(),3000);
  }
  document.getElementById('validateTicketForm')?.addEventListener('submit', async function(e){
    e.preventDefault();
    const fd = new FormData(this);
    try {
      const res = await fetch('/tmm/admin/api/tickets/validate.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.ok) {
        document.getElementById('valStatus').innerHTML = 'Status: <span class="px-2 py-1 rounded bg-blue-100 text-blue-700">Validated</span>';
        showToast('Ticket validated');
      } else {
        showToast(data.error || 'Validation failed', 'error');
      }
    } catch(err) {
      showToast('Network error', 'error');
    }
  });
  document.getElementById('paymentForm')?.addEventListener('submit', async function(e){
    e.preventDefault();
    const fd = new FormData(this);
    try {
      const res = await fetch('/tmm/admin/api/tickets/settle.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.ok) {
        document.getElementById('payStatus').innerHTML = 'Treasury: <span class="px-2 py-1 rounded bg-green-100 text-green-700">Verified</span>';
        showToast('Payment recorded');
      } else {
        showToast(data.error || 'Payment failed', 'error');
      }
    } catch(err) {
      showToast('Network error', 'error');
    }
  });
})();
</script>
