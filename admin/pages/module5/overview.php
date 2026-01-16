<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
require_any_permission(['module5.view','parking.manage']);

function m5_table_exists($db, $name) {
  $esc = $db->real_escape_string($name);
  $res = $db->query("SHOW TABLES LIKE '$esc'");
  return $res && $res->num_rows > 0;
}
function m5_column_exists($db, $table, $column) {
  $t = $db->real_escape_string($table);
  $c = $db->real_escape_string($column);
  $res = $db->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
  return $res && $res->num_rows > 0;
}
function m5_scalar($res, $key, $default = 0) {
  if (!$res) return $default;
  $row = $res->fetch_assoc();
  if (!$row) return $default;
  return isset($row[$key]) ? $row[$key] : $default;
}

$hasTerminals = m5_table_exists($db, 'terminals');
$hasTerminalAreas = m5_table_exists($db, 'terminal_areas');
$hasParkingAreas = m5_table_exists($db, 'parking_areas');
$hasTransactions = m5_table_exists($db, 'parking_transactions');
$hasViolations = m5_table_exists($db, 'parking_violations');

$terminalsTotal = 0;
$terminalsActive = 0;
$terminalAreasTotal = 0;
$terminalsNoAreas = 0;

if ($hasTerminals) {
  $terminalsTotal = (int)m5_scalar($db->query("SELECT COUNT(*) AS c FROM terminals WHERE type <> 'Parking'"), 'c', 0);
  if (m5_column_exists($db, 'terminals', 'status')) {
    $terminalsActive = (int)m5_scalar($db->query("SELECT COUNT(*) AS c FROM terminals WHERE type <> 'Parking' AND status='Active'"), 'c', 0);
  } else {
    $terminalsActive = $terminalsTotal;
  }
  if ($hasTerminalAreas) {
    $resNoAreas = $db->query("SELECT COUNT(*) AS c FROM terminals t LEFT JOIN terminal_areas ta ON ta.terminal_id=t.id WHERE t.type <> 'Parking' GROUP BY t.id HAVING COUNT(ta.id)=0");
    $terminalsNoAreas = $resNoAreas ? (int)$resNoAreas->num_rows : 0;
  }
}

if ($hasTerminalAreas) {
  $terminalAreasTotal = (int)m5_scalar($db->query("SELECT COUNT(*) AS c FROM terminal_areas"), 'c', 0);
}

$parkingAreasTotal = 0;
$parkingAreasActive = 0;
$parkingAreasAttention = 0;
if ($hasParkingAreas) {
  $parkingAreasTotal = (int)m5_scalar($db->query("SELECT COUNT(*) AS c FROM parking_areas"), 'c', 0);
  if (m5_column_exists($db, 'parking_areas', 'status')) {
    $parkingAreasActive = (int)m5_scalar($db->query("SELECT COUNT(*) AS c FROM parking_areas WHERE status <> 'Closed'"), 'c', 0);
    $parkingAreasAttention = (int)m5_scalar($db->query("SELECT COUNT(*) AS c FROM parking_areas WHERE status IN ('Full','Maintenance')"), 'c', 0);
  } else {
    $parkingAreasActive = $parkingAreasTotal;
  }
}

$transactionsToday = 0;
$revenueToday = 0.0;
$revenue7d = 0.0;
if ($hasTransactions) {
  $transactionsToday = (int)m5_scalar($db->query("SELECT COUNT(*) AS c FROM parking_transactions WHERE DATE(created_at)=CURDATE()"), 'c', 0);
  $revenueToday = (float)m5_scalar($db->query("SELECT COALESCE(SUM(amount),0) AS s FROM parking_transactions WHERE DATE(created_at)=CURDATE()"), 's', 0);
  $revenue7d = (float)m5_scalar($db->query("SELECT COALESCE(SUM(amount),0) AS s FROM parking_transactions WHERE created_at >= (NOW() - INTERVAL 7 DAY)"), 's', 0);
}

$violationsToday = 0;
$unpaidViolations = 0;
if ($hasViolations) {
  $violationsToday = (int)m5_scalar($db->query("SELECT COUNT(*) AS c FROM parking_violations WHERE DATE(created_at)=CURDATE()"), 'c', 0);
  if (m5_column_exists($db, 'parking_violations', 'status')) {
    $unpaidViolations = (int)m5_scalar($db->query("SELECT COUNT(*) AS c FROM parking_violations WHERE status IN ('Unpaid','Open')"), 'c', 0);
  }
}

$activityToday = $transactionsToday + $violationsToday;

$recentPayments = [];
if ($hasTransactions) {
  $sql = "SELECT t.vehicle_plate, t.amount, t.transaction_type, t.status, t.created_at, p.name AS area_name
    FROM parking_transactions t
    LEFT JOIN parking_areas p ON p.id=t.parking_area_id
    ORDER BY t.created_at DESC
    LIMIT 6";
  $res = $db->query($sql);
  if ($res) {
    while ($r = $res->fetch_assoc()) $recentPayments[] = $r;
  }
}

$recentViolations = [];
if ($hasViolations) {
  $sql = "SELECT v.vehicle_plate, v.violation_type, v.penalty_amount, v.status, v.created_at, p.name AS area_name
    FROM parking_violations v
    LEFT JOIN parking_areas p ON p.id=v.parking_area_id
    ORDER BY v.created_at DESC
    LIMIT 6";
  $res = $db->query($sql);
  if ($res) {
    while ($r = $res->fetch_assoc()) $recentViolations[] = $r;
  }
}

$workQueue = [];
if ($terminalsNoAreas > 0) {
  $workQueue[] = ['icon' => 'map-pin', 'label' => 'Terminals missing areas', 'meta' => $terminalsNoAreas . ' terminal(s) have zero areas', 'href' => '?page=module5/submodule1', 'tone' => 'amber'];
}
if ($parkingAreasAttention > 0) {
  $workQueue[] = ['icon' => 'parking-circle', 'label' => 'Parking areas need attention', 'meta' => $parkingAreasAttention . ' area(s) Full/Maintenance', 'href' => '?page=module5/submodule2', 'tone' => 'amber'];
}
if ($unpaidViolations > 0) {
  $workQueue[] = ['icon' => 'alert-triangle', 'label' => 'Unpaid violations', 'meta' => $unpaidViolations . ' open case(s)', 'href' => '?page=module5/submodule3', 'tone' => 'rose'];
}
if ($hasTransactions) {
  $workQueue[] = ['icon' => 'wallet', 'label' => 'Reconcile today’s fees', 'meta' => '₱' . number_format($revenueToday, 2) . ' collected today', 'href' => '?page=module5/submodule3', 'tone' => 'emerald'];
}
if (!$workQueue) {
  $workQueue[] = ['icon' => 'check-circle-2', 'label' => 'All caught up', 'meta' => 'No queued items detected from current data', 'href' => '?page=module5/submodule1', 'tone' => 'slate'];
}
?>

<div class="mx-auto max-w-7xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100">
    <!-- Header -->
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between mb-8 border-b border-slate-200 dark:border-slate-700 pb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Parking & Terminal Management</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Live overview of terminals, parking areas, fees, and enforcement activity.</p>
        </div>
        <div class="flex gap-2">
             <a href="?page=module5/submodule1" class="inline-flex items-center gap-2 rounded-lg bg-blue-700 hover:bg-blue-800 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-all">
                <i data-lucide="plus" class="w-4 h-4"></i>
                Add Terminal
            </a>
        </div>
    </div>

    <!-- Stats Overview -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-8">
        <!-- Stat Card 1 -->
        <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm hover:border-blue-400 transition-colors">
            <div class="flex items-center justify-between mb-2">
                <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Active Terminals</div>
                <i data-lucide="building-2" class="w-4 h-4 text-blue-600 dark:text-blue-400"></i>
            </div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo number_format($terminalsActive); ?></div>
            <div class="mt-1 text-xs text-slate-500"><?php echo $terminalAreasTotal; ?> Areas Total</div>
        </div>

        <!-- Stat Card 2 -->
        <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm hover:border-amber-400 transition-colors">
            <div class="flex items-center justify-between mb-2">
                <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Parking Areas</div>
                <i data-lucide="parking-circle" class="w-4 h-4 text-amber-600 dark:text-amber-400"></i>
            </div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo number_format($parkingAreasActive); ?></div>
            <div class="mt-1 text-xs text-slate-500"><?php echo $parkingAreasAttention; ?> Need Attention</div>
        </div>

        <!-- Stat Card 3 -->
        <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm hover:border-emerald-400 transition-colors">
            <div class="flex items-center justify-between mb-2">
                <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Activity Today</div>
                <i data-lucide="activity" class="w-4 h-4 text-emerald-600 dark:text-emerald-400"></i>
            </div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo number_format($activityToday); ?></div>
            <div class="mt-1 text-xs text-slate-500"><?php echo $transactionsToday; ?> Txns • <?php echo $violationsToday; ?> Vios</div>
        </div>

        <!-- Stat Card 4 -->
        <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm hover:border-violet-400 transition-colors">
            <div class="flex items-center justify-between mb-2">
                <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Fees Collected</div>
                <i data-lucide="wallet" class="w-4 h-4 text-violet-600 dark:text-violet-400"></i>
            </div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white">₱<?php echo number_format($revenueToday, 2); ?></div>
            <div class="mt-1 text-xs text-slate-500">Today's Total</div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
        <!-- Sidebar (Work Queue & Actions) -->
        <div class="flex flex-col gap-6">
            <!-- Work Queue -->
            <div class="rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm p-6">
                <h3 class="text-base font-bold text-slate-900 dark:text-white mb-4">Work Queue</h3>
                <div class="space-y-3">
                    <?php foreach ($workQueue as $q): ?>
                        <?php
                            $tone = (string)($q['tone'] ?? 'slate');
                            $colors = match($tone) {
                                'emerald' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400',
                                'amber' => 'bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-400',
                                'rose' => 'bg-rose-50 text-rose-700 dark:bg-rose-900/20 dark:text-rose-400',
                                default => 'bg-slate-50 text-slate-700 dark:bg-slate-700 dark:text-slate-300'
                            };
                        ?>
                        <a href="<?php echo htmlspecialchars((string)$q['href'], ENT_QUOTES); ?>" class="flex items-start gap-3 p-3 rounded-md transition-all hover:bg-slate-100 dark:hover:bg-slate-700/50 <?php echo $colors; ?>">
                            <div class="mt-0.5"><i data-lucide="<?php echo htmlspecialchars((string)$q['icon'], ENT_QUOTES); ?>" class="w-4 h-4"></i></div>
                            <div>
                                <div class="text-sm font-semibold"><?php echo htmlspecialchars((string)$q['label'], ENT_QUOTES); ?></div>
                                <div class="text-xs opacity-80 font-medium"><?php echo htmlspecialchars((string)$q['meta'], ENT_QUOTES); ?></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm p-6">
                <h3 class="text-base font-bold text-slate-900 dark:text-white mb-4">Quick Actions</h3>
                <div class="space-y-2">
                    <a href="?page=module5/submodule1" class="flex items-center justify-between p-3 rounded-md bg-slate-50 dark:bg-slate-700/50 hover:bg-blue-50 dark:hover:bg-blue-900/20 group transition-all border border-transparent hover:border-blue-200 dark:hover:border-blue-800">
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300 group-hover:text-blue-700 dark:group-hover:text-blue-400">Manage Terminals</span>
                        <i data-lucide="building-2" class="w-4 h-4 text-slate-400 group-hover:text-blue-500"></i>
                    </a>
                    <a href="?page=module5/submodule2" class="flex items-center justify-between p-3 rounded-md bg-slate-50 dark:bg-slate-700/50 hover:bg-blue-50 dark:hover:bg-blue-900/20 group transition-all border border-transparent hover:border-blue-200 dark:hover:border-blue-800">
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300 group-hover:text-blue-700 dark:group-hover:text-blue-400">Manage Parking</span>
                        <i data-lucide="parking-circle" class="w-4 h-4 text-slate-400 group-hover:text-blue-500"></i>
                    </a>
                    <a href="?page=module5/submodule3" class="flex items-center justify-between p-3 rounded-md bg-slate-50 dark:bg-slate-700/50 hover:bg-blue-50 dark:hover:bg-blue-900/20 group transition-all border border-transparent hover:border-blue-200 dark:hover:border-blue-800">
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300 group-hover:text-blue-700 dark:group-hover:text-blue-400">Fees & Enforcement</span>
                        <i data-lucide="shield" class="w-4 h-4 text-slate-400 group-hover:text-blue-500"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Recent Activity (2 Cols Wide) -->
        <div class="xl:col-span-2 space-y-6">
            <div class="rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
                <div class="p-6 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between bg-slate-50/50 dark:bg-slate-800/50">
                    <div>
                        <h2 class="text-base font-bold text-slate-900 dark:text-white">Recent Activity</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Latest payments and violations</p>
                    </div>
                    <a href="?page=module5/submodule3" class="text-xs font-semibold text-blue-600 hover:text-blue-500 hover:underline">View All</a>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-slate-200 dark:divide-slate-700">
                    <!-- Payments -->
                    <div>
                        <div class="px-4 py-3 bg-slate-50 dark:bg-slate-800/80 text-xs font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200 dark:border-slate-700">Latest Payments</div>
                        <div class="divide-y divide-slate-100 dark:divide-slate-700">
                            <?php if ($recentPayments): ?>
                                <?php foreach ($recentPayments as $p): ?>
                                    <div class="p-4 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <div class="font-bold text-sm text-slate-900 dark:text-white"><?php echo htmlspecialchars($p['vehicle_plate'] ?? '—'); ?></div>
                                                <div class="text-xs text-slate-500"><?php echo htmlspecialchars($p['area_name'] ?? '—'); ?></div>
                                            </div>
                                            <div class="text-right">
                                                <div class="font-bold text-sm text-emerald-600">₱<?php echo number_format($p['amount'] ?? 0, 2); ?></div>
                                                <div class="text-[10px] text-slate-400"><?php echo htmlspecialchars(substr($p['created_at'] ?? '', 11, 5)); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="p-6 text-center text-slate-400 text-xs italic">No recent payments.</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Violations -->
                    <div>
                        <div class="px-4 py-3 bg-slate-50 dark:bg-slate-800/80 text-xs font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200 dark:border-slate-700">Latest Violations</div>
                        <div class="divide-y divide-slate-100 dark:divide-slate-700">
                            <?php if ($recentViolations): ?>
                                <?php foreach ($recentViolations as $v): ?>
                                    <div class="p-4 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <div class="font-bold text-sm text-slate-900 dark:text-white"><?php echo htmlspecialchars($v['vehicle_plate'] ?? '—'); ?></div>
                                                <div class="text-xs text-slate-500"><?php echo htmlspecialchars($v['violation_type'] ?? 'Violation'); ?></div>
                                            </div>
                                            <div class="text-right">
                                                <div class="font-bold text-sm text-rose-600">₱<?php echo number_format($v['penalty_amount'] ?? 0, 2); ?></div>
                                                <div class="text-[10px] text-slate-400"><?php echo htmlspecialchars(substr($v['created_at'] ?? '', 11, 5)); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="p-6 text-center text-slate-400 text-xs italic">No recent violations.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        if(window.lucide) window.lucide.createIcons();
    });
</script>
