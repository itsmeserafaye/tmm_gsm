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
$docs = ['cr' => null, 'or' => null, 'insurance' => null, 'emission' => null];
if ($vehicleId > 0) {
  $res = $db->query("SHOW TABLES LIKE 'vehicle_documents'");
  if ($res && $res->fetch_row()) {
    $schema = '';
    $schRes = $db->query("SELECT DATABASE() AS db");
    if ($schRes) $schema = (string)(($schRes->fetch_assoc()['db'] ?? '') ?: '');
    $hasCol = function(string $table, string $col) use ($db, $schema): bool {
      if ($schema === '') return false;
      $t = $db->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1");
      if (!$t) return false;
      $t->bind_param('sss', $schema, $table, $col);
      $t->execute();
      $res = $t->get_result();
      $ok = (bool)($res && $res->fetch_row());
      $t->close();
      return $ok;
    };
    $idCol = $hasCol('vehicle_documents','doc_id') ? 'doc_id' : ($hasCol('vehicle_documents','id') ? 'id' : '');
    $typeCol = $hasCol('vehicle_documents','doc_type') ? 'doc_type' : ($hasCol('vehicle_documents','type') ? 'type' : '');
    $pathCol = $hasCol('vehicle_documents','file_path') ? 'file_path' : '';
    $verCol = $hasCol('vehicle_documents','is_verified') ? 'is_verified'
      : ($hasCol('vehicle_documents','verified') ? 'verified'
      : ($hasCol('vehicle_documents','isApproved') ? 'isApproved' : ''));
    $hasVehId = $hasCol('vehicle_documents','vehicle_id');
    $hasPlate = $hasCol('vehicle_documents','plate_number');
    if ($idCol !== '' && $typeCol !== '' && $pathCol !== '' && ($hasVehId || $hasPlate)) {
      $where = $hasVehId ? "vehicle_id=?" : "plate_number=?";
      $idVal = $hasVehId ? $vehicleId : $plate;
      $stmtD = $db->prepare("SELECT {$idCol} AS id, {$typeCol} AS doc_type, {$pathCol} AS file_path, " . ($verCol !== '' ? "COALESCE({$verCol},0)" : "0") . " AS is_verified FROM vehicle_documents WHERE {$where} ORDER BY {$idCol} DESC");
      if ($stmtD) {
        if ($hasVehId) $stmtD->bind_param('i', $idVal); else $stmtD->bind_param('s', $idVal);
        $stmtD->execute();
        $r = $stmtD->get_result();
        while ($r && ($row = $r->fetch_assoc())) {
          $t = strtoupper(trim((string)($row['doc_type'] ?? '')));
          $slot = null;
          if ($t === 'CR' || $t === 'ORCR') $slot = 'cr';
          if ($t === 'OR' || $t === 'ORCR') $slot = 'or';
          if ($t === 'INSURANCE') $slot = 'insurance';
          if ($t === 'EMISSION') $slot = 'emission';
          if ($slot === null) continue;
          if (!$docs[$slot]) {
            $onFile[$slot] = true;
            $docs[$slot] = [
              'id' => (int)($row['id'] ?? 0),
              'doc_type' => $t,
              'file_path' => (string)($row['file_path'] ?? ''),
              'is_verified' => (int)($row['is_verified'] ?? 0),
            ];
          }
        }
        $stmtD->close();
      }
    }
  }
}

echo json_encode([
  'ok' => true,
  'schedule_id' => $scheduleId,
  'vehicle_id' => $vehicleId,
  'plate_number' => $plate,
  'on_file' => $onFile,
  'docs' => $docs,
]);
?>
