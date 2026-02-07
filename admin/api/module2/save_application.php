<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/util.php';
$db = db();

header('Content-Type: application/json');
require_permission('module2.franchises.manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$operator_id = (int)($_POST['operator_id'] ?? 0);
$route_id = (int)($_POST['route_id'] ?? 0);
$vehicle_count = (int)($_POST['vehicle_count'] ?? 0);
$representative_name = trim((string)($_POST['representative_name'] ?? ''));
$assisted = (int)($_POST['assisted'] ?? 0) === 1;

$hasUpload = isset($_FILES['declared_fleet_doc']) && is_array($_FILES['declared_fleet_doc']);

if ($operator_id <= 0 || $route_id <= 0 || $vehicle_count <= 0) {
    echo json_encode(['ok' => false, 'error' => 'missing_required_fields']);
    exit;
}

try {
    $stmtO = $db->prepare("SELECT id, operator_type, status, verification_status, workflow_status FROM operators WHERE id=? LIMIT 1");
    if (!$stmtO) throw new Exception('db_prepare_failed');
    $stmtO->bind_param('i', $operator_id);
    $stmtO->execute();
    $op = $stmtO->get_result()->fetch_assoc();
    $stmtO->close();
    if (!$op) {
        echo json_encode(['ok' => false, 'error' => 'operator_not_found']);
        exit;
    }
    $opStatus = (string)($op['status'] ?? '');
    $wfStatus = (string)($op['workflow_status'] ?? '');
    $vsStatus = (string)($op['verification_status'] ?? '');
    if ($opStatus === 'Inactive' || $wfStatus === 'Inactive' || $vsStatus === 'Inactive') {
        echo json_encode(['ok' => false, 'error' => 'operator_inactive']);
        exit;
    }

    $stmtR = $db->prepare("SELECT id, route_id, status FROM routes WHERE id=? LIMIT 1");
    if (!$stmtR) throw new Exception('db_prepare_failed');
    $stmtR->bind_param('i', $route_id);
    $stmtR->execute();
    $route = $stmtR->get_result()->fetch_assoc();
    $stmtR->close();
    if (!$route) {
        echo json_encode(['ok' => false, 'error' => 'route_not_found']);
        exit;
    }
    if (($route['status'] ?? '') !== 'Active') {
        echo json_encode(['ok' => false, 'error' => 'route_inactive']);
        exit;
    }

    $franchise_ref = 'APP-' . date('Ymd') . '-' . substr(uniqid(), -6);
    $route_ids_val = (string)$route_id;
    $submittedByUserId = (int)($_SESSION['user_id'] ?? 0);
    $submittedByName = trim((string)($_SESSION['name'] ?? ($_SESSION['full_name'] ?? '')));
    if ($submittedByName === '') $submittedByName = trim((string)($_SESSION['email'] ?? ($_SESSION['user_email'] ?? '')));
    if ($submittedByName === '') $submittedByName = 'Admin';
    $submittedChannel = $assisted ? 'admin_assisted' : 'admin';

    $db->begin_transaction();

    $stmt = $db->prepare("INSERT INTO franchise_applications
                          (franchise_ref_number, operator_id, route_id, route_ids, vehicle_count, representative_name, status, submitted_at, submitted_by_user_id, submitted_by_name, submitted_channel)
                          VALUES (?, ?, ?, ?, ?, ?, 'Submitted', NOW(), ?, ?, ?)");
    if (!$stmt) throw new Exception('db_prepare_failed');
    $stmt->bind_param('siisisisss', $franchise_ref, $operator_id, $route_id, $route_ids_val, $vehicle_count, $representative_name, $submittedByUserId, $submittedByName, $submittedChannel);
    $execOk = $stmt->execute();
} catch (mysqli_sql_exception $e) {
    $db->rollback();
    if ($e->getCode() === 1062) {
        echo json_encode(['ok' => false, 'error' => 'duplicate_reference']);
        exit;
    }
    echo json_encode(['ok' => false, 'error' => 'db_error']);
    exit;
} catch (Throwable $e) {
    $db->rollback();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_error']);
    exit;
}

if ($execOk) {
    try {
        $app_id = $db->insert_id;
        if ($hasUpload) {
            $f = $_FILES['declared_fleet_doc'];
            $err = (int)($f['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($err !== UPLOAD_ERR_NO_FILE) {
                if ($err !== UPLOAD_ERR_OK) throw new Exception('declared_fleet_upload_error');
                $tmp = (string)($f['tmp_name'] ?? '');
                $orig = (string)($f['name'] ?? '');
                if ($tmp === '' || $orig === '' || !is_uploaded_file($tmp)) throw new Exception('declared_fleet_invalid_file');

                $allowedExt = ['pdf','xlsx','xls','csv'];
                $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                if ($ext === '' || !in_array($ext, $allowedExt, true)) throw new Exception('declared_fleet_invalid_type');

                $uploadDir = __DIR__ . '/../../uploads/franchise/';
                if (!is_dir($uploadDir)) @mkdir($uploadDir, 0777, true);
                $filename = 'APP' . $app_id . '_declared_fleet_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $dest = $uploadDir . $filename;
                if (!move_uploaded_file($tmp, $dest)) throw new Exception('declared_fleet_upload_failed');

                $safe = tmm_scan_file_for_viruses($dest);
                if (!$safe) {
                    if (is_file($dest)) @unlink($dest);
                    throw new Exception('declared_fleet_failed_scan');
                }

                $dbPath = 'franchise/' . $filename;
                $ins = $db->prepare("INSERT INTO documents (plate_number, type, file_path, uploaded_by, application_id) VALUES (NULL, 'Declared Fleet', ?, 'admin', ?)");
                if (!$ins) {
                    if (is_file($dest)) @unlink($dest);
                    throw new Exception('db_prepare_failed');
                }
                $ins->bind_param('si', $dbPath, $app_id);
                if (!$ins->execute()) {
                    $ins->close();
                    if (is_file($dest)) @unlink($dest);
                    throw new Exception('db_error');
                }
                $ins->close();
            }
        }

        $already = false;
        $chkFleet = $db->prepare("SELECT 1 FROM documents WHERE application_id=? AND type='Declared Fleet' LIMIT 1");
        if ($chkFleet) {
            $chkFleet->bind_param('i', $app_id);
            $chkFleet->execute();
            $already = (bool)$chkFleet->get_result()->fetch_row();
            $chkFleet->close();
        }
        if (!$already && (!$hasUpload || (int)(($_FILES['declared_fleet_doc']['error'] ?? UPLOAD_ERR_NO_FILE)) === UPLOAD_ERR_NO_FILE)) {
            $stmtFleet = $db->prepare("SELECT file_path, doc_status, is_verified, remarks
                                       FROM operator_documents
                                       WHERE operator_id=?
                                         AND doc_type='Others'
                                         AND (doc_status='Verified' OR is_verified=1)
                                         AND LOWER(COALESCE(remarks,'')) LIKE '%declared fleet%'
                                       ORDER BY uploaded_at DESC, doc_id DESC
                                       LIMIT 1");
            if ($stmtFleet) {
                $stmtFleet->bind_param('i', $operator_id);
                $stmtFleet->execute();
                $rowFleet = $stmtFleet->get_result()->fetch_assoc();
                $stmtFleet->close();
                $fp = $rowFleet ? trim((string)($rowFleet['file_path'] ?? '')) : '';
                if ($fp !== '') {
                    $hasVerifiedCol = false;
                    $col = $db->query("SHOW COLUMNS FROM documents LIKE 'verified'");
                    if ($col && $col->num_rows > 0) $hasVerifiedCol = true;
                    if ($hasVerifiedCol) {
                        $ins = $db->prepare("INSERT INTO documents (plate_number, type, file_path, uploaded_by, application_id, verified) VALUES (NULL, 'Declared Fleet', ?, 'admin', ?, 1)");
                    } else {
                        $ins = $db->prepare("INSERT INTO documents (plate_number, type, file_path, uploaded_by, application_id) VALUES (NULL, 'Declared Fleet', ?, 'admin', ?)");
                    }
                    if ($ins) {
                        $ins->bind_param('si', $fp, $app_id);
                        $ins->execute();
                        $ins->close();
                    }
                }
            }
        }

        $db->commit();
        tmm_audit_event($db, 'FRANCHISE_APPLICATION_SUBMITTED', 'FranchiseApplication', (string)$app_id, ['channel' => $submittedChannel, 'operator_id' => $operator_id, 'route_id' => $route_id, 'vehicle_count' => $vehicle_count]);
        echo json_encode([
            'ok' => true,
            'application_id' => $app_id,
            'franchise_ref_number' => $franchise_ref,
            'message' => "Application submitted. ID: APP-$app_id"
        ]);
    } catch (Throwable $e) {
        $db->rollback();
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => $e->getMessage() ?: 'submit_failed']);
    }
} else {
    $db->rollback();
    echo json_encode(['ok' => false, 'error' => $db->error]);
}
?>
