<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module2.view','module2.franchises.manage']);
$db = db();

$resPending = $db->query("SELECT COUNT(*) AS c FROM franchise_applications WHERE status='Pending' OR status='Under Review'");
$pending = (int)(($resPending && ($rowP = $resPending->fetch_assoc())) ? $rowP['c'] : 0);

$resEndorsed = $db->query("SELECT COUNT(*) AS c FROM franchise_applications WHERE status='Endorsed'");
$endorsed = (int)(($resEndorsed && ($rowE = $resEndorsed->fetch_assoc())) ? $rowE['c'] : 0);

$resCompliance = $db->query("SELECT COUNT(*) AS c FROM compliance_cases WHERE status='Open' OR status='Escalated'");
$compliance = (int)(($resCompliance && ($rowC = $resCompliance->fetch_assoc())) ? $rowC['c'] : 0);

$queue = $db->query("SELECT fa.franchise_ref_number, fa.status, fa.submitted_at, o.full_name AS operator, c.coop_name FROM franchise_applications fa LEFT JOIN operators o ON fa.operator_id=o.id LEFT JOIN coops c ON fa.coop_id=c.id ORDER BY fa.submitted_at DESC LIMIT 5");

$expiring = $db->query("SELECT er.endorsement_id, er.permit_number, er.expiry_date, fa.franchise_ref_number, o.full_name AS operator, c.coop_name FROM endorsement_records er LEFT JOIN franchise_applications fa ON er.application_id=fa.application_id LEFT JOIN operators o ON fa.operator_id=o.id LEFT JOIN coops c ON fa.coop_id=c.id WHERE er.expiry_date IS NOT NULL ORDER BY er.expiry_date ASC LIMIT 5");
?>

<div class="mx-auto max-w-7xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-8">
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between mb-2 border-b border-slate-200 dark:border-slate-700 pb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Franchise Management</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Handles LGU-level franchise endorsement workflows, permit issuance, validations, and monitoring aligned with LPTRP policies.</p>
        </div>
        <div class="flex gap-3">
            <a href="?page=module2/submodule1" class="inline-flex items-center gap-2 rounded-md bg-blue-700 hover:bg-blue-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-all active:scale-[0.98]">
                <i data-lucide="file-plus" class="w-4 h-4"></i>
                New Application
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm hover:border-orange-400 transition-colors">
            <div class="flex items-center justify-between mb-2">
                <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Pending Apps</div>
                <i data-lucide="file-clock" class="w-4 h-4 text-orange-600 dark:text-orange-400"></i>
            </div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo number_format($pending); ?></div>
            <div class="mt-1 text-xs text-slate-500">Pending or under review</div>
        </div>
        <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm hover:border-emerald-400 transition-colors">
            <div class="flex items-center justify-between mb-2">
                <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Endorsed</div>
                <i data-lucide="file-check" class="w-4 h-4 text-emerald-600 dark:text-emerald-400"></i>
            </div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo number_format($endorsed); ?></div>
            <div class="mt-1 text-xs text-slate-500">Approved franchises</div>
        </div>
        <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm hover:border-rose-400 transition-colors">
            <div class="flex items-center justify-between mb-2">
                <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Compliance Cases</div>
                <i data-lucide="alert-octagon" class="w-4 h-4 text-rose-600 dark:text-rose-400"></i>
            </div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo number_format($compliance); ?></div>
            <div class="mt-1 text-xs text-slate-500">Open or escalated</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Work Queue -->
        <div class="lg:col-span-2 flex flex-col gap-6">
            <div class="overflow-hidden rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
                <div class="p-6 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between bg-slate-50 dark:bg-slate-700/30">
                    <div>
                        <h2 class="text-base font-bold text-slate-900 dark:text-white">Recent Applications</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Latest submissions needing review</p>
                    </div>
                    <a href="?page=module2/submodule1" class="text-sm font-semibold text-blue-700 hover:text-blue-800">View All</a>
                </div>
                <div class="divide-y divide-slate-200 dark:divide-slate-700">
                    <?php if ($queue && $queue->num_rows > 0): ?>
                        <?php while ($row = $queue->fetch_assoc()): ?>
                            <?php
                                $status = $row['status'] ?? 'Pending';
                                $badgeClass = match($status) {
                                    'Endorsed' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-900/20 dark:text-emerald-400',
                                    'Rejected' => 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-900/20 dark:text-rose-400',
                                    'Under Review' => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-900/20 dark:text-amber-400',
                                    default => 'bg-slate-50 text-slate-700 ring-slate-600/20 dark:bg-slate-800 dark:text-slate-400'
                                };
                            ?>
                            <div class="p-4 hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors flex items-center justify-between group">
                                <div class="flex items-center gap-4">
                                    <div class="h-10 w-10 rounded-md bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-slate-600 dark:text-slate-200 font-bold text-xs">
                                        FR
                                    </div>
                                    <div>
                                        <div class="font-semibold text-slate-900 dark:text-white text-sm"><?php echo htmlspecialchars($row['franchise_ref_number']); ?></div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-1">
                                            <?php echo htmlspecialchars($row['operator'] ?? 'Unknown'); ?>
                                            <?php if (!empty($row['coop_name'])): ?>
                                                <span class="text-slate-300">•</span>
                                                <?php echo htmlspecialchars($row['coop_name']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-bold ring-1 ring-inset <?php echo $badgeClass; ?>">
                                        <?php echo htmlspecialchars($status); ?>
                                    </span>
                                    <a href="?page=module2/submodule1&q=<?php echo urlencode($row['franchise_ref_number']); ?>" class="p-2 rounded-md text-slate-400 hover:text-blue-700 hover:bg-slate-100 dark:hover:bg-slate-700 transition-all">
                                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="p-8 text-center text-slate-500 text-sm font-medium">No pending applications found.</div>
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
                    <a href="?page=module2/submodule2" class="flex items-center justify-between p-3 rounded-md bg-slate-50 dark:bg-slate-700/30 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                        <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Generate Permit</span>
                        <i data-lucide="stamp" class="w-4 h-4 text-slate-400"></i>
                    </a>
                    <a href="?page=module2/submodule3" class="flex items-center justify-between p-3 rounded-md bg-slate-50 dark:bg-slate-700/30 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                        <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Manage Renewals</span>
                        <i data-lucide="refresh-ccw" class="w-4 h-4 text-slate-400"></i>
                    </a>
                    <a href="?page=module2/submodule2#compliance" class="flex items-center justify-between p-3 rounded-md bg-slate-50 dark:bg-slate-700/30 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                        <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Compliance & Violations</span>
                        <i data-lucide="shield-alert" class="w-4 h-4 text-slate-400"></i>
                    </a>
                </div>
            </div>

            <!-- Expiring Permits -->
            <div class="rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Expiring Soon</h3>
                    <i data-lucide="calendar-clock" class="w-4 h-4 text-amber-500"></i>
                </div>
                <div class="space-y-4">
                    <?php if ($expiring && $expiring->num_rows > 0): ?>
                        <?php while ($er = $expiring->fetch_assoc()): ?>
                            <?php
                                $expiryRaw = $er['expiry_date'] ?? null;
                                $days = 0;
                                $label = 'N/A';
                                $color = 'text-slate-500';
                                
                                if ($expiryRaw) {
                                    $days = floor((strtotime($expiryRaw) - time()) / 86400);
                                    if ($days < 0) { $label = abs($days).'d ago'; $color = 'text-rose-500'; }
                                    elseif ($days == 0) { $label = 'Today'; $color = 'text-amber-500'; }
                                    else { $label = 'In '.$days.'d'; $color = $days <= 30 ? 'text-amber-500' : 'text-emerald-500'; }
                                }
                            ?>
                            <div class="flex items-start justify-between">
                                <div>
                                    <div class="text-xs font-black text-slate-700 dark:text-slate-200"><?php echo htmlspecialchars($er['permit_number'] ?? 'PERMIT'); ?></div>
                                    <div class="text-[10px] font-bold text-slate-400 uppercase mt-0.5">
                                        Ref: <?php echo htmlspecialchars($er['franchise_ref_number'] ?? 'N/A'); ?>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-xs font-black <?php echo $color; ?>"><?php echo $label; ?></div>
                                    <div class="text-[10px] font-medium text-slate-400"><?php echo htmlspecialchars($er['expiry_date'] ?? ''); ?></div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-sm text-slate-500 text-center py-4">No permits expiring soon.</div>
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
