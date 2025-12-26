<div class="mx-1 mt-1 p-6 dark:bg-slate-900 bg-white dark:text-slate-300 rounded-lg">
  <h1 class="text-2xl font-bold mb-2">Vehicle & Ownership Registry</h1>
  <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">Manages PUV master records, OR/CR document storage, ownership details, transfers, and status tracking.</p>

  <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <input class="col-span-1 px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Plate number">
    <select class="col-span-1 px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700">
      <option>Vehicle type</option>
      <option>Jeepney</option>
      <option>UV Express</option>
      <option>E-trike</option>
    </select>
    <select class="col-span-1 px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700">
      <option>Status</option>
      <option>Active</option>
      <option>Suspended</option>
      <option>Deactivated</option>
    </select>
    <button class="px-4 py-2 bg-[#4CAF50] text-white rounded-lg">Search</button>
  </div>

  <div class="overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead>
        <tr class="text-left text-slate-600 dark:text-slate-300">
          <th class="py-2 px-3">Plate</th>
          <th class="py-2 px-3">Type</th>
          <th class="py-2 px-3">Operator</th>
          <th class="py-2 px-3">COOP</th>
          <th class="py-2 px-3">Franchise ID</th>
          <th class="py-2 px-3">Route ID</th>
          <th class="py-2 px-3">Status</th>
          <th class="py-2 px-3">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y dark:divide-slate-700">
        <tr>
          <td class="py-2 px-3">ABC-1234</td>
          <td class="py-2 px-3">Jeepney</td>
          <td class="py-2 px-3">Juan Dela Cruz</td>
          <td class="py-2 px-3">Bayanihan COOP</td>
          <td class="py-2 px-3">FR-000987</td>
          <td class="py-2 px-3">R-12</td>
          <td class="py-2 px-3"><span class="px-2 py-1 rounded bg-green-100 text-green-700">Active</span></td>
          <td class="py-2 px-3 space-x-2">
            <button class="px-2 py-1 border rounded">View</button>
            <button class="px-2 py-1 border rounded">Transfer</button>
            <button class="px-2 py-1 border rounded">Upload Docs</button>
          </td>
        </tr>
        <tr>
          <td class="py-2 px-3">XYZ-5678</td>
          <td class="py-2 px-3">UV Express</td>
          <td class="py-2 px-3">Maria Santos</td>
          <td class="py-2 px-3">United Transport COOP</td>
          <td class="py-2 px-3">FR-001234</td>
          <td class="py-2 px-3">R-08</td>
          <td class="py-2 px-3"><span class="px-2 py-1 rounded bg-yellow-100 text-yellow-700">Suspended</span></td>
          <td class="py-2 px-3 space-x-2">
            <button class="px-2 py-1 border rounded">View</button>
            <button class="px-2 py-1 border rounded">Transfer</button>
            <button class="px-2 py-1 border rounded">Upload Docs</button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-8">
    <div class="p-4 border rounded-lg dark:border-slate-700">
      <h2 class="text-lg font-semibold mb-3">Create Vehicle Record</h2>
      <form class="space-y-3">
        <input class="w-full px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Plate number">
        <input class="w-full px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Vehicle type">
        <input class="w-full px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Operator name">
        <input class="w-full px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Franchise number">
        <input class="w-full px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Route ID">
        <select class="w-full px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700">
          <option>Status</option>
          <option>Active</option>
          <option>Suspended</option>
          <option>Deactivated</option>
        </select>
        <button type="button" class="px-4 py-2 bg-[#4CAF50] text-white rounded-lg">Save</button>
      </form>
    </div>
    <div class="p-4 border rounded-lg dark:border-slate-700">
      <h2 class="text-lg font-semibold mb-3">Upload Documents</h2>
      <form class="space-y-3">
        <div>
          <label class="block text-sm mb-1">OR Document</label>
          <input type="file" class="w-full text-sm">
        </div>
        <div>
          <label class="block text-sm mb-1">CR Document</label>
          <input type="file" class="w-full text-sm">
        </div>
        <div>
          <label class="block text-sm mb-1">Deed of Sale</label>
          <input type="file" class="w-full text-sm">
        </div>
        <button type="button" class="px-4 py-2 bg-[#4CAF50] text-white rounded-lg">Upload</button>
      </form>
    </div>
  </div>

  <div class="p-4 border rounded-lg dark:border-slate-700 mt-8">
    <h2 class="text-lg font-semibold mb-3">Ownership Transfer</h2>
    <form class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <input class="px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Plate number">
      <input class="px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="New operator name">
      <input class="px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Deed of sale reference">
      <button type="button" class="px-4 py-2 bg-[#4CAF50] text-white rounded-lg">Transfer</button>
    </form>
  </div>
</div>