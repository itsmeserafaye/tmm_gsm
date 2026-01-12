<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();

$period = $_GET['period'] ?? '30d';
$statusFilter = $_GET['status'] ?? '';
$coopFilter = isset($_GET['coop_id']) ? (int)$_GET['coop_id'] : 0;

$periodOptions = [
  '30d' => 'Last 30 days',
  '90d' => 'Last 90 days',
  'year' => 'This year',
  'all' => 'All time'
];
if (!isset($periodOptions[$period])) {
  $period = '30d';
}

$coops = [];
$resCoops = $db->query("SELECT id, coop_name FROM coops ORDER BY coop_name ASC");
if ($resCoops) {
  while ($row = $resCoops->fetch_assoc()) {
    $coops[] = $row;
  }
}

$where = [];
if ($period === '30d') {
  $where[] = "er.issued_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
} elseif ($period === '90d') {
  $where[] = "er.issued_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)";
} elseif ($period === 'year') {
  $where[] = "YEAR(er.issued_date) = YEAR(CURDATE())";
}
if ($statusFilter !== '') {
  $statusEsc = $db->real_escape_string($statusFilter);
  $where[] = "fa.status = '$statusEsc'";
}
if ($coopFilter > 0) {
  $where[] = "c.id = $coopFilter";
}

$sql = "SELECT er.endorsement_id,
               fa.application_id,
               fa.franchise_ref_number,
               fa.status,
               fa.submitted_at,
               c.coop_name,
               er.issued_date,
               er.permit_number,
               COALESCE(er.issued_date, fa.submitted_at) AS effective_date
        FROM franchise_applications fa
        LEFT JOIN endorsement_records er ON er.application_id = fa.application_id
        LEFT JOIN coops c ON fa.coop_id = c.id";
if ($where) {
  $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY effective_date DESC, fa.application_id DESC LIMIT 100";

$endorsements = [];
$resEnd = $db->query($sql);
if ($resEnd) {
  while ($row = $resEnd->fetch_assoc()) {
    $endorsements[] = $row;
  }
}

$upcomingSql = "SELECT er.issued_date,
                       DATE_ADD(er.issued_date, INTERVAL 1 YEAR) AS expiry_date,
                       er.permit_number,
                       fa.franchise_ref_number,
                       c.coop_name
                FROM endorsement_records er
                LEFT JOIN franchise_applications fa ON er.application_id = fa.application_id
                LEFT JOIN coops c ON fa.coop_id = c.id
                WHERE DATE_ADD(er.issued_date, INTERVAL 1 YEAR) >= CURDATE()
                  AND DATE_ADD(er.issued_date, INTERVAL 1 YEAR) <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)
                ORDER BY expiry_date ASC
                LIMIT 10";
$renewals = [];
$resRen = $db->query($upcomingSql);
if ($resRen) {
  while ($row = $resRen->fetch_assoc()) {
    $renewals[] = $row;
  }
}

function format_badge_class($status) {
  $s = strtoupper((string)$status);
  if ($s === 'ENDORSED') return 'bg-emerald-100 text-emerald-700';
  if ($s === 'REJECTED') return 'bg-rose-100 text-rose-700';
  if ($s === 'UNDER REVIEW') return 'bg-amber-100 text-amber-700';
  if ($s === 'PENDING') return 'bg-slate-100 text-slate-700';
  return 'bg-slate-100 text-slate-700';
}
?>

<div class="mx-1 mt-1 p-4 md:p-6 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 rounded-lg">
  <h1 class="text-2xl font-bold mb-2">Renewals, Monitoring & Reporting</h1>
  <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">Track permit validity, upcoming renewals, and recent endorsements.</p>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="p-4 border rounded-lg dark:border-slate-700">
      <h2 class="text-lg font-semibold mb-3">Upcoming Renewals (next 90 days)</h2>
      <ul class="text-sm space-y-2 max-h-64 overflow-y-auto">
        <?php if (empty($renewals)): ?>
          <li class="text-slate-500">No renewals due in the next 90 days.</li>
        <?php else: ?>
          <?php foreach ($renewals as $r): ?>
            <li class="flex items-center justify-between gap-3">
              <div class="flex flex-col">
                <span class="font-medium">
                  <?php echo htmlspecialchars($r['coop_name'] ?: ($r['franchise_ref_number'] ?: 'Unknown entity')); ?>
                </span>
                <span class="text-xs text-slate-500">
                  Permit <?php echo htmlspecialchars($r['permit_number'] ?? ''); ?>
                </span>
              </div>
              <div class="text-xs text-right">
                <div class="font-semibold text-amber-700 dark:text-amber-400">
                  <?php echo htmlspecialchars($r['expiry_date']); ?>
                </div>
                <div class="text-slate-500">Expiry date</div>
              </div>
            </li>
          <?php endforeach; ?>
        <?php endif; ?>
      </ul>
    </div>

    <div class="p-4 border rounded-lg dark:border-slate-700">
      <h2 class="text-lg font-semibold mb-3">Reporting Filters</h2>
      <form class="grid grid-cols-1 md:grid-cols-4 gap-3" method="GET">
        <input type="hidden" name="page" value="module2/submodule3">
        <div>
          <label class="block text-xs font-medium text-slate-500 mb-1">Period</label>
          <select name="period" class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" onchange="this.form.submit()">
            <?php foreach ($periodOptions as $key => $label): ?>
              <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $key === $period ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($label); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-500 mb-1">Status</label>
          <select name="status" class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" onchange="this.form.submit()">
            <option value="">All</option>
            <?php foreach (['Pending','Under Review','Endorsed','Rejected'] as $st): ?>
              <option value="<?php echo htmlspecialchars($st); ?>" <?php echo $statusFilter === $st ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($st); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-500 mb-1">Cooperative</label>
          <select name="coop_id" class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700" onchange="this.form.submit()">
            <option value="0">All cooperatives</option>
            <?php foreach ($coops as $c): ?>
              <option value="<?php echo (int)$c['id']; ?>" <?php echo $coopFilter === (int)$c['id'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($c['coop_name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </form>
    </div>
  </div>

  <div class="overflow-x-auto mt-6">
    <table class="min-w-full text-sm">
      <thead class="hidden md:table-header-group">
        <tr class="text-left text-slate-600 dark:text-slate-300">
          <th class="py-2 px-3">Endorsement ID</th>
          <th class="py-2 px-3">Cooperative</th>
          <th class="py-2 px-3">Franchise Ref</th>
          <th class="py-2 px-3">Issued</th>
          <th class="py-2 px-3">Permit No.</th>
          <th class="py-2 px-3">Status</th>
        </tr>
      </thead>
      <tbody class="divide-y dark:divide-slate-700">
        <?php if (empty($endorsements)): ?>
          <tr>
            <td colspan="6" class="py-4 px-3 text-center text-slate-500">
              No endorsements found for the selected filters.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($endorsements as $row): ?>
            <?php
              $status = $row['status'] ?? '';
              $badgeClass = format_badge_class($status);
            ?>
            <tr class="grid grid-cols-1 md:table-row gap-2 md:gap-0 p-2 md:p-0 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
              <td class="py-2 px-3">
                <span class="md:hidden font-semibold">Endorsement ID: </span>
                <?php echo htmlspecialchars('END-' . str_pad((string)$row['endorsement_id'], 4, '0', STR_PAD_LEFT)); ?>
              </td>
              <td class="py-2 px-3">
                <span class="md:hidden font-semibold">Cooperative: </span>
                <?php echo htmlspecialchars($row['coop_name'] ?? '—'); ?>
              </td>
              <td class="py-2 px-3">
                <span class="md:hidden font-semibold">Franchise Ref: </span>
                <?php echo htmlspecialchars($row['franchise_ref_number'] ?? '—'); ?>
              </td>
              <td class="py-2 px-3">
                <span class="md:hidden font-semibold">Issued: </span>
                <?php echo htmlspecialchars($row['issued_date'] ?? '—'); ?>
              </td>
              <td class="py-2 px-3">
                <span class="md:hidden font-semibold">Permit No.: </span>
                <?php echo htmlspecialchars($row['permit_number'] ?? '—'); ?>
              </td>
              <td class="py-2 px-3">
                <span class="md:hidden font-semibold">Status: </span>
                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium <?php echo $badgeClass; ?>">
                  <?php echo htmlspecialchars($status ?: 'Unknown'); ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
