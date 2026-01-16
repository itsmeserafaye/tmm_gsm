<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
header('Content-Type: application/json');
require_permission('module4.inspections.manage');
$plate = trim($_POST['plate_number'] ?? '');
$scheduledAt = trim($_POST['scheduled_at'] ?? '');
$location = trim($_POST['location'] ?? '');
$inspectorId = isset($_POST['inspector_id']) ? (int)$_POST['inspector_id'] : 0;
$inspectorLabel = trim($_POST['inspector_label'] ?? '');
$inspectionType = trim($_POST['inspection_type'] ?? 'Annual');
$requestedBy = trim($_POST['requested_by'] ?? '');
$contactPerson = trim($_POST['contact_person'] ?? '');
$contactNumber = trim($_POST['contact_number'] ?? '');
$crVerified = !empty($_POST['cr_verified']) ? 1 : 0;
$orVerified = !empty($_POST['or_verified']) ? 1 : 0;
if ($plate === '' || $scheduledAt === '' || $location === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_fields']);
  exit;
}
$allowedTypes = ['Annual','Reinspection','Compliance','Special'];
if (!in_array($inspectionType, $allowedTypes, true)) {
  $inspectionType = 'Annual';
}
if ($inspectorId > 0) {
  $inspStmt = $db->prepare("SELECT officer_id, active_status FROM officers WHERE officer_id=?");
  if ($inspStmt) {
    $inspStmt->bind_param('i', $inspectorId);
    $inspStmt->execute();
    $inspRow = $inspStmt->get_result()->fetch_assoc();
    if (!$inspRow || (int)($inspRow['active_status'] ?? 0) !== 1) {
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'inspector_inactive']);
      exit;
    }
  }
}
$hasVerifiedDocs = ($crVerified === 1 && $orVerified === 1);
$hasInspector = ($inspectorId > 0 || $inspectorLabel !== '');
if (!$hasVerifiedDocs) {
  $status = 'Pending Verification';
} elseif ($hasVerifiedDocs && !$hasInspector) {
  $status = 'Pending Assignment';
} else {
  $status = 'Scheduled';
}
$stmt = $db->prepare("INSERT INTO inspection_schedules (plate_number, scheduled_at, location, inspection_type, requested_by, contact_person, contact_number, inspector_id, inspector_label, status, cr_verified, or_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$inspectorIdDb = $inspectorId > 0 ? $inspectorId : null;
$stmt->bind_param('sssssssissii', $plate, $scheduledAt, $location, $inspectionType, $requestedBy, $contactPerson, $contactNumber, $inspectorIdDb, $inspectorLabel, $status, $crVerified, $orVerified);
$ok = $stmt->execute();
if (!$ok) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'insert_failed']);
  exit;
}
$scheduleId = (int)$stmt->insert_id;
echo json_encode(['ok' => true, 'schedule_id' => $scheduleId]);
?> 
