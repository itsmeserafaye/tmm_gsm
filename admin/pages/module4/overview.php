<div class="mx-1 mt-1 p-4 md:p-6 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 rounded-lg shadow-sm">
  <h1 class="text-2xl font-bold mb-2">Vehicle Inspection & Registration — Overview</h1>
  <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">Manages inspection scheduling, checklist execution, certification issuance, and LPTRP-aligned route validation.</p>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="p-4 rounded-lg shadow-sm bg-gradient-to-br from-[#4CAF50]/10 to-[#4A90E2]/10 border border-[#4CAF50]/20 transition-all hover:shadow-md">
      <div class="text-xs text-slate-600 dark:text-slate-300 uppercase tracking-wide font-semibold">Pending Verification</div>
      <div class="text-3xl font-bold text-slate-800 dark:text-slate-100 mt-1">6</div>
    </div>
    <div class="p-4 rounded-lg shadow-sm bg-gradient-to-br from-[#FDA811]/10 to-[#4A90E2]/10 border border-[#FDA811]/20 transition-all hover:shadow-md">
      <div class="text-xs text-slate-600 dark:text-slate-300 uppercase tracking-wide font-semibold">Scheduled Inspections</div>
      <div class="text-3xl font-bold text-slate-800 dark:text-slate-100 mt-1">18</div>
    </div>
    <div class="p-4 rounded-lg shadow-sm bg-gradient-to-br from-[#4CAF50]/10 to-[#4A90E2]/10 border border-[#4CAF50]/20 transition-all hover:shadow-md">
      <div class="text-xs text-slate-600 dark:text-slate-300 uppercase tracking-wide font-semibold">Certificates Issued</div>
      <div class="text-3xl font-bold text-slate-800 dark:text-slate-100 mt-1">102</div>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
    <div class="p-6 border rounded-lg dark:border-slate-700 bg-white dark:bg-slate-900 border-t-4 border-t-blue-500 shadow-sm hover:shadow-md transition-shadow">
      <h2 class="text-lg font-semibold mb-4 flex items-center gap-2 text-slate-800 dark:text-slate-100">
        <i data-lucide="list-todo" class="w-5 h-5 text-blue-500"></i> Work Queue
      </h2>
      <ul class="text-sm space-y-3">
        <li class="flex items-center justify-between p-3 bg-slate-50 dark:bg-slate-800/50 rounded-lg border border-slate-100 dark:border-slate-700/50">
          <div class="flex items-center gap-3">
            <span class="w-2 h-2 rounded-full bg-yellow-500"></span>
            <span class="font-medium">ABC-1234 • CR/OR Verification</span>
          </div>
          <a href="?page=module4/submodule1" class="px-3 py-1 text-xs font-medium text-blue-600 bg-blue-50 hover:bg-blue-100 dark:bg-blue-900/20 dark:text-blue-400 dark:hover:bg-blue-900/40 rounded-full transition-colors">Open</a>
        </li>
        <li class="flex items-center justify-between p-3 bg-slate-50 dark:bg-slate-800/50 rounded-lg border border-slate-100 dark:border-slate-700/50">
          <div class="flex items-center gap-3">
            <span class="w-2 h-2 rounded-full bg-blue-500"></span>
            <span class="font-medium">XYZ-5678 • Schedule Inspection</span>
          </div>
          <a href="?page=module4/submodule1" class="px-3 py-1 text-xs font-medium text-blue-600 bg-blue-50 hover:bg-blue-100 dark:bg-blue-900/20 dark:text-blue-400 dark:hover:bg-blue-900/40 rounded-full transition-colors">Open</a>
        </li>
        <li class="flex items-center justify-between p-3 bg-slate-50 dark:bg-slate-800/50 rounded-lg border border-slate-100 dark:border-slate-700/50">
          <div class="flex items-center gap-3">
            <span class="w-2 h-2 rounded-full bg-green-500"></span>
            <span class="font-medium">AAA-9999 • Certificate Generation</span>
          </div>
          <a href="?page=module4/submodule2" class="px-3 py-1 text-xs font-medium text-blue-600 bg-blue-50 hover:bg-blue-100 dark:bg-blue-900/20 dark:text-blue-400 dark:hover:bg-blue-900/40 rounded-full transition-colors">Open</a>
        </li>
      </ul>
    </div>
    <div class="p-6 border rounded-lg dark:border-slate-700 bg-white dark:bg-slate-900 border-t-4 border-t-green-500 shadow-sm hover:shadow-md transition-shadow">
      <h2 class="text-lg font-semibold mb-4 flex items-center gap-2 text-slate-800 dark:text-slate-100">
        <i data-lucide="zap" class="w-5 h-5 text-green-500"></i> Quick Actions
      </h2>
      <div class="grid grid-cols-1 gap-3">
        <a href="?page=module4/submodule1" class="flex items-center justify-between p-4 border rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-green-100 dark:bg-green-900/30 text-green-600 rounded-lg group-hover:bg-green-200 dark:group-hover:bg-green-900/50 transition-colors">
              <i data-lucide="calendar-check" class="w-5 h-5"></i>
            </div>
            <div>
              <div class="font-medium text-slate-800 dark:text-slate-200">Verify & Schedule</div>
              <div class="text-xs text-slate-500">Document check & appointments</div>
            </div>
          </div>
          <i data-lucide="chevron-right" class="w-4 h-4 text-slate-400"></i>
        </a>
        <a href="?page=module4/submodule2" class="flex items-center justify-between p-4 border rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-blue-100 dark:bg-blue-900/30 text-blue-600 rounded-lg group-hover:bg-blue-200 dark:group-hover:bg-blue-900/50 transition-colors">
              <i data-lucide="clipboard-check" class="w-5 h-5"></i>
            </div>
            <div>
              <div class="font-medium text-slate-800 dark:text-slate-200">Run Checklist</div>
              <div class="text-xs text-slate-500">Execute inspection items</div>
            </div>
          </div>
          <i data-lucide="chevron-right" class="w-4 h-4 text-slate-400"></i>
        </a>
        <a href="?page=module4/submodule3" class="flex items-center justify-between p-4 border rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-purple-100 dark:bg-purple-900/30 text-purple-600 rounded-lg group-hover:bg-purple-200 dark:group-hover:bg-purple-900/50 transition-colors">
              <i data-lucide="map" class="w-5 h-5"></i>
            </div>
            <div>
              <div class="font-medium text-slate-800 dark:text-slate-200">Route Validation</div>
              <div class="text-xs text-slate-500">Check capacity & compliance</div>
            </div>
          </div>
          <i data-lucide="chevron-right" class="w-4 h-4 text-slate-400"></i>
        </a>
      </div>
    </div>
  </div>
</div>