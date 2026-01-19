<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_permission('module4.inspections.manage');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$db = db();
$scheduleId = (int) ($_POST['schedule_id'] ?? 0);
$date = date('Y-m-d H:i:s');

if ($scheduleId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Invalid schedule ID']);
    exit;
}

$cr = isset($_POST['cr_verified']) ? 1 : 0;
$or = isset($_POST['or_verified']) ? 1 : 0;

// Update schedule
$stmt = $db->prepare("UPDATE inspection_schedules SET cr_verified=?, or_verified=?, updated_at=? WHERE schedule_id=?");
if (!$stmt) {
    echo json_encode(['ok' => false, 'error' => $db->error]);
    exit;
}

$stmt->bind_param('iisi', $cr, $or, $date, $scheduleId);
if (!$stmt->execute()) {
    echo json_encode(['ok' => false, 'error' => $stmt->error]);
    exit;
}

// Check if both verified, if so, maybe update status? 
// Logic from submodule1: status stays 'Pending Verification' until explicit status change? 
// No, usually verification is a step. But let's just save the flags.
// If both verified, we might want to ensure status allows inspection.
// For now, just save flags. User can proceed if UI updates.

echo json_encode(['ok' => true]);
