<?php
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module1.read','module1.write']);

require_once __DIR__ . '/../../includes/db.php';
$db = db();

$q = trim((string)($_GET['q'] ?? ''));
$vehicleType = trim((string)($_GET['vehicle_type'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';

$canManage = has_any_permission(['module1.routes.write','module1.write']);

$routeColsRes = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='routes' AND COLUMN_NAME IN ('fare_min','fare_max')"); 
$hasFareMin = false;
$hasFareMax = false;
if ($routeColsRes) {
  while ($c = $routeColsRes->fetch_assoc()) {
    $cn = (string)($c['COLUMN_NAME'] ?? '');
    if ($cn === 'fare_min') $hasFareMin = true;
    if ($cn === 'fare_max') $hasFareMax = true;
  }
}

$conds = ["1=1"];
$params = [];
$types = '';
if ($q !== '') {
  $like = "%$q%";
  $conds[] = "(r.route_id LIKE ? OR r.route_code LIKE ? OR r.route_name LIKE ? OR r.origin LIKE ? OR r.destination LIKE ?)";
  $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
  $types .= 'sssss';
}
if ($vehicleType !== '' && $vehicleType !== 'Vehicle type') {
  $conds[] = "EXISTS (SELECT 1 FROM route_vehicle_types aa WHERE aa.route_id=r.id AND aa.vehicle_type=? AND aa.vehicle_type<>'Tricycle')";
  $params[] = $vehicleType;
  $types .= 's';
}
if ($status !== '' && $status !== 'Status') {
  $conds[] = "r.status=?";
  $params[] = $status;
  $types .= 's';
}

$useAlloc = false;
$tAlloc = $db->query("SHOW TABLES LIKE 'route_vehicle_types'");
if ($tAlloc && $tAlloc->num_rows > 0) $useAlloc = true;

if ($useAlloc) {
  $sql = "SELECT
    r.id AS corridor_id,
    r.route_id,
    r.route_code,
    r.route_name,
    r.origin,
    r.destination,
    r.via,
    r.structure,
    r.distance_km,
    r.status AS corridor_status,
    a.id AS id,
    a.vehicle_type,
    a.authorized_units,
    a.fare_min AS fare_min,
    a.fare_max AS fare_max,
    a.status,
    r.created_at,
    r.updated_at,
    COALESCE(u.used_units,0) AS used_units,
    COALESCE(tc.terminal_categories, 'Unmapped') AS terminal_categories,
    COALESCE(tc.primary_terminal_category, 'Unmapped') AS primary_terminal_category
  FROM routes r
  LEFT JOIN route_vehicle_types a ON a.route_id=r.id AND a.vehicle_type<>'Tricycle'
  LEFT JOIN (
    SELECT
      tr.route_id,
      GROUP_CONCAT(DISTINCT COALESCE(NULLIF(t.category,''),'Unclassified') ORDER BY COALESCE(NULLIF(t.category,''),'Unclassified') SEPARATOR ' • ') AS terminal_categories,
      MIN(COALESCE(NULLIF(t.category,''),'Unclassified')) AS primary_terminal_category
    FROM terminal_routes tr
    JOIN terminals t ON t.id=tr.terminal_id AND COALESCE(t.type,'') <> 'Parking'
    GROUP BY tr.route_id
  ) tc ON tc.route_id=r.route_id
  LEFT JOIN (
    SELECT route_id, vehicle_type, COALESCE(SUM(vehicle_count),0) AS used_units
    FROM franchise_applications
    WHERE status IN ('Endorsed','LGU-Endorsed','Approved','LTFRB-Approved','PA Issued','CPC Issued')
    GROUP BY route_id, vehicle_type
  ) u ON u.route_id=r.id AND u.vehicle_type=a.vehicle_type
  WHERE " . implode(' AND ', $conds) . "
  ORDER BY COALESCE(tc.primary_terminal_category,'Unmapped') ASC, COALESCE(NULLIF(r.route_code,''), r.route_id) ASC, a.vehicle_type ASC, a.id DESC
  LIMIT 1000";
} else {
  $condsLegacy = ["1=1"];
  if ($q !== '') {
    $condsLegacy[] = "(r.route_id LIKE ? OR r.route_code LIKE ? OR r.route_name LIKE ? OR r.origin LIKE ? OR r.destination LIKE ?)";
  }
  if ($vehicleType !== '' && $vehicleType !== 'Vehicle type') {
    $condsLegacy[] = "r.vehicle_type=?";
  }
  if ($status !== '' && $status !== 'Status') {
    $condsLegacy[] = "r.status=?";
  }
  $sql = "SELECT
    r.id,
    r.route_id,
    r.route_code,
    r.route_name,
    r.vehicle_type,
    r.origin,
    r.destination,
    r.via,
    r.structure,
    r.distance_km,
    r.authorized_units,
    r.fare,
    " . ($hasFareMin ? "r.fare_min" : "NULL") . " AS fare_min,
    " . ($hasFareMax ? "r.fare_max" : "NULL") . " AS fare_max,
    r.status,
    r.created_at,
    r.updated_at,
    COALESCE(u.used_units,0) AS used_units,
    COALESCE(tc.terminal_categories, 'Unmapped') AS terminal_categories,
    COALESCE(tc.primary_terminal_category, 'Unmapped') AS primary_terminal_category
  FROM routes r
  LEFT JOIN (
    SELECT
      tr.route_id,
      GROUP_CONCAT(DISTINCT COALESCE(NULLIF(t.category,''),'Unclassified') ORDER BY COALESCE(NULLIF(t.category,''),'Unclassified') SEPARATOR ' • ') AS terminal_categories,
      MIN(COALESCE(NULLIF(t.category,''),'Unclassified')) AS primary_terminal_category
    FROM terminal_routes tr
    JOIN terminals t ON t.id=tr.terminal_id AND COALESCE(t.type,'') <> 'Parking'
    GROUP BY tr.route_id
  ) tc ON tc.route_id=r.route_id
  LEFT JOIN (
    SELECT route_id, COALESCE(SUM(vehicle_count),0) AS used_units
    FROM franchise_applications
    WHERE status IN ('Endorsed','LGU-Endorsed','Approved','LTFRB-Approved','PA Issued','CPC Issued')
    GROUP BY route_id
  ) u ON u.route_id=r.id
  WHERE " . implode(' AND ', $condsLegacy) . "
  ORDER BY r.status='Active' DESC, COALESCE(tc.primary_terminal_category,'Unmapped') ASC, COALESCE(NULLIF(r.route_code,''), r.route_id) ASC, r.id DESC
  LIMIT 1000";
}

$routes = [];
if ($params) {
  $stmt = $db->prepare($sql);
  if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $routes[] = $r;
    $stmt->close();
  }
} else {
  $res = $db->query($sql);
  if ($res) while ($r = $res->fetch_assoc()) $routes[] = $r;
}

$stripPrefix = function (string $code): string {
  $c = strtoupper(trim($code));
  $c = preg_replace('/^(BUS|UV|JEEP|JEEPNEY|TRI|TRICYCLE)\-+/', '', $c);
  $c = trim((string)$c);
  return $c !== '' ? $c : strtoupper(trim($code));
};

$corridors = [];
$legacyGroups = [];

if ($useAlloc) {
  foreach ($routes as $r) {
    $cid = (int)($r['corridor_id'] ?? 0);
    if ($cid <= 0) continue;
    if (!isset($corridors[$cid])) {
      $code = trim((string)($r['route_code'] ?? ''));
      if ($code === '') $code = trim((string)($r['route_id'] ?? ''));
      $catPrimary = trim((string)($r['primary_terminal_category'] ?? ''));
      if ($catPrimary === '') $catPrimary = 'Unmapped';
      $catList = trim((string)($r['terminal_categories'] ?? ''));
      if ($catList === '') $catList = $catPrimary;
      $corridors[$cid] = [
        'corridor_id' => $cid,
        'route_code' => $code,
        'route_name' => trim((string)($r['route_name'] ?? '')),
        'origin' => (string)($r['origin'] ?? ''),
        'destination' => (string)($r['destination'] ?? ''),
        'via' => (string)($r['via'] ?? ''),
        'structure' => (string)($r['structure'] ?? ''),
        'distance_km' => ($r['distance_km'] === null || $r['distance_km'] === '') ? null : (float)$r['distance_km'],
        'status' => (string)($r['corridor_status'] ?? $r['status'] ?? 'Active'),
        'terminal_categories' => $catList,
        'primary_terminal_category' => $catPrimary,
        'allocations' => [],
      ];
    }

    $vt = trim((string)($r['vehicle_type'] ?? ''));
    if ($vt === '') continue;
    $au = (int)($r['authorized_units'] ?? 0);
    $used = (int)($r['used_units'] ?? 0);
    $fareMin = $r['fare_min'] === null || $r['fare_min'] === '' ? null : (float)$r['fare_min'];
    $fareMax = $r['fare_max'] === null || $r['fare_max'] === '' ? null : (float)$r['fare_max'];
    if ($fareMax === null && $fareMin !== null) $fareMax = $fareMin;
    $corridors[$cid]['allocations'][] = [
      'vehicle_type' => $vt,
      'authorized_units' => $au,
      'used_units' => $used,
      'remaining_units' => $au > 0 ? max(0, $au - $used) : 0,
      'fare_min' => $fareMin,
      'fare_max' => $fareMax,
      'status' => (string)($r['status'] ?? 'Active'),
    ];
  }
} else {
  foreach ($routes as $r) {
    $code = trim((string)($r['route_code'] ?? ''));
    if ($code === '') $code = trim((string)($r['route_id'] ?? ''));
    $base = $stripPrefix($code);
    $key = $base !== '' ? $base : $code;
    if ($key === '') continue;

    if (!isset($legacyGroups[$key])) {
      $catPrimary = trim((string)($r['primary_terminal_category'] ?? ''));
      if ($catPrimary === '') $catPrimary = 'Unmapped';
      $catList = trim((string)($r['terminal_categories'] ?? ''));
      if ($catList === '') $catList = $catPrimary;
      $legacyGroups[$key] = [
        'base_code' => $key,
        'route_name' => trim((string)($r['route_name'] ?? '')),
        'origin' => (string)($r['origin'] ?? ''),
        'destination' => (string)($r['destination'] ?? ''),
        'via' => (string)($r['via'] ?? ''),
        'structure' => (string)($r['structure'] ?? ''),
        'distance_km' => ($r['distance_km'] === null || $r['distance_km'] === '') ? null : (float)$r['distance_km'],
        'status' => (string)($r['status'] ?? 'Active'),
        'terminal_categories' => $catList,
        'primary_terminal_category' => $catPrimary,
        'allocations' => [],
      ];
    }

    $vt = trim((string)($r['vehicle_type'] ?? ''));
    if ($vt === '' || $vt === 'Tricycle') continue;
    $au = (int)($r['authorized_units'] ?? 0);
    $used = (int)($r['used_units'] ?? 0);
    $fareMin = $r['fare_min'] === null || $r['fare_min'] === '' ? null : (float)$r['fare_min'];
    $fareMax = $r['fare_max'] === null || $r['fare_max'] === '' ? null : (float)$r['fare_max'];
    $storedFare = $r['fare'] === null || $r['fare'] === '' ? null : (float)$r['fare'];
    if ($fareMin === null && $storedFare !== null) $fareMin = $storedFare;
    if ($fareMax === null && $storedFare !== null) $fareMax = $storedFare;
    if ($fareMax === null && $fareMin !== null) $fareMax = $fareMin;
    $legacyGroups[$key]['allocations'][$vt] = [
      'vehicle_type' => $vt,
      'authorized_units' => $au,
      'used_units' => $used,
      'remaining_units' => $au > 0 ? max(0, $au - $used) : 0,
      'fare_min' => $fareMin,
      'fare_max' => $fareMax,
      'status' => (string)($r['status'] ?? 'Active'),
    ];
  }
  foreach ($legacyGroups as $k => $g) {
    $legacyGroups[$k]['allocations'] = array_values($g['allocations']);
  }
}

$corridors = array_values($corridors);
$legacyGroups = array_values($legacyGroups);
?>

<div class="mx-auto max-w-7xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-6">
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between border-b border-slate-200 dark:border-slate-700 pb-6">
    <div>
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">List of Routes</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-3xl">Define authorized routes, set capacity limits, and use route availability as the basis for franchise endorsement.</p>
    </div>
    <div class="flex flex-col sm:flex-row sm:items-center gap-2 w-full sm:w-auto">
      <?php if ($canManage): ?>
        <button type="button" id="btnAddRoute" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-md bg-blue-700 hover:bg-blue-800 px-4 py-2.5 text-sm font-semibold text-white transition-colors">
          <i data-lucide="plus" class="w-4 h-4"></i>
          Add Route
        </button>
      <?php endif; ?>
      <a href="?page=puv-database/tricycle-service-areas" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
        <i data-lucide="map" class="w-4 h-4"></i>
        Tricycle Service Areas
      </a>
    </div>
  </div>

  <div id="toast-container" class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 z-[120] flex flex-col gap-3 pointer-events-none"></div>

  <div class="bg-white dark:bg-slate-800 p-5 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
    <?php
      $exportItems = [];
      if (has_permission('reports.export')) {
        $exportItems[] = [
          'href' => $rootUrl . '/admin/api/module1/export_routes.php?' . http_build_query(['q' => $q, 'vehicle_type' => $vehicleType, 'status' => $status, 'format' => 'csv']),
          'label' => 'CSV',
          'icon' => 'download'
        ];
        $exportItems[] = [
          'href' => $rootUrl . '/admin/api/module1/export_routes.php?' . http_build_query(['q' => $q, 'vehicle_type' => $vehicleType, 'status' => $status, 'format' => 'excel']),
          'label' => 'Excel',
          'icon' => 'file-spreadsheet'
        ];
      }
      if ($canManage) {
        $exportItems[] = [
          'href' => $rootUrl . '/admin/tools/normalize_routes_realworld.php',
          'label' => 'Normalize',
          'icon' => 'wand-2',
          'target' => '_blank'
        ];
        $exportItems[] = [
          'tag' => 'button',
          'label' => 'Import',
          'icon' => 'upload',
          'attrs' => ['id' => 'btnImportRoutes']
        ];
      }
      if ($exportItems) tmm_render_export_toolbar($exportItems);
    ?>
    <div id="modalImportRoutes" class="fixed inset-0 z-[140] hidden items-center justify-center p-4">
      <div class="absolute inset-0 bg-slate-900/50" data-import-close="1"></div>
      <div class="relative w-full max-w-lg rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-xl p-6">
        <div class="text-lg font-black text-slate-900 dark:text-white">Import Routes</div>
        <div class="mt-1 text-sm font-semibold text-slate-500 dark:text-slate-400">Upload a CSV file.</div>
        <div class="mt-4">
          <input id="fileImportRoutes" type="file" accept=".csv,text/csv" class="w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-blue-700 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-blue-800">
        </div>
        <div class="mt-5 flex items-center justify-end gap-2">
          <button type="button" id="btnCancelImportRoutes" class="px-4 py-2.5 rounded-md bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 font-semibold">Cancel</button>
          <button type="button" id="btnUploadImportRoutes" class="px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold">Upload</button>
        </div>
      </div>
    </div>
    <form class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between" method="GET">
      <input type="hidden" name="page" value="puv-database/routes-lptrp">
      <div class="flex-1 flex flex-col sm:flex-row gap-3">
        <div class="relative flex-1 sm:max-w-md group">
          <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 group-focus-within:text-blue-500 transition-colors"></i>
          <input name="q" value="<?php echo htmlspecialchars($q); ?>" class="w-full pl-10 pr-4 py-2.5 text-sm font-semibold border-0 rounded-md bg-slate-50 dark:bg-slate-900/40 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-1 focus:ring-blue-500 transition-all placeholder:text-slate-400" placeholder="Search route code/name...">
        </div>
        <div class="relative w-full sm:w-52">
          <select name="vehicle_type" class="px-4 py-2.5 pr-10 text-sm font-semibold border-0 rounded-md bg-slate-50 dark:bg-slate-900/40 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-1 focus:ring-blue-500 transition-all appearance-none cursor-pointer">
            <option value="">All Vehicle Types</option>
            <?php foreach (['Jeepney','UV','Bus'] as $t): ?>
              <option value="<?php echo htmlspecialchars($t); ?>" <?php echo $vehicleType === $t ? 'selected' : ''; ?>><?php echo htmlspecialchars($t); ?></option>
            <?php endforeach; ?>
          </select>
          <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center">
            <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400"></i>
          </span>
        </div>
        <div class="relative w-full sm:w-44">
          <select name="status" class="px-4 py-2.5 pr-10 text-sm font-semibold border-0 rounded-md bg-slate-50 dark:bg-slate-900/40 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-1 focus:ring-blue-500 transition-all appearance-none cursor-pointer">
            <option value="">All Status</option>
            <?php foreach (['Active','Inactive'] as $s): ?>
              <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $status === $s ? 'selected' : ''; ?>><?php echo htmlspecialchars($s); ?></option>
            <?php endforeach; ?>
          </select>
          <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center">
            <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400"></i>
          </span>
        </div>
      </div>
      <div class="flex flex-col sm:flex-row sm:items-center gap-2 w-full sm:w-auto">
        <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-md bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 dark:hover:bg-slate-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors">
          <i data-lucide="filter" class="w-4 h-4"></i>
          Apply
        </button>
        <a href="?page=puv-database/routes-lptrp" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
          Reset
        </a>
      </div>
    </form>
  </div>

  <div class="space-y-4">
    <?php $items = $useAlloc ? $corridors : $legacyGroups; ?>
    <?php if (!$items): ?>
      <div class="bg-white dark:bg-slate-800 p-10 rounded-lg border border-slate-200 dark:border-slate-700 text-center text-sm text-slate-500 dark:text-slate-400 italic">No routes found.</div>
    <?php endif; ?>

    <?php $currentCat = null; ?>
    <?php foreach ($items as $r): ?>
      <?php
        $catPrimary = trim((string)($r['primary_terminal_category'] ?? ''));
        if ($catPrimary === '') $catPrimary = 'Unmapped';
        $catList = trim((string)($r['terminal_categories'] ?? ''));
        if ($catList === '') $catList = $catPrimary;
        $st = trim((string)($r['status'] ?? 'Active'));
        $badge = $st === 'Active'
          ? 'bg-emerald-100 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-400 dark:ring-emerald-500/20'
          : 'bg-slate-100 text-slate-700 ring-slate-600/20 dark:bg-slate-700/40 dark:text-slate-300 dark:ring-slate-500/20';
        $code = $useAlloc ? (string)($r['route_code'] ?? '') : (string)($r['base_code'] ?? '');
        $name = (string)($r['route_name'] ?? '');
        $fullRoute = trim((string)($r['origin'] ?? '') . ' → ' . (string)($r['destination'] ?? ''));
        $allocs = is_array($r['allocations'] ?? null) ? $r['allocations'] : [];
        $rowPayload = [
          'corridor_id' => (int)($r['corridor_id'] ?? 0),
          'route_code' => $code,
          'route_name' => $name,
          'origin' => (string)($r['origin'] ?? ''),
          'destination' => (string)($r['destination'] ?? ''),
          'via' => (string)($r['via'] ?? ''),
          'structure' => (string)($r['structure'] ?? ''),
          'distance_km' => $r['distance_km'] ?? null,
          'status' => $st,
          'terminal_categories' => $catList,
          'primary_terminal_category' => $catPrimary,
          'allocations' => $allocs,
          'legacy' => !$useAlloc,
        ];
      ?>

      <?php if ($currentCat !== $catPrimary): ?>
        <?php if ($currentCat !== null): ?>
          </div>
        </div>
        <?php endif; ?>
        <?php $currentCat = $catPrimary; ?>
        <div class="bg-white dark:bg-slate-800 p-5 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
          <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-2">
              <span class="w-2.5 h-2.5 rounded-full bg-blue-600 dark:bg-blue-400"></span>
              <div class="text-sm font-black uppercase tracking-widest text-slate-700 dark:text-slate-200"><?php echo htmlspecialchars($currentCat); ?></div>
            </div>
            <div class="text-xs font-semibold text-slate-500 dark:text-slate-400"><?php echo $useAlloc ? 'Route Corridors' : 'Legacy (grouped)'; ?></div>
          </div>
          <div class="mt-4 space-y-4">
      <?php endif; ?>

      <details class="group bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        <summary class="list-none cursor-pointer p-5">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-2">
                <div class="text-lg font-black text-slate-900 dark:text-white truncate"><?php echo htmlspecialchars($code); ?></div>
                <span class="px-2.5 py-1 rounded-lg text-xs font-bold ring-1 ring-inset <?php echo $badge; ?>"><?php echo htmlspecialchars($st); ?></span>
                <?php if (!$allocs): ?>
                  <span class="px-2.5 py-1 rounded-lg text-xs font-bold ring-1 ring-inset bg-amber-100 text-amber-700 ring-amber-600/20 dark:bg-amber-900/30 dark:text-amber-300 dark:ring-amber-500/20">Missing Allowed Vehicle Types</span>
                <?php endif; ?>
              </div>
              <div class="mt-1 text-sm text-slate-600 dark:text-slate-300 font-semibold truncate"><?php echo htmlspecialchars($name !== '' ? $name : '-'); ?></div>
              <div class="mt-1 text-xs text-slate-500 dark:text-slate-400 font-semibold"><?php echo htmlspecialchars($fullRoute !== ' → ' ? $fullRoute : '-'); ?></div>
              <div class="mt-3 flex flex-wrap gap-2">
                <?php foreach (array_values(array_filter(array_map('trim', explode('•', $catList)))) as $tag): ?>
                  <span class="inline-flex items-center rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 px-3 py-1.5 text-[11px] font-bold text-slate-700 dark:text-slate-200"><?php echo htmlspecialchars($tag); ?></span>
                <?php endforeach; ?>
              </div>
              <?php if ($allocs): ?>
                <div class="mt-3 flex flex-wrap gap-2">
                  <?php foreach (array_slice($allocs, 0, 6) as $a): ?>
                    <?php
                      $vt = (string)($a['vehicle_type'] ?? '');
                      $fareMin = $a['fare_min'] === null || $a['fare_min'] === '' ? null : (float)$a['fare_min'];
                      $fareMax = $a['fare_max'] === null || $a['fare_max'] === '' ? null : (float)$a['fare_max'];
                      if ($fareMax === null && $fareMin !== null) $fareMax = $fareMin;
                      $fareText = '—';
                      if ($fareMin !== null) {
                        if ($fareMax !== null && abs($fareMin - $fareMax) >= 0.001) $fareText = '₱' . number_format($fareMin, 2) . '–' . number_format($fareMax, 2);
                        else $fareText = '₱' . number_format((float)$fareMin, 2);
                      }
                    ?>
                    <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200">
                      <i data-lucide="tag" class="w-3.5 h-3.5 text-slate-400"></i>
                      <?php echo htmlspecialchars($vt); ?>
                      <span class="text-slate-500 dark:text-slate-400"><?php echo htmlspecialchars($fareText); ?></span>
                    </span>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>

            <div class="flex items-center gap-2 shrink-0">
              <button type="button" class="route-action inline-flex items-center justify-center p-2 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors" data-route-view="1" data-route="<?php echo htmlspecialchars(json_encode($rowPayload), ENT_QUOTES); ?>" title="View">
                <i data-lucide="eye" class="w-4 h-4"></i>
              </button>
              <?php if ($canManage && $useAlloc): ?>
                <button type="button" class="route-action inline-flex items-center justify-center p-2 rounded-lg bg-slate-900 dark:bg-slate-700 text-white hover:bg-slate-800 dark:hover:bg-slate-600 transition-colors" data-route-edit="1" data-route="<?php echo htmlspecialchars(json_encode($rowPayload), ENT_QUOTES); ?>" title="Edit">
                  <i data-lucide="pencil" class="w-4 h-4"></i>
                </button>
              <?php endif; ?>
              <div class="p-2 rounded-lg text-slate-400 group-open:rotate-180 transition-transform">
                <i data-lucide="chevron-down" class="w-4 h-4"></i>
              </div>
            </div>
          </div>
        </summary>
        <div class="px-5 pb-5">
          <?php if (!$useAlloc): ?>
            <div class="mb-4 p-4 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800">
              <div class="text-sm font-black text-amber-900 dark:text-amber-200">Legacy route group</div>
              <div class="mt-1 text-xs font-semibold text-amber-800 dark:text-amber-200/80">This is grouped from old route-per-vehicle-type records. Use the normalization tool to convert them into real-life route corridors with allocations.</div>
            </div>
          <?php endif; ?>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900/30 border border-slate-200 dark:border-slate-700">
              <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Route Details</div>
              <div class="mt-2 space-y-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
                <div><span class="text-slate-500 dark:text-slate-400">Origin:</span> <?php echo htmlspecialchars((string)($r['origin'] ?? '-') ?: '-'); ?></div>
                <div><span class="text-slate-500 dark:text-slate-400">Destination:</span> <?php echo htmlspecialchars((string)($r['destination'] ?? '-') ?: '-'); ?></div>
                <div><span class="text-slate-500 dark:text-slate-400">Structure:</span> <?php echo htmlspecialchars((string)($r['structure'] ?? '-') ?: '-'); ?></div>
                <div><span class="text-slate-500 dark:text-slate-400">Distance:</span> <?php echo htmlspecialchars(($r['distance_km'] === null || $r['distance_km'] === '') ? '-' : (string)$r['distance_km']); ?></div>
              </div>
              <div class="mt-3 text-xs font-semibold text-slate-600 dark:text-slate-300 whitespace-pre-wrap"><?php echo htmlspecialchars(trim((string)($r['via'] ?? '')) !== '' ? (string)$r['via'] : ''); ?></div>
            </div>

            <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
              <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Allowed Vehicle Types</div>
              <div class="mt-3 space-y-3">
                <?php if (!$allocs): ?>
                  <div class="text-sm text-slate-500 dark:text-slate-400 italic">No allocations yet. A real-life route must have at least one allowed vehicle type with a fare.</div>
                <?php else: ?>
                  <?php foreach ($allocs as $a): ?>
                    <?php
                      $vt = (string)($a['vehicle_type'] ?? '');
                      $au = (int)($a['authorized_units'] ?? 0);
                      $used = (int)($a['used_units'] ?? 0);
                      $rem = (int)($a['remaining_units'] ?? ($au > 0 ? max(0, $au - $used) : 0));
                      $fareMin = $a['fare_min'] === null || $a['fare_min'] === '' ? null : (float)$a['fare_min'];
                      $fareMax = $a['fare_max'] === null || $a['fare_max'] === '' ? null : (float)$a['fare_max'];
                      if ($fareMax === null && $fareMin !== null) $fareMax = $fareMin;
                      $fareText = '-';
                      if ($fareMin !== null) {
                        if ($fareMax !== null && abs($fareMin - $fareMax) >= 0.001) $fareText = '₱ ' . number_format($fareMin, 2) . ' – ' . number_format($fareMax, 2);
                        else $fareText = '₱ ' . number_format((float)$fareMin, 2);
                      }
                      $ast = (string)($a['status'] ?? 'Active');
                      $fareMissing = $fareMin === null && $fareMax === null;
                    ?>
                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900/30 border border-slate-200 dark:border-slate-700">
                      <div class="flex items-center justify-between gap-3">
                        <div class="text-sm font-black text-slate-900 dark:text-white"><?php echo htmlspecialchars($vt); ?></div>
                        <div class="text-sm font-black <?php echo $fareMissing ? 'text-rose-700 dark:text-rose-400' : 'text-slate-900 dark:text-white'; ?>"><?php echo htmlspecialchars($fareMissing ? 'Missing fare' : $fareText); ?></div>
                      </div>
                      <div class="mt-1 flex flex-wrap gap-2 text-xs font-semibold text-slate-600 dark:text-slate-300">
                        <span>Authorized: <?php echo (int)$au; ?></span>
                        <span>Used: <?php echo (int)$used; ?></span>
                        <span>Remaining: <?php echo (int)$rem; ?></span>
                        <span>Status: <?php echo htmlspecialchars($ast); ?></span>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </details>

    <?php endforeach; ?>
    <?php if ($currentCat !== null): ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<div id="modalRoute" class="fixed inset-0 z-[200] hidden">
  <div id="modalRouteBackdrop" class="absolute inset-0 bg-slate-900/50 opacity-0 transition-opacity"></div>
  <div class="absolute inset-0 flex items-center justify-center p-4">
    <div id="modalRoutePanel" class="w-full max-w-3xl rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-xl transform scale-95 opacity-0 transition-all">
      <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700">
        <div class="font-black text-slate-900 dark:text-white" id="modalRouteTitle">Route</div>
        <button type="button" id="modalRouteClose" class="p-2 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
          <i data-lucide="x" class="w-4 h-4"></i>
        </button>
      </div>
      <div id="modalRouteBody" class="p-6 max-h-[80vh] overflow-y-auto"></div>
    </div>
  </div>
</div>

<script>
  (function(){
    const rootUrl = <?php echo json_encode($rootUrl); ?>;
    const canManage = <?php echo json_encode($canManage); ?>;

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

    const btnImportRoutes = document.getElementById('btnImportRoutes');
    const modalImportRoutes = document.getElementById('modalImportRoutes');
    const fileImportRoutes = document.getElementById('fileImportRoutes');
    const btnCancelImportRoutes = document.getElementById('btnCancelImportRoutes');
    const btnUploadImportRoutes = document.getElementById('btnUploadImportRoutes');
    if (btnImportRoutes && modalImportRoutes && fileImportRoutes && btnCancelImportRoutes && btnUploadImportRoutes) {
      const closeImport = () => modalImportRoutes.classList.add('hidden');
      const openImport = () => {
        fileImportRoutes.value = '';
        btnUploadImportRoutes.disabled = false;
        modalImportRoutes.classList.remove('hidden');
      };
      btnImportRoutes.addEventListener('click', openImport);
      btnCancelImportRoutes.addEventListener('click', closeImport);
      modalImportRoutes.querySelectorAll('[data-import-close="1"]').forEach((el) => el.addEventListener('click', closeImport));
      btnUploadImportRoutes.addEventListener('click', async () => {
        const f = fileImportRoutes.files && fileImportRoutes.files[0] ? fileImportRoutes.files[0] : null;
        if (!f) { showToast('Please choose a CSV file.', 'error'); return; }
        const fd = new FormData();
        fd.append('file', f);
        btnUploadImportRoutes.disabled = true;
        try {
          const res = await fetch(rootUrl + '/admin/api/module1/import_routes.php', { method: 'POST', body: fd });
          const data = await res.json();
          if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'import_failed');
          showToast(`Import complete: ${data.inserted || 0} inserted, ${data.updated || 0} updated, ${data.skipped || 0} skipped.`);
          closeImport();
          setTimeout(() => { window.location.reload(); }, 600);
        } catch (e) {
          showToast(e.message || 'Import failed', 'error');
          btnUploadImportRoutes.disabled = false;
        }
      });
    }

    const modal = document.getElementById('modalRoute');
    const backdrop = document.getElementById('modalRouteBackdrop');
    const panel = document.getElementById('modalRoutePanel');
    const body = document.getElementById('modalRouteBody');
    const title = document.getElementById('modalRouteTitle');
    const closeBtn = document.getElementById('modalRouteClose');

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

    function parsePayload(el) {
      try { return JSON.parse(el.getAttribute('data-route') || '{}'); } catch (e) { return {}; }
    }
    function esc(s) {
      return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\"/g,'&quot;');
    }

    function renderForm(r) {
      const corridorId = r && r.corridor_id ? Number(r.corridor_id) : 0;
      const isEdit = corridorId > 0;
      const routeCode = r && r.route_code ? String(r.route_code) : '';
      const routeName = r && r.route_name ? String(r.route_name) : '';
      const origin = r && r.origin ? String(r.origin) : '';
      const destination = r && r.destination ? String(r.destination) : '';
      const via = r && r.via ? String(r.via) : '';
      const structure = r && r.structure ? String(r.structure) : '';
      const distanceKm = (r && r.distance_km !== null && r.distance_km !== undefined && r.distance_km !== '') ? Number(r.distance_km) : '';
      const status = r && r.status ? String(r.status) : 'Active';

      return `
        <form id="formCorridorSave" class="space-y-5" novalidate>
          ${isEdit ? `<input type="hidden" name="corridor_id" value="${corridorId}">` : ``}
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Route Code</label>
              <input name="route_code" required maxlength="64" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold uppercase" value="${esc(routeCode)}" placeholder="e.g., R001-BAGUMBONG-DEPARO">
            </div>
            <div>
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Route Name</label>
              <input name="route_name" required maxlength="128" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" value="${esc(routeName)}" placeholder="e.g., Bagumbong - Deparo">
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Route Structure</label>
              <select name="structure" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
                <option value="">Select</option>
                ${['Loop','Point-to-Point'].map((t) => `<option value="${t}" ${t===structure?'selected':''}>${t}</option>`).join('')}
              </select>
            </div>
            <div>
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Distance (km)</label>
              <input name="distance_km" type="number" min="0" step="0.01" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" value="${distanceKm}">
            </div>
            <div>
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Status</label>
              <select name="status" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
                ${['Active','Inactive'].map((t) => `<option value="${t}" ${t===status?'selected':''}>${t}</option>`).join('')}
              </select>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Origin</label>
              <input name="origin" maxlength="100" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" value="${esc(origin)}" placeholder="Starting point">
            </div>
            <div>
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Destination</label>
              <input name="destination" maxlength="100" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" value="${esc(destination)}" placeholder="End point">
            </div>
          </div>

          <div>
            <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Via</label>
            <textarea name="via" rows="3" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="Major streets / barangays">${esc(via)}</textarea>
          </div>

          <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900/30 border border-slate-200 dark:border-slate-700">
            <div class="flex items-center justify-between gap-3">
              <div>
                <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Service Allocation (Per Vehicle Type)</div>
                <div class="mt-1 text-sm text-slate-600 dark:text-slate-300 font-semibold">Jeepney / UV / Bus only. Tricycles use Service Areas.</div>
              </div>
              <button type="button" id="btnAddAlloc" class="px-3 py-2 rounded-md bg-slate-900 dark:bg-slate-700 text-white font-semibold">Add</button>
            </div>
            <div id="allocRows" class="mt-4 space-y-3"></div>
          </div>

          <div class="flex items-center justify-end gap-2">
            <button type="button" class="px-4 py-2.5 rounded-md bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 font-semibold" data-close="1">Cancel</button>
            <button id="btnCorridorSave" class="px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold">${isEdit ? 'Save Changes' : 'Create Route'}</button>
          </div>
        </form>
      `;
    }

    function allocRowHtml(a) {
      const vt = a && a.vehicle_type ? String(a.vehicle_type) : '';
      const au = (a && a.authorized_units !== null && a.authorized_units !== undefined && a.authorized_units !== '') ? Number(a.authorized_units) : 0;
      const fmin = (a && a.fare_min !== null && a.fare_min !== undefined && a.fare_min !== '') ? Number(a.fare_min) : '';
      const fmax = (a && a.fare_max !== null && a.fare_max !== undefined && a.fare_max !== '') ? Number(a.fare_max) : '';
      const st = a && a.status ? String(a.status) : 'Active';
      return `
        <div class="alloc-row p-3 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
          <div class="grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
            <div class="md:col-span-2">
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Vehicle Type</label>
              <select name="alloc_vehicle_type" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
                <option value="">Select</option>
                ${['Jeepney','UV','Bus'].map((t) => `<option value="${t}" ${t===vt?'selected':''}>${t}</option>`).join('')}
              </select>
            </div>
            <div>
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Authorized</label>
              <input name="alloc_authorized_units" type="number" min="0" max="9999" step="1" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" value="${au || 0}">
            </div>
            <div>
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Fare Min</label>
              <input name="alloc_fare_min" type="number" min="0" step="0.01" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" value="${fmin}">
            </div>
            <div>
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Fare Max</label>
              <input name="alloc_fare_max" type="number" min="0" step="0.01" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" value="${fmax}">
            </div>
            <div class="flex items-center gap-2">
              <div class="flex-1">
                <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Status</label>
                <select name="alloc_status" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
                  ${['Active','Inactive'].map((t) => `<option value="${t}" ${t===st?'selected':''}>${t}</option>`).join('')}
                </select>
              </div>
              <button type="button" class="btnRemoveAlloc px-3 py-2.5 rounded-md bg-rose-600 hover:bg-rose-700 text-white font-semibold">Remove</button>
            </div>
          </div>
        </div>
      `;
    }

    function collectAllocs() {
      const box = document.getElementById('allocRows');
      if (!box) return [];
      const rows = Array.from(box.querySelectorAll('.alloc-row'));
      const seen = new Set();
      const out = [];
      rows.forEach((row) => {
        const vt = (row.querySelector('select[name="alloc_vehicle_type"]')?.value || '').toString();
        if (!vt || seen.has(vt)) return;
        seen.add(vt);
        const au = (row.querySelector('input[name="alloc_authorized_units"]')?.value || '').toString();
        const fmin = (row.querySelector('input[name="alloc_fare_min"]')?.value || '').toString();
        const fmax = (row.querySelector('input[name="alloc_fare_max"]')?.value || '').toString();
        const st = (row.querySelector('select[name="alloc_status"]')?.value || 'Active').toString();
        out.push({
          vehicle_type: vt,
          authorized_units: au === '' ? null : Number(au),
          fare_min: fmin === '' ? null : Number(fmin),
          fare_max: fmax === '' ? null : Number(fmax),
          status: st
        });
      });
      return out;
    }

    function bindAllocRowHandlers(container) {
      Array.from(container.querySelectorAll('.btnRemoveAlloc')).forEach((b) => {
        b.addEventListener('click', () => {
          const row = b.closest('.alloc-row');
          if (row) row.remove();
        });
      });
    }

    async function saveCorridor(form) {
      const fd = new FormData(form);
      fd.append('allocations', JSON.stringify(collectAllocs()));
      const res = await fetch(rootUrl + '/admin/api/module1/save_route_corridor.php', { method: 'POST', body: fd });
      const data = await res.json().catch(() => null);
      if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'save_failed');
      return data;
    }

    function openView(r) {
      const allocs = Array.isArray(r.allocations) ? r.allocations : [];
      const allocHtml = allocs.length ? allocs.map((a) => {
        const vt = (a && a.vehicle_type) ? String(a.vehicle_type) : '-';
        const au = Number(a && a.authorized_units ? a.authorized_units : 0);
        const used = Number(a && a.used_units ? a.used_units : 0);
        const rem = Number(a && a.remaining_units ? a.remaining_units : (au > 0 ? Math.max(0, au - used) : 0));
        const fmin = (a && a.fare_min !== null && a.fare_min !== undefined && a.fare_min !== '') ? Number(a.fare_min) : null;
        const fmax = (a && a.fare_max !== null && a.fare_max !== undefined && a.fare_max !== '') ? Number(a.fare_max) : fmin;
        let fareText = '-';
        if (fmin !== null && !Number.isNaN(fmin)) {
          if (fmax !== null && !Number.isNaN(fmax) && Math.abs(fmin - fmax) >= 0.001) fareText = '₱ ' + fmin.toFixed(2) + ' – ' + fmax.toFixed(2);
          else fareText = '₱ ' + fmin.toFixed(2);
        }
        return `
          <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900/30 border border-slate-200 dark:border-slate-700">
            <div class="flex items-center justify-between gap-3">
              <div class="text-sm font-black text-slate-900 dark:text-white">${esc(vt)}</div>
              <div class="text-sm font-black text-slate-900 dark:text-white">${esc(fareText)}</div>
            </div>
            <div class="mt-1 flex flex-wrap gap-2 text-xs font-semibold text-slate-600 dark:text-slate-300">
              <span>Authorized: ${au}</span>
              <span>Used: ${used}</span>
              <span>Remaining: ${rem}</span>
              <span>Status: ${(a && a.status) ? esc(a.status) : 'Active'}</span>
            </div>
          </div>
        `;
      }).join('') : `<div class="text-sm text-slate-500 dark:text-slate-400 italic">No vehicle-type allocation yet.</div>`;

      const legacyNote = r && r.legacy ? `
        <div class="mb-4 p-4 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800">
          <div class="text-sm font-black text-amber-900 dark:text-amber-200">Legacy route group</div>
          <div class="mt-1 text-xs font-semibold text-amber-800 dark:text-amber-200/80">This was grouped from old route-per-vehicle-type records. Run migration to fully normalize.</div>
          <div class="mt-3">
            <a class="inline-flex items-center justify-center gap-2 rounded-md bg-slate-900 dark:bg-slate-700 px-4 py-2 text-sm font-semibold text-white" href="${rootUrl}/admin/tools/migrate_routes_to_realworld.php" target="_blank" rel="noopener">Open Migration Tool</a>
          </div>
        </div>
      ` : '';

      openModal(`
        ${legacyNote}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700">
            <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Route Code</div>
            <div class="mt-1 text-lg font-black text-slate-900 dark:text-white">${esc(r.route_code || '')}</div>
            <div class="mt-1 text-sm text-slate-600 dark:text-slate-300 font-semibold">${esc(r.route_name || '')}</div>
          </div>
          <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
            <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Route</div>
            <div class="mt-2 text-sm font-bold text-slate-900 dark:text-white">${esc((r.origin || '-'))} → ${esc((r.destination || '-'))}</div>
            <div class="mt-1 text-xs text-slate-600 dark:text-slate-300 font-semibold">${esc(r.structure || '-')}</div>
          </div>
        </div>
        <div class="mt-4 space-y-3">
          ${allocHtml}
        </div>
        <div class="mt-4 flex items-center justify-end gap-2">
          <button type="button" class="px-4 py-2.5 rounded-md bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 font-semibold" data-close="1">Close</button>
        </div>
      `, 'Route • ' + (r.route_code || ''));

      const c = body.querySelector('[data-close="1"]');
      if (c) c.addEventListener('click', closeModal);
    }

    function openEdit(r) {
      openModal(renderForm(r || { status: 'Active' }), (r && r.corridor_id) ? ('Edit Route • ' + (r.route_code || '')) : 'Add Route');
      const close = body.querySelector('[data-close="1"]');
      if (close) close.addEventListener('click', closeModal);
      const form = document.getElementById('formCorridorSave');
      const btnSave = document.getElementById('btnCorridorSave');
      const allocBox = document.getElementById('allocRows');
      const btnAddAlloc = document.getElementById('btnAddAlloc');
      const initialAllocs = Array.isArray(r && r.allocations) ? r.allocations : [];
      if (allocBox) {
        allocBox.innerHTML = '';
        if (initialAllocs.length) {
          initialAllocs.forEach((a) => { allocBox.insertAdjacentHTML('beforeend', allocRowHtml(a)); });
        } else {
          allocBox.insertAdjacentHTML('beforeend', allocRowHtml({ vehicle_type: 'Jeepney', status: 'Active' }));
        }
        bindAllocRowHandlers(allocBox);
      }
      if (btnAddAlloc && allocBox) {
        btnAddAlloc.addEventListener('click', () => {
          allocBox.insertAdjacentHTML('beforeend', allocRowHtml({ status: 'Active' }));
          bindAllocRowHandlers(allocBox);
        });
      }
      if (form && btnSave) {
        form.addEventListener('submit', async (e) => {
          e.preventDefault();
          if (!form.checkValidity()) { form.reportValidity(); return; }
          btnSave.disabled = true;
          btnSave.textContent = 'Saving...';
          try {
            await saveCorridor(form);
            showToast('Route saved.');
            const params = new URLSearchParams(window.location.search || '');
            params.set('page', 'puv-database/routes-lptrp');
            window.location.search = params.toString();
          } catch (err) {
            showToast((err && err.message) ? err.message : 'Failed', 'error');
            btnSave.disabled = false;
            btnSave.textContent = (r && r.corridor_id) ? 'Save Changes' : 'Create Route';
          }
        });
      }
    }

    if (canManage) {
      const btnAdd = document.getElementById('btnAddRoute');
      if (btnAdd) btnAdd.addEventListener('click', () => openEdit({ status: 'Active' }));
    }

    document.querySelectorAll('[data-route-view="1"]').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        if (e) { e.preventDefault(); e.stopPropagation(); }
        openView(parsePayload(btn));
      });
    });

    if (canManage) {
      document.querySelectorAll('[data-route-edit="1"]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
          if (e) { e.preventDefault(); e.stopPropagation(); }
          openEdit(parsePayload(btn));
        });
      });
    }
  })();
</script>
