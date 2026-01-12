<?php
require_once __DIR__ . '/../../includes/db.php';
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
<div class="mx-1 mt-1 p-4 md:p-6 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 rounded-lg">
  <h1 class="text-2xl font-bold mb-2">Franchise Management — Overview</h1>
  <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">Handles LGU-level franchise endorsement workflows, permit issuance, validations, and monitoring aligned with LPTRP policies.</p>
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="p-4 border rounded-lg dark:border-slate-700 bg-white dark:bg-slate-900 shadow-sm">
      <div class="flex items-center justify-between">
        <div>
          <div class="text-sm text-slate-500">Pending Applications</div>
          <div class="text-3xl font-bold mt-1"><?php echo $pending; ?></div>
        </div>
        <div class="p-2 rounded-full bg-amber-50 dark:bg-amber-900/30 text-amber-500">
          <i data-lucide="clock" class="w-6 h-6"></i>
        </div>
      </div>
    </div>
    <div class="p-4 border rounded-lg dark:border-slate-700 bg-white dark:bg-slate-900 shadow-sm">
      <div class="flex items-center justify-between">
        <div>
          <div class="text-sm text-slate-500">Endorsed</div>
          <div class="text-3xl font-bold mt-1"><?php echo $endorsed; ?></div>
        </div>
        <div class="p-2 rounded-full bg-emerald-50 dark:bg-emerald-900/30 text-emerald-500">
          <i data-lucide="check-circle-2" class="w-6 h-6"></i>
        </div>
      </div>
    </div>
    <div class="p-4 border rounded-lg dark:border-slate-700 bg-white dark:bg-slate-900 shadow-sm">
      <div class="flex items-center justify-between">
        <div>
          <div class="text-sm text-slate-500">Open Compliance Cases</div>
          <div class="text-3xl font-bold mt-1"><?php echo $compliance; ?></div>
        </div>
        <div class="p-2 rounded-full bg-rose-50 dark:bg-rose-900/30 text-rose-500">
          <i data-lucide="alert-circle" class="w-6 h-6"></i>
        </div>
      </div>
    </div>
  </div>
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">
    <div class="lg:col-span-2 p-4 border rounded-lg dark:border-slate-700 bg-white dark:bg-slate-900">
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-lg font-semibold">Work Queue</h2>
        <a href="?page=module1/submodule2" class="text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline">View all applications</a>
      </div>
      <ul class="text-sm divide-y divide-slate-200 dark:divide-slate-800">
        <?php if ($queue && $queue->num_rows > 0): ?>
          <?php while ($row = $queue->fetch_assoc()): ?>
            <?php
              $status = $row['status'] ?? 'Pending';
              $badgeClass = 'bg-slate-100 text-slate-700';
              if ($status === 'Endorsed') $badgeClass = 'bg-emerald-100 text-emerald-700';
              elseif ($status === 'Rejected') $badgeClass = 'bg-rose-100 text-rose-700';
              elseif ($status === 'Under Review') $badgeClass = 'bg-amber-100 text-amber-700';
            ?>
            <li class="flex items-center justify-between py-2">
              <div>
                <div class="font-medium"><?php echo htmlspecialchars($row['franchise_ref_number']); ?></div>
                <div class="text-xs text-slate-500">
                  <?php echo htmlspecialchars($row['operator'] ?? 'Unknown Operator'); ?>
                  <?php if (!empty($row['coop_name'])): ?>
                    • <?php echo htmlspecialchars($row['coop_name']); ?>
                  <?php endif; ?>
                </div>
              </div>
              <div class="flex items-center gap-2">
                <span class="px-2 py-0.5 rounded-full text-xs font-medium <?php echo $badgeClass; ?>">
                  <?php echo htmlspecialchars($status); ?>
                </span>
                <a href="?page=module1/submodule2&q=<?php echo urlencode($row['franchise_ref_number']); ?>" class="px-2 py-1 border rounded text-xs hover:bg-slate-50 dark:hover:bg-slate-800">Open</a>
              </div>
            </li>
          <?php endwhile; ?>
        <?php else: ?>
          <li class="py-4 text-sm text-slate-500 text-center">No franchise applications found.</li>
        <?php endif; ?>
      </ul>
    </div>
    <div class="p-4 border rounded-lg dark:border-slate-700 bg-white dark:bg-slate-900">
      <h2 class="text-lg font-semibold mb-3">Quick Actions</h2>
      <div class="flex flex-col gap-2 mb-4">
        <a href="?page=module1/submodule2" class="inline-flex items-center justify-between px-3 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-medium">
          <span>New Endorsement Application</span>
          <i data-lucide="file-plus" class="w-4 h-4"></i>
        </a>
        <a href="?page=module2/submodule2" class="inline-flex items-center justify-between px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 text-sm">
          <span>Generate Endorsement / Permit</span>
          <i data-lucide="stamp" class="w-4 h-4"></i>
        </a>
        <a href="?page=module2/submodule3" class="inline-flex items-center justify-between px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 text-sm">
          <span>Manage Renewals</span>
          <i data-lucide="refresh-ccw" class="w-4 h-4"></i>
        </a>
        <a href="?page=module2/submodule2#compliance" class="inline-flex items-center justify-between px-3 py-2 rounded-lg border border-rose-200 dark:border-rose-700 text-sm text-rose-600 dark:text-rose-400">
          <span>Compliance & Violations</span>
          <i data-lucide="shield-alert" class="w-4 h-4"></i>
        </a>
      </div>
      <h3 class="text-sm font-semibold mb-2 text-slate-700 dark:text-slate-300">Permits nearing expiry</h3>
      <ul class="text-xs space-y-1 max-h-40 overflow-y-auto">
        <?php if ($expiring && $expiring->num_rows > 0): ?>
          <?php while ($er = $expiring->fetch_assoc()): ?>
            <?php
              $expiryRaw = $er['expiry_date'] ?? null;
              $expiryLabel = 'N/A';
              $tagClass = 'text-slate-500';
              if ($expiryRaw) {
                $expTs = strtotime($expiryRaw);
                if ($expTs !== false) {
                  $days = floor(($expTs - time()) / 86400);
                  if ($days < 0) {
                    $expiryLabel = 'Expired ' . abs($days) . 'd ago';
                    $tagClass = 'text-rose-600 dark:text-rose-400';
                  } elseif ($days === 0) {
                    $expiryLabel = 'Expires today';
                    $tagClass = 'text-amber-600 dark:text-amber-400';
                  } elseif ($days <= 30) {
                    $expiryLabel = 'In ' . $days . 'd';
                    $tagClass = 'text-amber-600 dark:text-amber-400';
                  } else {
                    $expiryLabel = 'In ' . $days . 'd';
                    $tagClass = 'text-slate-600 dark:text-slate-300';
                  }
                }
              }
            ?>
            <?php
              $ref = $er['franchise_ref_number'] ?? '';
              $href = $ref !== '' ? '?page=module1/submodule2&q=' . urlencode($ref) : '';
            ?>
            <li>
              <?php if ($href !== ''): ?>
                <a href="<?php echo htmlspecialchars($href); ?>" class="flex items-center justify-between py-1 px-2 -mx-2 rounded hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer">
                  <div>
                    <div class="font-medium">
                      <?php echo htmlspecialchars($er['permit_number'] ?? 'PERMIT'); ?>
                    </div>
                    <div class="text-[11px] text-slate-500">
                      <?php if (!empty($er['operator']) || !empty($er['coop_name'])): ?>
                        <?php echo htmlspecialchars($er['operator'] ?? ''); ?>
                        <?php if (!empty($er['operator']) && !empty($er['coop_name'])): ?> • <?php endif; ?>
                        <?php echo htmlspecialchars($er['coop_name'] ?? ''); ?>
                        <span class="mx-1 text-slate-400">•</span>
                      <?php endif; ?>
                      Ref: <?php echo htmlspecialchars($er['franchise_ref_number'] ?? 'N/A'); ?>
                    </div>
                  </div>
                  <div class="text-[11px] text-right flex flex-col items-end">
                    <div class="flex items-center gap-1 <?php echo $tagClass; ?>">
                      <span><?php echo htmlspecialchars($expiryLabel); ?></span>
                      <i data-lucide="arrow-up-right" class="w-3 h-3 text-slate-400"></i>
                    </div>
                    <div class="text-slate-400 dark:text-slate-500">
                      <?php echo htmlspecialchars($er['expiry_date'] ?? ''); ?>
                    </div>
                  </div>
                </a>
              <?php else: ?>
                <div class="flex items-center justify-between py-1">
                  <div>
                    <div class="font-medium">
                      <?php echo htmlspecialchars($er['permit_number'] ?? 'PERMIT'); ?>
                    </div>
                    <div class="text-[11px] text-slate-500">
                      <?php if (!empty($er['operator']) || !empty($er['coop_name'])): ?>
                        <?php echo htmlspecialchars($er['operator'] ?? ''); ?>
                        <?php if (!empty($er['operator']) && !empty($er['coop_name'])): ?> • <?php endif; ?>
                        <?php echo htmlspecialchars($er['coop_name'] ?? ''); ?>
                        <span class="mx-1 text-slate-400">•</span>
                      <?php endif; ?>
                      Ref: <?php echo htmlspecialchars($er['franchise_ref_number'] ?? 'N/A'); ?>
                    </div>
                  </div>
                  <div class="text-[11px] text-right">
                    <div class="<?php echo $tagClass; ?>"><?php echo htmlspecialchars($expiryLabel); ?></div>
                    <div class="text-slate-400 dark:text-slate-500">
                      <?php echo htmlspecialchars($er['expiry_date'] ?? ''); ?>
                    </div>
                  </div>
                </div>
              <?php endif; ?>
            </li>
          <?php endwhile; ?>
        <?php else: ?>
          <li class="py-2 text-slate-500">No expiry data available yet.</li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</div>
