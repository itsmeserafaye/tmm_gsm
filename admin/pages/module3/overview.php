<?php
require_once __DIR__ . '/../../includes/db.php';
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

<div class="mx-1 mt-1 p-6 dark:bg-slate-900 bg-white dark:text-slate-300 rounded-lg">
  <h1 class="text-2xl font-bold mb-2">Traffic Violation & Ticketing — Overview</h1>
  <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">Digital enforcement and citation management aligned with MMDA STS, with LGU workflows for issuance, payment, compliance, and reporting.</p>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="p-4 border rounded-lg dark:border-slate-700 bg-white dark:bg-slate-900 shadow-sm">
      <div class="flex items-center justify-between">
        <div>
          <div class="text-sm text-slate-500">Pending Validation</div>
          <div class="text-3xl font-bold mt-1"><?php echo $pending; ?></div>
        </div>
        <div class="p-2 rounded-full bg-amber-50 dark:bg-amber-900/30 text-amber-500">
          <i data-lucide="alert-circle" class="w-6 h-6"></i>
        </div>
      </div>
    </div>
    <div class="p-4 border rounded-lg dark:border-slate-700 bg-white dark:bg-slate-900 shadow-sm">
      <div class="flex items-center justify-between">
        <div>
          <div class="text-sm text-slate-500">Settled Tickets</div>
          <div class="text-3xl font-bold mt-1"><?php echo $settled; ?></div>
        </div>
        <div class="p-2 rounded-full bg-emerald-50 dark:bg-emerald-900/30 text-emerald-500">
          <i data-lucide="check-circle-2" class="w-6 h-6"></i>
        </div>
      </div>
    </div>
    <div class="p-4 border rounded-lg dark:border-slate-700 bg-white dark:bg-slate-900 shadow-sm">
      <div class="flex items-center justify-between">
        <div>
          <div class="text-sm text-slate-500">Escalated Cases</div>
          <div class="text-3xl font-bold mt-1"><?php echo $escalated; ?></div>
        </div>
        <div class="p-2 rounded-full bg-rose-50 dark:bg-rose-900/30 text-rose-500">
          <i data-lucide="trending-up" class="w-6 h-6"></i>
        </div>
      </div>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
    <div class="p-4 border rounded-lg dark:border-slate-700 bg-white dark:bg-slate-900 shadow-sm">
      <div class="text-sm text-slate-500">Fines Collected Today</div>
      <div class="text-2xl font-bold mt-1 text-emerald-600 dark:text-emerald-400">₱<?php echo number_format($finesToday, 2); ?></div>
      <div class="text-xs text-slate-500 mt-1">Settled tickets issued today</div>
    </div>
    <div class="p-4 border rounded-lg dark:border-slate-700 bg-white dark:bg-slate-900 shadow-sm">
      <div class="text-sm text-slate-500">Fines This Month</div>
      <div class="text-2xl font-bold mt-1 text-slate-800 dark:text-slate-100">₱<?php echo number_format($finesThisMonth, 2); ?></div>
      <div class="text-xs text-slate-500 mt-1">All settled tickets this month</div>
    </div>
    <div class="p-4 border rounded-lg dark:border-slate-700 bg-white dark:bg-slate-900 shadow-sm">
      <div class="text-sm text-slate-500">Outstanding Fines</div>
      <div class="text-2xl font-bold mt-1 text-rose-600 dark:text-rose-400">₱<?php echo number_format($unsettledFines, 2); ?></div>
      <div class="text-xs text-slate-500 mt-1">Tickets not yet settled</div>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
    <div class="p-4 border rounded-lg dark:border-slate-700 bg-white dark:bg-slate-900">
      <h2 class="text-lg font-semibold mb-3">Work Queue</h2>
      <ul class="text-sm space-y-2">
        <?php if ($queue && $queue->num_rows > 0): ?>
          <?php while ($row = $queue->fetch_assoc()): ?>
            <?php
              $status = $row['status'] ?? 'Pending';
              $badgeClass = 'bg-amber-100 text-amber-700';
              if ($status === 'Validated') $badgeClass = 'bg-blue-100 text-blue-700';
              elseif ($status === 'Settled') $badgeClass = 'bg-emerald-100 text-emerald-700';
              elseif ($status === 'Escalated') $badgeClass = 'bg-rose-100 text-rose-700';
              $issued = $row['date_issued'] ?? '';
            ?>
            <li class="flex items-center justify-between gap-3 py-2">
              <div class="flex flex-col">
                <span class="font-medium">
                  <?php echo htmlspecialchars($row['ticket_number'] ?? ''); ?>
                </span>
                <span class="text-xs text-slate-500">
                  <?php echo htmlspecialchars($row['vehicle_plate'] ?? 'Unknown plate'); ?>
                  <?php if (!empty($row['violation_code'])): ?>
                    • <?php echo htmlspecialchars($row['violation_code']); ?>
                  <?php endif; ?>
                  <?php if ($issued !== ''): ?>
                    • <?php echo htmlspecialchars($issued); ?>
                  <?php endif; ?>
                </span>
              </div>
              <div class="flex items-center gap-2">
                <span class="px-2 py-0.5 rounded-full text-xs font-medium <?php echo $badgeClass; ?>">
                  <?php echo htmlspecialchars($status); ?>
                </span>
                <a href="?page=module3/submodule3&q=<?php echo urlencode($row['ticket_number'] ?? ''); ?>" class="px-2 py-1 border rounded text-xs hover:bg-slate-50 dark:hover:bg-slate-800">Open</a>
              </div>
            </li>
          <?php endwhile; ?>
        <?php else: ?>
          <li class="text-slate-500">No tickets found in the work queue.</li>
        <?php endif; ?>
      </ul>
    </div>
    <div class="p-4 border rounded-lg dark:border-slate-700 bg-white dark:bg-slate-900">
      <h2 class="text-lg font-semibold mb-3">Quick Actions</h2>
      <div class="flex flex-wrap gap-2">
        <a href="?page=module3/submodule1" class="inline-flex items-center justify-between px-3 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-medium">
          <span>New Ticket</span>
          <i data-lucide="file-plus" class="w-4 h-4"></i>
        </a>
        <a href="?page=module3/submodule2" class="inline-flex items-center justify-between px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 text-sm">
          <span>Validate & Payment</span>
          <i data-lucide="badge-check" class="w-4 h-4"></i>
        </a>
        <a href="?page=module3/submodule3" class="inline-flex items-center justify-between px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 text-sm">
          <span>Analytics & Reports</span>
          <i data-lucide="bar-chart-3" class="w-4 h-4"></i>
        </a>
      </div>
    </div>
    <div class="p-4 border rounded-lg dark:border-slate-700 bg-white dark:bg-slate-900 mt-6 md:mt-0">
      <h2 class="text-lg font-semibold mb-3">Top Violation Types</h2>
      <?php if (!empty($topViolations)): ?>
        <ul class="text-sm space-y-1">
          <?php foreach ($topViolations as $v): ?>
            <li class="flex items-center justify-between">
              <span class="text-slate-700 dark:text-slate-200"><?php echo htmlspecialchars($v['violation_code'] !== '' ? $v['violation_code'] : 'Unspecified'); ?></span>
              <span class="text-xs text-slate-500"><?php echo (int)$v['cnt']; ?> tickets</span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <div class="text-sm text-slate-500">No violation data available.</div>
      <?php endif; ?>
    </div>
  </div>
</div>
