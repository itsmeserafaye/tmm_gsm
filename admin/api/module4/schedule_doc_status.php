<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');
require_any_permission(['module4.inspect','module4.certify','module4.schedule','module1.read','module1.write']);

$db = db();
$scheduleId = (int)($_GET['schedule_id'] ?? 0);
if ($scheduleId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_schedule']);
  exit;
}

$stmt = $db->prepare("SELECT schedule_id, plate_number, vehicle_id FROM inspection_schedules WHERE schedule_id=? LIMIT 1");
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmt->bind_param('i', $scheduleId);
$stmt->execute();
$srow = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$srow) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'error' => 'schedule_not_found']);
  exit;
}

$plate = trim((string)($srow['plate_number'] ?? ''));
$vehicleId = (int)($srow['vehicle_id'] ?? 0);
if ($vehicleId <= 0 && $plate !== '') {
  $stmtV = $db->prepare("SELECT id FROM vehicles WHERE plate_number=? LIMIT 1");
  if ($stmtV) {
    $stmtV->bind_param('s', $plate);
    $stmtV->execute();
    $vr = $stmtV->get_result()->fetch_assoc();
    $stmtV->close();
    $vehicleId = (int)($vr['id'] ?? 0);
  }
}

$onFile = ['cr' => false, 'or' => false, 'insurance' => false, 'emission' => false];
if ($vehicleId > 0) {
  $res = $db->query("SHOW TABLES LIKE 'vehicle_documents'");
  if ($res && $res->fetch_row()) {
    $stmtD = $db->prepare("SELECT doc_type FROM vehicle_documents WHERE vehicle_id=?");
    if ($stmtD) {
      $stmtD->bind_param('i', $vehicleId);
      $stmtD->execute();
      $r = $stmtD->get_result();
      while ($r && ($row = $r->fetch_assoc())) {
        $t = strtoupper(trim((string)($row['doc_type'] ?? '')));
        if ($t === 'CR' || $t === 'ORCR') $onFile['cr'] = true;
        if ($t === 'OR' || $t === 'ORCR') $onFile['or'] = true;
        if ($t === 'INSURANCE') $onFile['insurance'] = true;
        if ($t === 'EMISSION') $onFile['emission'] = true;
      }
      $stmtD->close();
    }
  }
}

echo json_encode([
  'ok' => true,
  'schedule_id' => $scheduleId,
  'vehicle_id' => $vehicleId,
  'plate_number' => $plate,
  'on_file' => $onFile,
]);
?>
