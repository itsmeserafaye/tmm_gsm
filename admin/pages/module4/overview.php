<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module4.view','module4.inspections.manage']);
$db = db();

$period = $_GET['period'] ?? '7d';
$periodOptions = ['today', '7d', '30d', 'all'];
if (!in_array($period, $periodOptions, true)) {
  $period = '7d';
}

$flashNotice = isset($_GET['notice']) ? trim($_GET['notice']) : '';
$flashError = isset($_GET['error']) ? trim($_GET['error']) : '';

if (isset($_GET['export']) && $_GET['export'] === 'cert_queue') {
  // Export Logic (Keep as is)
  $sqlExport = "SELECT s.schedule_id, s.plate_number, s.scheduled_at, r.overall_status FROM inspection_schedules s JOIN inspection_results r ON r.schedule_id=s.schedule_id LEFT JOIN inspection_certificates c ON c.schedule_id=s.schedule_id WHERE c.cert_id IS NULL";
  if ($period === 'today') {
    $sqlExport .= " AND DATE(r.submitted_at) = CURDATE()";
  } elseif ($period === '7d') {
    $sqlExport .= " AND r.submitted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
  } elseif ($period === '30d') {
    $sqlExport .= " AND r.submitted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
  }
  $sqlExport .= " ORDER BY r.submitted_at DESC LIMIT 500";
  $resExport = $db->query($sqlExport);
  header('Content-Type: text/csv');
  $fileLabel = date('Ymd_His');
  header('Content-Disposition: attachment; filename="inspection_cert_queue_' . $fileLabel . '.csv"');
  echo "schedule_id,plate_number,scheduled_at,overall_status\n";
  if ($resExport) {
    while ($r = $resExport->fetch_assoc()) {
      $sid = (int)($r['schedule_id'] ?? 0);
      $plate = isset($r['plate_number']) ? str_replace('"', '""', $r['plate_number']) : '';
      $sched = isset($r['scheduled_at']) ? str_replace('"', '""', $r['scheduled_at']) : '';
      $st = isset($r['overall_status']) ? str_replace('"', '""', $r['overall_status']) : '';
      echo $sid . ',"' . $plate . '","' . $sched . '","' . $st . "\"\n";
    }
  }
  exit;
}

$pendingVerification = 0;
$scheduledInspections = 0;
$certificatesIssued = 0;
$resCounts = $db->query("SELECT SUM(CASE WHEN (cr_verified=0 OR or_verified=0) THEN 1 ELSE 0 END) AS pending_ver, SUM(CASE WHEN status='Scheduled' THEN 1 ELSE 0 END) AS scheduled FROM inspection_schedules");
if ($resCounts && ($row = $resCounts->fetch_assoc())) {
  $pendingVerification = (int)($row['pending_ver'] ?? 0);
  $scheduledInspections = (int)($row['scheduled'] ?? 0);
}
$resCertTotal = $db->query("SELECT COUNT(*) AS c FROM inspection_certificates");
if ($resCertTotal && ($row = $resCertTotal->fetch_assoc())) {
  $certificatesIssued = (int)($row['c'] ?? 0);
}

$queuePeriodCreated = '';
$queuePeriodScheduled = '';
$queuePeriodResult = '';
if ($period === 'today') {
  $queuePeriodCreated = " AND DATE(created_at) = CURDATE()";
  $queuePeriodScheduled = " AND DATE(scheduled_at) = CURDATE()";
  $queuePeriodResult = " AND DATE(r.submitted_at) = CURDATE()";
} elseif ($period === '7d') {
  $queuePeriodCreated = " AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
  $queuePeriodScheduled = " AND scheduled_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
  $queuePeriodResult = " AND r.submitted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($period === '30d') {
  $queuePeriodCreated = " AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
  $queuePeriodScheduled = " AND scheduled_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
  $queuePeriodResult = " AND r.submitted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
}

$verificationQueue = [];
$resVer = $db->query("SELECT schedule_id, plate_number, scheduled_at, status FROM inspection_schedules WHERE (cr_verified=0 OR or_verified=0)" . $queuePeriodCreated . " ORDER BY created_at DESC LIMIT 5");
if ($resVer) {
  while ($r = $resVer->fetch_assoc()) {
    $verificationQueue[] = $r;
  }
}

$upcomingQueue = [];
$resUpcoming = $db->query("SELECT schedule_id, plate_number, scheduled_at, status FROM inspection_schedules WHERE status='Scheduled'" . $queuePeriodScheduled . " ORDER BY scheduled_at ASC LIMIT 5");
if ($resUpcoming) {
  while ($r = $resUpcoming->fetch_assoc()) {
    $upcomingQueue[] = $r;
  }
}

$certificateQueue = [];
$resCertQueue = $db->query("SELECT s.schedule_id, s.plate_number, s.scheduled_at, r.overall_status FROM inspection_schedules s JOIN inspection_results r ON r.schedule_id=s.schedule_id LEFT JOIN inspection_certificates c ON c.schedule_id=s.schedule_id WHERE c.cert_id IS NULL" . $queuePeriodResult . " ORDER BY r.submitted_at DESC LIMIT 5");
if ($resCertQueue) {
  while ($r = $resCertQueue->fetch_assoc()) {
    $certificateQueue[] = $r;
  }
}
?>

<div class="mx-auto max-w-7xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-8">
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between mb-2 border-b border-slate-200 dark:border-slate-700 pb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Vehicle Inspection & Registration</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Manages inspection scheduling, checklist execution, certification issuance, and LPTRP-aligned route validation.</p>
        </div>
        <div class="flex gap-3">
            <a href="?page=module4/submodule1" class="inline-flex items-center gap-2 rounded-md bg-blue-700 hover:bg-blue-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-all active:scale-[0.98]">
                <i data-lucide="calendar-plus" class="w-4 h-4"></i>
                Schedule Inspection
            </a>
        </div>
    </div>

    <?php if ($flashError !== ''): ?>
        <div class="p-4 rounded-md bg-rose-50 border border-rose-200 text-rose-700 text-sm font-semibold flex items-center gap-2">
            <i data-lucide="alert-triangle" class="w-4 h-4"></i> <?php echo htmlspecialchars($flashError); ?>
        </div>
    <?php elseif ($flashNotice !== ''): ?>
        <div class="p-4 rounded-md bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm font-semibold flex items-center gap-2">
            <i data-lucide="check-circle" class="w-4 h-4"></i> <?php echo htmlspecialchars($flashNotice); ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm hover:border-amber-400 transition-colors">
            <div class="flex items-center justify-between mb-2">
                <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Pending Verification</div>
                <i data-lucide="file-search" class="w-4 h-4 text-amber-600 dark:text-amber-400"></i>
            </div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo number_format($pendingVerification); ?></div>
            <div class="mt-1 text-xs text-slate-500">CR/OR review required</div>
        </div>
        <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm hover:border-blue-400 transition-colors">
            <div class="flex items-center justify-between mb-2">
                <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Scheduled</div>
                <i data-lucide="calendar" class="w-4 h-4 text-blue-600 dark:text-blue-400"></i>
            </div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo number_format($scheduledInspections); ?></div>
            <div class="mt-1 text-xs text-slate-500">Upcoming inspections</div>
        </div>
        <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm hover:border-emerald-400 transition-colors">
            <div class="flex items-center justify-between mb-2">
                <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Certificates Issued</div>
                <i data-lucide="award" class="w-4 h-4 text-emerald-600 dark:text-emerald-400"></i>
            </div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo number_format($certificatesIssued); ?></div>
            <div class="mt-1 text-xs text-slate-500">Total issued</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Work Queue -->
        <div class="lg:col-span-2 space-y-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between p-4 bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm">
                <form method="GET" class="flex items-center gap-3">
                    <input type="hidden" name="page" value="module4/overview">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-300">Filter Period</span>
                    <div class="relative">
                        <select name="period" class="appearance-none pl-4 pr-10 py-2 rounded-md bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 text-sm font-semibold text-slate-900 dark:text-white focus:ring-1 focus:ring-blue-500 focus:border-blue-500 cursor-pointer outline-none" onchange="this.form.submit()">
                            <option value="today" <?php echo $period === 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="7d" <?php echo $period === '7d' ? 'selected' : ''; ?>>Last 7 days</option>
                            <option value="30d" <?php echo $period === '30d' ? 'selected' : ''; ?>>Last 30 days</option>
                            <option value="all" <?php echo $period === 'all' ? 'selected' : ''; ?>>All time</option>
                        </select>
                        <i data-lucide="chevron-down" class="absolute right-3 top-1/2 -translate-y-1/2 w-3 h-3 text-slate-400 pointer-events-none"></i>
                    </div>
                </form>
                <a href="?page=module4/overview&period=<?php echo htmlspecialchars($period, ENT_QUOTES); ?>&export=cert_queue" class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700/40 text-sm font-semibold transition-colors">
                    <i data-lucide="download" class="w-3.5 h-3.5"></i> Export Ready
                </a>
            </div>

            <!-- Queue Lists -->
            <div class="space-y-6">
                <!-- CR/OR Verification -->
                <div class="overflow-hidden rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
                    <div class="p-5 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <i data-lucide="file-search" class="w-5 h-5 text-amber-500"></i>
                            <h3 class="text-base font-bold text-slate-900 dark:text-white">CR/OR Verification</h3>
                        </div>
                        <span class="px-2.5 py-1 rounded-md bg-amber-100 text-amber-700 text-xs font-bold"><?php echo count($verificationQueue); ?> Pending</span>
                    </div>
                    <div class="divide-y divide-slate-200 dark:divide-slate-700">
                        <?php if ($verificationQueue): ?>
                            <?php foreach ($verificationQueue as $row): ?>
                                <div class="p-4 hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors flex items-center justify-between">
                                    <div>
                                        <div class="font-semibold text-slate-900 dark:text-white text-sm"><?php echo htmlspecialchars($row['plate_number']); ?></div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400">Document Verification Required</div>
                                    </div>
                                    <a href="?page=module4/submodule1&plate=<?php echo urlencode($row['plate_number']); ?>&schedule_id=<?php echo (int)$row['schedule_id']; ?>" class="px-3 py-1.5 rounded-md bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition-colors text-xs font-semibold">Verify</a>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="p-6 text-center text-slate-400 text-xs font-medium italic">No pending verifications.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Scheduled -->
                <div class="overflow-hidden rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
                    <div class="p-5 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <i data-lucide="calendar" class="w-5 h-5 text-blue-500"></i>
                            <h3 class="text-base font-bold text-slate-900 dark:text-white">Upcoming Inspections</h3>
                        </div>
                        <span class="px-2.5 py-1 rounded-md bg-blue-100 text-blue-700 text-xs font-bold"><?php echo count($upcomingQueue); ?> Scheduled</span>
                    </div>
                    <div class="divide-y divide-slate-200 dark:divide-slate-700">
                        <?php if ($upcomingQueue): ?>
                            <?php foreach ($upcomingQueue as $row): ?>
                                <div class="p-4 hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors flex items-center justify-between">
                                    <div>
                                        <div class="font-semibold text-slate-900 dark:text-white text-sm"><?php echo htmlspecialchars($row['plate_number']); ?></div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-1">
                                            <i data-lucide="clock" class="w-3 h-3"></i>
                                            <?php echo htmlspecialchars(substr($row['scheduled_at'], 0, 16)); ?>
                                        </div>
                                    </div>
                                    <a href="?page=module4/submodule1&schedule_id=<?php echo (int)$row['schedule_id']; ?>" class="px-3 py-1.5 rounded-md bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition-colors text-xs font-semibold">Details</a>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="p-6 text-center text-slate-400 text-xs font-medium italic">No upcoming inspections.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Ready for Certificate -->
                <div class="overflow-hidden rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
                    <div class="p-5 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <i data-lucide="award" class="w-5 h-5 text-emerald-500"></i>
                            <h3 class="text-base font-bold text-slate-900 dark:text-white">Ready for Certificate</h3>
                        </div>
                        <span class="px-2.5 py-1 rounded-md bg-emerald-100 text-emerald-700 text-xs font-bold"><?php echo count($certificateQueue); ?> Ready</span>
                    </div>
                    <div class="divide-y divide-slate-200 dark:divide-slate-700">
                        <?php if ($certificateQueue): ?>
                            <?php foreach ($certificateQueue as $row): ?>
                                <div class="p-4 hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors flex items-center justify-between">
                                    <div>
                                        <div class="font-semibold text-slate-900 dark:text-white text-sm"><?php echo htmlspecialchars($row['plate_number']); ?></div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-1">
                                            Status: <?php echo htmlspecialchars($row['overall_status']); ?>
                                        </div>
                                    </div>
                                    <a href="?page=module4/submodule2&schedule_id=<?php echo (int)$row['schedule_id']; ?>" class="px-3 py-1.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white text-xs font-semibold shadow-sm transition-colors">Issue Cert</a>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="p-6 text-center text-slate-400 text-xs font-medium italic">No certificates pending issuance.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="flex flex-col gap-6">
            <!-- Quick Actions -->
            <div class="rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm p-6">
                <h3 class="text-base font-bold text-slate-900 dark:text-white mb-4">Quick Actions</h3>
                <div class="space-y-3">
                    <a href="?page=module4/submodule1" class="flex items-center justify-between p-3 rounded-md bg-slate-50 dark:bg-slate-700/30 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                        <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Verify & Schedule</span>
                        <i data-lucide="file-check" class="w-4 h-4 text-slate-400"></i>
                    </a>
                    <a href="?page=module4/submodule2" class="flex items-center justify-between p-3 rounded-md bg-slate-50 dark:bg-slate-700/30 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                        <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Run Checklist</span>
                        <i data-lucide="list-checks" class="w-4 h-4 text-slate-400"></i>
                    </a>
                    <a href="?page=module4/submodule3" class="flex items-center justify-between p-3 rounded-md bg-slate-50 dark:bg-slate-700/30 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                        <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Route Validation</span>
                        <i data-lucide="map" class="w-4 h-4 text-slate-400"></i>
                    </a>
                </div>
                <div class="mt-6 p-4 rounded-md bg-slate-50 dark:bg-slate-900/30 border border-slate-200 dark:border-slate-700">
                    <div class="flex gap-2">
                        <i data-lucide="info" class="w-4 h-4 text-slate-500 dark:text-slate-300 flex-shrink-0 mt-0.5"></i>
                        <div class="text-xs text-slate-600 dark:text-slate-300 font-medium leading-relaxed">
                            Only inspected and certified vehicles can be enrolled in terminals and routes. Ensure all LPTRP requirements are met.
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
