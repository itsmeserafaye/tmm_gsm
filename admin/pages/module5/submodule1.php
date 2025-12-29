<div class="mx-1 mt-1 p-6 dark:bg-slate-900 bg-white dark:text-slate-300 rounded-lg">
  <h1 class="text-2xl font-bold mb-2">Parking and Terminal Permit & Inspection Management</h1>
  <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">Manages applications, inspections, approvals, renewals, and revocations of LGU-regulated parking areas, loading/unloading bays, and public transport terminals.</p>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
    <!-- Application Form -->
    <div class="p-4 border rounded-lg dark:border-slate-700">
      <h2 class="text-lg font-semibold mb-3">New Terminal Application</h2>
      <form id="formApp" class="space-y-3" method="POST" action="api/module5/save_terminal.php">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <input name="name" class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Terminal/Area Name" required>
            <select name="type" class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700">
                <option value="Terminal">Terminal</option>
                <option value="Parking">Parking Lot</option>
                <option value="LoadingBay">Loading Bay</option>
            </select>
        </div>
        <input name="address" class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Address/Location" required>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <input name="applicant" class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Applicant (Coop/Operator)" required>
            <input name="capacity" type="number" class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Est. Capacity">
        </div>
        <button type="submit" class="w-full px-4 py-2 bg-[#4CAF50] text-white rounded hover:bg-[#45a049] transition-colors">Submit Application</button>
      </form>
    </div>

    <!-- Inspection Schedule -->
    <div class="p-4 border rounded-lg dark:border-slate-700">
      <h2 class="text-lg font-semibold mb-3">Schedule Inspection</h2>
      <form id="formInsp" class="space-y-3" method="POST" action="api/module5/save_inspection.php">
        <select id="selTerminals" name="terminal_id" class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" required>
            <option value="">Select Terminal</option>
        </select>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <input name="date" type="datetime-local" class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" required>
            <input name="inspector" class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Inspector Name" required>
        </div>
        <input name="location" class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Specific Location/Landmark">
        <button type="submit" class="w-full px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 transition-colors">Schedule Inspection</button>
      </form>
    </div>
  </div>

  <!-- Applications List -->
  <div class="overflow-x-auto mt-6 border rounded-lg dark:border-slate-700">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-100 dark:bg-slate-800">
        <tr class="text-left text-slate-600 dark:text-slate-300">
          <th class="py-3 px-4 font-semibold">App #</th>
          <th class="py-3 px-4 font-semibold">Terminal</th>
          <th class="py-3 px-4 font-semibold">Type</th>
          <th class="py-3 px-4 font-semibold">Applicant</th>
          <th class="py-3 px-4 font-semibold">Status</th>
          <th class="py-3 px-4 font-semibold">Actions</th>
        </tr>
      </thead>
      <tbody id="listPermits" class="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-900">
        <tr><td colspan="6" class="p-4 text-center text-slate-500">Loading...</td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Approval Modal -->
<div id="modalApprove" class="fixed inset-0 z-50 hidden bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-900 rounded-lg w-full max-w-md p-6 shadow-xl">
        <h3 class="text-lg font-bold mb-4">Update Permit Status</h3>
        <form id="formUpdate" method="POST" action="api/module5/update_permit.php">
            <input type="hidden" name="permit_id" id="permId">
            <div class="mb-3">
                <label class="block text-sm mb-1">Status</label>
                <select name="status" class="w-full px-3 py-2 border rounded dark:bg-slate-800 dark:border-slate-700">
                    <option value="Approved">Approve (Issue Permit)</option>
                    <option value="Rejected">Reject</option>
                    <option value="Revoked">Revoke</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="block text-sm mb-1">Expiry Date (if approved)</label>
                <input type="date" name="expiry_date" class="w-full px-3 py-2 border rounded dark:bg-slate-800 dark:border-slate-700">
            </div>
            <div class="mb-4">
                <label class="block text-sm mb-1">Conditions / Remarks</label>
                <textarea name="conditions" rows="3" class="w-full px-3 py-2 border rounded dark:bg-slate-800 dark:border-slate-700"></textarea>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('modalApprove').classList.add('hidden')" class="px-4 py-2 border rounded">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-green-500 text-white rounded">Update</button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    function loadTerminals() {
        fetch('api/module5/list_terminals.php')
            .then(r => r.json())
            .then(data => {
                const sel = document.getElementById('selTerminals');
                sel.innerHTML = '<option value="">Select Terminal</option>';
                data.forEach(t => {
                    const opt = document.createElement('option');
                    opt.value = t.id;
                    opt.textContent = t.name;
                    sel.appendChild(opt);
                });
            });
    }

    function loadPermits() {
        fetch('api/module5/list_permits.php')
            .then(r => r.json())
            .then(data => {
                const tbody = document.getElementById('listPermits');
                tbody.innerHTML = '';
                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" class="p-4 text-center text-slate-500">No applications found.</td></tr>';
                    return;
                }
                data.forEach(row => {
                    const tr = document.createElement('tr');
                    let badge = 'bg-slate-100 text-slate-600';
                    if (row.status === 'Approved') badge = 'bg-green-100 text-green-700';
                    if (row.status === 'Rejected') badge = 'bg-red-100 text-red-700';
                    if (row.status === 'Pending') badge = 'bg-yellow-100 text-yellow-700';

                    tr.innerHTML = `
                        <td class="py-3 px-4 font-mono text-xs">${row.application_no}</td>
                        <td class="py-3 px-4 font-medium">${row.terminal_name}</td>
                        <td class="py-3 px-4 text-slate-500">${row.type}</td>
                        <td class="py-3 px-4">${row.applicant_name}</td>
                        <td class="py-3 px-4"><span class="px-2 py-1 rounded text-xs font-bold ${badge}">${row.status}</span></td>
                        <td class="py-3 px-4">
                            <button onclick="openModal(${row.id})" class="text-blue-500 hover:underline text-xs">Update</button>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            });
    }

    window.openModal = function(id) {
        document.getElementById('permId').value = id;
        document.getElementById('modalApprove').classList.remove('hidden');
    };

    function bindForm(id, cb) {
        const f = document.getElementById(id);
        f.addEventListener('submit', function(e) {
            e.preventDefault();
            const fd = new FormData(f);
            fetch(f.action, {method: 'POST', body: fd})
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        alert(res.message || 'Saved successfully');
                        f.reset();
                        if (cb) cb();
                    } else {
                        alert('Error: ' + res.message);
                    }
                });
        });
    }

    bindForm('formApp', () => { loadPermits(); loadTerminals(); });
    bindForm('formInsp');
    bindForm('formUpdate', () => { 
        document.getElementById('modalApprove').classList.add('hidden');
        loadPermits();
    });

    loadTerminals();
    loadPermits();
})();
</script>