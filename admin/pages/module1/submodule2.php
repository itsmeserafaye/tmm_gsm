<div class="mx-1 mt-1 p-6 dark:bg-slate-900 bg-white dark:text-slate-300 rounded-lg">
  <h1 class="text-2xl font-bold mb-2">Operator, Cooperative & Franchise Validation</h1>
  <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">Maintains operator and cooperative profiles and validates franchise references through cross-checks with Franchise Management.</p>

  <div class="p-4 border rounded-lg dark:border-slate-700 mb-6">
    <h2 class="text-lg font-semibold mb-3">Franchise Lookup</h2>
    <form class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <input class="px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Franchise ID">
      <input class="px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Operator name">
      <button type="button" class="px-4 py-2 bg-orange-500 text-white rounded-lg">Validate</button>
    </form>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="p-4 border rounded-lg dark:border-slate-700">
      <h3 class="text-md font-semibold mb-2">Franchise Details</h3>
      <div class="space-y-1 text-sm">
        <div><span class="font-semibold">ID:</span> FR-001234</div>
        <div><span class="font-semibold">Operator:</span> Maria Santos</div>
        <div><span class="font-semibold">COOP:</span> United Transport COOP</div>
        <div><span class="font-semibold">Validity:</span> 2025-12-31</div>
        <div><span class="font-semibold">Status:</span> <span class="px-2 py-1 rounded bg-green-100 text-green-700">Valid</span></div>
      </div>
    </div>

    <div class="p-4 border rounded-lg dark:border-slate-700">
      <h3 class="text-md font-semibold mb-2">Operator & Cooperative</h3>
      <div class="space-y-1 text-sm">
        <div><span class="font-semibold">Operator ID:</span> OP-789</div>
        <div><span class="font-semibold">Full Name:</span> Maria Santos</div>
        <div><span class="font-semibold">Contact:</span> 0917-000-0000</div>
        <div><span class="font-semibold">Cooperative:</span> United Transport COOP</div>
        <div><span class="font-semibold">LGU Approval:</span> <span class="px-2 py-1 rounded bg-blue-100 text-blue-700">APP-2024-3321</span></div>
      </div>
      <div class="mt-3">
        <button class="px-4 py-2 border rounded">Link to Vehicle</button>
      </div>
    </div>
  </div>

  <div class="p-4 border rounded-lg dark:border-slate-700 mt-6">
    <h3 class="text-md font-semibold mb-2">Validation Rules</h3>
    <ul class="list-disc ml-6 text-sm">
      <li>Only LGU-verified franchises are stored.</li>
      <li>Franchise must match LTFRB-issued reference.</li>
      <li>COOP without LGU approval cannot register vehicles.</li>
    </ul>
  </div>
</div>