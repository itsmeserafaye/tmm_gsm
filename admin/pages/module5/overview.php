<div class="mx-1 mt-1 p-6 dark:bg-slate-900 bg-white dark:text-slate-300 rounded-lg">
  <h1 class="text-2xl font-bold mb-2">Parking & Terminal Management — Overview</h1>
  <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">Administer permits, rosters, daily operations, fee reconciliation, enforcement links, and LPTRP-aligned planning for city terminals and parking.</p>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="p-4 border rounded-lg dark:border-slate-700 bg-blue-50 dark:bg-slate-800">
      <div class="text-sm text-slate-500 dark:text-slate-400">Active Terminals</div>
      <div id="statTerminals" class="text-3xl font-bold text-blue-600 dark:text-blue-400">...</div>
    </div>
    <div class="p-4 border rounded-lg dark:border-slate-700 bg-yellow-50 dark:bg-slate-800">
      <div class="text-sm text-slate-500 dark:text-slate-400">Pending Permit Apps</div>
      <div id="statPending" class="text-3xl font-bold text-yellow-600 dark:text-yellow-400">...</div>
    </div>
    <div class="p-4 border rounded-lg dark:border-slate-700 bg-green-50 dark:bg-slate-800">
      <div class="text-sm text-slate-500 dark:text-slate-400">Daily Logs (Today)</div>
      <div id="statLogs" class="text-3xl font-bold text-green-600 dark:text-green-400">...</div>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
    <div class="p-4 border rounded-lg dark:border-slate-700">
      <h2 class="text-lg font-semibold mb-3">Work Queue</h2>
      <ul class="text-sm space-y-2">
        <li class="flex items-center justify-between p-2 hover:bg-slate-50 dark:hover:bg-slate-800 rounded">
            <span>Manage Terminals & Routes</span>
            <a href="?page=module5/terminals" class="px-2 py-1 border rounded text-xs hover:bg-slate-100 dark:hover:bg-slate-700">Go</a>
        </li>
        <li class="flex items-center justify-between p-2 hover:bg-slate-50 dark:hover:bg-slate-800 rounded">
            <span>Manage Parking Areas</span>
            <a href="?page=module5/parking" class="px-2 py-1 border rounded text-xs hover:bg-slate-100 dark:hover:bg-slate-700">Go</a>
        </li>
        <li class="flex items-center justify-between p-2 hover:bg-slate-50 dark:hover:bg-slate-800 rounded">
            <span>Reconcile Daily Fees</span>
            <a href="?page=module5/submodule3" class="px-2 py-1 border rounded text-xs hover:bg-slate-100 dark:hover:bg-slate-700">Go</a>
        </li>
      </ul>
    </div>
    <div class="p-4 border rounded-lg dark:border-slate-700">
      <h2 class="text-lg font-semibold mb-3">Quick Actions</h2>
      <div class="flex flex-wrap gap-2">
        <a href="?page=module5/terminals" class="px-3 py-2 bg-[#4CAF50] text-white rounded hover:bg-[#45a049] transition-colors">Add Terminal Area</a>
        <a href="?page=module5/parking" class="px-3 py-2 border border-slate-300 dark:border-slate-600 rounded hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">Add Parking</a>
        <a href="?page=module5/submodule3" class="px-3 py-2 border border-slate-300 dark:border-slate-600 rounded hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">Record Payment</a>
      </div>
    </div>
  </div>
</div>

<script>
fetch('api/module5/get_stats.php')
    .then(r => r.json())
    .then(data => {
        document.getElementById('statTerminals').textContent = data.active_terminals;
        document.getElementById('statPending').textContent = data.pending_apps;
        document.getElementById('statLogs').textContent = data.today_logs;
    });
</script>