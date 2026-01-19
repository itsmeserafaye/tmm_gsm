<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module4.view','module4.inspections.manage']);
$db = db();

$flashNotice = isset($_GET['notice']) ? trim((string)$_GET['notice']) : '';
$flashError = isset($_GET['error']) ? trim((string)$_GET['error']) : '';

$routes = [];
$resRoutes = $db->query("SELECT route_id, route_name, max_vehicle_limit, status FROM routes ORDER BY route_name ASC");
if ($resRoutes) {
  while ($r = $resRoutes->fetch_assoc()) $routes[] = $r;
}
$terminals = [];
$resTerminals = $db->query("SELECT name, city, capacity, status FROM terminals ORDER BY name ASC");
if ($resTerminals) {
  while ($r = $resTerminals->fetch_assoc()) $terminals[] = $r;
}
$coops = [];
$resCoops = $db->query("SELECT id, coop_name, status FROM coops ORDER BY coop_name ASC");
if ($resCoops) {
  while ($r = $resCoops->fetch_assoc()) $coops[] = $r;
}

$rcRoute = trim((string)($_GET['rc_route'] ?? ''));
$rcTerminal = trim((string)($_GET['rc_terminal'] ?? ''));
$rcData = null;
$rcTerminalData = null;
$rcBreakdown = [];

if ($rcRoute !== '') {
  $stmt = $db->prepare("SELECT route_id, route_name, max_vehicle_limit, status FROM routes WHERE route_id=?");
  if ($stmt) {
    $stmt->bind_param('s', $rcRoute);
    $stmt->execute();
    $rcData = $stmt->get_result()->fetch_assoc() ?: null;
  }
  if ($rcData) {
    $stmtC = $db->prepare("SELECT COUNT(*) AS c FROM terminal_assignments WHERE route_id=? AND status='Authorized'");
    if ($stmtC) {
      $stmtC->bind_param('s', $rcRoute);
      $stmtC->execute();
      $rcData['assigned'] = (int)($stmtC->get_result()->fetch_assoc()['c'] ?? 0);
    } else {
      $rcData['assigned'] = 0;
    }
    $stmtB = $db->prepare("SELECT terminal_name, COUNT(*) AS assigned FROM terminal_assignments WHERE route_id=? AND status='Authorized' GROUP BY terminal_name ORDER BY assigned DESC LIMIT 20");
    if ($stmtB) {
      $stmtB->bind_param('s', $rcRoute);
      $stmtB->execute();
      $resB = $stmtB->get_result();
      while ($row = $resB->fetch_assoc()) $rcBreakdown[] = $row;
    }
    if ($rcTerminal !== '') {
      $stmtCT = $db->prepare("SELECT COUNT(*) AS c FROM terminal_assignments WHERE route_id=? AND terminal_name=? AND status='Authorized'");
      if ($stmtCT) {
        $stmtCT->bind_param('ss', $rcRoute, $rcTerminal);
        $stmtCT->execute();
        $assignedAt = (int)($stmtCT->get_result()->fetch_assoc()['c'] ?? 0);
        $rcTerminalData = ['terminal_name' => $rcTerminal, 'assigned' => $assignedAt];
      }
      $stmtT = $db->prepare("SELECT name, city, capacity, status FROM terminals WHERE name=?");
      if ($stmtT) {
        $stmtT->bind_param('s', $rcTerminal);
        $stmtT->execute();
        $tRow = $stmtT->get_result()->fetch_assoc() ?: null;
        if ($tRow) {
          $rcTerminalData = array_merge($rcTerminalData ?: [], $tRow);
        }
      }
    }
  }
}

$repQ = trim((string)($_GET['rep_q'] ?? ''));
$repPeriod = trim((string)($_GET['rep_period'] ?? '90d'));
$repStatus = trim((string)($_GET['rep_status'] ?? ''));
$repCoop = isset($_GET['rep_coop']) ? (int)$_GET['rep_coop'] : 0;
$repRoute = trim((string)($_GET['rep_route'] ?? ''));
$repTerminal = trim((string)($_GET['rep_terminal'] ?? ''));
$repPage = isset($_GET['rep_p']) ? (int)$_GET['rep_p'] : 1;
if ($repPage < 1) $repPage = 1;
$repPageSize = 25;

$periodStartSql = '';
if ($repPeriod === '30d') $periodStartSql = "NOW() - INTERVAL 30 DAY";
elseif ($repPeriod === '90d') $periodStartSql = "NOW() - INTERVAL 90 DAY";
elseif ($repPeriod === 'year') $periodStartSql = "NOW() - INTERVAL 365 DAY";
elseif ($repPeriod === 'all') $periodStartSql = '';
else $repPeriod = '90d';

$whereParts = [];
if ($repCoop > 0) $whereParts[] = "v.coop_id = " . $repCoop;
if ($repRoute !== '') {
  $esc = $db->real_escape_string($repRoute);
  $whereParts[] = "(COALESCE(ta.route_id, v.route_id) = '$esc')";
}
if ($repTerminal !== '') {
  $esc = $db->real_escape_string($repTerminal);
  $whereParts[] = "(ta.terminal_name = '$esc')";
}
if ($repQ !== '') {
  $esc = $db->real_escape_string($repQ);
  $like = '%' . $esc . '%';
  $whereParts[] = "(COALESCE(v.plate_number, sch.plate_number, '') LIKE '$like' OR v.operator_name LIKE '$like' OR v.coop_name LIKE '$like' OR r.route_name LIKE '$like' OR r.route_id LIKE '$like' OR ta.terminal_name LIKE '$like')";
}
if ($repStatus !== '') {
  $stKey = strtolower(trim($repStatus));
  if ($stKey === 'passed') {
    $whereParts[] = "LOWER(TRIM(COALESCE(last.overall_status, v.inspection_status, ''))) IN ('passed','pass')";
  } elseif ($stKey === 'failed') {
    $whereParts[] = "LOWER(TRIM(COALESCE(last.overall_status, v.inspection_status, ''))) IN ('failed','fail')";
  } elseif ($stKey === 'pending') {
    $whereParts[] = "LOWER(TRIM(COALESCE(last.overall_status, v.inspection_status, ''))) IN ('pending','for reinspection','reinspection')";
  } elseif ($stKey === 'no_result') {
    $whereParts[] = "TRIM(COALESCE(last.overall_status, v.inspection_status, '')) = ''";
  } else {
    $repStatus = '';
  }
}

$latestSubqueryWhere = [];
if ($periodStartSql !== '') {
  $latestSubqueryWhere[] = "ir2.submitted_at >= $periodStartSql";
}
$latestSubqueryWhereSql = $latestSubqueryWhere ? ('WHERE ' . implode(' AND ', $latestSubqueryWhere)) : '';

$latestSql = "SELECT REPLACE(REPLACE(UPPER(s2.plate_number), '-', ''), ' ', '') AS plate_norm, MAX(ir2.result_id) AS max_result_id
  FROM inspection_schedules s2
  JOIN inspection_results ir2 ON ir2.schedule_id=s2.schedule_id
  $latestSubqueryWhereSql
  GROUP BY plate_norm";

$plateIndexSql = "SELECT DISTINCT REPLACE(REPLACE(UPPER(plate_number), '-', ''), ' ', '') AS plate_norm
  FROM vehicles
  WHERE plate_number IS NOT NULL AND plate_number <> ''
  UNION
  SELECT DISTINCT REPLACE(REPLACE(UPPER(plate_number), '-', ''), ' ', '') AS plate_norm
  FROM inspection_schedules
  WHERE plate_number IS NOT NULL AND plate_number <> ''";
$latestScheduleSql = "SELECT REPLACE(REPLACE(UPPER(plate_number), '-', ''), ' ', '') AS plate_norm, MAX(schedule_id) AS max_schedule_id
  FROM inspection_schedules
  WHERE plate_number IS NOT NULL AND plate_number <> ''
  GROUP BY plate_norm";

$reportWhereSql = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';

$reportRows = [];
$reportTotal = 0;
$sqlCount = "SELECT COUNT(*) AS c
  FROM ($plateIndexSql) pi
  LEFT JOIN vehicles v ON REPLACE(REPLACE(UPPER(v.plate_number), '-', ''), ' ', '') = pi.plate_norm
  LEFT JOIN ($latestScheduleSql) si ON si.plate_norm = pi.plate_norm
  LEFT JOIN inspection_schedules sch ON sch.schedule_id = si.max_schedule_id
  LEFT JOIN terminal_assignments ta ON ta.plate_number=v.plate_number AND ta.status='Authorized'
  LEFT JOIN routes r ON r.route_id=COALESCE(ta.route_id, v.route_id)
  LEFT JOIN (
    SELECT REPLACE(REPLACE(UPPER(s.plate_number), '-', ''), ' ', '') AS plate_norm, ir.overall_status, ir.submitted_at
    FROM inspection_schedules s
    JOIN inspection_results ir ON ir.schedule_id=s.schedule_id
    JOIN ($latestSql) l ON l.plate_norm=REPLACE(REPLACE(UPPER(s.plate_number), '-', ''), ' ', '') AND l.max_result_id=ir.result_id
  ) last ON last.plate_norm=pi.plate_norm
  $reportWhereSql";
$resCount = $db->query($sqlCount);
if ($resCount && ($row = $resCount->fetch_assoc())) {
  $reportTotal = (int)($row['c'] ?? 0);
}
$repMaxPage = $reportTotal > 0 ? (int)ceil($reportTotal / $repPageSize) : 1;
if ($repMaxPage < 1) $repMaxPage = 1;
if ($repPage > $repMaxPage) $repPage = $repMaxPage;
$repOffset = ($repPage - 1) * $repPageSize;
if ($repOffset < 0) $repOffset = 0;

$sqlReport = "SELECT COALESCE(v.plate_number, sch.plate_number, pi.plate_norm) AS plate_number, v.operator_name, v.coop_name, v.coop_id, v.route_id AS vehicle_route_id, v.inspection_status AS vehicle_inspection_status, v.inspection_cert_ref, c.certificate_number AS schedule_cert_ref,
  ta.route_id AS assigned_route_id, ta.terminal_name,
  r.route_name,
  last.overall_status AS last_status, last.submitted_at AS last_submitted_at
  FROM ($plateIndexSql) pi
  LEFT JOIN vehicles v ON REPLACE(REPLACE(UPPER(v.plate_number), '-', ''), ' ', '') = pi.plate_norm
  LEFT JOIN ($latestScheduleSql) si ON si.plate_norm = pi.plate_norm
  LEFT JOIN inspection_schedules sch ON sch.schedule_id = si.max_schedule_id
  LEFT JOIN inspection_certificates c ON c.schedule_id = sch.schedule_id AND c.status='Issued'
  LEFT JOIN terminal_assignments ta ON ta.plate_number=v.plate_number AND ta.status='Authorized'
  LEFT JOIN routes r ON r.route_id=COALESCE(ta.route_id, v.route_id)
  LEFT JOIN (
    SELECT REPLACE(REPLACE(UPPER(s.plate_number), '-', ''), ' ', '') AS plate_norm, ir.overall_status, ir.submitted_at
    FROM inspection_schedules s
    JOIN inspection_results ir ON ir.schedule_id=s.schedule_id
    JOIN ($latestSql) l ON l.plate_norm=REPLACE(REPLACE(UPPER(s.plate_number), '-', ''), ' ', '') AND l.max_result_id=ir.result_id
  ) last ON last.plate_norm=pi.plate_norm
  $reportWhereSql
  ORDER BY COALESCE(v.plate_number, sch.plate_number, pi.plate_norm) ASC
  LIMIT $repOffset, $repPageSize";
$resReport = $db->query($sqlReport);
if ($resReport) {
  while ($row = $resReport->fetch_assoc()) $reportRows[] = $row;
}

function badge_class_for_status($s) {
  $v = strtolower(trim((string)$s));
  if ($v === 'passed' || $v === 'pass') return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400';
  if ($v === 'failed' || $v === 'fail') return 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400';
  if ($v === 'pending' || $v === 'for reinspection' || $v === 'reinspection') return 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400';
  if ($v === '') return 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400';
  return 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300';
}

function safe_qs($pairs) {
  $out = [];
  foreach ($pairs as $k => $v) $out[] = urlencode((string)$k) . '=' . urlencode((string)$v);
  return implode('&', $out);
}

$terminalsForSelect = array_map(function ($t) { return (string)($t['name'] ?? ''); }, $terminals);
?>

<div class="mx-auto max-w-7xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-8">
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between mb-2 border-b border-slate-200 dark:border-slate-700 pb-6">
    <div>
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Route Validation & Compliance</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Analyze route capacity, monitor compliance rates, and generate inspection reports.</p>
    </div>
    <div class="flex gap-3">
        <a href="?page=module4/submodule3" class="inline-flex items-center gap-2 rounded-md bg-white dark:bg-slate-800 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition-colors">
            <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
            Reset
        </a>
        <?php if (has_permission('module4.inspections.manage')): ?>
          <a href="?page=module4/submodule2" class="inline-flex items-center gap-2 rounded-md bg-blue-700 hover:bg-blue-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors">
              <i data-lucide="clipboard-check" class="w-4 h-4"></i>
              Go to Inspection
          </a>
        <?php endif; ?>
    </div>
  </div>

  <?php if ($flashError !== ''): ?>
    <div class="rounded-md border border-rose-200 bg-rose-50 p-4 dark:border-rose-900/30 dark:bg-rose-900/20 flex items-start gap-3">
        <i data-lucide="alert-circle" class="h-5 w-5 text-rose-400 mt-0.5"></i>
        <div class="text-sm font-semibold text-rose-800 dark:text-rose-200"><?php echo htmlspecialchars($flashError, ENT_QUOTES); ?></div>
    </div>
  <?php elseif ($flashNotice !== ''): ?>
    <div class="rounded-md border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-900/30 dark:bg-emerald-900/20 flex items-start gap-3">
        <i data-lucide="check-circle" class="h-5 w-5 text-emerald-400 mt-0.5"></i>
        <div class="text-sm font-semibold text-emerald-800 dark:text-emerald-200"><?php echo htmlspecialchars($flashNotice, ENT_QUOTES); ?></div>
    </div>
  <?php endif; ?>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Left: Route Capacity -->
    <div class="lg:col-span-1 space-y-6">
        <div class="overflow-hidden rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
            <div class="relative p-6">
                <h2 class="text-base font-bold text-slate-900 dark:text-white mb-4 flex items-center gap-2">
                    <i data-lucide="bar-chart-2" class="w-5 h-5 text-slate-500 dark:text-slate-300"></i>
                    Route Capacity
                </h2>
                <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">Check assignment levels against LPTRP limits.</p>

                <form method="GET" class="space-y-4">
                    <input type="hidden" name="page" value="module4/submodule3">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-300 mb-2">Route</label>
                        <div class="relative">
                            <select name="rc_route" onchange="this.form.submit()" class="block w-full rounded-md bg-white dark:bg-slate-900/50 py-2.5 pl-4 pr-10 text-slate-900 dark:text-white font-semibold text-sm border border-slate-200 dark:border-slate-600 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 appearance-none outline-none transition-all">
                                <option value="">Select Route...</option>
                                <?php foreach ($routes as $r): ?>
                                    <?php $rid = (string)($r['route_id'] ?? ''); ?>
                                    <option value="<?php echo htmlspecialchars($rid, ENT_QUOTES); ?>" <?php echo $rid === $rcRoute ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(($r['route_name'] ?? $rid) . ' (' . $rid . ')', ENT_QUOTES); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <i data-lucide="chevron-down" class="absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>
                    
                    <?php if ($rcRoute !== ''): ?>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-300 mb-2">Terminal Filter (Optional)</label>
                            <div class="relative">
                                <select name="rc_terminal" onchange="this.form.submit()" class="block w-full rounded-md bg-white dark:bg-slate-900/50 py-2.5 pl-4 pr-10 text-slate-900 dark:text-white font-semibold text-sm border border-slate-200 dark:border-slate-600 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 appearance-none outline-none transition-all">
                                    <option value="">All Terminals</option>
                                    <?php foreach ($terminalsForSelect as $tName): ?>
                                        <option value="<?php echo htmlspecialchars($tName, ENT_QUOTES); ?>" <?php echo $tName === $rcTerminal ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($tName, ENT_QUOTES); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <i data-lucide="chevron-down" class="absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none"></i>
                            </div>
                        </div>
                    <?php endif; ?>
                </form>

                <?php if ($rcRoute === ''): ?>
                    <div class="mt-8 text-center p-6 rounded-md bg-slate-50 dark:bg-slate-900/30 border border-dashed border-slate-200 dark:border-slate-700">
                        <i data-lucide="map" class="w-8 h-8 text-slate-300 mx-auto mb-2"></i>
                        <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">Select a route to view analysis</p>
                    </div>
                <?php elseif (!$rcData): ?>
                    <div class="mt-6 p-4 rounded-md bg-rose-50 text-rose-700 text-sm font-semibold border border-rose-200">Route not found.</div>
                <?php else: ?>
                    <?php
                        $max = (int)($rcData['max_vehicle_limit'] ?? 0);
                        $assigned = (int)($rcData['assigned'] ?? 0);
                        $pct = ($max > 0) ? (int)round(min(100, ($assigned / $max) * 100)) : 0;
                        $within = ($max <= 0) ? true : ($assigned <= $max);
                        $barClass = $within ? 'bg-emerald-500' : 'bg-rose-500';
                        $pillClass = $within ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400';
                        $pillText = $within ? 'Within Limit' : 'Over Limit';
                        $pillIcon = $within ? 'check-circle' : 'alert-circle';
                    ?>
                    <div class="mt-6 pt-6 border-t border-slate-200 dark:border-slate-700">
                        <div class="flex items-center justify-between mb-4">
                            <div class="font-bold text-slate-900 dark:text-white truncate max-w-[150px]" title="<?php echo htmlspecialchars((string)($rcData['route_name'] ?? $rcRoute), ENT_QUOTES); ?>">
                                <?php echo htmlspecialchars((string)($rcData['route_name'] ?? $rcRoute), ENT_QUOTES); ?>
                            </div>
                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider <?php echo $pillClass; ?>">
                                <i data-lucide="<?php echo $pillIcon; ?>" class="w-3 h-3"></i> <?php echo $pillText; ?>
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-3 mb-4">
                            <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-700/30 border border-slate-100 dark:border-slate-700">
                                <div class="text-[10px] uppercase font-bold text-slate-400 mb-1">Assigned</div>
                                <div class="text-xl font-black text-slate-800 dark:text-white"><?php echo $assigned; ?></div>
                            </div>
                            <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-700/30 border border-slate-100 dark:border-slate-700">
                                <div class="text-[10px] uppercase font-bold text-slate-400 mb-1">Max Limit</div>
                                <div class="text-xl font-black text-slate-800 dark:text-white"><?php echo $max > 0 ? $max : '∞'; ?></div>
                            </div>
                        </div>

                        <div class="mb-2 flex justify-between text-xs font-bold text-slate-500">
                            <span>Utilization</span>
                            <span><?php echo $pct; ?>%</span>
                        </div>
                        <div class="w-full bg-slate-100 dark:bg-slate-700 rounded-full h-3 overflow-hidden mb-6">
                            <div class="<?php echo $barClass; ?> h-full rounded-full transition-all duration-500" style="width: <?php echo $pct; ?>%"></div>
                        </div>

                        <?php if ($rcTerminalData): ?>
                            <div class="p-4 rounded-2xl bg-indigo-50 dark:bg-indigo-900/10 border border-indigo-100 dark:border-indigo-900/30 mb-6">
                                <div class="flex items-center justify-between gap-2 mb-3">
                                    <div class="text-sm font-bold text-indigo-900 dark:text-indigo-200">
                                        <i data-lucide="map-pin" class="w-3.5 h-3.5 inline mr-1"></i>
                                        <?php echo htmlspecialchars((string)($rcTerminalData['terminal_name'] ?? $rcTerminal), ENT_QUOTES); ?>
                                    </div>
                                    <?php if (isset($rcTerminalData['status']) && (string)$rcTerminalData['status'] !== ''): ?>
                                        <span class="text-[10px] font-bold text-indigo-600 dark:text-indigo-400 uppercase"><?php echo htmlspecialchars((string)$rcTerminalData['status'], ENT_QUOTES); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="grid grid-cols-2 gap-3 text-xs">
                                    <div>
                                        <div class="text-indigo-500/70 font-bold mb-0.5">Assigned Here</div>
                                        <div class="text-lg font-black text-indigo-700 dark:text-indigo-300"><?php echo (int)($rcTerminalData['assigned'] ?? 0); ?></div>
                                    </div>
                                    <div>
                                        <div class="text-indigo-500/70 font-bold mb-0.5">Capacity</div>
                                        <div class="text-lg font-black text-indigo-700 dark:text-indigo-300"><?php echo isset($rcTerminalData['capacity']) && (int)$rcTerminalData['capacity'] > 0 ? (int)$rcTerminalData['capacity'] : '—'; ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div>
                            <div class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">Terminal Breakdown</div>
                            <?php if (!$rcBreakdown): ?>
                                <div class="text-xs text-slate-500 italic">No authorized assignments yet.</div>
                            <?php else: ?>
                                <div class="space-y-2">
                                    <?php foreach ($rcBreakdown as $b): ?>
                                        <div class="flex items-center justify-between p-2 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                            <span class="text-xs font-bold text-slate-700 dark:text-slate-300"><?php echo htmlspecialchars((string)($b['terminal_name'] ?? ''), ENT_QUOTES); ?></span>
                                            <span class="text-xs font-black text-slate-900 dark:text-white bg-slate-100 dark:bg-slate-700 px-2 py-1 rounded-lg"><?php echo (int)($b['assigned'] ?? 0); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right: Compliance Report -->
    <div class="lg:col-span-2 space-y-6">
        <div class="overflow-hidden rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm h-full flex flex-col">
            <div class="p-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                    <div>
                        <h2 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <i data-lucide="file-check" class="w-5 h-5 text-slate-500 dark:text-slate-300"></i>
                            Compliance Reporting
                        </h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Inspection results filtered by period and context.</p>
                    </div>
                    <a href="<?php echo htmlspecialchars($rootUrl ?? '', ENT_QUOTES); ?>/admin/api/module4/export_compliance_report.php?<?php echo safe_qs(['rep_q' => $repQ, 'rep_period' => $repPeriod, 'rep_status' => $repStatus, 'rep_coop' => $repCoop, 'rep_route' => $repRoute, 'rep_terminal' => $repTerminal]); ?>" class="inline-flex items-center gap-2 rounded-md bg-white dark:bg-slate-800 px-4 py-2 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition-colors border border-slate-200 dark:border-slate-600">
                        <i data-lucide="download" class="w-4 h-4"></i> Export CSV
                    </a>
                </div>

                <form method="GET" class="space-y-3">
                    <input type="hidden" name="page" value="module4/submodule3">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                        <div class="md:col-span-2">
                            <input name="rep_q" value="<?php echo htmlspecialchars($repQ, ENT_QUOTES); ?>" class="block w-full rounded-md bg-white dark:bg-slate-900/50 py-2.5 px-3 text-sm font-semibold text-slate-900 dark:text-white border border-slate-200 dark:border-slate-600 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all placeholder:text-slate-400" placeholder="Search Plate, Operator, Route...">
                        </div>
                        
                        <div class="relative">
                            <select name="rep_period" onchange="this.form.submit()" class="block w-full rounded-md bg-white dark:bg-slate-900/50 py-2.5 pl-3 pr-8 text-sm font-semibold text-slate-900 dark:text-white border border-slate-200 dark:border-slate-600 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none appearance-none transition-all">
                                <option value="30d" <?php echo $repPeriod === '30d' ? 'selected' : ''; ?>>Last 30 Days</option>
                                <option value="90d" <?php echo $repPeriod === '90d' ? 'selected' : ''; ?>>Last 90 Days</option>
                                <option value="year" <?php echo $repPeriod === 'year' ? 'selected' : ''; ?>>Last Year</option>
                                <option value="all" <?php echo $repPeriod === 'all' ? 'selected' : ''; ?>>All Time</option>
                            </select>
                            <i data-lucide="chevron-down" class="absolute right-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400 pointer-events-none"></i>
                        </div>

                        <div class="relative">
                            <select name="rep_status" onchange="this.form.submit()" class="block w-full rounded-md bg-white dark:bg-slate-900/50 py-2.5 pl-3 pr-8 text-sm font-semibold text-slate-900 dark:text-white border border-slate-200 dark:border-slate-600 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none appearance-none transition-all">
                                <option value="" <?php echo $repStatus === '' ? 'selected' : ''; ?>>All Statuses</option>
                                <option value="Passed" <?php echo strtolower($repStatus) === 'passed' ? 'selected' : ''; ?>>Passed</option>
                                <option value="Failed" <?php echo strtolower($repStatus) === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                <option value="Pending" <?php echo strtolower($repStatus) === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="no_result" <?php echo strtolower($repStatus) === 'no_result' ? 'selected' : ''; ?>>No Result</option>
                            </select>
                            <i data-lucide="chevron-down" class="absolute right-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 pt-2">
                        <div class="relative">
                            <select name="rep_coop" onchange="this.form.submit()" class="block w-full rounded-md bg-white dark:bg-slate-900/50 py-2.5 pl-3 pr-8 text-sm font-semibold text-slate-900 dark:text-white border border-slate-200 dark:border-slate-600 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none appearance-none transition-all">
                                <option value="0">All Cooperatives</option>
                                <?php foreach ($coops as $c): ?>
                                    <?php $cid = (int)($c['id'] ?? 0); ?>
                                    <option value="<?php echo $cid; ?>" <?php echo $cid === $repCoop ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars((string)($c['coop_name'] ?? ''), ENT_QUOTES); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <i data-lucide="chevron-down" class="absolute right-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400 pointer-events-none"></i>
                        </div>

                        <div class="relative">
                            <select name="rep_route" onchange="this.form.submit()" class="block w-full rounded-md bg-white dark:bg-slate-900/50 py-2.5 pl-3 pr-8 text-sm font-semibold text-slate-900 dark:text-white border border-slate-200 dark:border-slate-600 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none appearance-none transition-all">
                                <option value="">All Routes</option>
                                <?php foreach ($routes as $r): ?>
                                    <?php $rid = (string)($r['route_id'] ?? ''); ?>
                                    <option value="<?php echo htmlspecialchars($rid, ENT_QUOTES); ?>" <?php echo $rid === $repRoute ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(($r['route_name'] ?? $rid) . ' (' . $rid . ')', ENT_QUOTES); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <i data-lucide="chevron-down" class="absolute right-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400 pointer-events-none"></i>
                        </div>

                        <div class="relative">
                            <select name="rep_terminal" onchange="this.form.submit()" class="block w-full rounded-md bg-white dark:bg-slate-900/50 py-2.5 pl-3 pr-8 text-sm font-semibold text-slate-900 dark:text-white border border-slate-200 dark:border-slate-600 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none appearance-none transition-all">
                                <option value="">All Terminals</option>
                                <?php foreach ($terminalsForSelect as $tName): ?>
                                    <option value="<?php echo htmlspecialchars($tName, ENT_QUOTES); ?>" <?php echo $tName === $repTerminal ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tName, ENT_QUOTES); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <i data-lucide="chevron-down" class="absolute right-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>
                </form>
            </div>

            <div class="flex-1 overflow-x-auto custom-scrollbar">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50 dark:bg-slate-800/80 sticky top-0 z-10">
                        <tr>
                            <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider whitespace-nowrap">Vehicle</th>
                            <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider whitespace-nowrap">Route / Terminal</th>
                            <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider whitespace-nowrap">Inspection</th>
                            <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider whitespace-nowrap">Certificate</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700 bg-white dark:bg-slate-900">
                        <?php if (!$reportRows): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <div class="h-12 w-12 rounded-full bg-slate-50 dark:bg-slate-800 flex items-center justify-center mb-3">
                                            <i data-lucide="filter" class="w-6 h-6 text-slate-300"></i>
                                        </div>
                                        <p class="text-sm font-bold text-slate-900 dark:text-white">No matching records</p>
                                        <p class="text-xs text-slate-500 mt-1">Try adjusting your filters</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($reportRows as $row): ?>
                                <?php
                                    $plate = (string)($row['plate_number'] ?? '');
                                    $routeId = (string)($row['assigned_route_id'] ?? ($row['vehicle_route_id'] ?? ''));
                                    $routeName = (string)($row['route_name'] ?? '');
                                    $terminalName = (string)($row['terminal_name'] ?? '');
                                    $coopName = (string)($row['coop_name'] ?? '');
                                    $status = (string)($row['last_status'] ?? ($row['vehicle_inspection_status'] ?? ''));
                                    $statusLabel = $status !== '' ? $status : 'No Result';
                                    $badge = badge_class_for_status($status);
                                    $certRef = (string)($row['inspection_cert_ref'] ?? '');
                                    if ($certRef === '') {
                                        $certRef = (string)($row['schedule_cert_ref'] ?? '');
                                    }
                                    $qrUrl = '';
                                    if ($plate !== '' && $certRef !== '') {
                                        $qrPayload = 'CITY-INSPECTION|' . $plate . '|' . $certRef;
                                        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . urlencode($qrPayload);
                                    }
                                    $inspectedAt = (string)($row['last_submitted_at'] ?? '');
                                ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="font-black text-sm text-slate-800 dark:text-white uppercase mb-0.5"><?php echo htmlspecialchars($plate, ENT_QUOTES); ?></div>
                                        <div class="text-xs font-medium text-slate-500"><?php echo htmlspecialchars($coopName ?: 'Unknown Coop', ENT_QUOTES); ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col gap-1">
                                            <div class="flex items-center gap-1.5 text-xs font-bold text-slate-700 dark:text-slate-300">
                                                <i data-lucide="map" class="w-3 h-3 text-indigo-400"></i>
                                                <?php echo htmlspecialchars($routeId ?: '—', ENT_QUOTES); ?>
                                            </div>
                                            <div class="flex items-center gap-1.5 text-xs font-medium text-slate-500">
                                                <i data-lucide="map-pin" class="w-3 h-3 text-slate-400"></i>
                                                <?php echo htmlspecialchars($terminalName ?: 'No Terminal', ENT_QUOTES); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-bold <?php echo $badge; ?>">
                                            <?php echo htmlspecialchars($statusLabel, ENT_QUOTES); ?>
                                        </span>
                                        <?php if ($inspectedAt !== ''): ?>
                                            <div class="text-[10px] font-medium text-slate-400 mt-1">
                                                <?php echo htmlspecialchars(substr($inspectedAt, 0, 10), ENT_QUOTES); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if ($certRef !== ''): ?>
                                            <div class="flex items-center gap-2">
                                                <div class="font-mono text-xs font-bold text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-700 px-2 py-1 rounded">
                                                    <?php echo htmlspecialchars($certRef, ENT_QUOTES); ?>
                                                </div>
                                                <?php if ($qrUrl !== ''): ?>
                                                    <button type="button" class="btn-show-qr p-1.5 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-all" data-qr-url="<?php echo htmlspecialchars($qrUrl, ENT_QUOTES); ?>">
                                                        <i data-lucide="qr-code" class="w-4 h-4"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-xs text-slate-400 italic">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="p-4 border-t border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 flex items-center justify-between">
                <?php
                    $from = $reportTotal > 0 ? ($repOffset + 1) : 0;
                    $to = $reportTotal > 0 ? ($repOffset + min(count($reportRows), $repPageSize)) : 0;
                ?>
                <div class="text-xs font-bold text-slate-500">
                    Showing <?php echo $from; ?>–<?php echo $to; ?> of <?php echo $reportTotal; ?> results
                </div>
                
                <div class="flex items-center gap-2">
                    <?php if ($repPage > 1): ?>
                        <a href="?<?php echo safe_qs(['page' => 'module4/submodule3', 'rep_q' => $repQ, 'rep_period' => $repPeriod, 'rep_status' => $repStatus, 'rep_coop' => $repCoop, 'rep_route' => $repRoute, 'rep_terminal' => $repTerminal, 'rep_p' => $repPage - 1]); ?>" class="p-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 transition-all shadow-sm">
                            <i data-lucide="chevron-left" class="w-4 h-4"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($repPage < $repMaxPage): ?>
                        <a href="?<?php echo safe_qs(['page' => 'module4/submodule3', 'rep_q' => $repQ, 'rep_period' => $repPeriod, 'rep_status' => $repStatus, 'rep_coop' => $repCoop, 'rep_route' => $repRoute, 'rep_terminal' => $repTerminal, 'rep_p' => $repPage + 1]); ?>" class="p-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 transition-all shadow-sm">
                            <i data-lucide="chevron-right" class="w-4 h-4"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
  </div>
</div>

<!-- QR Modal -->
<div id="qr-modal-overlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center z-[100] hidden transition-opacity opacity-0">
    <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-2xl p-8 w-80 transform transition-all scale-95">
        <div class="text-center mb-6">
            <h3 class="text-lg font-black text-slate-800 dark:text-white">Certificate QR</h3>
            <p class="text-xs font-medium text-slate-500">Scan to verify authenticity</p>
        </div>
        <div class="flex justify-center mb-6">
            <div class="p-4 rounded-2xl bg-white shadow-inner border border-slate-100">
                <img id="qr-modal-image" src="" alt="Certificate QR" class="w-48 h-48 rounded-lg">
            </div>
        </div>
        <button type="button" id="qr-modal-close-x" class="w-full py-3 rounded-xl bg-slate-100 dark:bg-slate-800 text-sm font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition-all">Close</button>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if(window.lucide) window.lucide.createIcons();

    var qrModal = document.getElementById('qr-modal-overlay');
    var qrModalImage = document.getElementById('qr-modal-image');
    var closeX = document.getElementById('qr-modal-close-x');

    function openQrModal(url) {
        if (!qrModal || !qrModalImage) return;
        qrModalImage.src = url || '';
        qrModal.classList.remove('hidden');
        setTimeout(() => {
            qrModal.classList.remove('opacity-0');
            qrModal.querySelector('div').classList.remove('scale-95');
            qrModal.querySelector('div').classList.add('scale-100');
        }, 10);
    }

    function closeQrModal() {
        if (!qrModal || !qrModalImage) return;
        qrModal.classList.add('opacity-0');
        qrModal.querySelector('div').classList.remove('scale-100');
        qrModal.querySelector('div').classList.add('scale-95');
        setTimeout(() => {
            qrModalImage.src = '';
            qrModal.classList.add('hidden');
        }, 300);
    }

    document.addEventListener('click', function (e) {
        if (e.target.closest('.btn-show-qr')) {
            var btn = e.target.closest('.btn-show-qr');
            openQrModal(btn.dataset.qrUrl);
        }
        if (qrModal && e.target === qrModal) {
            closeQrModal();
        }
    });

    if (closeX) {
        closeX.addEventListener('click', function () {
            closeQrModal();
        });
    }
});
</script>
