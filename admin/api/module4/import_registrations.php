<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/import.php';
$db = db();
header('Content-Type: application/json');
require_permission('module4.schedule');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}

[$tmp, $err] = tmm_import_get_uploaded_csv('file');
if ($err) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => $err]);
  exit;
}

[, $rows, $err2] = tmm_import_read_csv($tmp);
if ($err2) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => $err2]);
  exit;
}

$stmtFindVehicleById = $db->prepare("SELECT id FROM vehicles WHERE id=? LIMIT 1");
$stmtFindVehicleByPlate = $db->prepare("SELECT id FROM vehicles WHERE plate_number=? LIMIT 1");
$stmtFindReg = $db->prepare("SELECT registration_id FROM vehicle_registrations WHERE vehicle_id=? ORDER BY registration_id DESC LIMIT 1");
$stmtIns = $db->prepare("INSERT INTO vehicle_registrations (vehicle_id, orcr_no, orcr_date, registration_status, created_at) VALUES (?, ?, ?, ?, NOW())");
$stmtUpd = $db->prepare("UPDATE vehicle_registrations SET orcr_no=?, orcr_date=?, registration_status=? WHERE registration_id=?");
if (!$stmtFindVehicleById || !$stmtFindVehicleByPlate || !$stmtFindReg || !$stmtIns || !$stmtUpd) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}

$allowed = ['Pending','Recorded','Registered','Expired'];

$inserted = 0;
$updated = 0;
$skipped = 0;
$errors = [];

$db->begin_transaction();
try {
  foreach ($rows as $idx => $r) {
    $vidRaw = trim((string)($r['vehicle_id'] ?? ''));
    $plate = strtoupper(trim((string)($r['plate_number'] ?? '')));

    $vehicleId = 0;
    if ($vidRaw !== '' && ctype_digit($vidRaw)) {
      $vid = (int)$vidRaw;
      $stmtFindVehicleById->bind_param('i', $vid);
      $stmtFindVehicleById->execute();
      $rowV = $stmtFindVehicleById->get_result()->fetch_assoc();
      $vehicleId = (int)($rowV['id'] ?? 0);
    }
    if ($vehicleId <= 0 && $plate !== '') {
      $stmtFindVehicleByPlate->bind_param('s', $plate);
      $stmtFindVehicleByPlate->execute();
      $rowV = $stmtFindVehicleByPlate->get_result()->fetch_assoc();
      $vehicleId = (int)($rowV['id'] ?? 0);
    }
    if ($vehicleId <= 0) { $skipped++; continue; }

    $orcrNo = trim((string)($r['orcr_no'] ?? ''));
    $orcrDate = trim((string)($r['orcr_date'] ?? ''));
    if ($orcrNo === '' || $orcrDate === '') { $skipped++; continue; }

    $regStatus = trim((string)($r['registration_status'] ?? 'Registered'));
    if (!in_array($regStatus, $allowed, true)) $regStatus = 'Registered';

    $stmtFindReg->bind_param('i', $vehicleId);
    $stmtFindReg->execute();
    $rowR = $stmtFindReg->get_result()->fetch_assoc();
    $regId = (int)($rowR['registration_id'] ?? 0);

    if ($regId > 0) {
      $stmtUpd->bind_param('sssi', $orcrNo, $orcrDate, $regStatus, $regId);
      $ok = $stmtUpd->execute();
      if (!$ok) {
        $errors[] = ['row' => $idx + 2, 'error' => 'update_failed'];
        $skipped++;
        continue;
      }
      $updated++;
    } else {
      $stmtIns->bind_param('isss', $vehicleId, $orcrNo, $orcrDate, $regStatus);
      $ok = $stmtIns->execute();
      if (!$ok) {
        $errors[] = ['row' => $idx + 2, 'error' => 'insert_failed'];
        $skipped++;
        continue;
      }
      $inserted++;
    }
  }
  $db->commit();
} catch (Throwable $e) {
  $db->rollback();
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'import_failed']);
  exit;
}

echo json_encode([
  'ok' => true,
  'inserted' => $inserted,
  'updated' => $updated,
  'skipped' => $skipped,
  'errors' => $errors
]);

