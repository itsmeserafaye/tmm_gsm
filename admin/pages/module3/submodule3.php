<div class="mx-1 mt-1 p-6 dark:bg-slate-900 bg-white dark:text-slate-300 rounded-lg">
  <h1 class="text-2xl font-bold mb-2">Analytics, Reporting & Integration</h1>
  <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">Dashboards and reports with synchronization hooks for MMDA STS, Parking & Terminal, and Inspection modules.</p>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="p-4 border rounded-lg dark:border-slate-700">
      <h2 class="text-lg font-semibold mb-3">Reporting Filters</h2>
      <form class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <select class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700"><option>Period</option><option>30d</option><option>90d</option><option>Year</option></select>
        <select class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700"><option>Status</option><option>Pending</option><option>Validated</option><option>Settled</option><option>Escalated</option></select>
        <input class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Plate or Franchise">
        <button type="button" class="md:col-span-3 px-4 py-2 bg-[#4CAF50] text-white rounded">Generate</button>
      </form>
    </div>

    <div class="p-4 border rounded-lg dark:border-slate-700">
      <h2 class="text-lg font-semibold mb-3">Integration Actions</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <button class="px-3 py-2 border rounded">Sync to STS</button>
        <button class="px-3 py-2 border rounded">Export CSV</button>
        <button class="px-3 py-2 border rounded">Notify Inspection</button>
        <button class="px-3 py-2 border rounded">Notify Parking</button>
      </div>
    </div>
  </div>

  <div class="overflow-x-auto mt-6">
    <table class="min-w-full text-sm">
      <thead>
        <tr class="text-left text-slate-600 dark:text-slate-300">
          <th class="py-2 px-3">Ticket #</th>
          <th class="py-2 px-3">Violation</th>
          <th class="py-2 px-3">Plate</th>
          <th class="py-2 px-3">Status</th>
          <th class="py-2 px-3">Fine</th>
          <th class="py-2 px-3">Issued</th>
        </tr>
      </thead>
      <tbody class="divide-y dark:divide-slate-700">
        <tr>
          <td class="py-2 px-3">TCK-2025-0103</td>
          <td class="py-2 px-3">No Loading Zone</td>
          <td class="py-2 px-3">AAA-9999</td>
          <td class="py-2 px-3"><span class="px-2 py-1 rounded bg-yellow-100 text-yellow-700">Validated</span></td>
          <td class="py-2 px-3">₱1000</td>
          <td class="py-2 px-3">2025-12-26</td>
        </tr>
        <tr>
          <td class="py-2 px-3">TCK-2025-0104</td>
          <td class="py-2 px-3">Disregarding Traffic Signs</td>
          <td class="py-2 px-3">BBB-2222</td>
          <td class="py-2 px-3"><span class="px-2 py-1 rounded bg-green-100 text-green-700">Settled</span></td>
          <td class="py-2 px-3">₱500</td>
          <td class="py-2 px-3">2025-12-26</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>