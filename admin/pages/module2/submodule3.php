<?php
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module2.view','module2.franchises.manage']);
?>
<div class="mx-auto max-w-7xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-8">
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between border-b border-slate-200 dark:border-slate-700 pb-6">
    <div>
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Renewals, Monitoring & Reporting</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Track permit validity, upcoming renewals, and recent endorsements.</p>
    </div>
  </div>

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
      if ($s === 'ENDORSED') return 'bg-emerald-100 text-emerald-700 border border-emerald-200';
      if ($s === 'REJECTED') return 'bg-rose-100 text-rose-700 border border-rose-200';
      if ($s === 'UNDER REVIEW') return 'bg-blue-100 text-blue-700 border border-blue-200';
      if ($s === 'PENDING') return 'bg-amber-100 text-amber-700 border border-amber-200';
      return 'bg-slate-100 text-slate-700 border border-slate-200';
    }
  ?>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Upcoming Renewals -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden lg:col-span-1 flex flex-col">
      <div class="p-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30 flex items-center gap-3">
        <div class="p-1.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
          <i data-lucide="calendar-clock" class="w-5 h-5"></i>
        </div>
        <h2 class="text-base font-bold text-slate-900 dark:text-white">Upcoming Renewals</h2>
      </div>
      
      <div class="p-4 overflow-y-auto max-h-[400px]">
        <?php if (empty($renewals)): ?>
          <div class="flex flex-col items-center justify-center py-8 text-center">
            <div class="p-3 bg-emerald-50 rounded-full mb-3">
              <i data-lucide="check-circle" class="w-6 h-6 text-emerald-500"></i>
            </div>
            <p class="text-sm text-slate-500">No renewals due in the next 90 days.</p>
          </div>
        <?php else: ?>
          <div class="space-y-3">
            <?php foreach ($renewals as $r): ?>
              <?php
                $expiry = new DateTime($r['expiry_date']);
                $today = new DateTime();
                $diff = $today->diff($expiry);
                $days = $diff->days;
                $urgent = $days <= 30;
              ?>
              <div class="p-3 rounded-xl border <?php echo $urgent ? 'border-amber-200 bg-amber-50' : 'border-slate-100 bg-white'; ?> transition-all">
                <div class="flex items-start justify-between mb-2">
                  <div class="font-medium text-sm text-slate-800 line-clamp-1" title="<?php echo htmlspecialchars($r['coop_name'] ?: $r['franchise_ref_number']); ?>">
                    <?php echo htmlspecialchars($r['coop_name'] ?: ($r['franchise_ref_number'] ?: 'Unknown entity')); ?>
                  </div>
                  <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide <?php echo $urgent ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600'; ?>">
                    <?php echo $days; ?> Days
                  </span>
                </div>
                <div class="flex items-center justify-between text-xs text-slate-500">
                  <span class="font-mono">Permit: <?php echo htmlspecialchars($r['permit_number'] ?? '—'); ?></span>
                  <span>Exp: <?php echo htmlspecialchars($r['expiry_date']); ?></span>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Reporting Filters & Results -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden lg:col-span-2 flex flex-col h-full">
      <div class="p-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
          <div class="p-2 bg-indigo-100 text-indigo-600 rounded-lg">
            <i data-lucide="file-bar-chart" class="w-5 h-5"></i>
          </div>
          <h2 class="text-lg font-semibold text-slate-800">Endorsement Report</h2>
        </div>
        
        <form class="flex flex-wrap gap-2" method="GET">
          <input type="hidden" name="page" value="module2/submodule3">
          <select name="period" class="px-3 py-2 border border-slate-200 rounded-xl bg-white text-xs focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none" onchange="this.form.submit()">
            <?php foreach ($periodOptions as $key => $label): ?>
              <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $key === $period ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($label); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <select name="status" class="px-3 py-2 border border-slate-200 rounded-xl bg-white text-xs focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none" onchange="this.form.submit()">
            <option value="">All Status</option>
            <?php foreach (['Pending','Under Review','Endorsed','Rejected'] as $st): ?>
              <option value="<?php echo htmlspecialchars($st); ?>" <?php echo $statusFilter === $st ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($st); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <select name="coop_id" class="px-3 py-2 border border-slate-200 rounded-xl bg-white text-xs focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none max-w-[150px]" onchange="this.form.submit()">
            <option value="0">All Coops</option>
            <?php foreach ($coops as $c): ?>
              <option value="<?php echo (int)$c['id']; ?>" <?php echo $coopFilter === (int)$c['id'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($c['coop_name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
      
      <div class="overflow-x-auto flex-1">
        <table class="min-w-full text-sm text-left">
          <thead class="bg-slate-50 text-slate-500 font-medium border-b border-slate-100">
            <tr>
              <th class="py-3 px-4 text-xs uppercase tracking-wider">Endorsement ID</th>
              <th class="py-3 px-4 text-xs uppercase tracking-wider">Entity Details</th>
              <th class="py-3 px-4 text-xs uppercase tracking-wider">Issued Date</th>
              <th class="py-3 px-4 text-xs uppercase tracking-wider">Permit No.</th>
              <th class="py-3 px-4 text-right text-xs uppercase tracking-wider">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php if (empty($endorsements)): ?>
              <tr>
                <td colspan="5" class="py-8 text-center text-slate-400">
                  <div class="flex flex-col items-center gap-2">
                    <i data-lucide="filter-x" class="w-8 h-8 stroke-1"></i>
                    <span class="text-sm">No records found for these filters.</span>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($endorsements as $row): ?>
                <?php
                  $status = $row['status'] ?? '';
                  $badgeClass = format_badge_class($status);
                ?>
                <tr class="hover:bg-slate-50/50 transition-colors">
                  <td class="py-3 px-4 font-mono text-xs text-slate-600">
                    <?php echo htmlspecialchars('END-' . str_pad((string)$row['endorsement_id'], 4, '0', STR_PAD_LEFT)); ?>
                  </td>
                  <td class="py-3 px-4">
                    <div class="font-medium text-slate-800 text-sm"><?php echo htmlspecialchars($row['coop_name'] ?? '—'); ?></div>
                    <div class="text-xs text-slate-500 font-mono mt-0.5"><?php echo htmlspecialchars($row['franchise_ref_number'] ?? ''); ?></div>
                  </td>
                  <td class="py-3 px-4 text-slate-600">
                    <?php echo htmlspecialchars($row['issued_date'] ?? '—'); ?>
                  </td>
                  <td class="py-3 px-4 text-slate-600 font-mono text-xs">
                    <?php echo htmlspecialchars($row['permit_number'] ?? '—'); ?>
                  </td>
                  <td class="py-3 px-4 text-right">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold uppercase tracking-wide <?php echo $badgeClass; ?>">
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
  </div>
</div>

<script>
  if (window.lucide) window.lucide.createIcons();
</script>
