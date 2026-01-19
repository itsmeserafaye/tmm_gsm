<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_permission('module4.inspections.manage');
$db = db();
$scheduleId = isset($_GET['schedule_id']) ? (int)$_GET['schedule_id'] : 0;
$flashNotice = isset($_GET['notice']) ? trim($_GET['notice']) : '';
$flashError = isset($_GET['error']) ? trim($_GET['error']) : '';
$schedule = null;
$approvers = [];
$latestResult = null;
$latestItems = [];
$pickQ = trim($_GET['pick_q'] ?? '');
$pickMode = trim($_GET['pick_mode'] ?? 'ready');
$pickModeAllowed = ['ready','completed','pending_verification','pending_assignment','all'];
if (!in_array($pickMode, $pickModeAllowed, true)) {
  $pickMode = 'ready';
}
$pickSchedules = [];
$pickTotal = 0;
$photoRows = [];

if ($scheduleId > 0) {
  $stmt = $db->prepare("SELECT s.schedule_id, s.plate_number, s.scheduled_at, s.location, s.status, s.inspector_id, s.inspector_label, s.cr_verified, s.or_verified, s.inspection_type, s.requested_by, s.contact_person, s.contact_number, v.operator_name, v.franchise_id, v.inspection_status, v.inspection_cert_ref, o.name AS inspector_name, o.badge_no FROM inspection_schedules s LEFT JOIN vehicles v ON s.plate_number=v.plate_number LEFT JOIN officers o ON s.inspector_id=o.officer_id WHERE s.schedule_id=?");
  if ($stmt) {
    $stmt->bind_param('i', $scheduleId);
    $stmt->execute();
    $schedule = $stmt->get_result()->fetch_assoc() ?: null;
  }
}
$resApprovers = $db->query("SELECT officer_id, name, badge_no FROM officers WHERE active_status=1 ORDER BY name");
if ($resApprovers) {
  while ($r = $resApprovers->fetch_assoc()) {
    $approvers[] = $r;
  }
}
if ($schedule) {
  $rStmt = $db->prepare("SELECT result_id, overall_status, remarks, submitted_at FROM inspection_results WHERE schedule_id=? ORDER BY submitted_at DESC LIMIT 1");
  if ($rStmt) {
    $rStmt->bind_param('i', $scheduleId);
    $rStmt->execute();
    $latestResult = $rStmt->get_result()->fetch_assoc() ?: null;
  }
  if ($latestResult && (int)($latestResult['result_id'] ?? 0) > 0) {
    $rid = (int)$latestResult['result_id'];
    $iStmt = $db->prepare("SELECT item_code, status FROM inspection_checklist_items WHERE result_id=?");
    if ($iStmt) {
      $iStmt->bind_param('i', $rid);
      $iStmt->execute();
      $res = $iStmt->get_result();
      if ($res) {
        while ($row = $res->fetch_assoc()) {
          $code = strtoupper(trim((string)($row['item_code'] ?? '')));
          if ($code !== '') {
            $latestItems[$code] = (string)($row['status'] ?? '');
          }
        }
      }
    }
    $pStmt = $db->prepare("SELECT photo_id, file_path, uploaded_at FROM inspection_photos WHERE result_id=? ORDER BY uploaded_at DESC LIMIT 12");
    if ($pStmt) {
      $pStmt->bind_param('i', $rid);
      $pStmt->execute();
      $pres = $pStmt->get_result();
      if ($pres) {
        while ($pr = $pres->fetch_assoc()) {
          $photoRows[] = $pr;
        }
      }
    }
  }
}
function opt_sel($current, $value) {
  return strcasecmp((string)$current, (string)$value) === 0 ? 'selected' : '';
}

$pickWhereParts = [];
if ($pickQ !== '') {
  $esc = $db->real_escape_string($pickQ);
  $like = '%' . $esc . '%';
  $pickWhereParts[] = "(s.plate_number LIKE '$like' OR s.location LIKE '$like' OR s.inspector_label LIKE '$like' OR o.name LIKE '$like' OR o.badge_no LIKE '$like')";
}
if ($pickMode === 'ready') {
  $pickWhereParts[] = "s.status IN ('Scheduled','Rescheduled')";
} elseif ($pickMode === 'completed') {
  $pickWhereParts[] = "s.status='Completed'";
} elseif ($pickMode === 'pending_verification') {
  $pickWhereParts[] = "s.status='Pending Verification'";
} elseif ($pickMode === 'pending_assignment') {
  $pickWhereParts[] = "s.status='Pending Assignment'";
}
$pickWhereSql = $pickWhereParts ? (' WHERE ' . implode(' AND ', $pickWhereParts)) : '';
$sqlPick = "SELECT s.schedule_id, s.plate_number, s.scheduled_at, s.location, s.status, s.cr_verified, s.or_verified, s.inspector_label, o.name AS inspector_name, o.badge_no, v.operator_name, v.inspection_cert_ref FROM inspection_schedules s LEFT JOIN officers o ON s.inspector_id=o.officer_id LEFT JOIN vehicles v ON v.plate_number=s.plate_number" . $pickWhereSql . " ORDER BY s.scheduled_at DESC LIMIT 30";
$resPick = $db->query($sqlPick);
if ($resPick) {
  while ($row = $resPick->fetch_assoc()) {
    $pickSchedules[] = $row;
  }
  $pickTotal = count($pickSchedules);
}
?>



<div class="mx-auto max-w-7xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-8">
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between mb-2 border-b border-slate-200 dark:border-slate-700 pb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Inspection Execution</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Record checklist findings, upload evidence, and issue compliance certificates.</p>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php if ($flashError !== ''): ?>
        <div class="rounded-md border border-rose-200 bg-rose-50 p-4 dark:border-rose-900/30 dark:bg-rose-900/20">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i data-lucide="alert-circle" class="h-5 w-5 text-rose-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-semibold text-rose-800 dark:text-rose-200"><?php echo htmlspecialchars($flashError, ENT_QUOTES); ?></p>
                </div>
            </div>
        </div>
    <?php elseif ($flashNotice !== ''): ?>
        <div class="rounded-md border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-900/30 dark:bg-emerald-900/20">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i data-lucide="check-circle" class="h-5 w-5 text-emerald-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-semibold text-emerald-800 dark:text-emerald-200"><?php echo htmlspecialchars($flashNotice, ENT_QUOTES); ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column: Selection List -->
        <div class="space-y-6">
            <div class="overflow-hidden rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm h-full flex flex-col">
                <div class="p-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <i data-lucide="list-checks" class="w-5 h-5 text-slate-500 dark:text-slate-300"></i>
                            Select Schedule
                        </h2>
                        <a href="?page=module4/submodule1" class="text-sm font-semibold text-blue-700 hover:text-blue-800">Go to Scheduling</a>
                    </div>
                    
                    <form method="GET" class="space-y-3">
                        <input type="hidden" name="page" value="module4/submodule2">
                        <?php if ($scheduleId > 0): ?>
                            <input type="hidden" name="schedule_id" value="<?php echo (int)$scheduleId; ?>">
                        <?php endif; ?>
                        
                        <input name="pick_q" value="<?php echo htmlspecialchars($pickQ, ENT_QUOTES); ?>" class="block w-full rounded-md bg-white dark:bg-slate-900/50 py-2.5 px-3 text-sm font-semibold text-slate-900 dark:text-white border border-slate-200 dark:border-slate-600 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all" placeholder="Search Plate, Inspector...">
                        
                        <div class="flex gap-2">
                            <select name="pick_mode" class="block w-full rounded-md bg-white dark:bg-slate-900/50 py-2.5 px-3 text-sm font-semibold text-slate-900 dark:text-white border border-slate-200 dark:border-slate-600 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none appearance-none transition-all">
                                <option value="ready" <?php echo $pickMode === 'ready' ? 'selected' : ''; ?>>Ready for Inspection</option>
                                <option value="completed" <?php echo $pickMode === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="pending_verification" <?php echo $pickMode === 'pending_verification' ? 'selected' : ''; ?>>Pending Docs</option>
                                <option value="all" <?php echo $pickMode === 'all' ? 'selected' : ''; ?>>All Schedules</option>
                            </select>
                            <button type="submit" class="rounded-md bg-blue-700 hover:bg-blue-800 px-4 py-2 text-sm font-semibold text-white transition-colors shadow-sm">
                                <i data-lucide="search" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </form>
                </div>

                <div class="flex-1 overflow-y-auto p-2 space-y-2 custom-scrollbar max-h-[600px]">
                    <?php if ($pickSchedules): ?>
                        <?php foreach ($pickSchedules as $r): ?>
                            <?php
                                $sid = (int)($r['schedule_id'] ?? 0);
                                $isActive = $sid === $scheduleId;
                                $plate = (string)($r['plate_number'] ?? '');
                                $st = (string)($r['status'] ?? '');
                                $crOk = (int)($r['cr_verified'] ?? 0) === 1;
                                $orOk = (int)($r['or_verified'] ?? 0) === 1;
                                
                                $statusColor = 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300';
                                if ($st === 'Completed') $statusColor = 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400';
                                if ($st === 'Scheduled' || $st === 'Rescheduled') $statusColor = 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400';
                                if ($st === 'Pending Verification') $statusColor = 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400';
                            ?>
                            <a href="?page=module4/submodule2&schedule_id=<?php echo $sid; ?>" class="block group relative p-4 rounded-2xl border transition-all duration-300 <?php echo $isActive ? 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800 shadow-md ring-1 ring-blue-300 dark:ring-blue-700' : 'bg-white dark:bg-slate-800 border-transparent hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:border-slate-200 dark:hover:border-slate-600'; ?>">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <div class="font-black text-sm text-slate-800 dark:text-white"><?php echo htmlspecialchars($plate, ENT_QUOTES); ?></div>
                                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mt-0.5">#<?php echo $sid; ?></div>
                                    </div>
                                    <span class="px-2 py-1 rounded-lg text-[10px] font-bold <?php echo $statusColor; ?>"><?php echo htmlspecialchars($st, ENT_QUOTES); ?></span>
                                </div>
                                
                                <div class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-2">
                                    <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
                                    <?php echo htmlspecialchars(substr((string)($r['scheduled_at'] ?? ''), 0, 16), ENT_QUOTES); ?>
                                </div>

                                <div class="flex items-center gap-2 mt-2 pt-2 border-t border-slate-100 dark:border-slate-700/50">
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold <?php echo $crOk ? 'text-emerald-600 bg-emerald-50 dark:bg-emerald-900/20' : 'text-rose-600 bg-rose-50 dark:bg-rose-900/20'; ?>">
                                        CR <?php echo $crOk ? '✓' : '✗'; ?>
                                    </span>
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold <?php echo $orOk ? 'text-emerald-600 bg-emerald-50 dark:bg-emerald-900/20' : 'text-rose-600 bg-rose-50 dark:bg-rose-900/20'; ?>">
                                        OR <?php echo $orOk ? '✓' : '✗'; ?>
                                    </span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="h-48 flex flex-col items-center justify-center text-center p-6">
                            <i data-lucide="inbox" class="w-8 h-8 text-slate-300 mb-2"></i>
                            <p class="text-xs font-medium text-slate-500">No schedules match your filters.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column: Inspection Details -->
        <div class="lg:col-span-2 space-y-6">
            <?php if ($schedule): ?>
                <?php
                    $plateNo = (string)($schedule['plate_number'] ?? '');
                    $docsOk = ((int)($schedule['cr_verified'] ?? 0) === 1 && (int)($schedule['or_verified'] ?? 0) === 1);
                    $schStatus = (string)($schedule['status'] ?? '');
                    $canInspect = in_array($schStatus, ['Scheduled','Rescheduled','Completed'], true);
                    $certRef = (string)($schedule['inspection_cert_ref'] ?? '');
                    
                    $inspLabel = trim((string)($schedule['inspector_label'] ?? ''));
                    if ($inspLabel === '') {
                        $inspParts = [];
                        if (!empty($schedule['inspector_name'])) $inspParts[] = $schedule['inspector_name'];
                        if (!empty($schedule['badge_no'])) $inspParts[] = $schedule['badge_no'];
                        $inspLabel = implode(' - ', $inspParts);
                    }
                    
                    $qrUrlCurrent = '';
                    if ($plateNo !== '' && $certRef !== '') {
                        $qrPayloadCurrent = 'CITY-INSPECTION|' . $plateNo . '|' . $certRef;
                        $qrUrlCurrent = 'https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=' . urlencode($qrPayloadCurrent);
                    }
                ?>
                
                <!-- Info Card -->
                <div class="relative overflow-hidden rounded-3xl bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 shadow-xl shadow-slate-200/40 dark:shadow-none p-6">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-blue-400/20 to-cyan-400/20 blur-3xl rounded-full -mr-10 -mt-10 pointer-events-none"></div>
                    <div class="relative">
                        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
                            <div>
                                <h2 class="text-2xl font-black text-slate-800 dark:text-white flex items-center gap-3">
                                    <?php echo htmlspecialchars($plateNo, ENT_QUOTES); ?>
                                    <span class="px-3 py-1 rounded-xl bg-slate-100 dark:bg-slate-700 text-sm font-bold text-slate-600 dark:text-slate-300">
                                        <?php echo htmlspecialchars((string)($schedule['inspection_type'] ?? 'Annual'), ENT_QUOTES); ?>
                                    </span>
                                </h2>
                                <div class="flex items-center gap-2 mt-1 text-sm font-medium text-slate-500">
                                    <i data-lucide="user" class="w-4 h-4"></i>
                                    <?php echo htmlspecialchars($inspLabel ?: 'Unassigned', ENT_QUOTES); ?>
                                </div>
                            </div>
                            
                            <?php if (!$docsOk): ?>
                                <div class="px-4 py-2 rounded-xl bg-rose-50 border border-rose-100 text-rose-700 text-xs font-bold flex items-center gap-2">
                                    <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                                    Docs Unverified
                                    <button type="button" onclick="openVerifyModal()" class="underline hover:text-rose-800 ml-1">Verify Now</button>
                                </div>
                            <?php elseif (!$canInspect): ?>
                                <div class="px-4 py-2 rounded-xl bg-amber-50 border border-amber-100 text-amber-700 text-xs font-bold flex items-center gap-2">
                                    <i data-lucide="clock" class="w-4 h-4"></i>
                                    Status: <?php echo htmlspecialchars($schStatus, ENT_QUOTES); ?>
                                </div>
                            <?php else: ?>
                                <div class="px-4 py-2 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-700 text-xs font-bold flex items-center gap-2">
                                    <i data-lucide="check-circle" class="w-4 h-4"></i>
                                    Ready for Inspection
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                            <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-700/30">
                                <div class="font-bold text-slate-400 uppercase tracking-wider mb-1">Schedule</div>
                                <div class="font-semibold text-slate-700 dark:text-slate-200"><?php echo htmlspecialchars(substr((string)($schedule['scheduled_at'] ?? ''), 0, 16), ENT_QUOTES); ?></div>
                            </div>
                            <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-700/30">
                                <div class="font-bold text-slate-400 uppercase tracking-wider mb-1">Operator</div>
                                <div class="font-semibold text-slate-700 dark:text-slate-200 truncate"><?php echo htmlspecialchars($schedule['operator_name'] ?? '—', ENT_QUOTES); ?></div>
                            </div>
                            <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-700/30">
                                <div class="font-bold text-slate-400 uppercase tracking-wider mb-1">Franchise</div>
                                <div class="font-semibold text-slate-700 dark:text-slate-200"><?php echo htmlspecialchars($schedule['franchise_id'] ?? '—', ENT_QUOTES); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Checklist Card -->
                <div class="relative overflow-hidden rounded-3xl bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 shadow-xl shadow-slate-200/40 dark:shadow-none">
                    <div class="p-6 border-b border-slate-100 dark:border-slate-700">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-lg font-black text-slate-800 dark:text-white flex items-center gap-2">
                                <i data-lucide="clipboard-check" class="w-5 h-5 text-blue-500"></i>
                                Checklist
                            </h3>
                            <?php if ($latestResult): ?>
                                <div class="text-xs font-bold text-slate-500">
                                    Last Result: <span class="text-slate-800 dark:text-white"><?php echo htmlspecialchars($latestResult['overall_status'] ?? '', ENT_QUOTES); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="p-6">
                        <?php
                          $disabled = (!$docsOk || !$canInspect) ? 'disabled' : '';
                          $rRemarks = $latestResult ? (string)($latestResult['remarks'] ?? '') : '';
                        ?>
                        <form id="inspection-checklist-form" class="space-y-4">
                            <input type="hidden" name="schedule_id" value="<?php echo (int)$schedule['schedule_id']; ?>">
                            
                            <!-- Items Grid -->
                            <div class="space-y-3">
                                <?php
                                    $items = [
                                        'LIGHTS' => 'Lights & Horn',
                                        'BRAKES' => 'Brakes System',
                                        'EMISSION' => 'Emission & Smoke',
                                        'TIRES' => 'Tires & Wipers',
                                        'INTERIOR' => 'Interior Safety',
                                        'DOCS' => 'Plates & Stickers'
                                    ];
                                    
                                    foreach ($items as $code => $label):
                                        $cur = strtoupper((string)($latestItems[$code] ?? ''));
                                        $curVal = ($cur === 'PASS' || $cur === 'PASSED') ? 'Pass' : (($cur === 'FAIL' || $cur === 'FAILED') ? 'Fail' : '');
                                ?>
                                    <div class=
                                    "flex items-center justify-between p-3 rounded-2xl bg-slate-50 dark:bg-slate-700/30 border border-slate-100 dark:border-slate-700/50">
                                        <span class="text-sm font-bold text-slate-700 dark:text-slate-200"><?php echo $label; ?></span>
                                        <input type="hidden" name="items[<?php echo $code; ?>]" id="item-<?php echo $code; ?>" data-item-code="<?php echo $code; ?>" value="<?php echo htmlspecialchars($curVal, ENT_QUOTES); ?>">
                                        
                                        <div class="flex gap-2">
                                            <button type="button" 
                                                class="item-toggle px-4 py-2 rounded-xl text-xs font-bold transition-all transform active:scale-95 flex items-center gap-1.5 <?php echo $curVal === 'Pass' ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-500/30' : 'bg-white dark:bg-slate-800 text-slate-500 border border-slate-200 dark:border-slate-600 hover:border-emerald-500 hover:text-emerald-500'; ?>"
                                                data-item-code="<?php echo $code; ?>" 
                                                data-item-value="Pass" 
                                                <?php echo $disabled; ?>>
                                                <i data-lucide="check" class="w-3.5 h-3.5"></i> Pass
                                            </button>
                                            <button type="button" 
                                                class="item-toggle px-4 py-2 rounded-xl text-xs font-bold transition-all transform active:scale-95 flex items-center gap-1.5 <?php echo $curVal === 'Fail' ? 'bg-rose-600 text-white shadow-lg shadow-rose-500/30' : 'bg-white dark:bg-slate-800 text-slate-500 border border-slate-200 dark:border-slate-600 hover:border-rose-500 hover:text-rose-500'; ?>"
                                                data-item-code="<?php echo $code; ?>" 
                                                data-item-value="Fail" 
                                                <?php echo $disabled; ?>>
                                                <i data-lucide="x" class="w-3.5 h-3.5"></i> Fail
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="pt-4">
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Findings & Remarks</label>
                                <textarea name="remarks" class="block w-full rounded-2xl border-0 bg-slate-50 dark:bg-slate-900/50 py-3 px-4 text-sm font-medium text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-blue-500 transition-all" rows="3" placeholder="Describe defects or add notes..." <?php echo $disabled; ?>><?php echo htmlspecialchars($rRemarks, ENT_QUOTES); ?></textarea>
                            </div>
                        </form>

                        <!-- Photos -->
                        <div class="mt-8 border-t border-slate-100 dark:border-slate-700 pt-6">
                            <div class="flex items-center justify-between mb-4">
                                <h4 class="text-sm font-black text-slate-800 dark:text-white flex items-center gap-2">
                                    <i data-lucide="camera" class="w-4 h-4 text-blue-500"></i> Evidence
                                </h4>
                            </div>
                            
                            <form id="inspection-photo-form" enctype="multipart/form-data" class="mb-4">
                                <input type="hidden" name="schedule_id" value="<?php echo (int)$schedule['schedule_id']; ?>">
                                <div class="flex gap-2">
                                    <label class="cursor-pointer inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 text-xs font-bold hover:bg-slate-200 dark:hover:bg-slate-600 transition-all <?php echo $disabled ? 'opacity-50 pointer-events-none' : ''; ?>">
                                        <i data-lucide="plus" class="w-4 h-4"></i> Add Photos
                                        <input name="photos[]" type="file" multiple accept=".jpg,.jpeg,.png" class="hidden" <?php echo $disabled; ?>>
                                    </label>
                                    <button type="submit" id="btn-photo-upload" class="px-4 py-2 rounded-xl bg-blue-600 text-white text-xs font-bold hover:bg-blue-500 transition-all shadow-md shadow-blue-500/20 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                                        Upload Selected
                                    </button>
                                </div>
                                <div id="photo-upload-status" class="mt-2 text-xs font-medium text-slate-500 min-h-[1.25rem]"></div>
                            </form>

                            <?php if ($photoRows): ?>
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                    <?php foreach ($photoRows as $p): 
                                        $path = (string)($p['file_path'] ?? '');
                                        $url = $path !== '' ? (($rootUrl ?? '') . '/admin/uploads/' . ltrim($path, '/')) : '';
                                    ?>
                                        <a href="<?php echo htmlspecialchars($url, ENT_QUOTES); ?>" target="_blank" class="block group relative aspect-square rounded-2xl overflow-hidden border border-slate-200 dark:border-slate-700">
                                            <img src="<?php echo htmlspecialchars($url, ENT_QUOTES); ?>" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110" alt="Evidence">
                                            <div class="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-colors"></div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="p-6 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-dashed border-slate-300 dark:border-slate-600 text-center">
                                    <p class="text-xs text-slate-400">No photos uploaded yet</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Action Card -->
                <div class="relative overflow-hidden rounded-3xl bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 shadow-xl shadow-slate-200/40 dark:shadow-none p-6">
                    <h3 class="text-lg font-black text-slate-800 dark:text-white mb-6 flex items-center gap-2">
                        <i data-lucide="award" class="w-5 h-5 text-blue-500"></i>
                        Decision & Certificate
                    </h3>
                    
                    <?php $overallCur = $latestResult ? ($latestResult['overall_status'] ?? 'Passed') : 'Passed'; ?>
                    
                    <form id="inspection-result-form" class="space-y-4">
                        <input type="hidden" id="inspection-schedule-id" value="<?php echo (int)$schedule['schedule_id']; ?>">
                        <input type="hidden" id="inspection-plate" value="<?php echo htmlspecialchars($plateNo, ENT_QUOTES); ?>">
                        <input type="hidden" id="inspection-has-result" value="<?php echo $latestResult ? '1' : '0'; ?>">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Overall Result</label>
                                <div class="relative">
                                    <select id="inspection-overall-status" class="block w-full rounded-xl border-0 bg-slate-50 dark:bg-slate-900/50 py-3 pl-4 pr-10 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-blue-500 appearance-none transition-all" <?php echo $disabled; ?>>
                                        <option value="Passed" <?php echo opt_sel($overallCur, 'Passed'); ?>>Passed</option>
                                        <option value="Failed" <?php echo opt_sel($overallCur, 'Failed'); ?>>Failed</option>
                                        <option value="Pending" <?php echo opt_sel($overallCur, 'Pending'); ?>>Pending</option>
                                        <option value="For Reinspection" <?php echo opt_sel($overallCur, 'For Reinspection'); ?>>For Reinspection</option>
                                    </select>
                                    <i data-lucide="chevron-down" class="absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none"></i>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Approving Officer</label>
                                <input id="inspection-approver-name" class="block w-full rounded-xl border-0 bg-slate-50 dark:bg-slate-900/50 py-3 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-blue-500 transition-all" placeholder="Enter Name to Issue Cert" <?php echo $disabled; ?>>
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-3 pt-4">
                            <button type="button" id="btn-save-inspection" class="flex-1 rounded-xl bg-slate-100 dark:bg-slate-700 px-6 py-3 text-sm font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-600 transition-all disabled:opacity-50 disabled:cursor-not-allowed" <?php echo $disabled; ?>>
                                Save Draft
                            </button>
                            <button type="button" id="btn-generate-certificate" class="flex-1 rounded-xl bg-gradient-to-r from-blue-600 to-cyan-600 px-6 py-3 text-sm font-bold text-white shadow-lg shadow-blue-500/30 hover:shadow-blue-500/40 hover:from-blue-500 hover:to-cyan-500 transition-all transform active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed" <?php echo $disabled; ?>>
                                <i data-lucide="file-check" class="w-4 h-4 inline-block mr-2 -mt-0.5"></i> Issue Certificate
                            </button>
                        </div>
                        <div id="inspection-inline-message" class="text-center text-xs font-medium text-slate-500 min-h-[1.25rem]"></div>
                    </form>
                    
                    <?php if ($certRef !== ''): ?>
                        <div class="mt-6 p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-100 dark:border-emerald-900/30 flex items-center justify-between">
                            <div>
                                <div class="text-xs font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider mb-1">Active Certificate</div>
                                <div class="font-mono text-lg font-black text-emerald-800 dark:text-emerald-200"><?php echo htmlspecialchars($certRef, ENT_QUOTES); ?></div>
                            </div>
                            <?php if ($qrUrlCurrent !== ''): ?>
                                <button type="button" class="btn-show-qr p-2 bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-emerald-100 dark:border-emerald-900/50" data-qr-url="<?php echo htmlspecialchars($qrUrlCurrent, ENT_QUOTES); ?>">
                                    <i data-lucide="qr-code" class="w-6 h-6 text-emerald-600"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <div class="h-full flex flex-col items-center justify-center p-12 text-center rounded-3xl bg-slate-50 dark:bg-slate-800/50 border-2 border-dashed border-slate-200 dark:border-slate-700">
                    <div class="w-20 h-20 rounded-full bg-white dark:bg-slate-800 shadow-sm flex items-center justify-center mb-6">
                        <i data-lucide="clipboard-list" class="w-10 h-10 text-slate-300"></i>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">No Inspection Selected</h3>
                    <p class="mt-2 text-sm text-slate-500 max-w-xs">Select a schedule from the list on the left to start an inspection.</p>
                </div>
            <?php endif; ?>
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
        <button type="button" id="qr-modal-close" class="w-full py-3 rounded-xl bg-slate-100 dark:bg-slate-800 text-sm font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition-all">Close</button>
    </div>
</div>

<!-- Verify Modal -->
<div id="verify-modal-overlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center z-[100] hidden transition-opacity opacity-0">
    <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-2xl p-8 w-96 transform transition-all scale-95 border border-slate-100 dark:border-slate-700">
        <h3 class="text-xl font-black text-slate-800 dark:text-white mb-4">Verify Documents</h3>
        <p class="text-sm text-slate-500 mb-6">Confirm that you have physically checked the documents.</p>
        
        <form id="verify-docs-form" class="space-y-4">
            <input type="hidden" name="schedule_id" value="<?php echo (int)$scheduleId; ?>">
            <label class="flex items-center gap-3 p-4 rounded-2xl bg-slate-50 dark:bg-slate-800 cursor-pointer border border-transparent hover:border-blue-200 transition-colors">
                <input type="checkbox" name="cr_verified" value="1" <?php echo ((int)($schedule['cr_verified']??0)===1)?'checked':''; ?> class="w-5 h-5 rounded text-blue-600 focus:ring-blue-500 border-gray-300">
                <span class="font-bold text-slate-700 dark:text-slate-200">CR Verified</span>
            </label>
            <label class="flex items-center gap-3 p-4 rounded-2xl bg-slate-50 dark:bg-slate-800 cursor-pointer border border-transparent hover:border-blue-200 transition-colors">
                <input type="checkbox" name="or_verified" value="1" <?php echo ((int)($schedule['or_verified']??0)===1)?'checked':''; ?> class="w-5 h-5 rounded text-blue-600 focus:ring-blue-500 border-gray-300">
                <span class="font-bold text-slate-700 dark:text-slate-200">OR Verified</span>
            </label>
            
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="closeVerifyModal()" class="flex-1 py-3 rounded-xl bg-slate-100 dark:bg-slate-800 text-sm font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-200 transition-colors">Cancel</button>
                <button type="submit" class="flex-1 py-3 rounded-xl bg-blue-600 text-sm font-bold text-white hover:bg-blue-500 shadow-lg shadow-blue-500/20 transition-colors">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if(window.lucide) window.lucide.createIcons();

    // Elements
    var btnSave = document.getElementById('btn-save-inspection');
    var btnCert = document.getElementById('btn-generate-certificate');
    var photoForm = document.getElementById('inspection-photo-form');
    var photoBtn = document.getElementById('btn-photo-upload');
    var photoStatus = document.getElementById('photo-upload-status');
    var scheduleInput = document.getElementById('inspection-schedule-id');
    var statusSelect = document.getElementById('inspection-overall-status');
    var shortRemarks = document.querySelector('textarea[name="remarks"]');
    var approverNameInput = document.getElementById('inspection-approver-name');
    var checklistForm = document.getElementById('inspection-checklist-form');
    var inlineMsg = document.getElementById('inspection-inline-message');
    var hasResultInput = document.getElementById('inspection-has-result');
    var plateInput = document.getElementById('inspection-plate');
    var qrModal = document.getElementById('qr-modal-overlay');
    var qrModalImage = document.getElementById('qr-modal-image');
    var qrModalClose = document.getElementById('qr-modal-close');

    // UI Helpers
    function setInline(message, tone) {
        if (!inlineMsg) return;
        inlineMsg.textContent = message || '';
        inlineMsg.className = 'text-center text-xs font-bold min-h-[1.25rem] transition-colors ' + 
            (tone === 'ok' ? 'text-emerald-600' : (tone === 'error' ? 'text-rose-600' : 'text-slate-500'));
    }

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

    // Checklist Logic
    function updateItemButtons(code, val) {
        document.querySelectorAll(`.item-toggle[data-item-code="${code}"]`).forEach(btn => {
            var btnVal = btn.dataset.itemValue;
            var isSelected = btnVal.toLowerCase() === val.toLowerCase();
            
            // Reset classes
            if (btnVal === 'Pass') {
                btn.className = `item-toggle px-4 py-2 rounded-xl text-xs font-bold transition-all transform active:scale-95 flex items-center gap-1.5 ${isSelected ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-500/30' : 'bg-white dark:bg-slate-800 text-slate-500 border border-slate-200 dark:border-slate-600 hover:border-emerald-500 hover:text-emerald-500'}`;
            } else {
                btn.className = `item-toggle px-4 py-2 rounded-xl text-xs font-bold transition-all transform active:scale-95 flex items-center gap-1.5 ${isSelected ? 'bg-rose-600 text-white shadow-lg shadow-rose-500/30' : 'bg-white dark:bg-slate-800 text-slate-500 border border-slate-200 dark:border-slate-600 hover:border-rose-500 hover:text-rose-500'}`;
            }
        });
    }

    document.querySelectorAll('.item-toggle').forEach(btn => {
        btn.addEventListener('click', function() {
            if (this.disabled) return;
            var code = this.dataset.itemCode;
            var val = this.dataset.itemValue;
            var input = document.getElementById('item-' + code);
            
            input.value = val;
            updateItemButtons(code, val);
            
            // Auto update overall status suggestion
            var allPass = Array.from(document.querySelectorAll('input[name^="items"]')).every(i => i.value === 'Pass');
            if (allPass && statusSelect) statusSelect.value = 'Passed';
            
            // Enable photo upload if all checked
            var allChecked = Array.from(document.querySelectorAll('input[name^="items"]')).every(i => i.value !== '');
            if (photoBtn) photoBtn.disabled = !allChecked;
        });
    });

    // Submission Logic
    function submitChecklist(onSuccess) {
        if (!scheduleInput || !checklistForm) return;
        var fd = new FormData(checklistForm);
        if (statusSelect) fd.set('overall_status', statusSelect.value);
        
        fetch((window.TMM_ROOT_URL || '') + '/admin/api/module4/submit_checklist.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if (data && data.ok) onSuccess(data);
                else throw new Error(data.error || 'Failed to save');
            })
            .catch(err => setInline(err.message, 'error'));
    }

    if (btnSave) {
        btnSave.addEventListener('click', () => {
            setInline('Saving...', '');
            submitChecklist(() => {
                setInline('Saved successfully!', 'ok');
                setTimeout(() => window.location.reload(), 800);
            });
        });
    }

    if (btnCert) {
        btnCert.addEventListener('click', () => {
            if (!approverNameInput.value.trim()) {
                setInline('Please enter Approving Officer name.', 'error');
                approverNameInput.focus();
                return;
            }
            setInline('Processing...', '');
            submitChecklist((data) => {
                if (data.overall_status !== 'Passed') {
                    setInline('Cannot issue certificate for failed inspection.', 'error');
                    return;
                }
                
                var fd = new FormData();
                fd.set('schedule_id', scheduleInput.value);
                fd.set('approved_name', approverNameInput.value);
                
                fetch((window.TMM_ROOT_URL || '') + '/admin/api/module4/generate_certificate.php', { method: 'POST', body: fd })
                    .then(res => res.json())
                    .then(data => {
                        if (data && data.ok) {
                            setInline('Certificate Issued!', 'ok');
                            setTimeout(() => window.location.reload(), 800);
                        } else {
                            throw new Error(data.error || 'Generation failed');
                        }
                    })
                    .catch(err => setInline(err.message, 'error'));
            });
        });
    }

    // Photo Upload
    if (photoForm && photoBtn) {
        photoForm.querySelector('input[type="file"]').addEventListener('change', function() {
            if (this.files.length > 0) {
                photoBtn.disabled = false;
                photoBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                photoBtn.textContent = `Upload ${this.files.length} Photo${this.files.length > 1 ? 's' : ''}`;
            }
        });

        photoForm.addEventListener('submit', (e) => {
            e.preventDefault();
            var statusEl = document.getElementById('photo-upload-status');
            statusEl.textContent = 'Uploading...';
            statusEl.className = 'mt-2 text-xs font-bold text-blue-500';
            photoBtn.disabled = true;

            // Ensure checklist exists first
            submitChecklist(() => {
                fetch((window.TMM_ROOT_URL || '') + '/admin/api/module4/upload_inspection_photos.php', { method: 'POST', body: new FormData(photoForm) })
                    .then(res => res.json())
                    .then(data => {
                        if (data && data.ok) {
                            statusEl.textContent = 'Uploaded!';
                            statusEl.className = 'mt-2 text-xs font-bold text-emerald-500';
                            setTimeout(() => window.location.reload(), 800);
                        } else {
                            throw new Error(data.error || 'Upload failed');
                        }
                    })
                    .catch(err => {
                        statusEl.textContent = err.message;
                        statusEl.className = 'mt-2 text-xs font-bold text-rose-500';
                        photoBtn.disabled = false;
                    });
            });
        });
    }

    // QR Modal
    document.addEventListener('click', function (e) {
        if (e.target.closest('.btn-show-qr')) {
            var btn = e.target.closest('.btn-show-qr');
            openQrModal(btn.dataset.qrUrl);
        }
        if (e.target === qrModal) closeQrModal();
    });
    if (qrModalClose) qrModalClose.addEventListener('click', closeQrModal);

    // Verify Modal Logic
    var vModal = document.getElementById('verify-modal-overlay');
    window.openVerifyModal = function() {
        if(!vModal) return;
        vModal.classList.remove('hidden');
        setTimeout(() => {
            vModal.classList.remove('opacity-0');
            vModal.querySelector('div').classList.remove('scale-95');
            vModal.querySelector('div').classList.add('scale-100');
        }, 10);
    };
    window.closeVerifyModal = function() {
        if(!vModal) return;
        vModal.classList.add('opacity-0');
        vModal.querySelector('div').classList.remove('scale-100');
        vModal.querySelector('div').classList.add('scale-95');
        setTimeout(() => vModal.classList.add('hidden'), 300);
    };
    
    var vForm = document.getElementById('verify-docs-form');
    if(vForm) {
        vForm.addEventListener('submit', function(e){
            e.preventDefault();
            var fd = new FormData(this);
            var btn = this.querySelector('button[type="submit"]');
            var oldIdx = btn.innerHTML;
            btn.disabled = true;
            btn.textContent = 'Saving...';
            
            fetch((window.TMM_ROOT_URL || '') + '/admin/api/module4/verify_docs.php', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(data => {
                    if(data && data.ok) {
                        window.location.reload();
                    } else {
                        alert(data.error || 'Failed to verify');
                        btn.disabled = false;
                        btn.innerHTML = oldIdx;
                    }
                })
                .catch(err => {
                    alert('Error: ' + err);
                    btn.disabled = false;
                    btn.innerHTML = oldIdx;
                });
        });
    }
});
</script>
