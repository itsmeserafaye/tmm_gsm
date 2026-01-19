<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();

header('Content-Type: application/json');
require_permission('module2.franchises.manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$app_id = (int)($_POST['application_id'] ?? 0);
$officer = trim($_POST['officer_name'] ?? 'System');
$notes = trim($_POST['notes'] ?? '');

if ($app_id === 0) {
    echo json_encode(['ok' => false, 'error' => 'Missing Application ID']);
    exit;
}

function tmm_endorse_response(mysqli $db, array $app, string $message, ?string $permitNoOverride = null): void {
    $appId = (int)($app['application_id'] ?? 0);
    $frRef = trim((string)($app['franchise_ref_number'] ?? ''));

    $permitNo = $permitNoOverride;
    if ($permitNo === null && $appId > 0) {
        $stmtE = $db->prepare("SELECT permit_number FROM endorsement_records WHERE application_id=? ORDER BY endorsement_id DESC LIMIT 1");
        if ($stmtE) {
            $stmtE->bind_param('i', $appId);
            $stmtE->execute();
            $rowE = $stmtE->get_result()->fetch_assoc();
            $stmtE->close();
            if ($rowE && isset($rowE['permit_number'])) $permitNo = (string)$rowE['permit_number'];
        }
    }

    $plate = null;
    if ($frRef !== '') {
      $stmtP = $db->prepare("SELECT plate_number FROM vehicles WHERE franchise_id=? ORDER BY plate_number ASC LIMIT 1");
      if ($stmtP) {
        $stmtP->bind_param('s', $frRef);
        $stmtP->execute();
        $rowP = $stmtP->get_result()->fetch_assoc();
        $stmtP->close();
        if ($rowP && isset($rowP['plate_number'])) $plate = (string)$rowP['plate_number'];
      }
    }

    $routeCode = null;
    $routeId = trim((string)($app['route_ids'] ?? ''));
    if ($routeId !== '') {
      $stmtR = $db->prepare("SELECT route_code FROM lptrp_routes WHERE id=? LIMIT 1");
      if ($stmtR) {
        $stmtR->bind_param('s', $routeId);
        $stmtR->execute();
        $rowR = $stmtR->get_result()->fetch_assoc();
        $stmtR->close();
        if ($rowR && isset($rowR['route_code'])) $routeCode = (string)$rowR['route_code'];
      }
    }

    echo json_encode([
      'ok' => true,
      'message' => $message,
      'permit_no' => $permitNo,
      'franchise_ref_number' => $frRef,
      'plate_number' => $plate,
      'route_code' => $routeCode,
    ]);
    exit;
}

$db->begin_transaction();
try {
    $stmtA = $db->prepare("SELECT application_id, franchise_ref_number, route_ids, vehicle_count, status FROM franchise_applications WHERE application_id=? FOR UPDATE");
    if (!$stmtA) {
        throw new Exception('db_prepare_failed');
    }
    $stmtA->bind_param('i', $app_id);
    $stmtA->execute();
    $app = $stmtA->get_result()->fetch_assoc();
    $stmtA->close();

    if (!$app) {
        $db->rollback();
        echo json_encode(['ok' => false, 'error' => 'Application not found']);
        exit;
    }

    if (($app['status'] ?? '') === 'Endorsed') {
        $db->commit();
        tmm_endorse_response($db, $app, 'Application already endorsed');
    }

    $permit_no = "PERMIT-" . date('Y') . "-" . str_pad((string)$app_id, 4, '0', STR_PAD_LEFT);

    $stmtIns = $db->prepare("INSERT INTO endorsement_records (application_id, issued_date, permit_number)
                             VALUES (?, CURDATE(), ?)
                             ON DUPLICATE KEY UPDATE issued_date=issued_date");
    if (!$stmtIns) {
        throw new Exception('db_prepare_failed');
    }
    $stmtIns->bind_param('is', $app_id, $permit_no);
    if (!$stmtIns->execute()) {
        throw new Exception('insert_failed');
    }
    $stmtIns->close();

    $db->query("UPDATE franchise_applications SET status = 'Endorsed' WHERE application_id = $app_id");

    $route_id = (string)($app['route_ids'] ?? '');
    $count = (int)($app['vehicle_count'] ?? 0);
    if ($route_id !== '' && $count > 0) {
        $stmtL = $db->prepare("UPDATE lptrp_routes SET current_vehicle_count = current_vehicle_count + ? WHERE id = ?");
        if ($stmtL) {
            $stmtL->bind_param('is', $count, $route_id);
            $stmtL->execute();
            $stmtL->close();
        }
    }

    $frRef = trim((string)($app['franchise_ref_number'] ?? ''));
    if ($frRef !== '') {
        $stmtVeh = $db->prepare("UPDATE vehicles SET status='Active' WHERE franchise_id=? AND (status IS NULL OR status='' OR status='Suspended')");
        if ($stmtVeh) {
            $stmtVeh->bind_param('s', $frRef);
            $stmtVeh->execute();
            $stmtVeh->close();
        }
    }

    $db->commit();
    tmm_endorse_response($db, $app, 'Endorsement issued successfully');
} catch (Throwable $e) {
    $db->rollback();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_error']);
}
?>
