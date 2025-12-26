<div class="mx-1 mt-1 p-6 dark:bg-slate-900 bg-white dark:text-slate-300 rounded-lg">
  <h1 class="text-2xl font-bold mb-2">Renewals, Monitoring & Reporting</h1>
  <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">Tracks validity, renewal schedules, compliance history, and generates management reports.</p>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="p-4 border rounded-lg dark:border-slate-700">
      <h2 class="text-lg font-semibold mb-3">Renewal Reminders</h2>
      <ul class="text-sm space-y-2">
        <li class="flex items-center justify-between"><span>United Transport COOP • 2026-01-15</span><button class="px-2 py-1 border rounded">Notify</button></li>
        <li class="flex items-center justify-between"><span>Bayanihan COOP • 2025-12-30</span><button class="px-2 py-1 border rounded">Notify</button></li>
        <li class="flex items-center justify-between"><span>Makisama COOP • 2026-02-10</span><button class="px-2 py-1 border rounded">Notify</button></li>
      </ul>
    </div>

    <div class="p-4 border rounded-lg dark:border-slate-700">
      <h2 class="text-lg font-semibold mb-3">Reporting Filters</h2>
      <form class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <select class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700"><option>Period</option><option>30d</option><option>90d</option><option>Year</option></select>
        <select class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700"><option>Status</option><option>Endorsed</option><option>Conditional</option><option>Rejected</option></select>
        <select class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700"><option>Coop</option><option>United Transport</option><option>Bayanihan</option></select>
        <button type="button" class="md:col-span-3 px-4 py-2 bg-[#4CAF50] text-white rounded">Generate Report</button>
      </form>
    </div>
  </div>

  <div class="overflow-x-auto mt-6">
    <table class="min-w-full text-sm">
      <thead>
        <tr class="text-left text-slate-600 dark:text-slate-300">
          <th class="py-2 px-3">Endorsement ID</th>
          <th class="py-2 px-3">Application</th>
          <th class="py-2 px-3">Issued</th>
          <th class="py-2 px-3">Method</th>
          <th class="py-2 px-3">Permit No.</th>
          <th class="py-2 px-3">Status</th>
        </tr>
      </thead>
      <tbody class="divide-y dark:divide-slate-700">
        <tr>
          <td class="py-2 px-3">END-2025-0101</td>
          <td class="py-2 px-3">APP-2025-0011</td>
          <td class="py-2 px-3">2025-12-20</td>
          <td class="py-2 px-3">Email</td>
          <td class="py-2 px-3">PER-2025-7781</td>
          <td class="py-2 px-3"><span class="px-2 py-1 rounded bg-green-100 text-green-700">Active</span></td>
        </tr>
        <tr>
          <td class="py-2 px-3">END-2025-0090</td>
          <td class="py-2 px-3">APP-2025-0023</td>
          <td class="py-2 px-3">2025-12-10</td>
          <td class="py-2 px-3">Portal</td>
          <td class="py-2 px-3">PER-2025-7702</td>
          <td class="py-2 px-3"><span class="px-2 py-1 rounded bg-yellow-100 text-yellow-700">Conditional</span></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>