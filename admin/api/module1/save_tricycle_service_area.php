<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');
require_permission('module1.routes.write');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$areaCode = strtoupper(trim((string)($_POST['area_code'] ?? '')));
$areaName = trim((string)($_POST['area_name'] ?? ''));
$barangay = trim((string)($_POST['barangay'] ?? ''));
$terminalId = isset($_POST['terminal_id']) && $_POST['terminal_id'] !== '' ? (int)$_POST['terminal_id'] : null;
$authorizedUnits = isset($_POST['authorized_units']) && $_POST['authorized_units'] !== '' ? (int)$_POST['authorized_units'] : null;
$fareMin = isset($_POST['fare_min']) && $_POST['fare_min'] !== '' ? (float)$_POST['fare_min'] : null;
$fareMax = isset($_POST['fare_max']) && $_POST['fare_max'] !== '' ? (float)$_POST['fare_max'] : null;
$status = trim((string)($_POST['status'] ?? 'Active'));
$coverage = trim((string)($_POST['coverage_notes'] ?? ''));
$pointsRaw = trim((string)($_POST['points'] ?? ''));

if ($areaCode === '' || strlen($areaCode) < 3) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'invalid_area_code']);
  exit;
}
if ($areaName === '') $areaName = $areaCode;
if (!in_array($status, ['Active','Inactive'], true)) $status = 'Active';
if ($fareMin !== null && $fareMin < 0) { http_response_code(400); echo json_encode(['ok' => false, 'error' => 'invalid_fare']); exit; }
if ($fareMax !== null && $fareMax < 0) { http_response_code(400); echo json_encode(['ok' => false, 'error' => 'invalid_fare']); exit; }
if ($fareMin !== null && $fareMax !== null && $fareMax < $fareMin) { http_response_code(400); echo json_encode(['ok' => false, 'error' => 'invalid_fare_range']); exit; }
if ($fareMin === null && $fareMax !== null) $fareMin = $fareMax;
if ($fareMax === null && $fareMin !== null) $fareMax = $fareMin;

$points = [];
if ($pointsRaw !== '') {
  $lines = preg_split('/\r\n|\r|\n/', $pointsRaw);
  foreach ($lines as $ln) {
    $p = trim((string)$ln);
    if ($p === '') continue;
    if (strlen($p) > 128) $p = substr($p, 0, 128);
    $points[] = $p;
  }
  $points = array_values(array_unique($points));
  if (count($points) > 50) $points = array_slice($points, 0, 50);
}

try {
  $db->begin_transaction();

  if ($id > 0) {
    $stmtCur = $db->prepare("SELECT id FROM tricycle_service_areas WHERE id=? LIMIT 1");
    if (!$stmtCur) throw new Exception('db_prepare_failed');
    $stmtCur->bind_param('i', $id);
    $stmtCur->execute();
    $exists = $stmtCur->get_result()->fetch_assoc();
    $stmtCur->close();
    if (!$exists) throw new Exception('not_found');

    $stmtDup = $db->prepare("SELECT id FROM tricycle_service_areas WHERE area_code=? AND id<>? LIMIT 1");
    if (!$stmtDup) throw new Exception('db_prepare_failed');
    $stmtDup->bind_param('si', $areaCode, $id);
    $stmtDup->execute();
    $dup = $stmtDup->get_result()->fetch_assoc();
    $stmtDup->close();
    if ($dup) throw new Exception('duplicate_area_code');

    $stmt = $db->prepare("UPDATE tricycle_service_areas
                          SET area_code=?, area_name=?, barangay=?, terminal_id=?, authorized_units=?, fare_min=?, fare_max=?, coverage_notes=?, status=?
                          WHERE id=?");
    if (!$stmt) throw new Exception('db_prepare_failed');
    $barangayBind = $barangay !== '' ? $barangay : null;
    $coverageBind = $coverage !== '' ? $coverage : null;
    $stmt->bind_param('sssiiiddsi', $areaCode, $areaName, $barangayBind, $terminalId, $authorizedUnits, $fareMin, $fareMax, $coverageBind, $status, $id);
    $ok = $stmt->execute();
    $stmt->close();
  } else {
    $stmtDup = $db->prepare("SELECT id FROM tricycle_service_areas WHERE area_code=? LIMIT 1");
    if (!$stmtDup) throw new Exception('db_prepare_failed');
    $stmtDup->bind_param('s', $areaCode);
    $stmtDup->execute();
    $dup = $stmtDup->get_result()->fetch_assoc();
    $stmtDup->close();
    if ($dup) throw new Exception('duplicate_area_code');

    $stmt = $db->prepare("INSERT INTO tricycle_service_areas(area_code, area_name, barangay, terminal_id, authorized_units, fare_min, fare_max, coverage_notes, status)
                          VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) throw new Exception('db_prepare_failed');
    $barangayBind = $barangay !== '' ? $barangay : null;
    $coverageBind = $coverage !== '' ? $coverage : null;
    $stmt->bind_param('sssiiidds', $areaCode, $areaName, $barangayBind, $terminalId, $authorizedUnits, $fareMin, $fareMax, $coverageBind, $status);
    $ok = $stmt->execute();
    $id = (int)$db->insert_id;
    $stmt->close();
  }

  if ($id > 0) {
    $db->query("DELETE FROM tricycle_service_area_points WHERE area_id=" . (int)$id);
    if ($points) {
      $stmtP = $db->prepare("INSERT INTO tricycle_service_area_points(area_id, point_name, point_type, sort_order) VALUES (?, ?, 'Landmark', ?)");
      if (!$stmtP) throw new Exception('db_prepare_failed');
      $i = 0;
      foreach ($points as $p) {
        $i++;
        $stmtP->bind_param('isi', $id, $p, $i);
        $stmtP->execute();
      }
      $stmtP->close();
    }
  }

  $db->commit();
  echo json_encode(['ok' => (bool)$ok, 'id' => $id, 'area_code' => $areaCode]);
} catch (Throwable $e) {
  $db->rollback();
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => $e->getMessage() ?: 'save_failed']);
}
?>

