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

    $allowedEndorse = ['Endorsed (Conditional)','Endorsed (Complete)','Rejected'];
    $endorsementStatus = 'Endorsed (Complete)';
    foreach ($allowedEndorse as $opt) {
        if (strcasecmp($endorsementStatusRaw, $opt) === 0) { $endorsementStatus = $opt; break; }
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
    $stmtU = $db->prepare("UPDATE franchise_applications SET status=?, endorsed_at=NOW(), remarks=CASE WHEN ?<>'' THEN ? ELSE remarks END WHERE application_id=?");
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
