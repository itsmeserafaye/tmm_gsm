<div class="mx-1 mt-1 p-6 dark:bg-slate-900 bg-white dark:text-slate-300 rounded-lg">
  <h1 class="text-2xl font-bold mb-2">Inspection Execution & Certification</h1>
  <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">Use checklist to record inspection findings, attach photos, and issue city inspection certificates.</p>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="p-4 border rounded-lg dark:border-slate-700">
      <h2 class="text-lg font-semibold mb-3">Checklist</h2>
      <div class="space-y-2 text-sm">
        <div class="flex items-center justify-between"><span>Lights & Horn</span><select class="border rounded px-2 py-1"><option>Pass</option><option>Fail</option><option>N/A</option></select></div>
        <div class="flex items-center justify-between"><span>Brakes</span><select class="border rounded px-2 py-1"><option>Pass</option><option>Fail</option><option>N/A</option></select></div>
        <div class="flex items-center justify-between"><span>Emission & Smoke Test</span><select class="border rounded px-2 py-1"><option>Pass</option><option>Fail</option><option>N/A</option></select></div>
        <div class="flex items-center justify-between"><span>Tires & Wipers</span><select class="border rounded px-2 py-1"><option>Pass</option><option>Fail</option><option>N/A</option></select></div>
        <div class="flex items-center justify-between"><span>Interior Safety</span><select class="border rounded px-2 py-1"><option>Pass</option><option>Fail</option><option>N/A</option></select></div>
        <div class="flex items-center justify-between"><span>Documents & Plate</span><select class="border rounded px-2 py-1"><option>Pass</option><option>Fail</option><option>N/A</option></select></div>
      </div>
      <div class="mt-3">
        <label class="block text-sm mb-1">Photos</label>
        <input type="file" class="w-full text-sm">
      </div>
    </div>
    <div class="p-4 border rounded-lg dark:border-slate-700">
      <h2 class="text-lg font-semibold mb-3">Result & Certificate</h2>
      <form class="space-y-3">
        <select class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700"><option>Passed</option><option>Failed</option><option>Pending</option><option>For Reinspection</option></select>
        <input class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Remarks">
        <button type="button" class="px-4 py-2 bg-[#4CAF50] text-white rounded">Generate Certificate</button>
      </form>
      <div class="mt-3 text-sm">Certificate: <span class="px-2 py-1 rounded bg-green-100 text-green-700">CERT-2025-8801</span></div>
    </div>
  </div>
</div>