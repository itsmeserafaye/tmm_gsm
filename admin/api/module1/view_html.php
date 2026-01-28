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

$stmt = $db->prepare("SELECT v.id AS vehicle_id, v.plate_number, v.vehicle_type, v.operator_id, COALESCE(NULLIF(o.name,''), NULLIF(o.full_name,''), NULLIF(v.operator_name,''), '') AS operator_display,
                             v.engine_no, v.chassis_no, v.make, v.model, v.year_model, v.fuel_type, v.color, v.status, v.created_at
                      FROM vehicles v
                      LEFT JOIN operators o ON o.id=v.operator_id
                      WHERE v.plate_number=?");
$stmt->bind_param('s', $plate);
$stmt->execute();
$v = $stmt->get_result()->fetch_assoc();
header('Content-Type: text/html; charset=utf-8');

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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Left Column -->
        <div class="lg:col-span-2 space-y-6">
            
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
                    <h3 class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <i data-lucide="settings-2" class="w-4 h-4 text-slate-500"></i> Management
                    </h3>
                </div>
                <div class="<?php echo $cardBodyClass; ?> space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Status Form -->
                        <form id="formStatus" method="POST" action="api/module1/update_vehicle.php">
                            <input type="hidden" name="plate_number" value="<?php echo htmlspecialchars($v['plate_number']); ?>">
                            <label class="<?php echo $labelClass; ?>">Update Status</label>
                            <div class="flex gap-2">
                                <select name="status" class="<?php echo $inputClass; ?>">
                                    <option disabled>Select Status</option>
                                    <option <?php echo ($v['status']==='Unlinked'?'selected':''); ?>>Unlinked</option>
                                    <option <?php echo ($v['status']==='Linked'?'selected':''); ?>>Linked</option>
                                    <option <?php echo ($v['status']==='Active'?'selected':''); ?>>Active</option>
                                    <option <?php echo ($v['status']==='Inactive'?'selected':''); ?>>Inactive</option>
                                </select>
                                <button class="<?php echo $btnClass; ?>">Save</button>
                            </div>
                        </form>

                        <!-- Type Form -->
                        <form id="formType" method="POST" action="api/module1/update_vehicle.php">
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
                        <form id="formDetails" class="space-y-4" method="POST" action="api/module1/update_vehicle.php" novalidate>
                            <input type="hidden" name="plate_number" value="<?php echo htmlspecialchars($v['plate_number']); ?>">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="<?php echo $labelClass; ?>">Engine No</label>
                                    <input name="engine_no" minlength="5" maxlength="20" pattern="^[A-Z0-9\\-]{5,20}$" autocapitalize="characters" data-tmm-uppercase="1" data-tmm-filter="engine" class="<?php echo $inputClass; ?>" value="<?php echo htmlspecialchars((string)($v['engine_no'] ?? '')); ?>" placeholder="e.g., 1NZFE-12345">
                                    <div class="mt-1 text-[10px] font-semibold text-slate-500 dark:text-slate-400">Engine number (from engine block or CR)</div>
                                </div>
                                <div>
                                    <label class="<?php echo $labelClass; ?>">Chassis No</label>
                                    <input name="chassis_no" minlength="17" maxlength="17" pattern="^[A-HJ-NPR-Z0-9]{17}$" autocapitalize="characters" data-tmm-uppercase="1" data-tmm-filter="vin" class="<?php echo $inputClass; ?>" value="<?php echo htmlspecialchars((string)($v['chassis_no'] ?? '')); ?>" placeholder="17 characters (no I, O, Q)">
                                </div>
                                <div>
                                    <label class="<?php echo $labelClass; ?>">Color</label>
                                    <input name="color" maxlength="64" class="<?php echo $inputClass; ?>" value="<?php echo htmlspecialchars((string)($v['color'] ?? '')); ?>" placeholder="e.g., White">
                                </div>
                                <div>
                                    <label class="<?php echo $labelClass; ?>">Make</label>
                                    <select id="vehEditMakeSelect" class="<?php echo $inputClass; ?>"></select>
                                    <div id="vehEditMakeOtherWrap" class="hidden mt-2">
                                        <input id="vehEditMakeOtherInput" maxlength="100" class="<?php echo $inputClass; ?>" placeholder="Type make">
                                    </div>
                                    <input id="vehEditMakeHidden" name="make" type="hidden" value="<?php echo htmlspecialchars((string)($v['make'] ?? '')); ?>">
                                </div>
                                <div>
                                    <label class="<?php echo $labelClass; ?>">Model</label>
                                    <select id="vehEditModelSelect" class="<?php echo $inputClass; ?>"></select>
                                    <div id="vehEditModelOtherWrap" class="hidden mt-2">
                                        <input id="vehEditModelOtherInput" maxlength="100" class="<?php echo $inputClass; ?>" placeholder="Type model">
                                    </div>
                                    <input id="vehEditModelHidden" name="model" type="hidden" value="<?php echo htmlspecialchars((string)($v['model'] ?? '')); ?>">
                                </div>
                                <div>
                                    <label class="<?php echo $labelClass; ?>">Year Model</label>
                                    <input name="year_model" type="tel" inputmode="numeric" minlength="4" maxlength="4" pattern="^[0-9]{4}$" class="<?php echo $inputClass; ?>" value="<?php echo htmlspecialchars((string)($v['year_model'] ?? '')); ?>" placeholder="e.g., 2023">
                                </div>
                                <div>
                                    <label class="<?php echo $labelClass; ?>">Fuel Type</label>
                                    <select id="vehEditFuelSelect" class="<?php echo $inputClass; ?>"></select>
                                    <div id="vehEditFuelOtherWrap" class="hidden mt-2">
                                        <input id="vehEditFuelOtherInput" maxlength="64" class="<?php echo $inputClass; ?>" placeholder="Type fuel type">
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
                            <div>
                                <input name="operator_id" inputmode="numeric" maxlength="10" pattern="^[0-9]{1,10}$" data-tmm-numeric-only="1" class="<?php echo $inputClass; ?>" placeholder="Operator ID (preferred)">
                            </div>
                            <div>
                                <input name="operator_name" class="<?php echo $inputClass; ?>" placeholder="Operator Name (fallback)" value="<?php echo htmlspecialchars($v['operator_display'] ?? ''); ?>">
                            </div>
                            <div>
                                <button class="<?php echo $btnClass; ?> w-full">Link Operator</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>

        <!-- Right Column -->
        <div class="space-y-6">
            
            <!-- Documents Card -->
            <div class="<?php echo $cardClass; ?> h-full flex flex-col">
                <div class="<?php echo $cardHeaderClass; ?>">
                    <h3 class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <i data-lucide="file-text" class="w-4 h-4 text-amber-500"></i> Documents
                    </h3>
                    <span class="text-xs font-medium px-2 py-1 rounded-md bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                        <?php
                        $stmtCount = $db->prepare("SELECT COUNT(*) as c FROM vehicle_documents WHERE vehicle_id=?");
                        $stmtCount->bind_param('i', $v['vehicle_id']);
                        $stmtCount->execute();
                        echo $stmtCount->get_result()->fetch_assoc()['c'];
                        ?>
                    </span>
                </div>
                
                <div class="flex-grow p-4 space-y-3 overflow-y-auto max-h-[500px]">
                    <?php
                    $stmtD = $db->prepare("SELECT doc_id, doc_type, file_path, uploaded_at, is_verified FROM vehicle_documents WHERE vehicle_id=? ORDER BY uploaded_at DESC");
                    $stmtD->bind_param('i', $v['vehicle_id']);
                    $stmtD->execute();
                    $resD = $stmtD->get_result();
                    if ($resD->num_rows === 0):
                    ?>
                        <div class="flex flex-col items-center justify-center py-10 text-center">
                            <div class="p-3 rounded-full bg-slate-100 dark:bg-slate-800 mb-3">
                                <i data-lucide="file-x" class="w-6 h-6 text-slate-400"></i>
                            </div>
                            <p class="text-sm text-slate-500 dark:text-slate-400">No documents uploaded.</p>
                        </div>
                    <?php else: while ($d = $resD->fetch_assoc()): ?>
                        <div class="group flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 hover:border-blue-200 dark:hover:border-blue-800 hover:bg-blue-50/50 dark:hover:bg-blue-900/10 transition-all">
                            <div class="flex items-center gap-3">
                                <div class="p-2 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-slate-500 dark:text-slate-400 group-hover:text-blue-500 group-hover:border-blue-200 dark:group-hover:border-blue-800 transition-colors">
                                    <i data-lucide="file" class="w-4 h-4"></i>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                      <div class="text-sm font-bold text-slate-700 dark:text-slate-200 group-hover:text-blue-700 dark:group-hover:text-blue-300"><?php echo htmlspecialchars($d['doc_type']); ?></div>
                                      <?php $isV = (int)($d['is_verified'] ?? 0) === 1; ?>
                                      <span class="text-[10px] font-black px-2 py-0.5 rounded-full <?php echo $isV ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'; ?>">
                                        <?php echo $isV ? 'Verified' : 'Pending'; ?>
                                      </span>
                                    </div>
                                    <div class="text-[10px] text-slate-400"><?php echo date('M d, Y', strtotime($d['uploaded_at'])); ?></div>
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5">
                              <button type="button" class="p-2 rounded-lg text-slate-400 hover:text-emerald-600 hover:bg-white dark:hover:bg-slate-800 hover:shadow-sm transition-all" data-doc-verify="1" data-doc-id="<?php echo (int)($d['doc_id'] ?? 0); ?>" data-doc-verified="<?php echo $isV ? '1' : '0'; ?>" title="<?php echo $isV ? 'Mark Pending' : 'Mark Verified'; ?>">
                                <i data-lucide="<?php echo $isV ? 'rotate-ccw' : 'check-circle-2'; ?>" class="w-4 h-4"></i>
                              </button>
                              <a href="<?php echo htmlspecialchars($rootUrl, ENT_QUOTES); ?>/admin/uploads/<?php echo htmlspecialchars($d['file_path']); ?>" target="_blank" class="p-2 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-white dark:hover:bg-slate-800 hover:shadow-sm transition-all" title="View Document">
                                  <i data-lucide="external-link" class="w-4 h-4"></i>
                              </a>
                            </div>
                        </div>
                    <?php endwhile; endif; ?>
                </div>

                <div class="p-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30">
                    <h4 class="text-xs font-bold text-slate-900 dark:text-white mb-3 uppercase tracking-wide">Upload New Documents</h4>
                    <form id="formUpload" class="space-y-3" method="POST" enctype="multipart/form-data" action="<?php echo htmlspecialchars($rootUrl, ENT_QUOTES); ?>/admin/api/module1/upload_docs.php">
                        <input type="hidden" name="plate_number" value="<?php echo htmlspecialchars($v['plate_number']); ?>">
                        
                        <div class="grid grid-cols-4 gap-2">
                            <div class="relative group">
                                <label class="flex flex-col items-center justify-center p-3 rounded-lg border border-dashed border-slate-300 dark:border-slate-600 hover:border-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/10 cursor-pointer transition-all h-20">
                                    <i data-lucide="file-plus" class="w-5 h-5 text-slate-400 mb-1"></i>
                                    <span class="text-[10px] font-medium text-slate-500">OR</span>
                                    <input name="or" type="file" accept=".pdf,.jpg,.jpeg,.png" class="hidden">
                                </label>
                            </div>
                            <div class="relative group">
                                <label class="flex flex-col items-center justify-center p-3 rounded-lg border border-dashed border-slate-300 dark:border-slate-600 hover:border-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/10 cursor-pointer transition-all h-20">
                                    <i data-lucide="file-plus" class="w-5 h-5 text-slate-400 mb-1"></i>
                                    <span class="text-[10px] font-medium text-slate-500">CR</span>
                                    <input name="cr" type="file" accept=".pdf,.jpg,.jpeg,.png" class="hidden">
                                </label>
                            </div>
                            <div class="relative group">
                                <label class="flex flex-col items-center justify-center p-3 rounded-lg border border-dashed border-slate-300 dark:border-slate-600 hover:border-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/10 cursor-pointer transition-all h-20">
                                    <i data-lucide="file-plus" class="w-5 h-5 text-slate-400 mb-1"></i>
                                    <span class="text-[10px] font-medium text-slate-500">Deed</span>
                                    <input name="deed" type="file" accept=".pdf,.jpg,.jpeg,.png" class="hidden">
                                </label>
                            </div>
                            <div class="relative group">
                                <label class="flex flex-col items-center justify-center p-3 rounded-lg border border-dashed border-slate-300 dark:border-slate-600 hover:border-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/10 cursor-pointer transition-all h-20">
                                    <i data-lucide="file-plus" class="w-5 h-5 text-slate-400 mb-1"></i>
                                    <span class="text-[10px] font-medium text-slate-500">Emission</span>
                                    <input name="emission" type="file" accept=".pdf,.jpg,.jpeg,.png" class="hidden">
                                </label>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wide mb-1">OR Expiry Date (required if OR uploaded)</label>
                                <input name="or_expiry_date" type="date" class="<?php echo $inputClass; ?>">
                            </div>
                        </div>

                        <button class="<?php echo $btnClass; ?> w-full flex items-center justify-center gap-2">
                            <i data-lucide="upload-cloud" class="w-4 h-4"></i> Upload Selected Files
                        </button>
                    </form>
                    
                    <?php if (getenv('TMM_AV_SCANNER')): ?>
                        <p class="mt-2 text-[10px] text-slate-400 text-center flex items-center justify-center gap-1">
                            <i data-lucide="shield-check" class="w-3 h-3"></i> Files are scanned for viruses.
                        </p>
                    <?php endif; ?>
                    
                    <div id="uploadProgress" class="w-full bg-slate-200 dark:bg-slate-700 h-1.5 rounded-full mt-3 overflow-hidden hidden">
                        <div id="uploadBar" class="h-full bg-blue-600 w-0 transition-all duration-300"></div>
                    </div>
                    <div id="uploadMsg" class="text-xs mt-2 font-medium text-center min-h-[1.25rem]"></div>
                </div>
            </div>
        
        </div>
    </div>
</div>

<script>
(function(){
  var makeOptions = [
    'Toyota','Mitsubishi','Nissan','Isuzu','Suzuki','Hyundai','Kia','Ford','Honda','Mazda','Chevrolet',
    'Foton','Hino','Daewoo','Mercedes-Benz','BMW','Audi','Volkswagen','BYD','Geely','Chery','MG','Changan'
  ];
  var modelOptionsByMake = {
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
  var fuelOptions = ['Diesel','Gasoline','Hybrid','Electric','LPG','CNG'];

  function refresh(){
    fetch("api/module1/view_html.php?plate=<?php echo htmlspecialchars($v['plate_number']); ?>")
      .then(r=>r.text())
      .then(html=>{
        var c=document.getElementById("vehicleModalBody") || document.getElementById("modalVehBody");
        if(c){c.innerHTML=html; if(window.lucide&&window.lucide.createIcons) window.lucide.createIcons();}
      });
  }

  function normalizeUpperNoSpaces(v){ return (v||"").toString().toUpperCase().replace(/\s+/g, ""); }
  function normalizeEngine(v){ return normalizeUpperNoSpaces(v).replace(/[^A-Z0-9-]/g, "").slice(0, 20); }
  function normalizeVin(v){ return normalizeUpperNoSpaces(v).replace(/[^A-HJ-NPR-Z0-9]/g, "").slice(0, 17); }

  function setupVinEngine(){
    var engine = document.querySelector('input[name="engine_no"]');
    if(engine){
      var validateE = function(){
        var v = engine.value || '';
        if(v !== '' && !/^[A-Z0-9-]{5,20}$/.test(v)) engine.setCustomValidity('Engine No must be 5–20 characters (A–Z, 0–9, hyphen).');
        else engine.setCustomValidity('');
      };
      engine.addEventListener('input', function(){ engine.value = normalizeEngine(engine.value); validateE(); });
      engine.addEventListener('blur', function(){ engine.value = normalizeEngine(engine.value); validateE(); });
      validateE();
    }
    var vin = document.querySelector('input[name="chassis_no"]');
    if(vin){
      var validateV = function(){
        var v = vin.value || '';
        if(v !== '' && !/^[A-HJ-NPR-Z0-9]{17}$/.test(v)) vin.setCustomValidity('Chassis No must be a 17-character VIN (no I, O, Q).');
        else vin.setCustomValidity('');
      };
      vin.addEventListener('input', function(){ vin.value = normalizeVin(vin.value); validateV(); });
      vin.addEventListener('blur', function(){ vin.value = normalizeVin(vin.value); validateV(); });
      validateV();
    }
  }

  function setupDropdowns(){
    var makeSelect = document.getElementById('vehEditMakeSelect');
    var makeOtherWrap = document.getElementById('vehEditMakeOtherWrap');
    var makeOtherInput = document.getElementById('vehEditMakeOtherInput');
    var makeHidden = document.getElementById('vehEditMakeHidden');

    var modelSelect = document.getElementById('vehEditModelSelect');
    var modelOtherWrap = document.getElementById('vehEditModelOtherWrap');
    var modelOtherInput = document.getElementById('vehEditModelOtherInput');
    var modelHidden = document.getElementById('vehEditModelHidden');

    var fuelSelect = document.getElementById('vehEditFuelSelect');
    var fuelOtherWrap = document.getElementById('vehEditFuelOtherWrap');
    var fuelOtherInput = document.getElementById('vehEditFuelOtherInput');
    var fuelHidden = document.getElementById('vehEditFuelHidden');

    function setWrapVisible(w, visible){ if(!w) return; if(visible) w.classList.remove('hidden'); else w.classList.add('hidden'); }

    function fillMake(){
      if(!makeSelect) return;
      var html = '<option value="">Select</option>';
      for(var i=0;i<makeOptions.length;i++){ var m=makeOptions[i]; html += '<option value="'+m+'">'+m+'</option>'; }
      html += '<option value="__OTHER__">Other</option>';
      makeSelect.innerHTML = html;
    }
    function fillFuel(){
      if(!fuelSelect) return;
      var html = '<option value="">Select</option>';
      for(var i=0;i<fuelOptions.length;i++){ var f=fuelOptions[i]; html += '<option value="'+f+'">'+f+'</option>'; }
      html += '<option value="__OTHER__">Other</option>';
      fuelSelect.innerHTML = html;
    }
    function fillModel(makeVal){
      if(!modelSelect) return;
      var models = modelOptionsByMake[makeVal] || [];
      var html = '<option value="">Select</option>';
      for(var i=0;i<models.length;i++){ var m=models[i]; html += '<option value="'+m+'">'+m+'</option>'; }
      html += '<option value="__OTHER__">Other</option>';
      modelSelect.innerHTML = html;
    }

    fillMake();
    fillFuel();
    fillModel('');

    var curMake = makeHidden ? (makeHidden.value || '') : '';
    var curModel = modelHidden ? (modelHidden.value || '') : '';
    var curFuel = fuelHidden ? (fuelHidden.value || '') : '';

    if(makeSelect && makeHidden){
      var isKnownMake = makeOptions.indexOf(curMake) !== -1;
      makeSelect.value = isKnownMake ? curMake : (curMake ? '__OTHER__' : '');
      if(!isKnownMake && curMake){
        setWrapVisible(makeOtherWrap, true);
        if(makeOtherInput) makeOtherInput.value = curMake;
      } else {
        setWrapVisible(makeOtherWrap, false);
      }
      makeHidden.value = curMake;
      fillModel(isKnownMake ? curMake : '');

      makeSelect.addEventListener('change', function(){
        var v = makeSelect.value || '';
        if(v === '__OTHER__'){
          makeHidden.value = makeOtherInput ? (makeOtherInput.value || '') : '';
          setWrapVisible(makeOtherWrap, true);
          if(makeOtherInput) makeOtherInput.focus();
          fillModel('');
        } else {
          makeHidden.value = v;
          setWrapVisible(makeOtherWrap, false);
          fillModel(v);
        }
        if(modelSelect){ modelSelect.value = ''; }
        if(modelHidden){ modelHidden.value = ''; }
        if(modelOtherInput){ modelOtherInput.value = ''; }
        setWrapVisible(modelOtherWrap, false);
      });
      if(makeOtherInput){
        makeOtherInput.addEventListener('input', function(){ makeHidden.value = makeOtherInput.value || ''; });
        makeOtherInput.addEventListener('blur', function(){ makeHidden.value = makeOtherInput.value || ''; });
      }
    }

    if(modelSelect && modelHidden){
      var knownModels = modelOptionsByMake[curMake] || [];
      var isKnownModel = knownModels.indexOf(curModel) !== -1;
      modelSelect.value = isKnownModel ? curModel : (curModel ? '__OTHER__' : '');
      if(!isKnownModel && curModel){
        setWrapVisible(modelOtherWrap, true);
        if(modelOtherInput) modelOtherInput.value = curModel;
      } else {
        setWrapVisible(modelOtherWrap, false);
      }
      modelHidden.value = curModel;
      modelSelect.addEventListener('change', function(){
        var v = modelSelect.value || '';
        if(v === '__OTHER__'){
          modelHidden.value = modelOtherInput ? (modelOtherInput.value || '') : '';
          setWrapVisible(modelOtherWrap, true);
          if(modelOtherInput) modelOtherInput.focus();
        } else {
          modelHidden.value = v;
          setWrapVisible(modelOtherWrap, false);
        }
      });
      if(modelOtherInput){
        modelOtherInput.addEventListener('input', function(){ modelHidden.value = modelOtherInput.value || ''; });
        modelOtherInput.addEventListener('blur', function(){ modelHidden.value = modelOtherInput.value || ''; });
      }
    }

    if(fuelSelect && fuelHidden){
      var isKnownFuel = fuelOptions.indexOf(curFuel) !== -1;
      fuelSelect.value = isKnownFuel ? curFuel : (curFuel ? '__OTHER__' : '');
      if(!isKnownFuel && curFuel){
        setWrapVisible(fuelOtherWrap, true);
        if(fuelOtherInput) fuelOtherInput.value = curFuel;
      } else {
        setWrapVisible(fuelOtherWrap, false);
      }
      fuelHidden.value = curFuel;
      fuelSelect.addEventListener('change', function(){
        var v = fuelSelect.value || '';
        if(v === '__OTHER__'){
          fuelHidden.value = fuelOtherInput ? (fuelOtherInput.value || '') : '';
          setWrapVisible(fuelOtherWrap, true);
          if(fuelOtherInput) fuelOtherInput.focus();
        } else {
          fuelHidden.value = v;
          setWrapVisible(fuelOtherWrap, false);
        }
      });
      if(fuelOtherInput){
        fuelOtherInput.addEventListener('input', function(){ fuelHidden.value = fuelOtherInput.value || ''; });
        fuelOtherInput.addEventListener('blur', function(){ fuelHidden.value = fuelOtherInput.value || ''; });
      }
    }
  }

  function bind(id){
    var f=document.getElementById(id);
    if(!f) return;
    f.addEventListener("submit", function(e){
      e.preventDefault();
      if (typeof f.checkValidity === 'function' && !f.checkValidity()) {
        if (typeof f.reportValidity === 'function') f.reportValidity();
        return;
      }
      var fd=new FormData(f);
      var btn = f.querySelector("button");
      var originalContent = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<span class="animate-spin inline-block w-4 h-4 border-2 border-current border-t-transparent rounded-full"></span>';
      fetch(f.action, {method:"POST", body:fd})
        .then(()=>{ refresh(); })
        .catch(()=>{ btn.disabled=false; btn.innerHTML=originalContent; });
    });
  }
  
  // File input feedback
  document.querySelectorAll('input[type="file"]').forEach(input => {
    input.addEventListener('change', function() {
        var label = this.parentElement;
        if(this.files && this.files.length > 0) {
            label.classList.add('border-blue-500', 'bg-blue-50', 'dark:bg-blue-900/20');
            label.classList.remove('border-slate-300', 'dark:border-slate-600');
            var icon = label.querySelector('i');
            if(icon) {
                icon.setAttribute('data-lucide', 'check');
                icon.classList.remove('text-slate-400');
                icon.classList.add('text-blue-500');
                if(window.lucide&&window.lucide.createIcons) window.lucide.createIcons();
            }
        }
    });
  });

  bind("formStatus");
  bind("formType");
  bind("formAssign");
  bind("formDetails");
  bind("formLink");
  setupVinEngine();
  setupDropdowns();
  
  document.querySelectorAll('[data-doc-verify="1"]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const id = btn.getAttribute('data-doc-id') || '';
      const isV = btn.getAttribute('data-doc-verified') === '1';
      const next = isV ? 0 : 1;
      try {
        const fd = new FormData();
        fd.append('doc_id', id);
        fd.append('is_verified', String(next));
        await fetch("<?php echo htmlspecialchars($rootUrl, ENT_QUOTES); ?>/admin/api/module1/verify_vehicle_document.php", { method: "POST", body: fd });
        refresh();
      } catch (_) { }
    });
  });
  
  var fu=document.getElementById("formUpload");
  if(fu){
    fu.addEventListener("submit", function(e){
      e.preventDefault();
      var fd=new FormData(fu);
      
      // Check if files selected
      var hasFiles = false;
      for (var p of fd.entries()) { if(p[1] instanceof File && p[1].size > 0) hasFiles = true; }
      
      if(!hasFiles) {
          var msg = document.getElementById("uploadMsg");
          msg.textContent = "Please select at least one file.";
          msg.className = "text-xs mt-2 font-medium text-center text-amber-600";
          return;
      }

      var xhr=new XMLHttpRequest();
      var bar=document.getElementById("uploadBar");
      var wrap=document.getElementById("uploadProgress");
      var msg=document.getElementById("uploadMsg");
      
      wrap.classList.remove("hidden");
      bar.style.width="0%";
      msg.textContent="Uploading...";
      msg.className = "text-xs mt-2 font-medium text-center text-slate-500";
      
      xhr.upload.addEventListener("progress", function(ev){
        if(ev.lengthComputable){
          var p=Math.round((ev.loaded/ev.total)*100);
          bar.style.width=p+"%";
        }
      });
      
      xhr.onreadystatechange=function(){
        if(xhr.readyState===4){
          if(xhr.status>=200 && xhr.status<300){
            msg.textContent="Documents uploaded successfully";
            msg.className = "text-xs mt-2 font-medium text-center text-emerald-600";
            setTimeout(refresh, 500);
          } else {
            msg.textContent="Upload failed. Please try again.";
            msg.className = "text-xs mt-2 font-medium text-center text-red-600";
          }
          setTimeout(function(){
            wrap.classList.add("hidden");
            bar.style.width="0%";
          }, 2000);
        }
      };
      xhr.open("POST", fu.action);
      xhr.send(fd);
    });
  }
})();
</script>
