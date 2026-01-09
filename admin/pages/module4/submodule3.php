<div class="mx-1 mt-1 p-4 md:p-6 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 rounded-lg">
  <h1 class="text-2xl font-bold mb-2">Route Validation & Compliance Reporting</h1>
  <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">Validate terminals and routes against LPTRP capacity and produce inspection compliance reports.</p>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="p-6 border rounded-lg ring-1 ring-slate-200 dark:ring-slate-700 bg-white dark:bg-slate-900 border-t-4 border-t-blue-500 shadow-sm">
      <h2 class="text-lg font-semibold mb-3 flex items-center gap-2"><i data-lucide="map" class="w-5 h-5 text-blue-500"></i> Route Capacity</h2>
      <form class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <input class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all" placeholder="Route ID">
        <select class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all">
          <option>Terminal</option>
          <option>Central Terminal</option>
          <option>East Hub</option>
        </select>
        <button type="button" class="w-full px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white font-medium rounded-lg transition-colors shadow-sm">Validate</button>
      </form>
      <div class="mt-4 p-3 bg-slate-50 dark:bg-slate-800/50 rounded-lg border dark:border-slate-700">
        <div class="flex justify-between items-center text-sm mb-2">
          <span class="text-slate-600 dark:text-slate-400">Capacity Usage</span>
          <span class="font-medium">20/50</span>
        </div>
        <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-2.5 overflow-hidden">
          <div class="bg-blue-500 h-2.5 rounded-full" style="width: 40%"></div>
        </div>
        <div class="text-xs mt-2 text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
          <i data-lucide="check-circle" class="w-3 h-3"></i> Within limit
        </div>
      </div>
    </div>

    <div class="p-6 border rounded-lg ring-1 ring-slate-200 dark:ring-slate-700 bg-white dark:bg-slate-900 border-t-4 border-t-violet-500 shadow-sm">
      <h2 class="text-lg font-semibold mb-3 flex items-center gap-2"><i data-lucide="file-bar-chart" class="w-5 h-5 text-violet-500"></i> Compliance Reporting</h2>
      <form class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <select class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-violet-500/20 focus:border-violet-500 outline-none transition-all">
          <option>Period</option>
          <option>30d</option>
          <option>90d</option>
          <option>Year</option>
        </select>
        <select class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-violet-500/20 focus:border-violet-500 outline-none transition-all">
          <option>Status</option>
          <option>Passed</option>
          <option>Failed</option>
          <option>Pending</option>
        </select>
        <select class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-violet-500/20 focus:border-violet-500 outline-none transition-all">
          <option>Coop</option>
          <option>United Transport</option>
          <option>Bayanihan</option>
        </select>
        <button type="button" class="md:col-span-3 w-full px-4 py-2 bg-violet-500 hover:bg-violet-600 text-white font-medium rounded-lg transition-colors shadow-sm flex items-center justify-center gap-2">
          <i data-lucide="download" class="w-4 h-4"></i> Generate Report
        </button>
      </form>
    </div>
  </div>

  <div class="mt-6 border rounded-lg ring-1 ring-slate-200 dark:ring-slate-700 overflow-hidden shadow-sm">
    <div class="bg-slate-50 dark:bg-slate-800 px-4 py-3 border-b dark:border-slate-700">
      <h3 class="font-medium text-slate-700 dark:text-slate-300">Recent Inspection Records</h3>
    </div>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm text-left">
        <thead class="bg-slate-50 dark:bg-slate-800 text-slate-500 dark:text-slate-400 font-medium border-b dark:border-slate-700">
          <tr>
            <th class="py-3 px-4">Vehicle</th>
            <th class="py-3 px-4">Route</th>
            <th class="py-3 px-4">Terminal</th>
            <th class="py-3 px-4">Inspection Status</th>
            <th class="py-3 px-4">Certificate</th>
          </tr>
        </thead>
        <tbody class="divide-y dark:divide-slate-700 bg-white dark:bg-slate-900">
          <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
            <td class="py-3 px-4 font-medium">ABC-1234</td>
            <td class="py-3 px-4">R-12</td>
            <td class="py-3 px-4">Central Terminal</td>
            <td class="py-3 px-4"><span class="px-2 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700 ring-1 ring-emerald-600/20">Passed</span></td>
            <td class="py-3 px-4 font-mono text-slate-500">CERT-2025-8801</td>
          </tr>
          <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
            <td class="py-3 px-4 font-medium">XYZ-5678</td>
            <td class="py-3 px-4">R-08</td>
            <td class="py-3 px-4">East Hub</td>
            <td class="py-3 px-4"><span class="px-2 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-700 ring-1 ring-amber-600/20">Pending</span></td>
            <td class="py-3 px-4 text-slate-400">—</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>
<script>
  if(window.lucide) window.lucide.createIcons();
</script>