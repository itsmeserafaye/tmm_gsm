<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('module5.manage_terminal');

require_once __DIR__ . '/../../includes/db.php';
$db = db();

$initialTab = 'terminals';

$statTerminals = (int)($db->query("SELECT COUNT(*) AS c FROM terminals WHERE type <> 'Parking'")->fetch_assoc()['c'] ?? 0);
$statAssignments = (int)($db->query("SELECT COUNT(*) AS c FROM terminal_assignments WHERE terminal_id IS NOT NULL")->fetch_assoc()['c'] ?? 0);
$statTerminalSlotsFree = (int)($db->query("SELECT COUNT(*) AS c FROM parking_slots ps JOIN terminals t ON t.id=ps.terminal_id WHERE ps.status='Free' AND t.type <> 'Parking'")->fetch_assoc()['c'] ?? 0);
$statTerminalSlotsOccupied = (int)($db->query("SELECT COUNT(*) AS c FROM parking_slots ps JOIN terminals t ON t.id=ps.terminal_id WHERE ps.status='Occupied' AND t.type <> 'Parking'")->fetch_assoc()['c'] ?? 0);
$statTerminalPaymentsToday = (int)($db->query("SELECT COUNT(*) AS c FROM parking_payments pp JOIN parking_slots ps ON ps.slot_id=pp.slot_id JOIN terminals t ON t.id=ps.terminal_id WHERE DATE(pp.paid_at)=CURDATE() AND t.type <> 'Parking'")->fetch_assoc()['c'] ?? 0);

$statParkingAreas = (int)($db->query("SELECT COUNT(*) AS c FROM terminals WHERE type='Parking'")->fetch_assoc()['c'] ?? 0);
$statParkingSlotsFree = (int)($db->query("SELECT COUNT(*) AS c FROM parking_slots ps JOIN terminals t ON t.id=ps.terminal_id WHERE ps.status='Free' AND t.type='Parking'")->fetch_assoc()['c'] ?? 0);
$statParkingSlotsOccupied = (int)($db->query("SELECT COUNT(*) AS c FROM parking_slots ps JOIN terminals t ON t.id=ps.terminal_id WHERE ps.status='Occupied' AND t.type='Parking'")->fetch_assoc()['c'] ?? 0);
$statParkingPaymentsToday = (int)($db->query("SELECT COUNT(*) AS c FROM parking_payments pp JOIN parking_slots ps ON ps.slot_id=pp.slot_id JOIN terminals t ON t.id=ps.terminal_id WHERE DATE(pp.paid_at)=CURDATE() AND t.type='Parking'")->fetch_assoc()['c'] ?? 0);

$hasRouteCode = false;
$colRes = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='routes' AND COLUMN_NAME IN ('route_code','vehicle_type')");
if ($colRes) {
  while ($c = $colRes->fetch_assoc()) {
    $cn = (string)($c['COLUMN_NAME'] ?? '');
    if ($cn === 'route_code') $hasRouteCode = true;
  }
}
$routeLabelExpr = $hasRouteCode ? "COALESCE(NULLIF(r.route_code,''), r.route_id)" : "r.route_id";

$allRoutes = [];
$hasRoutesTable = $db->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='routes' LIMIT 1");
if ($hasRoutesTable && $hasRoutesTable->fetch_row()) {
  $hasRouteStatus = (bool)($db->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='routes' AND COLUMN_NAME='status' LIMIT 1")?->fetch_row());
  $statusCond = $hasRouteStatus ? " WHERE COALESCE(status,'Active')='Active'" : "";
  $resRoutes = $db->query("SELECT route_id, route_code, route_name, vehicle_type, origin, destination FROM routes" . $statusCond . " ORDER BY COALESCE(NULLIF(route_name,''), COALESCE(NULLIF(route_code,''), route_id)) ASC LIMIT 2000");
  if ($resRoutes) {
    while ($r = $resRoutes->fetch_assoc()) {
      $rid = trim((string)($r['route_id'] ?? ''));
      $rcode = trim((string)($r['route_code'] ?? ''));
      $ref = $hasRouteCode ? ($rcode !== '' ? $rcode : $rid) : $rid;
      if ($ref === '') continue;
      $allRoutes[] = [
        'ref' => $ref,
        'route_id' => $rid,
        'route_code' => $rcode,
        'route_name' => (string)($r['route_name'] ?? ''),
        'vehicle_type' => (string)($r['vehicle_type'] ?? ''),
        'origin' => (string)($r['origin'] ?? ''),
        'destination' => (string)($r['destination'] ?? ''),
      ];
    }
  }
}

$taCols = [];
$taColRes = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='terminal_assignments'");
if ($taColRes) {
  while ($c = $taColRes->fetch_assoc()) {
    $taCols[(string)($c['COLUMN_NAME'] ?? '')] = true;
  }
}
$taTerminalIdCol = isset($taCols['terminal_id']) ? 'terminal_id' : '';
$taTerminalNameCol = isset($taCols['terminal_name']) ? 'terminal_name' : (isset($taCols['terminal']) ? 'terminal' : '');

$termCols = [];
$termColRes = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='terminals' AND COLUMN_NAME IN ('city','address','category','status')");
if ($termColRes) {
  while ($c = $termColRes->fetch_assoc()) {
    $termCols[(string)($c['COLUMN_NAME'] ?? '')] = true;
  }
}
$termHasCity = isset($termCols['city']);
$termHasAddress = isset($termCols['address']);
$termHasCategory = isset($termCols['category']);
$termHasStatus = isset($termCols['status']);

$ownerNameExpr = "NULL";
$faExists = (bool)($db->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='facility_agreements' LIMIT 1")?->fetch_row());
$foExists = (bool)($db->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='facility_owners' LIMIT 1")?->fetch_row());
if ($faExists && $foExists) {
  $faCols = [];
  $resCols = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='facility_agreements'");
  if ($resCols) while ($c = $resCols->fetch_assoc()) $faCols[(string)($c['COLUMN_NAME'] ?? '')] = true;
  $tidCol = isset($faCols['terminal_id']) ? 'terminal_id' : (isset($faCols['facility_id']) ? 'facility_id' : '');
  $statusCol = isset($faCols['status']) ? 'status' : '';
  $createdCol = isset($faCols['created_at']) ? 'created_at' : '';
  if ($tidCol !== '') {
    $order = $statusCol !== '' ? "FIELD(fa.$statusCol, 'Active', 'Expiring Soon', 'Expired', 'Terminated'), " : '';
    $order .= $createdCol !== '' ? "fa.$createdCol DESC" : "fa.id DESC";
    $ownerNameExpr = "(SELECT fo.name FROM facility_agreements fa JOIN facility_owners fo ON fa.owner_id = fo.id WHERE fa.$tidCol = t.id ORDER BY $order LIMIT 1)";
  }
}

$qFilter = trim((string)($_GET['q'] ?? ''));
$cityFilter = trim((string)($_GET['city'] ?? ''));
$catFilter = trim((string)($_GET['category'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));
$cities = [];
$categories = [];
$statuses = [];
if ($termHasCity) {
  $resCities = $db->query("SELECT DISTINCT TRIM(COALESCE(city,'')) AS city FROM terminals WHERE type <> 'Parking' AND COALESCE(city,'') <> '' ORDER BY city ASC LIMIT 200");
  if ($resCities) while ($r = $resCities->fetch_assoc()) { $c = trim((string)($r['city'] ?? '')); if ($c !== '') $cities[] = $c; }
}
if ($termHasCategory) {
  $resCats = $db->query("SELECT DISTINCT TRIM(COALESCE(category,'')) AS category FROM terminals WHERE type <> 'Parking' AND COALESCE(category,'') <> '' ORDER BY category ASC LIMIT 200");
  if ($resCats) while ($r = $resCats->fetch_assoc()) { $c = trim((string)($r['category'] ?? '')); if ($c !== '') $categories[] = $c; }
}
if ($termHasStatus) {
  $resStats = $db->query("SELECT DISTINCT TRIM(COALESCE(status,'')) AS status FROM terminals WHERE type <> 'Parking' AND COALESCE(status,'') <> '' ORDER BY status ASC LIMIT 200");
  if ($resStats) while ($r = $resStats->fetch_assoc()) { $s = trim((string)($r['status'] ?? '')); if ($s !== '') $statuses[] = $s; }
}

$assignCountByTerminalId = [];
$assignCountByTerminalName = [];
if ($taTerminalIdCol !== '') {
  $resA = $db->query("SELECT terminal_id, COUNT(*) AS c FROM terminal_assignments WHERE terminal_id IS NOT NULL GROUP BY terminal_id");
  if ($resA) while ($r = $resA->fetch_assoc()) $assignCountByTerminalId[(int)($r['terminal_id'] ?? 0)] = (int)($r['c'] ?? 0);
} elseif ($taTerminalNameCol !== '') {
  $resA = $db->query("SELECT $taTerminalNameCol AS terminal_name, COUNT(*) AS c FROM terminal_assignments WHERE COALESCE($taTerminalNameCol,'')<>'' GROUP BY $taTerminalNameCol");
  if ($resA) while ($r = $resA->fetch_assoc()) $assignCountByTerminalName[(string)($r['terminal_name'] ?? '')] = (int)($r['c'] ?? 0);
}

$terminalRows = [];
$sqlTerm = "SELECT
  t.id,
  t.name,
  " . ($termHasCategory ? "t.category" : "NULL") . " AS category,
  t.location,
  " . ($termHasCity ? "t.city" : "NULL") . " AS city,
  " . ($termHasAddress ? "t.address" : "NULL") . " AS address,
  t.capacity,
  $ownerNameExpr AS owner_name,
  COALESCE(GROUP_CONCAT(DISTINCT COALESCE(NULLIF(r.route_name,''), $routeLabelExpr) ORDER BY COALESCE(NULLIF(r.route_name,''), $routeLabelExpr) SEPARATOR ', '), '') AS routes_served,
  COUNT(DISTINCT tr.route_id) AS route_count
FROM terminals t
LEFT JOIN terminal_routes tr ON tr.terminal_id=t.id
LEFT JOIN routes r ON r.route_id=tr.route_id
WHERE t.type <> 'Parking'";

$params = [];
$types = '';

if ($qFilter !== '') {
  $sqlTerm .= " AND (t.name LIKE ? OR t.location LIKE ? OR COALESCE(t.category,'') LIKE ?)";
  $qv = '%' . $qFilter . '%';
  $params[] = $qv;
  $params[] = $qv;
  $params[] = $qv;
  $types .= 'sss';
}
if ($cityFilter !== '') {
  $sqlTerm .= " AND t.city = ?";
  $params[] = $cityFilter;
  $types .= 's';
}
if ($catFilter !== '') {
  $sqlTerm .= " AND t.category = ?";
  $params[] = $catFilter;
  $types .= 's';
}
if ($statusFilter !== '' && $termHasStatus) {
  $sqlTerm .= " AND COALESCE(t.status,'') = ?";
  $params[] = $statusFilter;
  $types .= 's';
}

$sqlTerm .= " GROUP BY t.id ORDER BY COALESCE(NULLIF(t.category,''), 'Unclassified') ASC, t.name ASC LIMIT 500";

if ($types !== '') {
  $stmt = $db->prepare($sqlTerm);
  if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
  } else {
    $res = false;
  }
} else {
  $res = $db->query($sqlTerm);
}

if ($res) while ($r = $res->fetch_assoc()) $terminalRows[] = $r;


$parkingRows = [];


$permCountByTerminal = [];
try {
  $chkDocs = $db->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='facility_documents' LIMIT 1");
  if ($chkDocs && $chkDocs->fetch_row()) {
    $cols = [];
    $colRes = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='facility_documents'");
    if ($colRes) while ($c = $colRes->fetch_assoc()) $cols[(string)($c['COLUMN_NAME'] ?? '')] = true;
    $tidCol = isset($cols['terminal_id']) ? 'terminal_id' : (isset($cols['facility_id']) ? 'facility_id' : '');
    $typeCol = isset($cols['doc_type']) ? 'doc_type' : (isset($cols['type']) ? 'type' : (isset($cols['document_type']) ? 'document_type' : ''));
    if ($tidCol !== '' && $typeCol !== '') {
      $resPerm = $db->query("SELECT $tidCol AS tid, COUNT(*) AS c FROM facility_documents WHERE LOWER(COALESCE($typeCol,'')) LIKE '%permit%' GROUP BY $tidCol");
      if ($resPerm) {
        while ($row = $resPerm->fetch_assoc()) {
          $tid = (int)($row['tid'] ?? 0);
          $c = (int)($row['c'] ?? 0);
          if ($tid > 0) $permCountByTerminal[$tid] = ($permCountByTerminal[$tid] ?? 0) + $c;
        }
      }
    }
  }

  $chkPerm = $db->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='terminal_permits' LIMIT 1");
  if ($chkPerm && $chkPerm->fetch_row()) {
    $cols = [];
    $colRes = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='terminal_permits'");
    if ($colRes) while ($c = $colRes->fetch_assoc()) $cols[(string)($c['COLUMN_NAME'] ?? '')] = true;
    $tidCol = isset($cols['terminal_id']) ? 'terminal_id' : (isset($cols['facility_id']) ? 'facility_id' : '');
    if ($tidCol !== '') {
      $resPerm = $db->query("SELECT $tidCol AS tid, COUNT(*) AS c FROM terminal_permits GROUP BY $tidCol");
      if ($resPerm) {
        while ($row = $resPerm->fetch_assoc()) {
          $tid = (int)($row['tid'] ?? 0);
          $c = (int)($row['c'] ?? 0);
          if ($tid > 0) $permCountByTerminal[$tid] = ($permCountByTerminal[$tid] ?? 0) + $c;
        }
      }
    }
  }
} catch (Throwable $e) {}

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';
?>

<div class="mx-auto max-w-7xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-6">
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between border-b border-slate-200 dark:border-slate-700 pb-6">
    <div>
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Terminal List</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Create terminals and view assignments, slots, and payments.</p>
    </div>
    <div class="flex items-center gap-3">
      <a href="?page=module5/submodule2" class="inline-flex items-center justify-center gap-2 rounded-md bg-blue-700 hover:bg-blue-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-all active:scale-[0.98]">
        <i data-lucide="link" class="w-4 h-4"></i>
        Assign Vehicle
      </a>
    </div>
  </div>

  <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
    <div class="px-6 pt-5 pb-0">
      <div class="flex items-center gap-6 border-b border-slate-200 dark:border-slate-700">
        <button type="button" id="tabBtnTerminals" role="tab" aria-selected="true" class="py-3 text-sm font-black uppercase tracking-widest border-b-2 border-blue-700 text-blue-700">
          Terminals
        </button>

      </div>
    </div>

    <div id="tabPanelTerminals" role="tabpanel" class="p-6 space-y-6">
      <div class="grid grid-cols-1 md:grid-cols-5 gap-6">
        <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
          <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Terminals</div>
          <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-white"><?php echo $statTerminals; ?></div>
        </div>
        <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
          <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Assignments</div>
          <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-white"><?php echo $statAssignments; ?></div>
        </div>
        <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
          <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Free Slots</div>
          <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-white"><?php echo $statTerminalSlotsFree; ?></div>
        </div>
        <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
          <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Occupied Slots</div>
          <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-white"><?php echo $statTerminalSlotsOccupied; ?></div>
        </div>
        <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
          <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Payments Today</div>
          <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-white"><?php echo $statTerminalPaymentsToday; ?></div>
        </div>
      </div>

      <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30 space-y-3">
          <?php
            $exportItems = [];
            $qs = http_build_query([
              'q' => $qFilter,
              'city' => $cityFilter,
              'category' => $catFilter,
              'status' => $statusFilter,
              'type' => 'Terminal'
            ]);
            if (has_permission('reports.export')) {
              $exportItems[] = [
                'href' => $rootUrl . '/admin/api/module5/export_terminals_csv.php',
                'label' => 'CSV',
                'icon' => 'download'
              ];
              $exportItems[] = [
                'href' => $rootUrl . '/admin/api/module5/export_terminals_csv.php?format=excel',
                'label' => 'Excel',
                'icon' => 'file-spreadsheet'
              ];
            }
            if (has_permission('reports.export') || has_permission('module5.manage_terminal')) {
              $exportItems[] = [
                'href' => $rootUrl . '/admin/api/module5/print_terminals.php?' . $qs,
                'label' => 'Print',
                'icon' => 'printer',
                'attrs' => [
                  'data-print-url' => $rootUrl . '/admin/api/module5/print_terminals.php?' . $qs,
                  'data-report-name' => 'Terminal List Report'
                ]
              ];
            }
            $exportItems[] = [
              'tag' => 'button',
              'label' => 'Import',
              'icon' => 'upload',
              'attrs' => ['id' => 'btnImportTerminals']
            ];
            if ($exportItems) tmm_render_export_toolbar($exportItems, ['mb' => 'mb-0']);
          ?>
          <form method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end mb-4">
            <input type="hidden" name="page" value="module5/submodule1">
            <div class="md:col-span-4">
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Search</label>
              <div class="relative">
                <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                <input name="q" value="<?php echo htmlspecialchars($qFilter); ?>" class="w-full pl-9 pr-4 py-2.5 rounded-md bg-white dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="Terminal name / location">
              </div>
            </div>
            <div class="md:col-span-2">
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">City</label>
              <select name="city" class="w-full px-4 py-2.5 rounded-md bg-white dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
                <option value="" <?php echo $cityFilter === '' ? 'selected' : ''; ?>>All Cities</option>
                <?php foreach ($cities as $c): ?>
                  <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $cityFilter === $c ? 'selected' : ''; ?>><?php echo htmlspecialchars($c); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="md:col-span-2">
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Category</label>
              <select name="category" class="w-full px-4 py-2.5 rounded-md bg-white dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
                <option value="" <?php echo $catFilter === '' ? 'selected' : ''; ?>>All Categories</option>
                <?php foreach ($categories as $c): ?>
                  <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $catFilter === $c ? 'selected' : ''; ?>><?php echo htmlspecialchars($c); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="md:col-span-2">
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Status</label>
              <select name="status" class="w-full px-4 py-2.5 rounded-md bg-white dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" <?php echo $termHasStatus ? '' : 'disabled'; ?>>
                <option value="" <?php echo $statusFilter === '' ? 'selected' : ''; ?>>All Status</option>
                <?php foreach ($statuses as $s): ?>
                  <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $statusFilter === $s ? 'selected' : ''; ?>><?php echo htmlspecialchars($s); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="md:col-span-2 flex items-center gap-2">
              <button class="flex-1 px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white text-sm font-semibold transition-colors shadow-sm">Apply</button>
              <a href="?page=module5/submodule1" class="px-4 py-2.5 rounded-md bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 text-sm font-semibold transition-colors hover:bg-slate-50 dark:hover:bg-slate-700" title="Reset Filters">
                <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
              </a>
            </div>
          </form>
          <div id="modalImportTerminals" class="fixed inset-0 z-[140] hidden items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/50" data-import-close="1"></div>
            <div class="relative w-full max-w-lg rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-xl p-6">
              <div class="text-lg font-black text-slate-900 dark:text-white">Import Terminals</div>
              <div class="mt-1 text-sm font-semibold text-slate-500 dark:text-slate-400">Upload a CSV file.</div>
              <div class="mt-4">
                <input id="fileImportTerminals" type="file" accept=".csv,text/csv" class="w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-blue-700 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-blue-800">
              </div>
              <div class="mt-5 flex items-center justify-end gap-2">
                <button type="button" id="btnCancelImportTerminals" class="px-4 py-2.5 rounded-md bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 font-semibold">Cancel</button>
                <button type="button" id="btnUploadImportTerminals" class="px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold">Upload</button>
              </div>
            </div>
          </div>
          <div class="flex items-center justify-between gap-3">
            <button type="button" id="btnOpenCreateTerminal" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white text-sm font-semibold shadow-sm transition-all active:scale-[0.98]">
              <i data-lucide="plus" class="w-4 h-4"></i>
              Create Terminal
            </button>
          </div>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-700 border-b border-slate-200 dark:border-slate-700">
              <tr class="text-left text-slate-500 dark:text-slate-400">
                <th class="py-4 px-6 font-black uppercase tracking-widest text-xs">Name</th>
                <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">Owner</th>
                <th class="py-4 px-4 font-black uppercase tracking-widest text-xs hidden md:table-cell">Location</th>
                <th class="py-4 px-4 font-black uppercase tracking-widest text-xs hidden lg:table-cell">Routes</th>
                <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">Assigned</th>
                <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">Capacity</th>
                <th class="py-4 px-4 font-black uppercase tracking-widest text-xs text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y-2 divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-800" id="termBodyTerminals">
              <?php if ($terminalRows): ?>
                <?php $currentCat = null; ?>
                <?php foreach ($terminalRows as $t): ?>
                  <?php
                    $cat = trim((string)($t['category'] ?? ''));
                    if ($cat === '') $cat = 'Unclassified';
                  ?>
                  <?php if ($currentCat !== $cat): ?>
                    <?php $currentCat = $cat; ?>
                    <tr data-group="1" class="bg-blue-100/90 dark:bg-blue-900/25 border-t-2 border-blue-300 dark:border-blue-700">
                      <td colspan="7" class="py-3 px-6 text-xs font-black uppercase tracking-widest text-blue-900 dark:text-blue-100">
                        <span class="inline-flex items-center gap-2">
                          <span class="w-2.5 h-2.5 rounded-full bg-blue-600 dark:bg-blue-400"></span>
                          <?php echo htmlspecialchars($currentCat); ?>
                        </span>
                      </td>
                    </tr>
                  <?php endif; ?>
                  <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                    <td class="py-4 px-6 font-black text-slate-900 dark:text-white">
                      <?php echo htmlspecialchars((string)($t['name'] ?? '')); ?>
                      <?php
                        $tidBadge = (int)($t['id'] ?? 0);
                        $pc = (int)($permCountByTerminal[$tidBadge] ?? 0);
                        $hasPermit = $pc > 0;
                      ?>
                      <span class="ml-2 inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-black <?php echo $hasPermit ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-300' : 'bg-rose-100 text-rose-800 dark:bg-rose-900/20 dark:text-rose-300'; ?>">
                        <?php echo $hasPermit ? 'Permit on file' : 'No permit'; ?>
                      </span>
                    </td>
                    <td class="py-4 px-4 text-slate-700 dark:text-slate-200 font-semibold">
                      <?php $owner = trim((string)($t['owner_name'] ?? '')); ?>
                      <?php if ($owner): ?>
                        <div class="flex items-center gap-2">
                          <span><?php echo htmlspecialchars($owner); ?></span>
                          <button type="button" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 transition-colors" data-terminal-info="<?php echo (int)($t['id'] ?? 0); ?>" title="View Details">
                            <i data-lucide="info" class="w-4 h-4"></i>
                          </button>
                          <button type="button" class="text-slate-600 hover:text-blue-700 dark:text-slate-300 dark:hover:text-blue-300 transition-colors" data-terminal-agreement="<?php echo (int)($t['id'] ?? 0); ?>" title="Edit Details">
                            <i data-lucide="pencil" class="w-4 h-4"></i>
                          </button>
                        </div>
                      <?php else: ?>
                        <span class="text-slate-400 italic text-xs">Unspecified</span>
                        <button type="button" class="ml-1 text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors" data-terminal-agreement="<?php echo (int)($t['id'] ?? 0); ?>" title="Add Details">
                          <i data-lucide="plus-circle" class="w-4 h-4 inline"></i>
                        </button>
                      <?php endif; ?>
                    </td>
                    <td class="py-4 px-4 hidden md:table-cell text-slate-600 dark:text-slate-300 font-semibold"><?php echo htmlspecialchars((string)($t['location'] ?? '')); ?></td>
                    <td class="py-4 px-4 hidden lg:table-cell text-xs text-slate-600 dark:text-slate-300 font-semibold">
                      <?php $rc = (int)($t['route_count'] ?? 0); ?>
                      <?php if ($rc > 0): ?>
                          <span class="inline-flex items-center justify-center px-2 py-1 rounded-md bg-slate-100 dark:bg-slate-700/50 text-slate-700 dark:text-slate-200 text-[11px] font-black"><?php echo $rc; ?></span>
                          <button type="button" data-terminal-routes="<?php echo (int)($t['id'] ?? 0); ?>"
                            class="inline-flex items-center justify-center p-2 rounded-md bg-slate-100 dark:bg-slate-700/50 text-slate-700 dark:text-slate-200 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors"
                            title="View routes">
                            <i data-lucide="list" class="w-4 h-4"></i>
                            <span class="sr-only">View routes</span>
                          </button>
                        </div>
                      <?php else: ?>
                        <div class="flex items-center gap-2">
                          <span class="text-[11px] font-bold text-slate-400">No routes mapped</span>
                          <button type="button" data-terminal-routes="<?php echo (int)($t['id'] ?? 0); ?>" class="text-[11px] font-black text-blue-700 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">Map</button>
                        </div>
                      <?php endif; ?>
                    </td>
                    <td class="py-4 px-4 text-slate-700 dark:text-slate-200 font-semibold">
                      <?php
                        $tid = (int)($t['id'] ?? 0);
                        $tname = (string)($t['name'] ?? '');
                        $cnt = $taTerminalIdCol !== '' ? (int)($assignCountByTerminalId[$tid] ?? 0) : (int)($assignCountByTerminalName[$tname] ?? 0);
                      ?>
                      <div class="flex items-center gap-2">
                        <span class="inline-flex items-center justify-center px-2 py-1 rounded-md bg-slate-100 dark:bg-slate-700/50 text-slate-700 dark:text-slate-200 text-xs font-black"><?php echo $cnt; ?></span>
                        <button type="button" data-terminal-vehicles="<?php echo $tid; ?>" class="text-xs font-black text-blue-700 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">View</button>
                      </div>
                    </td>
                    <td class="py-4 px-4 text-slate-700 dark:text-slate-200 font-semibold"><?php echo (int)($t['capacity'] ?? 0); ?></td>
                    <td class="py-4 px-4 text-right whitespace-nowrap">
                      <?php
                        $editPayload = [
                          'id' => (int)($t['id'] ?? 0),
                          'name' => (string)($t['name'] ?? ''),
                          'city' => (string)($t['city'] ?? ''),
                          'location' => (string)($t['location'] ?? ''),
                          'address' => (string)($t['address'] ?? ''),
                          'capacity' => (int)($t['capacity'] ?? 0),
                          'category' => (string)($t['category'] ?? ''),
                        ];
                      ?>
                      <div class="inline-flex items-center justify-end gap-1">
                        <button type="button" title="Edit" class="inline-flex items-center justify-center p-1.5 rounded-md bg-slate-100 dark:bg-slate-700/50 text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors" data-terminal-edit="1" data-terminal="<?php echo htmlspecialchars(json_encode($editPayload), ENT_QUOTES); ?>">
                          <i data-lucide="pencil" class="w-4 h-4"></i>
                          <span class="sr-only">Edit</span>
                        </button>
                        <a title="Slots" aria-label="Slots" href="?page=module5/submodule4&<?php echo http_build_query(['terminal_id'=>(int)($t['id'] ?? 0),'tab'=>'slots']); ?>" class="inline-flex items-center justify-center p-1.5 rounded-md bg-slate-100 dark:bg-slate-700/50 text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">
                          <i data-lucide="layout-grid" class="w-4 h-4"></i>
                          <span class="sr-only">Slots</span>
                        </a>
                        <a title="Payments" aria-label="Payments" href="?page=module5/submodule4&<?php echo http_build_query(['terminal_id'=>(int)($t['id'] ?? 0),'tab'=>'payments']); ?>" class="inline-flex items-center justify-center p-1.5 rounded-md bg-slate-100 dark:bg-slate-700/50 text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">
                          <i data-lucide="credit-card" class="w-4 h-4"></i>
                          <span class="sr-only">Payments</span>
                        </a>
                        <a title="Assign" aria-label="Assign" href="?page=module5/submodule2&terminal_id=<?php echo (int)($t['id'] ?? 0); ?>" class="inline-flex items-center justify-center p-1.5 rounded-md bg-slate-100 dark:bg-slate-700/50 text-slate-700 dark:text-slate-200 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors">
                          <i data-lucide="link" class="w-4 h-4"></i>
                          <span class="sr-only">Assign</span>
                        </a>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="7" class="py-12 text-center text-slate-500 font-medium italic">No terminals yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>


  </div>

  <div id="toast-container" class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 z-[100] flex flex-col gap-3 pointer-events-none"></div>

  <div id="terminalRoutesModal" class="fixed inset-0 z-[200] hidden">
    <div data-modal-backdrop class="absolute inset-0 bg-black/40"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
      <div class="w-full max-w-3xl rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-xl overflow-hidden flex flex-col max-h-[85vh]">
        <div class="p-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
          <div>
            <div class="text-sm font-black text-slate-900 dark:text-white">Routes & Fares</div>
            <div id="terminalRoutesModalSub" class="text-xs text-slate-500 dark:text-slate-400 font-semibold"></div>
          </div>
          <div class="flex flex-col sm:flex-row sm:items-center gap-2">
            <button type="button" id="btnEditTerminalRoutes" class="px-3 py-2 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-black hover:bg-slate-200 dark:hover:bg-slate-700">Edit</button>
            <button type="button" data-modal-close class="p-2 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-200">
              <i data-lucide="x" class="w-4 h-4"></i>
            </button>
          </div>
        </div>
        <div id="terminalRoutesView" class="p-4 overflow-x-auto overflow-y-auto flex-1">
          <table class="min-w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700">
              <tr class="text-left text-slate-500 dark:text-slate-400">
                <th class="py-3 px-3 font-black uppercase tracking-widest text-xs">Route</th>
                <th class="py-3 px-3 font-black uppercase tracking-widest text-xs">From</th>
                <th class="py-3 px-3 font-black uppercase tracking-widest text-xs">To</th>
                <th class="py-3 px-3 font-black uppercase tracking-widest text-xs text-right">Fare</th>
                <th class="py-3 px-3 font-black uppercase tracking-widest text-xs text-right">Manage</th>
              </tr>
            </thead>
            <tbody id="terminalRoutesModalBody" class="divide-y divide-slate-200 dark:divide-slate-700">
              <tr><td colspan="5" class="py-10 text-center text-slate-500 font-medium italic">Loading...</td></tr>
            </tbody>
          </table>
        </div>
        <div id="terminalRoutesEdit" class="hidden p-4 overflow-y-auto flex-1 space-y-3">
          <div class="flex flex-col sm:flex-row sm:items-center gap-2">
            <input id="terminalRoutesEditSearch" class="w-full sm:flex-1 px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="Search route...">
            <button type="button" id="btnTerminalRoutesSelectAll" class="w-full sm:w-auto px-3 py-2 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-black hover:bg-slate-200 dark:hover:bg-slate-700">All</button>
            <button type="button" id="btnTerminalRoutesClearAll" class="w-full sm:w-auto px-3 py-2 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-black hover:bg-slate-200 dark:hover:bg-slate-700">None</button>
          </div>
          <div id="terminalRoutesEditList" class="space-y-2"></div>
        </div>
        <div id="terminalRoutesEditFooter" class="hidden p-4 border-t border-slate-200 dark:border-slate-700 flex items-center justify-end gap-2">
          <button type="button" id="btnTerminalRoutesCancel" class="px-4 py-2.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-sm font-black hover:bg-slate-200 dark:hover:bg-slate-700">Cancel</button>
          <button type="button" id="btnTerminalRoutesSave" class="px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white text-sm font-black">Save</button>
        </div>
      </div>
    </div>
  </div>

  <div id="terminalVehiclesModal" class="fixed inset-0 z-[200] hidden">
    <div data-modal-backdrop class="absolute inset-0 bg-black/40"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
      <div class="w-full max-w-3xl rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-xl overflow-hidden flex flex-col max-h-[85vh]">
        <div class="p-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
          <div>
            <div class="text-sm font-black text-slate-900 dark:text-white">Assigned Vehicles</div>
            <div id="terminalVehiclesModalSub" class="text-xs text-slate-500 dark:text-slate-400 font-semibold"></div>
          </div>
          <button type="button" data-modal-close class="p-2 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-200">
            <i data-lucide="x" class="w-4 h-4"></i>
          </button>
        </div>
        <div class="p-4 overflow-x-auto overflow-y-auto flex-1">
          <table class="min-w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700">
              <tr class="text-left text-slate-500 dark:text-slate-400">
                <th class="py-3 px-3 font-black uppercase tracking-widest text-xs">Plate</th>
                <th class="py-3 px-3 font-black uppercase tracking-widest text-xs">Operator</th>
                <th class="py-3 px-3 font-black uppercase tracking-widest text-xs">Type</th>
                <th class="py-3 px-3 font-black uppercase tracking-widest text-xs text-right">Assigned</th>
              </tr>
            </thead>
            <tbody id="terminalVehiclesModalBody" class="divide-y divide-slate-200 dark:divide-slate-700">
              <tr><td colspan="4" class="py-10 text-center text-slate-500 font-medium italic">Loading...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<div id="terminalCreateModal" class="fixed inset-0 z-[200] hidden">
  <div data-modal-backdrop class="absolute inset-0 bg-black/40"></div>
  <div class="absolute inset-0 flex items-center justify-center p-4">
    <div class="w-full max-w-3xl rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-xl overflow-hidden">
      <div class="p-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between gap-3">
        <div id="terminalCreateModalTitle" class="text-sm font-black text-slate-900 dark:text-white">Create Terminal</div>
        <button type="button" data-modal-close class="p-2 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-200">
          <i data-lucide="x" class="w-4 h-4"></i>
        </button>
      </div>
      <div class="p-6">
        <form id="formTerminal" class="grid grid-cols-1 md:grid-cols-12 gap-4" novalidate enctype="multipart/form-data">
          <input type="hidden" name="type" value="Terminal">
          <input type="hidden" name="id" value="">
          <input type="hidden" name="agreement_id" value="">

          <!-- Tabs -->
          <div class="md:col-span-12 border-b border-slate-200 dark:border-slate-700 mb-2">
            <nav class="-mb-px flex gap-6" aria-label="Tabs">
              <button type="button" class="tab-btn border-b-2 border-blue-600 py-2 px-1 text-sm font-bold text-blue-600 dark:text-blue-400" data-target="tab-general">General</button>
              <button type="button" class="tab-btn border-b-2 border-transparent py-2 px-1 text-sm font-bold text-slate-500 dark:text-slate-400 hover:border-slate-300 hover:text-slate-700 dark:hover:text-slate-200" data-target="tab-owner">Owner & Agreement</button>
              <button type="button" class="tab-btn border-b-2 border-transparent py-2 px-1 text-sm font-bold text-slate-500 dark:text-slate-400 hover:border-slate-300 hover:text-slate-700 dark:hover:text-slate-200" data-target="tab-docs">Documents</button>
            </nav>
          </div>

          <!-- Tab: General -->
          <div id="tab-general" class="tab-pane md:col-span-12 grid grid-cols-1 md:grid-cols-12 gap-4">
            <div class="md:col-span-4">
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Name *</label>
              <input name="name" required minlength="3" maxlength="80" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., Victory Liner - Caloocan">
            </div>
            <div class="md:col-span-4">
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">City *</label>
              <input name="city" required maxlength="100" value="Caloocan City" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="Caloocan City">
            </div>
            <div class="md:col-span-4">
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Classification</label>
              <select name="category" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
                <option value="">Select</option>
                <?php foreach (['Provincial Bus Terminal','City Transport Hub','District Transport Terminal','Barangay Transport Terminal'] as $c): ?>
                  <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="md:col-span-8">
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Location *</label>
              <input name="location" required maxlength="120" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., Monumento">
            </div>
            <div class="md:col-span-4">
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Capacity</label>
              <input name="capacity" type="number" min="0" max="5000" step="1" value="0" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
            </div>
            <div class="md:col-span-12">
              <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Address</label>
              <input name="address" maxlength="180" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="Complete Address (Optional)">
            </div>
          </div>

          <!-- Tab: Owner & Agreement -->
          <div id="tab-owner" class="tab-pane hidden md:col-span-12 grid grid-cols-1 md:grid-cols-12 gap-4">
             <div class="md:col-span-12 border-b border-slate-100 dark:border-slate-800 pb-2 mb-2">
               <h3 class="text-sm font-black text-slate-800 dark:text-slate-200">Owner Information</h3>
             </div>
             <div class="md:col-span-6">
               <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Owner Name *</label>
               <input name="owner_name" maxlength="255" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="Person / Company / Coop Name">
             </div>
             <div class="md:col-span-3">
               <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Type</label>
               <select name="owner_type" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
                 <option value="Person">Person</option>
                 <option value="Cooperative">Cooperative</option>
                 <option value="Company">Company</option>
                 <option value="Government">Government</option>
                 <option value="Other" selected>Other</option>
               </select>
             </div>
             <div class="md:col-span-3">
               <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Contact Info</label>
               <input name="owner_contact" maxlength="255" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="Phone / Email">
             </div>

             <div class="md:col-span-12 border-b border-slate-100 dark:border-slate-800 pb-2 mb-2 mt-2">
               <h3 class="text-sm font-black text-slate-800 dark:text-slate-200">Agreement / Contract</h3>
             </div>
             <div class="md:col-span-4">
               <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Agreement Type</label>
               <select name="agreement_type" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
                 <option value="MOA">MOA</option>
                 <option value="Lease Contract">Lease Contract</option>
                 <option value="Rental Agreement">Rental Agreement</option>
                 <option value="Other">Other</option>
               </select>
             </div>
             <div class="md:col-span-4">
               <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Reference No.</label>
               <input name="agreement_reference_no" maxlength="100" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="Optional">
             </div>
             <div class="md:col-span-4">
               <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Status</label>
               <select name="agreement_status" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
                 <option value="Active">Active</option>
                 <option value="Expiring Soon">Expiring Soon</option>
                 <option value="Expired">Expired</option>
                 <option value="Terminated">Terminated</option>
               </select>
             </div>
             <div class="md:col-span-3">
               <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Start Date</label>
               <input name="start_date" type="date" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
             </div>
             <div class="md:col-span-3">
               <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">End Date</label>
               <input name="end_date" type="date" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
             </div>
             <div class="md:col-span-3">
               <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Rent Amount</label>
               <input name="rent_amount" type="number" step="0.01" min="0" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="0.00">
             </div>
             <div class="md:col-span-3">
               <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Frequency</label>
               <select name="rent_frequency" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
                 <option value="Monthly">Monthly</option>
                 <option value="Weekly">Weekly</option>
                 <option value="Annual">Annual</option>
                 <option value="One-time">One-time</option>
               </select>
             </div>
             <div class="md:col-span-12">
               <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Terms Summary</label>
               <textarea name="terms_summary" rows="2" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="Short notes about the agreement..."></textarea>
             </div>
          </div>

          <!-- Tab: Documents -->
          <div id="tab-docs" class="tab-pane hidden md:col-span-12 space-y-4">
             <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
               <div>
                 <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">MOA File (PDF/Image)</label>
                 <input name="moa_file" type="file" accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-blue-700 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-blue-800">
               </div>
               <div>
                 <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Contract File</label>
                 <input name="contract_file" type="file" accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-blue-700 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-blue-800">
               </div>
               <div>
                 <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Terminal Permit / Clearance</label>
                 <input name="permit_file" type="file" accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-blue-700 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-blue-800">
               </div>
               <div>
                 <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Other Attachments (Multiple)</label>
                 <input name="other_attachments[]" type="file" multiple accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-blue-700 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-blue-800">
               </div>
             </div>
             <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-md text-xs text-blue-800 dark:text-blue-300">
               <strong>Note:</strong> Uploading new files will add to the existing documents list.
             </div>
          </div>

          <div class="md:col-span-12 flex items-center justify-end gap-2 pt-4 border-t border-slate-200 dark:border-slate-700">
            <button type="button" id="btnCancelCreateTerminal" class="px-4 py-2.5 rounded-md bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 font-semibold">Cancel</button>
            <button id="btnSaveTerminal" class="px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold">Save</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<div id="terminalInfoModal" class="fixed inset-0 z-[200] hidden">
  <div data-modal-backdrop class="absolute inset-0 bg-black/40"></div>
  <div class="absolute inset-0 flex items-center justify-center p-4">
    <div class="w-full max-w-2xl rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-xl overflow-hidden flex flex-col max-h-[90vh]">
      <div class="p-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
        <div class="text-sm font-black text-slate-900 dark:text-white">Terminal Details</div>
        <button type="button" data-modal-close class="p-2 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-200">
          <i data-lucide="x" class="w-4 h-4"></i>
        </button>
      </div>
      <div id="terminalInfoContent" class="p-6 overflow-y-auto space-y-6">
        <div class="text-center text-slate-500 italic py-10">Loading...</div>
      </div>
    </div>
  </div>
</div>

<div id="terminalAgreementModal" class="fixed inset-0 z-[210] hidden">
  <div data-modal-backdrop class="absolute inset-0 bg-black/40"></div>
  <div class="absolute inset-0 flex items-center justify-center p-4">
    <div class="w-full max-w-4xl rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-xl overflow-hidden flex flex-col max-h-[90vh]">
      <div class="p-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between gap-3">
        <div>
          <div class="text-sm font-black text-slate-900 dark:text-white">Terminal Agreement</div>
          <div id="terminalAgreementSub" class="text-xs text-slate-500 dark:text-slate-400 font-semibold"></div>
        </div>
        <button type="button" data-modal-close class="p-2 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-200">
          <i data-lucide="x" class="w-4 h-4"></i>
        </button>
      </div>
      <div class="p-6 overflow-y-auto">
        <form id="formTerminalAgreement" class="grid grid-cols-1 md:grid-cols-12 gap-4" novalidate enctype="multipart/form-data">
          <input type="hidden" name="terminal_id" value="">
          <input type="hidden" name="agreement_id" value="">
          <div class="md:col-span-6">
            <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-2">Owner Information</div>
            <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
              <div class="md:col-span-12">
                <label class="block text-[11px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Owner Name *</label>
                <input name="owner_name" required maxlength="255" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
              </div>
              <div class="md:col-span-6">
                <label class="block text-[11px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Type</label>
                <select name="owner_type" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
                  <option value="Person">Person</option>
                  <option value="Cooperative">Cooperative</option>
                  <option value="Company">Company</option>
                  <option value="Government">Government</option>
                  <option value="Other">Other</option>
                </select>
              </div>
              <div class="md:col-span-6">
                <label class="block text-[11px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Contact</label>
                <input name="owner_contact" maxlength="255" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
              </div>
            </div>
          </div>
          <div class="md:col-span-6">
            <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-2">Agreement</div>
            <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
              <div class="md:col-span-6">
                <label class="block text-[11px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Type</label>
                <select name="agreement_type" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
                  <option value="MOA">MOA</option>
                  <option value="Lease Contract">Lease Contract</option>
                  <option value="Rental Agreement">Rental Agreement</option>
                  <option value="Other">Other</option>
                </select>
              </div>
              <div class="md:col-span-6">
                <label class="block text-[11px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Ref No.</label>
                <input name="agreement_reference_no" maxlength="100" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
              </div>
              <div class="md:col-span-4">
                <label class="block text-[11px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Rent Amount</label>
                <input name="rent_amount" type="number" step="0.01" min="0" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
              </div>
              <div class="md:col-span-4">
                <label class="block text-[11px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Frequency</label>
                <select name="rent_frequency" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
                  <option value="Monthly">Monthly</option>
                  <option value="Weekly">Weekly</option>
                  <option value="Annual">Annual</option>
                  <option value="One-time">One-time</option>
                </select>
              </div>
              <div class="md:col-span-4">
                <label class="block text-[11px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Status</label>
                <select name="agreement_status" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
                  <option value="Active">Active</option>
                  <option value="Expiring Soon">Expiring Soon</option>
                  <option value="Expired">Expired</option>
                  <option value="Terminated">Terminated</option>
                </select>
              </div>
              <div class="md:col-span-6">
                <label class="block text-[11px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Start Date *</label>
                <input name="start_date" type="date" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
              </div>
              <div class="md:col-span-6">
                <label class="block text-[11px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">End Date *</label>
                <input name="end_date" type="date" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
              </div>
            </div>
          </div>
          <div class="md:col-span-12">
            <label class="block text-[11px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Terms Summary</label>
            <textarea name="terms_summary" rows="3" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold"></textarea>
          </div>
          <div class="md:col-span-12 grid grid-cols-1 md:grid-cols-12 gap-4">
            <div class="md:col-span-4">
              <label class="block text-[11px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">MOA</label>
              <input name="moa_file" type="file" accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-blue-700 file:px-3 file:py-2 file:text-xs file:font-black file:text-white hover:file:bg-blue-800">
            </div>
            <div class="md:col-span-4">
              <label class="block text-[11px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Contract</label>
              <input name="contract_file" type="file" accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-blue-700 file:px-3 file:py-2 file:text-xs file:font-black file:text-white hover:file:bg-blue-800">
            </div>
            <div class="md:col-span-4">
              <label class="block text-[11px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Permit</label>
              <input name="permit_file" type="file" accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-blue-700 file:px-3 file:py-2 file:text-xs file:font-black file:text-white hover:file:bg-blue-800">
            </div>
            <div class="md:col-span-12">
              <label class="block text-[11px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Other Attachments</label>
              <input name="other_attachments[]" type="file" multiple accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-blue-700 file:px-3 file:py-2 file:text-xs file:font-black file:text-white hover:file:bg-blue-800">
            </div>
          </div>
          <div class="md:col-span-12 flex items-center justify-end gap-2 pt-4 border-t border-slate-200 dark:border-slate-700">
            <button type="button" data-modal-cancel class="px-4 py-2.5 rounded-md bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 font-semibold">Cancel</button>
            <button id="btnSaveTerminalAgreement" class="px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold">Save Details</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
  (function(){
    const rootUrl = <?php echo json_encode($rootUrl); ?>;
    const initialTab = <?php echo json_encode($initialTab); ?>;
    const allRoutes = <?php echo json_encode($allRoutes); ?>;

    const tabBtnTerminals = document.getElementById('tabBtnTerminals');
    const panelTerminals = document.getElementById('tabPanelTerminals');

    const terminalCreateModal = document.getElementById('terminalCreateModal');
    const btnOpenCreateTerminal = document.getElementById('btnOpenCreateTerminal');
    const btnCancelCreateTerminal = document.getElementById('btnCancelCreateTerminal');

    const formTerminal = document.getElementById('formTerminal');
    const btnSaveTerminal = document.getElementById('btnSaveTerminal');

    // const searchTerm = document.getElementById('terminalSearchTerm');
    const tbodyTerm = document.getElementById('termBodyTerminals');

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

    const btnImportTerminals = document.getElementById('btnImportTerminals');
    const modalImportTerminals = document.getElementById('modalImportTerminals');
    const fileImportTerminals = document.getElementById('fileImportTerminals');
    const btnCancelImportTerminals = document.getElementById('btnCancelImportTerminals');
    const btnUploadImportTerminals = document.getElementById('btnUploadImportTerminals');
    if (btnImportTerminals && modalImportTerminals && fileImportTerminals && btnCancelImportTerminals && btnUploadImportTerminals) {
      const closeImport = () => modalImportTerminals.classList.add('hidden');
      const openImport = () => {
        fileImportTerminals.value = '';
        btnUploadImportTerminals.disabled = false;
        modalImportTerminals.classList.remove('hidden');
      };
      btnImportTerminals.addEventListener('click', openImport);
      btnCancelImportTerminals.addEventListener('click', closeImport);
      modalImportTerminals.querySelectorAll('[data-import-close="1"]').forEach((el) => el.addEventListener('click', closeImport));
      btnUploadImportTerminals.addEventListener('click', async () => {
        const f = fileImportTerminals.files && fileImportTerminals.files[0] ? fileImportTerminals.files[0] : null;
        if (!f) { showToast('Please choose a CSV file.', 'error'); return; }
        const fd = new FormData();
        fd.append('file', f);
        btnUploadImportTerminals.disabled = true;
        try {
          const res = await fetch(rootUrl + '/admin/api/module5/import_terminals.php', { method: 'POST', body: fd });
          const data = await res.json();
          if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'import_failed');
          showToast(`Import complete: ${data.inserted || 0} inserted, ${data.updated || 0} updated, ${data.skipped || 0} skipped.`);
          closeImport();
          setTimeout(() => { window.location.reload(); }, 600);
        } catch (e) {
          showToast(e.message || 'Import failed', 'error');
          btnUploadImportTerminals.disabled = false;
        }
      });
    }

    function openCreateTerminalModal() {
      if (!terminalCreateModal) return;
      const title = document.getElementById('terminalCreateModalTitle');
      if (title) title.textContent = 'Create Terminal';
      terminalCreateModal.classList.remove('hidden');
      if (window.lucide) window.lucide.createIcons();
      try { if (formTerminal) formTerminal.reset(); } catch (e) {}
      if (formTerminal) {
        const idEl = formTerminal.querySelector('input[name="id"]');
        if (idEl) idEl.value = '';
      }
    }
    function closeCreateTerminalModal() {
      if (!terminalCreateModal) return;
      terminalCreateModal.classList.add('hidden');
    }
    if (btnOpenCreateTerminal) btnOpenCreateTerminal.addEventListener('click', openCreateTerminalModal);
    if (btnCancelCreateTerminal) btnCancelCreateTerminal.addEventListener('click', closeCreateTerminalModal);
    if (terminalCreateModal) {
      const closeBtn = terminalCreateModal.querySelector('[data-modal-close]');
      const backdrop = terminalCreateModal.querySelector('[data-modal-backdrop]');
      if (closeBtn) closeBtn.addEventListener('click', closeCreateTerminalModal);
      if (backdrop) backdrop.addEventListener('click', closeCreateTerminalModal);
    }

    function parseTerminalPayload(el) {
      try { return JSON.parse(el.getAttribute('data-terminal') || '{}'); } catch (e) { return {}; }
    }

    function openEditTerminalModal(t) {
      if (!terminalCreateModal || !formTerminal) return;
      const title = document.getElementById('terminalCreateModalTitle');
      if (title) title.textContent = 'Edit Terminal';
      terminalCreateModal.classList.remove('hidden');
      if (window.lucide) window.lucide.createIcons();

      const set = (name, value) => {
        const el = formTerminal.querySelector(`[name="${name}"]`);
        if (!el) return;
        el.value = (value === null || value === undefined) ? '' : String(value);
      };
      set('id', t.id || '');
      set('name', t.name || '');
      set('city', t.city || 'Caloocan City');
      set('category', t.category || '');
      set('location', t.location || '');
      set('address', t.address || '');
      set('capacity', (t.capacity !== null && t.capacity !== undefined) ? t.capacity : 0);
      
      // Reset other tabs
      set('agreement_id', '');
      set('owner_name', '');
      set('owner_type', 'Other');
      set('owner_contact', '');
      set('agreement_type', 'MOA');
      set('agreement_reference_no', '');
      set('agreement_status', 'Active');
      set('start_date', '');
      set('end_date', '');
      set('rent_amount', '');
      set('rent_frequency', 'Monthly');
      set('terms_summary', '');
      
      // Reset tab view to General
      const firstTab = document.querySelector('.tab-btn[data-target="tab-general"]');
      if (firstTab) firstTab.click();

      // Fetch enhanced details
      if (t.id) {
        fetch(rootUrl + '/admin/api/module5/get_terminal_details.php?id=' + t.id)
          .then(res => res.json())
          .then(data => {
            if (data && data.success) {
               const a = data.agreement || {};
               set('owner_name', a.owner_name);
               set('owner_type', a.owner_type || 'Other');
               set('owner_contact', a.owner_contact);
               if (a.id) {
                 set('agreement_id', a.id);
                 set('agreement_type', a.agreement_type);
                 set('agreement_reference_no', a.reference_no);
                 set('agreement_status', a.status);
                 set('start_date', a.start_date);
                 set('end_date', a.end_date);
                 set('rent_amount', a.rent_amount);
                 set('rent_frequency', a.rent_frequency);
                 set('terms_summary', a.terms_summary);
               }
            }
          })
          .catch(err => console.error(err));
      }
    }

    Array.from(document.querySelectorAll('[data-terminal-edit="1"]')).forEach((btn) => {
      btn.addEventListener('click', () => openEditTerminalModal(parseTerminalPayload(btn)));
    });

    function setActiveTab(tab) {
      const isTerm = tab === 'terminals';
      if (panelTerminals) panelTerminals.classList.toggle('hidden', !isTerm);
      if (tabBtnTerminals) {
        tabBtnTerminals.setAttribute('aria-selected', isTerm ? 'true' : 'false');
        tabBtnTerminals.className = isTerm
          ? 'py-3 text-sm font-black uppercase tracking-widest border-b-2 border-blue-700 text-blue-700'
          : 'py-3 text-sm font-black uppercase tracking-widest border-b-2 border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-200';
      }
      try { localStorage.setItem('module5_list_tab', tab); } catch (e) {}
    }

    if (tabBtnTerminals) tabBtnTerminals.addEventListener('click', () => setActiveTab('terminals'));

    let saved = '';
    try { saved = localStorage.getItem('module5_list_tab') || ''; } catch (e) {}
    setActiveTab('terminals');

    async function saveTerminal(formEl, btnEl) {
      if (!formEl || !btnEl) return;
      btnEl.disabled = true;
      btnEl.textContent = 'Saving...';
      try {
        const res = await fetch(rootUrl + '/admin/api/module5/save_terminal.php', { method: 'POST', body: new FormData(formEl) });
        const data = await res.json();
        if (!data || !data.ok) throw new Error((data && data.message) ? data.message : 'save_failed');
        showToast('Saved.');
        closeCreateTerminalModal();
        setTimeout(() => { window.location.reload(); }, 400);
      } catch (err) {
        showToast(err.message || 'Failed', 'error');
        btnEl.disabled = false;
        btnEl.textContent = 'Save';
      }
    }

    if (formTerminal && btnSaveTerminal) {
      formTerminal.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!formTerminal.checkValidity()) { formTerminal.reportValidity(); return; }
        await saveTerminal(formTerminal, btnSaveTerminal);
      });
    }

    function filterRows(searchEl, tbodyEl) {
      if (!searchEl || !tbodyEl) return;
      const q = (searchEl.value || '').trim().toLowerCase();
      const rows = Array.from(tbodyEl.querySelectorAll('tr'));
      rows.forEach(function (tr) {
        if (tr.getAttribute('data-group') === '1') return;
        const tds = tr.querySelectorAll('td');
        if (!tds || tds.length < 2) return;
        const name = (tds[0].textContent || '').toLowerCase();
        const loc = (tds[1].textContent || '').toLowerCase();
        const ok = q === '' || name.includes(q) || loc.includes(q);
        tr.style.display = ok ? '' : 'none';
      });

      let activeHeader = null;
      let hasVisible = false;
      rows.forEach((tr) => {
        if (tr.getAttribute('data-group') === '1') {
          if (activeHeader) activeHeader.style.display = hasVisible ? '' : 'none';
          activeHeader = tr;
          hasVisible = false;
          return;
        }
        if (tr.style.display !== 'none') hasVisible = true;
      });
      if (activeHeader) activeHeader.style.display = hasVisible ? '' : 'none';
    }
    // if (searchTerm) searchTerm.addEventListener('input', () => filterRows(searchTerm, tbodyTerm));

    const modal = document.getElementById('terminalRoutesModal');
    const modalBody = document.getElementById('terminalRoutesModalBody');
    const modalSub = document.getElementById('terminalRoutesModalSub');
    const routesView = document.getElementById('terminalRoutesView');
    const routesEdit = document.getElementById('terminalRoutesEdit');
    const routesEditFooter = document.getElementById('terminalRoutesEditFooter');
    const btnEditTerminalRoutes = document.getElementById('btnEditTerminalRoutes');
    const routesEditSearch = document.getElementById('terminalRoutesEditSearch');
    const routesEditList = document.getElementById('terminalRoutesEditList');
    const btnRoutesSelectAll = document.getElementById('btnTerminalRoutesSelectAll');
    const btnRoutesClearAll = document.getElementById('btnTerminalRoutesClearAll');
    const btnRoutesCancel = document.getElementById('btnTerminalRoutesCancel');
    const btnRoutesSave = document.getElementById('btnTerminalRoutesSave');

    let currentTerminalId = 0;
    let currentTerminalName = '';
    let selectedRouteRefs = new Set();
    let lastFilter = '';
    function openModal() { if (modal) modal.classList.remove('hidden'); }
    function closeModal() { if (modal) modal.classList.add('hidden'); }
    if (modal) {
      const closeBtn = modal.querySelector('[data-modal-close]');
      const backdrop = modal.querySelector('[data-modal-backdrop]');
      if (closeBtn) closeBtn.addEventListener('click', closeModal);
      if (backdrop) backdrop.addEventListener('click', closeModal);
    }

    function setRoutesMode(mode) {
      const isEdit = mode === 'edit';
      if (routesView) routesView.classList.toggle('hidden', isEdit);
      if (routesEdit) routesEdit.classList.toggle('hidden', !isEdit);
      if (routesEditFooter) routesEditFooter.classList.toggle('hidden', !isEdit);
      if (btnEditTerminalRoutes) btnEditTerminalRoutes.textContent = isEdit ? 'Back' : 'Edit';
    }

    function getRouteLabel(r) {
      const name = (r && r.route_name) ? String(r.route_name) : '';
      const ref = (r && r.ref) ? String(r.ref) : '';
      return name ? (ref ? (name + ' (' + ref + ')') : name) : (ref || '-');
    }

    function computeFilteredRoutes(filterText) {
      const q = (filterText || '').trim().toLowerCase();
      return (Array.isArray(allRoutes) ? allRoutes : []).filter((r) => {
        const ref = (r && r.ref) ? String(r.ref) : '';
        const name = (r && r.route_name) ? String(r.route_name) : '';
        const origin = (r && r.origin) ? String(r.origin) : '';
        const dest = (r && r.destination) ? String(r.destination) : '';
        const vt = (r && r.vehicle_type) ? String(r.vehicle_type) : '';
        const hay = (ref + ' ' + name + ' ' + origin + ' ' + dest + ' ' + vt).toLowerCase();
        return q === '' || hay.includes(q);
      });
    }

    function renderRoutesEdit(filterText) {
      if (!routesEditList) return;
      lastFilter = (filterText || '').toString();
      const items = computeFilteredRoutes(lastFilter);
      if (!items.length) {
        routesEditList.innerHTML = '<div class="py-8 text-center text-slate-500 font-medium italic">No routes found.</div>';
        return;
      }
      routesEditList.innerHTML = items.map((r) => {
        const ref = (r && r.ref) ? String(r.ref) : '';
        const checked = ref && selectedRouteRefs.has(ref) ? 'checked' : '';
        const label = getRouteLabel(r);
        const sub = [r.origin, r.destination].filter(Boolean).join(' → ');
        const vt = (r.vehicle_type || '').toString();
        const subText = [sub, vt ? ('Type: ' + vt) : ''].filter(Boolean).join(' • ');
        const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        return `
          <label class="flex items-start gap-3 p-3 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900/40 hover:border-blue-300 dark:hover:border-blue-700 transition-colors">
            <input type="checkbox" class="mt-1 w-4 h-4" data-route-ref="${esc(ref)}" ${checked}>
            <div class="min-w-0">
              <div class="font-black text-slate-900 dark:text-white truncate">${esc(label)}</div>
              <div class="text-xs text-slate-500 dark:text-slate-400 font-semibold truncate">${esc(subText || '')}</div>
            </div>
          </label>
        `;
      }).join('');
      routesEditList.querySelectorAll('input[data-route-ref]').forEach((el) => {
        el.addEventListener('change', () => {
          const ref = (el.getAttribute('data-route-ref') || '').toString();
          if (!ref) return;
          if (el.checked) selectedRouteRefs.add(ref);
          else selectedRouteRefs.delete(ref);
        });
      });
    }

    async function showTerminalRoutes(terminalId) {
      if (!modalBody) return;
      currentTerminalId = Number(terminalId || 0);
      currentTerminalName = '';
      selectedRouteRefs = new Set();
      setRoutesMode('view');
      modalBody.innerHTML = '<tr><td colspan="5" class="py-10 text-center text-slate-500 font-medium italic">Loading...</td></tr>';
      if (modalSub) modalSub.textContent = 'Terminal ID: ' + String(terminalId);
      openModal();
      try {
        const res = await fetch(rootUrl + '/admin/api/module5/terminal_routes.php?terminal_id=' + encodeURIComponent(String(terminalId)));
        const data = await res.json();
        if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'load_failed');
        const rows = Array.isArray(data.data) ? data.data : [];
        if (rows[0] && rows[0].terminal_name) currentTerminalName = String(rows[0].terminal_name);
        rows.forEach((r) => {
          const ref = (r && r.route_ref) ? String(r.route_ref) : '';
          if (ref) selectedRouteRefs.add(ref);
        });
        if (modalSub) modalSub.textContent = (currentTerminalName ? currentTerminalName : 'Routes') + ' • ' + rows.length + ' route(s)';
        if (!rows.length) {
          modalBody.innerHTML = '<tr><td colspan="5" class="py-10 text-center text-slate-500 font-medium italic">No routes mapped.</td></tr>';
        } else {
          modalBody.innerHTML = rows.map(r => {
          const code = (r.route_code || r.route_ref || '-').toString();
          const name = (r.route_name || '').toString();
          const vt = (r.vehicle_type || '').toString();
          const routeLabel = `
            <div class="font-black text-slate-900 dark:text-white">${code}</div>
            <div class="text-xs text-slate-500 dark:text-slate-400 font-semibold">${[name && name !== code ? name : '', vt ? ('Type: ' + vt) : ''].filter(Boolean).join(' • ')}</div>
          `;
          const origin = (r.origin || '-').toString();
          const dest = (r.destination || '-').toString();
          const min = (r.fare_min === null || r.fare_min === undefined || r.fare_min === '') ? null : Number(r.fare_min);
          const max = (r.fare_max === null || r.fare_max === undefined || r.fare_max === '') ? null : Number(r.fare_max);
          let fare = '-';
          if (min !== null && !Number.isNaN(min)) {
            const maxv = (max !== null && !Number.isNaN(max)) ? max : min;
            fare = Math.abs(min - maxv) < 0.001 ? ('₱' + min.toFixed(2)) : ('₱' + min.toFixed(2) + ' – ' + maxv.toFixed(2));
          } else if (r.fare !== null && r.fare !== undefined && String(r.fare).trim() !== '') {
            const fv = String(r.fare).trim();
            const n = Number(fv);
            fare = Number.isNaN(n) ? ('₱' + fv) : ('₱' + n.toFixed(2));
          }
          const manage = r.route_db_id
            ? `<a target="_blank" rel="noopener" title="Open Route Assignment"
                 href="?page=module2/submodule5&route_id=${Number(r.route_db_id)}"
                 class="inline-flex items-center justify-center p-1.5 rounded-md bg-slate-100 dark:bg-slate-700/50 text-slate-700 dark:text-slate-200 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors">
                 <i data-lucide="settings" class="w-4 h-4"></i>
               </a>`
            : '-';
          return `
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
              <td class="py-3 px-3">${routeLabel}</td>
              <td class="py-3 px-3 text-slate-600 dark:text-slate-300">${origin}</td>
              <td class="py-3 px-3 text-slate-600 dark:text-slate-300">${dest}</td>
              <td class="py-3 px-3 text-right font-bold text-slate-900 dark:text-white">${fare}</td>
              <td class="py-3 px-3 text-right">${manage}</td>
            </tr>
          `;
          }).join('');
        }
        if (window.lucide) window.lucide.createIcons();
      } catch (e) {
        modalBody.innerHTML = '<tr><td colspan="5" class="py-10 text-center text-rose-600 font-semibold">Failed to load routes.</td></tr>';
      }
    }

    Array.from(document.querySelectorAll('[data-terminal-routes]')).forEach((btn) => {
      btn.addEventListener('click', () => {
        const id = Number(btn.getAttribute('data-terminal-routes') || 0);
        if (id > 0) showTerminalRoutes(id);
      });
    });

    if (btnEditTerminalRoutes) {
      btnEditTerminalRoutes.addEventListener('click', () => {
        const isEdit = routesEdit && !routesEdit.classList.contains('hidden');
        if (isEdit) setRoutesMode('view');
        else {
          setRoutesMode('edit');
          if (routesEditSearch) routesEditSearch.value = '';
          renderRoutesEdit('');
          if (routesEditSearch) routesEditSearch.focus();
        }
      });
    }

    if (routesEditSearch) {
      routesEditSearch.addEventListener('input', () => {
        renderRoutesEdit(routesEditSearch.value || '');
      });
    }

    if (btnRoutesSelectAll) {
      btnRoutesSelectAll.addEventListener('click', () => {
        const items = computeFilteredRoutes(lastFilter);
        items.forEach((r) => {
          const ref = (r && r.ref) ? String(r.ref) : '';
          if (ref) selectedRouteRefs.add(ref);
        });
        renderRoutesEdit(lastFilter);
      });
    }

    if (btnRoutesClearAll) {
      btnRoutesClearAll.addEventListener('click', () => {
        const items = computeFilteredRoutes(lastFilter);
        items.forEach((r) => {
          const ref = (r && r.ref) ? String(r.ref) : '';
          if (ref) selectedRouteRefs.delete(ref);
        });
        renderRoutesEdit(lastFilter);
      });
    }

    if (btnRoutesCancel) {
      btnRoutesCancel.addEventListener('click', () => {
        setRoutesMode('view');
      });
    }

    if (btnRoutesSave) {
      btnRoutesSave.addEventListener('click', async () => {
        const id = Number(currentTerminalId || 0);
        if (!id) return;
        btnRoutesSave.disabled = true;
        const prevText = btnRoutesSave.textContent;
        btnRoutesSave.textContent = 'Saving...';
        try {
          const fd = new FormData();
          fd.append('terminal_id', String(id));
          fd.append('routes', JSON.stringify(Array.from(selectedRouteRefs.values())));
          const res = await fetch(rootUrl + '/admin/api/module5/save_terminal_routes.php', { method: 'POST', body: fd });
          const data = await res.json();
          if (!data || !data.ok) throw new Error((data && (data.message || data.error)) ? (data.message || data.error) : 'save_failed');
          showToast('Routes updated.');
          setTimeout(() => { window.location.reload(); }, 400);
        } catch (err) {
          showToast(err.message || 'Failed', 'error');
          btnRoutesSave.disabled = false;
          btnRoutesSave.textContent = prevText;
        }
      });
    }

    const vModal = document.getElementById('terminalVehiclesModal');
    const vModalBody = document.getElementById('terminalVehiclesModalBody');
    const vModalSub = document.getElementById('terminalVehiclesModalSub');
    function openVModal() { if (vModal) vModal.classList.remove('hidden'); }
    function closeVModal() { if (vModal) vModal.classList.add('hidden'); }
    if (vModal) {
      const closeBtn = vModal.querySelector('[data-modal-close]');
      const backdrop = vModal.querySelector('[data-modal-backdrop]');
      if (closeBtn) closeBtn.addEventListener('click', closeVModal);
      if (backdrop) backdrop.addEventListener('click', closeVModal);
    }

    async function showTerminalVehicles(terminalId) {
      if (!vModalBody) return;
      vModalBody.innerHTML = '<tr><td colspan="4" class="py-10 text-center text-slate-500 font-medium italic">Loading...</td></tr>';
      if (vModalSub) vModalSub.textContent = 'Terminal ID: ' + String(terminalId);
      openVModal();
      try {
        const res = await fetch(rootUrl + '/admin/api/module5/terminal_assignments.php?terminal_id=' + encodeURIComponent(String(terminalId)));
        const data = await res.json();
        if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'load_failed');
        const rows = Array.isArray(data.data) ? data.data : [];
        if (!rows.length) {
          vModalBody.innerHTML = '<tr><td colspan="4" class="py-10 text-center text-slate-500 font-medium italic">No assigned vehicles.</td></tr>';
          return;
        }
        if (vModalSub) vModalSub.textContent = (rows[0].terminal_name ? String(rows[0].terminal_name) : 'Assignments') + ' • ' + rows.length + ' vehicle(s)';
        vModalBody.innerHTML = rows.map(r => {
          const plate = (r.plate_number || '-').toString();
          const op = (r.operator_name || '-').toString();
          const vt = (r.vehicle_type || '-').toString();
          const at = (r.assigned_at || '').toString();
          const dt = at ? new Date(at) : null;
          const atText = dt && !isNaN(dt.getTime()) ? dt.toLocaleString() : (at || '-');
          return `
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
              <td class="py-3 px-3 font-black text-slate-900 dark:text-white">${plate}</td>
              <td class="py-3 px-3 text-slate-600 dark:text-slate-300 font-semibold">${op}</td>
              <td class="py-3 px-3 text-slate-600 dark:text-slate-300 font-semibold">${vt}</td>
              <td class="py-3 px-3 text-right text-slate-600 dark:text-slate-300 font-semibold">${atText}</td>
            </tr>
          `;
        }).join('');
      } catch (e) {
        vModalBody.innerHTML = '<tr><td colspan="4" class="py-10 text-center text-rose-600 font-semibold">Failed to load assigned vehicles.</td></tr>';
      }
    }

    Array.from(document.querySelectorAll('[data-terminal-vehicles]')).forEach((btn) => {
      btn.addEventListener('click', () => {
        const id = Number(btn.getAttribute('data-terminal-vehicles') || 0);
        if (id > 0) showTerminalVehicles(id);
      });
    });

    document.addEventListener('click', (e) => {
      const btn = e.target.closest('#btnOpenCreateTerminal');
      if (btn) openCreateTerminalModal();
    });

    // --- Enhanced Logic ---
    // Tabs
    document.querySelectorAll('.tab-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const target = btn.getAttribute('data-target');
        const nav = btn.parentElement;
        nav.querySelectorAll('.tab-btn').forEach(b => {
           if (b === btn) {
             b.classList.remove('border-transparent', 'text-slate-500', 'hover:border-slate-300', 'hover:text-slate-700', 'dark:text-slate-400', 'dark:hover:text-slate-200');
             b.classList.add('border-blue-600', 'text-blue-600', 'dark:text-blue-400');
           } else {
             b.classList.add('border-transparent', 'text-slate-500', 'hover:border-slate-300', 'hover:text-slate-700', 'dark:text-slate-400', 'dark:hover:text-slate-200');
             b.classList.remove('border-blue-600', 'text-blue-600', 'dark:text-blue-400');
           }
        });
        const container = nav.closest('form');
        if (container) {
          container.querySelectorAll('.tab-pane').forEach(p => {
            p.classList.toggle('hidden', p.id !== target);
          });
        }
      });
    });

    // Info Modal
    const infoModal = document.getElementById('terminalInfoModal');
    const infoContent = document.getElementById('terminalInfoContent');
    function closeInfoModal() { if (infoModal) infoModal.classList.add('hidden'); }
    if (infoModal) {
       infoModal.querySelectorAll('[data-modal-close]').forEach(el => el.addEventListener('click', closeInfoModal));
       const bd = infoModal.querySelector('[data-modal-backdrop]');
       if (bd) bd.addEventListener('click', closeInfoModal);
    }
    
    async function showTerminalInfo(id) {
       if (!infoModal || !infoContent) return;
       infoModal.classList.remove('hidden');
       infoContent.innerHTML = '<div class="text-center text-slate-500 italic py-10">Loading...</div>';
       try {
         const res = await fetch(rootUrl + '/admin/api/module5/get_terminal_details.php?id=' + id);
         const data = await res.json();
         if (!data || !data.success) throw new Error(data.message || 'Error');
         
         const t = data.terminal || {};
         const a = data.agreement || {};
         const d = data.documents || [];
         const owner = a.owner_name || 'Unspecified';
         const contact = a.owner_contact || '-';
         
         const docsHtml = d.length ? d.map(doc => `
           <div class="flex items-center justify-between p-3 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800">
             <div class="flex items-center gap-3">
               <div class="p-2 rounded bg-white dark:bg-slate-700 text-blue-600"><i data-lucide="file-text" class="w-4 h-4"></i></div>
               <div><div class="font-bold text-slate-900 dark:text-white text-sm">${doc.doc_type || 'Document'}</div><div class="text-xs text-slate-500">${doc.uploaded_at || ''}</div></div>
             </div>
             <a href="${rootUrl}/uploads/${doc.file_path}" target="_blank" class="text-xs font-bold text-blue-600 hover:underline">Download</a>
           </div>
         `).join('') : '<div class="text-slate-500 italic text-sm">No documents attached.</div>';

         infoContent.innerHTML = `
           <div class="space-y-6">
             <div class="grid grid-cols-2 gap-4">
               <div><div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Name</div><div class="font-black text-slate-900 dark:text-white text-lg">${t.name}</div></div>
               <div><div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Location</div><div class="font-semibold text-slate-700 dark:text-slate-200">${t.location || '-'}</div></div>
             </div>
             <div class="border-t border-slate-200 dark:border-slate-700 pt-4">
               <h4 class="font-black text-slate-900 dark:text-white mb-3 flex items-center gap-2"><i data-lucide="user" class="w-4 h-4"></i> Owner Information</h4>
               <div class="grid grid-cols-2 gap-4">
                 <div><div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Owner Name</div><div class="font-bold text-slate-900 dark:text-white">${owner}</div></div>
                 <div><div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Contact</div><div class="font-semibold text-slate-700 dark:text-slate-200">${contact}</div></div>
               </div>
             </div>
             <div class="border-t border-slate-200 dark:border-slate-700 pt-4">
               <h4 class="font-black text-slate-900 dark:text-white mb-3 flex items-center gap-2"><i data-lucide="file-check" class="w-4 h-4"></i> Agreement Details</h4>
               ${a.id ? `
                 <div class="grid grid-cols-2 gap-4 text-sm">
                   <div><span class="text-slate-500">Type:</span> <span class="font-bold dark:text-white">${a.agreement_type}</span></div>
                   <div><span class="text-slate-500">Status:</span> <span class="font-bold ${a.status==='Active'?'text-emerald-600':'text-rose-600'}">${a.status}</span></div>
                   <div><span class="text-slate-500">Rent:</span> <span class="font-bold dark:text-white">${parseFloat(a.rent_amount||0).toFixed(2)} / ${a.rent_frequency}</span></div>
                   <div><span class="text-slate-500">Ref No:</span> <span class="font-bold dark:text-white">${a.reference_no||'-'}</span></div>
                   <div><span class="text-slate-500">Start:</span> <span class="font-bold dark:text-white">${a.start_date||'-'}</span></div>
                   <div><span class="text-slate-500">End:</span> <span class="font-bold dark:text-white">${a.end_date||'-'}</span></div>
                   <div class="col-span-2"><span class="text-slate-500">Duration:</span> <span class="font-bold dark:text-white">${a.duration_computed||'-'}</span></div>
                   <div class="col-span-2 bg-slate-50 dark:bg-slate-800 p-3 rounded text-slate-600 dark:text-slate-300 italic text-xs border border-slate-200 dark:border-slate-700">${a.terms_summary||'No terms summary.'}</div>
                 </div>
               ` : '<div class="text-slate-500 italic">No active agreement found.</div>'}
             </div>
             <div class="border-t border-slate-200 dark:border-slate-700 pt-4">
               <h4 class="font-black text-slate-900 dark:text-white mb-3 flex items-center gap-2"><i data-lucide="folder" class="w-4 h-4"></i> Documents</h4>
               <div class="space-y-2">${docsHtml}</div>
             </div>
           </div>
         `;
         if (window.lucide) window.lucide.createIcons();
       } catch (e) {
         infoContent.innerHTML = '<div class="text-center text-rose-500 font-bold py-10">Failed to load details.</div>';
       }
    }
    document.addEventListener('click', (e) => {
       const btn = e.target.closest('[data-terminal-info]');
       if (btn) showTerminalInfo(btn.getAttribute('data-terminal-info'));
    });

    const agreeModal = document.getElementById('terminalAgreementModal');
    const agreeSub = document.getElementById('terminalAgreementSub');
    const agreeForm = document.getElementById('formTerminalAgreement');
    const agreeSaveBtn = document.getElementById('btnSaveTerminalAgreement');
    function closeAgreeModal() { if (agreeModal) agreeModal.classList.add('hidden'); }
    function openAgreeModal() { if (agreeModal) agreeModal.classList.remove('hidden'); if (window.lucide) window.lucide.createIcons(); }
    if (agreeModal) {
      const closeBtn = agreeModal.querySelector('[data-modal-close]');
      const cancelBtn = agreeModal.querySelector('[data-modal-cancel]');
      const bd = agreeModal.querySelector('[data-modal-backdrop]');
      if (closeBtn) closeBtn.addEventListener('click', closeAgreeModal);
      if (cancelBtn) cancelBtn.addEventListener('click', closeAgreeModal);
      if (bd) bd.addEventListener('click', closeAgreeModal);
    }

    function setAgreeValue(name, value) {
      if (!agreeForm) return;
      const el = agreeForm.querySelector(`[name="${name}"]`);
      if (!el) return;
      el.value = (value === null || value === undefined) ? '' : String(value);
    }

    async function loadAgreementIntoModal(terminalId) {
      if (!agreeForm) return;
      setAgreeValue('terminal_id', terminalId);
      setAgreeValue('agreement_id', '');
      setAgreeValue('owner_name', '');
      setAgreeValue('owner_type', 'Other');
      setAgreeValue('owner_contact', '');
      setAgreeValue('agreement_type', 'MOA');
      setAgreeValue('agreement_reference_no', '');
      setAgreeValue('rent_amount', '');
      setAgreeValue('rent_frequency', 'Monthly');
      setAgreeValue('agreement_status', 'Active');
      setAgreeValue('start_date', '');
      setAgreeValue('end_date', '');
      setAgreeValue('terms_summary', '');
      if (agreeSub) agreeSub.textContent = 'Terminal ID: ' + String(terminalId);

      try {
        const res = await fetch(rootUrl + '/admin/api/module5/get_terminal_details.php?id=' + encodeURIComponent(String(terminalId)));
        const data = await res.json();
        if (data && data.success) {
          const t = data.terminal || {};
          const a = data.agreement || {};
          if (agreeSub) agreeSub.textContent = (t.name ? String(t.name) : 'Terminal') + ' • Agreement';
          if (a && a.id) {
            setAgreeValue('agreement_id', a.id);
            setAgreeValue('agreement_type', a.agreement_type);
            setAgreeValue('agreement_reference_no', a.reference_no);
            setAgreeValue('rent_amount', a.rent_amount);
            setAgreeValue('rent_frequency', a.rent_frequency);
            setAgreeValue('agreement_status', a.status);
            setAgreeValue('start_date', a.start_date);
            setAgreeValue('end_date', a.end_date);
            setAgreeValue('terms_summary', a.terms_summary);
          }
          setAgreeValue('owner_name', a.owner_name);
          setAgreeValue('owner_type', a.owner_type || 'Other');
          setAgreeValue('owner_contact', a.owner_contact);
        }
      } catch (_) {}
    }

    document.addEventListener('click', async (e) => {
      const btn = e.target.closest('[data-terminal-agreement]');
      if (!btn) return;
      const id = Number(btn.getAttribute('data-terminal-agreement') || 0);
      if (!id) return;
      await loadAgreementIntoModal(id);
      openAgreeModal();
    });

    if (agreeForm && agreeSaveBtn) {
      agreeForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!agreeForm.checkValidity()) { agreeForm.reportValidity(); return; }
        agreeSaveBtn.disabled = true;
        const prev = agreeSaveBtn.textContent;
        agreeSaveBtn.textContent = 'Saving...';
        try {
          const res = await fetch(rootUrl + '/admin/api/module5/save_terminal_agreement.php', { method: 'POST', body: new FormData(agreeForm) });
          const data = await res.json().catch(() => null);
          if (!data || !data.ok) throw new Error((data && data.message) ? data.message : 'save_failed');
          showToast('Details saved.');
          closeAgreeModal();
          setTimeout(() => { window.location.reload(); }, 350);
        } catch (err) {
          showToast(err.message || 'Failed', 'error');
          agreeSaveBtn.disabled = false;
          agreeSaveBtn.textContent = prev;
        }
      });
    }

  })();
</script>
