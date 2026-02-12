<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$types = [];
if (is_file(__DIR__ . '/../../includes/vehicle_types.php')) { require_once __DIR__ . '/../../includes/vehicle_types.php'; $types = vehicle_types(); }
$db = db();
$plate = trim($_GET['plate'] ?? '');
$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';
require_login();
if (!has_any_permission(['module1.view','module1.vehicles.write','module1.routes.write','module2.view','module4.view','module5.view'])) {
  http_response_code(403);
  echo '<div class="text-sm">Forbidden</div>';
  exit;
}

$hasCol = function (string $table, string $col) use ($db): bool {
  $t = $db->real_escape_string($table);
  $c = $db->real_escape_string($col);
  $r = $db->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
  return $r && ($r->num_rows ?? 0) > 0;
};
$ensureVehCols = function () use ($db, $hasCol): void {
  if (!$hasCol('vehicles', 'or_number')) { @$db->query("ALTER TABLE vehicles ADD COLUMN or_number VARCHAR(12) NULL"); }
  if (!$hasCol('vehicles', 'cr_number')) { @$db->query("ALTER TABLE vehicles ADD COLUMN cr_number VARCHAR(64) NULL"); }
  if (!$hasCol('vehicles', 'cr_issue_date')) { @$db->query("ALTER TABLE vehicles ADD COLUMN cr_issue_date DATE NULL"); }
  if (!$hasCol('vehicles', 'registered_owner')) { @$db->query("ALTER TABLE vehicles ADD COLUMN registered_owner VARCHAR(150) NULL"); }
};
$ensureVehCols();

$stmt = $db->prepare("SELECT v.id AS vehicle_id, v.plate_number, v.vehicle_type, v.operator_id,
                             CASE WHEN o.id IS NULL OR COALESCE(v.operator_id,0)=0 THEN 0 ELSE 1 END AS operator_exists,
                             CASE WHEN o.id IS NULL OR COALESCE(v.operator_id,0)=0 THEN '' ELSE COALESCE(NULLIF(o.name,''), NULLIF(o.full_name,''), '') END AS operator_display,
                             v.engine_no, v.chassis_no, v.make, v.model, v.year_model, v.fuel_type, v.color,
                             v.or_number, v.cr_number, v.cr_issue_date, v.registered_owner,
                             v.status, v.created_at
                      FROM vehicles v
                      LEFT JOIN operators o ON o.id=v.operator_id
                      WHERE v.plate_number=?");
header('Content-Type: text/html; charset=utf-8');
if (!$stmt) {
    echo '<div class="flex flex-col items-center justify-center p-12 text-center rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-dashed border-slate-300 dark:border-slate-700">';
    echo '  <div class="p-4 rounded-full bg-slate-100 dark:bg-slate-800 mb-4"><i data-lucide="alert-triangle" class="w-8 h-8 text-slate-400"></i></div>';
    echo '  <h3 class="text-lg font-bold text-slate-900 dark:text-white">Unable to Load Vehicle</h3>';
    echo '  <p class="text-slate-500 dark:text-slate-400 max-w-xs mt-2">A database query failed while loading this record.</p>';
    echo '</div>';
    exit;
}
$stmt->bind_param('s', $plate);
$stmt->execute();
$v = $stmt->get_result()->fetch_assoc();

if (!$v) {
    echo '<div class="flex flex-col items-center justify-center p-12 text-center rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-dashed border-slate-300 dark:border-slate-700">';
    echo '  <div class="p-4 rounded-full bg-slate-100 dark:bg-slate-800 mb-4"><i data-lucide="search-x" class="w-8 h-8 text-slate-400"></i></div>';
    echo '  <h3 class="text-lg font-bold text-slate-900 dark:text-white">Vehicle Not Found</h3>';
    echo '  <p class="text-slate-500 dark:text-slate-400 max-w-xs mt-2">The requested vehicle record could not be found.</p>';
    echo '</div>';
    exit;
}

$statusClass = match($v['status']) {
    'Active' => 'bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20',
    'Linked' => 'bg-blue-100 text-blue-700 border-blue-200 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500/20',
    'Unlinked' => 'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/20',
    'Blocked' => 'bg-rose-100 text-rose-700 border-rose-200 dark:bg-rose-500/10 dark:text-rose-400 dark:border-rose-500/20',
    'Inactive' => 'bg-rose-100 text-rose-700 border-rose-200 dark:bg-rose-500/10 dark:text-rose-400 dark:border-rose-500/20',
    default => 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-500/10 dark:text-slate-400 dark:border-slate-500/20'
};

// Common Styles
$cardClass = "overflow-hidden rounded-2xl bg-white dark:bg-slate-900 shadow-sm border border-slate-200 dark:border-slate-700";
$cardHeaderClass = "px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-800/50";
$cardBodyClass = "p-6";
$inputClass = "block w-full rounded-lg bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 py-2 px-3 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 sm:text-sm transition-all";
$btnClass = "rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 transition-all active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed";
$labelClass = "block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1 uppercase tracking-wide";

?>

<div class="space-y-6 animate-in fade-in zoom-in-95 duration-300">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 bg-white dark:bg-slate-900 p-6 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700">
        <div class="flex items-start gap-5">
            <div class="p-4 rounded-xl bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 ring-1 ring-blue-100 dark:ring-blue-800/30">
                <i data-lucide="bus" class="w-8 h-8"></i>
            </div>
            <div>
                <div class="flex items-center gap-3">
                    <h2 class="text-3xl font-black text-slate-900 dark:text-white tracking-tight"><?php echo htmlspecialchars($v['plate_number']); ?></h2>
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold border <?php echo $statusClass; ?>">
                        <?php echo htmlspecialchars($v['status']); ?>
                    </span>
                </div>
                <?php $vs = (string)($v['status'] ?? ''); ?>
                <?php if ($vs === 'Blocked'): ?>
                    <div class="mt-2 inline-flex items-center gap-2 rounded-xl bg-rose-50 text-rose-700 border border-rose-200 px-3 py-1.5 text-xs font-bold">
                        <i data-lucide="octagon-alert" class="w-4 h-4"></i>
                        Operation blocked (OR expired)
                    </div>
                <?php elseif ($vs === 'Inactive'): ?>
                    <div class="mt-2 inline-flex items-center gap-2 rounded-xl bg-amber-50 text-amber-800 border border-amber-200 px-3 py-1.5 text-xs font-bold">
                        <i data-lucide="triangle-alert" class="w-4 h-4"></i>
                        Inactive (missing OR)
                    </div>
                <?php endif; ?>
                <div class="text-base font-medium text-slate-500 dark:text-slate-400 mt-1 flex items-center gap-2">
                    <i data-lucide="user" class="w-4 h-4"></i>
                    <?php echo htmlspecialchars($v['operator_display'] !== '' ? $v['operator_display'] : 'Unlinked'); ?>
                </div>
                <div class="text-xs text-slate-400 mt-2 flex items-center gap-2">
                    <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
                    Registered on <?php echo date('F d, Y', strtotime($v['created_at'])); ?>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6">
        
        <!-- Left Column -->
        <div class="space-y-6">
            
            <!-- Details Card -->
            <div class="<?php echo $cardClass; ?>">
                <div class="<?php echo $cardHeaderClass; ?>">
                    <h3 class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <i data-lucide="info" class="w-4 h-4 text-blue-500"></i> Vehicle Information
                    </h3>
                </div>
                <div class="<?php echo $cardBodyClass; ?>">
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-6">
                        <div>
                            <dt class="<?php echo $labelClass; ?>">Vehicle Type</dt>
                            <dd class="text-lg font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($v['vehicle_type']); ?></dd>
                        </div>
                        <div>
                            <dt class="<?php echo $labelClass; ?>">Vehicle ID</dt>
                            <dd class="text-lg font-bold text-slate-900 dark:text-white"><?php echo (int)$v['vehicle_id']; ?></dd>
                        </div>
                        <div>
                            <dt class="<?php echo $labelClass; ?>">Engine No</dt>
                            <dd class="font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($v['engine_no'] ?? '-'); ?></dd>
                        </div>
                        <div>
                            <dt class="<?php echo $labelClass; ?>">Chassis No</dt>
                            <dd class="font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($v['chassis_no'] ?? '-'); ?></dd>
                        </div>
                        <div>
                            <dt class="<?php echo $labelClass; ?>">CR Number</dt>
                            <dd class="font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars((string)($v['cr_number'] ?? '') ?: '-'); ?></dd>
                        </div>
                        <div>
                            <dt class="<?php echo $labelClass; ?>">CR Issue Date</dt>
                            <dd class="font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars((string)($v['cr_issue_date'] ?? '') ?: '-'); ?></dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="<?php echo $labelClass; ?>">Registered Owner</dt>
                            <dd class="font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars((string)($v['registered_owner'] ?? '') ?: '-'); ?></dd>
                        </div>
                        <div>
                            <dt class="<?php echo $labelClass; ?>">Make / Model</dt>
                            <dd class="font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars(trim(($v['make'] ?? '') . ' ' . ($v['model'] ?? '')) ?: '-'); ?></dd>
                        </div>
                        <div>
                            <dt class="<?php echo $labelClass; ?>">Year / Fuel</dt>
                            <dd class="font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars(trim(($v['year_model'] ?? '') . ' ' . ($v['fuel_type'] ?? '')) ?: '-'); ?></dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Management Card -->
            <div class="<?php echo $cardClass; ?>">
                <div class="<?php echo $cardHeaderClass; ?>">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <i data-lucide="settings-2" class="w-4 h-4 text-slate-500"></i> Management
                        </h3>
                        <button type="button" id="btnVehEnableEdit" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-blue-700 hover:bg-blue-800 text-white text-xs font-bold transition-colors">
                            <i data-lucide="pencil" class="w-4 h-4"></i>
                            Enable Editing
                        </button>
                    </div>
                </div>
                <div class="<?php echo $cardBodyClass; ?> space-y-6">
                    <div id="vehEditLocked" class="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40 p-4 text-sm font-semibold text-slate-600 dark:text-slate-300">
                        Editing is disabled. Click “Enable Editing” to update this record.
                    </div>
                    <div id="vehEditWrap" class="hidden space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Status Form -->
                        <form id="formStatus" method="POST" action="<?php echo htmlspecialchars($rootUrl, ENT_QUOTES); ?>/admin/api/module1/update_vehicle.php">
                            <input type="hidden" name="plate_number" value="<?php echo htmlspecialchars($v['plate_number']); ?>">
                            <label class="<?php echo $labelClass; ?>">Update Status</label>
                            <div class="flex gap-2">
                                <select name="status" class="<?php echo $inputClass; ?>">
                                    <option disabled>Select Status</option>
                                    <?php if (!in_array((string)$v['status'], ['Active','Inactive'], true)): ?>
                                        <option selected disabled><?php echo htmlspecialchars((string)$v['status']); ?></option>
                                    <?php endif; ?>
                                    <option <?php echo ($v['status']==='Active'?'selected':''); ?>>Active</option>
                                    <option <?php echo ($v['status']==='Inactive'?'selected':''); ?>>Inactive</option>
                                </select>
                                <button class="<?php echo $btnClass; ?>">Save</button>
                            </div>
                        </form>

                        <!-- Type Form -->
                        <form id="formType" method="POST" action="<?php echo htmlspecialchars($rootUrl, ENT_QUOTES); ?>/admin/api/module1/update_vehicle.php">
                            <input type="hidden" name="plate_number" value="<?php echo htmlspecialchars($v['plate_number']); ?>">
                            <label class="<?php echo $labelClass; ?>">Update Type</label>
                            <div class="flex gap-2">
                                <select name="vehicle_type" class="<?php echo $inputClass; ?>">
                                    <option disabled>Select Type</option>
                                    <?php foreach ($types as $t): ?>
                                        <option <?php echo ($v['vehicle_type']===$t?'selected':''); ?>><?php echo htmlspecialchars($t); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="<?php echo $btnClass; ?>">Save</button>
                            </div>
                        </form>
                    </div>

                    <div id="vehUpdateDetails" class="border-t border-slate-100 dark:border-slate-800 pt-6">
                        <label class="<?php echo $labelClass; ?> mb-3">Update Details</label>
                        <form id="formDetails" class="space-y-4" method="POST" action="<?php echo htmlspecialchars($rootUrl, ENT_QUOTES); ?>/admin/api/module1/update_vehicle.php" novalidate>
                            <input type="hidden" name="plate_number" value="<?php echo htmlspecialchars($v['plate_number']); ?>">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="<?php echo $labelClass; ?>">Engine No</label>
                                    <input name="engine_no" minlength="5" maxlength="20" pattern="^[A-Z0-9\\-]{5,20}$" autocapitalize="characters" data-tmm-uppercase="1" data-tmm-filter="engine" class="<?php echo $inputClass; ?>" value="<?php echo htmlspecialchars((string)($v['engine_no'] ?? '')); ?>" placeholder="e.g., 1NZFE-12345">
                                    <div class="mt-1 text-[10px] font-semibold text-slate-500 dark:text-slate-400">Engine number (from engine block or CR)</div>
                                </div>
                                <div>
                                    <label class="<?php echo $labelClass; ?>">Chassis No</label>
                                    <input name="chassis_no" minlength="17" maxlength="17" pattern="^[A-HJ-NPR-Z0-9]{17}$" autocapitalize="characters" data-tmm-uppercase="1" data-tmm-filter="vin" class="<?php echo $inputClass; ?>" value="<?php echo htmlspecialchars((string)($v['chassis_no'] ?? '')); ?>" placeholder="e.g., NCP12345678901234">
                                </div>
                                <div>
                                    <label class="<?php echo $labelClass; ?>">OR Number</label>
                                    <input name="or_number" inputmode="numeric" minlength="6" maxlength="12" pattern="^[0-9]{6,12}$" data-tmm-filter="digits" class="<?php echo $inputClass; ?>" value="<?php echo htmlspecialchars((string)($v['or_number'] ?? '')); ?>" placeholder="e.g., 123456">
                                </div>
                                <div>
                                    <label class="<?php echo $labelClass; ?>">CR Number</label>
                                    <input name="cr_number" minlength="6" maxlength="20" pattern="^[A-Z0-9\\-]{6,20}$" autocapitalize="characters" data-tmm-uppercase="1" data-tmm-filter="alnumdash" class="<?php echo $inputClass; ?>" value="<?php echo htmlspecialchars((string)($v['cr_number'] ?? '')); ?>" placeholder="e.g., ABCD-123456">
                                </div>
                                <div>
                                    <label class="<?php echo $labelClass; ?>">CR Issue Date</label>
                                    <input name="cr_issue_date" type="date" class="<?php echo $inputClass; ?>" value="<?php echo htmlspecialchars((string)($v['cr_issue_date'] ?? '')); ?>">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="<?php echo $labelClass; ?>">Registered Owner</label>
                                    <input name="registered_owner" list="vehEditOwnerList" maxlength="120" class="<?php echo $inputClass; ?>" value="<?php echo htmlspecialchars((string)($v['registered_owner'] ?? '')); ?>" placeholder="e.g., Juan Dela Cruz">
                                    <datalist id="vehEditOwnerList"></datalist>
                                </div>
                                <div>
                                    <label class="<?php echo $labelClass; ?>">Color</label>
                                    <input name="color" maxlength="64" class="<?php echo $inputClass; ?>" value="<?php echo htmlspecialchars((string)($v['color'] ?? '')); ?>" placeholder="e.g., White">
                                </div>
                                <div>
                                    <label class="<?php echo $labelClass; ?>">Make</label>
                                    <select id="vehEditMakeSelect" class="<?php echo $inputClass; ?>"></select>
                                    <div id="vehEditMakeOtherWrap" class="hidden mt-2">
                                        <input id="vehEditMakeOtherInput" maxlength="40" class="<?php echo $inputClass; ?>" placeholder="Type make">
                                    </div>
                                    <input id="vehEditMakeHidden" name="make" type="hidden" value="<?php echo htmlspecialchars((string)($v['make'] ?? '')); ?>">
                                </div>
                                <div>
                                    <label class="<?php echo $labelClass; ?>">Model</label>
                                    <select id="vehEditModelSelect" class="<?php echo $inputClass; ?>"></select>
                                    <div id="vehEditModelOtherWrap" class="hidden mt-2">
                                        <input id="vehEditModelOtherInput" maxlength="40" class="<?php echo $inputClass; ?>" placeholder="Type model">
                                    </div>
                                    <input id="vehEditModelHidden" name="model" type="hidden" value="<?php echo htmlspecialchars((string)($v['model'] ?? '')); ?>">
                                </div>
                                <div>
                                    <label class="<?php echo $labelClass; ?>">Year Model</label>
                                    <select name="year_model" class="<?php echo $inputClass; ?>">
                                        <option value="">Select year</option>
                                        <?php $curY = (int)date('Y'); ?>
                                        <?php for ($y = $curY; $y >= 1950; $y--): ?>
                                            <option value="<?php echo $y; ?>" <?php echo ((string)($v['year_model'] ?? '') === (string)$y) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="<?php echo $labelClass; ?>">Fuel Type</label>
                                    <select id="vehEditFuelSelect" class="<?php echo $inputClass; ?>"></select>
                                    <div id="vehEditFuelOtherWrap" class="hidden mt-2">
                                        <input id="vehEditFuelOtherInput" maxlength="20" class="<?php echo $inputClass; ?>" placeholder="Type fuel type">
                                    </div>
                                    <input id="vehEditFuelHidden" name="fuel_type" type="hidden" value="<?php echo htmlspecialchars((string)($v['fuel_type'] ?? '')); ?>">
                                </div>
                            </div>
                            <button class="<?php echo $btnClass; ?>">Save Details</button>
                        </form>
                    </div>

                    <div class="border-t border-slate-100 dark:border-slate-800 pt-6">
                        <label class="<?php echo $labelClass; ?> mb-3">Link Vehicle to Operator</label>
                        <form id="formLink" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end" method="POST" action="<?php echo htmlspecialchars($rootUrl, ENT_QUOTES); ?>/admin/api/module1/link_vehicle_operator.php">
                            <input type="hidden" name="plate_number" value="<?php echo htmlspecialchars($v['plate_number']); ?>">
                            
                            <!-- Searchable Dropdown Container -->
                            <div class="md:col-span-2 relative">
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wide mb-1">Search Operator</label>
                                <div class="relative">
                                    <input type="hidden" name="operator_id" id="linkOpId" value="<?php echo ((int)($v['operator_exists'] ?? 0) === 1) ? ((int)($v['operator_id'] ?? 0) ?: '') : ''; ?>">
                                    <div class="relative">
                                        <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                                        <input type="text" id="linkOpSearch" name="operator_name" class="<?php echo $inputClass; ?> pl-9" 
                                               placeholder="Search by name or ID..." 
                                               autocomplete="off" 
                                               value="<?php echo htmlspecialchars(((int)($v['operator_exists'] ?? 0) === 1) ? ($v['operator_display'] ?? '') : ''); ?>">
                                        <button type="button" id="linkOpClear" class="absolute right-2 top-1/2 -translate-y-1/2 p-1 rounded-full hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400 <?php echo empty($v['operator_display']) ? 'hidden' : ''; ?>">
                                            <i data-lucide="x" class="w-3 h-3"></i>
                                        </button>
                                    </div>
                                    
                                    <!-- Dropdown Results -->
                                    <div id="linkOpResults" class="absolute z-50 left-0 right-0 mt-1 max-h-60 overflow-y-auto bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-200 dark:border-slate-700 hidden">
                                        <!-- Items will be injected here -->
                                    </div>
                                </div>
                                <div class="mt-1 text-[10px] text-slate-500 dark:text-slate-400" id="linkOpHint">Type to search existing operators</div>
                            </div>

                            <div>
                                <button class="<?php echo $btnClass; ?> w-full" id="btnLinkOp">Link Operator</button>
                            </div>
                        </form>
                    </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
