<div class="mx-1 mt-1 p-6 dark:bg-slate-900 bg-white dark:text-slate-300 rounded-lg">
  <h1 class="text-2xl font-bold mb-2">Validation, Endorsement & Compliance Engine</h1>
  <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">Document verification, LPTRP capacity enforcement, endorsement generation, and compliance workflows.</p>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="p-4 border rounded-lg dark:border-slate-700">
      <h2 class="text-lg font-semibold mb-3">Validate Application</h2>
      <form class="space-y-3">
        <input class="w-full px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Tracking #">
        <input class="w-full px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Franchise Ref">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          <button type="button" class="px-3 py-2 border rounded">Check LPTRP Routes</button>
          <button type="button" class="px-3 py-2 border rounded">Check COOP Registry</button>
          <button type="button" class="px-3 py-2 border rounded">Check Capacity</button>
        </div>
      </form>
      <div class="mt-3 text-sm">Capacity Result: <span class="px-2 py-1 rounded bg-green-100 text-green-700">Within Limit</span></div>
    </div>

    <div class="p-4 border rounded-lg dark:border-slate-700">
      <h2 class="text-lg font-semibold mb-3">Generate Endorsement / Permit</h2>
      <form class="space-y-3">
        <select class="w-full px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700">
          <option>Endorsement to LTFRB</option>
          <option>Local Permit</option>
        </select>
        <input class="w-full px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Issued by">
        <input class="w-full px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Document Ref">
        <select class="w-full px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700">
          <option>Method sent</option>
          <option>API</option>
          <option>Email</option>
          <option>Portal</option>
          <option>Manual</option>
        </select>
        <button type="button" class="px-4 py-2 bg-[#4CAF50] text-white rounded-lg">Generate</button>
      </form>
      <div class="mt-3 text-sm">Fee Receipt: <span class="px-2 py-1 rounded bg-green-100 text-green-700">Paid</span></div>
    </div>
  </div>

  <div class="p-4 border rounded-lg dark:border-slate-700 mt-6">
    <h2 class="text-lg font-semibold mb-3">Compliance</h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="p-3 border rounded dark:border-slate-700">
        <div class="text-sm text-slate-500">Violations (30d)</div>
        <div class="text-2xl font-bold">18</div>
      </div>
      <div class="p-3 border rounded dark:border-slate-700">
        <div class="text-sm text-slate-500">Inspection Failures</div>
        <div class="text-2xl font-bold">3</div>
      </div>
      <div class="p-3 border rounded dark:border-slate-700">
        <div class="text-sm text-slate-500">Active Cases</div>
        <div class="text-2xl font-bold">2</div>
      </div>
    </div>
    <div class="mt-4">
      <button class="px-3 py-2 border rounded">Create Compliance Case</button>
      <button class="ml-2 px-3 py-2 border rounded">Apply Temporary Hold</button>
    </div>
  </div>
</div>