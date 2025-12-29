<div class="mx-1 mt-1 p-6 dark:bg-slate-900 bg-white dark:text-slate-300 rounded-lg">
  <h1 class="text-2xl font-bold mb-2">Parking and Terminal Permit & Inspection Management</h1>
  <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">Manages applications, inspections, approvals, renewals, and revocations of LGU-regulated parking areas, loading/unloading bays, and public transport terminals. Includes compliance checks, permit conditions, and storage of inspection and permit documentation.</p>

  <div class="p-4 border rounded-lg dark:border-slate-700 mb-6">
    <h2 class="text-lg font-semibold mb-3">Permit Application</h2>
    <form class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <input class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Terminal name">
      <input class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Address">
      <input class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Applicant (coop/operator)">
      <div><label class="block text-sm mb-1">Site Plan</label><input type="file" class="w-full text-sm"></div>
      <div><label class="block text-sm mb-1">Safety Certificates</label><input type="file" class="w-full text-sm"></div>
      <div><label class="block text-sm mb-1">Operator List</label><input type="file" class="w-full text-sm"></div>
      <button type="button" class="md:col-span-3 px-4 py-2 bg-[#4CAF50] text-white rounded">Create Application</button>
    </form>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="p-4 border rounded-lg dark:border-slate-700">
      <h3 class="text-md font-semibold mb-2">Schedule Site Inspection</h3>
      <form class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <input type="datetime-local" class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700">
        <select class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700"><option>Inspector</option><option>Inspector Dela Cruz</option><option>Inspector Santos</option></select>
        <input class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Location">
        <button type="button" class="px-4 py-2 bg-[#4CAF50] text-white rounded">Schedule</button>
      </form>
    </div>

    <div class="p-4 border rounded-lg dark:border-slate-700">
      <h3 class="text-md font-semibold mb-2">Permit Issuance</h3>
      <form class="space-y-3">
        <input class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Conditions (hours, capacity)">
        <input class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Expiry date">
        <input class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Receipt ID">
        <button type="button" class="px-4 py-2 bg-[#4CAF50] text-white rounded">Issue Permit</button>
      </form>
    </div>
  </div>

  <div class="overflow-x-auto mt-6">
    <table class="min-w-full text-sm">
      <thead>
        <tr class="text-left text-slate-600 dark:text-slate-300">
          <th class="py-2 px-3">Application #</th>
          <th class="py-2 px-3">Terminal</th>
          <th class="py-2 px-3">Status</th>
          <th class="py-2 px-3">Assigned Inspector</th>
          <th class="py-2 px-3">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y dark:divide-slate-700">
        <tr>
          <td class="py-2 px-3">PERM-2025-1101</td>
          <td class="py-2 px-3">Central Terminal</td>
          <td class="py-2 px-3"><span class="px-2 py-1 rounded bg-yellow-100 text-yellow-700">Under Review</span></td>
          <td class="py-2 px-3">Inspector Dela Cruz</td>
          <td class="py-2 px-3 space-x-2"><button class="px-2 py-1 border rounded">Open</button><button class="px-2 py-1 border rounded">Approve</button></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>