<?php
  require_once __DIR__ . '/../../includes/db.php';
  $db = db();
?>
<div class="mx-1 mt-1 p-4 md:p-6 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 rounded-lg">
  <h1 class="text-2xl font-bold mb-2">Renewals, Monitoring & Reporting</h1>
  <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">Tracks validity, renewal schedules, compliance history, and generates management reports.</p>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <!-- Renewal Reminders -->
    <div class="p-6 border rounded-lg ring-1 ring-slate-200 dark:ring-slate-700 bg-white dark:bg-slate-900 border-t-4 border-t-purple-500 shadow-sm">
      <h2 class="text-lg font-semibold mb-4 flex items-center gap-2"><i data-lucide="calendar-clock" class="w-5 h-5 text-purple-500"></i> Upcoming Renewals</h2>
      <div class="space-y-3">
         <!-- Static for now, would be dynamic in production -->
         <div class="flex items-center justify-between p-3 bg-slate-50 dark:bg-slate-800 rounded-lg">
            <div>
              <div class="font-medium text-sm">United Transport COOP</div>
              <div class="text-xs text-slate-500">Expires: 2026-01-15</div>
            </div>
            <button class="px-3 py-1 text-xs bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded hover:bg-slate-50 transition-colors">Notify</button>
         </div>
         <div class="flex items-center justify-between p-3 bg-slate-50 dark:bg-slate-800 rounded-lg">
            <div>
              <div class="font-medium text-sm">Bayanihan COOP</div>
              <div class="text-xs text-slate-500">Expires: 2025-12-30</div>
            </div>
            <button class="px-3 py-1 text-xs bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded hover:bg-slate-50 transition-colors">Notify</button>
         </div>
      </div>
    </div>

    <!-- Reporting -->
    <div class="p-6 border rounded-lg ring-1 ring-slate-200 dark:ring-slate-700 bg-white dark:bg-slate-900 border-t-4 border-t-blue-500 shadow-sm">
      <h2 class="text-lg font-semibold mb-4 flex items-center gap-2"><i data-lucide="bar-chart-3" class="w-5 h-5 text-blue-500"></i> Generate Reports</h2>
      <form class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
           <div>
             <label class="block text-xs font-medium text-slate-500 mb-1">Period</label>
             <select class="w-full px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700"><option>Last 30 Days</option><option>Last Quarter</option><option>YTD</option></select>
           </div>
           <div>
             <label class="block text-xs font-medium text-slate-500 mb-1">Status</label>
             <select class="w-full px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700"><option>All Statuses</option><option>Endorsed</option><option>Pending</option></select>
           </div>
        </div>
        <button type="button" class="flex items-center justify-center gap-2 px-6 py-2.5 bg-blue-500 hover:bg-blue-600 text-white font-medium rounded-lg w-full transition-colors shadow-sm shadow-blue-500/30">
          <span>Download PDF Report</span>
          <i data-lucide="download" class="w-4 h-4"></i>
        </button>
      </form>
    </div>
  </div>

  <!-- Endorsement History -->
  <div class="mt-8 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
    <div class="p-4 border-b border-slate-200 dark:border-slate-700">
      <h2 class="font-semibold text-slate-800 dark:text-slate-100 flex items-center gap-2">
        <i data-lucide="history" class="w-5 h-5 text-slate-400"></i>
        Endorsement History
      </h2>
    </div>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700">
          <tr class="text-left text-slate-600 dark:text-slate-400">
            <th class="py-3 px-4 font-semibold uppercase tracking-wider text-xs">Endorsement ID</th>
            <th class="py-3 px-4 font-semibold uppercase tracking-wider text-xs">App ID</th>
            <th class="py-3 px-4 font-semibold uppercase tracking-wider text-xs">Issued Date</th>
            <th class="py-3 px-4 font-semibold uppercase tracking-wider text-xs">Permit #</th>
            <th class="py-3 px-4 font-semibold uppercase tracking-wider text-xs">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
          <?php
            $res = $db->query("SELECT * FROM endorsement_records ORDER BY created_at DESC LIMIT 10");
            if($res->num_rows > 0):
              while($r = $res->fetch_assoc()):
          ?>
          <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
            <td class="py-3 px-4 text-slate-900 dark:text-slate-100">END-<?php echo $r['endorsement_id']; ?></td>
            <td class="py-3 px-4 text-slate-600 dark:text-slate-400">APP-<?php echo $r['application_id']; ?></td>
            <td class="py-3 px-4 text-slate-600 dark:text-slate-400"><?php echo $r['issued_date']; ?></td>
            <td class="py-3 px-4 font-mono text-slate-600 dark:text-slate-400"><?php echo htmlspecialchars($r['permit_number']); ?></td>
            <td class="py-3 px-4">
              <button class="text-blue-600 hover:underline">View</button>
            </td>
          </tr>
          <?php endwhile; else: ?>
          <tr><td colspan="5" class="py-8 text-center text-slate-500 italic">No endorsements issued yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
