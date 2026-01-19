<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
header('Content-Type: application/json');
require_permission('module4.schedule');
$vehicleId = (int)($_POST['vehicle_id'] ?? 0);
$plate = trim((string)($_POST['plate_number'] ?? ($_POST['plate_no'] ?? '')));
$scheduledAt = trim((string)($_POST['scheduled_at'] ?? ($_POST['schedule_date'] ?? '')));
$scheduleDate = trim((string)($_POST['schedule_date'] ?? $scheduledAt));
$location = trim($_POST['location'] ?? '');
$inspectorId = isset($_POST['inspector_id']) ? (int)$_POST['inspector_id'] : 0;
$inspectorLabel = trim($_POST['inspector_label'] ?? '');
$inspectionType = trim($_POST['inspection_type'] ?? 'Annual');
$requestedBy = trim($_POST['requested_by'] ?? '');
$contactPerson = trim($_POST['contact_person'] ?? '');
$contactNumber = trim($_POST['contact_number'] ?? '');
$crVerified = !empty($_POST['cr_verified']) ? 1 : 0;
$orVerified = !empty($_POST['or_verified']) ? 1 : 0;
if (($vehicleId <= 0 && $plate === '') || $scheduledAt === '' || $location === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_fields']);
  exit;
}
$plateDb = $plate;
if ($vehicleId > 0) {
  $stmtV = $db->prepare("SELECT id, plate_number FROM vehicles WHERE id=? LIMIT 1");
  if (!$stmtV) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
    exit;
  }
  $stmtV->bind_param('i', $vehicleId);
  $stmtV->execute();
  $veh = $stmtV->get_result()->fetch_assoc();
  $stmtV->close();
  if (!$veh) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'vehicle_not_found']);
    exit;
  }
  $plateDb = (string)($veh['plate_number'] ?? '');
  if ($plateDb === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'vehicle_missing_plate']);
    exit;
  }
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
$stmt = $db->prepare("INSERT INTO inspection_schedules (plate_number, vehicle_id, scheduled_at, schedule_date, location, inspection_type, requested_by, contact_person, contact_number, inspector_id, inspector_label, status, cr_verified, or_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$inspectorIdDb = $inspectorId > 0 ? $inspectorId : null;
$vehIdDb = $vehicleId > 0 ? $vehicleId : null;
$stmt->bind_param('sisssssssissii', $plateDb, $vehIdDb, $scheduledAt, $scheduleDate, $location, $inspectionType, $requestedBy, $contactPerson, $contactNumber, $inspectorIdDb, $inspectorLabel, $status, $crVerified, $orVerified);
$ok = $stmt->execute();
if (!$ok) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'insert_failed']);
  exit;
}
$scheduleId = (int)$stmt->insert_id;
echo json_encode(['ok' => true, 'schedule_id' => $scheduleId]);
?> 
