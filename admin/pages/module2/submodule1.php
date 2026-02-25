<?php
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module2.read','module2.apply','module2.endorse','module2.approve','module2.history']);

require_once __DIR__ . '/../../includes/db.php';
$db = db();

$operators = [];
$resO = $db->query("SELECT id, COALESCE(NULLIF(name,''), full_name) AS display_name, operator_type, status FROM operators ORDER BY created_at DESC LIMIT 800");
if ($resO) {
  while ($r = $resO->fetch_assoc()) {
    $id = (int)($r['id'] ?? 0);
    $nm = trim((string)($r['display_name'] ?? ''));
    if ($id <= 0 || $nm === '') continue;
    $operators[] = [
      'id' => $id,
      'display_name' => $nm,
      'operator_type' => (string)($r['operator_type'] ?? ''),
      'status' => (string)($r['status'] ?? ''),
    ];
  }
}

$hasFranchises = (bool)($db->query("SHOW TABLES LIKE 'franchises'")?->fetch_row());
if ($hasFranchises) {
  @$db->query("UPDATE franchises SET status='Expired' WHERE status='Active' AND expiry_date IS NOT NULL AND expiry_date < CURDATE()");
  @$db->query("UPDATE franchise_applications fa
               JOIN franchises f ON f.application_id=fa.application_id
               SET fa.status='Expired'
               WHERE f.status='Expired'
                 AND fa.status IN ('Active','Approved','Pending Review','Returned for Correction','PA Issued','CPC Issued','LTFRB-Approved','Submitted','Pending','Under Review')");
}

$statTotal = (int)($db->query("SELECT COUNT(*) AS c FROM franchise_applications")->fetch_assoc()['c'] ?? 0);
$statPendingReview = (int)($db->query("SELECT COUNT(*) AS c FROM franchise_applications WHERE status='Pending Review'")->fetch_assoc()['c'] ?? 0);
$statReturned = (int)($db->query("SELECT COUNT(*) AS c FROM franchise_applications WHERE status='Returned for Correction'")->fetch_assoc()['c'] ?? 0);
$statApproved = (int)($db->query("SELECT COUNT(*) AS c FROM franchise_applications WHERE status='Approved'")->fetch_assoc()['c'] ?? 0);
// Treat LGU-Endorsed PUV applications as Active together with issued tricycle franchises
$statActive = (int)($db->query("SELECT COUNT(*) AS c FROM franchise_applications WHERE status='Active' OR (status='LGU-Endorsed' AND (vehicle_type IS NULL OR vehicle_type<>'Tricycle'))")->fetch_assoc()['c'] ?? 0);
$statExpired = (int)($db->query("SELECT COUNT(*) AS c FROM franchise_applications WHERE status='Expired'")->fetch_assoc()['c'] ?? 0);
$statRevoked = (int)($db->query("SELECT COUNT(*) AS c FROM franchise_applications WHERE status='Revoked'")->fetch_assoc()['c'] ?? 0);

$q = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$basis = trim((string)($_GET['basis'] ?? 'submitted')); // submitted|endorsed|approved
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
$coverage = trim((string)($_GET['coverage'] ?? '')); // '', 'route', 'service_area'
$highlightAppId = (int)($_GET['highlight_application_id'] ?? 0);

$sql = "SELECT fa.application_id, fa.franchise_ref_number, fa.operator_id,
               COALESCE(NULLIF(o.name,''), o.full_name) AS operator_name,
               fa.route_ids,
               fa.approved_route_ids,
               fa.route_id,
               fa.service_area_id,
               fa.approved_service_area_id,
               fa.vehicle_type,
               fa.submitted_channel,
               COALESCE(r.route_id, sa.area_code) AS route_code,
               COALESCE(r.origin, sap.points, '') AS origin,
               COALESCE(r.destination, '') AS destination,
               fa.vehicle_count, fa.representative_name,
               fa.status, fa.submitted_at, fa.endorsed_at, fa.approved_at
        FROM franchise_applications fa
        LEFT JOIN operators o ON o.id=fa.operator_id
        LEFT JOIN routes r ON r.id=fa.route_id
        LEFT JOIN tricycle_service_areas sa ON sa.id=COALESCE(fa.approved_service_area_id, fa.service_area_id)
        LEFT JOIN (
          SELECT area_id, GROUP_CONCAT(point_name ORDER BY sort_order ASC, point_id ASC SEPARATOR ' • ') AS points
          FROM tricycle_service_area_points
          GROUP BY area_id
        ) sap ON sap.area_id=sa.id";
$conds = [];
$params = [];
$types = '';
if ($q !== '') {
  $conds[] = "(fa.franchise_ref_number LIKE ? OR COALESCE(NULLIF(o.name,''), o.full_name) LIKE ? OR COALESCE(r.route_id, sa.area_code) LIKE ? OR COALESCE(r.origin, sap.points, '') LIKE ? OR COALESCE(r.destination,'') LIKE ?)";
  $params[] = "%$q%";
  $params[] = "%$q%";
  $params[] = "%$q%";
  $params[] = "%$q%";
  $params[] = "%$q%";
  $types .= 'sssss';
}
if ($status !== '' && $status !== 'Status') {
  $conds[] = "fa.status=?";
  $params[] = $status;
  $types .= 's';
}
if ($coverage === 'route') {
  $conds[] = "COALESCE(fa.service_area_id,0)=0 AND COALESCE(fa.route_id,0)<>0";
}
if ($coverage === 'service_area') {
  $conds[] = "COALESCE(fa.service_area_id,0)<>0";
}
if ($from !== '' && preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $from)) {
  $col = ($basis === 'endorsed') ? 'fa.endorsed_at' : (($basis === 'approved') ? 'fa.approved_at' : 'fa.submitted_at');
  $conds[] = "DATE($col) >= ?";
  $params[] = $from;
  $types .= 's';
}
if ($to !== '' && preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $to)) {
  $col = ($basis === 'endorsed') ? 'fa.endorsed_at' : (($basis === 'approved') ? 'fa.approved_at' : 'fa.submitted_at');
  $conds[] = "DATE($col) <= ?";
  $params[] = $to;
  $types .= 's';
}
if ($conds) $sql .= " WHERE " . implode(" AND ", $conds);
$sql .= " ORDER BY fa.submitted_at DESC LIMIT 300";

if ($params) {
  $stmt = $db->prepare($sql);
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();
} else {
  $res = $db->query($sql);
}

$appRows = [];
if ($res) {
  while ($rr = $res->fetch_assoc()) $appRows[] = $rr;
}
if (isset($stmt) && $stmt instanceof mysqli_stmt) {
  try { $stmt->close(); } catch (Throwable $_) { }
}

$useMock = false;
if ($useMock) {
  $now = date('Y-m-d H:i:s');
  $appRows = [
    [
      'application_id' => 90001,
      'franchise_ref_number' => 'FR-2026-0001',
      'operator_id' => 101,
      'operator_name' => 'UV Express Operators Cooperative',
      'route_id' => 0,
      'route_ids' => '1,2',
      'approved_route_ids' => '1,2',
      'route_code' => 'R-001',
      'origin' => 'Bagumbong',
      'destination' => 'Novaliches Bayan',
      'vehicle_count' => 15,
      'representative_name' => 'Maria Santos',
      'status' => 'PA Issued',
      'submitted_at' => date('Y-m-d H:i:s', strtotime('-9 days')),
      'endorsed_at' => date('Y-m-d H:i:s', strtotime('-7 days')),
      'approved_at' => date('Y-m-d H:i:s', strtotime('-5 days')),
      'routes_display' => 'R-001 • Bagumbong → Novaliches Bayan | R-002 • Bagumbong → Deparo',
    ],
    [
      'application_id' => 90002,
      'franchise_ref_number' => 'FR-2026-0002',
      'operator_id' => 102,
      'operator_name' => 'Bagumbong Jeepney Operators Association',
      'route_id' => 0,
      'route_ids' => '3',
      'approved_route_ids' => '3',
      'route_code' => 'R-003',
      'origin' => 'Bagumbong',
      'destination' => 'Deparo',
      'vehicle_count' => 12,
      'representative_name' => 'Juan Dela Cruz',
      'status' => 'CPC Issued',
      'submitted_at' => date('Y-m-d H:i:s', strtotime('-20 days')),
      'endorsed_at' => date('Y-m-d H:i:s', strtotime('-18 days')),
      'approved_at' => date('Y-m-d H:i:s', strtotime('-16 days')),
      'routes_display' => 'R-003 • Bagumbong → Deparo',
    ],
  ];
  $statTotal = 2;
  $statSubmitted = 0;
  $statEndorsed = 0;
  $statApproved = 2;
  $statExpired = 0;
  $statRevoked = 0;
}

$routeIds = [];
$tmmExtractRouteIds = function (string $csv): array {
  $out = [];
  if ($csv === '') return $out;
  if (preg_match_all('/\d+/', $csv, $m)) {
    foreach ($m[0] as $x) {
      $id = (int)$x;
      if ($id > 0) $out[] = $id;
    }
  }
  return $out;
};
foreach ($appRows as $row) {
  $rid = (int)($row['route_id'] ?? 0);
  if ($rid > 0) $routeIds[$rid] = true;
}

$routeMap = [];
if ($routeIds) {
  $ids = array_map('intval', array_keys($routeIds));
  sort($ids);
  $in = implode(',', $ids);
  $resR = $db->query("SELECT id, COALESCE(NULLIF(route_code,''), route_id) AS code, route_name, origin, destination FROM routes WHERE id IN ($in)");
  if ($resR) {
    while ($r = $resR->fetch_assoc()) {
      $id = (int)($r['id'] ?? 0);
      if ($id <= 0) continue;
      $routeMap[$id] = $r;
    }
  }
}

$tmmRouteLabel = function (array $r): string {
  $code = trim((string)($r['code'] ?? ($r['route_code'] ?? ($r['route_id'] ?? ''))));
  if ($code === '') $code = '-';
  $ro = trim((string)($r['origin'] ?? ''));
  $rd = trim((string)($r['destination'] ?? ''));
  $label = $code;
  if ($ro !== '' || $rd !== '') $label .= ' • ' . trim($ro . ' → ' . $rd);
  return $label;
};

foreach ($appRows as &$row) {
  $rid = (int)($row['route_id'] ?? 0);
  if ($rid > 0 && isset($routeMap[$rid])) {
    $row['routes_display'] = $tmmRouteLabel($routeMap[$rid]);
  } else {
    $row['routes_display'] = '';
  }
}
unset($row);

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';
?>

<div class="mx-auto max-w-7xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-6">
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between border-b border-slate-200 dark:border-slate-700 pb-6">
    <div>
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Franchise Applications</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">Tricycle-only flow: Pending Review → Approved / Rejected / Returned for Correction → Active (issued) → Expired / Revoked.</p>
    </div>
    <div class="flex items-center gap-3">
      <button type="button" id="btnOpenTriSubmit" class="inline-flex items-center justify-center gap-2 rounded-md bg-blue-700 hover:bg-blue-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-all active:scale-[0.98]">
        <i data-lucide="file-plus" class="w-4 h-4"></i>
        Submit Application
      </button>
      <a href="?page=module2/submodule4" class="inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
        <i data-lucide="clipboard-check" class="w-4 h-4"></i>
        Staff Evaluation
      </a>
      <a href="?page=module2/submodule6" class="inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
        <i data-lucide="badge-check" class="w-4 h-4"></i>
        Issuance
      </a>
    </div>
  </div>

  <div id="toast-container" class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 z-[100] flex flex-col gap-3 pointer-events-none"></div>

  <div class="grid grid-cols-1 md:grid-cols-6 gap-6">
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="flex items-center justify-between mb-2">
        <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total</div>
        <i data-lucide="layers" class="w-4 h-4 text-blue-600 dark:text-blue-400"></i>
      </div>
      <div class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo $statTotal; ?></div>
    </div>
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="flex items-center justify-between mb-2">
        <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Pending Review</div>
        <i data-lucide="send" class="w-4 h-4 text-amber-600 dark:text-amber-400"></i>
      </div>
      <div class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo $statPendingReview; ?></div>
    </div>
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="flex items-center justify-between mb-2">
        <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Returned</div>
        <i data-lucide="undo-2" class="w-4 h-4 text-amber-600 dark:text-amber-400"></i>
      </div>
      <div class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo $statReturned; ?></div>
    </div>
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="flex items-center justify-between mb-2">
        <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Approved</div>
        <i data-lucide="check-circle-2" class="w-4 h-4 text-blue-600 dark:text-blue-400"></i>
      </div>
      <div class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo $statApproved; ?></div>
    </div>
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="flex items-center justify-between mb-2">
        <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Active</div>
        <i data-lucide="badge-check" class="w-4 h-4 text-emerald-600 dark:text-emerald-400"></i>
      </div>
      <div class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo $statActive; ?></div>
    </div>
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="flex items-center justify-between mb-2">
        <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Expired</div>
        <i data-lucide="clock" class="w-4 h-4 text-slate-600 dark:text-slate-300"></i>
      </div>
      <div class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo $statExpired; ?></div>
    </div>
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="flex items-center justify-between mb-2">
        <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Revoked</div>
        <i data-lucide="ban" class="w-4 h-4 text-rose-600 dark:text-rose-400"></i>
      </div>
      <div class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo $statRevoked; ?></div>
    </div>
  </div>

  <div class="bg-white dark:bg-slate-800 p-5 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
    <?php if (has_permission('reports.export')): ?>
      <?php tmm_render_export_toolbar([
        [
          'href' => $rootUrl . '/admin/api/module2/export_applications_csv.php?' . http_build_query(['q' => $q, 'status' => $status, 'basis' => $basis, 'from' => $from, 'to' => $to, 'coverage' => $coverage]),
          'label' => 'CSV',
          'icon' => 'download'
        ],
        [
          'href' => $rootUrl . '/admin/api/module2/export_applications_csv.php?' . http_build_query(['q' => $q, 'status' => $status, 'format' => 'excel', 'basis' => $basis, 'from' => $from, 'to' => $to, 'coverage' => $coverage]),
          'label' => 'Excel',
          'icon' => 'file-spreadsheet'
        ],
        [
          'href' => $rootUrl . '/admin/api/module2/print_applications.php?' . http_build_query(['q' => $q, 'status' => $status, 'basis' => $basis, 'from' => $from, 'to' => $to, 'coverage' => $coverage]),
          'label' => 'Print',
          'icon' => 'printer',
          'attrs' => [
            'data-print-url' => $rootUrl . '/admin/api/module2/print_applications.php?' . http_build_query(['q' => $q, 'status' => $status, 'basis' => $basis, 'from' => $from, 'to' => $to, 'coverage' => $coverage]),
            'data-report-name' => 'Franchise Applications Report'
          ]
        ]
      ]); ?>
    <?php endif; ?>
    <form class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-6" method="GET">
      <input type="hidden" name="page" value="module2/submodule1">
      
      <!-- Search -->
      <div class="relative group">
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
          <i data-lucide="search" class="w-4 h-4 text-slate-400 group-focus-within:text-blue-500 transition-colors"></i>
        </div>
        <input name="q" value="<?php echo htmlspecialchars($q); ?>" class="block w-full pl-10 pr-3 py-2.5 text-sm font-semibold border-0 rounded-lg bg-slate-50 dark:bg-slate-900/40 text-slate-900 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-blue-500 transition-all placeholder:text-slate-400" placeholder="Search operator...">
      </div>

      <!-- Status -->
      <div class="relative">
        <select name="status" class="block w-full pl-3 pr-10 py-2.5 text-sm font-semibold border-0 rounded-lg bg-slate-50 dark:bg-slate-900/40 text-slate-900 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-blue-500 transition-all appearance-none cursor-pointer">
          <option value="">All Status</option>
          <?php foreach (['Pending Review','Returned for Correction','Approved','Active','Rejected','Expired','Revoked'] as $s): ?>
            <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $status === $s ? 'selected' : ''; ?>><?php echo htmlspecialchars($s); ?></option>
          <?php endforeach; ?>
        </select>
        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
          <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400"></i>
        </div>
      </div>

      <!-- Coverage -->
      <div class="relative">
        <select name="coverage" class="block w-full pl-3 pr-10 py-2.5 text-sm font-semibold border-0 rounded-lg bg-slate-50 dark:bg-slate-900/40 text-slate-900 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-blue-500 transition-all appearance-none cursor-pointer">
          <?php $cov = in_array($coverage, ['route','service_area'], true) ? $coverage : ''; ?>
          <option value="" <?php echo $cov===''?'selected':''; ?>>All Coverage</option>
          <option value="route" <?php echo $cov==='route'?'selected':''; ?>>Routes</option>
          <option value="service_area" <?php echo $cov==='service_area'?'selected':''; ?>>Service Areas</option>
        </select>
        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
          <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400"></i>
        </div>
      </div>

      <!-- Basis -->
      <div class="relative">
        <select name="basis" class="block w-full pl-3 pr-10 py-2.5 text-sm font-semibold border-0 rounded-lg bg-slate-50 dark:bg-slate-900/40 text-slate-900 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-blue-500 transition-all appearance-none cursor-pointer">
          <?php $bb = in_array($basis, ['submitted','endorsed','approved'], true) ? $basis : 'submitted'; ?>
          <option value="submitted" <?php echo $bb==='submitted'?'selected':''; ?>>Submitted Date</option>
          <option value="endorsed" <?php echo $bb==='endorsed'?'selected':''; ?>>Endorsed Date</option>
          <option value="approved" <?php echo $bb==='approved'?'selected':''; ?>>Approved Date</option>
        </select>
        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
          <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400"></i>
        </div>
      </div>

      <div class="flex items-center gap-2 xl:col-span-2">
        <div class="relative flex-1 min-w-[10rem]">
          <input name="from" type="date" value="<?php echo htmlspecialchars($from); ?>" class="block w-full px-3 pr-10 py-2.5 text-sm font-semibold border-0 rounded-lg bg-slate-50 dark:bg-slate-900/40 text-slate-900 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-blue-500 transition-all">
          <i data-lucide="calendar" class="pointer-events-none absolute inset-y-0 right-3 my-auto w-4 h-4 text-slate-400"></i>
        </div>
        <span class="text-slate-400">-</span>
        <div class="relative flex-1 min-w-[10rem]">
          <input name="to" type="date" value="<?php echo htmlspecialchars($to); ?>" class="block w-full px-3 pr-10 py-2.5 text-sm font-semibold border-0 rounded-lg bg-slate-50 dark:bg-slate-900/40 text-slate-900 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-blue-500 transition-all">
          <i data-lucide="calendar" class="pointer-events-none absolute inset-y-0 right-3 my-auto w-4 h-4 text-slate-400"></i>
        </div>
      </div>

      <!-- Buttons -->
      <div class="flex items-center gap-2">
        <button class="flex-1 inline-flex items-center justify-center gap-2 rounded-lg bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 dark:hover:bg-slate-600 px-4 py-2.5 text-sm font-semibold text-white transition-all shadow-sm">
          <i data-lucide="filter" class="w-4 h-4"></i>
          Apply
        </button>
        <a href="?page=module2/submodule1" class="inline-flex items-center justify-center rounded-lg bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/50 px-3 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 transition-all shadow-sm" title="Reset">
          <i data-lucide="x" class="w-4 h-4"></i>
        </a>
      </div>
    </form>
  </div>

  <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-700 border-b border-slate-200 dark:border-slate-700">
          <tr class="text-left text-slate-500 dark:text-slate-400">
            <th class="py-4 px-6 font-black uppercase tracking-widest text-xs">Application</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">Operator</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs hidden md:table-cell">Service Area / TODA Zone</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs hidden sm:table-cell">Units</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">Status</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs hidden sm:table-cell">Submitted</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-800">
          <?php if ($appRows): ?>
            <?php foreach ($appRows as $row): ?>
              <?php
                $appId = (int)($row['application_id'] ?? 0);
                $vehType = (string)($row['vehicle_type'] ?? '');
                $isHighlight = $highlightAppId > 0 && $highlightAppId === $appId;
                $rawStatus = (string)($row['status'] ?? '');
                $isPuvEndorse = (($vehType !== '' && strcasecmp($vehType, 'Tricycle') !== 0) || strcasecmp((string)($row['submitted_channel'] ?? ''), 'PUV_LOCAL_ENDORSEMENT') === 0);
                // Collapse internal statuses into: Active / Inactive / Expired
                if ($rawStatus === 'Expired') {
                  $st = 'Expired';
                } elseif ($rawStatus === 'Active' || ($rawStatus === 'LGU-Endorsed' && $isPuvEndorse)) {
                  $st = 'Active';
                } else {
                  $st = 'Inactive';
                }
                $badge = match($st) {
                  'Active' => 'bg-emerald-100 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-400 dark:ring-emerald-500/20',
                  'Expired' => 'bg-slate-200 text-slate-700 ring-slate-600/20 dark:bg-slate-700 dark:text-slate-200 dark:ring-slate-500/20',
                  default => 'bg-slate-100 text-slate-700 ring-slate-600/20 dark:bg-slate-800 dark:text-slate-400'
                };
              ?>
              <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors group <?php echo $isHighlight ? 'bg-emerald-50/70 dark:bg-emerald-900/15 ring-1 ring-inset ring-emerald-200/70 dark:ring-emerald-900/30' : ''; ?>" <?php echo $isHighlight ? 'id="app-row-highlight"' : ''; ?>>
                <td class="py-4 px-6">
                  <div class="font-black text-slate-900 dark:text-white">APP-<?php echo $appId; ?></div>
                  <div class="text-xs text-slate-500 dark:text-slate-400 mt-1"><?php echo htmlspecialchars((string)($row['franchise_ref_number'] ?? '')); ?></div>
                </td>
                <td class="py-4 px-4 text-slate-700 dark:text-slate-200 font-semibold">
                  <?php echo htmlspecialchars((string)($row['operator_name'] ?? '')); ?>
                  <?php if (!empty($row['representative_name'] ?? '')): ?>
                    <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">Rep: <?php echo htmlspecialchars((string)$row['representative_name']); ?></div>
                  <?php endif; ?>
                    <?php
                      $vehLabel = trim((string)($row['vehicle_type'] ?? ''));
                      $channelLabel = trim((string)($row['submitted_channel'] ?? ''));
                      $isPuvEndorse = ($vehLabel !== '' && strcasecmp($vehLabel, 'Tricycle') !== 0) || strcasecmp($channelLabel, 'PUV_LOCAL_ENDORSEMENT') === 0;
                    ?>
                  <?php if ($isPuvEndorse): ?>
                    <div class="mt-1 inline-flex items-center px-2 py-0.5 rounded-full bg-violet-50 text-violet-700 border border-violet-200 text-[11px] font-black tracking-wide">
                      PUV Local Endorsement
                    </div>
                  <?php endif; ?>
                </td>
                <td class="py-4 px-4 hidden md:table-cell text-slate-600 dark:text-slate-300 font-medium">
                  <?php
                    $areaId = (int)($row['service_area_id'] ?? 0);
                    if ($areaId > 0) {
                      $rc = trim((string)($row['route_code'] ?? ''));
                      $ro = trim((string)($row['origin'] ?? ''));
                      $label = $rc !== '' ? $rc : '-';
                      if ($ro !== '') $label .= ' • ' . $ro;
                      echo htmlspecialchars($label);
                    } else {
                    $rc = trim((string)($row['route_code'] ?? ''));
                    $ro = trim((string)($row['origin'] ?? ''));
                    $rd = trim((string)($row['destination'] ?? ''));
                    $label = $rc !== '' ? $rc : '-';
                    if ($ro !== '' || $rd !== '') $label .= ' • ' . trim($ro . ' → ' . $rd);
                    echo htmlspecialchars($label);
                    }
                  ?>
                </td>
                <td class="py-4 px-4 hidden sm:table-cell font-black text-slate-700 dark:text-slate-200"><?php echo (int)($row['vehicle_count'] ?? 0); ?></td>
                <td class="py-4 px-4">
                  <span class="px-2.5 py-1 rounded-lg text-xs font-bold ring-1 ring-inset <?php echo $badge; ?>"><?php echo htmlspecialchars($st); ?></span>
                </td>
                <td class="py-4 px-4 hidden sm:table-cell text-xs text-slate-500 dark:text-slate-400 font-medium">
                  <?php echo htmlspecialchars(date('M d, Y', strtotime((string)($row['submitted_at'] ?? 'now')))); ?>
                </td>
                <td class="py-4 px-4 text-right">
                  <div class="flex items-center justify-end gap-2 opacity-100 md:opacity-0 md:group-hover:opacity-100 transition-opacity">
                    <button type="button" class="p-2 rounded-xl bg-slate-100 dark:bg-slate-700/50 text-slate-500 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-all" data-app-view="1" data-app-id="<?php echo (int)$appId; ?>" title="View Details">
                      <i data-lucide="eye" class="w-4 h-4"></i>
                    </button>
                    <?php if (has_permission('module2.franchises.manage')): ?>
                      <?php if (strcasecmp($vehType, 'Tricycle') === 0): ?>
                        <?php if (in_array($rawStatus, ['Pending Review','Returned for Correction'], true)): ?>
                          <a href="?<?php echo http_build_query(['page'=>'module2/submodule4','application_id'=>$appId]); ?>" class="p-2 rounded-xl bg-slate-100 dark:bg-slate-700/50 text-slate-500 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-all" title="Staff Evaluation">
                            <i data-lucide="clipboard-check" class="w-4 h-4"></i>
                          </a>
                        <?php endif; ?>
                        <?php if ($rawStatus === 'Approved'): ?>
                          <a href="?<?php echo http_build_query(['page'=>'module2/submodule6','application_id'=>$appId]); ?>" class="p-2 rounded-xl bg-slate-100 dark:bg-slate-700/50 text-slate-500 hover:text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-all" title="Issue Franchise">
                            <i data-lucide="badge-check" class="w-4 h-4"></i>
                          </a>
                        <?php endif; ?>
                      <?php else: ?>
                        <?php if ($rawStatus === 'Submitted'): ?>
                          <a href="?<?php echo http_build_query(['page'=>'module2/submodule3','application_id'=>$appId,'tab'=>'review']); ?>" class="p-2 rounded-xl bg-slate-100 dark:bg-slate-700/50 text-slate-500 hover:text-violet-700 hover:bg-violet-50 dark:hover:bg-violet-900/20 transition-all" title="PUV Local Endorsement / Permit">
                            <i data-lucide="file-check-2" class="w-4 h-4"></i>
                          </a>
                        <?php endif; ?>
                      <?php endif; ?>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="7" class="py-12 text-center text-slate-500 font-medium italic">No applications found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div id="modalApp" class="fixed inset-0 z-[200] hidden">
  <div id="modalAppBackdrop" class="absolute inset-0 bg-slate-900/50 opacity-0 transition-opacity"></div>
  <div class="absolute inset-0 flex items-center justify-center p-4">
    <div id="modalAppPanel" class="w-full max-w-4xl rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-xl transform scale-95 opacity-0 transition-all">
      <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700">
        <div class="font-black text-slate-900 dark:text-white" id="modalAppTitle">Application</div>
        <button type="button" id="modalAppClose" class="p-2 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
          <i data-lucide="x" class="w-4 h-4"></i>
        </button>
      </div>
      <div id="modalAppBody" class="p-6 max-h-[80vh] overflow-y-auto"></div>
    </div>
  </div>
</div>

<div id="modalTriSubmit" class="fixed inset-0 z-[210] hidden">
  <div id="modalTriSubmitBackdrop" class="absolute inset-0 bg-slate-900/50 opacity-0 transition-opacity"></div>
  <div class="absolute inset-0 flex items-center justify-center p-4">
    <div id="modalTriSubmitPanel" class="w-full max-w-4xl rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-xl transform scale-95 opacity-0 transition-all">
      <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700">
        <div class="font-black text-slate-900 dark:text-white">Submit Tricycle Franchise Application</div>
        <button type="button" id="modalTriSubmitClose" class="p-2 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
          <i data-lucide="x" class="w-4 h-4"></i>
        </button>
      </div>
      <div class="p-6 max-h-[80vh] overflow-y-auto">
        <form id="formTriSubmit" class="space-y-5" novalidate>
          <input type="hidden" name="vehicle_type" value="Tricycle">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">
                <span class="text-rose-600">*</span> Operator
              </label>
              <input name="operator_pick" list="triOperatorPickList" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="Select from list (e.g., 123 - Juan Dela Cruz)">
              <datalist id="triOperatorPickList">
                <?php foreach ($operators as $o): ?>
                  <option value="<?php echo htmlspecialchars($o['id'] . ' - ' . $o['display_name'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($o['operator_type'] . ' • ' . $o['status']); ?></option>
                <?php endforeach; ?>
              </datalist>
            </div>
          </div>

          <div>
            <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">
              <span class="text-rose-600">*</span> Service Area / TODA Zone
            </label>
            <input id="triServicePick" name="service_pick" list="triServicePickList" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="Select Service Area / TODA Zone">
            <datalist id="triServicePickList"></datalist>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">
                <span class="text-rose-600">*</span> Requested number of units
              </label>
              <input name="vehicle_count" type="number" min="1" max="500" step="1" value="1" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., 5">
            </div>
          </div>

          <div class="border-t border-slate-200 dark:border-slate-700 pt-5">
            <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-3">Upload Requirements</div>
            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900/30 border border-slate-200 dark:border-slate-700">
              <div class="text-sm text-slate-700 dark:text-slate-100 font-semibold">Required uploads for tricycle operators:</div>
              <ul class="mt-2 space-y-1 text-xs text-slate-600 dark:text-slate-300">
                <li>Government ID</li>
                <li>Barangay Clearance</li>
                <li>Proof of Residency</li>
                <li>Police Clearance (optional)</li>
                <li>Application form</li>
              </ul>
            </div>
          </div>

          <div class="flex items-center justify-end gap-2 pt-2">
            <button id="btnTriSubmit" class="px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold">Submit</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
  (function(){
    const rootUrl = <?php echo json_encode($rootUrl); ?>;
    const canManage = <?php echo json_encode(has_permission('module2.franchises.manage')); ?>;
    const useMock = <?php echo json_encode($useMock); ?>;
    const mockApps = useMock ? ({
      90001: {
        application_id: 90001,
        franchise_ref_number: 'FR-2026-0001',
        operator_id: 101,
        operator_name: 'UV Express Operators Cooperative',
        operator_type: 'Cooperative',
        operator_status: 'Active',
        route_id: 1,
        route_code: 'R-001',
        route_name: 'Bagumbong - Novaliches Bayan',
        origin: 'Bagumbong',
        destination: 'Novaliches Bayan',
        route_status: 'Active',
        vehicle_count: 15,
        status: 'PA Issued',
        submitted_at: <?php echo json_encode(date('c', strtotime('-9 days'))); ?>,
        endorsed_at: <?php echo json_encode(date('c', strtotime('-7 days'))); ?>,
        approved_at: <?php echo json_encode(date('c', strtotime('-5 days'))); ?>,
        ltfrb_ref_no: '2026-0001',
        remarks: 'Approved for operations subject to standard compliance requirements.',
        routes_display: 'R-001 • Bagumbong → Novaliches Bayan | R-002 • Bagumbong → Deparo',
      },
      90002: {
        application_id: 90002,
        franchise_ref_number: 'FR-2026-0002',
        operator_id: 102,
        operator_name: 'Bagumbong Jeepney Operators Association',
        operator_type: 'Association',
        operator_status: 'Active',
        route_id: 3,
        route_code: 'R-003',
        route_name: 'Bagumbong - Deparo',
        origin: 'Bagumbong',
        destination: 'Deparo',
        route_status: 'Active',
        vehicle_count: 12,
        status: 'CPC Issued',
        submitted_at: <?php echo json_encode(date('c', strtotime('-20 days'))); ?>,
        endorsed_at: <?php echo json_encode(date('c', strtotime('-18 days'))); ?>,
        approved_at: <?php echo json_encode(date('c', strtotime('-16 days'))); ?>,
        ltfrb_ref_no: '2026-0002',
        remarks: 'Approved for operations subject to standard compliance requirements.',
        routes_display: 'R-003 • Bagumbong → Deparo',
      }
    }) : ({});

    function showToast(message, type) {
      const container = document.getElementById('toast-container');
      if (!container) return;
      const t = (type || 'success').toString();
      const color = t === 'error' ? 'bg-rose-600' : 'bg-emerald-600';
      const el = document.createElement('div');
      el.className = `pointer-events-auto px-4 py-3 rounded-xl shadow-lg text-white text-sm font-semibold ${color}`;
      el.textContent = message;
      container.appendChild(el);
      setTimeout(() => { el.classList.add('opacity-0'); el.style.transition = 'opacity 250ms'; }, 2600);
      setTimeout(() => { el.remove(); }, 3000);
    }

    const modal = document.getElementById('modalApp');
    const backdrop = document.getElementById('modalAppBackdrop');
    const panel = document.getElementById('modalAppPanel');
    const body = document.getElementById('modalAppBody');
    const title = document.getElementById('modalAppTitle');
    const closeBtn = document.getElementById('modalAppClose');

    function openModal(html, t) {
      if (t) title.textContent = t;
      body.innerHTML = html;
      modal.classList.remove('hidden');
      requestAnimationFrame(() => {
        backdrop.classList.remove('opacity-0');
        panel.classList.remove('scale-95','opacity-0');
      });
      if (window.lucide) window.lucide.createIcons();
    }
    function closeModal() {
      panel.classList.add('scale-95','opacity-0');
      backdrop.classList.add('opacity-0');
      setTimeout(() => {
        modal.classList.add('hidden');
        body.innerHTML = '';
      }, 200);
    }
    if (backdrop) backdrop.addEventListener('click', closeModal);
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal(); });

    function formatDate(v) {
      if (!v) return '-';
      const d = new Date(v);
      if (isNaN(d.getTime())) return String(v);
      return d.toLocaleString();
    }

    function prettyMissing(list) {
      if (!Array.isArray(list) || !list.length) return '';
      return list.filter(Boolean).join(', ');
    }

    async function loadApp(appId) {
      const id = Number(appId || 0);
      try {
        const res = await fetch(rootUrl + '/admin/api/module2/get_application.php?application_id=' + encodeURIComponent(appId));
        const data = await res.json();
        if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'load_failed');
        return data.data;
      } catch (e) {
        if (mockApps && mockApps[id]) return mockApps[id];
        throw e;
      }
    }

    async function loadOperatorDocs(operatorId) {
      const res = await fetch(rootUrl + '/admin/api/module2/list_operator_verified_docs.php?operator_id=' + encodeURIComponent(operatorId));
      const data = await res.json();
      if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'load_failed');
      return Array.isArray(data.data) ? data.data : [];
    }

    let routesCache = null;
    async function loadRoutes() {
      if (routesCache) return routesCache;
      const res = await fetch(rootUrl + '/admin/api/module2/routes_list.php');
      const data = await res.json();
      if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'routes_load_failed');
      routesCache = Array.isArray(data.data) ? data.data : [];
      return routesCache;
    }
    function routeOptionLabel(r) {
      const code = (r.route_code || r.route_id || '-').toString();
      const name = (r.route_name || '').toString();
      const od = ((r.origin || '') + (r.destination ? (' → ' + r.destination) : '')).trim();
      return [code, name && name !== code ? name : '', od].filter(Boolean).join(' • ');
    }

    function operatorDocLabel(d) {
      const remarks = (d && d.remarks) ? String(d.remarks) : '';
      const labelPart = remarks.split('|')[0].trim();
      if (labelPart) return labelPart;
      const dt = (d && (d.doc_type || d.type)) ? String(d.doc_type || d.type) : '';
      const map = {
        GovID: 'Government ID',
        CDA: 'CDA Document',
        SEC: 'SEC Document',
        BarangayCert: 'Barangay Document',
        Others: 'Supporting Document',
      };
      return map[dt] || dt || 'Document';
    }

    async function loadApplicationDocs(appId) {
      const res = await fetch(rootUrl + '/admin/api/module2/list_application_docs.php?application_id=' + encodeURIComponent(appId));
      const data = await res.json();
      if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'load_failed');
      return Array.isArray(data.data) ? data.data : [];
    }

    document.querySelectorAll('[data-app-view="1"]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const appId = btn.getAttribute('data-app-id');
        openModal('<div class="text-sm text-slate-500 dark:text-slate-400">Loading...</div>', 'Application');
        try {
          const a = await loadApp(appId);
          const docs = a && a.operator_id ? await loadOperatorDocs(a.operator_id) : [];
          const appDocs = a && a.application_id ? await loadApplicationDocs(a.application_id) : [];
          const routeLabel = (a.routes_display || '').toString().trim()
            || ((a.route_code || '-') + ((a.origin || a.destination) ? (' • ' + (a.origin || '') + ' → ' + (a.destination || '')) : ''));
          body.innerHTML = `
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
              <div class="lg:col-span-2 space-y-4">
                <div class="p-5 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700">
                  <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Application</div>
                  <div class="mt-2 text-lg font-black text-slate-900 dark:text-white">APP-${a.application_id}</div>
                  <div class="mt-1 text-sm text-slate-600 dark:text-slate-300">${(a.franchise_ref_number || '').toString()}</div>
                </div>
                <div class="p-5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
                  <div class="flex items-center justify-between gap-3 mb-3">
                    <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Details</div>
                    <button type="button" id="btnEditApp" class="inline-flex items-center justify-center p-2 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors" title="Edit">
                      <i data-lucide="pencil" class="w-4 h-4"></i>
                    </button>
                  </div>
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4" id="appDetailsView">
                    <div>
                      <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Operator</div>
                      <div class="mt-1 font-bold text-slate-900 dark:text-white">${(a.operator_name || '').toString()}</div>
                      <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">Type: ${(a.operator_type || '-').toString()} • Status: ${(a.operator_status || '-').toString()}</div>
                    </div>
                    <div>
                      <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Route</div>
                      <div class="mt-1 font-bold text-slate-900 dark:text-white">${routeLabel}</div>
                      <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">Route status: ${(a.route_status || '-').toString()}</div>
                    </div>
                    <div>
                      <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Vehicle Count</div>
                      <div class="mt-1 font-bold text-slate-900 dark:text-white">${Number(a.vehicle_count || 0)}</div>
                    </div>
                    <div>
                      <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Status</div>
                      <div class="mt-1 font-bold text-slate-900 dark:text-white">${(a.status || '-').toString()}</div>
                    </div>
                    <div class="sm:col-span-2">
                      <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Representative Name</div>
                      <div class="mt-1 font-bold text-slate-900 dark:text-white">${(a.representative_name || '-').toString()}</div>
                    </div>
                    <div>
                      <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Submitted</div>
                      <div class="mt-1 text-sm text-slate-700 dark:text-slate-200">${formatDate(a.submitted_at)}</div>
                    </div>
                    <div class="sm:col-span-2">
                      <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Remarks</div>
                      <div class="mt-1 text-sm text-slate-700 dark:text-slate-200 whitespace-pre-wrap">${(a.remarks || '').toString() || '-'}</div>
                    </div>
                  </div>
                  <form id="appDetailsEdit" class="hidden space-y-4" novalidate>
                    <input type="hidden" name="application_id" value="${Number(a.application_id || 0)}">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                      <div class="sm:col-span-2">
                        <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Route</label>
                        <select name="route_id" id="editRouteId" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold"></select>
                      </div>
                      <div>
                        <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Vehicle Count</label>
                        <input name="vehicle_count" type="number" min="1" max="5000" value="${Number(a.vehicle_count || 0)}" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
                      </div>
                      <div class="sm:col-span-2">
                        <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Representative Name</label>
                        <input name="representative_name" maxlength="120" value="${(a.representative_name || '').toString()}" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
                      </div>
                    </div>
                    <div class="flex items-center justify-end gap-2">
                      <button type="button" id="btnCancelEditApp" class="px-4 py-2.5 rounded-md bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 font-semibold">Cancel</button>
                      <button id="btnSaveEditApp" class="px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold">Save Changes</button>
                    </div>
                  </form>
                </div>
              </div>
              <div class="space-y-4">
                <div class="p-5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
                  <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Supporting Documents (Application)</div>
                  <div class="mt-3 space-y-2">
                    ${appDocs.length ? appDocs.map((d) => {
                      const href = rootUrl + '/admin/uploads/' + encodeURIComponent((d.file_path || '').toString());
                      return `<a href="${href}" target="_blank" class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 hover:border-blue-200 dark:hover:border-blue-800 hover:bg-blue-50/40 dark:hover:bg-blue-900/10 transition-all">
                        <div>
                          <div class="text-sm font-black text-slate-800 dark:text-white">${(d.type || '').toString()}</div>
                          <div class="text-xs text-slate-500 dark:text-slate-400">${formatDate(d.uploaded_at)}</div>
                        </div>
                        <div class="text-slate-400 hover:text-blue-600"><i data-lucide="external-link" class="w-4 h-4"></i></div>
                      </a>`;
                    }).join('') : `<div class="text-sm text-slate-500 dark:text-slate-400 italic">No application documents uploaded.</div>`}
                  </div>
                </div>
                <div class="p-5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
                  <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Verified Operator Documents</div>
                  <div class="mt-3 space-y-2">
                    ${docs.length ? docs.map((d) => {
                      const href = rootUrl + '/admin/uploads/' + encodeURIComponent((d.file_path || '').toString());
                      const vdt = d.verified_at ? new Date(d.verified_at) : null;
                      const vdate = vdt && !isNaN(vdt.getTime()) ? vdt.toLocaleString() : '';
                      const vby = (d.verified_by_name || '').toString().trim();
                      return `<a href="${href}" target="_blank" class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 hover:border-blue-200 dark:hover:border-blue-800 hover:bg-blue-50/40 dark:hover:bg-blue-900/10 transition-all">
                        <div>
                          <div class="text-sm font-black text-slate-800 dark:text-white">${operatorDocLabel(d)}</div>
                          <div class="text-xs text-slate-500 dark:text-slate-400">${formatDate(d.uploaded_at)}</div>
                          ${(vby || vdate) ? `<div class="text-[11px] text-slate-500 dark:text-slate-400 font-semibold">Verified by ${vby || '-'} • ${vdate || '-'}</div>` : ``}
                        </div>
                        <div class="text-slate-400 hover:text-blue-600"><i data-lucide="external-link" class="w-4 h-4"></i></div>
                      </a>`;
                    }).join('') : `<div class="text-sm text-slate-500 dark:text-slate-400 italic">No verified operator documents found.</div>`}
                  </div>
                </div>
              </div>
            </div>
          `;
          if (window.lucide) window.lucide.createIcons();

          const btnEdit = body.querySelector('#btnEditApp');
          const viewBox = body.querySelector('#appDetailsView');
          const editForm = body.querySelector('#appDetailsEdit');
          const routeSel = body.querySelector('#editRouteId');
          const btnCancel = body.querySelector('#btnCancelEditApp');
          const btnSave = body.querySelector('#btnSaveEditApp');

          async function openEdit() {
            if (!editForm || !viewBox) return;
            editForm.classList.remove('hidden');
            viewBox.classList.add('hidden');
            if (routeSel) {
              routeSel.innerHTML = `<option value="">Loading...</option>`;
              const routes = await loadRoutes();
              routeSel.innerHTML = routes.map((r) => {
                const rid = Number(r.id || 0);
                const selected = rid === Number(a.route_id || 0) ? 'selected' : '';
                return `<option value="${rid}" ${selected}>${routeOptionLabel(r)}</option>`;
              }).join('');
            }
          }
          function closeEdit() {
            if (!editForm || !viewBox) return;
            editForm.classList.add('hidden');
            viewBox.classList.remove('hidden');
          }

          if (btnEdit) btnEdit.addEventListener('click', () => { openEdit().catch((e) => showToast(e.message || 'Failed', 'error')); });
          if (btnCancel) btnCancel.addEventListener('click', closeEdit);
          if (editForm) {
            editForm.addEventListener('submit', async (e) => {
              e.preventDefault();
              if (!btnSave) return;
              btnSave.disabled = true;
              const oldText = btnSave.textContent;
              btnSave.textContent = 'Saving...';
              try {
                const fd = new FormData(editForm);
                const res = await fetch(rootUrl + '/admin/api/module2/update_application.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (!data || !data.ok) throw new Error((data && (data.message || data.error)) ? (data.message || data.error) : 'update_failed');
                showToast('Application updated.');
                window.location.reload();
              } catch (err) {
                showToast(err.message || 'Failed', 'error');
              } finally {
                btnSave.disabled = false;
                btnSave.textContent = oldText;
              }
            });
          }
        } catch (err) {
          body.innerHTML = `<div class="text-sm text-rose-600">${(err && err.message) ? err.message : 'Failed to load.'}</div>`;
        }
      });
    });

    const highlight = document.getElementById('app-row-highlight');
    if (highlight) setTimeout(() => { highlight.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 300);
  })();
</script>
