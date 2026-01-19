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
$stmt = $db->prepare("UPDATE inspection_schedules SET cr_verified=?, or_verified=? WHERE schedule_id=?");
if (!$stmt) {
    echo json_encode(['ok' => false, 'error' => $db->error]);
    exit;
}

$stmt->bind_param('iii', $cr, $or, $scheduleId);
if (!$stmt->execute()) {
    echo json_encode(['ok' => false, 'error' => $stmt->error]);
    exit;
}

// Apply real-world flow: verification gates assignment and inspection.
$stmtS = $db->prepare("SELECT status, inspector_id FROM inspection_schedules WHERE schedule_id=? LIMIT 1");
if (!$stmtS) {
    echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
    exit;
}
$stmtS->bind_param('i', $scheduleId);
$stmtS->execute();
$rowS = $stmtS->get_result()->fetch_assoc();
$stmtS->close();

$currentStatus = (string)($rowS['status'] ?? '');
$inspectorId = (int)($rowS['inspector_id'] ?? 0);

$finalStatus = $currentStatus;
if ($currentStatus !== 'Completed' && $currentStatus !== 'Cancelled') {
    if ($cr === 1 && $or === 1) {
        if ($inspectorId > 0) {
            $finalStatus = ($currentStatus === 'Rescheduled') ? 'Rescheduled' : 'Scheduled';
        } else {
            $finalStatus = 'Pending Assignment';
        }
    } else {
        $finalStatus = 'Pending Verification';
    }
}

if ($finalStatus !== $currentStatus) {
    $stmtU = $db->prepare("UPDATE inspection_schedules SET status=? WHERE schedule_id=?");
    if ($stmtU) {
        $stmtU->bind_param('si', $finalStatus, $scheduleId);
        $stmtU->execute();
        $stmtU->close();
    }
}

echo json_encode(['ok' => true, 'status' => $finalStatus, 'cr_verified' => $cr, 'or_verified' => $or]);
