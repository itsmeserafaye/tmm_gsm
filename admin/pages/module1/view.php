<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module1.view','module1.vehicles.write']);
$db = db();
$plate = trim($_GET['plate'] ?? '');
$v = null;
if ($plate !== '') {
  $stmt = $db->prepare("SELECT v.id AS vehicle_id, v.plate_number, v.vehicle_type, v.operator_id,
                               COALESCE(NULLIF(o.name,''), NULLIF(o.full_name,''), NULLIF(v.operator_name,''), '') AS operator_display,
                               v.engine_no, v.chassis_no, v.make, v.model, v.year_model, v.fuel_type,
                               v.status, v.created_at
                        FROM vehicles v
                        LEFT JOIN operators o ON o.id=v.operator_id
                        WHERE v.plate_number=?");
  $stmt->bind_param('s', $plate);
  $stmt->execute();
  $v = $stmt->get_result()->fetch_assoc();
}
$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';
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
                'Linked' => 'bg-blue-100 text-blue-700 border-blue-200 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500/20',
                'Unlinked' => 'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/20',
                'Blocked' => 'bg-rose-100 text-rose-700 border-rose-200 dark:bg-rose-500/10 dark:text-rose-400 dark:border-rose-500/20',
                'Inactive' => 'bg-rose-100 text-rose-700 border-rose-200 dark:bg-rose-500/10 dark:text-rose-400 dark:border-rose-500/20',
                default => 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-500/10 dark:text-slate-400 dark:border-slate-500/20'
              };
            ?>
            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold border <?php echo $statusClass; ?>">
              <?php echo htmlspecialchars($v['status']); ?>
            </span>
          </div>
          <?php $vs = (string)($v['status'] ?? ''); ?>
          <?php if ($vs === 'Blocked'): ?>
            <div class="mt-2 inline-flex items-center gap-2 rounded-xl bg-rose-50 text-rose-700 border border-rose-200 px-3 py-1.5 text-xs font-bold">
              <i data-lucide="octagon-alert" class="w-4 h-4"></i>
              Operation blocked (OR expired)
            </div>
          <?php elseif ($vs === 'Inactive'): ?>
            <div class="mt-2 inline-flex items-center gap-2 rounded-xl bg-amber-50 text-amber-800 border border-amber-200 px-3 py-1.5 text-xs font-bold">
              <i data-lucide="triangle-alert" class="w-4 h-4"></i>
              Inactive (missing OR)
            </div>
          <?php endif; ?>
          <div class="text-base font-medium text-slate-500 dark:text-slate-400 mt-1 flex items-center gap-2">
            <i data-lucide="user" class="w-4 h-4"></i>
            <?php echo htmlspecialchars($v['operator_display'] !== '' ? $v['operator_display'] : 'Unlinked'); ?>
          </div>
          <div class="text-xs text-slate-400 mt-2 flex items-center gap-2">
            <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
            Registered on <?php echo date('F d, Y', strtotime($v['created_at'])); ?>
          </div>
        </div>
      </div>
    </div>

    <div class="space-y-6">
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
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Vehicle ID</span>
            <div class="font-bold text-slate-900 dark:text-white text-lg"><?php echo (int)($v['vehicle_id'] ?? 0); ?></div>
          </div>
          <div class="space-y-1">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Engine No</span>
            <div class="font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($v['engine_no'] ?? '-'); ?></div>
          </div>
          <div class="space-y-1">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Chassis No</span>
            <div class="font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($v['chassis_no'] ?? '-'); ?></div>
          </div>
          <div class="space-y-1">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Make / Model</span>
            <div class="font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars(trim((string)($v['make'] ?? '') . ' ' . (string)($v['model'] ?? '')) ?: '-'); ?></div>
          </div>
          <div class="space-y-1">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Year / Fuel</span>
            <div class="font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars(trim((string)($v['year_model'] ?? '') . ' ' . (string)($v['fuel_type'] ?? '')) ?: '-'); ?></div>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>
