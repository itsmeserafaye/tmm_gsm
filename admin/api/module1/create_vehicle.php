<?php
@ini_set('display_errors', '0');
@ini_set('html_errors', '0');
error_reporting(E_ALL);

register_shutdown_function(function () {
    if (defined('TMM_TEST')) return;
    $err = error_get_last();
    if (!$err) return;
    $type = (int)($err['type'] ?? 0);
    if (!in_array($type, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) return;
    http_response_code(500);
    if (!headers_sent()) header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'server_error']);
    exit;
});

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/util.php';

header('Content-Type: application/json');

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return false;
    throw new ErrorException((string)$message, 0, (int)$severity, (string)$file, (int)$line);
});

try {
    $db = db();
    require_any_permission(['module1.write','module1.vehicles.write']);

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

    $operatorExists = false;
    $opNameResolved = '';
    if ($operatorId > 0) {
        $stmtOp = $db->prepare("SELECT id, name, full_name FROM operators WHERE id=? LIMIT 1");
        if ($stmtOp) {
            $stmtOp->bind_param('i', $operatorId);
            $stmtOp->execute();
            $rowOp = $stmtOp->get_result()->fetch_assoc();
            $stmtOp->close();
            if ($rowOp) {
                $operatorExists = true;
                $opNameResolved = trim((string)($rowOp['name'] ?? ''));
                if ($opNameResolved === '') $opNameResolved = trim((string)($rowOp['full_name'] ?? ''));
            } else {
                $operatorId = 0;
            }
        }
    }
    if (!$operatorExists && $operatorName !== '') {
        $stmtOp = $db->prepare("SELECT id, name, full_name FROM operators WHERE COALESCE(NULLIF(registered_name,''), NULLIF(name,''), full_name)=? OR full_name=? OR name=? LIMIT 1");
        if ($stmtOp) {
            $stmtOp->bind_param('sss', $operatorName, $operatorName, $operatorName);
            $stmtOp->execute();
            $rowOp = $stmtOp->get_result()->fetch_assoc();
            $stmtOp->close();
            if ($rowOp) {
                $operatorExists = true;
                $operatorId = (int)($rowOp['id'] ?? 0);
                $opNameResolved = trim((string)($rowOp['name'] ?? ''));
                if ($opNameResolved === '') $opNameResolved = trim((string)($rowOp['full_name'] ?? ''));
            }
        }
    }
    if ($opNameResolved === '' && $operatorName !== '') $opNameResolved = $operatorName;

    $recordStatus = ($operatorExists && $operatorId > 0) ? 'Linked' : 'Encoded';
    $vehicleStatus = $recordStatus === 'Linked' ? 'Pending Inspection' : 'Declared';
    if ($registeredOwner === '' && $opNameResolved !== '') $registeredOwner = $opNameResolved;

    $route = '';
    $franchise = '';
    $inspectionStatus = 'Pending';

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
    $crIssueDateBind = $crIssueDate !== '' ? $crIssueDate : null;
    $stmt->bind_param('ssissssssssssssssisss', $plate, $type, $operatorIdBind, $opNameResolved, $engineNo, $chassisNo, $make, $model, $yearModel, $fuelType, $color, $recordStatus, $vehicleStatus, $inspectionStatus, $orNumber, $crNumber, $crIssueDateBind, $registeredOwner, $submittedPortalUserId, $submittedNameBind, $submittedAtBind);
    $ok = $stmt->execute();
    if (!$ok) {
        $errno = (int)($stmt->errno ?? 0);
        $errText = (string)($stmt->error ?? '');
        $db->rollback();
        $lower = strtolower($errText);
        if ($errno === 1292 && (str_contains($lower, 'cr_issue_date') || str_contains($lower, 'incorrect date'))) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'invalid_cr_issue_date']);
            exit;
        }
        if ($errno === 1054 && preg_match("/unknown column '([^']+)'/i", $errText, $m)) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'db_schema_mismatch', 'missing_column' => (string)($m[1] ?? '')]);
            exit;
        }
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'db_insert_failed', 'errno' => $errno]);
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

    $vehicleDocsSchema = function () use ($db): array {
        static $schema = null;
        if (is_array($schema)) return $schema;
        $schema = ['exists' => false, 'cols' => [], 'types' => []];
        $check = $db->query("SHOW TABLES LIKE 'vehicle_documents'");
        if (!$check || !$check->fetch_row()) return $schema;
        $schema['exists'] = true;
        $res = $db->query("SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vehicle_documents'");
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $col = (string)($r['COLUMN_NAME'] ?? '');
                if ($col !== '') $schema['cols'][$col] = true;
                if ($col !== '') $schema['types'][$col] = (string)($r['COLUMN_TYPE'] ?? '');
            }
        }
        return $schema;
    };

    $tryInsertVehicleDoc = function (int $vehicleId, string $docType, string $filename, ?string $expiryDate) use ($db, $plate, $vehicleDocsSchema): bool {
        $schema = $vehicleDocsSchema();
        if (empty($schema['exists'])) return false;
        $cols = (array)($schema['cols'] ?? []);

        $idCol = null;
        if (isset($cols['vehicle_id'])) $idCol = 'vehicle_id';
        elseif (isset($cols['plate_number'])) $idCol = 'plate_number';

        $typeCol = null;
        if (isset($cols['doc_type'])) $typeCol = 'doc_type';
        elseif (isset($cols['document_type'])) $typeCol = 'document_type';
        elseif (isset($cols['type'])) $typeCol = 'type';

        $pathCol = null;
        if (isset($cols['file_path'])) $pathCol = 'file_path';
        elseif (isset($cols['document_path'])) $pathCol = 'document_path';
        elseif (isset($cols['doc_path'])) $pathCol = 'doc_path';
        elseif (isset($cols['path'])) $pathCol = 'path';

        if ($idCol === null || $typeCol === null || $pathCol === null) return false;

        $extraCols = [];
        $extraTypes = '';
        $extraParams = [];
        if (isset($cols['is_verified'])) {
            $extraCols[] = 'is_verified';
            $extraTypes .= 'i';
            $extraParams[] = 0;
        } elseif (isset($cols['verified'])) {
            $extraCols[] = 'verified';
            $extraTypes .= 'i';
            $extraParams[] = 0;
        }
        if (isset($cols['expiry_date'])) {
            $extraCols[] = 'expiry_date';
            $extraTypes .= 's';
            $extraParams[] = ($expiryDate !== null && trim($expiryDate) !== '') ? trim($expiryDate) : null;
        }

        $typeMeta = (string)(($schema['types'] ?? [])[$typeCol] ?? '');
        $enumValues = [];
        if ($typeMeta !== '' && stripos($typeMeta, "enum(") === 0) {
            if (preg_match_all("/'([^']*)'/", $typeMeta, $m)) {
                $enumValues = $m[1] ?? [];
            }
        }
        $variant = $docType;
        if ($enumValues) {
            $matched = null;
            foreach ($enumValues as $ev) {
                if (strcasecmp($ev, $docType) === 0) { $matched = $ev; break; }
            }
            if ($matched !== null) $variant = $matched;
        }

        $sql = "INSERT INTO vehicle_documents ($idCol, $typeCol, $pathCol" . ($extraCols ? (", " . implode(", ", $extraCols)) : "") . ") VALUES (?,?,?" . ($extraCols ? ("," . implode(",", array_fill(0, count($extraCols), "?"))) : "") . ")";
        $stmt = $db->prepare($sql);
        if (!$stmt) throw new Exception('db_prepare_failed');

        $typesBase = ($idCol === 'vehicle_id') ? 'iss' : 'sss';
        $idVal = ($idCol === 'vehicle_id') ? $vehicleId : $plate;
        $types = $typesBase . $extraTypes;
        $params = array_merge([$idVal, $variant, $filename], $extraParams);
        $stmt->bind_param($types, ...$params);
        $ok = (bool)$stmt->execute();
        $stmt->close();
        if (!$ok) throw new Exception('db_insert_failed');
        return true;
    };

    $dedupeVehicleDocs = function (int $vehicleId, string $docType, string $keepFilename) use ($db, $plate, $uploadsDir, $vehicleDocsSchema): void {
        $schema = $vehicleDocsSchema();
        if (empty($schema['exists'])) return;
        $cols = (array)($schema['cols'] ?? []);

        $idCol = null;
        if (isset($cols['vehicle_id'])) $idCol = 'vehicle_id';
        elseif (isset($cols['plate_number'])) $idCol = 'plate_number';

        $typeCol = null;
        if (isset($cols['doc_type'])) $typeCol = 'doc_type';
        elseif (isset($cols['document_type'])) $typeCol = 'document_type';
        elseif (isset($cols['type'])) $typeCol = 'type';

        $pathCol = null;
        if (isset($cols['file_path'])) $pathCol = 'file_path';
        elseif (isset($cols['document_path'])) $pathCol = 'document_path';
        elseif (isset($cols['doc_path'])) $pathCol = 'doc_path';
        elseif (isset($cols['path'])) $pathCol = 'path';

        if ($idCol === null || $typeCol === null || $pathCol === null) return;
        $idVal = ($idCol === 'vehicle_id') ? $vehicleId : $plate;
        $typesBase = ($idCol === 'vehicle_id') ? 'iss' : 'sss';

        $paths = [];
        $stmtS = $db->prepare("SELECT $pathCol AS file_path FROM vehicle_documents WHERE $idCol=? AND $typeCol=? AND $pathCol<>?");
        if ($stmtS) {
            $stmtS->bind_param($typesBase, $idVal, $docType, $keepFilename);
            $stmtS->execute();
            $res = $stmtS->get_result();
            while ($res && ($r = $res->fetch_assoc())) {
                $p = trim((string)($r['file_path'] ?? ''));
                if ($p !== '') $paths[] = $p;
            }
            $stmtS->close();
        }
        $stmtD = $db->prepare("DELETE FROM vehicle_documents WHERE $idCol=? AND $typeCol=? AND $pathCol<>?");
        if ($stmtD) {
            $stmtD->bind_param($typesBase, $idVal, $docType, $keepFilename);
            $stmtD->execute();
            $stmtD->close();
        }
        foreach ($paths as $p) {
            $full = rtrim($uploadsDir, '/\\') . '/' . basename($p);
            if (is_file($full)) @unlink($full);
        }
    };

    $insertDoc = function (int $vehicleId, string $docType, string $filePath, ?string $expiry) use ($db, $plate, $tryInsertVehicleDoc, $dedupeVehicleDocs): void {
        $ok = $tryInsertVehicleDoc($vehicleId, $docType, $filePath, $expiry);
        if ($ok) {
            $dedupeVehicleDocs($vehicleId, $docType, $filePath);
            return;
        }
        $hasExpiry = false;
        $r = $db->query("SHOW COLUMNS FROM documents LIKE 'expiry_date'");
        if ($r && $r->num_rows > 0) $hasExpiry = true;
        if ($hasExpiry) {
            $stmtD = $db->prepare("INSERT INTO documents (plate_number, type, file_path, expiry_date) VALUES (?, ?, ?, ?)");
            if (!$stmtD) throw new Exception('db_prepare_failed');
            $typeLower = strtolower($docType) === 'insurance' ? 'insurance' : strtolower($docType);
            $stmtD->bind_param('ssss', $plate, $typeLower, $filePath, $expiry);
            if (!$stmtD->execute()) { $stmtD->close(); throw new Exception('db_insert_failed'); }
            $stmtD->close();
        } else {
            $stmtD = $db->prepare("INSERT INTO documents (plate_number, type, file_path) VALUES (?, ?, ?)");
            if (!$stmtD) throw new Exception('db_prepare_failed');
            $typeLower = strtolower($docType) === 'insurance' ? 'insurance' : strtolower($docType);
            $stmtD->bind_param('sss', $plate, $typeLower, $filePath);
            if (!$stmtD->execute()) { $stmtD->close(); throw new Exception('db_insert_failed'); }
            $stmtD->close();
        }
    };

    $crPath = $moveAndScan($crFile, 'cr');
    $insertDoc($vehicleId, 'CR', $crPath, null);

    if ($hasOrUpload) {
        $orPath = $moveAndScan($orFile, 'or');
        $insertDoc($vehicleId, 'OR', $orPath, $orExpiry !== '' ? $orExpiry : null);
    }

    $db->commit();

    if ($assisted) {
        tmm_audit_event($db, 'PUV_ASSISTED_VEHICLE_ENCODED', 'Vehicle', (string)$vehicleId, ['assisted' => true, 'plate_number' => $plate]);
    }
    echo json_encode(['ok' => true, 'vehicle_id' => $vehicleId, 'plate_number' => $plate, 'status' => $vehicleStatus, 'inspection_status' => $inspectionStatus, 'assisted' => $assisted]);
} catch (Throwable $e) {
    if (defined('TMM_TEST')) {
        throw $e;
    }
    if (isset($db) && $db instanceof mysqli) {
        try { $db->rollback(); } catch (Throwable $_) {}
    }
    $rawMsg = (string)$e->getMessage();
    $safe = [
        'invalid_plate',
        'missing_vehicle_type',
        'invalid_engine_no',
        'invalid_chassis_no',
        'invalid_or_number',
        'invalid_cr_number',
        'invalid_cr_issue_date',
        'cr_required',
        'or_expiry_required',
        'ocr_confirmation_required',
        'db_prepare_failed',
        'db_insert_failed',
        'upload_move_failed',
        'invalid_file_type',
        'file_failed_security_scan',
        'server_error',
        'db_connect_error',
    ];
    $errCode = in_array($rawMsg, $safe, true) ? $rawMsg : 'server_error';
    $status = in_array($errCode, ['invalid_plate','missing_vehicle_type','invalid_engine_no','invalid_chassis_no','invalid_or_number','invalid_cr_number','invalid_cr_issue_date','cr_required','or_expiry_required','ocr_confirmation_required'], true) ? 400 : 500;
    http_response_code($status);
    error_log('create_vehicle.php error: ' . get_class($e) . ' ' . $rawMsg);
    echo json_encode(['ok' => false, 'error' => $errCode]);
}
?> 
