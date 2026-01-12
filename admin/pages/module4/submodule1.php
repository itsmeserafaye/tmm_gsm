<div class="mx-1 mt-1 p-6 dark:bg-slate-900 bg-white dark:text-slate-300 rounded-lg">
  <h1 class="text-2xl font-bold mb-2">Vehicle Verification & Inspection Scheduling</h1>
  <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">Upload and verify CR/OR, check PUV and Franchise records, and schedule inspections with authorized inspectors.</p>

  <div class="p-4 border rounded-lg dark:border-slate-700 mb-6">
    <h2 class="text-lg font-semibold mb-3">LTO Document Upload & Verification</h2>
    <form class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <input class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Plate number">
      <input class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Operator ID">
      <div></div>
      <div>
        <label class="block text-sm mb-1">CR Document</label>
        <input type="file" class="w-full text-sm">
      </div>
      <div>
        <label class="block text-sm mb-1">OR Document</label>
        <input type="file" class="w-full text-sm">
      </div>
      <div class="md:col-span-1">
        <button type="button" class="px-4 py-2 bg-[#4CAF50] text-white rounded">Verify</button>
      </div>
    </form>
    <div class="mt-3 text-sm">Verification: <span class="px-2 py-1 rounded bg-green-100 text-green-700">Verified</span></div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="p-4 border rounded-lg dark:border-slate-700">
      <h2 class="text-lg font-semibold mb-3">Cross-Checks</h2>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <button class="px-3 py-2 border rounded">GET /vehicle/{plate}</button>
        <button class="px-3 py-2 border rounded">GET /franchise/{operator}</button>
        <button class="px-3 py-2 border rounded">POST /auth/validateInspector</button>
      </div>
    </div>
    <div class="p-4 border rounded-lg dark:border-slate-700">
      <h2 class="text-lg font-semibold mb-3">Inspection Scheduling</h2>
      <form class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <input type="datetime-local" class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700">
        <input class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Location">
        <select class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700"><option>Inspector</option><option>Inspector Dela Cruz</option><option>Inspector Santos</option></select>
        <button type="button" class="px-4 py-2 bg-[#4CAF50] text-white rounded">Schedule</button>
      </form>
      <div class="mt-3 text-sm">Schedule: <span class="px-2 py-1 rounded bg-blue-100 text-blue-700">2025-12-30 10:00</span></div>
    </div>
  </div>
</div>
