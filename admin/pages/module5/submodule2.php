<div class="mx-1 mt-1 p-6 dark:bg-slate-900 bg-white dark:text-slate-300 rounded-lg">
  <h1 class="text-2xl font-bold mb-2">Parking and Terminal Operations & Vehicle Enrollment</h1>
  <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">Handles the enrollment of operators and vehicles for authorized parking spaces and terminals. Manages daily operations such as entry/exit logging, bay or terminal assignment, occupancy monitoring, and incident reporting within LGU-managed facilities.</p>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="p-4 border rounded-lg dark:border-slate-700">
      <h2 class="text-lg font-semibold mb-3">Operator & Vehicle Enrollment</h2>
      <form class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <input class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Terminal ID">
        <input class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Operator ID">
        <div class="md:col-span-2"><label class="block text-sm mb-1">Vehicle list</label><input type="file" class="w-full text-sm"></div>
        <button type="button" class="md:col-span-2 px-4 py-2 bg-[#4CAF50] text-white rounded">Enroll</button>
      </form>
    </div>

    <div class="p-4 border rounded-lg dark:border-slate-700">
      <h2 class="text-lg font-semibold mb-3">Daily Logs</h2>
      <form class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <input class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Vehicle plate">
        <input type="datetime-local" class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700">
        <select class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700"><option>Activity</option><option>Entry</option><option>Exit</option><option>Unload</option></select>
        <button type="button" class="px-4 py-2 bg-[#4CAF50] text-white rounded">Record</button>
      </form>
    </div>
  </div>

  <div class="overflow-x-auto mt-6">
    <table class="min-w-full text-sm">
      <thead>
        <tr class="text-left text-slate-600 dark:text-slate-300">
          <th class="py-2 px-3">Time</th>
          <th class="py-2 px-3">Plate</th>
          <th class="py-2 px-3">Activity</th>
          <th class="py-2 px-3">Remarks</th>
        </tr>
      </thead>
      <tbody class="divide-y dark:divide-slate-700">
        <tr>
          <td class="py-2 px-3">08:03</td>
          <td class="py-2 px-3">ABC-1234</td>
          <td class="py-2 px-3">Entry</td>
          <td class="py-2 px-3">—</td>
        </tr>
        <tr>
          <td class="py-2 px-3">08:35</td>
          <td class="py-2 px-3">XYZ-5678</td>
          <td class="py-2 px-3">Unload</td>
          <td class="py-2 px-3">Minor delay</td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="p-4 border rounded-lg dark:border-slate-700 mt-6">
    <h2 class="text-lg font-semibold mb-3">Incident Reporting</h2>
    <form class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <input class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Vehicle plate">
      <input class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Incident type">
      <input class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Remarks">
      <div class="md:col-span-3"><label class="block text-sm mb-1">Evidence</label><input type="file" class="w-full text-sm"></div>
      <button type="button" class="md:col-span-3 px-4 py-2 bg-[#4CAF50] text-white rounded">Submit Incident</button>
    </form>
  </div>
</div>