<div class="mx-1 mt-1 p-6 dark:bg-slate-900 bg-white dark:text-slate-300 rounded-lg">
  <h1 class="text-2xl font-bold mb-2">Parking Fees, Enforcement & Analytics</h1>
  <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">Records parking and terminal usage fees, synchronizes payment confirmations with the City Treasury, and links violations or unauthorized parking incidents to the Traffic Violation & Ticketing System. Generates utilization, revenue, and compliance analytics to support LPTRP enforcement and transport planning.</p>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="p-4 border rounded-lg dark:border-slate-700">
      <h2 class="text-lg font-semibold mb-3">Fee Charges & Payments</h2>
      <form class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <input class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Terminal ID">
        <input class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Amount">
        <select class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700"><option>Charge Type</option><option>Permit Fee</option><option>Usage Fee</option><option>Stall Rent</option></select>
        <input class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Due date">
        <button type="button" class="md:col-span-2 px-4 py-2 bg-[#4CAF50] text-white rounded">Create Charge</button>
      </form>
      <div class="mt-3 text-sm">Receipt: <span class="px-2 py-1 rounded bg-green-100 text-green-700">REC-2025-5501</span></div>
    </div>

    <div class="p-4 border rounded-lg dark:border-slate-700">
      <h2 class="text-lg font-semibold mb-3">Enforcement & Violations</h2>
      <form class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <input class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Incident ID">
        <input class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Vehicle plate">
        <button type="button" class="px-4 py-2 bg-[#4CAF50] text-white rounded">Create Ticket</button>
      </form>
      <div class="mt-3 text-sm">Ticket: <span class="px-2 py-1 rounded bg-yellow-100 text-yellow-700">TCK-2025-2201</span></div>
    </div>
  </div>

  <div class="p-4 border rounded-lg dark:border-slate-700 mt-6">
    <h2 class="text-lg font-semibold mb-3">Reconciliation & Analytics</h2>
    <form class="grid grid-cols-1 md:grid-cols-3 gap-3">
      <input class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Date (YYYY-MM-DD)">
      <select class="px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700"><option>Terminal</option><option>Central Terminal</option><option>East Hub</option></select>
      <button type="button" class="px-4 py-2 bg-[#4CAF50] text-white rounded">Reconcile</button>
    </form>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
      <div class="p-3 border rounded dark:border-slate-700"><div class="text-sm text-slate-500">Total Fees</div><div class="text-2xl font-bold">₱52,300</div></div>
      <div class="p-3 border rounded dark:border-slate-700"><div class="text-sm text-slate-500">Receipts Verified</div><div class="text-2xl font-bold">96%</div></div>
      <div class="p-3 border rounded dark:border-slate-700"><div class="text-sm text-slate-500">Incidents</div><div class="text-2xl font-bold">11</div></div>
    </div>
  </div>
</div>