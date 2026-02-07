<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('module3.analytics');

require_once __DIR__ . '/../../includes/db.php';
$db = db();

$countViolations = (int)($db->query("SELECT COUNT(*) AS c FROM violations")->fetch_assoc()['c'] ?? 0);
$countPendingV = (int)($db->query("SELECT COUNT(*) AS c FROM violations WHERE workflow_status='Pending'")->fetch_assoc()['c'] ?? 0);
$countVerifiedV = (int)($db->query("SELECT COUNT(*) AS c FROM violations WHERE workflow_status='Verified'")->fetch_assoc()['c'] ?? 0);
$countClosedV = (int)($db->query("SELECT COUNT(*) AS c FROM violations WHERE workflow_status='Closed'")->fetch_assoc()['c'] ?? 0);

$countSts = (int)($db->query("SELECT COUNT(*) AS c FROM sts_tickets")->fetch_assoc()['c'] ?? 0);
$countPendingPay = (int)($db->query("SELECT COUNT(*) AS c FROM sts_tickets WHERE status='Pending Payment'")->fetch_assoc()['c'] ?? 0);
$countPaid = (int)($db->query("SELECT COUNT(*) AS c FROM sts_tickets WHERE status='Paid'")->fetch_assoc()['c'] ?? 0);
$countClosed = (int)($db->query("SELECT COUNT(*) AS c FROM sts_tickets WHERE status='Closed'")->fetch_assoc()['c'] ?? 0);

$sumPaid = (float)($db->query("SELECT COALESCE(SUM(fine_amount),0) AS s FROM sts_tickets WHERE status='Paid'")->fetch_assoc()['s'] ?? 0);
$sumPending = (float)($db->query("SELECT COALESCE(SUM(fine_amount),0) AS s FROM sts_tickets WHERE status='Pending Payment'")->fetch_assoc()['s'] ?? 0);

$draftCount = (int)($db->query("SELECT COUNT(*) AS c
                               FROM violations v
                               LEFT JOIN sts_tickets t ON t.linked_violation_id=v.id
                               WHERE v.workflow_status IN ('Pending','Verified') AND t.sts_ticket_id IS NULL")->fetch_assoc()['c'] ?? 0);

$violationsPerVehicle = [];
$res = $db->query("SELECT plate_number, COUNT(*) AS cnt
                   FROM violations
                   GROUP BY plate_number
                   ORDER BY cnt DESC, plate_number ASC
                   LIMIT 20");
if ($res) while ($r = $res->fetch_assoc()) $violationsPerVehicle[] = $r;

$violationsPerOperator = [];
$res = $db->query("SELECT v.operator_id, COALESCE(NULLIF(o.registered_name,''), NULLIF(o.name,''), o.full_name) AS operator_name, COUNT(*) AS cnt
                   FROM violations v
                   LEFT JOIN operators o ON o.id=v.operator_id
                   WHERE v.operator_id IS NOT NULL AND v.operator_id<>0
                   GROUP BY v.operator_id
                   ORDER BY cnt DESC
                   LIMIT 20");
if ($res) while ($r = $res->fetch_assoc()) $violationsPerOperator[] = $r;

$ticketsByViolationType = [];
$res = $db->query("SELECT v.violation_type, vt.description AS violation_desc, COUNT(*) AS cnt,
                          SUM(CASE WHEN t.status='Paid' THEN 1 ELSE 0 END) AS paid_cnt,
                          SUM(CASE WHEN t.status='Pending Payment' THEN 1 ELSE 0 END) AS pending_cnt
                   FROM sts_tickets t
                   LEFT JOIN violations v ON v.id=t.linked_violation_id
                   LEFT JOIN violation_types vt ON vt.violation_code=v.violation_type
                   GROUP BY v.violation_type, vt.description
                   ORDER BY cnt DESC
                   LIMIT 20");
if ($res) while ($r = $res->fetch_assoc()) $ticketsByViolationType[] = $r;

$repeatOffenders = [];
$res = $db->query("SELECT v.plate_number, COUNT(*) AS cnt,
                          MAX(v.violation_date) AS last_seen
                   FROM violations v
                   WHERE v.violation_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                   GROUP BY v.plate_number
                   HAVING cnt >= 2
                   ORDER BY cnt DESC, last_seen DESC
                   LIMIT 20");
if ($res) while ($r = $res->fetch_assoc()) $repeatOffenders[] = $r;

$draftViolations = [];
$res = $db->query("SELECT v.id, v.plate_number, v.violation_type, vt.description AS violation_desc, v.location, v.violation_date, v.workflow_status
                   FROM violations v
                   LEFT JOIN sts_tickets t ON t.linked_violation_id=v.id
                   LEFT JOIN violation_types vt ON vt.violation_code=v.violation_type
                   WHERE v.workflow_status IN ('Pending','Verified') AND t.sts_ticket_id IS NULL
                   ORDER BY v.violation_date DESC, v.id DESC
                   LIMIT 30");
if ($res) while ($r = $res->fetch_assoc()) $draftViolations[] = $r;
?>

<div class="mx-auto max-w-7xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-8">
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between border-b border-slate-200 dark:border-slate-700 pb-6">
    <div>
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Reports & Monitoring</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Compliance overview for violations, STS tickets, payments, and repeat offenders.</p>
    </div>
  </div>

  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Violations</div>
      <div class="mt-2 text-2xl font-black"><?php echo number_format($countViolations); ?></div>
      <div class="mt-2 text-xs font-semibold text-slate-500">Pending: <?php echo number_format($countPendingV); ?> • Verified: <?php echo number_format($countVerifiedV); ?> • Closed: <?php echo number_format($countClosedV); ?></div>
    </div>
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">STS Tickets</div>
      <div class="mt-2 text-2xl font-black"><?php echo number_format($countSts); ?></div>
      <div class="mt-2 text-xs font-semibold text-slate-500">Pending: <?php echo number_format($countPendingPay); ?> • Paid: <?php echo number_format($countPaid); ?> • Closed: <?php echo number_format($countClosed); ?></div>
    </div>
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Paid Total</div>
      <div class="mt-2 text-2xl font-black text-emerald-600">₱<?php echo number_format($sumPaid, 2); ?></div>
      <div class="mt-2 text-xs font-semibold text-slate-500">From STS tickets marked Paid</div>
    </div>
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Pending Amount</div>
      <div class="mt-2 text-2xl font-black text-amber-600">₱<?php echo number_format($sumPending, 2); ?></div>
      <div class="mt-2 text-xs font-semibold text-slate-500">Pending payment</div>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
      <div class="p-5 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30">
        <div class="text-base font-black">Violations per Vehicle</div>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700">
            <tr class="text-left text-xs font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">
              <th class="px-5 py-3">Plate</th>
              <th class="px-5 py-3">Count</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
            <?php if (!$violationsPerVehicle): ?>
              <tr><td colspan="2" class="px-5 py-6 text-center text-slate-500">No data</td></tr>
            <?php else: foreach ($violationsPerVehicle as $r): ?>
              <tr>
                <td class="px-5 py-3 font-black"><?php echo htmlspecialchars((string)($r['plate_number'] ?? '')); ?></td>
                <td class="px-5 py-3 font-black"><?php echo number_format((int)($r['cnt'] ?? 0)); ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
      <div class="p-5 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30">
        <div class="text-base font-black">Violations per Operator</div>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700">
            <tr class="text-left text-xs font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">
              <th class="px-5 py-3">Operator</th>
              <th class="px-5 py-3">Count</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
            <?php if (!$violationsPerOperator): ?>
              <tr><td colspan="2" class="px-5 py-6 text-center text-slate-500">No data</td></tr>
            <?php else: foreach ($violationsPerOperator as $r): ?>
              <tr>
                <td class="px-5 py-3 font-semibold"><?php echo htmlspecialchars((string)($r['operator_name'] ?? ('Operator #' . (string)($r['operator_id'] ?? '')))); ?></td>
                <td class="px-5 py-3 font-black"><?php echo number_format((int)($r['cnt'] ?? 0)); ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
      <div class="p-5 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30">
        <div class="text-base font-black">Tickets per Violation Type</div>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700">
            <tr class="text-left text-xs font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">
              <th class="px-5 py-3">Type</th>
              <th class="px-5 py-3">Total</th>
              <th class="px-5 py-3">Paid</th>
              <th class="px-5 py-3">Pending</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
            <?php if (!$ticketsByViolationType): ?>
              <tr><td colspan="4" class="px-5 py-6 text-center text-slate-500">No data</td></tr>
            <?php else: foreach ($ticketsByViolationType as $r): ?>
              <tr>
                <td class="px-5 py-3 font-semibold"><?php echo htmlspecialchars((string)($r['violation_type'] ?? '-')); ?><?php if (!empty($r['violation_desc'])): ?> • <?php echo htmlspecialchars((string)$r['violation_desc']); ?><?php endif; ?></td>
                <td class="px-5 py-3 font-black"><?php echo number_format((int)($r['cnt'] ?? 0)); ?></td>
                <td class="px-5 py-3 font-black text-emerald-600"><?php echo number_format((int)($r['paid_cnt'] ?? 0)); ?></td>
                <td class="px-5 py-3 font-black text-amber-600"><?php echo number_format((int)($r['pending_cnt'] ?? 0)); ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
      <div class="p-5 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30">
        <div class="text-base font-black">Repeat Offenders (90 days)</div>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700">
            <tr class="text-left text-xs font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">
              <th class="px-5 py-3">Plate</th>
              <th class="px-5 py-3">Count</th>
              <th class="px-5 py-3">Last Seen</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
            <?php if (!$repeatOffenders): ?>
              <tr><td colspan="3" class="px-5 py-6 text-center text-slate-500">No data</td></tr>
            <?php else: foreach ($repeatOffenders as $r): ?>
              <tr>
                <td class="px-5 py-3 font-black"><?php echo htmlspecialchars((string)($r['plate_number'] ?? '')); ?></td>
                <td class="px-5 py-3 font-black"><?php echo number_format((int)($r['cnt'] ?? 0)); ?></td>
                <td class="px-5 py-3 text-xs font-semibold text-slate-600 dark:text-slate-300"><?php echo htmlspecialchars((string)($r['last_seen'] ?? '')); ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <div class="p-5 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30">
      <div class="text-base font-black">Draft Tickets Pending Official Issuance</div>
      <div class="text-sm text-slate-500 dark:text-slate-400 mt-1">Violations recorded but not yet linked to an official STS ticket. Total: <?php echo number_format($draftCount); ?></div>
    </div>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700">
          <tr class="text-left text-xs font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">
            <th class="px-5 py-3">Violation ID</th>
            <th class="px-5 py-3">Date</th>
            <th class="px-5 py-3">Plate</th>
            <th class="px-5 py-3">Type</th>
            <th class="px-5 py-3">Location</th>
            <th class="px-5 py-3">Workflow</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
          <?php if (!$draftViolations): ?>
            <tr><td colspan="6" class="px-5 py-6 text-center text-slate-500">No draft records</td></tr>
          <?php else: foreach ($draftViolations as $r): ?>
            <tr>
              <td class="px-5 py-3 font-black"><?php echo number_format((int)($r['id'] ?? 0)); ?></td>
              <td class="px-5 py-3 text-xs font-semibold text-slate-600 dark:text-slate-300"><?php echo htmlspecialchars((string)($r['violation_date'] ?? '')); ?></td>
              <td class="px-5 py-3 font-black"><?php echo htmlspecialchars((string)($r['plate_number'] ?? '')); ?></td>
              <td class="px-5 py-3 text-xs font-semibold"><?php echo htmlspecialchars((string)($r['violation_type'] ?? '')); ?><?php if (!empty($r['violation_desc'])): ?> • <?php echo htmlspecialchars((string)$r['violation_desc']); ?><?php endif; ?></td>
              <td class="px-5 py-3 text-xs font-semibold text-slate-600 dark:text-slate-300"><?php echo htmlspecialchars((string)($r['location'] ?? '')); ?></td>
              <td class="px-5 py-3 text-xs font-black"><?php echo htmlspecialchars((string)($r['workflow_status'] ?? '')); ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
