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

$operator_id = (int)($_POST['operator_id'] ?? 0);
$route_id = (int)($_POST['route_id'] ?? 0);
$vehicle_count = (int)($_POST['vehicle_count'] ?? 0);
$representative_name = trim((string)($_POST['representative_name'] ?? ''));

if ($operator_id <= 0 || $route_id <= 0 || $vehicle_count <= 0) {
    echo json_encode(['ok' => false, 'error' => 'missing_required_fields']);
    exit;
}

try {
    $stmtO = $db->prepare("SELECT id, operator_type, status FROM operators WHERE id=? LIMIT 1");
    if (!$stmtO) throw new Exception('db_prepare_failed');
    $stmtO->bind_param('i', $operator_id);
    $stmtO->execute();
    $op = $stmtO->get_result()->fetch_assoc();
    $stmtO->close();
    if (!$op) {
        echo json_encode(['ok' => false, 'error' => 'operator_not_found']);
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

    $stmt = $db->prepare("INSERT INTO franchise_applications (franchise_ref_number, operator_id, route_id, route_ids, vehicle_count, representative_name, status, submitted_at)
                          VALUES (?, ?, ?, ?, ?, ?, 'Submitted', NOW())");
    if (!$stmt) throw new Exception('db_prepare_failed');
    $stmt->bind_param('siisis', $franchise_ref, $operator_id, $route_id, $route_ids_val, $vehicle_count, $representative_name);
    $execOk = $stmt->execute();
} catch (mysqli_sql_exception $e) {
    if ($e->getCode() === 1062) {
        echo json_encode(['ok' => false, 'error' => 'duplicate_reference']);
        exit;
    }
    echo json_encode(['ok' => false, 'error' => 'db_error']);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_error']);
    exit;
}

if ($execOk) {
    $app_id = $db->insert_id;
    echo json_encode([
        'ok' => true,
        'application_id' => $app_id,
        'franchise_ref_number' => $franchise_ref,
        'message' => "Application submitted. ID: APP-$app_id"
    ]);
} else {
    echo json_encode(['ok' => false, 'error' => $db->error]);
}
?>
