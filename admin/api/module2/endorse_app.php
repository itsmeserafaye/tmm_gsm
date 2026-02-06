<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/franchise_gate.php';
$db = db();

header('Content-Type: application/json');
require_permission('module2.franchises.manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$app_id = (int)($_POST['application_id'] ?? 0);
$notes = trim((string)($_POST['notes'] ?? ''));
$endorsementStatusRaw = trim((string)($_POST['endorsement_status'] ?? ''));
$conditions = trim((string)($_POST['conditions'] ?? ''));
$conditions = substr($conditions, 0, 500);

if ($app_id === 0) {
    echo json_encode(['ok' => false, 'error' => 'missing_application_id']);
    exit;
}

$db->begin_transaction();
try {
    $stmtA = $db->prepare("SELECT application_id, franchise_ref_number, operator_id, route_id, vehicle_count, status FROM franchise_applications WHERE application_id=? FOR UPDATE");
    if (!$stmtA) {
        throw new Exception('db_prepare_failed');
    }
    $stmtA->bind_param('i', $app_id);
    $stmtA->execute();
    $app = $stmtA->get_result()->fetch_assoc();
    $stmtA->close();

    if (!$app) {
        $db->rollback();
        echo json_encode(['ok' => false, 'error' => 'application_not_found']);
        exit;
    }

    $curStatus = (string)($app['status'] ?? '');
    if ($curStatus === 'Endorsed' || $curStatus === 'LGU-Endorsed') {
        $db->commit();
        echo json_encode(['ok' => true, 'message' => 'Application already endorsed']);
        exit;
    }
    if ($curStatus !== 'Submitted') {
        $db->rollback();
        echo json_encode(['ok' => false, 'error' => 'invalid_status']);
        exit;
    }

    $opId = (int)($app['operator_id'] ?? 0);
    $routeId = (int)($app['route_id'] ?? 0);
    $want = (int)($app['vehicle_count'] ?? 0);
    if ($want <= 0) $want = 1;
    $gate = tmm_can_endorse_application($db, $opId, $routeId, $want, $app_id);
    if (!$gate['ok']) {
        $db->rollback();
        echo json_encode($gate);
        exit;
    }

    $hasCol = function (string $table, string $col) use ($db): bool {
        $table = trim($table);
        $col = trim($col);
        if ($table === '' || $col === '') return false;
        $res = $db->query("SHOW COLUMNS FROM `{$table}` LIKE '" . $db->real_escape_string($col) . "'");
        return $res && ($res->num_rows ?? 0) > 0;
    };

    $vdTypeCol = $hasCol('vehicle_documents', 'doc_type') ? 'doc_type'
        : ($hasCol('vehicle_documents', 'document_type') ? 'document_type'
        : ($hasCol('vehicle_documents', 'type') ? 'type' : 'doc_type'));
    $vdVerifiedCol = $hasCol('vehicle_documents', 'is_verified') ? 'is_verified'
        : ($hasCol('vehicle_documents', 'verified') ? 'verified'
        : ($hasCol('vehicle_documents', 'isApproved') ? 'isApproved' : 'is_verified'));
    $vdHasVehicleId = $hasCol('vehicle_documents', 'vehicle_id');
    $vdHasPlate = $hasCol('vehicle_documents', 'plate_number');
    $join = $vdHasVehicleId && $vdHasPlate
        ? "(vd.vehicle_id=v.id OR ((vd.vehicle_id IS NULL OR vd.vehicle_id=0) AND vd.plate_number=v.plate_number))"
        : ($vdHasVehicleId ? "vd.vehicle_id=v.id" : ($vdHasPlate ? "vd.plate_number=v.plate_number" : "0=1"));
    $verCond = "COALESCE(vd.`{$vdVerifiedCol}`,0)=1";
    $insCond = "LOWER(vd.`{$vdTypeCol}`) IN ('insurance','ins')";
    $orcrCond = "LOWER(vd.`{$vdTypeCol}`) IN ('orcr','or/cr')";
    $orCond = "LOWER(vd.`{$vdTypeCol}`)='or'";
    $crCond = "LOWER(vd.`{$vdTypeCol}`)='cr'";

    $hasRegs = (bool)($db->query("SHOW TABLES LIKE 'vehicle_registrations'")?->fetch_row());

    $stmtVeh = $db->prepare("SELECT v.plate_number, COALESCE(v.record_status,'') AS record_status, COALESCE(v.inspection_status,'') AS inspection_status,
                                    COALESCE(vr.registration_status,'') AS registration_status,
                                    COALESCE(NULLIF(vr.orcr_no,''),'') AS orcr_no,
                                    vr.orcr_date,
                                    MAX(CASE WHEN {$insCond} AND {$verCond} THEN 1 ELSE 0 END) AS ins_ok,
                                    MAX(CASE WHEN {$orcrCond} AND {$verCond} THEN 1 ELSE 0 END) AS orcr_ok,
                                    MAX(CASE WHEN {$orCond} AND {$verCond} THEN 1 ELSE 0 END) AS or_ok,
                                    MAX(CASE WHEN {$crCond} AND {$verCond} THEN 1 ELSE 0 END) AS cr_ok
                             FROM vehicles v
                             LEFT JOIN vehicle_documents vd ON {$join}
                             " . ($hasRegs ? "LEFT JOIN vehicle_registrations vr ON vr.vehicle_id=v.id" : "LEFT JOIN (SELECT NULL AS vehicle_id, '' AS registration_status, '' AS orcr_no, NULL AS orcr_date) vr ON 1=0") . "
                             WHERE v.operator_id=? AND COALESCE(v.record_status,'') <> 'Archived'
                             GROUP BY v.plate_number, v.record_status, v.inspection_status, vr.registration_status, vr.orcr_no, vr.orcr_date
                             ORDER BY v.created_at DESC");
    $countInspected = 0;
    $countDocs = 0;
    if ($stmtVeh) {
        $stmtVeh->bind_param('i', $opId);
        $stmtVeh->execute();
        $resVeh = $stmtVeh->get_result();
        while ($resVeh && ($r = $resVeh->fetch_assoc())) {
            $inspOk = ((string)($r['inspection_status'] ?? '')) === 'Passed';
            if ($inspOk) $countInspected++;
            $insOk = ((int)($r['ins_ok'] ?? 0)) === 1;
            $orcrOk = ((int)($r['orcr_ok'] ?? 0)) === 1 || ((((int)($r['or_ok'] ?? 0)) === 1) && (((int)($r['cr_ok'] ?? 0)) === 1));
            $regOk = true;
            if ($hasRegs) {
                $regSt = (string)($r['registration_status'] ?? '');
                $orcrNo = (string)($r['orcr_no'] ?? '');
                $orcrDate = $r['orcr_date'] ?? null;
                $regOk = in_array($regSt, ['Registered','Recorded'], true) && trim($orcrNo) !== '' && !empty($orcrDate);
            }
            if ($orcrOk && $insOk && $regOk) $countDocs++;
        }
        $stmtVeh->close();
    }

    $suggested = [];
    if ($countInspected < $want) $suggested[] = 'Subject to passing vehicle inspection';
    if ($countDocs < $want) $suggested[] = 'Subject to submission of OR/CR and insurance';

    $allowedEndorse = ['Endorsed (Conditional)','Endorsed (Complete)','Rejected'];
    $endorsementStatus = 'Endorsed (Complete)';
    foreach ($allowedEndorse as $opt) {
        if (strcasecmp($endorsementStatusRaw, $opt) === 0) { $endorsementStatus = $opt; break; }
    }
    if ($endorsementStatus !== 'Rejected' && $suggested) {
        if ($endorsementStatus === 'Endorsed (Complete)') $endorsementStatus = 'Endorsed (Conditional)';
        $existing = trim((string)$conditions);
        $lines = $existing !== '' ? preg_split('/\r\n|\r|\n/', $existing) : [];
        $lines = array_values(array_filter(array_map(fn($x) => trim((string)$x), (array)$lines), fn($x) => $x !== ''));
        foreach ($suggested as $s) {
            $exists = false;
            foreach ($lines as $ln) {
                if (strcasecmp($ln, $s) === 0) { $exists = true; break; }
            }
            if (!$exists) $lines[] = $s;
        }
        $conditions = implode("\n", $lines);
    }
    $conditionsBind = $conditions !== '' ? $conditions : null;

    $permit_no = $endorsementStatus === 'Rejected' ? null : ("PERMIT-" . date('Y') . "-" . str_pad((string)$app_id, 4, '0', STR_PAD_LEFT));
    $stmtIns = $db->prepare("INSERT INTO endorsement_records (application_id, issued_date, permit_number, endorsement_status, conditions)
                             VALUES (?, CURDATE(), ?, ?, ?)
                             ON DUPLICATE KEY UPDATE
                               issued_date=VALUES(issued_date),
                               permit_number=VALUES(permit_number),
                               endorsement_status=VALUES(endorsement_status),
                               conditions=VALUES(conditions)");
    if (!$stmtIns) throw new Exception('db_prepare_failed');
    $stmtIns->bind_param('isss', $app_id, $permit_no, $endorsementStatus, $conditionsBind);
    if (!$stmtIns->execute()) throw new Exception('insert_failed');
    $stmtIns->close();

    $nextAppStatus = $endorsementStatus === 'Rejected' ? 'Rejected' : 'LGU-Endorsed';
    $stmtU = $db->prepare("UPDATE franchise_applications
                           SET status=?,
                               endorsed_at=NOW(),
                               remarks=CASE WHEN ?<>'' THEN ? ELSE remarks END
                           WHERE application_id=?");
    if (!$stmtU) throw new Exception('db_prepare_failed');
    $stmtU->bind_param('sssi', $nextAppStatus, $notes, $notes, $app_id);
    $stmtU->execute();
    $stmtU->close();

    $db->commit();
    echo json_encode(['ok' => true, 'message' => 'Endorsement saved', 'permit_number' => $permit_no, 'endorsement_status' => $endorsementStatus]);
} catch (Throwable $e) {
    $db->rollback();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_error']);
}
?>
