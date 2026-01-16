<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module3.view','tickets.issue','tickets.validate','tickets.settle']);
$db = db();

$pending = 0;
$settled = 0;
$escalated = 0;
$finesToday = 0.0;
$finesThisMonth = 0.0;
$unsettledFines = 0.0;
$topViolations = [];

$resPending = $db->query("SELECT COUNT(*) AS c FROM tickets WHERE status='Pending'");
if ($resPending && ($rowP = $resPending->fetch_assoc())) {
  $pending = (int)$rowP['c'];
}

$resSettled = $db->query("SELECT COUNT(*) AS c FROM tickets WHERE status='Settled'");
if ($resSettled && ($rowS = $resSettled->fetch_assoc())) {
  $settled = (int)$rowS['c'];
}

$resEscalated = $db->query("SELECT COUNT(*) AS c FROM tickets WHERE status='Escalated'");
if ($resEscalated && ($rowE = $resEscalated->fetch_assoc())) {
  $escalated = (int)$rowE['c'];
}

$resFinesToday = $db->query("SELECT SUM(fine_amount) AS total FROM tickets WHERE status='Settled' AND DATE(date_issued) = CURDATE()");
if ($resFinesToday && ($rowFT = $resFinesToday->fetch_assoc()) && $rowFT['total'] !== null) {
  $finesToday = (float)$rowFT['total'];
}

$resFinesMonth = $db->query("SELECT SUM(fine_amount) AS total FROM tickets WHERE status='Settled' AND YEAR(date_issued) = YEAR(CURDATE()) AND MONTH(date_issued) = MONTH(CURDATE())");
if ($resFinesMonth && ($rowFM = $resFinesMonth->fetch_assoc()) && $rowFM['total'] !== null) {
  $finesThisMonth = (float)$rowFM['total'];
}

$resUnsettled = $db->query("SELECT SUM(fine_amount) AS total FROM tickets WHERE status!='Settled'");
if ($resUnsettled && ($rowU = $resUnsettled->fetch_assoc()) && $rowU['total'] !== null) {
  $unsettledFines = (float)$rowU['total'];
}

$resTopViolations = $db->query("SELECT violation_code, COUNT(*) AS cnt FROM tickets GROUP BY violation_code ORDER BY cnt DESC LIMIT 5");
if ($resTopViolations) {
  while ($rowTV = $resTopViolations->fetch_assoc()) {
    $topViolations[] = $rowTV;
  }
}

$queue = $db->query("SELECT ticket_number, vehicle_plate, violation_code, status, fine_amount, date_issued FROM tickets ORDER BY date_issued DESC LIMIT 5");
?>

<div class="mx-auto max-w-7xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-8">
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between mb-2 border-b border-slate-200 dark:border-slate-700 pb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Traffic Violation & Ticketing</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Digital enforcement and citation management aligned with MMDA STS, with LGU workflows for issuance, payment, compliance, and reporting.</p>
        </div>
        <div class="flex gap-3">
            <a href="?page=module3/submodule1" class="inline-flex items-center gap-2 rounded-md bg-blue-700 hover:bg-blue-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-all active:scale-[0.98]">
                <i data-lucide="file-plus" class="w-4 h-4"></i>
                Issue Ticket
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm hover:border-amber-400 transition-colors">
            <div class="flex items-center justify-between mb-2">
                <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Pending Validation</div>
                <i data-lucide="alert-circle" class="w-4 h-4 text-amber-600 dark:text-amber-400"></i>
            </div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo number_format($pending); ?></div>
            <div class="mt-1 text-xs text-slate-500">Awaiting validation</div>
        </div>
        <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm hover:border-emerald-400 transition-colors">
            <div class="flex items-center justify-between mb-2">
                <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Settled Tickets</div>
                <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-600 dark:text-emerald-400"></i>
            </div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo number_format($settled); ?></div>
            <div class="mt-1 text-xs text-slate-500">Paid and closed</div>
        </div>
        <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm hover:border-rose-400 transition-colors">
            <div class="flex items-center justify-between mb-2">
                <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Escalated Cases</div>
                <i data-lucide="trending-up" class="w-4 h-4 text-rose-600 dark:text-rose-400"></i>
            </div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo number_format($escalated); ?></div>
            <div class="mt-1 text-xs text-slate-500">Action required</div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Collected Today</div>
            <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">₱<?php echo number_format($finesToday, 2); ?></div>
            <div class="text-xs text-slate-500 mt-1">Settled tickets issued today</div>
        </div>
        <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Collected This Month</div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white">₱<?php echo number_format($finesThisMonth, 2); ?></div>
            <div class="text-xs text-slate-500 mt-1">All settled tickets this month</div>
        </div>
        <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Outstanding Fines</div>
            <div class="text-2xl font-bold text-rose-600 dark:text-rose-400">₱<?php echo number_format($unsettledFines, 2); ?></div>
            <div class="text-xs text-slate-500 mt-1">Tickets not yet settled</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Work Queue -->
        <div class="lg:col-span-2 flex flex-col gap-6">
            <div class="overflow-hidden rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
                <div class="p-6 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between bg-slate-50 dark:bg-slate-700/30">
                    <div>
                        <h2 class="text-base font-bold text-slate-900 dark:text-white">Recent Tickets</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Latest violations issued</p>
                    </div>
                    <a href="?page=module3/submodule3" class="text-sm font-semibold text-blue-700 hover:text-blue-800">View All</a>
                </div>
                <div class="divide-y divide-slate-200 dark:divide-slate-700">
                    <?php if ($queue && $queue->num_rows > 0): ?>
                        <?php while ($row = $queue->fetch_assoc()): ?>
                            <?php
                                $status = $row['status'] ?? 'Pending';
                                $badgeClass = match($status) {
                                    'Validated' => 'bg-blue-50 text-blue-700 ring-blue-600/20 dark:bg-blue-900/20 dark:text-blue-400',
                                    'Settled' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-900/20 dark:text-emerald-400',
                                    'Escalated' => 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-900/20 dark:text-rose-400',
                                    default => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-900/20 dark:text-amber-400'
                                };
                                $issued = $row['date_issued'] ?? '';
                            ?>
                            <div class="p-4 hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <div class="h-10 w-10 rounded-md bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-slate-600 dark:text-slate-200 font-bold text-xs">
                                        TC
                                    </div>
                                    <div>
                                        <div class="font-semibold text-slate-900 dark:text-white text-sm"><?php echo htmlspecialchars($row['ticket_number'] ?? ''); ?></div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-1">
                                            <?php echo htmlspecialchars($row['vehicle_plate'] ?? 'Unknown'); ?>
                                            <?php if (!empty($row['violation_code'])): ?>
                                                <span class="text-slate-300">•</span>
                                                <?php echo htmlspecialchars($row['violation_code']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-bold ring-1 ring-inset <?php echo $badgeClass; ?>">
                                        <?php echo htmlspecialchars($status); ?>
                                    </span>
                                    <a href="?page=module3/submodule3&q=<?php echo urlencode($row['ticket_number'] ?? ''); ?>" class="p-2 rounded-md text-slate-400 hover:text-blue-700 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="p-8 text-center text-slate-500 text-sm font-medium">No tickets found.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="flex flex-col gap-6">
            <!-- Quick Actions -->
            <div class="rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm p-6">
                <h3 class="text-base font-bold text-slate-900 dark:text-white mb-4">Quick Actions</h3>
                <div class="space-y-3">
                    <a href="?page=module3/submodule2" class="flex items-center justify-between p-3 rounded-md bg-slate-50 dark:bg-slate-700/30 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                        <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Validate & Payment</span>
                        <i data-lucide="badge-check" class="w-4 h-4 text-slate-400"></i>
                    </a>
                    <a href="?page=module3/submodule3" class="flex items-center justify-between p-3 rounded-md bg-slate-50 dark:bg-slate-700/30 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                        <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Analytics & Reports</span>
                        <i data-lucide="bar-chart-3" class="w-4 h-4 text-slate-400"></i>
                    </a>
                </div>
            </div>

            <!-- Top Violations -->
            <div class="rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Top Violations</h3>
                    <i data-lucide="bar-chart-2" class="w-4 h-4 text-slate-400"></i>
                </div>
                <div class="space-y-3">
                    <?php if (!empty($topViolations)): ?>
                        <?php foreach ($topViolations as $i => $v): ?>
                            <div class="flex items-center justify-between p-2 rounded-md hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                                <div class="flex items-center gap-3">
                                    <span class="flex items-center justify-center w-6 h-6 rounded-md bg-slate-100 dark:bg-slate-700 text-xs font-bold text-slate-600 dark:text-slate-200"><?php echo $i + 1; ?></span>
                                    <span class="text-sm font-semibold text-slate-900 dark:text-slate-200"><?php echo htmlspecialchars($v['violation_code'] !== '' ? $v['violation_code'] : 'Unspecified'); ?></span>
                                </div>
                                <span class="text-xs font-bold text-slate-500"><?php echo (int)$v['cnt']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-sm text-slate-500 text-center py-4">No violation data available.</div>
                    <?php endif; ?>
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
