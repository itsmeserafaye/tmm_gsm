<div class="mx-1 mt-1 p-6 dark:bg-slate-900 bg-white dark:text-slate-300 rounded-lg">
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
      <button type="button" class="px-4 py-2 bg-[#4CAF50] text-white rounded-lg md:col-span-2">Create Application</button>
    </form>
  </div>

  <div class="overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead>
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
        <tr>
          <td class="py-2 px-3">APP-2025-0011</td>
          <td class="py-2 px-3">United Transport COOP</td>
          <td class="py-2 px-3">FR-001234</td>
          <td class="py-2 px-3">R-12,R-08</td>
          <td class="py-2 px-3">25</td>
          <td class="py-2 px-3"><span class="px-2 py-1 rounded bg-yellow-100 text-yellow-700">Under Review</span></td>
          <td class="py-2 px-3 space-x-2"><button class="px-2 py-1 border rounded">Open</button><button class="px-2 py-1 border rounded">Assign Officer</button></td>
        </tr>
        <tr>
          <td class="py-2 px-3">APP-2025-0023</td>
          <td class="py-2 px-3">Bayanihan COOP</td>
          <td class="py-2 px-3">FR-000987</td>
          <td class="py-2 px-3">R-05</td>
          <td class="py-2 px-3">12</td>
          <td class="py-2 px-3"><span class="px-2 py-1 rounded bg-blue-100 text-blue-700">Pending Documents</span></td>
          <td class="py-2 px-3 space-x-2"><button class="px-2 py-1 border rounded">Open</button><button class="px-2 py-1 border rounded">Request Docs</button></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>