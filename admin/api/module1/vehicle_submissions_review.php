<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/util.php';
require_permission('module1.write');

$db = db();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  error_response(405, 'method_not_allowed');
}

$submissionId = (int)($_POST['submission_id'] ?? 0);
$decision = strtolower(trim((string)($_POST['decision'] ?? '')));
$remarks = trim((string)($_POST['remarks'] ?? ''));

if ($submissionId <= 0) error_response(400, 'invalid_submission_id');
if (!in_array($decision, ['approve','reject'], true)) error_response(400, 'invalid_decision');

$stmt = $db->prepare("SELECT * FROM vehicle_record_submissions WHERE submission_id=? LIMIT 1");
if (!$stmt) error_response(500, 'db_prepare_failed');
$stmt->bind_param('i', $submissionId);
$stmt->execute();
$sub = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$sub) error_response(404, 'submission_not_found');
if ((string)($sub['status'] ?? '') !== 'Submitted') error_response(400, 'submission_not_pending');

$adminUserId = (int)($_SESSION['user_id'] ?? 0);
$adminName = trim((string)($_SESSION['name'] ?? 'Admin'));
if ($adminUserId <= 0) error_response(401, 'not_authenticated');

$portalUserId = (int)($sub['portal_user_id'] ?? 0);
$plate = trim((string)($sub['plate_number'] ?? ''));
$vehicleType = trim((string)($sub['vehicle_type'] ?? ''));
$engineNo = trim((string)($sub['engine_no'] ?? ''));
$chassisNo = trim((string)($sub['chassis_no'] ?? ''));
$make = trim((string)($sub['make'] ?? ''));
$model = trim((string)($sub['model'] ?? ''));
$yearModel = trim((string)($sub['year_model'] ?? ''));
$fuelType = trim((string)($sub['fuel_type'] ?? ''));
$color = trim((string)($sub['color'] ?? ''));
$orNumber = trim((string)($sub['or_number'] ?? ''));
$crNumber = trim((string)($sub['cr_number'] ?? ''));
$crIssueDate = trim((string)($sub['cr_issue_date'] ?? ''));
$registeredOwner = trim((string)($sub['registered_owner'] ?? ''));
$crFile = trim((string)($sub['cr_file_path'] ?? ''));
$orFile = trim((string)($sub['or_file_path'] ?? ''));
$orExpiry = trim((string)($sub['or_expiry_date'] ?? ''));
$submittedAt = trim((string)($sub['submitted_at'] ?? ''));
$submittedByName = trim((string)($sub['submitted_by_name'] ?? ''));

$operatorId = 0;
$stmtOp = $db->prepare("SELECT puv_operator_id FROM operator_portal_users WHERE id=? LIMIT 1");
if ($stmtOp) {
  $stmtOp->bind_param('i', $portalUserId);
  $stmtOp->execute();
  $row = $stmtOp->get_result()->fetch_assoc();
  $stmtOp->close();
  if ($row) $operatorId = (int)($row['puv_operator_id'] ?? 0);
}

$now = date('Y-m-d H:i:s');

$db->begin_transaction();
try {
  if ($decision === 'reject') {
    $stmtUp = $db->prepare("UPDATE vehicle_record_submissions
                            SET status='Rejected', approved_by_user_id=?, approved_by_name=?, approved_at=?, approval_remarks=?
                            WHERE submission_id=?");
    if (!$stmtUp) throw new Exception('db_prepare_failed');
    $stmtUp->bind_param('isssi', $adminUserId, $adminName, $now, $remarks, $submissionId);
    $stmtUp->execute();
    $stmtUp->close();

    tmm_audit_event($db, 'PUV_VEHICLE_REJECTED', 'VehicleSubmission', (string)$submissionId, ['remarks' => $remarks, 'plate_number' => $plate]);
    $db->commit();
    echo json_encode(['ok' => true]);
    exit;
  }

  $vehId = 0;
  $stmtFind = $db->prepare("SELECT id FROM vehicles WHERE plate_number=? LIMIT 1");
  if ($stmtFind) {
    $stmtFind->bind_param('s', $plate);
    $stmtFind->execute();
    $row = $stmtFind->get_result()->fetch_assoc();
    $stmtFind->close();
    if ($row) $vehId = (int)($row['id'] ?? 0);
  }

  if ($vehId > 0) {
    $stmtV = $db->prepare("UPDATE vehicles SET vehicle_type=?, engine_no=?, chassis_no=?, make=?, model=?, year_model=?, fuel_type=?, color=?,
                                         or_number=?, cr_number=?, cr_issue_date=?, registered_owner=?,
                                         operator_id=COALESCE(NULLIF(?,0), operator_id),
                                         current_operator_id=COALESCE(NULLIF(?,0), current_operator_id),
                                         record_status=CASE WHEN ? > 0 THEN 'Linked' ELSE record_status END,
                                         submitted_by_portal_user_id=?, submitted_by_name=?, submitted_at=?,
                                         approved_by_user_id=?, approved_by_name=?, approved_at=?,
                                         status=COALESCE(NULLIF(status,''),'Declared')
                           WHERE id=?");
    if (!$stmtV) throw new Exception('db_prepare_failed');
    $stmtV->bind_param(
      'ssssssssssssiiiississi',
      $vehicleType, $engineNo, $chassisNo, $make, $model, $yearModel, $fuelType, $color,
      $orNumber, $crNumber, $crIssueDate, $registeredOwner,
      $operatorId, $operatorId, $operatorId,
      $portalUserId, $submittedByName, $submittedAt,
      $adminUserId, $adminName, $now,
      $vehId
    );
    $stmtV->execute();
    $stmtV->close();
  } else {
    $recStatus = $operatorId > 0 ? 'Linked' : 'Encoded';
    $stmtIns = $db->prepare("INSERT INTO vehicles
      (plate_number, vehicle_type, operator_id, current_operator_id, operator_name, engine_no, chassis_no, make, model, year_model, fuel_type, color, record_status, status, inspection_status,
       or_number, cr_number, cr_issue_date, registered_owner,
       submitted_by_portal_user_id, submitted_by_name, submitted_at, approved_by_user_id, approved_by_name, approved_at, created_at)
      VALUES
      (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Declared', 'Pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    if (!$stmtIns) throw new Exception('db_prepare_failed');
    $opName = $submittedByName !== '' ? $submittedByName : 'Operator';
    $stmtIns->bind_param(
      'ssiisssssssssssssississ',
      $plate, $vehicleType, $operatorId, $operatorId, $opName, $engineNo, $chassisNo, $make, $model, $yearModel, $fuelType, $color, $recStatus,
      $orNumber, $crNumber, $crIssueDate, $registeredOwner,
      $portalUserId, $submittedByName, $submittedAt, $adminUserId, $adminName, $now
    );
    $stmtIns->execute();
    $vehId = (int)$db->insert_id;
    $stmtIns->close();
  }

  if ($vehId > 0) {
    if ($crFile !== '') {
      $stmtDoc = $db->prepare("INSERT INTO documents (plate_number, type, file_path, uploaded_by, verified) VALUES (?, 'cr', ?, 'operator_portal', 0)");
      if ($stmtDoc) {
        $stmtDoc->bind_param('ss', $plate, $crFile);
        $stmtDoc->execute();
        $stmtDoc->close();
      }
    }
    if ($orFile !== '') {
      $stmtDoc2 = $db->prepare("INSERT INTO documents (plate_number, type, file_path, uploaded_by, verified) VALUES (?, 'or', ?, 'operator_portal', 0)");
      if ($stmtDoc2) {
        $stmtDoc2->bind_param('ss', $plate, $orFile);
        $stmtDoc2->execute();
        $stmtDoc2->close();
      }
    }

    $stmtPlate = $db->prepare("INSERT IGNORE INTO operator_portal_user_plates (user_id, plate_number) VALUES (?, ?)");
    if ($stmtPlate) {
      $stmtPlate->bind_param('is', $portalUserId, $plate);
      $stmtPlate->execute();
      $stmtPlate->close();
    }
  }

  $stmtUp = $db->prepare("UPDATE vehicle_record_submissions
                          SET status='Approved', approved_by_user_id=?, approved_by_name=?, approved_at=?, approval_remarks=?, vehicle_id=?
                          WHERE submission_id=?");
  if (!$stmtUp) throw new Exception('db_prepare_failed');
  $stmtUp->bind_param('isssii', $adminUserId, $adminName, $now, $remarks, $vehId, $submissionId);
  $stmtUp->execute();
  $stmtUp->close();

  tmm_audit_event($db, 'PUV_VEHICLE_APPROVED', 'Vehicle', (string)$vehId, ['submission_id' => $submissionId, 'plate_number' => $plate]);

  $db->commit();
  echo json_encode(['ok' => true, 'vehicle_id' => $vehId]);
} catch (Throwable $e) {
  $db->rollback();
  error_response(500, 'db_error', ['message' => $e->getMessage()]);
}
