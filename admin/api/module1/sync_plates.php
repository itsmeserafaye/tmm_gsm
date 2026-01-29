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

$stmt = $db->prepare("SELECT id, plate_number, operator_name FROM vehicles WHERE operator_name IS NOT NULL AND operator_name != ''");
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
    $opName = trim($v['operator_name']);
    if ($opName === '') {
        continue;
    }

    $stmtP = $db->prepare("SELECT id, full_name, association_name FROM operator_portal_users WHERE full_name=? OR association_name=? LIMIT 1");
    if (!$stmtP) {
        continue;
    }

    $stmtP->bind_param('ss', $opName, $opName);
    $stmtP->execute();
    $resP = $stmtP->get_result();
    $user = $resP->fetch_assoc();
    $stmtP->close();

    if (!$user) {
        $notFound++;
        continue;
    }

    $userId = (int) $user['id'];

    $stmtChk = $db->prepare("SELECT id FROM operator_portal_user_plates WHERE user_id=? AND plate_number=? LIMIT 1");
    $stmtChk->bind_param('is', $userId, $plate);
    $stmtChk->execute();
    $exists = $stmtChk->get_result()->fetch_assoc();
    $stmtChk->close();

    if ($exists) {
        $skipped++;
    } else {
        $stmtIns = $db->prepare("INSERT INTO operator_portal_user_plates (user_id, plate_number) VALUES (?, ?)");
        if ($stmtIns) {
            $stmtIns->bind_param('is', $userId, $plate);
            if ($stmtIns->execute()) {
                $synced++;
            } else {
                $failed++;
            }
            $stmtIns->close();
        } else {
            $failed++;
        }
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
