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

    if (($app['status'] ?? '') === 'Endorsed') {
        $db->commit();
        echo json_encode(['ok' => true, 'message' => 'Application already endorsed']);
        exit;
    }
    if (($app['status'] ?? '') !== 'Submitted') {
        $db->rollback();
        echo json_encode(['ok' => false, 'error' => 'invalid_status']);
        exit;
    }

    $opId = (int)($app['operator_id'] ?? 0);
    $stmtO = $db->prepare("SELECT status, verification_status FROM operators WHERE id=? LIMIT 1");
    if (!$stmtO) throw new Exception('db_prepare_failed');
    $stmtO->bind_param('i', $opId);
    $stmtO->execute();
    $op = $stmtO->get_result()->fetch_assoc();
    $stmtO->close();
    if (!$op) {
        $db->rollback();
        echo json_encode(['ok' => false, 'error' => 'operator_not_found']);
        exit;
    }
    if (($op['verification_status'] ?? '') !== 'Verified') {
        $db->rollback();
        echo json_encode(['ok' => false, 'error' => 'operator_not_verified']);
        exit;
    }

    $routeId = (int)($app['route_id'] ?? 0);
    if ($routeId > 0) {
        $stmtR = $db->prepare("SELECT authorized_units, status FROM routes WHERE id=? LIMIT 1");
        if (!$stmtR) throw new Exception('db_prepare_failed');
        $stmtR->bind_param('i', $routeId);
        $stmtR->execute();
        $route = $stmtR->get_result()->fetch_assoc();
        $stmtR->close();
        if (!$route) {
            $db->rollback();
            echo json_encode(['ok' => false, 'error' => 'route_not_found']);
            exit;
        }
        if (($route['status'] ?? '') !== 'Active') {
            $db->rollback();
            echo json_encode(['ok' => false, 'error' => 'route_inactive']);
            exit;
        }
        $cap = (int)($route['authorized_units'] ?? 0);
        if ($cap > 0) {
            $stmtC = $db->prepare("SELECT COALESCE(SUM(vehicle_count),0) AS c FROM franchise_applications WHERE route_id=? AND status IN ('Endorsed','Approved')");
            if (!$stmtC) throw new Exception('db_prepare_failed');
            $stmtC->bind_param('i', $routeId);
            $stmtC->execute();
            $cur = $stmtC->get_result()->fetch_assoc();
            $stmtC->close();
            $curCount = (int)($cur['c'] ?? 0);
            $want = (int)($app['vehicle_count'] ?? 0);
            if ($curCount + $want > $cap) {
                $db->rollback();
                echo json_encode(['ok' => false, 'error' => 'route_over_capacity']);
                exit;
            }
        }
    }

    $permit_no = "PERMIT-" . date('Y') . "-" . str_pad((string)$app_id, 4, '0', STR_PAD_LEFT);
    $stmtIns = $db->prepare("INSERT INTO endorsement_records (application_id, issued_date, permit_number)
                             VALUES (?, CURDATE(), ?)
                             ON DUPLICATE KEY UPDATE issued_date=VALUES(issued_date), permit_number=VALUES(permit_number)");
    if (!$stmtIns) throw new Exception('db_prepare_failed');
    $stmtIns->bind_param('is', $app_id, $permit_no);
    if (!$stmtIns->execute()) throw new Exception('insert_failed');
    $stmtIns->close();

    $stmtU = $db->prepare("UPDATE franchise_applications SET status='Endorsed', endorsed_at=NOW(), remarks=CASE WHEN ?<>'' THEN ? ELSE remarks END WHERE application_id=?");
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
