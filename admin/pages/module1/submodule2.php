<?php
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module1.read','module1.write']);

require_once __DIR__ . '/../../includes/db.php';
$db = db();

$schema = '';
$schRes = $db->query("SELECT DATABASE() AS db");
if ($schRes) { $schema = (string)(($schRes->fetch_assoc()['db'] ?? '') ?: ''); }
function tmm_has_column_mod1_sub2(mysqli $db, string $schema, string $table, string $col): bool {
  if ($schema === '') return false;
  $t = $db->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1");
  if (!$t) return false;
  $t->bind_param('sss', $schema, $table, $col);
  $t->execute();
  $res = $t->get_result();
  $ok = (bool)($res && $res->fetch_row());
  $t->close();
  return $ok;
}
$vdHasVehicleId = tmm_has_column_mod1_sub2($db, $schema, 'vehicle_documents', 'vehicle_id');
$vdHasPlate = tmm_has_column_mod1_sub2($db, $schema, 'vehicle_documents', 'plate_number');
$orcrCond = "LOWER(vd.doc_type) IN ('orcr','or/cr','or','cr')";
$docsHasExpiry = tmm_has_column_mod1_sub2($db, $schema, 'documents', 'expiry_date');

$statTotalVeh = (int)($db->query("SELECT COUNT(*) AS c FROM vehicles")->fetch_assoc()['c'] ?? 0);
$statLinkedVeh = (int)($db->query("SELECT COUNT(*) AS c FROM vehicles WHERE operator_id IS NOT NULL AND operator_id>0")->fetch_assoc()['c'] ?? 0);
$statUnlinkedVeh = (int)($db->query("SELECT COUNT(*) AS c FROM vehicles WHERE operator_id IS NULL OR operator_id=0")->fetch_assoc()['c'] ?? 0);
$statWithOrcr = 0;
if ($vdHasVehicleId && $vdHasPlate) {
  $statWithOrcr = (int)($db->query("SELECT COUNT(DISTINCT v.id) AS c
                                    FROM vehicles v
                                    JOIN vehicle_documents vd ON (vd.vehicle_id=v.id OR ((vd.vehicle_id IS NULL OR vd.vehicle_id=0) AND vd.plate_number=v.plate_number))
                                    WHERE $orcrCond")->fetch_assoc()['c'] ?? 0);
} elseif ($vdHasVehicleId) {
  $statWithOrcr = (int)($db->query("SELECT COUNT(DISTINCT vehicle_id) AS c FROM vehicle_documents vd WHERE vd.vehicle_id IS NOT NULL AND vd.vehicle_id>0 AND $orcrCond")->fetch_assoc()['c'] ?? 0);
} elseif ($vdHasPlate) {
  $statWithOrcr = (int)($db->query("SELECT COUNT(DISTINCT v.id) AS c
                                    FROM vehicles v
                                    JOIN vehicle_documents vd ON vd.plate_number=v.plate_number
                                    WHERE $orcrCond")->fetch_assoc()['c'] ?? 0);
}
$statMissingOrcr = max(0, $statTotalVeh - $statWithOrcr);

$q = trim((string)($_GET['q'] ?? ''));
$vehicleType = trim((string)($_GET['vehicle_type'] ?? ''));
$recordStatus = trim((string)($_GET['record_status'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$highlightPlate = strtoupper(trim((string)($_GET['highlight_plate'] ?? '')));

$hasOrcrSql = "0 AS has_orcr";
if ($vdHasVehicleId && $vdHasPlate) {
  $hasOrcrSql = "(SELECT COUNT(*) FROM vehicle_documents vd WHERE (vd.vehicle_id=v.id OR ((vd.vehicle_id IS NULL OR vd.vehicle_id=0) AND vd.plate_number=v.plate_number)) AND $orcrCond) AS has_orcr";
} elseif ($vdHasVehicleId) {
  $hasOrcrSql = "(SELECT COUNT(*) FROM vehicle_documents vd WHERE vd.vehicle_id=v.id AND $orcrCond) AS has_orcr";
} elseif ($vdHasPlate) {
  $hasOrcrSql = "(SELECT COUNT(*) FROM vehicle_documents vd WHERE vd.plate_number=v.plate_number AND $orcrCond) AS has_orcr";
}

$sql = "SELECT v.id AS vehicle_id,
               v.plate_number,
               v.vehicle_type,
               v.operator_id,
               COALESCE(NULLIF(o.name,''), NULLIF(o.full_name,''), NULLIF(v.operator_name,''), '') AS operator_display,
               v.engine_no, v.chassis_no, v.make, v.model, v.year_model, v.fuel_type,
               v.record_status, v.status, v.created_at,
               $hasOrcrSql
        FROM vehicles v
        LEFT JOIN operators o ON o.id=v.operator_id";
$conds = [];
$params = [];
$types = '';

if ($q !== '') {
  $conds[] = "(v.plate_number LIKE ? OR v.operator_name LIKE ? OR o.name LIKE ? OR o.full_name LIKE ?)";
  $params[] = "%$q%";
  $params[] = "%$q%";
  $params[] = "%$q%";
  $params[] = "%$q%";
  $types .= 'ssss';
}
if ($vehicleType !== '' && $vehicleType !== 'Vehicle type') {
  $conds[] = "v.vehicle_type=?";
  $params[] = $vehicleType;
  $types .= 's';
}
if ($recordStatus !== '' && $recordStatus !== 'Record status') {
  $conds[] = "v.record_status=?";
  $params[] = $recordStatus;
  $types .= 's';
}
if ($status !== '' && $status !== 'Status') {
  if ($status === 'Linked') {
    $conds[] = "v.record_status='Linked'";
  } elseif ($status === 'Unlinked') {
    $conds[] = "v.record_status='Encoded'";
  } else {
    $conds[] = "v.status=?";
    $params[] = $status;
    $types .= 's';
  }
}
if ($conds) $sql .= " WHERE " . implode(" AND ", $conds);
$sql .= " ORDER BY v.created_at DESC LIMIT 300";

if ($params) {
  $stmt = $db->prepare($sql);
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();
} else {
  $res = $db->query($sql);
}

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';

require_once __DIR__ . '/../../includes/vehicle_types.php';
$typesList = vehicle_types();
?>

<div class="mx-auto max-w-7xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-6">
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between border-b border-slate-200 dark:border-slate-700 pb-6">
    <div>
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Vehicles</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">Register vehicle master records in the PUV Database (pre-franchise allowed). Linking a vehicle to an operator does not mean it is allowed to operate—activation depends on franchise approval, OR/CR recording, and a passed inspection.</p>
    </div>
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
      <a href="?page=module1/submodule1" class="inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
        <i data-lucide="users" class="w-4 h-4"></i>
        Operators
      </a>
      <a href="?page=module1/submodule4" class="inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
        <i data-lucide="link-2" class="w-4 h-4"></i>
        Link Operator
      </a>
      <?php if (has_permission('reports.export')): ?>
        <a href="<?php echo htmlspecialchars($rootUrl); ?>/admin/api/module1/export_vehicles_csv.php?<?php echo http_build_query(['q'=>$q,'record_status'=>$recordStatus,'status'=>$status]); ?>"
          class="inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
          <i data-lucide="download" class="w-4 h-4"></i>
          Export CSV
        </a>
        <a href="<?php echo htmlspecialchars($rootUrl); ?>/admin/api/module1/export_vehicles_csv.php?<?php echo http_build_query(['q'=>$q,'record_status'=>$recordStatus,'status'=>$status,'format'=>'excel']); ?>"
          class="inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
          <i data-lucide="file-spreadsheet" class="w-4 h-4"></i>
          Export Excel
        </a>
      <?php endif; ?>
      <?php if (has_permission('module1.vehicles.write')): ?>
        <button id="btnOpenAddVehicle" type="button" class="inline-flex items-center justify-center gap-2 rounded-md bg-blue-700 hover:bg-blue-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-all active:scale-[0.98]">
          <i data-lucide="plus" class="w-4 h-4"></i>
          Add Vehicle
        </button>
      <?php endif; ?>
    </div>
  </div>

  <div id="toast-container" class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 z-[100] flex flex-col gap-3 pointer-events-none"></div>

  <div class="grid grid-cols-1 md:grid-cols-5 gap-6">
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total</div>
      <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-white"><?php echo number_format($statTotalVeh); ?></div>
    </div>
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Linked</div>
      <div class="mt-2 text-2xl font-bold text-emerald-600 dark:text-emerald-400"><?php echo number_format($statLinkedVeh); ?></div>
    </div>
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Unlinked</div>
      <div class="mt-2 text-2xl font-bold text-amber-600 dark:text-amber-400"><?php echo number_format($statUnlinkedVeh); ?></div>
    </div>
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">With OR/CR</div>
      <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-white"><?php echo number_format($statWithOrcr); ?></div>
    </div>
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Missing OR/CR</div>
      <div class="mt-2 text-2xl font-bold text-rose-600 dark:text-rose-400"><?php echo number_format($statMissingOrcr); ?></div>
    </div>
  </div>

  <div class="bg-white dark:bg-slate-800 p-5 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
    <form class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between" method="GET">
      <input type="hidden" name="page" value="module1/submodule2">
      <div class="flex-1 flex flex-col sm:flex-row gap-3">
        <div class="relative flex-1 sm:max-w-sm group">
          <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 group-focus-within:text-blue-500 transition-colors"></i>
          <input name="q" value="<?php echo htmlspecialchars($q); ?>" class="w-full pl-10 pr-4 py-2.5 text-sm font-semibold border-0 rounded-md bg-slate-50 dark:bg-slate-900/40 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-all placeholder:text-slate-400" placeholder="Search plate or operator...">
        </div>
        <div class="relative w-full sm:w-52">
          <select name="vehicle_type" class="px-4 py-2.5 pr-10 text-sm font-semibold border-0 rounded-md bg-slate-50 dark:bg-slate-900/40 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-all appearance-none cursor-pointer">
            <option value="">All Types</option>
            <?php foreach ($typesList as $t): ?>
              <option value="<?php echo htmlspecialchars($t); ?>" <?php echo $vehicleType === $t ? 'selected' : ''; ?>><?php echo htmlspecialchars($t); ?></option>
            <?php endforeach; ?>
          </select>
          <i data-lucide="chevron-down" class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none"></i>
        </div>
        <div class="relative w-full sm:w-52">
          <select name="record_status" class="px-4 py-2.5 pr-10 text-sm font-semibold border-0 rounded-md bg-slate-50 dark:bg-slate-900/40 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-all appearance-none cursor-pointer">
            <option value="">All Record Status</option>
            <?php foreach (['Encoded','Linked','Archived'] as $s): ?>
              <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $recordStatus === $s ? 'selected' : ''; ?>><?php echo htmlspecialchars($s); ?></option>
            <?php endforeach; ?>
          </select>
          <i data-lucide="chevron-down" class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none"></i>
        </div>
        <div class="relative w-full sm:w-44">
          <select name="status" class="px-4 py-2.5 pr-10 text-sm font-semibold border-0 rounded-md bg-slate-50 dark:bg-slate-900/40 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-all appearance-none cursor-pointer">
            <option value="">All Status</option>
            <?php foreach (['Unlinked','Linked','Active','Inactive'] as $s): ?>
              <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $status === $s ? 'selected' : ''; ?>><?php echo htmlspecialchars($s); ?></option>
            <?php endforeach; ?>
          </select>
          <i data-lucide="chevron-down" class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none"></i>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <button class="inline-flex items-center gap-2 rounded-md bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 dark:hover:bg-slate-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors">
          <i data-lucide="filter" class="w-4 h-4"></i>
          Apply
        </button>
        <a href="?page=module1/submodule2" class="inline-flex items-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
          Reset
        </a>
      </div>
    </form>
  </div>

  <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-700 border-b border-slate-200 dark:border-slate-700">
          <tr class="text-left text-slate-500 dark:text-slate-400">
            <th class="py-4 px-6 font-black uppercase tracking-widest text-xs">Plate</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs hidden md:table-cell">Type</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">Operator</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs hidden lg:table-cell">Docs</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs">Record</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs hidden sm:table-cell">Created</th>
            <th class="py-4 px-4 font-black uppercase tracking-widest text-xs text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-800">
          <?php if ($res && $res->num_rows > 0): ?>
            <?php while ($row = $res->fetch_assoc()): ?>
              <?php
                $plate = (string)($row['plate_number'] ?? '');
                $plateUp = strtoupper($plate);
                $isHighlight = $highlightPlate !== '' && $highlightPlate === $plateUp;
                $rs = (string)($row['record_status'] ?? '');
                if ($rs === '') {
                  $opId = (int)($row['operator_id'] ?? 0);
                  $rs = $opId > 0 ? 'Linked' : 'Encoded';
                }
                $st = (string)($row['status'] ?? '');
                $badgeRs = match($rs) {
                  'Linked' => 'bg-blue-100 text-blue-700 ring-blue-600/20 dark:bg-blue-900/30 dark:text-blue-400 dark:ring-blue-500/20',
                  'Archived' => 'bg-rose-100 text-rose-700 ring-rose-600/20 dark:bg-rose-900/30 dark:text-rose-400 dark:ring-rose-500/20',
                  'Encoded' => 'bg-amber-100 text-amber-700 ring-amber-600/20 dark:bg-amber-900/30 dark:text-amber-400 dark:ring-amber-500/20',
                  default => 'bg-slate-100 text-slate-700 ring-slate-600/20 dark:bg-slate-800 dark:text-slate-400'
                };
                $badgeSt = match($st) {
                  'Active' => 'bg-emerald-100 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-400 dark:ring-emerald-500/20',
                  'Blocked' => 'bg-rose-100 text-rose-700 ring-rose-600/20 dark:bg-rose-900/30 dark:text-rose-400 dark:ring-rose-500/20',
                  'Inactive' => 'bg-rose-100 text-rose-700 ring-rose-600/20 dark:bg-rose-900/30 dark:text-rose-400 dark:ring-rose-500/20',
                  'Linked' => 'bg-blue-100 text-blue-700 ring-blue-600/20 dark:bg-blue-900/30 dark:text-blue-400 dark:ring-blue-500/20',
                  'Unlinked' => 'bg-amber-100 text-amber-700 ring-amber-600/20 dark:bg-amber-900/30 dark:text-amber-400 dark:ring-amber-500/20',
                  default => 'bg-slate-100 text-slate-700 ring-slate-600/20 dark:bg-slate-800 dark:text-slate-400'
                };
                $hasOrcr = (int)($row['has_orcr'] ?? 0) > 0;
              ?>
              <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors group <?php echo $isHighlight ? 'bg-emerald-50/70 dark:bg-emerald-900/15 ring-1 ring-inset ring-emerald-200/70 dark:ring-emerald-900/30' : ''; ?>" <?php echo $isHighlight ? 'id="veh-row-highlight"' : ''; ?>>
                <td class="py-4 px-6">
                  <div class="font-black text-slate-900 dark:text-white"><?php echo htmlspecialchars($plateUp); ?></div>
                  <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">ID: <?php echo (int)($row['vehicle_id'] ?? 0); ?></div>
                </td>
                <td class="py-4 px-4 hidden md:table-cell">
                  <span class="inline-flex items-center rounded-lg bg-slate-100 dark:bg-slate-700/50 px-2.5 py-1 text-xs font-bold text-slate-600 dark:text-slate-300 ring-1 ring-inset ring-slate-500/10"><?php echo htmlspecialchars((string)($row['vehicle_type'] ?? '')); ?></span>
                </td>
                <td class="py-4 px-4 text-slate-600 dark:text-slate-300 font-medium">
                  <?php echo htmlspecialchars((string)($row['operator_display'] ?? '')); ?>
                  <?php if ((string)($row['operator_display'] ?? '') === ''): ?>
                    <span class="text-slate-400 italic">Unlinked</span>
                  <?php endif; ?>
                </td>
                <td class="py-4 px-4 hidden lg:table-cell">
                  <?php
                    $plateKey = $db->real_escape_string($plateUp);
                    $docsTbl = $db->query("SHOW TABLES LIKE 'documents'");
                    $hasDocsTbl = (bool)($docsTbl && $docsTbl->fetch_row());
                    $hasOr = false;
                    $hasCr = false;
                    $orValid = true;
                    if ($hasDocsTbl) {
                      $rr = $db->query("SELECT
                                          MAX(CASE WHEN LOWER(type)='or' THEN 1 ELSE 0 END) AS has_or,
                                          MAX(CASE WHEN LOWER(type)='cr' THEN 1 ELSE 0 END) AS has_cr" . ($docsHasExpiry ? ",
                                          MAX(CASE WHEN LOWER(type)='or' AND (expiry_date IS NULL OR expiry_date >= CURDATE()) THEN 1 ELSE 0 END) AS or_valid" : "") . "
                                        FROM documents WHERE plate_number='{$plateKey}'");
                      $m = $rr ? $rr->fetch_assoc() : null;
                      $hasOr = (int)($m['has_or'] ?? 0) === 1;
                      $hasCr = (int)($m['has_cr'] ?? 0) === 1;
                      if ($docsHasExpiry) $orValid = (int)($m['or_valid'] ?? 0) === 1;
                    }
                    $label = 'Missing';
                    if ($hasOr && !$orValid) $label = 'OR expired';
                    else if ($hasOr && $hasCr) $label = 'OR & CR on file';
                    else if ($hasOr) $label = 'OR on file';
                    else if ($hasCr) $label = 'CR on file';
                    else if ($hasOrcr) $label = 'OR/CR on file';
                    $ok = $label !== 'Missing';
                  ?>
                  <span class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-bold ring-1 ring-inset <?php echo $ok ? 'bg-emerald-100 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-400 dark:ring-emerald-500/20' : 'bg-slate-100 text-slate-700 ring-slate-600/20 dark:bg-slate-800 dark:text-slate-400'; ?>">
                    <?php echo htmlspecialchars($label); ?>
                  </span>
                </td>
                <td class="py-4 px-4">
                  <div class="flex flex-wrap items-center gap-2">
                    <span class="px-2.5 py-1 rounded-lg text-xs font-bold ring-1 ring-inset <?php echo $badgeRs; ?>"><?php echo htmlspecialchars($rs); ?></span>
                    <span class="px-2.5 py-1 rounded-lg text-xs font-bold ring-1 ring-inset <?php echo $badgeSt; ?>"><?php echo htmlspecialchars($st); ?></span>
                    <?php if ($st === 'Blocked'): ?>
                      <span class="px-2.5 py-1 rounded-lg text-xs font-black bg-rose-50 text-rose-700 border border-rose-200 inline-flex items-center gap-2">
                        <i data-lucide="octagon-alert" class="w-4 h-4"></i>
                        Operation blocked
                      </span>
                    <?php elseif ($st === 'Inactive'): ?>
                      <span class="px-2.5 py-1 rounded-lg text-xs font-black bg-amber-50 text-amber-800 border border-amber-200 inline-flex items-center gap-2">
                        <i data-lucide="triangle-alert" class="w-4 h-4"></i>
                        Missing OR
                      </span>
                    <?php endif; ?>
                  </div>
                </td>
                <td class="py-4 px-4 text-slate-500 font-medium text-xs hidden sm:table-cell">
                  <?php echo htmlspecialchars(date('M d, Y', strtotime((string)($row['created_at'] ?? 'now')))); ?>
                </td>
                <td class="py-4 px-4 text-right">
                  <div class="flex items-center justify-end gap-2 opacity-100 md:opacity-0 md:group-hover:opacity-100 transition-opacity">
                    <button type="button" class="p-2 rounded-xl bg-slate-100 dark:bg-slate-700/50 text-slate-500 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-all" data-veh-view="1" data-plate="<?php echo htmlspecialchars($plateUp, ENT_QUOTES); ?>" title="View Details">
                      <i data-lucide="eye" class="w-4 h-4"></i>
                    </button>
                    <?php if (has_permission('module1.vehicles.write')): ?>
                      <button type="button" class="p-2 rounded-xl bg-slate-100 dark:bg-slate-700/50 text-slate-500 hover:text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-all" data-veh-docs="1" data-vehicle-id="<?php echo (int)($row['vehicle_id'] ?? 0); ?>" data-plate="<?php echo htmlspecialchars($plateUp, ENT_QUOTES); ?>" title="Upload / View Docs">
                        <i data-lucide="upload-cloud" class="w-4 h-4"></i>
                      </button>
                      <a href="?page=module1/submodule4&plate=<?php echo urlencode($plateUp); ?>" class="p-2 rounded-xl bg-slate-100 dark:bg-slate-700/50 text-slate-500 hover:text-violet-600 hover:bg-violet-50 dark:hover:bg-violet-900/20 transition-all inline-flex items-center justify-center" title="Link Operator">
                        <i data-lucide="link-2" class="w-4 h-4"></i>
                      </a>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="7" class="py-12 text-center text-slate-500 font-medium italic">No vehicles found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div id="modalVeh" class="fixed inset-0 z-[200] hidden">
  <div id="modalVehBackdrop" class="absolute inset-0 bg-slate-900/50 opacity-0 transition-opacity"></div>
  <div class="absolute inset-0 flex items-center justify-center p-4">
    <div id="modalVehPanel" class="w-full max-w-4xl rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-xl transform scale-95 opacity-0 transition-all">
      <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700">
        <div class="font-black text-slate-900 dark:text-white" id="modalVehTitle">Vehicle</div>
        <button type="button" id="modalVehClose" class="p-2 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
          <i data-lucide="x" class="w-4 h-4"></i>
        </button>
      </div>
      <div id="modalVehBody" class="p-6 max-h-[80vh] overflow-y-auto"></div>
    </div>
  </div>
</div>

<script>
  (function(){
    const rootUrl = <?php echo json_encode($rootUrl); ?>;
    const canWrite = <?php echo json_encode(has_permission('module1.vehicles.write')); ?>;
    const vehicleTypes = <?php echo json_encode(array_values($typesList)); ?>;
    const makeOptions = [
      'Toyota','Mitsubishi','Nissan','Isuzu','Suzuki','Hyundai','Kia','Ford','Honda','Mazda','Chevrolet',
      'Foton','Hino','Daewoo','Mercedes-Benz','BMW','Audi','Volkswagen','BYD','Geely','Chery','MG','Changan'
    ];
    const modelOptionsByMake = {
      'Toyota': ['Hiace','Coaster','Innova','Vios','Fortuner','Hilux','Tamaraw FX','LiteAce'],
      'Mitsubishi': ['L300','L200','Adventure','Montero Sport','Canter','Rosa'],
      'Nissan': ['Urvan','Navara','NV350','Almera'],
      'Isuzu': ['N-Series','Elf','Traviz','D-Max','MU-X'],
      'Suzuki': ['Carry','APV','Ertiga'],
      'Hyundai': ['H-100','Starex','County'],
      'Kia': ['K2500','K2700'],
      'Ford': ['Transit','Ranger','Everest'],
      'Honda': ['Civic','City','Brio'],
      'Mazda': ['BT-50'],
      'Chevrolet': ['Trailblazer'],
      'Foton': ['Gratour','Tornado'],
      'Hino': ['Dutro'],
    };
    const fuelOptions = ['Diesel','Gasoline','Hybrid','Electric','LPG','CNG'];

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

    const modal = document.getElementById('modalVeh');
    const backdrop = document.getElementById('modalVehBackdrop');
    const panel = document.getElementById('modalVehPanel');
    const body = document.getElementById('modalVehBody');
    const title = document.getElementById('modalVehTitle');
    const closeBtn = document.getElementById('modalVehClose');

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

    document.querySelectorAll('[data-veh-view="1"]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const plate = btn.getAttribute('data-plate') || '';
        openModal(`<div class="text-sm text-slate-500 dark:text-slate-400">Loading...</div>`, 'Vehicle • ' + plate);
        try {
          const res = await fetch(rootUrl + '/admin/api/module1/view_html.php?plate=' + encodeURIComponent(plate));
          const html = await res.text();
          body.innerHTML = html;
          if (window.lucide) window.lucide.createIcons();
        } catch (err) {
          body.innerHTML = `<div class="text-sm text-rose-600">Failed to load.</div>`;
        }
      });
    });

    document.querySelectorAll('[data-veh-docs="1"]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        if (!canWrite) return;
        const plate = btn.getAttribute('data-plate') || '';
        const vehicleId = btn.getAttribute('data-vehicle-id') || '';
        openModal(`
          <form id="formUploadVehDocs" class="space-y-5" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="vehicle_id" value="${vehicleId}">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">OR (PDF/JPG/PNG)</label>
                <input name="or" type="file" accept=".pdf,.jpg,.jpeg,.png" class="w-full text-sm">
              </div>
              <div>
                <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">CR (PDF/JPG/PNG)</label>
                <input name="cr" type="file" accept=".pdf,.jpg,.jpeg,.png" class="w-full text-sm">
              </div>
              <div class="sm:col-span-2">
                <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">OR Expiry Date (required if OR uploaded)</label>
                <input name="or_expiry_date" type="date" class="w-full px-4 py-2.5 rounded-md bg-white dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
              </div>
              <div>
                <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Insurance</label>
                <input name="insurance" type="file" accept=".pdf,.jpg,.jpeg,.png" class="w-full text-sm">
              </div>
              <div class="sm:col-span-2">
                <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Others</label>
                <input name="others" type="file" accept=".pdf,.jpg,.jpeg,.png" class="w-full text-sm">
              </div>
            </div>
            <div class="flex items-center justify-end gap-2 pt-2">
              <button type="button" class="px-4 py-2.5 rounded-md bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 font-semibold" data-veh-docs-cancel="1">Close</button>
              <button id="btnVehDocsSave" class="px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold">Upload</button>
            </div>
          </form>
          <div class="mt-6 border-t border-slate-200 dark:border-slate-700 pt-5">
            <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-3">Existing Docs</div>
            <div id="vehDocsList" class="text-sm text-slate-500 dark:text-slate-400">Loading...</div>
          </div>
        `, 'Documents • ' + plate);

        const cancel = body.querySelector('[data-veh-docs-cancel="1"]');
        if (cancel) cancel.addEventListener('click', closeModal);

        async function loadDocs() {
          try {
            const res = await fetch(rootUrl + '/admin/api/module1/list_documents.php?vehicle_id=' + encodeURIComponent(vehicleId) + '&plate=' + encodeURIComponent(plate));
            const data = await res.json();
            const list = document.getElementById('vehDocsList');
            if (!list) return;
            if (!data || !data.ok) throw new Error((data && data.error) ? data.error : 'load_failed');
            const rows = Array.isArray(data.data) ? data.data : [];
            if (!rows.length) { list.innerHTML = '<div class="italic">No documents uploaded.</div>'; return; }
            list.innerHTML = rows.map((d) => {
              const href = rootUrl + '/admin/uploads/' + encodeURIComponent(d.file_path || '');
              const dt = d.uploaded_at ? new Date(d.uploaded_at) : null;
              const date = dt && !isNaN(dt.getTime()) ? dt.toLocaleString() : '';
              const expRaw = (d.expiry_date || '').toString();
              const expDate = expRaw ? new Date(expRaw + 'T00:00:00') : null;
              const expText = expDate && !isNaN(expDate.getTime()) ? expDate.toLocaleDateString() : '';
              const isV = Number(d.is_verified || 0) === 1;
              const badge = isV
                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400';
              const canVerify = (d.source || '') === 'vehicle_documents' || (d.source || '') === 'documents';
              return `
                <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 hover:border-blue-200 dark:hover:border-blue-800 hover:bg-blue-50/40 dark:hover:bg-blue-900/10 transition-all mb-2">
                  <div class="flex items-center gap-3">
                    <div class="p-2 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-500">
                      <i data-lucide="file" class="w-4 h-4"></i>
                    </div>
                    <div>
                      <div class="flex items-center gap-2">
                        <div class="text-sm font-black text-slate-800 dark:text-white">${(d.type || '').toString()}</div>
                        <span class="text-[10px] font-black px-2 py-0.5 rounded-full ${badge}">${isV ? 'Verified' : 'Pending'}</span>
                      </div>
                      <div class="text-xs text-slate-500 dark:text-slate-400">${date}${(String(d.type||'').toUpperCase()==='OR' && expText) ? (' • Expires: ' + expText) : ''}</div>
                    </div>
                  </div>
                  <div class="flex items-center gap-1.5">
                    ${canVerify ? `<button type="button" class="px-3 py-2 rounded-lg text-xs font-bold ${isV ? 'bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200' : 'bg-emerald-600 hover:bg-emerald-700 text-white'}" data-doc-verify="1" data-source="${(d.source||'')}" data-id="${String(d.id||'')}" data-next="${isV ? '0' : '1'}">${isV ? 'Mark Pending' : 'Verify'}</button>` : ``}
                    <a href="${href}" target="_blank" class="p-2 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-white dark:hover:bg-slate-800 transition-all" title="View"><i data-lucide="external-link" class="w-4 h-4"></i></a>
                  </div>
                </div>
              `;
            }).join('');
            if (window.lucide) window.lucide.createIcons();
            list.querySelectorAll('[data-doc-verify="1"]').forEach((b) => {
              b.addEventListener('click', async () => {
                const id = b.getAttribute('data-id') || '';
                const source = b.getAttribute('data-source') || '';
                const next = b.getAttribute('data-next') || '';
                if (!id || !source) return;
                const fd = new FormData();
                fd.append('doc_id', id);
                fd.append('source', source);
                fd.append('is_verified', next);
                try {
                  const rr = await fetch(rootUrl + '/admin/api/module1/verify_document.php', { method: 'POST', body: fd });
                  const dd = await rr.json().catch(() => null);
                  if (!dd || !dd.ok) throw new Error((dd && dd.error) ? dd.error : 'verify_failed');
                  showToast('Updated verification.');
                  await loadDocs();
                } catch (e) {
                  showToast('Failed to update verification.', 'error');
                }
              });
            });
          } catch (err) {
            const list = document.getElementById('vehDocsList');
            if (list) list.innerHTML = '<div class="text-rose-600">Failed to load documents.</div>';
          }
        }

        await loadDocs();

        const form = document.getElementById('formUploadVehDocs');
        const btnSave = document.getElementById('btnVehDocsSave');
        if (form && btnSave) {
          form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(form);
            const hasFiles = ['or','cr','insurance','others'].some((k) => fd.get(k) && fd.get(k).name);
            if (!hasFiles) { showToast('Select at least one file.', 'error'); return; }
            btnSave.disabled = true;
            btnSave.textContent = 'Uploading...';
            try {
              const res = await fetch(rootUrl + '/admin/api/module1/upload_docs.php', { method: 'POST', body: fd });
              const data = await res.json();
              if (!data || !data.ok) {
                const base = (data && data.error) ? String(data.error) : 'upload_failed';
                const extra = (data && data.details)
                  ? (typeof data.details === 'string' ? data.details : JSON.stringify(data.details))
                  : '';
                throw new Error(extra ? (base + ' ' + extra) : base);
              }
              showToast('Documents uploaded.');
              await loadDocs();
              setTimeout(() => {
                const params = new URLSearchParams(window.location.search || '');
                params.set('page', 'module1/submodule2');
                if (plate) params.set('highlight_plate', plate);
                window.location.search = params.toString();
              }, 400);
              btnSave.disabled = false;
              btnSave.textContent = 'Upload';
            } catch (err) {
              showToast(err.message || 'Upload failed', 'error');
              btnSave.disabled = false;
              btnSave.textContent = 'Upload';
            }
          });
        }
      });
    });

    const btnAdd = document.getElementById('btnOpenAddVehicle');
    if (btnAdd && canWrite) {
      btnAdd.addEventListener('click', () => {
        openModal(`
          <form id="formAddVehicle" class="space-y-5" novalidate>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Plate No</label>
                <input name="plate_no" required minlength="7" maxlength="8" pattern="^[A-Za-z]{3}\\-[0-9]{3,4}$" autocapitalize="characters" data-tmm-mask="plate" data-tmm-uppercase="1" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold uppercase" placeholder="e.g., ABC-1234 (ABC1234 also ok)">
              </div>
              <div>
                <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Vehicle Type</label>
                <select name="vehicle_type" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
                  <option value="" disabled selected>Select type</option>
                  ${vehicleTypes.map((t) => `<option value="${t}">${t}</option>`).join('')}
                </select>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Engine No</label>
                <input name="engine_no" minlength="5" maxlength="20" pattern="^[A-Z0-9\\-]{5,20}$" autocapitalize="characters" data-tmm-uppercase="1" data-tmm-filter="engine" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., 1NZFE-12345">
                <div class="mt-1 text-[11px] font-semibold text-slate-500 dark:text-slate-400">Engine number (from engine block or CR)</div>
              </div>
              <div>
                <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Chassis No</label>
                <input name="chassis_no" minlength="17" maxlength="17" pattern="^[A-HJ-NPR-Z0-9]{17}$" autocapitalize="characters" data-tmm-uppercase="1" data-tmm-filter="vin" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., NCP12345678901234">
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <div>
                <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Make</label>
                <select id="vehMakeSelect" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold"></select>
                <div id="vehMakeOtherWrap" class="hidden mt-2">
                  <input id="vehMakeOtherInput" maxlength="40" class="w-full px-4 py-2.5 rounded-md bg-white dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="Type make">
                </div>
                <input id="vehMakeHidden" name="make" type="hidden">
              </div>
              <div>
                <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Model</label>
                <select id="vehModelSelect" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold"></select>
                <div id="vehModelOtherWrap" class="hidden mt-2">
                  <input id="vehModelOtherInput" maxlength="40" class="w-full px-4 py-2.5 rounded-md bg-white dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="Type model">
                </div>
                <input id="vehModelHidden" name="model" type="hidden">
              </div>
              <div>
                <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Year</label>
                <input name="year_model" type="tel" inputmode="numeric" minlength="4" maxlength="4" pattern="^[0-9]{4}$" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., 2018">
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Fuel Type</label>
                <select id="vehFuelSelect" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold"></select>
                <div id="vehFuelOtherWrap" class="hidden mt-2">
                  <input id="vehFuelOtherInput" maxlength="20" class="w-full px-4 py-2.5 rounded-md bg-white dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="Type fuel type">
                </div>
                <input id="vehFuelHidden" name="fuel_type" type="hidden">
              </div>
              <div>
                <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Color (optional)</label>
                <input name="color" maxlength="64" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., White">
              </div>
            </div>

            <div class="p-4 rounded-xl bg-white dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700">
              <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">CR Metadata (Optional)</div>
              <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1">CR Number</label>
                  <input name="cr_number" maxlength="64" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="e.g., CR-2026-000123">
                </div>
                <div>
                  <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1">CR Issue Date</label>
                  <input name="cr_issue_date" type="date" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
                </div>
                <div class="sm:col-span-2">
                  <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1">Registered Owner</label>
                  <input name="registered_owner" maxlength="150" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="Name as it appears on CR">
                </div>
              </div>
            </div>

            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700">
              <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Required Documents</div>
              <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1">CR (Required)</label>
                  <input name="cr" type="file" required accept=".pdf,.jpg,.jpeg,.png" class="w-full text-sm">
                  <div class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">CR missing → Cannot encode.</div>
                </div>
                <div>
                  <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1">OR (Optional)</label>
                  <input name="or" type="file" accept=".pdf,.jpg,.jpeg,.png" class="w-full text-sm" data-or-file="1">
                  <div class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">OR missing → Encoded but Inactive.</div>
                </div>
                <div class="sm:col-span-2">
                  <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1">OR Expiry Date</label>
                  <input name="or_expiry_date" type="date" class="w-full px-4 py-2.5 rounded-md bg-white dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold" data-or-expiry="1">
                  <div class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">OR expired → Operation blocked.</div>
                </div>
              </div>
            </div>

            <div id="ocrWrap" class="p-4 rounded-xl bg-white dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700">
              <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                  <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">OCR Scan (CR)</div>
                  <div class="mt-1 text-sm font-semibold text-slate-700 dark:text-slate-200">Scan the CR to auto-fill vehicle details.</div>
                </div>
                <div class="flex items-center gap-2">
                  <button type="button" id="btnScanCr" class="px-4 py-2.5 rounded-md bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 dark:hover:bg-slate-600 text-white text-sm font-bold">Scan CR & Auto-fill</button>
                </div>
              </div>
              <div id="ocrMsg" class="mt-3 text-sm font-semibold text-slate-600 dark:text-slate-300 hidden"></div>
              <div id="ocrResult" class="mt-3 hidden">
                <div class="rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 p-3">
                  <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-2">Extracted</div>
                  <div id="ocrFieldsGrid" class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs font-semibold text-slate-700 dark:text-slate-200"></div>
                  <details class="mt-3">
                    <summary class="cursor-pointer text-xs font-black text-slate-600 dark:text-slate-300">Show OCR text</summary>
                    <pre id="ocrRawPreview" class="mt-2 text-[11px] whitespace-pre-wrap break-words text-slate-600 dark:text-slate-300"></pre>
                  </details>
                </div>
              </div>
              <div id="ocrConfirmWrap" class="mt-4 hidden">
                <label class="flex items-start gap-3 p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800">
                  <input type="checkbox" id="ocrConfirm" class="mt-1 w-4 h-4" />
                  <div class="text-sm font-semibold text-amber-900 dark:text-amber-200">
                    I confirm the scanned details are correct.
                    <div class="text-xs font-medium text-amber-700 dark:text-amber-300 mt-1">Required before saving when OCR is used.</div>
                  </div>
                </label>
                <input type="hidden" name="ocr_used" value="0" id="ocrUsedInput">
                <input type="hidden" name="ocr_confirmed" value="0" id="ocrConfirmedInput">
              </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
              <button type="button" class="px-4 py-2.5 rounded-md bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 font-semibold" data-veh-cancel="1">Cancel</button>
              <button id="btnSaveVehicle" class="px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold">Save</button>
            </div>
          </form>
        `, 'Add Vehicle');

        const cancel = body.querySelector('[data-veh-cancel="1"]');
        if (cancel) cancel.addEventListener('click', closeModal);

        const form = document.getElementById('formAddVehicle');
        const btnSave = document.getElementById('btnSaveVehicle');
        if (!form || !btnSave) return;
        const plateInput = form.querySelector('input[name="plate_no"]');
        const normalizePlate = (value) => {
          const v = (value || '').toString().toUpperCase().replace(/\s+/g, '').replace(/[^A-Z0-9-]/g, '').replace(/-+/g, '-');
          const letters = v.replace(/[^A-Z]/g, '').slice(0, 3);
          const digits = v.replace(/[^0-9]/g, '').slice(0, 4);
          if (letters.length < 3) return letters + digits;
          return letters + '-' + digits;
        };
        if (plateInput) {
          plateInput.addEventListener('input', () => { plateInput.value = normalizePlate(plateInput.value); });
          plateInput.addEventListener('blur', () => { plateInput.value = normalizePlate(plateInput.value); });
        }
        const yearInput = form.querySelector('input[name="year_model"]');
        const normalizeYear = (value) => (value || '').toString().replace(/\D+/g, '').slice(0, 4);
        if (yearInput) {
          yearInput.addEventListener('input', () => { yearInput.value = normalizeYear(yearInput.value); });
          yearInput.addEventListener('blur', () => { yearInput.value = normalizeYear(yearInput.value); });
        }

        const normalizeUpperNoSpaces = (value) => (value || '').toString().toUpperCase().replace(/\s+/g, '');
        const normalizeEngine = (value) => normalizeUpperNoSpaces(value).replace(/[^A-Z0-9-]/g, '').slice(0, 20);
        const normalizeVin = (value) => normalizeUpperNoSpaces(value).replace(/[^A-HJ-NPR-Z0-9]/g, '').slice(0, 17);

        const engineInput = form.querySelector('input[name="engine_no"]');
        if (engineInput) {
          const validate = () => {
            const v = engineInput.value || '';
            if (v !== '' && !/^[A-Z0-9-]{5,20}$/.test(v)) engineInput.setCustomValidity('Engine No must be 5–20 characters (A–Z, 0–9, hyphen).');
            else engineInput.setCustomValidity('');
          };
          engineInput.addEventListener('input', () => { engineInput.value = normalizeEngine(engineInput.value); validate(); });
          engineInput.addEventListener('blur', () => { engineInput.value = normalizeEngine(engineInput.value); validate(); });
        }
        const vinInput = form.querySelector('input[name="chassis_no"]');
        if (vinInput) {
          const validate = () => {
            const v = vinInput.value || '';
            if (v !== '' && !/^[A-HJ-NPR-Z0-9]{17}$/.test(v)) vinInput.setCustomValidity('Chassis No must be a 17-character VIN (no I, O, Q).');
            else vinInput.setCustomValidity('');
          };
          vinInput.addEventListener('input', () => { vinInput.value = normalizeVin(vinInput.value); validate(); });
          vinInput.addEventListener('blur', () => { vinInput.value = normalizeVin(vinInput.value); validate(); });
        }

        const makeSelect = document.getElementById('vehMakeSelect');
        const makeOtherInput = document.getElementById('vehMakeOtherInput');
        const makeHidden = document.getElementById('vehMakeHidden');
        const makeOtherWrap = document.getElementById('vehMakeOtherWrap');
        const modelSelect = document.getElementById('vehModelSelect');
        const modelOtherInput = document.getElementById('vehModelOtherInput');
        const modelHidden = document.getElementById('vehModelHidden');
        const modelOtherWrap = document.getElementById('vehModelOtherWrap');
        const fuelSelect = document.getElementById('vehFuelSelect');
        const fuelOtherInput = document.getElementById('vehFuelOtherInput');
        const fuelHidden = document.getElementById('vehFuelHidden');
        const fuelOtherWrap = document.getElementById('vehFuelOtherWrap');

        function setWrapVisible(wrap, visible) { if (!wrap) return; wrap.classList.toggle('hidden', !visible); }

        function fillMakeOptions() {
          if (!makeSelect) return;
          makeSelect.innerHTML =
            `<option value="">Select</option>` +
            makeOptions.map((m) => `<option value="${m}">${m}</option>`).join('') +
            `<option value="__OTHER__">Other</option>`;
        }
        function fillFuelOptions() {
          if (!fuelSelect) return;
          fuelSelect.innerHTML =
            `<option value="">Select</option>` +
            fuelOptions.map((f) => `<option value="${f}">${f}</option>`).join('') +
            `<option value="__OTHER__">Other</option>`;
        }
        function fillModelOptions(makeValue) {
          if (!modelSelect) return;
          const models = modelOptionsByMake[makeValue] || [];
          modelSelect.innerHTML =
            `<option value="">Select</option>` +
            models.map((m) => `<option value="${m}">${m}</option>`).join('') +
            `<option value="__OTHER__">Other</option>`;
        }

        fillMakeOptions();
        fillFuelOptions();
        fillModelOptions('');

        if (makeSelect && makeHidden) {
          makeSelect.addEventListener('change', () => {
            const v = makeSelect.value || '';
            if (v === '__OTHER__') {
              if (makeOtherInput) makeOtherInput.value = '';
              makeHidden.value = '';
              setWrapVisible(makeOtherWrap, true);
              if (makeOtherInput) makeOtherInput.focus();
            } else {
              makeHidden.value = v;
              setWrapVisible(makeOtherWrap, false);
            }
            fillModelOptions(v);
            if (modelSelect && modelHidden) {
              modelSelect.value = '';
              modelHidden.value = '';
              if (modelOtherInput) modelOtherInput.value = '';
              setWrapVisible(modelOtherWrap, false);
            }
          });
        }
        if (modelSelect && modelHidden) {
          modelSelect.addEventListener('change', () => {
            const v = modelSelect.value || '';
            if (v === '__OTHER__') {
              if (modelOtherInput) modelOtherInput.value = '';
              modelHidden.value = '';
              setWrapVisible(modelOtherWrap, true);
              if (modelOtherInput) modelOtherInput.focus();
            } else {
              modelHidden.value = v;
              setWrapVisible(modelOtherWrap, false);
            }
          });
        }
        if (fuelSelect && fuelHidden) {
          fuelSelect.addEventListener('change', () => {
            const v = fuelSelect.value || '';
            if (v === '__OTHER__') {
              if (fuelOtherInput) fuelOtherInput.value = '';
              fuelHidden.value = '';
              setWrapVisible(fuelOtherWrap, true);
              if (fuelOtherInput) fuelOtherInput.focus();
            } else {
              fuelHidden.value = v;
              setWrapVisible(fuelOtherWrap, false);
            }
          });
        }
        if (makeOtherInput && makeHidden) {
          makeOtherInput.addEventListener('input', () => { makeHidden.value = makeOtherInput.value; });
          makeOtherInput.addEventListener('blur', () => { makeHidden.value = makeOtherInput.value; });
        }
        if (modelOtherInput && modelHidden) {
          modelOtherInput.addEventListener('input', () => { modelHidden.value = modelOtherInput.value; });
          modelOtherInput.addEventListener('blur', () => { modelHidden.value = modelOtherInput.value; });
        }
        if (fuelOtherInput && fuelHidden) {
          fuelOtherInput.addEventListener('input', () => { fuelHidden.value = fuelOtherInput.value; });
          fuelOtherInput.addEventListener('blur', () => { fuelHidden.value = fuelOtherInput.value; });
        }

        const orFileInput = form.querySelector('[data-or-file="1"]');
        const orExpiryInput = form.querySelector('[data-or-expiry="1"]');
        const syncOrExpiryRequired = () => {
          const hasOr = !!(orFileInput && orFileInput.files && orFileInput.files.length > 0);
          if (orExpiryInput) {
            orExpiryInput.required = hasOr;
            if (!hasOr) orExpiryInput.setCustomValidity('');
          }
        };
        if (orFileInput) orFileInput.addEventListener('change', syncOrExpiryRequired);
        if (orExpiryInput) orExpiryInput.addEventListener('change', syncOrExpiryRequired);
        syncOrExpiryRequired();

        const crFileInput = form.querySelector('input[name="cr"]');
        const btnScanCr = document.getElementById('btnScanCr');
        const ocrMsg = document.getElementById('ocrMsg');
        const ocrResult = document.getElementById('ocrResult');
        const ocrFieldsGrid = document.getElementById('ocrFieldsGrid');
        const ocrRawPreview = document.getElementById('ocrRawPreview');
        const ocrConfirmWrap = document.getElementById('ocrConfirmWrap');
        const ocrConfirm = document.getElementById('ocrConfirm');
        const ocrUsedInput = document.getElementById('ocrUsedInput');
        const ocrConfirmedInput = document.getElementById('ocrConfirmedInput');

        const setOcrMsg = (text, kind) => {
          if (!ocrMsg) return;
          ocrMsg.textContent = text;
          ocrMsg.classList.remove('hidden');
          ocrMsg.className = 'mt-3 text-sm font-semibold ' + (kind === 'error' ? 'text-rose-700 dark:text-rose-300' : (kind === 'success' ? 'text-emerald-700 dark:text-emerald-300' : 'text-slate-600 dark:text-slate-300'));
        };

        const applyExtracted = (fields) => {
          if (!fields || typeof fields !== 'object') return;
          const map = {
            plate_no: 'plate_no',
            engine_no: 'engine_no',
            chassis_no: 'chassis_no',
            year_model: 'year_model',
            color: 'color',
            cr_number: 'cr_number',
            cr_issue_date: 'cr_issue_date',
            registered_owner: 'registered_owner'
          };
          Object.keys(map).forEach((k) => {
            const v = fields[k];
            if (!v) return;
            const el = form.querySelector(`[name="${map[k]}"]`);
            if (!el) return;
            el.value = String(v);
            el.classList.add('ring-2','ring-emerald-300');
            setTimeout(() => { el.classList.remove('ring-2','ring-emerald-300'); }, 1200);
          });

          const pickFromSelect = (selectEl, hiddenEl, otherWrap, otherInput, value) => {
            if (!selectEl || !hiddenEl) return;
            const raw = (value || '').toString().trim();
            if (!raw) return;
            const norm = raw.toLowerCase();
            const opts = Array.from(selectEl.options || []);
            const found = opts.find((o) => (o.value || '').toString().trim().toLowerCase() === norm);
            if (found) {
              selectEl.value = found.value;
              hiddenEl.value = found.value;
              setWrapVisible(otherWrap, false);
              if (otherInput) otherInput.value = '';
              return;
            }
            const otherOpt = opts.find((o) => (o.value || '').toString() === '__OTHER__');
            if (otherOpt) selectEl.value = '__OTHER__';
            hiddenEl.value = raw;
            setWrapVisible(otherWrap, true);
            if (otherInput) otherInput.value = raw;
          };

          if (fields.make) pickFromSelect(makeSelect, makeHidden, makeOtherWrap, makeOtherInput, fields.make);
          if (fields.model) pickFromSelect(modelSelect, modelHidden, modelOtherWrap, modelOtherInput, fields.model);
          if (fields.fuel_type) pickFromSelect(fuelSelect, fuelHidden, fuelOtherWrap, fuelOtherInput, fields.fuel_type);
        };

        const setOcrUsed = (used) => {
          if (ocrUsedInput) ocrUsedInput.value = used ? '1' : '0';
          if (ocrConfirmWrap) ocrConfirmWrap.classList.toggle('hidden', !used);
          if (ocrConfirmedInput) ocrConfirmedInput.value = '0';
          if (ocrConfirm) ocrConfirm.checked = false;
        };

        const showOcrResult = (fields, rawPreview) => {
          if (ocrResult) ocrResult.classList.remove('hidden');
          if (ocrRawPreview) ocrRawPreview.textContent = (rawPreview || '').toString();
          if (!ocrFieldsGrid) return;
          const esc = (s) => (s === null || s === undefined) ? '' : String(s).replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
          const order = [
            ['plate_no','Plate'],
            ['engine_no','Engine'],
            ['chassis_no','Chassis'],
            ['make','Make'],
            ['model','Model'],
            ['year_model','Year'],
            ['fuel_type','Fuel'],
            ['color','Color'],
            ['cr_number','CR No'],
            ['cr_issue_date','CR Date'],
            ['registered_owner','Owner']
          ];
          ocrFieldsGrid.innerHTML = order.map(([k, label]) => {
            const v = fields && fields[k] ? String(fields[k]) : '';
            const vv = v !== '' ? v : '—';
            return `<div class="flex items-center justify-between gap-2 rounded-lg bg-white dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700 px-2.5 py-2"><span class="text-slate-500 dark:text-slate-400 font-black">${esc(label)}</span><span class="text-slate-800 dark:text-white font-bold">${esc(vv)}</span></div>`;
          }).join('');
        };

        if (ocrConfirm) {
          ocrConfirm.addEventListener('change', () => {
            if (ocrConfirmedInput) ocrConfirmedInput.value = ocrConfirm.checked ? '1' : '0';
          });
        }

        if (btnScanCr) {
          btnScanCr.addEventListener('click', async () => {
            const f = crFileInput && crFileInput.files && crFileInput.files[0] ? crFileInput.files[0] : null;
            if (!f) { setOcrMsg('Select a CR file first.', 'error'); return; }
            btnScanCr.disabled = true;
            btnScanCr.textContent = 'Scanning...';
            setOcrMsg('Scanning CR and extracting fields...', 'info');
            if (ocrResult) ocrResult.classList.add('hidden');
            try {
              const fd = new FormData();
              fd.append('cr', f);
              const res = await fetch(rootUrl + '/admin/api/module1/ocr_scan_cr.php', { method: 'POST', body: fd });
              const data = await res.json().catch(() => null);
              if (!data || !data.ok) {
                const msg = (data && data.message) ? String(data.message) : 'OCR failed';
                const extra = (data && data.data && data.data.error) ? (' (' + String(data.data.error) + ')') : '';
                const rawPrev = (data && data.data && data.data.raw_text_preview) ? String(data.data.raw_text_preview) : '';
                const flds = (data && data.data && data.data.fields) ? data.data.fields : null;
                if (rawPrev || flds) showOcrResult(flds || {}, rawPrev);
                throw new Error(msg + extra);
              }
              const fields = data.data && data.data.fields ? data.data.fields : null;
              const rawPrev = data.data && data.data.raw_text_preview ? String(data.data.raw_text_preview) : '';
              showOcrResult(fields || {}, rawPrev);
              applyExtracted(fields);
              setOcrUsed(true);
              setOcrMsg('Scan complete. Review auto-filled fields and confirm before saving.', 'success');
            } catch (e) {
              setOcrUsed(false);
              setOcrMsg((e && e.message) ? String(e.message) : 'OCR failed', 'error');
            } finally {
              btnScanCr.disabled = false;
              btnScanCr.textContent = 'Scan CR & Auto-fill';
            }
          });
        }

        form.addEventListener('submit', async (e) => {
          e.preventDefault();
          if (ocrUsedInput && ocrUsedInput.value === '1' && ocrConfirmedInput && ocrConfirmedInput.value !== '1') {
            showToast('Confirm scanned details before saving.', 'error');
            if (ocrConfirmWrap) ocrConfirmWrap.classList.remove('hidden');
            return;
          }
          if (!form.checkValidity()) { form.reportValidity(); return; }
          btnSave.disabled = true;
          btnSave.textContent = 'Saving...';
          try {
            const fd = new FormData(form);
            const res = await fetch(rootUrl + '/admin/api/module1/create_vehicle.php', { method: 'POST', body: fd });
            const data = await res.json().catch(() => null);
            if (!data || !data.ok) {
              const code = (data && data.error) ? String(data.error) : 'save_failed';
              const msg = code === 'cr_required' ? 'CR is required. Upload CR to encode the vehicle.'
                : code === 'or_expiry_required' ? 'OR expiry date is required when uploading OR.'
                : (data && data.message) ? String(data.message) : code;
              throw new Error(msg);
            }
            const plate = (data.plate_number || fd.get('plate_no') || '').toString().toUpperCase().trim();
            const st = (data.status || '').toString();
            if (st === 'Active') showToast('Vehicle saved. Status: ACTIVE');
            else if (st === 'Blocked') showToast('Vehicle saved. Status: BLOCKED (OR expired)', 'error');
            else showToast('Vehicle saved. Status: INACTIVE (missing OR)');
            const params = new URLSearchParams(window.location.search || '');
            params.set('page', 'module1/submodule2');
            if (plate) params.set('highlight_plate', plate);
            window.location.search = params.toString();
          } catch (err) {
            showToast(err.message || 'Failed', 'error');
            btnSave.disabled = false;
            btnSave.textContent = 'Save';
          }
        });
      });
    }

    const highlight = document.getElementById('veh-row-highlight');
    if (highlight) {
      setTimeout(() => { highlight.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 300);
    }
  })();
</script>
