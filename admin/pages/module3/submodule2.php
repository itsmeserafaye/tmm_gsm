<div class="mx-1 mt-1 p-6 dark:bg-slate-900 bg-white dark:text-slate-300 rounded-lg">
  <h1 class="text-2xl font-bold mb-2">Validation, Payment & Compliance</h1>
  <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">Cross-validate ticket data with PUV and Franchise records, monitor payments, and aggregate repeat violations.</p>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="p-4 border rounded-lg dark:border-slate-700">
      <h2 class="text-lg font-semibold mb-3">Validate Ticket</h2>
      <form class="space-y-3">
        <input class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Ticket #">
        <input class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Vehicle plate">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          <button type="button" class="px-3 py-2 border rounded">Check PUV DB</button>
          <button type="button" class="px-3 py-2 border rounded">Check Franchise</button>
          <button type="button" class="px-3 py-2 border rounded">Check Citizen</button>
        </div>
      </form>
      <div class="mt-3 text-sm">Status: <span class="px-2 py-1 rounded bg-green-100 text-green-700">Validated</span></div>
    </div>

    <div class="p-4 border rounded-lg dark:border-slate-700">
      <h2 class="text-lg font-semibold mb-3">Payment Processing</h2>
      <form class="space-y-3">
        <input class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Fine amount">
        <input class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Due date">
        <input class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Receipt ref">
        <button type="button" class="px-4 py-2 bg-[#4CAF50] text-white rounded">Mark Paid</button>
      </form>
      <div class="mt-3 text-sm">Treasury: <span class="px-2 py-1 rounded bg-green-100 text-green-700">Verified</span></div>
    </div>
  </div>

  <div class="p-4 border rounded-lg dark:border-slate-700 mt-6">
    <h2 class="text-lg font-semibold mb-3">Compliance Summary</h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="p-3 border rounded dark:border-slate-700">
        <div class="text-sm text-slate-500">Violations (30d)</div>
        <div class="text-2xl font-bold">27</div>
      </div>
      <div class="p-3 border rounded dark:border-slate-700">
        <div class="text-sm text-slate-500">Repeat Offenders</div>
        <div class="text-2xl font-bold">6</div>
      </div>
      <div class="p-3 border rounded dark:border-slate-700">
        <div class="text-sm text-slate-500">Escalations</div>
        <div class="text-2xl font-bold">3</div>
      </div>
    </div>
    <div class="mt-4">
      <button class="px-3 py-2 border rounded">Notify Franchise</button>
      <button class="ml-2 px-3 py-2 border rounded">Create Case</button>
    </div>
  </div>
</div>