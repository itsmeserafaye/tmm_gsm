<?php
ob_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

// Clear any startup output (warnings, etc)
$startup_output = ob_get_clean();

header('Content-Type: application/json');

if (strlen($startup_output) > 0) {
    // Log warnings but try to proceed
    // If it was a fatal error, we wouldn't be here (script would have stopped)
    // So these are likely warnings/notices.
}

$db = db();
require_permission('module1.vehicles.write');

// 1. Get all vehicles with operator info
$stmt = $db->prepare("SELECT id, plate_number, operator_name FROM vehicles WHERE operator_name IS NOT NULL AND operator_name != ''");
if (!$stmt) {
    echo json_encode(['ok' => false, 'error' => $db->error, 'debug_log' => strip_tags($startup_output)]);
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
    if ($opName === '') continue;

    // 2. Find matching operator portal user
    // Match by full_name OR association_name (case-insensitive usually, but explicit)
    $stmtP = $db->prepare("SELECT id, full_name, association_name FROM operator_portal_users WHERE TRIM(full_name)=? OR TRIM(association_name)=? LIMIT 1");
    if (!$stmtP) continue;
    
    $stmtP->bind_param('ss', $opName, $opName);
    $stmtP->execute();
    $resP = $stmtP->get_result();
    $user = $resP->fetch_assoc();
    $stmtP->close();

    if (!$user) {
        $notFound++;
        continue;
    }

    $userId = (int)$user['id'];

    // 3. Check if already linked
    $stmtChk = $db->prepare("SELECT id FROM operator_portal_user_plates WHERE user_id=? AND plate_number=? LIMIT 1");
    $stmtChk->bind_param('is', $userId, $plate);
    $stmtChk->execute();
    $exists = $stmtChk->get_result()->fetch_assoc();
    $stmtChk->close();

    if ($exists) {
        $skipped++;
    } else {
        // 4. Insert link
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
        'failed' => $failed
    ],
    'debug_log' => strip_tags($startup_output)
]);

