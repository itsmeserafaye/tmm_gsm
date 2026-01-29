<?php
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

$db = db();

// Manual permission check to avoid redirection/HTML response
if (empty($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

if (!function_exists('has_permission')) {
    // Should be loaded by auth.php, but just in case
    echo json_encode(['ok' => false, 'error' => 'server_error_auth_missing']);
    exit;
}

if (!has_permission('module1.vehicles.write')) {
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
}

// 1. Get all vehicles with operator info

$stmt = $db->prepare("SELECT id, plate_number, operator_name, operator_id, current_operator_id FROM vehicles WHERE record_status='Linked' AND ((operator_name IS NOT NULL AND operator_name != '') OR (operator_id IS NOT NULL AND operator_id>0) OR (current_operator_id IS NOT NULL AND current_operator_id>0))");
if (!$stmt) {
    echo json_encode(['ok' => false, 'error' => $db->error]);
    exit;
}

$stmt->execute();
$res = $stmt->get_result();
$vehicles = [];
while ($row = $res->fetch_assoc()) {
    $vehicles[] = $row;
}
$stmt->close();

$synced = 0;
$skipped = 0;
$failed = 0;
$notFound = 0;

foreach ($vehicles as $v) {
    $plate = $v['plate_number'];
    $opName = trim((string)($v['operator_name'] ?? ''));
    $opId = (int)($v['current_operator_id'] ?? 0);
    if ($opId <= 0) $opId = (int)($v['operator_id'] ?? 0);

    $user = null;
    if ($opId > 0) {
        $stmtE = $db->prepare("SELECT email FROM operators WHERE id=? LIMIT 1");
        if ($stmtE) {
            $stmtE->bind_param('i', $opId);
            $stmtE->execute();
            $rowE = $stmtE->get_result()->fetch_assoc();
            $stmtE->close();
            $email = strtolower(trim((string)($rowE['email'] ?? '')));
            if ($email !== '') {
                $stmtP = $db->prepare("SELECT id, full_name, association_name FROM operator_portal_users WHERE email=? LIMIT 1");
                if ($stmtP) {
                    $stmtP->bind_param('s', $email);
                    $stmtP->execute();
                    $resP = $stmtP->get_result();
                    $user = $resP->fetch_assoc();
                    $stmtP->close();
                }
            }
        }
    }

    if (!$user && $opName !== '') {
        $stmtP = $db->prepare("SELECT id, full_name, association_name FROM operator_portal_users WHERE full_name=? OR association_name=? LIMIT 1");
        if ($stmtP) {
            $stmtP->bind_param('ss', $opName, $opName);
            $stmtP->execute();
            $resP = $stmtP->get_result();
            $user = $resP->fetch_assoc();
            $stmtP->close();
        }
    }

    if (!$user) {
        $notFound++;
        continue;
    }

    $userId = (int) $user['id'];

    $stmtIns = $db->prepare("INSERT INTO operator_portal_user_plates (user_id, plate_number) VALUES (?, ?) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id)");
    if ($stmtIns) {
        $stmtIns->bind_param('is', $userId, $plate);
        if ($stmtIns->execute()) {
            if ($stmtIns->affected_rows > 0) $synced++;
            else $skipped++;
        } else {
            $failed++;
        }
        $stmtIns->close();
    } else {
        $failed++;
    }
}

echo json_encode([
    'ok' => true,
    'stats' => [
        'processed' => count($vehicles),
        'synced' => $synced,
        'skipped' => $skipped,
        'not_found' => $notFound,
        'failed' => $failed,
    ],
]);
