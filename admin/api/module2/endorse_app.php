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

    $permit_no = "PERMIT-" . date('Y') . "-" . str_pad((string)$app_id, 4, '0', STR_PAD_LEFT);
    $stmtIns = $db->prepare("INSERT INTO endorsement_records (application_id, issued_date, permit_number)
                             VALUES (?, CURDATE(), ?)
                             ON DUPLICATE KEY UPDATE issued_date=VALUES(issued_date), permit_number=VALUES(permit_number)");
    if (!$stmtIns) throw new Exception('db_prepare_failed');
    $stmtIns->bind_param('is', $app_id, $permit_no);
    if (!$stmtIns->execute()) throw new Exception('insert_failed');
    $stmtIns->close();

    $stmtU = $db->prepare("UPDATE franchise_applications SET status='LGU-Endorsed', endorsed_at=NOW(), remarks=CASE WHEN ?<>'' THEN ? ELSE remarks END WHERE application_id=?");
    if (!$stmtU) throw new Exception('db_prepare_failed');
    $stmtU->bind_param('ssi', $notes, $notes, $app_id);
    $stmtU->execute();
    $stmtU->close();

    $db->commit();
    echo json_encode(['ok' => true, 'message' => 'Endorsement issued successfully', 'permit_number' => $permit_no]);
} catch (Throwable $e) {
    $db->rollback();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_error']);
}
?>
