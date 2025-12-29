<?php require_once __DIR__ . '/../../includes/db.php'; $db = db(); ?>
<div class="mx-1 mt-1 p-4 md:p-6 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 rounded-lg">
  <h1 class="text-2xl font-bold mb-2">Traffic Violation & Ticketing — Overview</h1>
  <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">Digital enforcement and citation management aligned with MMDA STS, with LGU workflows for issuance, payment, compliance, and reporting.</p>
  <?php
    $pending = $db->query("SELECT COUNT(*) AS c FROM tickets WHERE status='Pending'")->fetch_assoc()['c'] ?? 0;
    $settled = $db->query("SELECT COUNT(*) AS c FROM tickets WHERE status='Settled'")->fetch_assoc()['c'] ?? 0;
    $escalated = $db->query("SELECT COUNT(*) AS c FROM tickets WHERE status='Escalated'")->fetch_assoc()['c'] ?? 0;
  ?>
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="p-6 bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm relative overflow-hidden group border-t-4 border-t-blue-500">
      <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
        <i data-lucide="file-search" class="w-16 h-16 text-blue-500"></i>
      </div>
      <div class="relative z-10">
      <div class="text-sm text-slate-500">Pending Validation</div>
      <div class="text-3xl font-bold"><?php echo (int)$pending; ?></div>
      </div>
    </div>
    <div class="p-6 bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm relative overflow-hidden group border-t-4 border-t-emerald-500">
      <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
        <i data-lucide="check-circle-2" class="w-16 h-16 text-emerald-500"></i>
      </div>
      <div class="relative z-10">
      <div class="text-sm text-slate-500">Settled Tickets</div>
      <div class="text-3xl font-bold"><?php echo (int)$settled; ?></div>
      </div>
    </div>
    <div class="p-6 bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm relative overflow-hidden group border-t-4 border-t-red-500">
      <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
        <i data-lucide="alert-triangle" class="w-16 h-16 text-red-500"></i>
      </div>
      <div class="relative z-10">
      <div class="text-sm text-slate-500">Escalated Cases</div>
      <div class="text-3xl font-bold"><?php echo (int)$escalated; ?></div>
      </div>
    </div>
  </div>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
    <div class="p-4 border rounded-lg ring-1 ring-slate-200 dark:ring-slate-700 bg-white dark:bg-slate-900 border-t-4 border-t-blue-500">
      <h2 class="text-lg font-semibold mb-3 flex items-center gap-2"><i data-lucide="list-todo" class="w-5 h-5 text-blue-500"></i> Work Queue</h2>
      <ul class="text-sm space-y-2">
        <?php
          $res = $db->query("SELECT ticket_number, vehicle_plate, violation_code FROM tickets WHERE status='Pending' ORDER BY date_issued ASC LIMIT 5");
          if ($res && $res->num_rows > 0):
            while ($r = $res->fetch_assoc()):
        ?>
        <li class="flex items-center justify-between"><span><?php echo htmlspecialchars($r['ticket_number']); ?> • <?php echo htmlspecialchars($r['vehicle_plate']); ?> • <?php echo htmlspecialchars($r['violation_code']); ?></span><a href="?page=module3/submodule1&q=<?php echo urlencode($r['ticket_number']); ?>" class="px-2 py-1 border rounded">Open</a></li>
        <?php endwhile; else: ?>
        <li class="text-slate-500">No pending tickets.</li>
        <?php endif; ?>
      </ul>
    </div>
    <div class="p-4 border rounded-lg ring-1 ring-slate-200 dark:ring-slate-700 bg-white dark:bg-slate-900 border-t-4 border-t-amber-500">
      <h2 class="text-lg font-semibold mb-3 flex items-center gap-2"><i data-lucide="zap" class="w-5 h-5 text-amber-500"></i> Quick Actions</h2>
      <div class="grid grid-cols-1 gap-3">
        <a href="?page=module3/submodule1" class="flex items-center justify-between p-4 border border-slate-200 dark:border-slate-700 rounded-lg hover:border-green-500 dark:hover:border-green-500 hover:shadow-md transition-all group">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-green-100 dark:bg-green-900/30 rounded-lg text-green-600 dark:text-green-400 group-hover:bg-green-600 group-hover:text-white transition-colors">
              <i data-lucide="ticket" class="w-5 h-5"></i>
            </div>
            <div>
              <div class="font-medium text-slate-800 dark:text-slate-200">New Ticket</div>
              <div class="text-xs text-slate-500">Create violation record</div>
            </div>
          </div>
          <i data-lucide="chevron-right" class="w-4 h-4 text-slate-400 group-hover:text-green-500"></i>
        </a>
        <a href="?page=module3/submodule2" class="flex items-center justify-between p-4 border border-slate-200 dark:border-slate-700 rounded-lg hover:border-blue-500 dark:hover:border-blue-500 hover:shadow-md transition-all group">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg text-blue-600 dark:text-blue-400 group-hover:bg-blue-600 group-hover:text-white transition-colors">
              <i data-lucide="search-check" class="w-5 h-5"></i>
            </div>
            <div>
              <div class="font-medium text-slate-800 dark:text-slate-200">Validate & Payment</div>
              <div class="text-xs text-slate-500">Check records & record payment</div>
            </div>
          </div>
          <i data-lucide="chevron-right" class="w-4 h-4 text-slate-400 group-hover:text-blue-500"></i>
        </a>
        <a href="?page=module3/submodule3" class="flex items-center justify-between p-4 border border-slate-200 dark:border-slate-700 rounded-lg hover:border-indigo-500 dark:hover:border-indigo-500 hover:shadow-md transition-all group">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-indigo-100 dark:bg-indigo-900/30 rounded-lg text-indigo-600 dark:text-indigo-400 group-hover:bg-indigo-600 group-hover:text-white transition-colors">
              <i data-lucide="bar-chart-3" class="w-5 h-5"></i>
            </div>
            <div>
              <div class="font-medium text-slate-800 dark:text-slate-200">Analytics</div>
              <div class="text-xs text-slate-500">Reports & integrations</div>
            </div>
          </div>
          <i data-lucide="chevron-right" class="w-4 h-4 text-slate-400 group-hover:text-indigo-500"></i>
        </a>
      </div>
    </div>
  </div>
</div>
