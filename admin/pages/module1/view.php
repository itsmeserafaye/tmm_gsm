<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module1.view','module1.vehicles.write','module1.routes.write','module1.coops.write']);
$db = db();
$plate = trim($_GET['plate'] ?? '');
$v = null;
if ($plate !== '') {
  $stmt = $db->prepare("SELECT plate_number, vehicle_type, operator_name, coop_name, franchise_id, route_id, status, created_at FROM vehicles WHERE plate_number=?");
  $stmt->bind_param('s', $plate);
  $stmt->execute();
  $v = $stmt->get_result()->fetch_assoc();
}
?>
<div class="p-1 space-y-6">
  <?php if (!$v): ?>
    <div class="flex flex-col items-center justify-center p-12 text-center rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-dashed border-slate-300 dark:border-slate-700">
      <div class="p-4 rounded-full bg-slate-100 dark:bg-slate-800 mb-4">
        <i data-lucide="search-x" class="w-8 h-8 text-slate-400"></i>
      </div>
      <h3 class="text-lg font-bold text-slate-900 dark:text-white">Vehicle Not Found</h3>
      <p class="text-slate-500 dark:text-slate-400 max-w-xs mt-2">The requested vehicle record could not be found in the registry.</p>
    </div>
  <?php else: ?>
    <!-- Header with Status -->
    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 bg-white dark:bg-slate-900 p-6 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700">
      <div class="flex items-start gap-5">
        <div class="p-4 rounded-xl bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 ring-1 ring-blue-100 dark:ring-blue-800/30">
          <i data-lucide="bus" class="w-8 h-8"></i>
        </div>
        <div>
          <div class="flex items-center gap-3">
            <h2 class="text-3xl font-black text-slate-900 dark:text-white tracking-tight"><?php echo htmlspecialchars($v['plate_number']); ?></h2>
            <?php
              $statusClass = match($v['status']) {
                'Active' => 'bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20',
                'Suspended' => 'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/20',
                'Deactivated' => 'bg-rose-100 text-rose-700 border-rose-200 dark:bg-rose-500/10 dark:text-rose-400 dark:border-rose-500/20',
                default => 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-500/10 dark:text-slate-400 dark:border-slate-500/20'
              };
            ?>
            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold border <?php echo $statusClass; ?>">
              <?php echo htmlspecialchars($v['status']); ?>
            </span>
          </div>
          <div class="text-base font-medium text-slate-500 dark:text-slate-400 mt-1 flex items-center gap-2">
            <i data-lucide="user" class="w-4 h-4"></i>
            <?php echo htmlspecialchars($v['operator_name']); ?>
          </div>
          <div class="text-xs text-slate-400 mt-2 flex items-center gap-2">
            <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
            Registered on <?php echo date('F d, Y', strtotime($v['created_at'])); ?>
          </div>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Left Column: Details -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Profile Card -->
        <div class="overflow-hidden rounded-2xl bg-white dark:bg-slate-900 shadow-sm border border-slate-200 dark:border-slate-700">
          <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-800/50">
            <h3 class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
              <i data-lucide="info" class="w-4 h-4 text-blue-500"></i> Vehicle Information
            </h3>
          </div>
          <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-6">
            <div class="space-y-1">
              <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Vehicle Type</span>
              <div class="font-bold text-slate-900 dark:text-white text-lg"><?php echo htmlspecialchars($v['vehicle_type']); ?></div>
            </div>
            <div class="space-y-1">
              <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Franchise ID</span>
              <div class="font-bold text-indigo-600 dark:text-indigo-400 text-lg flex items-center gap-2">
                <?php echo htmlspecialchars($v['franchise_id'] ?? '-'); ?>
                <?php if (!empty($v['franchise_id'])): ?>
                  <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-500"></i>
                <?php endif; ?>
              </div>
            </div>
            <div class="space-y-1 sm:col-span-2">
              <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Cooperative</span>
              <div class="font-medium text-slate-900 dark:text-white flex items-center gap-2">
                <div class="p-1.5 rounded-md bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400">
                  <i data-lucide="building-2" class="w-4 h-4"></i>
                </div>
                <?php echo htmlspecialchars($v['coop_name'] ?? 'No Cooperative Assigned'); ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Assignment Card -->
        <div class="overflow-hidden rounded-2xl bg-white dark:bg-slate-900 shadow-sm border border-slate-200 dark:border-slate-700">
          <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-800/50">
            <h3 class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
              <i data-lucide="map-pin" class="w-4 h-4 text-emerald-500"></i> Current Assignment
            </h3>
          </div>
          <div class="p-6">
            <?php
              $stmtA = $db->prepare("SELECT route_id, terminal_name, status, assigned_at FROM terminal_assignments WHERE plate_number=?");
              $stmtA->bind_param('s', $plate);
              $stmtA->execute();
              $a = $stmtA->get_result()->fetch_assoc();
            ?>
            <?php if (!$a): ?>
              <div class="flex items-center justify-center p-8 text-slate-500 italic bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-dashed border-slate-200 dark:border-slate-700">
                <span class="flex items-center gap-2">
                  <i data-lucide="alert-circle" class="w-4 h-4"></i> Vehicle is not currently assigned to any terminal.
                </span>
              </div>
            <?php else: ?>
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/30 border border-slate-100 dark:border-slate-800">
                  <div class="text-xs font-semibold text-slate-400 mb-1 uppercase tracking-wider">Route</div>
                  <div class="font-bold text-slate-900 dark:text-white text-lg"><?php echo htmlspecialchars($a['route_id']); ?></div>
                </div>
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/30 border border-slate-100 dark:border-slate-800">
                  <div class="text-xs font-semibold text-slate-400 mb-1 uppercase tracking-wider">Terminal</div>
                  <div class="font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($a['terminal_name']); ?></div>
                </div>
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/30 border border-slate-100 dark:border-slate-800">
                  <div class="text-xs font-semibold text-slate-400 mb-1 uppercase tracking-wider">Status</div>
                  <div class="font-bold text-lg <?php echo $a['status']==='Authorized'?'text-emerald-600 dark:text-emerald-400':'text-amber-600 dark:text-amber-400'; ?> flex items-center gap-2">
                    <?php echo htmlspecialchars($a['status']); ?>
                    <?php if ($a['status']==='Authorized'): ?>
                      <i data-lucide="check-circle" class="w-4 h-4"></i>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Right Column: Documents -->
      <div class="space-y-6">
        <div class="overflow-hidden rounded-2xl bg-white dark:bg-slate-900 shadow-sm border border-slate-200 dark:border-slate-700 h-full">
          <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-800/50">
            <h3 class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
              <i data-lucide="file-text" class="w-4 h-4 text-amber-500"></i> Documents
            </h3>
            <span class="text-xs font-medium px-2 py-1 rounded-md bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
              <?php
                $stmtCount = $db->prepare("SELECT COUNT(*) as c FROM documents WHERE plate_number=?");
                $stmtCount->bind_param('s', $plate);
                $stmtCount->execute();
                echo $stmtCount->get_result()->fetch_assoc()['c'];
              ?>
            </span>
          </div>
          <div class="p-4 space-y-3">
            <?php
              $stmtD = $db->prepare("SELECT type, file_path, uploaded_at FROM documents WHERE plate_number=? ORDER BY uploaded_at DESC");
              $stmtD->bind_param('s', $plate);
              $stmtD->execute();
              $resD = $stmtD->get_result();
              if ($resD->num_rows === 0):
            ?>
              <div class="flex flex-col items-center justify-center py-10 text-center">
                <div class="p-3 rounded-full bg-slate-100 dark:bg-slate-800 mb-3">
                  <i data-lucide="file-x" class="w-6 h-6 text-slate-400"></i>
                </div>
                <p class="text-sm text-slate-500 dark:text-slate-400">No documents uploaded.</p>
              </div>
            <?php else: while ($d = $resD->fetch_assoc()): ?>
              <div class="group flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 hover:border-blue-200 dark:hover:border-blue-800 hover:bg-blue-50/50 dark:hover:bg-blue-900/10 transition-all">
                <div class="flex items-center gap-3">
                  <div class="p-2 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-slate-500 dark:text-slate-400 group-hover:text-blue-500 group-hover:border-blue-200 dark:group-hover:border-blue-800 transition-colors">
                    <i data-lucide="file" class="w-4 h-4"></i>
                  </div>
                  <div>
                    <div class="text-sm font-bold text-slate-700 dark:text-slate-200 group-hover:text-blue-700 dark:group-hover:text-blue-300"><?php echo htmlspecialchars($d['type']); ?></div>
                    <div class="text-[10px] text-slate-400"><?php echo date('M d, Y', strtotime($d['uploaded_at'])); ?></div>
                  </div>
                </div>
                <a href="<?php echo htmlspecialchars($rootUrl ?? '', ENT_QUOTES); ?>/admin/<?php echo htmlspecialchars($d['file_path']); ?>" target="_blank" class="p-2 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-white dark:hover:bg-slate-800 hover:shadow-sm transition-all" title="View Document">
                  <i data-lucide="external-link" class="w-4 h-4"></i>
                </a>
              </div>
            <?php endwhile; endif; ?>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>
