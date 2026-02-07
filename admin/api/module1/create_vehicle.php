<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/util.php';

header('Content-Type: application/json');

try {
    $db = db();
    require_permission('module1.vehicles.write');

    $plateRaw = (string)($_POST['plate_number'] ?? ($_POST['plate_no'] ?? ''));
    $plateNorm = strtoupper(preg_replace('/\s+/', '', trim($plateRaw)));
    $plateNorm = preg_replace('/[^A-Z0-9-]/', '', $plateNorm);
    $letters = substr(preg_replace('/[^A-Z]/', '', $plateNorm), 0, 3);
    $digits = substr(preg_replace('/[^0-9]/', '', $plateNorm), 0, 4);
    $plate = ($letters !== '' && $digits !== '') ? ($letters . '-' . $digits) : $plateNorm;
    if ($plate === '' || !preg_match('/^[A-Z]{3}\-[0-9]{3,4}$/', $plate)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid_plate']);
        exit;
    }

    $type = trim((string)($_POST['vehicle_type'] ?? ''));
    $engineNoRaw = (string)($_POST['engine_no'] ?? ($_POST['engine_number'] ?? ''));
    $engineNo = strtoupper(preg_replace('/\s+/', '', trim($engineNoRaw)));
    $engineNo = preg_replace('/[^A-Z0-9\-]/', '', $engineNo);
    $chassisNoRaw = (string)($_POST['chassis_no'] ?? ($_POST['chassis_number'] ?? ''));
    $chassisNo = strtoupper(preg_replace('/\s+/', '', trim($chassisNoRaw)));
    $chassisNo = preg_replace('/[^A-HJ-NPR-Z0-9]/', '', $chassisNo);
    $make = trim((string)($_POST['make'] ?? ''));
    $model = trim((string)($_POST['model'] ?? ''));
    $yearModel = trim((string)($_POST['year_model'] ?? ''));
    $fuelType = trim((string)($_POST['fuel_type'] ?? ''));
    $color = trim((string)($_POST['color'] ?? ''));
    $operatorId = isset($_POST['operator_id']) && $_POST['operator_id'] !== '' ? (int)$_POST['operator_id'] : 0;
    $operatorName = trim((string)($_POST['operator_name'] ?? ''));
    $assisted = (int)($_POST['assisted'] ?? 0) === 1;
    $ocrUsed = (int)($_POST['ocr_used'] ?? 0);
    $ocrConfirmed = (int)($_POST['ocr_confirmed'] ?? 0);
    $orNumberRaw = (string)($_POST['or_number'] ?? '');
    $orNumber = preg_replace('/[^0-9]/', '', trim($orNumberRaw));
    $orNumber = substr($orNumber, 0, 12);
    $crNumberRaw = (string)($_POST['cr_number'] ?? '');
    $crNumber = strtoupper(preg_replace('/\s+/', '', trim($crNumberRaw)));
    $crNumber = preg_replace('/[^A-Z0-9\-]/', '', $crNumber);
    $crNumber = substr($crNumber, 0, 20);
    $crIssueDate = trim((string)($_POST['cr_issue_date'] ?? ''));
    $registeredOwner = trim((string)($_POST['registered_owner'] ?? ''));

    if ($ocrUsed === 1 && $ocrConfirmed !== 1) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'ocr_confirmation_required']);
        exit;
    }

    if ($crIssueDate !== '' && !preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $crIssueDate)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid_cr_issue_date']);
        exit;
    }

    if ($type === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'missing_vehicle_type']);
        exit;
    }

    if ($engineNo !== '' && !preg_match('/^[A-Z0-9\-]{5,20}$/', $engineNo)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid_engine_no']);
        exit;
    }
    if ($chassisNo !== '' && !preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $chassisNo)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid_chassis_no']);
        exit;
    }
    if ($orNumber !== '' && !preg_match('/^[0-9]{6,12}$/', $orNumber)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid_or_number']);
        exit;
    }
    if ($crNumber !== '' && !preg_match('/^[A-Z0-9\-]{6,20}$/', $crNumber)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid_cr_number']);
        exit;
    }

    $ensureVehicleCrCols = function () use ($db): void {
        $has = function (string $col) use ($db): bool {
            $r = $db->query("SHOW COLUMNS FROM vehicles LIKE '" . $db->real_escape_string($col) . "'");
            return $r && $r->num_rows > 0;
        };
        if (!$has('or_number')) { @$db->query("ALTER TABLE vehicles ADD COLUMN or_number VARCHAR(12) NULL"); }
        if (!$has('cr_number')) { @$db->query("ALTER TABLE vehicles ADD COLUMN cr_number VARCHAR(64) NULL"); }
        if (!$has('cr_issue_date')) { @$db->query("ALTER TABLE vehicles ADD COLUMN cr_issue_date DATE NULL"); }
        if (!$has('registered_owner')) { @$db->query("ALTER TABLE vehicles ADD COLUMN registered_owner VARCHAR(150) NULL"); }
        if (!$has('submitted_by_portal_user_id')) { @$db->query("ALTER TABLE vehicles ADD COLUMN submitted_by_portal_user_id INT DEFAULT NULL"); }
        if (!$has('submitted_by_name')) { @$db->query("ALTER TABLE vehicles ADD COLUMN submitted_by_name VARCHAR(150) DEFAULT NULL"); }
        if (!$has('submitted_at')) { @$db->query("ALTER TABLE vehicles ADD COLUMN submitted_at DATETIME DEFAULT NULL"); }
    };

    $ensureExpiryCol = function () use ($db): bool {
        $res = $db->query("SHOW COLUMNS FROM documents LIKE 'expiry_date'");
        if ($res && $res->num_rows > 0) return true;
        $tbl = $db->query("SHOW TABLES LIKE 'documents'");
        if (!$tbl || !$tbl->fetch_row()) return false;
        return (bool)$db->query("ALTER TABLE documents ADD COLUMN expiry_date DATE NULL");
    };

    $uploadsDir = __DIR__ . '/../../uploads';
    if (!is_dir($uploadsDir)) {
        @mkdir($uploadsDir, 0777, true);
    }

    $crFile = $_FILES['cr'] ?? null;
    if (!is_array($crFile) || (int)($crFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'cr_required']);
        exit;
    }

    $orFile = $_FILES['or'] ?? null;
    $hasOrUpload = is_array($orFile) && (int)($orFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
    $orExpiry = trim((string)($_POST['or_expiry_date'] ?? ''));
    if ($hasOrUpload) {
        if ($orExpiry === '' || !preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $orExpiry)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'or_expiry_required']);
            exit;
        }
    }

    $recordStatus = ($operatorId > 0 || $operatorName !== '') ? 'Linked' : 'Encoded';
    $vehicleStatus = $recordStatus === 'Linked' ? 'Pending Inspection' : 'Declared/linked';

    $opNameResolved = '';
    if ($operatorId > 0) {
        $stmtOp = $db->prepare("SELECT name, full_name FROM operators WHERE id=? LIMIT 1");
        if ($stmtOp) {
            $stmtOp->bind_param('i', $operatorId);
            $stmtOp->execute();
            $rowOp = $stmtOp->get_result()->fetch_assoc();
            $stmtOp->close();
            if ($rowOp) {
                $opNameResolved = trim((string)($rowOp['name'] ?? ''));
                if ($opNameResolved === '') $opNameResolved = trim((string)($rowOp['full_name'] ?? ''));
            }
        }
    }
    if ($opNameResolved === '' && $operatorName !== '') $opNameResolved = $operatorName;
    if ($registeredOwner === '' && $opNameResolved !== '') $registeredOwner = $opNameResolved;

    $route = '';
    $franchise = '';
    $inspectionStatus = 'Pending';

    $ensureVehicleCrCols();
    $submittedByName = trim((string)($_SESSION['name'] ?? ($_SESSION['full_name'] ?? '')));
    if ($submittedByName === '') $submittedByName = trim((string)($_SESSION['email'] ?? ($_SESSION['user_email'] ?? '')));
    if ($submittedByName === '') $submittedByName = 'Admin';
    $db->begin_transaction();

    $stmt = $db->prepare("INSERT INTO vehicles(plate_number, vehicle_type, operator_id, operator_name, engine_no, chassis_no, make, model, year_model, fuel_type, color, record_status, status, inspection_status, or_number, cr_number, cr_issue_date, registered_owner, submitted_by_portal_user_id, submitted_by_name, submitted_at)
                          VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                          ON DUPLICATE KEY UPDATE
                            vehicle_type=VALUES(vehicle_type),
                            operator_id=VALUES(operator_id),
                            operator_name=VALUES(operator_name),
                            engine_no=VALUES(engine_no),
                            chassis_no=VALUES(chassis_no),
                            make=VALUES(make),
                            model=VALUES(model),
                            year_model=VALUES(year_model),
                            fuel_type=VALUES(fuel_type),
                            color=VALUES(color),
                            record_status=VALUES(record_status),
                            status=VALUES(status),
                            inspection_status=COALESCE(NULLIF(inspection_status,''), VALUES(inspection_status)),
                            or_number=CASE WHEN VALUES(or_number)<>'' THEN VALUES(or_number) ELSE or_number END,
                            cr_number=CASE WHEN VALUES(cr_number)<>'' THEN VALUES(cr_number) ELSE cr_number END,
                            cr_issue_date=CASE WHEN VALUES(cr_issue_date)<>'' THEN VALUES(cr_issue_date) ELSE cr_issue_date END,
                            registered_owner=CASE WHEN VALUES(registered_owner)<>'' THEN VALUES(registered_owner) ELSE registered_owner END,
                            submitted_by_portal_user_id=COALESCE(submitted_by_portal_user_id, VALUES(submitted_by_portal_user_id)),
                            submitted_by_name=COALESCE(NULLIF(submitted_by_name,''), VALUES(submitted_by_name)),
                            submitted_at=COALESCE(submitted_at, VALUES(submitted_at))");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
        exit;
    }
    $operatorIdBind = $operatorId > 0 ? $operatorId : null;
    $submittedPortalUserId = null;
    $submittedNameBind = $assisted ? $submittedByName : null;
    $submittedAtBind = $assisted ? date('Y-m-d H:i:s') : null;
    $stmt->bind_param('ssissssssssssssssiss', $plate, $type, $operatorIdBind, $opNameResolved, $engineNo, $chassisNo, $make, $model, $yearModel, $fuelType, $color, $recordStatus, $vehicleStatus, $inspectionStatus, $orNumber, $crNumber, $crIssueDate, $registeredOwner, $submittedPortalUserId, $submittedNameBind, $submittedAtBind);
    $ok = $stmt->execute();
    if (!$ok) {
        $db->rollback();
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'db_insert_failed']);
        exit;
    }
    $vehicleId = (int)$db->insert_id;
    if ($vehicleId <= 0) {
        $chk = $db->prepare("SELECT id FROM vehicles WHERE plate_number=? LIMIT 1");
        if ($chk) {
            $chk->bind_param('s', $plate);
            $chk->execute();
            $row = $chk->get_result()->fetch_assoc();
            $chk->close();
            $vehicleId = (int)($row['id'] ?? 0);
        }
    }

    $moveAndScan = function (array $file, string $suffix) use ($uploadsDir, $plate): string {
        $name = (string)($file['name'] ?? '');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','pdf'], true)) {
            throw new Exception('invalid_file_type');
        }
        $filename = $plate . '_' . $suffix . '_' . time() . '.' . $ext;
        $dest = $uploadsDir . '/' . $filename;
        if (!move_uploaded_file((string)$file['tmp_name'], $dest)) {
            throw new Exception('upload_move_failed');
        }
        $safe = tmm_scan_file_for_viruses($dest);
        if (!$safe) {
            if (is_file($dest)) { @unlink($dest); }
            throw new Exception('file_failed_security_scan');
        }
        return $filename;
    };

    $ensureExpiryCol();

    $insertDoc = function (string $typeLower, string $filePath, ?string $expiry) use ($db, $plate): void {
        $hasExpiry = false;
        $r = $db->query("SHOW COLUMNS FROM documents LIKE 'expiry_date'");
        if ($r && $r->num_rows > 0) $hasExpiry = true;
        if ($hasExpiry) {
            $stmtD = $db->prepare("INSERT INTO documents (plate_number, type, file_path, expiry_date) VALUES (?, ?, ?, ?)");
            if (!$stmtD) throw new Exception('db_prepare_failed');
            $stmtD->bind_param('ssss', $plate, $typeLower, $filePath, $expiry);
            if (!$stmtD->execute()) { $stmtD->close(); throw new Exception('db_insert_failed'); }
            $stmtD->close();
        } else {
            $stmtD = $db->prepare("INSERT INTO documents (plate_number, type, file_path) VALUES (?, ?, ?)");
            if (!$stmtD) throw new Exception('db_prepare_failed');
            $stmtD->bind_param('sss', $plate, $typeLower, $filePath);
            if (!$stmtD->execute()) { $stmtD->close(); throw new Exception('db_insert_failed'); }
            $stmtD->close();
        }
    };

    $crPath = $moveAndScan($crFile, 'cr');
    $insertDoc('cr', $crPath, null);

    if ($hasOrUpload) {
        $orPath = $moveAndScan($orFile, 'or');
        $insertDoc('or', $orPath, $orExpiry !== '' ? $orExpiry : null);
    }

    $db->commit();

    if ($assisted) {
        tmm_audit_event($db, 'PUV_ASSISTED_VEHICLE_ENCODED', 'Vehicle', (string)$vehicleId, ['assisted' => true, 'plate_number' => $plate]);
    }
    echo json_encode(['ok' => true, 'vehicle_id' => $vehicleId, 'plate_number' => $plate, 'status' => $vehicleStatus, 'inspection_status' => $inspectionStatus, 'assisted' => $assisted]);
} catch (Exception $e) {
    if (defined('TMM_TEST')) {
        throw $e;
    }
    if (isset($db) && $db instanceof mysqli) {
        try { $db->rollback(); } catch (Throwable $_) {}
    }
    http_response_code(400);
    $msg = $e->getMessage();
    echo json_encode(['ok' => false, 'error' => $msg !== '' ? $msg : 'request_failed']);
}
?> 
