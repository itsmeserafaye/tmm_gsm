<div class="mx-1 mt-1 p-4 md:p-6 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 rounded-lg">
  <h1 class="text-2xl font-bold mb-2">Franchise Application & Cooperative Management</h1>
  <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">Intake and tracking of franchise endorsement applications, cooperative profiles, consolidation status, and documentation.</p>

  <div class="p-4 border rounded-lg dark:border-slate-700 mb-6">
    <h2 class="text-lg font-semibold mb-3">Submit Application</h2>
    <form class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <input class="px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Cooperative name">
      <input class="px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Coop registration no.">
      <input class="px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="LTFRB franchise ref no.">
      <input class="px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Vehicle count requested">
      <input class="px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Proposed route IDs (comma)">
      <input class="px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Fee receipt ID">
      <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label class="block text-sm mb-1">LTFRB document</label>
          <input type="file" class="w-full text-sm">
        </div>
        <div>
          <label class="block text-sm mb-1">Coop registration</label>
          <input type="file" class="w-full text-sm">
        </div>
        <div>
          <label class="block text-sm mb-1">Member vehicles list</label>
          <input type="file" class="w-full text-sm">
        </div>
      </div>
      <button type="button" class="px-4 py-2 bg-[#4CAF50] text-white rounded-lg md:col-span-2 w-full md:w-auto">Create Application</button>
    </form>
  </div>

  <div class="overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="hidden md:table-header-group">
        <tr class="text-left text-slate-600 dark:text-slate-300">
          <th class="py-2 px-3">Tracking #</th>
          <th class="py-2 px-3">Cooperative</th>
          <th class="py-2 px-3">Franchise Ref</th>
          <th class="py-2 px-3">Routes</th>
          <th class="py-2 px-3">Vehicle Count</th>
          <th class="py-2 px-3">Status</th>
          <th class="py-2 px-3">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y dark:divide-slate-700">
        <tr class="grid grid-cols-1 md:table-row gap-2 md:gap-0 p-2 md:p-0">
          <td class="py-2 px-3"><span class="md:hidden font-semibold">Tracking #: </span>APP-2025-0011</td>
          <td class="py-2 px-3"><span class="md:hidden font-semibold">Cooperative: </span>United Transport COOP</td>
          <td class="py-2 px-3"><span class="md:hidden font-semibold">Franchise Ref: </span>FR-001234</td>
          <td class="py-2 px-3"><span class="md:hidden font-semibold">Routes: </span>R-12,R-08</td>
          <td class="py-2 px-3"><span class="md:hidden font-semibold">Vehicle Count: </span>25</td>
          <td class="py-2 px-3"><span class="md:hidden font-semibold">Status: </span><span class="px-2 py-1 rounded bg-yellow-100 text-yellow-700">Under Review</span></td>
          <td class="py-2 px-3 space-y-2 md:space-x-2 md:space-y-0"><button class="px-2 py-1 border rounded w-full md:w-auto">Open</button><button class="px-2 py-1 border rounded w-full md:w-auto">Assign Officer</button></td>
        </tr>
        <tr class="grid grid-cols-1 md:table-row gap-2 md:gap-0 p-2 md:p-0">
          <td class="py-2 px-3"><span class="md:hidden font-semibold">Tracking #: </span>APP-2025-0023</td>
          <td class="py-2 px-3"><span class="md:hidden font-semibold">Cooperative: </span>Bayanihan COOP</td>
          <td class="py-2 px-3"><span class="md:hidden font-semibold">Franchise Ref: </span>FR-000987</td>
          <td class="py-2 px-3"><span class="md:hidden font-semibold">Routes: </span>R-05</td>
          <td class="py-2 px-3"><span class="md:hidden font-semibold">Vehicle Count: </span>12</td>
          <td class="py-2 px-3"><span class="md:hidden font-semibold">Status: </span><span class="px-2 py-1 rounded bg-blue-100 text-blue-700">Pending Documents</span></td>
          <td class="py-2 px-3 space-y-2 md:space-x-2 md:space-y-0"><button class="px-2 py-1 border rounded w-full md:w-auto">Open</button><button class="px-2 py-1 border rounded w-full md:w-auto">Request Docs</button></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
