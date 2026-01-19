<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_permission('module4.inspections.manage');
$db = db();
$tmm_norm_plate = function (string $plate): string {
    $p = strtoupper(trim($plate));
    $p = preg_replace('/[^A-Z0-9]/', '', $p);
    return $p !== null ? $p : '';
};
$tmm_resolve_plate = function (mysqli $db, string $plate) use ($tmm_norm_plate): string {
    $clean = strtoupper(trim($plate));
    $norm = $tmm_norm_plate($clean);
    if ($norm === '') return $clean;
    $stmt = $db->prepare("SELECT plate_number FROM vehicles WHERE REPLACE(REPLACE(UPPER(plate_number), '-', ''), ' ', '') = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $norm);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row && isset($row['plate_number']) && (string)$row['plate_number'] !== '') return (string)$row['plate_number'];
    }
    return $clean;
};
$plateParam = trim($_GET['plate'] ?? '');
$frRefParam = trim($_GET['fr_ref'] ?? '');
$scheduleParam = isset($_GET['schedule_id']) ? (int) $_GET['schedule_id'] : 0;
$prefillTypeParam = trim($_GET['prefill_type'] ?? '');
$prefillType = '';
$allowedPrefillTypes = ['Annual', 'Reinspection', 'Compliance', 'Special'];
foreach ($allowedPrefillTypes as $t) {
    if ($prefillTypeParam !== '' && strcasecmp($prefillTypeParam, $t) === 0) {
        $prefillType = $t;
        break;
    }
}
$scheduleMessage = '';
$scheduleError = '';
$flashNotice = isset($_GET['notice']) ? trim($_GET['notice']) : '';
$flashError = isset($_GET['error']) ? trim($_GET['error']) : '';

if ($plateParam === '' && $frRefParam !== '') {
    $stmtPlate = $db->prepare("SELECT plate_number FROM vehicles WHERE franchise_id=? AND plate_number <> '' ORDER BY plate_number ASC LIMIT 1");
    if ($stmtPlate) {
        $stmtPlate->bind_param('s', $frRefParam);
        $stmtPlate->execute();
        $rowP = $stmtPlate->get_result()->fetch_assoc();
        $stmtPlate->close();
        if ($rowP && isset($rowP['plate_number']))
            $plateParam = (string) $rowP['plate_number'];
    }
}
if ($plateParam !== '') {
    $plateParam = $tmm_resolve_plate($db, $plateParam);
}

// --- Action Handling ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !has_permission('module4.inspections.manage')) {
    http_response_code(403);
    echo 'forbidden';
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_doc_verification') {
    $scheduleIdPost = isset($_POST['schedule_id']) ? (int) $_POST['schedule_id'] : 0;
    $crVerifiedPost = !empty($_POST['cr_verified']) ? 1 : 0;
    $orVerifiedPost = !empty($_POST['or_verified']) ? 1 : 0;
    if ($scheduleIdPost <= 0) {
        $scheduleError = 'Missing schedule reference.';
    } else {
        $stmtUpd = $db->prepare("UPDATE inspection_schedules SET cr_verified=?, or_verified=? WHERE schedule_id=?");
        if ($stmtUpd) {
            $stmtUpd->bind_param('iii', $crVerifiedPost, $orVerifiedPost, $scheduleIdPost);
            if ($stmtUpd->execute()) {
                $stQ = $db->prepare("SELECT inspector_id, inspector_label FROM inspection_schedules WHERE schedule_id=?");
                $inspNow = 0;
                $inspLabelNow = '';
                if ($stQ) {
                    $stQ->bind_param('i', $scheduleIdPost);
                    $stQ->execute();
                    $rNow = $stQ->get_result()->fetch_assoc();
                    $inspNow = (int) ($rNow['inspector_id'] ?? 0);
                    $inspLabelNow = trim((string) ($rNow['inspector_label'] ?? ''));
                }
                $hasVerifiedDocsNow = ($crVerifiedPost === 1 && $orVerifiedPost === 1);
                $hasInspectorNow = ($inspNow > 0 || $inspLabelNow !== '');
                if (!$hasVerifiedDocsNow) {
                    $statusNow = 'Pending Verification';
                } elseif ($hasVerifiedDocsNow && !$hasInspectorNow) {
                    $statusNow = 'Pending Assignment';
                } else {
                    $statusNow = 'Scheduled';
                }
                $stU = $db->prepare("UPDATE inspection_schedules SET status=? WHERE schedule_id=?");
                if ($stU) {
                    $stU->bind_param('si', $statusNow, $scheduleIdPost);
                    $stU->execute();
                }
                $flashNotice = 'CR/OR verification updated.';
                $scheduleParam = $scheduleIdPost;
            } else {
                $scheduleError = 'Failed to update CR/OR verification.';
            }
        } else {
            $scheduleError = 'Database error while updating verification.';
        }
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'schedule_inspection') {
    $platePost = trim($_POST['plate_number'] ?? '');
    $scheduledAtPost = trim($_POST['scheduled_at'] ?? '');
    $locationPost = trim($_POST['location'] ?? '');
    $inspectorIdPost = isset($_POST['inspector_id']) ? (int) $_POST['inspector_id'] : 0;
    $inspectorLabelPost = trim($_POST['inspector_label'] ?? '');
    $inspectionTypePost = trim($_POST['inspection_type'] ?? 'Annual');
    $requestedByPost = trim($_POST['requested_by'] ?? '');
    $contactPersonPost = trim($_POST['contact_person'] ?? '');
    $contactNumberPost = trim($_POST['contact_number'] ?? '');
    $crVerifiedPost = !empty($_POST['cr_verified']) ? 1 : 0;
    $orVerifiedPost = !empty($_POST['or_verified']) ? 1 : 0;
    if ($platePost === '' || $scheduledAtPost === '' || $locationPost === '') {
        $scheduleError = 'Plate, schedule date/time, and inspection site are required.';
    } else {
        $platePost = $tmm_resolve_plate($db, $platePost);
        if ($inspectorIdPost > 0) {
            $inspStmt = $db->prepare("SELECT officer_id, active_status FROM officers WHERE officer_id=?");
            if ($inspStmt) {
                $inspStmt->bind_param('i', $inspectorIdPost);
                $inspStmt->execute();
                $inspRow = $inspStmt->get_result()->fetch_assoc();
                if (!$inspRow || (int) ($inspRow['active_status'] ?? 0) !== 1) {
                    $scheduleError = 'Selected inspector is not active or does not exist.';
                }
            } else {
                $scheduleError = 'Database error while validating inspector.';
            }
        }
    }
    if ($scheduleError === '') {
        $allowedTypes = ['Annual', 'Reinspection', 'Compliance', 'Special'];
        if (!in_array($inspectionTypePost, $allowedTypes, true)) {
            $inspectionTypePost = 'Annual';
        }
        $hasVerifiedDocsNew = ($crVerifiedPost === 1 && $orVerifiedPost === 1);
        $hasInspectorNew = ($inspectorIdPost > 0 || $inspectorLabelPost !== '');
        if (!$hasVerifiedDocsNew) {
            $statusNew = 'Pending Verification';
        } elseif ($hasVerifiedDocsNew && !$hasInspectorNew) {
            $statusNew = 'Pending Assignment';
        } else {
            $statusNew = 'Scheduled';
        }
        $inspectorIdDbPost = $inspectorIdPost > 0 ? $inspectorIdPost : null;
        $stmtIns = $db->prepare("INSERT INTO inspection_schedules (plate_number, scheduled_at, location, inspection_type, requested_by, contact_person, contact_number, inspector_id, inspector_label, status, cr_verified, or_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmtIns) {
            $stmtIns->bind_param('sssssssissii', $platePost, $scheduledAtPost, $locationPost, $inspectionTypePost, $requestedByPost, $contactPersonPost, $contactNumberPost, $inspectorIdDbPost, $inspectorLabelPost, $statusNew, $crVerifiedPost, $orVerifiedPost);
            if ($stmtIns->execute()) {
                $scheduleIdNew = $stmtIns->insert_id;
                $notice = 'Inspection request logged for ' . $platePost . '.';
                header('Location: ?page=module4/submodule1&schedule_id=' . (int) $scheduleIdNew . '&plate=' . urlencode($platePost) . '&notice=' . urlencode($notice));
                exit;
            } else {
                $scheduleError = 'Failed to schedule inspection.';
            }
        } else {
            $scheduleError = 'Database error while scheduling inspection.';
        }
    }
}

// --- Data Fetching ---
$inspectors = [];
$resInspectors = $db->query("SELECT officer_id, name, badge_no FROM officers WHERE active_status=1 ORDER BY name");
if ($resInspectors) {
    while ($row = $resInspectors->fetch_assoc()) {
        $inspectors[] = $row;
    }
}
$vehicleInfo = null;
$orDoc = null;
$crDoc = null;
if ($plateParam !== '') {
    $stmtVeh = $db->prepare("SELECT plate_number, operator_name, franchise_id, inspection_status, inspection_cert_ref FROM vehicles WHERE plate_number=?");
    if ($stmtVeh) {
        $stmtVeh->bind_param('s', $plateParam);
        $stmtVeh->execute();
        $vehicleInfo = $stmtVeh->get_result()->fetch_assoc() ?: null;
    }
    $stmtDocs = $db->prepare("SELECT type, file_path, uploaded_at, verified FROM documents WHERE plate_number=? AND type IN ('or','cr') ORDER BY uploaded_at DESC");
    if ($stmtDocs) {
        $stmtDocs->bind_param('s', $plateParam);
        $stmtDocs->execute();
        $resDocs = $stmtDocs->get_result();
        while ($doc = $resDocs->fetch_assoc()) {
            $t = strtolower((string) ($doc['type'] ?? ''));
            if ($t === 'or' && $orDoc === null) {
                $orDoc = $doc;
            }
            if ($t === 'cr' && $crDoc === null) {
                $crDoc = $doc;
            }
        }
    }
}
$recentLocations = [];
$resLoc = $db->query("SELECT DISTINCT location FROM inspection_schedules WHERE location IS NOT NULL AND location <> '' ORDER BY location ASC LIMIT 200");
if ($resLoc) {
    while ($r = $resLoc->fetch_assoc()) {
        $val = trim((string) ($r['location'] ?? ''));
        if ($val !== '') {
            $recentLocations[] = $val;
        }
    }
}
$needsInspectionPlates = [];
$orderCol = 'application_id';
$chkSubmitted = $db->query("SHOW COLUMNS FROM franchise_applications LIKE 'submitted_at'");
if ($chkSubmitted && $chkSubmitted->num_rows > 0)
    $orderCol = 'submitted_at';
else {
    $chkCreated = $db->query("SHOW COLUMNS FROM franchise_applications LIKE 'created_at'");
    if ($chkCreated && $chkCreated->num_rows > 0)
        $orderCol = 'created_at';
}
$resNeedPlates = $db->query("SELECT DISTINCT v.plate_number, v.operator_name
                             FROM franchise_applications fa
                             JOIN vehicles v ON v.franchise_id = fa.franchise_ref_number
                             WHERE fa.status='Endorsed'
                               AND v.plate_number <> ''
                               AND (v.inspection_status IS NULL OR v.inspection_status='' OR UPPER(v.inspection_status) <> 'PASSED')
                               AND NOT EXISTS (
                                 SELECT 1 FROM inspection_schedules s
                                 WHERE s.plate_number = v.plate_number
                                   AND s.status IN ('Pending Verification','Pending Assignment','Scheduled')
                               )
                             ORDER BY fa.$orderCol DESC, v.plate_number ASC
                             LIMIT 25");
if ($resNeedPlates) {
    while ($r = $resNeedPlates->fetch_assoc()) {
        $p = strtoupper(trim((string) ($r['plate_number'] ?? '')));
        if ($p === '')
            continue;
        $needsInspectionPlates[] = [
            'plate_number' => $p,
            'operator_name' => (string) ($r['operator_name'] ?? ''),
        ];
    }
}
$selectedSchedule = null;
if ($scheduleParam > 0) {
    $stmtSch = $db->prepare("SELECT schedule_id, plate_number, scheduled_at, location, status, inspector_id, inspector_label, cr_verified, or_verified, inspection_type, requested_by, contact_person, contact_number, inspection_cert_ref FROM inspection_schedules WHERE schedule_id=?");
    if ($stmtSch) {
        $stmtSch->bind_param('i', $scheduleParam);
        $stmtSch->execute();
        $selectedSchedule = $stmtSch->get_result()->fetch_assoc() ?: null;
    }
}
$search = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$inspectorFilter = isset($_GET['insp']) ? (int) $_GET['insp'] : 0;
$inspectorFilterName = trim($_GET['insp_name'] ?? '');
$scheduledFrom = trim($_GET['scheduled_from'] ?? '');
$scheduledTo = trim($_GET['scheduled_to'] ?? '');
$page = isset($_GET['p']) ? (int) $_GET['p'] : 1;
if ($page < 1)
    $page = 1;
$pageSize = 10;

$scheduleWhereParts = [];
if ($search !== '') {
    $esc = $db->real_escape_string($search);
    $like = '%' . $esc . '%';
    $scheduleWhereParts[] = "(s.plate_number LIKE '$like' OR s.location LIKE '$like' OR s.inspector_label LIKE '$like' OR o.name LIKE '$like' OR o.badge_no LIKE '$like')";
}
$statusAllowed = ['Scheduled', 'Completed', 'Cancelled'];
if ($statusFilter !== '' && in_array($statusFilter, $statusAllowed, true)) {
    $escStatus = $db->real_escape_string($statusFilter);
    $scheduleWhereParts[] = "s.status = '$escStatus'";
}
if ($inspectorFilter > 0) {
    $scheduleWhereParts[] = "s.inspector_id = " . $inspectorFilter;
}
if ($inspectorFilterName !== '') {
    $escInsp = $db->real_escape_string($inspectorFilterName);
    $likeInsp = '%' . $escInsp . '%';
    $scheduleWhereParts[] = "(s.inspector_label LIKE '$likeInsp' OR o.name LIKE '$likeInsp' OR o.badge_no LIKE '$likeInsp')";
}
$dateFromValid = $scheduledFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $scheduledFrom);
$dateToValid = $scheduledTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $scheduledTo);
if ($dateFromValid) {
    $fromEsc = $db->real_escape_string($scheduledFrom);
    $scheduleWhereParts[] = "DATE(s.scheduled_at) >= '$fromEsc'";
}
if ($dateToValid) {
    $toEsc = $db->real_escape_string($scheduledTo);
    $scheduleWhereParts[] = "DATE(s.scheduled_at) <= '$toEsc'";
}
$scheduleWhereSql = '';
if ($scheduleWhereParts) {
    $scheduleWhereSql = ' WHERE ' . implode(' AND ', $scheduleWhereParts);
}

// Export
if (isset($_GET['export']) && $_GET['export'] === 'schedules') {
    $sqlExport = "SELECT s.schedule_id, s.plate_number, s.scheduled_at, s.location, s.status, s.inspector_label, o.name AS inspector_name, o.badge_no FROM inspection_schedules s LEFT JOIN officers o ON s.inspector_id=o.officer_id" . $scheduleWhereSql . " ORDER BY s.scheduled_at ASC LIMIT 1000";
    $resExport = $db->query($sqlExport);
    header('Content-Type: text/csv');
    $fileLabel = date('Ymd_His');
    header('Content-Disposition: attachment; filename="inspection_schedules_' . $fileLabel . '.csv"');
    echo "schedule_id,plate_number,scheduled_at,location,inspector,status\n";
    if ($resExport) {
        while ($r = $resExport->fetch_assoc()) {
            $sid = (int) ($r['schedule_id'] ?? 0);
            $plate = isset($r['plate_number']) ? str_replace('"', '""', $r['plate_number']) : '';
            $sched = isset($r['scheduled_at']) ? str_replace('"', '""', $r['scheduled_at']) : '';
            $loc = isset($r['location']) ? str_replace('"', '""', $r['location']) : '';
            $inspLabel = trim((string) ($r['inspector_label'] ?? ''));
            if ($inspLabel !== '') {
                $insp = str_replace('"', '""', $inspLabel);
            } else {
                $inspParts = [];
                if (!empty($r['inspector_name']))
                    $inspParts[] = $r['inspector_name'];
                if (!empty($r['badge_no']))
                    $inspParts[] = $r['badge_no'];
                $insp = str_replace('"', '""', implode(' - ', $inspParts));
            }
            $st = isset($r['status']) ? str_replace('"', '""', $r['status']) : '';
            echo $sid . ',"' . $plate . '","' . $sched . '","' . $loc . '","' . $insp . '","' . $st . "\"\n";
        }
    }
    exit;
}

$totalSchedules = 0;
$sqlCount = "SELECT COUNT(*) AS c FROM inspection_schedules s" . $scheduleWhereSql;
$resCount = $db->query($sqlCount);
if ($resCount && ($row = $resCount->fetch_assoc())) {
    $totalSchedules = (int) ($row['c'] ?? 0);
}
$maxPage = $totalSchedules > 0 ? (int) ceil($totalSchedules / $pageSize) : 1;
if ($page > $maxPage)
    $page = $maxPage;
$offset = ($page - 1) * $pageSize;
if ($offset < 0)
    $offset = 0;

$upcomingSchedules = [];
$sqlUpcoming = "SELECT s.schedule_id, s.plate_number, s.scheduled_at, s.location, s.status, s.inspector_label, o.name AS inspector_name, o.badge_no, v.inspection_cert_ref FROM inspection_schedules s LEFT JOIN officers o ON s.inspector_id=o.officer_id LEFT JOIN vehicles v ON v.plate_number=s.plate_number" . $scheduleWhereSql . " ORDER BY s.scheduled_at ASC LIMIT " . $offset . ", " . $pageSize;
$resUpcoming = $db->query($sqlUpcoming);
if ($resUpcoming) {
    while ($row = $resUpcoming->fetch_assoc()) {
        $upcomingSchedules[] = $row;
    }
}
?>

<div class="mx-auto max-w-7xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-8">
    <div
        class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between mb-2 border-b border-slate-200 dark:border-slate-700 pb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Inspection Scheduling</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Manage vehicle inspections, verify documents
                (OR/CR), and assign inspectors.</p>
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
                    <p class="text-sm font-semibold text-rose-800 dark:text-rose-200">
                        <?php echo htmlspecialchars($flashError, ENT_QUOTES); ?></p>
                </div>
            </div>
        </div>
    <?php elseif ($flashNotice !== ''): ?>
        <div
            class="rounded-md border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-900/30 dark:bg-emerald-900/20">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i data-lucide="check-circle" class="h-5 w-5 text-emerald-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-semibold text-emerald-800 dark:text-emerald-200">
                        <?php echo htmlspecialchars($flashNotice, ENT_QUOTES); ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column: Verification & Details -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Vehicle Status Card -->
            <div
                class="overflow-hidden rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
                <div class="relative p-6">
                    <h2 class="text-base font-bold text-slate-900 dark:text-white mb-4 flex items-center gap-2">
                        <i data-lucide="search" class="w-5 h-5 text-slate-500 dark:text-slate-300"></i>
                        Vehicle & Document Status
                    </h2>

                    <form method="GET" class="flex gap-3 mb-6">
                        <input type="hidden" name="page" value="module4/submodule1">
                        <div class="relative flex-1 group">
                            <input id="lookup-plate" name="plate"
                                value="<?php echo htmlspecialchars($plateParam, ENT_QUOTES); ?>"
                                class="block w-full rounded-md bg-white dark:bg-slate-900/50 py-2.5 px-4 text-slate-900 dark:text-white border border-slate-200 dark:border-slate-600 placeholder:text-slate-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all font-semibold text-sm uppercase"
                                placeholder="<?php echo htmlspecialchars($plateParam !== '' ? 'Search Plate (e.g. ABC1234)' : (!empty($needsInspectionPlates) ? ('Try: ' . $needsInspectionPlates[0]['plate_number'] . ' (needs inspection)') : 'Search Plate (e.g. ABC1234)'), ENT_QUOTES); ?>">
                            <div id="lookup-plate-suggestions"
                                class="absolute z-50 mt-1 w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-md shadow-xl text-xs max-h-48 overflow-y-auto hidden">
                            </div>
                        </div>
                        <button type="submit"
                            class="rounded-md bg-blue-700 hover:bg-blue-800 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors active:scale-[0.98]">
                            Lookup
                        </button>
                    </form>

                    <?php if ($vehicleInfo): ?>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                            <div
                                class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-700/30 border border-slate-100 dark:border-slate-700">
                                <div class="text-[10px] uppercase font-bold tracking-wider text-slate-400 mb-1">Plate Number
                                </div>
                                <div class="text-xl font-black text-slate-800 dark:text-white">
                                    <?php echo htmlspecialchars($vehicleInfo['plate_number'] ?? '', ENT_QUOTES); ?></div>
                            </div>
                            <div
                                class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-700/30 border border-slate-100 dark:border-slate-700">
                                <div class="text-[10px] uppercase font-bold tracking-wider text-slate-400 mb-1">Operator
                                </div>
                                <div class="font-bold text-slate-700 dark:text-slate-200 truncate">
                                    <?php echo htmlspecialchars($vehicleInfo['operator_name'] ?? '—', ENT_QUOTES); ?></div>
                            </div>
                            <div
                                class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-700/30 border border-slate-100 dark:border-slate-700">
                                <div class="text-[10px] uppercase font-bold tracking-wider text-slate-400 mb-1">Status</div>
                                <?php
                                $inspStatus = strtoupper((string) ($vehicleInfo['inspection_status'] ?? ''));
                                $badgeClass = 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400';
                                $labelText = $inspStatus !== '' ? $inspStatus : 'Not inspected';
                                if ($inspStatus === 'PASSED')
                                    $badgeClass = 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400';
                                if ($inspStatus === 'FAILED')
                                    $badgeClass = 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400';
                                ?>
                                <span
                                    class="inline-flex items-center rounded-lg px-2 py-1 text-xs font-bold <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($labelText, ENT_QUOTES); ?></span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- OR Document -->
                            <div
                                class="relative group p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:border-blue-300 dark:hover:border-blue-700 transition-all">
                                <div class="flex justify-between items-start mb-2">
                                    <div class="font-bold text-slate-700 dark:text-slate-200">OR Document</div>
                                    <?php if ($orDoc): ?>
                                        <span
                                            class="px-2 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider <?php echo ((int) ($orDoc['verified'] ?? 0) === 1) ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'; ?>">
                                            <?php echo ((int) ($orDoc['verified'] ?? 0) === 1) ? 'Verified' : 'Uploaded'; ?>
                                        </span>
                                    <?php else: ?>
                                        <span
                                            class="px-2 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider bg-rose-100 text-rose-700">Missing</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($orDoc): ?>
                                    <div class="text-xs text-slate-500 mb-3">Uploaded:
                                        <?php echo htmlspecialchars(substr((string) ($orDoc['uploaded_at'] ?? ''), 0, 16), ENT_QUOTES); ?>
                                    </div>
                                    <button type="button" onclick="openDocModal('<?php echo htmlspecialchars($rootUrl ?? '', ENT_QUOTES); ?>/admin/uploads/<?php echo rawurlencode($orDoc['file_path']); ?>', 'OR Document')" class="inline-flex items-center gap-1 text-xs font-bold text-blue-600 hover:text-blue-500 hover:underline">
                                        <i data-lucide="eye" class="w-3 h-3"></i> View File
                                    </button>
                                <?php else: ?>
                                    <div class="text-xs text-slate-400 italic">No document found</div>
                                <?php endif; ?>
                            </div>

                            <!-- CR Document -->
                            <div
                                class="relative group p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:border-blue-300 dark:hover:border-blue-700 transition-all">
                                <div class="flex justify-between items-start mb-2">
                                    <div class="font-bold text-slate-700 dark:text-slate-200">CR Document</div>
                                    <?php if ($crDoc): ?>
                                        <span
                                            class="px-2 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider <?php echo ((int) ($crDoc['verified'] ?? 0) === 1) ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'; ?>">
                                            <?php echo ((int) ($crDoc['verified'] ?? 0) === 1) ? 'Verified' : 'Uploaded'; ?>
                                        </span>
                                    <?php else: ?>
                                        <span
                                            class="px-2 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider bg-rose-100 text-rose-700">Missing</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($crDoc): ?>
                                    <div class="text-xs text-slate-500 mb-3">Uploaded:
                                        <?php echo htmlspecialchars(substr((string) ($crDoc['uploaded_at'] ?? ''), 0, 16), ENT_QUOTES); ?>
                                    </div>
                                    <button type="button" onclick="openDocModal('<?php echo htmlspecialchars($rootUrl ?? '', ENT_QUOTES); ?>/admin/uploads/<?php echo rawurlencode($crDoc['file_path']); ?>', 'CR Document')" class="inline-flex items-center gap-1 text-xs font-bold text-blue-600 hover:text-blue-500 hover:underline">
                                        <i data-lucide="eye" class="w-3 h-3"></i> View File
                                    </button>
                                <?php else: ?>
                                    <div class="text-xs text-slate-400 italic">No document found</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Upload Form if missing -->
                        <?php if (!$orDoc || !$crDoc): ?>
                            <div
                                class="mt-6 p-4 rounded-2xl bg-slate-50 dark:bg-slate-700/30 border border-dashed border-slate-300 dark:border-slate-600">
                                <h3 class="text-sm font-bold text-slate-700 dark:text-slate-200 mb-3 flex items-center gap-2">
                                    <i data-lucide="upload-cloud" class="w-4 h-4"></i> Upload Missing Documents
                                </h3>
                                <form id="doc-upload-form" class="space-y-4" enctype="multipart/form-data">
                                    <input type="hidden" name="plate_number"
                                        value="<?php echo htmlspecialchars($vehicleInfo['plate_number'] ?? '', ENT_QUOTES); ?>">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <?php if (!$orDoc): ?>
                                            <div>
                                                <label
                                                    class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">OR
                                                    File</label>
                                                <input name="or" type="file" accept=".pdf,.jpg,.jpeg,.png"
                                                    class="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition-all">
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!$crDoc): ?>
                                            <div>
                                                <label
                                                    class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">CR
                                                    File</label>
                                                <input name="cr" type="file" accept=".pdf,.jpg,.jpeg,.png"
                                                    class="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition-all">
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <div id="doc-upload-status" class="text-xs font-medium text-slate-500"></div>
                                        <button type="submit" id="btn-doc-upload"
                                            class="px-4 py-2 rounded-xl bg-slate-800 dark:bg-white text-white dark:text-slate-900 text-xs font-bold hover:bg-slate-700 dark:hover:bg-slate-100 transition-all">Upload
                                            Files</button>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>

                        <!-- Verification Controls -->
                        <?php if ($selectedSchedule && (string) ($selectedSchedule['plate_number'] ?? '') === (string) ($vehicleInfo['plate_number'] ?? '')): ?>
                            <div class="mt-6 border-t border-slate-100 dark:border-slate-700 pt-6">
                                <div class="flex items-center justify-between mb-4">
                                    <div>
                                        <h3 class="text-sm font-black text-slate-800 dark:text-white">Verify for Schedule
                                            #<?php echo (int) $selectedSchedule['schedule_id']; ?></h3>
                                        <p class="text-xs text-slate-500">
                                            <?php echo htmlspecialchars(substr((string) ($selectedSchedule['scheduled_at'] ?? ''), 0, 16), ENT_QUOTES); ?>
                                        </p>
                                    </div>
                                    <span
                                        class="px-3 py-1 rounded-xl text-xs font-bold bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                                        <?php echo htmlspecialchars((string) ($selectedSchedule['status'] ?? ''), ENT_QUOTES); ?>
                                    </span>
                                </div>
                                <form method="POST"
                                    class="flex flex-wrap items-center gap-4 bg-slate-50 dark:bg-slate-800/50 p-4 rounded-2xl">
                                    <input type="hidden" name="action" value="update_doc_verification">
                                    <input type="hidden" name="schedule_id"
                                        value="<?php echo (int) $selectedSchedule['schedule_id']; ?>">

                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="cr_verified" value="1" <?php echo ((int) ($selectedSchedule['cr_verified'] ?? 0) === 1) ? 'checked' : ''; ?>
                                            class="w-5 h-5 rounded-lg border-slate-300 text-blue-600 focus:ring-blue-500 transition-all">
                                        <span class="text-sm font-bold text-slate-700 dark:text-slate-200">CR Verified</span>
                                    </label>

                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="or_verified" value="1" <?php echo ((int) ($selectedSchedule['or_verified'] ?? 0) === 1) ? 'checked' : ''; ?>
                                            class="w-5 h-5 rounded-lg border-slate-300 text-blue-600 focus:ring-blue-500 transition-all">
                                        <span class="text-sm font-bold text-slate-700 dark:text-slate-200">OR Verified</span>
                                    </label>

                                    <div class="flex-1"></div>
                                    <button type="submit"
                                        class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold shadow-lg shadow-blue-500/20 transition-all">
                                        Update Status
                                    </button>
                                </form>
                            </div>
                        <?php else: ?>
                            <div
                                class="mt-6 p-4 rounded-2xl bg-blue-50/50 dark:bg-blue-900/10 border border-blue-100 dark:border-blue-900/30 text-center">
                                <p class="text-xs font-medium text-blue-600 dark:text-blue-400">Select an upcoming schedule from
                                    the list to manage verification.</p>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <div class="py-12 text-center">
                            <div
                                class="mx-auto h-16 w-16 rounded-full bg-slate-50 dark:bg-slate-800 flex items-center justify-center mb-4">
                                <i data-lucide="search" class="w-8 h-8 text-slate-300"></i>
                            </div>
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">No vehicle selected</h3>
                            <p class="mt-1 text-xs text-slate-500">Enter a plate number above to view details.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Schedule Form -->
            <div
                class="overflow-hidden rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
                <div class="relative p-6">
                    <h2 class="text-base font-bold text-slate-900 dark:text-white mb-6 flex items-center gap-2">
                        <i data-lucide="calendar-plus" class="w-5 h-5 text-slate-500 dark:text-slate-300"></i>
                        New Schedule
                    </h2>

                    <?php if ($scheduleError !== ''): ?>
                        <div
                            class="mb-6 p-4 rounded-md bg-rose-50 border border-rose-200 text-rose-700 text-sm font-semibold">
                            <?php echo htmlspecialchars($scheduleError, ENT_QUOTES); ?>
                        </div>
                    <?php elseif ($scheduleMessage !== ''): ?>
                        <div
                            class="mb-6 p-4 rounded-md bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm font-semibold">
                            <?php echo $scheduleMessage; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="schedule-form" class="space-y-5">
                        <input type="hidden" name="action" value="schedule_inspection">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label
                                    class="block text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-300 mb-2">Plate
                                    Number</label>
                                <div class="relative">
                                    <input id="schedule-plate" name="plate_number"
                                        value="<?php echo htmlspecialchars($plateParam, ENT_QUOTES); ?>"
                                        class="block w-full rounded-md bg-white dark:bg-slate-900/50 py-2.5 px-4 text-slate-900 dark:text-white font-semibold border border-slate-200 dark:border-slate-600 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all"
                                        placeholder="<?php echo htmlspecialchars($plateParam !== '' ? 'ABC1234' : (!empty($needsInspectionPlates) ? ('Try: ' . $needsInspectionPlates[0]['plate_number']) : 'ABC1234'), ENT_QUOTES); ?>">
                                    <div id="schedule-plate-suggestions"
                                        class="absolute z-50 mt-1 w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-md shadow-xl text-xs max-h-48 overflow-y-auto hidden">
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label
                                    class="block text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-300 mb-2">Schedule
                                    Date</label>
                                <input type="datetime-local" name="scheduled_at"
                                    class="block w-full rounded-md bg-white dark:bg-slate-900/50 py-2.5 px-4 text-slate-900 dark:text-white font-semibold border border-slate-200 dark:border-slate-600 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all">
                            </div>
                        </div>

                        <div>
                            <label
                                class="block text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-300 mb-2">Inspection
                                Site</label>
                            <div class="relative">
                                <input id="schedule-location" name="location"
                                    class="block w-full rounded-md bg-white dark:bg-slate-900/50 py-2.5 px-4 text-slate-900 dark:text-white font-semibold border border-slate-200 dark:border-slate-600 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all"
                                    placeholder="e.g. City Inspection Yard • Lane A" autocomplete="off">
                                <div id="schedule-location-suggestions"
                                    class="absolute z-50 mt-1 w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-md shadow-xl text-xs max-h-48 overflow-y-auto hidden">
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label
                                    class="block text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-300 mb-2">Type</label>
                                <div class="relative">
                                    <select name="inspection_type"
                                        class="block w-full rounded-md bg-white dark:bg-slate-900/50 py-2.5 pl-4 pr-10 text-slate-900 dark:text-white font-semibold border border-slate-200 dark:border-slate-600 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none appearance-none transition-all">
                                        <?php $selType = $prefillType !== '' ? $prefillType : 'Annual'; ?>
                                        <option value="Annual" <?php echo $selType === 'Annual' ? 'selected' : ''; ?>>
                                            Annual Inspection</option>
                                        <option value="Reinspection" <?php echo $selType === 'Reinspection' ? 'selected' : ''; ?>>Reinspection</option>
                                        <option value="Compliance" <?php echo $selType === 'Compliance' ? 'selected' : ''; ?>>Compliance Check</option>
                                        <option value="Special" <?php echo $selType === 'Special' ? 'selected' : ''; ?>>
                                            Special Inspection</option>
                                    </select>
                                    <i data-lucide="chevron-down"
                                        class="absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none"></i>
                                </div>
                            </div>
                            <div>
                                <label
                                    class="block text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-300 mb-2">Requesting
                                    Office</label>
                                <input name="requested_by"
                                    class="block w-full rounded-md bg-white dark:bg-slate-900/50 py-2.5 px-4 text-slate-900 dark:text-white font-semibold border border-slate-200 dark:border-slate-600 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all"
                                    placeholder="Optional">
                            </div>
                        </div>

                        <div>
                            <label
                                class="block text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-300 mb-2">Assigned
                                Inspector</label>
                            <div class="relative">
                                <input id="schedule-inspector" name="inspector_label"
                                    class="block w-full rounded-md bg-white dark:bg-slate-900/50 py-2.5 px-4 text-slate-900 dark:text-white font-semibold border border-slate-200 dark:border-slate-600 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all"
                                    placeholder="Search Inspector Name or Badge ID" autocomplete="off">
                                <input type="hidden" name="inspector_id" id="schedule-inspector-id" value="">
                                <div id="schedule-inspector-suggestions"
                                    class="absolute z-50 mt-1 w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-md shadow-xl text-xs max-h-48 overflow-y-auto hidden">
                                </div>
                            </div>
                            <div id="inspector-status" class="mt-2 text-xs font-medium text-slate-500 min-h-[1.25rem]">
                            </div>
                        </div>

                        <div class="pt-2 flex justify-end">
                            <button type="submit"
                                class="rounded-md bg-blue-700 hover:bg-blue-800 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors active:scale-[0.98]">
                                Create Schedule
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Column: Upcoming List -->
        <div class="space-y-6">
            <div
                class="overflow-hidden rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm h-full flex flex-col">
                <div class="p-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <i data-lucide="list-checks" class="w-5 h-5 text-slate-500 dark:text-slate-300"></i>
                            Upcoming
                        </h2>
                        <?php if ($upcomingSchedules): ?>
                            <a href="?page=module4/submodule1&q=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>&insp=<?php echo (int) $inspectorFilter; ?>&insp_name=<?php echo urlencode($inspectorFilterName); ?>&scheduled_from=<?php echo urlencode($scheduledFrom); ?>&scheduled_to=<?php echo urlencode($scheduledTo); ?>&export=schedules"
                                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-md bg-white dark:bg-slate-800 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition-colors">
                                <i data-lucide="download" class="w-3.5 h-3.5"></i> Export
                            </a>
                        <?php endif; ?>
                    </div>

                    <form method="GET" id="upcoming-filter-form" class="space-y-3">
                        <input type="hidden" name="page" value="module4/submodule1">
                        <input id="upcoming-filter-q" type="text" name="q"
                            value="<?php echo htmlspecialchars($search, ENT_QUOTES); ?>"
                            class="block w-full rounded-md bg-white dark:bg-slate-900/50 py-2.5 px-3 text-sm font-semibold text-slate-900 dark:text-white border border-slate-200 dark:border-slate-600 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all"
                            placeholder="Filter by Plate, Location...">

                        <div class="grid grid-cols-2 gap-3">
                            <select name="status"
                                class="block w-full rounded-md bg-white dark:bg-slate-900/50 py-2.5 px-3 text-sm font-semibold text-slate-900 dark:text-white border border-slate-200 dark:border-slate-600 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none appearance-none transition-all">
                                <option value="">All Status</option>
                                <option value="Scheduled" <?php echo $statusFilter === 'Scheduled' ? 'selected' : ''; ?>>
                                    Scheduled</option>
                                <option value="Completed" <?php echo $statusFilter === 'Completed' ? 'selected' : ''; ?>>
                                    Completed</option>
                                <option value="Cancelled" <?php echo $statusFilter === 'Cancelled' ? 'selected' : ''; ?>>
                                    Cancelled</option>
                            </select>
                            <input type="date" name="scheduled_from"
                                value="<?php echo htmlspecialchars($scheduledFrom, ENT_QUOTES); ?>"
                                class="block w-full rounded-md bg-white dark:bg-slate-900/50 py-2.5 px-3 text-sm font-semibold text-slate-900 dark:text-white border border-slate-200 dark:border-slate-600 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all">
                        </div>
                    </form>
                </div>

                <div class="flex-1 overflow-y-auto p-2 space-y-2 custom-scrollbar">
                    <?php if ($upcomingSchedules): ?>
                        <?php foreach ($upcomingSchedules as $row): ?>
                            <div onclick="window.location.href='?page=module4/submodule2&pick_q=<?php echo urlencode($row['plate_number'] ?? ''); ?>&pick_mode=all&schedule_id=<?php echo (int) $row['schedule_id']; ?>'"
                                class="group relative p-4 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/30 border border-slate-200 dark:border-slate-700 hover:border-blue-300 dark:hover:border-blue-500 transition-all cursor-pointer shadow-sm hover:shadow-md">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <span
                                            class="text-sm font-black text-slate-800 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                                            <?php echo htmlspecialchars($row['plate_number'] ?? '', ENT_QUOTES); ?>
                                        </span>
                                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mt-0.5">
                                            #<?php echo (int) $row['schedule_id']; ?>
                                        </div>
                                    </div>
                                    <?php
                                    $st = $row['status'] ?? '';
                                    $sc = 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400';
                                    if ($st === 'Completed')
                                        $sc = 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400';
                                    if ($st === 'Cancelled')
                                        $sc = 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300';
                                    ?>
                                    <span
                                        class="px-2 py-1 rounded-lg text-[10px] font-bold <?php echo $sc; ?>"><?php echo htmlspecialchars($st ?: 'Scheduled', ENT_QUOTES); ?></span>
                                </div>

                                <div
                                    class="flex items-center gap-2 text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">
                                    <i data-lucide="calendar" class="w-3.5 h-3.5 text-blue-500"></i>
                                    <?php echo htmlspecialchars(substr((string) ($row['scheduled_at'] ?? ''), 0, 16), ENT_QUOTES); ?>
                                </div>

                                <?php if (!empty($row['location'])): ?>
                                    <div class="flex items-center gap-2 text-xs text-slate-500 mb-2">
                                        <i data-lucide="map-pin" class="w-3.5 h-3.5"></i>
                                        <span
                                            class="truncate"><?php echo htmlspecialchars($row['location'] ?? '', ENT_QUOTES); ?></span>
                                    </div>
                                <?php endif; ?>

                                <div
                                    class="pt-2 mt-2 border-t border-slate-100 dark:border-slate-700/50 flex items-center justify-between">
                                    <div class="flex items-center gap-1.5">
                                        <div
                                            class="h-5 w-5 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center text-[10px] font-bold text-slate-600 dark:text-slate-300">
                                            <i data-lucide="user" class="w-3 h-3"></i>
                                        </div>
                                        <span
                                            class="text-xs font-medium text-slate-600 dark:text-slate-300 truncate max-w-[120px]">
                                            <?php
                                            $label = trim((string) ($row['inspector_label'] ?? ''));
                                            if ($label === '') {
                                                $inspParts = [];
                                                if (!empty($row['inspector_name']))
                                                    $inspParts[] = $row['inspector_name'];
                                                if (!empty($row['badge_no']))
                                                    $inspParts[] = $row['badge_no'];
                                                $label = implode(' - ', $inspParts);
                                            }
                                            echo htmlspecialchars($label ?: 'Unassigned', ENT_QUOTES);
                                            ?>
                                        </span>
                                    </div>

                                    <?php
                                    $plateRow = (string) ($row['plate_number'] ?? '');
                                    $certRefRow = (string) ($row['inspection_cert_ref'] ?? '');
                                    $hasQrRow = ($plateRow !== '' && $certRefRow !== '' && ($st === 'Completed'));
                                      if ($hasQrRow) {
                                        $qrPayloadRow = 'CITY-INSPECTION|' . $plateRow . '|' . $certRefRow;
                                        $qrUrlRow = 'https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=' . urlencode($qrPayloadRow);
                                        // Use click handler directly
                                        echo '<button type="button" onclick="event.stopPropagation(); window.openQrModal(\''.htmlspecialchars($qrUrlRow, ENT_QUOTES).'\')" class="btn-show-qr p-1.5 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-all"><i data-lucide="qr-code" class="w-4 h-4"></i></button>';
                                      }
                                    ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <!-- Pagination -->
                        <?php if ($maxPage > 1): ?>
                            <div class="flex items-center justify-center gap-2 pt-4">
                                <?php if ($page > 1): ?>
                                    <a href="?page=module4/submodule1&q=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>&insp=<?php echo (int) $inspectorFilter; ?>&insp_name=<?php echo urlencode($inspectorFilterName); ?>&scheduled_from=<?php echo urlencode($scheduledFrom); ?>&scheduled_to=<?php echo urlencode($scheduledTo); ?>&p=<?php echo $page - 1; ?>"
                                        class="p-2 rounded-xl bg-slate-100 dark:bg-slate-700 text-slate-600 hover:bg-slate-200 transition-all">
                                        <i data-lucide="chevron-left" class="w-4 h-4"></i>
                                    </a>
                                <?php endif; ?>
                                <span class="text-xs font-bold text-slate-500">Page <?php echo $page; ?> of
                                    <?php echo $maxPage; ?></span>
                                <?php if ($page < $maxPage): ?>
                                    <a href="?page=module4/submodule1&q=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>&insp=<?php echo (int) $inspectorFilter; ?>&insp_name=<?php echo urlencode($inspectorFilterName); ?>&scheduled_from=<?php echo urlencode($scheduledFrom); ?>&scheduled_to=<?php echo urlencode($scheduledTo); ?>&p=<?php echo $page + 1; ?>"
                                        class="p-2 rounded-md bg-slate-100 dark:bg-slate-700 text-slate-600 hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors">
                                        <i data-lucide="chevron-right" class="w-4 h-4"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <div class="h-64 flex flex-col items-center justify-center text-center p-6">
                            <div
                                class="h-16 w-16 rounded-full bg-slate-50 dark:bg-slate-800 flex items-center justify-center mb-4">
                                <i data-lucide="calendar-off" class="w-8 h-8 text-slate-300"></i>
                            </div>
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">No schedules found</h3>
                            <p class="mt-1 text-xs text-slate-500">Adjust filters or create a new schedule.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- QR Modal -->
<div id="qr-modal-overlay"
    class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center z-[100] hidden transition-opacity opacity-0">
    <div
        class="bg-white dark:bg-slate-900 rounded-2xl shadow-2xl p-6 w-80 transform transition-all scale-95 border border-slate-200 dark:border-slate-700">
        <div class="text-center mb-6">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Certificate QR</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">Scan to verify authenticity</p>
        </div>
        <div class="flex justify-center mb-6">
            <div
                class="p-4 rounded-md bg-white dark:bg-slate-900 shadow-inner border border-slate-200 dark:border-slate-700">
                <img id="qr-modal-image" src="" alt="Certificate QR" class="w-48 h-48 rounded-lg">
            </div>
        </div>
        <button type="button" id="qr-modal-close"
            class="w-full py-2.5 rounded-md bg-white dark:bg-slate-800 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition-colors">Close</button>
    </div>
</div>

<!-- Document Modal -->
<div id="doc-modal-overlay"
    class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center z-[100] hidden transition-opacity opacity-0">
    <div
        class="bg-white dark:bg-slate-900 rounded-2xl shadow-2xl p-6 w-11/12 max-w-3xl h-5/6 flex flex-col transform transition-all scale-95 border border-slate-200 dark:border-slate-700">
        <div class="flex justify-between items-center mb-4">
            <h3 id="doc-modal-title" class="text-lg font-bold text-slate-900 dark:text-white">Document Viewer</h3>
            <button type="button" id="doc-modal-close"
                class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>
        <div class="flex-1 overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700 mb-6">
            <iframe id="doc-modal-iframe" src="" frameborder="0" class="w-full h-full bg-slate-50 dark:bg-slate-800"></iframe>
        </div>
        <button type="button"
            class="w-full py-2.5 rounded-md bg-white dark:bg-slate-800 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition-colors"
            onclick="closeDocModal()">Close</button>
    </div>
</div>

<!-- Doc Viewer Modal -->
<div id="doc-modal-overlay" class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm flex items-center justify-center z-[100] hidden transition-opacity opacity-0">
    <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-2xl w-full max-w-4xl h-[90vh] flex flex-col transform transition-all scale-95 border border-slate-200 dark:border-slate-700">
        <div class="flex items-center justify-between p-4 border-b border-slate-200 dark:border-slate-700">
            <h3 id="doc-modal-title" class="text-lg font-bold text-slate-900 dark:text-white">Document Viewer</h3>
            <button type="button" onclick="closeDocModal()" class="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 transition-colors">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <div class="flex-1 bg-slate-100 dark:bg-slate-950/50 p-1 overflow-hidden relative">
             <iframe id="doc-modal-frame" src="" class="w-full h-full rounded border border-slate-200 dark:border-slate-800 bg-white"></iframe>
             <div id="doc-loading" class="absolute inset-0 flex items-center justify-center pointer-events-none">
                 <i data-lucide="loader-2" class="w-8 h-8 text-blue-500 animate-spin"></i>
             </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.lucide) window.lucide.createIcons();

        // Data
        var inspectors = <?php echo json_encode(array_map(function ($r) {
            return [
                'officer_id' => (int) ($r['officer_id'] ?? 0),
                'name' => (string) ($r['name'] ?? ''),
                'badge_no' => (string) ($r['badge_no'] ?? '')
            ];
        }, $inspectors), JSON_UNESCAPED_SLASHES); ?>;
        var recentLocations = <?php echo json_encode($recentLocations, JSON_UNESCAPED_SLASHES); ?>;
        var recentEndorsedPlates = <?php echo json_encode($needsInspectionPlates, JSON_UNESCAPED_SLASHES); ?>;

        // Elements
        var inspectorStatus = document.getElementById('inspector-status');
        var scheduleInspectorInput = document.getElementById('schedule-inspector');
        var scheduleInspectorId = document.getElementById('schedule-inspector-id');
        var scheduleInspectorSuggestions = document.getElementById('schedule-inspector-suggestions');
        var scheduleForm = document.getElementById('schedule-form');
        var upcomingForm = document.getElementById('upcoming-filter-form');
        var lookupPlateInput = document.getElementById('lookup-plate');
        var lookupPlateSuggestions = document.getElementById('lookup-plate-suggestions');
        var schedulePlateInput = document.getElementById('schedule-plate');
        var schedulePlateSuggestions = document.getElementById('schedule-plate-suggestions');
        var scheduleLocationInput = document.getElementById('schedule-location');
        var scheduleLocationSuggestions = document.getElementById('schedule-location-suggestions');
        var upcomingQInput = document.getElementById('upcoming-filter-q');
        var docUploadForm = document.getElementById('doc-upload-form');
        var docUploadButton = document.getElementById('btn-doc-upload');
        var docUploadStatus = document.getElementById('doc-upload-status');
        var qrModal = document.getElementById('qr-modal-overlay');
        var qrModalImage = document.getElementById('qr-modal-image');
        var qrModalClose = document.getElementById('qr-modal-close');
        var docModal = document.getElementById('doc-modal-overlay');
        var docModalIframe = document.getElementById('doc-modal-iframe');
        var docModalTitle = document.getElementById('doc-modal-title');
        var docModalClose = document.getElementById('doc-modal-close');

        // UI Helpers
        function clearBox(box) {
            if (!box) return;
            box.innerHTML = '';
            box.classList.add('hidden');
        }

        function renderBox(box, nodes) {
            if (!box) return;
            box.innerHTML = '';
            if (!nodes || !nodes.length) {
                clearBox(box);
                return;
            }
            nodes.forEach(function (n) { box.appendChild(n); });
            box.classList.remove('hidden');
        }

        function openQrModal(url) {
            if (!qrModal || !qrModalImage) return;
            qrModalImage.src = url || '';
            qrModal.classList.remove('hidden');
            // Simple animation
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

        function openDocModal(url, title) {
            var ov = document.getElementById('doc-modal-overlay');
            var fr = document.getElementById('doc-modal-frame');
            var ti = document.getElementById('doc-modal-title');
            var ld = document.getElementById('doc-loading');

            if (!ov || !fr) return;

            if (title && ti) ti.textContent = title;
            fr.src = url;

            if (ld) ld.classList.remove('hidden');
            fr.onload = function () {
                if (ld) ld.classList.add('hidden');
            };

            ov.classList.remove('hidden');
            setTimeout(() => {
                ov.classList.remove('opacity-0');
                ov.querySelector('div').classList.remove('scale-95');
                ov.querySelector('div').classList.add('scale-100');
            }, 10);
        }

        function closeDocModal() {
            var ov = document.getElementById('doc-modal-overlay');
            var fr = document.getElementById('doc-modal-frame');

            if (!ov) return;

            ov.classList.add('opacity-0');
            ov.querySelector('div').classList.remove('scale-100');
            ov.querySelector('div').classList.add('scale-95');

            setTimeout(() => {
                ov.classList.add('hidden');
                if (fr) fr.src = 'about:blank';
            }, 300);
        }

        function setInspectorStatus(message, tone) {
            if (!inspectorStatus) return;
            inspectorStatus.textContent = message || '';
            inspectorStatus.className = 'mt-2 text-xs font-medium min-h-[1.25rem] transition-colors duration-300 ' +
                (tone === 'ok' ? 'text-emerald-600' : (tone === 'error' ? 'text-rose-600' : 'text-slate-500'));
        }

        // Inspector Logic
        function validateInspector(id) {
            if (!inspectorStatus) return;
            if (!id) {
                setInspectorStatus('', '');
                return;
            }
            setInspectorStatus('Checking status...', '');
            var formData = new FormData();
            formData.set('officer_id', String(id));
            fetch((window.TMM_ROOT_URL || '') + '/admin/api/module4/validate_inspector.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data && data.ok && data.active) {
                        var label = data.name || '';
                        var badge = data.badge_no || '';
                        setInspectorStatus('✓ Active: ' + [label, badge].filter(Boolean).join(' — '), 'ok');
                    } else {
                        setInspectorStatus('⚠ Inactive or not found', 'error');
                    }
                })
                .catch(() => setInspectorStatus('⚠ Validation failed', 'error'));
        }

        function findInspectorMatches(q) {
            var v = (q || '').trim().toLowerCase();
            if (!v) return [];
            return inspectors.filter(it => {
                var name = (it.name || '').toLowerCase();
                var badge = (it.badge_no || '').toLowerCase();
                return (name && name.includes(v)) || (badge && badge.includes(v));
            }).slice(0, 5);
        }

        function createSuggestionItem(primary, secondary, onClick) {
            var div = document.createElement('div');
            div.className = 'px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-700/50 cursor-pointer border-b border-slate-50 dark:border-slate-700/50 last:border-0 transition-colors';
            div.innerHTML = `
            <div class="font-bold text-slate-800 dark:text-white">${primary}</div>
            ${secondary ? `<div class="text-[10px] font-medium text-slate-500 mt-0.5">${secondary}</div>` : ''}
        `;
            div.addEventListener('pointerdown', function (e) {
                e.preventDefault();
                e.stopPropagation();
                onClick();
            });
            return div;
        }

        function setupInspectorInput(inputEl, idEl, boxEl) {
            if (!inputEl || !idEl || !boxEl) return;
            var t = null;

            inputEl.addEventListener('input', function () {
                if (t) clearTimeout(t);
                idEl.value = '0';
                t = setTimeout(function () {
                    var matches = findInspectorMatches(inputEl.value);
                    if (!matches.length) {
                        clearBox(boxEl);
                        return;
                    }
                    var nodes = matches.map(it => {
                        return createSuggestionItem(
                            it.name,
                            it.badge_no,
                            function () {
                                inputEl.value = [it.name, it.badge_no].filter(Boolean).join(' — ');
                                idEl.value = String(it.officer_id || 0);
                                clearBox(boxEl);
                                if (inputEl === scheduleInspectorInput) validateInspector(it.officer_id);
                            }
                        );
                    });
                    renderBox(boxEl, nodes);
                }, 200);
            });

            inputEl.addEventListener('blur', () => setTimeout(() => clearBox(boxEl), 200));
        }

        setupInspectorInput(scheduleInspectorInput, scheduleInspectorId, scheduleInspectorSuggestions);

        // Vehicle Logic
        function fetchPlateSuggestions(q) {
            if ((q || '').trim().length < 2) return Promise.resolve([]);
            return fetch((window.TMM_ROOT_URL || '') + '/admin/api/module1/list_vehicles.php?q=' + encodeURIComponent(q))
                .then(res => res.json())
                .then(data => (data && data.ok && Array.isArray(data.data)) ? data.data.slice(0, 5) : [])
                .catch(() => []);
        }

        function setupPlateInput(inputEl, boxEl, fallbackItems) {
            if (!inputEl || !boxEl) return;
            var timer = null;

            function showFallback() {
                if (!fallbackItems || !fallbackItems.length) { clearBox(boxEl); return; }
                var nodes = fallbackItems.slice(0, 6).map(it => {
                    return createSuggestionItem(
                        it.plate_number,
                        (it.operator_name || '') ? (it.operator_name + ' • needs inspection') : 'Needs inspection',
                        function () {
                            inputEl.value = it.plate_number || '';
                            clearBox(boxEl);
                        }
                    );
                });
                renderBox(boxEl, nodes);
            }

            inputEl.addEventListener('input', function () {
                if (timer) clearTimeout(timer);
                timer = setTimeout(function () {
                    if ((inputEl.value || '').trim().length < 2) {
                        showFallback();
                        return;
                    }
                    fetchPlateSuggestions(inputEl.value).then(items => {
                        if (!items.length) {
                            clearBox(boxEl);
                            return;
                        }
                        var nodes = items.map(it => {
                            return createSuggestionItem(
                                it.plate_number,
                                [it.operator_name, it.status].filter(Boolean).join(' • '),
                                function () {
                                    inputEl.value = it.plate_number || '';
                                    clearBox(boxEl);
                                    if (inputEl === lookupPlateInput) inputEl.form.submit();
                                }
                            );
                        });
                        renderBox(boxEl, nodes);
                    });
                }, 200);
            });
            inputEl.addEventListener('focus', function () {
                if ((inputEl.value || '').trim().length < 2) showFallback();
            });
            inputEl.addEventListener('blur', () => setTimeout(() => clearBox(boxEl), 200));
        }

        setupPlateInput(lookupPlateInput, lookupPlateSuggestions, recentEndorsedPlates || []);
        setupPlateInput(schedulePlateInput, schedulePlateSuggestions, recentEndorsedPlates || []);

        // Location Logic
        function setupLocationInput(inputEl, boxEl) {
            if (!inputEl || !boxEl) return;
            var timer = null;
            inputEl.addEventListener('input', function () {
                if (timer) clearTimeout(timer);
                timer = setTimeout(function () {
                    var v = (inputEl.value || '').trim().toLowerCase();
                    if (v.length < 2) { clearBox(boxEl); return; }

                    var matches = recentLocations.filter(loc => loc.toLowerCase().includes(v)).slice(0, 5);
                    if (!matches.length) { clearBox(boxEl); return; }

                    var nodes = matches.map(loc => {
                        return createSuggestionItem(loc, null, function () {
                            inputEl.value = loc;
                            clearBox(boxEl);
                        });
                    });
                    renderBox(boxEl, nodes);
                }, 200);
            });
            inputEl.addEventListener('blur', () => setTimeout(() => clearBox(boxEl), 200));
        }

        setupLocationInput(scheduleLocationInput, scheduleLocationSuggestions);

        // Doc Upload
        if (docUploadForm && docUploadButton && docUploadStatus) {
            docUploadForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var hasFile = Array.from(docUploadForm.querySelectorAll('input[type="file"]')).some(inp => inp.files.length > 0);

                if (!hasFile) {
                    docUploadStatus.textContent = 'Please select a file first.';
                    docUploadStatus.className = 'text-xs font-bold text-rose-500';
                    return;
                }

                docUploadStatus.textContent = 'Uploading...';
                docUploadStatus.className = 'text-xs font-bold text-blue-500';
                docUploadButton.disabled = true;
                docUploadButton.classList.add('opacity-50', 'cursor-not-allowed');

                fetch((window.TMM_ROOT_URL || '') + '/admin/api/module1/upload_docs.php', { method: 'POST', body: new FormData(docUploadForm) })
                    .then(res => res.json())
                    .then(data => {
                        if (data && data.ok) {
                            docUploadStatus.textContent = 'Success! Reloading...';
                            docUploadStatus.className = 'text-xs font-bold text-emerald-500';
                            setTimeout(() => window.location.reload(), 800);
                        } else {
                            throw new Error(data.error || 'Upload failed');
                        }
                    })
                    .catch(err => {
                        docUploadStatus.textContent = err.message;
                        docUploadStatus.className = 'text-xs font-bold text-rose-500';
                        docUploadButton.disabled = false;
                        docUploadButton.classList.remove('opacity-50', 'cursor-not-allowed');
                    });
            });
        }

        // Filters Auto-Submit
        if (upcomingForm) {
            var debouncedSubmit = null;
            upcomingForm.addEventListener('change', () => upcomingForm.submit());
            if (upcomingQInput) {
                upcomingQInput.addEventListener('input', () => {
                    if (debouncedSubmit) clearTimeout(debouncedSubmit);
                    debouncedSubmit = setTimeout(() => upcomingForm.submit(), 400);
                });
            }
        }

        // QR Modal logic exposed globally
    window.openQrModal = openQrModal;
    window.openDocModal = openDocModal;
    window.closeDocModal = closeDocModal;
    
    // Legacy support if needed but updated above
    document.addEventListener('click', function (e) {
        if (e.target === qrModal) closeQrModal();
        if (e.target === docModal) closeDocModal();
    });
        if (qrModalClose) qrModalClose.addEventListener('click', closeQrModal);
        if (docModalClose) docModalClose.addEventListener('click', closeDocModal);
    });
</script>
