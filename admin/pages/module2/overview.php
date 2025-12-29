<div class="mx-1 mt-1 p-4 md:p-6 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 rounded-lg">
  <h1 class="text-2xl font-bold mb-2">Franchise Management — Overview</h1>
  <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">Handles LGU-level franchise endorsement workflows, permit issuance, validations, and monitoring aligned with LPTRP policies.</p>
  
  <?php 
    require_once __DIR__ . '/../../includes/db.php'; 
    $db = db(); 
    
    // Fetch real counts
    $total = $db->query("SELECT COUNT(*) as c FROM franchise_applications")->fetch_assoc()['c'] ?? 0;
    $endorsed = $db->query("SELECT COUNT(*) as c FROM franchise_applications WHERE status='Endorsed'")->fetch_assoc()['c'] ?? 0;
    $pending = $db->query("SELECT COUNT(*) as c FROM franchise_applications WHERE status='Pending' OR status='Under Review'")->fetch_assoc()['c'] ?? 0;
  ?>

  <!-- Summary Cards -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="p-6 bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm relative overflow-hidden group">
      <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
        <i data-lucide="file-stack" class="w-16 h-16 text-blue-500"></i>
      </div>
      <div class="relative z-10">
        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Pending Applications</p>
        <h3 class="text-3xl font-bold text-slate-800 dark:text-slate-100 mt-1"><?php echo $pending; ?></h3>
        <div class="flex items-center mt-2 text-xs text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30 w-fit px-2 py-1 rounded-full">
          <i data-lucide="clock" class="w-3 h-3 mr-1"></i>
          <span>Awaiting Review</span>
        </div>
      </div>
    </div>

    <div class="p-6 bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm relative overflow-hidden group">
      <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
        <i data-lucide="check-circle-2" class="w-16 h-16 text-emerald-500"></i>
      </div>
      <div class="relative z-10">
        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Endorsed / Permits</p>
        <h3 class="text-3xl font-bold text-slate-800 dark:text-slate-100 mt-1"><?php echo $endorsed; ?></h3>
        <div class="flex items-center mt-2 text-xs text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/30 w-fit px-2 py-1 rounded-full">
          <i data-lucide="badge-check" class="w-3 h-3 mr-1"></i>
          <span>Official</span>
        </div>
      </div>
    </div>

    <div class="p-6 bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm relative overflow-hidden group">
      <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
        <i data-lucide="alert-triangle" class="w-16 h-16 text-amber-500"></i>
      </div>
      <div class="relative z-10">
        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Compliance Cases</p>
        <?php 
          $cases = $db->query("SELECT COUNT(*) as c FROM compliance_cases WHERE status='Open'")->fetch_assoc()['c'] ?? 0;
        ?>
        <h3 class="text-3xl font-bold text-slate-800 dark:text-slate-100 mt-1"><?php echo $cases; ?></h3>
        <div class="flex items-center mt-2 text-xs text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/30 w-fit px-2 py-1 rounded-full">
          <i data-lucide="gavel" class="w-3 h-3 mr-1"></i>
          <span>Active Issues</span>
        </div>
      </div>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
    <div class="p-6 border rounded-lg dark:border-slate-700 bg-white dark:bg-slate-900 shadow-sm">
      <h2 class="text-lg font-semibold mb-4 flex items-center gap-2"><i data-lucide="list-todo" class="w-5 h-5 text-slate-500"></i> Work Queue</h2>
      <ul class="space-y-3">
        <?php
          $q = $db->query("SELECT fa.franchise_ref_number, o.full_name, fa.submitted_at FROM franchise_applications fa JOIN operators o ON fa.operator_id = o.id WHERE fa.status='Pending' ORDER BY fa.submitted_at ASC LIMIT 5");
          if($q->num_rows > 0):
            while($r = $q->fetch_assoc()):
        ?>
        <li class="flex items-center justify-between p-3 bg-slate-50 dark:bg-slate-800 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700/80 transition-colors">
          <div class="flex flex-col">
            <span class="font-medium text-sm text-slate-800 dark:text-slate-200"><?php echo htmlspecialchars($r['franchise_ref_number']); ?></span>
            <span class="text-xs text-slate-500"><?php echo htmlspecialchars($r['full_name']); ?> • <?php echo date('M d', strtotime($r['submitted_at'])); ?></span>
          </div>
          <a href="?page=module2/submodule1&q=<?php echo urlencode($r['franchise_ref_number']); ?>" class="px-3 py-1.5 text-xs font-medium text-blue-600 bg-blue-50 dark:bg-blue-900/30 dark:text-blue-400 rounded-full hover:bg-blue-100 dark:hover:bg-blue-900/50 transition-colors">Review</a>
        </li>
        <?php endwhile; else: ?>
        <li class="text-sm text-slate-500 italic text-center py-4">No pending applications.</li>
        <?php endif; ?>
      </ul>
    </div>
    <div class="p-6 border rounded-lg dark:border-slate-700 bg-white dark:bg-slate-900 shadow-sm">
      <h2 class="text-lg font-semibold mb-4 flex items-center gap-2"><i data-lucide="zap" class="w-5 h-5 text-amber-500"></i> Quick Actions</h2>
      <div class="grid grid-cols-1 gap-3">
        <a href="?page=module2/submodule1" class="flex items-center justify-between p-4 border border-slate-200 dark:border-slate-700 rounded-lg hover:border-blue-500 dark:hover:border-blue-500 hover:shadow-md transition-all group">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg text-blue-600 dark:text-blue-400 group-hover:bg-blue-600 group-hover:text-white transition-colors">
              <i data-lucide="plus-circle" class="w-5 h-5"></i>
            </div>
            <div>
              <div class="font-medium text-slate-800 dark:text-slate-200">New Application</div>
              <div class="text-xs text-slate-500">Register new franchise intake</div>
            </div>
          </div>
          <i data-lucide="chevron-right" class="w-4 h-4 text-slate-400 group-hover:text-blue-500"></i>
        </a>

        <a href="?page=module2/submodule2" class="flex items-center justify-between p-4 border border-slate-200 dark:border-slate-700 rounded-lg hover:border-emerald-500 dark:hover:border-emerald-500 hover:shadow-md transition-all group">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg text-emerald-600 dark:text-emerald-400 group-hover:bg-emerald-600 group-hover:text-white transition-colors">
              <i data-lucide="check-square" class="w-5 h-5"></i>
            </div>
            <div>
              <div class="font-medium text-slate-800 dark:text-slate-200">Generate Endorsement</div>
              <div class="text-xs text-slate-500">Issue permits for valid apps</div>
            </div>
          </div>
          <i data-lucide="chevron-right" class="w-4 h-4 text-slate-400 group-hover:text-emerald-500"></i>
        </a>
      </div>
    </div>
  </div>
</div>
