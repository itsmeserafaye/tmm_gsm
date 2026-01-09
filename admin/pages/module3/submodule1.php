<div class="mx-1 mt-1 p-6 dark:bg-slate-900 bg-white dark:text-slate-300 rounded-lg">
  <h1 class="text-2xl font-bold mb-2">Violation Logging & Ticket Processing</h1>
  <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">On-site and automated violation recording, ticket generation, evidence attachment, and initial case creation.</p>

  <div class="p-4 border rounded-lg dark:border-slate-700 mb-6">
    <h2 class="text-lg font-semibold mb-3">Create Ticket</h2>
    <form class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <select class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700"><option>Violation code</option><option>Illegal Parking</option><option>No Loading Zone</option><option>Disregarding Traffic Signs</option></select>
      <input class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Vehicle plate">
      <input class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Driver/Operator name">
      <input class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Location">
      <input type="datetime-local" class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700">
      <div class="md:col-span-3 grid grid-cols-1 md:grid-cols-3 gap-4">
        <div><label class="block text-sm mb-1">Photo</label><input type="file" class="w-full text-sm"></div>
        <div><label class="block text-sm mb-1">Video</label><input type="file" class="w-full text-sm"></div>
        <div><label class="block text-sm mb-1">Notes</label><input class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Additional info"></div>
      </div>
      <button type="button" class="md:col-span-3 px-4 py-2 bg-[#4CAF50] text-white rounded">Generate Ticket</button>
    </form>
  </div>

  <div class="overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead>
        <tr class="text-left text-slate-600 dark:text-slate-300">
          <th class="py-2 px-3">Ticket #</th>
          <th class="py-2 px-3">Violation</th>
          <th class="py-2 px-3">Plate</th>
          <th class="py-2 px-3">Issued By</th>
          <th class="py-2 px-3">Status</th>
          <th class="py-2 px-3">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y dark:divide-slate-700">
        <tr>
          <td class="py-2 px-3">TCK-2025-0101</td>
          <td class="py-2 px-3">Illegal Parking</td>
          <td class="py-2 px-3">ABC-1234</td>
          <td class="py-2 px-3">Officer Dela Cruz</td>
          <td class="py-2 px-3"><span class="px-2 py-1 rounded bg-yellow-100 text-yellow-700">Pending</span></td>
          <td class="py-2 px-3 space-x-2"><button class="px-2 py-1 border rounded">Open</button><button class="px-2 py-1 border rounded">Validate</button></td>
        </tr>
        <tr>
          <td class="py-2 px-3">TCK-2025-0102</td>
          <td class="py-2 px-3">Disregarding Traffic Signs</td>
          <td class="py-2 px-3">XYZ-5678</td>
          <td class="py-2 px-3">Officer Santos</td>
          <td class="py-2 px-3"><span class="px-2 py-1 rounded bg-green-100 text-green-700">Settled</span></td>
          <td class="py-2 px-3 space-x-2"><button class="px-2 py-1 border rounded">Open</button><button class="px-2 py-1 border rounded">Receipt</button></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>