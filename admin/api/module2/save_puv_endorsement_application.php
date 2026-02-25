<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/vehicle_types.php';
require_once __DIR__ . '/../../includes/security.php';

$db = db();
header('Content-Type: application/json');
require_permission('module2.apply');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}

$operatorId = (int)($_POST['operator_id'] ?? 0);
$routeId = (int)($_POST['route_id'] ?? 0);
$vehicleTypeRaw = trim((string)($_POST['vehicle_type'] ?? ''));
$vehicleCount = (int)($_POST['vehicle_count'] ?? 0);
$vehicleNotes = trim((string)($_POST['vehicle_notes'] ?? ''));
$vehicleNotes = substr($vehicleNotes, 0, 200);

if ($operatorId <= 0 || $routeId <= 0 || $vehicleCount <= 0) {
  echo json_encode(['ok' => false, 'error' => 'missing_required_fields']);
  exit;
}

if ($vehicleTypeRaw === '' || strlen($vehicleTypeRaw) > 60) {
  echo json_encode(['ok' => false, 'error' => 'invalid_vehicle_type']);
  exit;
}

function tmm_normalize_puv_vehicle_category($v) {
  $s = trim((string)$v);
  if ($s === '') return '';
  if (in_array($s, ['Tricycle','Jeepney','UV','Bus'], true)) return $s;
  $l = strtolower($s);
  if (strpos($l, 'tricycle') !== false || strpos($l, 'e-trike') !== false || strpos($l, 'pedicab') !== false) return 'Tricycle';
  if (strpos($l, 'jeepney') !== false) return 'Jeepney';
  if (strpos($l, 'bus') !== false || strpos($l, 'mini-bus') !== false) return 'Bus';
  if (strpos($l, 'uv') !== false || strpos($l, 'van') !== false || strpos($l, 'shuttle') !== false) return 'UV';
  return '';
}

$normType = tmm_normalize_puv_vehicle_category($vehicleTypeRaw);
if ($normType === '') {
  echo json_encode(['ok' => false, 'error' => 'invalid_vehicle_type']);
  exit;
}
if ($normType === 'Tricycle') {
  echo json_encode(['ok' => false, 'error' => 'tricycle_only']);
  exit;
}

try {
  $stmtOp = $db->prepare("SELECT id FROM operators WHERE id=? LIMIT 1");
  if (!$stmtOp) throw new Exception('db_prepare_failed');
  $stmtOp->bind_param('i', $operatorId);
  $stmtOp->execute();
  $opRow = $stmtOp->get_result()->fetch_assoc();
  $stmtOp->close();
  if (!$opRow) {
    echo json_encode(['ok' => false, 'error' => 'operator_not_found']);
    exit;
  }

  $stmtRoute = $db->prepare("SELECT id, status FROM routes WHERE id=? LIMIT 1");
  if (!$stmtRoute) throw new Exception('db_prepare_failed');
  $stmtRoute->bind_param('i', $routeId);
  $stmtRoute->execute();
  $routeRow = $stmtRoute->get_result()->fetch_assoc();
  $stmtRoute->close();
  if (!$routeRow || (string)($routeRow['status'] ?? '') !== 'Active') {
    echo json_encode(['ok' => false, 'error' => 'route_not_found']);
    exit;
  }

  $vehicleCount = max(1, min(500, $vehicleCount));

  $db->begin_transaction();

  $frRef = 'PUV-' . date('Ymd') . '-' . substr(strtoupper(bin2hex(random_bytes(3))), 0, 6);

  $submittedByUserId = (int)($_SESSION['user_id'] ?? 0);
  $submittedByName = trim((string)($_SESSION['name'] ?? ($_SESSION['full_name'] ?? '')));
  if ($submittedByName === '') $submittedByName = trim((string)($_SESSION['email'] ?? ($_SESSION['user_email'] ?? '')));
  if ($submittedByName === '') $submittedByName = 'Staff';

  $stmtIns = $db->prepare("INSERT INTO franchise_applications
    (franchise_ref_number, operator_id, route_id, service_area_id, vehicle_type, route_ids, vehicle_count, representative_name, status, lptrp_status, submitted_at, submitted_by_user_id, submitted_by_name, submitted_channel, validation_notes)
    VALUES (?, ?, ?, NULL, ?, ?, ?, NULL, 'Submitted', 'Submitted', NOW(), ?, ?, 'PUV_LOCAL_ENDORSEMENT', ?)");
  if (!$stmtIns) throw new Exception('db_prepare_failed');

  $routeIdsVal = 'ROUTE:' . (string)$routeId;
  $stmtIns->bind_param('siisssisss', $frRef, $operatorId, $routeId, $normType, $routeIdsVal, $vehicleCount, $submittedByUserId, $submittedByName, $vehicleNotes);
  if (!$stmtIns->execute()) throw new Exception('insert_failed');
  $appId = (int)$stmtIns->insert_id;
  $stmtIns->close();

  $uploadDir = __DIR__ . '/../../uploads/franchise/';
  if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0777, true);
  }

  $allowedExt = ['pdf','jpg','jpeg','png','xlsx','xls','csv'];
  $fileMap = [
    'doc_ltfrb_proof' => 'ltfrb_proof',
    'doc_orcr' => 'orcr',
    'doc_insurance' => 'insurance',
    'doc_other' => 'supporting',
  ];

  foreach ($fileMap as $field => $type) {
    if (!isset($_FILES[$field])) continue;
    $f = $_FILES[$field];
    $err = (int)($f['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_NO_FILE) continue;
    if ($err !== UPLOAD_ERR_OK) {
      throw new Exception('file_invalid');
    }
    $tmp = (string)($f['tmp_name'] ?? '');
    $orig = (string)($f['name'] ?? '');
    if ($tmp === '' || $orig === '') {
      throw new Exception('file_invalid');
    }
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
      throw new Exception('file_invalid');
    }
    $filename = 'APP' . $appId . '_' . $type . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = $uploadDir . $filename;
    if (!move_uploaded_file($tmp, $dest)) {
      throw new Exception('file_invalid');
    }
    $safe = tmm_scan_file_for_viruses($dest);
    if (!$safe) {
      if (is_file($dest)) @unlink($dest);
      throw new Exception('file_invalid');
    }
    $dbPath = 'franchise/' . $filename;
    $insDoc = $db->prepare("INSERT INTO documents (plate_number, type, file_path, uploaded_by, application_id) VALUES (NULL, ?, ?, 'admin', ?)");
    if (!$insDoc) {
      if (is_file($dest)) @unlink($dest);
      throw new Exception('db_prepare_failed');
    }
    $insDoc->bind_param('ssi', $type, $dbPath, $appId);
    if (!$insDoc->execute()) {
      if (is_file($dest)) @unlink($dest);
      $insDoc->close();
      throw new Exception('db_insert_failed');
    }
    $insDoc->close();
  }

  $db->commit();
  echo json_encode(['ok' => true, 'application_id' => $appId, 'reference' => $frRef]);
} catch (Throwable $e) {
  if ($db->errno === 0) {
    try { $db->rollback(); } catch (Throwable $_) {}
  }
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => ($e->getMessage() === 'file_invalid' ? 'file_invalid' : 'db_error')]);
}
