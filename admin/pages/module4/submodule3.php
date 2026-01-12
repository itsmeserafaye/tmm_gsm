<div class="mx-1 mt-1 p-6 dark:bg-slate-900 bg-white dark:text-slate-300 rounded-lg">
  <h1 class="text-2xl font-bold mb-2">Route Validation & Compliance Reporting</h1>
  <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">Validate terminals and routes against LPTRP capacity and produce inspection compliance reports.</p>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="p-4 border rounded-lg dark:border-slate-700">
      <h2 class="text-lg font-semibold mb-3">Route Capacity</h2>
      <form class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <input class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Route ID">
        <select class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700"><option>Terminal</option><option>Central Terminal</option><option>East Hub</option></select>
        <button type="button" class="px-4 py-2 bg-[#4CAF50] text-white rounded">Validate</button>
      </form>
      <div class="mt-3">
        <div class="text-sm">Max Vehicles: 50</div>
        <div class="w-full bg-slate-200 rounded h-2 mt-2"><div class="bg-[#4CAF50] h-2 rounded" style="width: 40%"></div></div>
        <div class="text-xs mt-1">20/50 assigned • Within limit</div>
      </div>
    </div>

    <div class="p-4 border rounded-lg dark:border-slate-700">
      <h2 class="text-lg font-semibold mb-3">Compliance Reporting</h2>
      <form class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <select class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700"><option>Period</option><option>30d</option><option>90d</option><option>Year</option></select>
        <select class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700"><option>Status</option><option>Passed</option><option>Failed</option><option>Pending</option></select>
        <select class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700"><option>Coop</option><option>United Transport</option><option>Bayanihan</option></select>
        <button type="button" class="md:col-span-3 px-4 py-2 bg-[#4CAF50] text-white rounded">Generate Report</button>
      </form>
    </div>
  </div>

  <div class="overflow-x-auto mt-6">
    <table class="min-w-full text-sm">
      <thead>
        <tr class="text-left text-slate-600 dark:text-slate-300">
          <th class="py-2 px-3">Vehicle</th>
          <th class="py-2 px-3">Route</th>
          <th class="py-2 px-3">Terminal</th>
          <th class="py-2 px-3">Inspection Status</th>
          <th class="py-2 px-3">Certificate</th>
        </tr>
      </thead>
      <tbody class="divide-y dark:divide-slate-700">
        <tr>
          <td class="py-2 px-3">ABC-1234</td>
          <td class="py-2 px-3">R-12</td>
          <td class="py-2 px-3">Central Terminal</td>
          <td class="py-2 px-3"><span class="px-2 py-1 rounded bg-green-100 text-green-700">Passed</span></td>
          <td class="py-2 px-3">CERT-2025-8801</td>
        </tr>
        <tr>
          <td class="py-2 px-3">XYZ-5678</td>
          <td class="py-2 px-3">R-08</td>
          <td class="py-2 px-3">East Hub</td>
          <td class="py-2 px-3"><span class="px-2 py-1 rounded bg-yellow-100 text-yellow-700">Pending</span></td>
          <td class="py-2 px-3">—</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>