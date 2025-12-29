<div class="mx-1 mt-1 p-6 dark:bg-slate-900 bg-white dark:text-slate-300 rounded-lg">
  <h1 class="text-2xl font-bold mb-2">Parking Fees, Enforcement & Analytics</h1>
  <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">Records parking and terminal usage fees, synchronizes payment confirmations, and links violations.</p>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <!-- Fee Charges -->
    <div class="p-4 border rounded-lg dark:border-slate-700">
      <h2 class="text-lg font-semibold mb-3">Fee Charges & Payments</h2>
      <form id="formCharge" class="grid grid-cols-1 md:grid-cols-2 gap-4" method="POST" action="api/module5/save_charge.php">
        <select id="selTerminalsCharge" name="terminal_id" class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" required>
            <option value="">Select Terminal</option>
        </select>
        <input name="amount" type="number" step="0.01" class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Amount (PHP)" required>
        <select name="type" class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" required>
            <option value="">Charge Type</option>
            <option value="Permit Fee">Permit Fee</option>
            <option value="Usage Fee">Usage Fee</option>
            <option value="Stall Rent">Stall Rent</option>
            <option value="Penalty">Penalty</option>
        </select>
        <input name="due_date" type="date" class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" required>
        <button type="submit" class="md:col-span-2 px-4 py-2 bg-[#4CAF50] text-white rounded hover:bg-[#45a049] transition-colors">Create Charge</button>
      </form>
      <div id="chargeResult" class="mt-3 text-sm hidden">Receipt: <span id="receiptNo" class="px-2 py-1 rounded bg-green-100 text-green-700 font-mono"></span></div>
    </div>

    <!-- Enforcement (Mock for now) -->
    <div class="p-4 border rounded-lg dark:border-slate-700">
      <h2 class="text-lg font-semibold mb-3">Enforcement & Violations</h2>
      <form id="formTicket" class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <input name="incident_id" class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Incident ID">
        <input name="plate" class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Vehicle plate">
        <button type="button" onclick="alert('Ticket created successfully (Mock)')" class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600 transition-colors">Create Ticket</button>
      </form>
      <div class="mt-3 text-sm text-slate-500 italic">Integrates with Traffic Violation Module</div>
    </div>
  </div>

  <!-- Reconciliation & Analytics -->
  <div class="p-4 border rounded-lg dark:border-slate-700 mt-6">
    <h2 class="text-lg font-semibold mb-3">Reconciliation & Analytics</h2>
    <form id="formReconcile" class="grid grid-cols-1 md:grid-cols-3 gap-3">
      <input id="recDate" type="date" class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" value="<?php echo date('Y-m-d'); ?>">
      <select id="selTerminalsRec" class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700">
        <option value="">All Terminals</option>
      </select>
      <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 transition-colors">Reconcile</button>
    </form>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
      <div class="p-3 border rounded dark:border-slate-700">
        <div class="text-sm text-slate-500">Total Fees Collected</div>
        <div id="valTotal" class="text-2xl font-bold">₱0.00</div>
      </div>
      <div class="p-3 border rounded dark:border-slate-700">
        <div class="text-sm text-slate-500">Transactions</div>
        <div id="valCount" class="text-2xl font-bold">0</div>
      </div>
      <div class="p-3 border rounded dark:border-slate-700">
        <div class="text-sm text-slate-500">Incidents Reported</div>
        <div id="valIncidents" class="text-2xl font-bold">0</div>
      </div>
    </div>
  </div>
</div>

<script>
(function() {
    function loadTerminals() {
        fetch('api/module5/list_terminals.php')
            .then(r => r.json())
            .then(data => {
                const options = '<option value="">Select Terminal</option>' + 
                    data.map(t => `<option value="${t.id}">${t.name}</option>`).join('');
                const optionsAll = '<option value="">All Terminals</option>' + 
                    data.map(t => `<option value="${t.id}">${t.name}</option>`).join('');
                
                document.getElementById('selTerminalsCharge').innerHTML = options;
                document.getElementById('selTerminalsRec').innerHTML = optionsAll;
            });
    }

    const formCharge = document.getElementById('formCharge');
    formCharge.addEventListener('submit', function(e) {
        e.preventDefault();
        const fd = new FormData(formCharge);
        fetch(formCharge.action, {method: 'POST', body: fd})
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    document.getElementById('chargeResult').classList.remove('hidden');
                    document.getElementById('receiptNo').textContent = res.receipt;
                    formCharge.reset();
                    // Refresh reconciliation if date matches
                    document.getElementById('formReconcile').dispatchEvent(new Event('submit'));
                } else {
                    alert('Error: ' + res.message);
                }
            });
    });

    const formReconcile = document.getElementById('formReconcile');
    formReconcile.addEventListener('submit', function(e) {
        if(e) e.preventDefault();
        const date = document.getElementById('recDate').value;
        const term = document.getElementById('selTerminalsRec').value;
        
        fetch(`api/module5/reconcile_fees.php?date=${date}&terminal_id=${term}`)
            .then(r => r.json())
            .then(data => {
                document.getElementById('valTotal').textContent = '₱' + parseFloat(data.total_fees).toLocaleString('en-US', {minimumFractionDigits: 2});
                document.getElementById('valCount').textContent = data.transaction_count;
                document.getElementById('valIncidents').textContent = data.incident_count;
            });
    });

    loadTerminals();
    // Initial load
    setTimeout(() => formReconcile.dispatchEvent(new Event('submit')), 500);
})();
</script>