<div class="mx-1 mt-1 p-6 dark:bg-slate-900 bg-white dark:text-slate-300 rounded-lg">
  <h1 class="text-2xl font-bold mb-2">Parking and Terminal Operations & Vehicle Enrollment</h1>
  <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">Handles the enrollment of operators and vehicles for authorized parking spaces and terminals. Manages daily operations such as entry/exit logging.</p>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <!-- Enrollment Form -->
    <div class="p-4 border rounded-lg dark:border-slate-700">
      <h2 class="text-lg font-semibold mb-3">Operator & Vehicle Enrollment</h2>
      <form id="formEnroll" class="grid grid-cols-1 md:grid-cols-2 gap-4" method="POST" action="api/module5/enroll_operator.php">
        <select id="selTerminalsEnroll" name="terminal_id" class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" required>
            <option value="">Select Terminal</option>
        </select>
        <input name="operator" class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Operator Name" required>
        <div class="md:col-span-2">
            <input name="plate" class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Vehicle Plate Number" required>
        </div>
        <button type="submit" class="md:col-span-2 px-4 py-2 bg-[#4CAF50] text-white rounded hover:bg-[#45a049] transition-colors">Enroll Vehicle</button>
      </form>
    </div>

    <!-- Daily Logs Form -->
    <div class="p-4 border rounded-lg dark:border-slate-700">
      <h2 class="text-lg font-semibold mb-3">Daily Logs</h2>
      <form id="formLog" class="grid grid-cols-1 md:grid-cols-3 gap-4" method="POST" action="api/module5/log_activity.php">
        <select id="selTerminalsLog" name="terminal_id" class="md:col-span-3 px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" required>
            <option value="">Select Terminal</option>
        </select>
        <input name="plate" class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Vehicle Plate" required>
        <select name="activity" class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" required>
            <option value="">Activity</option>
            <option value="Entry">Entry</option>
            <option value="Exit">Exit</option>
            <option value="Unload">Unload</option>
            <option value="Load">Load</option>
        </select>
        <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 transition-colors">Record</button>
      </form>
    </div>
  </div>

  <!-- Logs Table -->
  <div class="overflow-x-auto mt-6 border rounded-lg dark:border-slate-700">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-100 dark:bg-slate-800">
        <tr class="text-left text-slate-600 dark:text-slate-300">
          <th class="py-3 px-4 font-semibold">Time</th>
          <th class="py-3 px-4 font-semibold">Terminal</th>
          <th class="py-3 px-4 font-semibold">Plate</th>
          <th class="py-3 px-4 font-semibold">Activity</th>
          <th class="py-3 px-4 font-semibold">Remarks</th>
        </tr>
      </thead>
      <tbody id="tableLogs" class="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-900">
        <tr><td colspan="5" class="p-4 text-center text-slate-500">Loading logs...</td></tr>
      </tbody>
    </table>
  </div>

  <!-- Incident Reporting -->
  <div class="p-4 border rounded-lg dark:border-slate-700 mt-6">
    <h2 class="text-lg font-semibold mb-3">Incident Reporting</h2>
    <form id="formIncident" class="grid grid-cols-1 md:grid-cols-3 gap-4" method="POST" action="api/module5/report_incident.php">
      <select id="selTerminalsInc" name="terminal_id" class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" required>
        <option value="">Select Terminal</option>
      </select>
      <input name="plate" class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Vehicle Plate" required>
      <input name="type" class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Incident Type (e.g., Collision)" required>
      <div class="md:col-span-3">
        <textarea name="description" class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Description / Remarks"></textarea>
      </div>
      <button type="submit" class="md:col-span-3 px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600 transition-colors">Submit Incident Report</button>
    </form>
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
                
                document.getElementById('selTerminalsEnroll').innerHTML = options;
                document.getElementById('selTerminalsLog').innerHTML = options;
                document.getElementById('selTerminalsInc').innerHTML = options;
            });
    }

    function loadLogs() {
        fetch('api/module5/list_logs.php')
            .then(r => r.json())
            .then(data => {
                const tbody = document.getElementById('tableLogs');
                tbody.innerHTML = '';
                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" class="p-4 text-center text-slate-500">No logs found.</td></tr>';
                    return;
                }
                data.forEach(row => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td class="py-3 px-4 font-mono text-xs">${row.log_time}</td>
                        <td class="py-3 px-4">${row.terminal_name}</td>
                        <td class="py-3 px-4 font-bold">${row.vehicle_plate}</td>
                        <td class="py-3 px-4"><span class="px-2 py-1 rounded text-xs font-bold ${row.activity_type === 'Entry' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700'}">${row.activity_type}</span></td>
                        <td class="py-3 px-4 text-slate-500 text-xs">${row.remarks || '-'}</td>
                    `;
                    tbody.appendChild(tr);
                });
            });
    }

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

    bindForm('formEnroll');
    bindForm('formLog', loadLogs);
    bindForm('formIncident');

    loadTerminals();
    loadLogs();
})();
</script>