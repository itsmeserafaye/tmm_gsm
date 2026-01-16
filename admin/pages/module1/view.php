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
<div class="p-1">
  <?php if (!$v): ?>
    <div class="p-6 text-center text-slate-500 italic">Vehicle not found in registry.</div>
  <?php else: ?>
    <!-- Header with Status -->
    <div class="flex items-start justify-between mb-6">
      <div>
        <h2 class="text-2xl font-black text-slate-900 dark:text-white"><?php echo htmlspecialchars($v['plate_number']); ?></h2>
        <div class="text-sm font-semibold text-slate-500 dark:text-slate-400 mt-1"><?php echo htmlspecialchars($v['operator_name']); ?></div>
      </div>
      <?php
        $statusClass = match($v['status']) {
          'Active' => 'bg-emerald-100 text-emerald-700 ring-emerald-600/20',
          'Suspended' => 'bg-amber-100 text-amber-700 ring-amber-600/20',
          'Deactivated' => 'bg-rose-100 text-rose-700 ring-rose-600/20',
          default => 'bg-slate-100 text-slate-700 ring-slate-600/20'
        };
      ?>
      <span class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-bold ring-1 ring-inset <?php echo $statusClass; ?>">
        <?php echo htmlspecialchars($v['status']); ?>
      </span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <!-- Profile Card -->
      <div class="p-5 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700">
        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4 flex items-center gap-2">
          <i data-lucide="user" class="w-4 h-4"></i> Profile
        </h3>
        <div class="space-y-3 text-sm">
          <div class="flex justify-between">
            <span class="text-slate-500 dark:text-slate-400">Vehicle Type</span>
            <span class="font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($v['vehicle_type']); ?></span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-500 dark:text-slate-400">Cooperative</span>
            <span class="font-bold text-slate-900 dark:text-white text-right truncate max-w-[180px]" title="<?php echo htmlspecialchars($v['coop_name'] ?? '-'); ?>">
              <?php echo htmlspecialchars($v['coop_name'] ?? '-'); ?>
            </span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-500 dark:text-slate-400">Franchise ID</span>
            <span class="font-bold text-indigo-600 dark:text-indigo-400"><?php echo htmlspecialchars($v['franchise_id'] ?? '-'); ?></span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-500 dark:text-slate-400">Registered</span>
            <span class="font-medium text-slate-700 dark:text-slate-300"><?php echo date('M d, Y', strtotime($v['created_at'])); ?></span>
          </div>
        </div>
      </div>

      <!-- Documents Card -->
      <div class="p-5 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700">
        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4 flex items-center gap-2">
          <i data-lucide="file-text" class="w-4 h-4"></i> Documents
        </h3>
        <div class="space-y-3">
          <?php
            $stmtD = $db->prepare("SELECT type, file_path, uploaded_at FROM documents WHERE plate_number=? ORDER BY uploaded_at DESC");
            $stmtD->bind_param('s', $plate);
            $stmtD->execute();
            $resD = $stmtD->get_result();
            if ($resD->num_rows === 0):
          ?>
            <div class="text-sm text-slate-400 italic">No documents uploaded.</div>
          <?php else: while ($d = $resD->fetch_assoc()): ?>
            <div class="flex items-center justify-between p-2 rounded-lg bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-700">
              <div class="flex items-center gap-2">
                <i data-lucide="file" class="w-4 h-4 text-slate-400"></i>
                <span class="text-sm font-bold text-slate-700 dark:text-slate-200"><?php echo htmlspecialchars($d['type']); ?></span>
              </div>
              <a href="/tmm/admin/<?php echo htmlspecialchars($d['file_path']); ?>" target="_blank" class="text-xs font-bold text-blue-600 hover:text-blue-700 hover:underline">
                View
              </a>
            </div>
          <?php endwhile; endif; ?>
        </div>
      </div>
    </div>

    <!-- Assignment Card -->
    <div class="mt-6 p-5 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700">
      <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4 flex items-center gap-2">
        <i data-lucide="map-pin" class="w-4 h-4"></i> Current Assignment
      </h3>
      <?php
        $stmtA = $db->prepare("SELECT route_id, terminal_name, status, assigned_at FROM terminal_assignments WHERE plate_number=?");
        $stmtA->bind_param('s', $plate);
        $stmtA->execute();
        $a = $stmtA->get_result()->fetch_assoc();
      ?>
      <?php if (!$a): ?>
        <div class="text-sm text-slate-500 italic">Vehicle is not currently assigned to any terminal.</div>
      <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <div class="text-xs text-slate-400 mb-1">Route</div>
            <div class="font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($a['route_id']); ?></div>
          </div>
          <div>
            <div class="text-xs text-slate-400 mb-1">Terminal</div>
            <div class="font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($a['terminal_name']); ?></div>
          </div>
          <div>
            <div class="text-xs text-slate-400 mb-1">Status</div>
            <div class="font-bold <?php echo $a['status']==='Authorized'?'text-emerald-600':'text-amber-600'; ?>">
              <?php echo htmlspecialchars($a['status']); ?>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
