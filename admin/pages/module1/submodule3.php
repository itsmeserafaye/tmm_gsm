<div class="mx-1 mt-1 p-6 dark:bg-slate-900 bg-white dark:text-slate-300 rounded-lg">
  <h1 class="text-2xl font-bold mb-2">Route & Terminal Assignment Management</h1>
  <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">Assign vehicles to LPTRP-approved routes and authorized terminals, enforcing capacity and eligibility.</p>

  <div class="p-4 border rounded-lg dark:border-slate-700 mb-6">
    <h2 class="text-lg font-semibold mb-3">Assign Route</h2>
    <form class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <input class="px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Plate number">
      <input class="px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Route ID">
      <select class="px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700">
        <option>Terminal</option>
        <option>Central Terminal</option>
        <option>East Hub</option>
        <option>North Bay</option>
      </select>
      <button type="button" class="px-4 py-2 bg-[#4CAF50] text-white rounded-lg">Assign</button>
    </form>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="p-4 border rounded-lg dark:border-slate-700">
      <h3 class="text-md font-semibold mb-2">Route Capacity</h3>
      <div class="text-sm">Route R-12 • Max Vehicles: 50</div>
      <div class="w-full bg-slate-200 rounded h-2 mt-2">
        <div class="bg-[#4CAF50] h-2 rounded" style="width: 60%"></div>
      </div>
      <div class="text-xs mt-1">30/50 assigned</div>
    </div>

    <div class="p-4 border rounded-lg dark:border-slate-700">
      <h3 class="text-md font-semibold mb-2">Terminals for Route</h3>
      <ul class="text-sm space-y-1">
        <li class="flex items-center justify-between">
          <span>Central Terminal</span>
          <span class="px-2 py-1 rounded bg-green-100 text-green-700">Allowed</span>
        </li>
        <li class="flex items-center justify-between">
          <span>East Hub</span>
          <span class="px-2 py-1 rounded bg-yellow-100 text-yellow-700">Limited</span>
        </li>
        <li class="flex items-center justify-between">
          <span>North Bay</span>
          <span class="px-2 py-1 rounded bg-red-100 text-red-700">Restricted</span>
        </li>
      </ul>
    </div>
  </div>

  <div class="overflow-x-auto mt-6">
    <table class="min-w-full text-sm">
      <thead>
        <tr class="text-left text-slate-600 dark:text-slate-300">
          <th class="py-2 px-3">Plate</th>
          <th class="py-2 px-3">Route</th>
          <th class="py-2 px-3">Terminal</th>
          <th class="py-2 px-3">Status</th>
          <th class="py-2 px-3">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y dark:divide-slate-700">
        <tr>
          <td class="py-2 px-3">ABC-1234</td>
          <td class="py-2 px-3">R-12</td>
          <td class="py-2 px-3">Central Terminal</td>
          <td class="py-2 px-3"><span class="px-2 py-1 rounded bg-green-100 text-green-700">Authorized</span></td>
          <td class="py-2 px-3"><button class="px-2 py-1 border rounded">Reassign</button></td>
        </tr>
        <tr>
          <td class="py-2 px-3">XYZ-5678</td>
          <td class="py-2 px-3">R-08</td>
          <td class="py-2 px-3">East Hub</td>
          <td class="py-2 px-3"><span class="px-2 py-1 rounded bg-yellow-100 text-yellow-700">Pending</span></td>
          <td class="py-2 px-3"><button class="px-2 py-1 border rounded">Approve</button></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>