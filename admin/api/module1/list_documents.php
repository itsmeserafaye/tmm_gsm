<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
header('Content-Type: application/json');
require_any_permission(['module1.view','module1.vehicles.write']);

$schema = '';
$schRes = $db->query("SELECT DATABASE() AS db");
if ($schRes) { $schema = (string)(($schRes->fetch_assoc()['db'] ?? '') ?: ''); }
function tmm_has_column(mysqli $db, string $schema, string $table, string $col): bool {
  if ($schema === '') return false;
  $t = $db->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1");
  if (!$t) return false;
  $t->bind_param('sss', $schema, $table, $col);
  $t->execute();
  $res = $t->get_result();
  $ok = (bool)($res && $res->fetch_row());
  $t->close();
  return $ok;
}

$plate = trim((string)($_GET['plate'] ?? ($_GET['plate_number'] ?? '')));
$vehicleId = isset($_GET['vehicle_id']) ? (int)$_GET['vehicle_id'] : 0;
$type = trim((string)($_GET['type'] ?? ''));
if ($type !== '') {
  $t = strtolower(trim($type));
  if ($t === 'or' || $t === 'cr' || $t === 'orcr' || $t === 'or/cr') $type = 'ORCR';
  elseif ($t === 'insurance') $type = 'Insurance';
  elseif ($t === 'deed') $type = 'Others';
  elseif ($t === 'others') $type = 'Others';
}

if ($plate === '' && $vehicleId <= 0) {
  echo json_encode(['ok' => false, 'error' => 'missing_plate']);
  exit;
}

$vehRow = null;
if ($vehicleId > 0) {
  $stmtV = $db->prepare("SELECT id, plate_number FROM vehicles WHERE id=? LIMIT 1");
  if ($stmtV) {
    $stmtV->bind_param('i', $vehicleId);
    $stmtV->execute();
    $vehRow = $stmtV->get_result()->fetch_assoc();
    $stmtV->close();
  }
} else {
  $stmtV = $db->prepare("SELECT id, plate_number FROM vehicles WHERE plate_number=? LIMIT 1");
  if ($stmtV) {
    $stmtV->bind_param('s', $plate);
    $stmtV->execute();
    $vehRow = $stmtV->get_result()->fetch_assoc();
    $stmtV->close();
  }
}
if ($vehRow && isset($vehRow['id'])) {
  $vehicleId = (int)$vehRow['id'];
  if ($plate === '') $plate = (string)($vehRow['plate_number'] ?? '');
}

$rows = [];
if ($vehicleId > 0) {
  $useNew = tmm_has_column($db, $schema, 'vehicle_documents', 'vehicle_id') && tmm_has_column($db, $schema, 'vehicle_documents', 'doc_type');
  if ($useNew) {
    $sql = "SELECT doc_id AS id, ? AS plate_number, doc_type AS type, file_path, uploaded_at, is_verified, verified_by, verified_at FROM vehicle_documents WHERE vehicle_id=?";
    $params = [$plate, $vehicleId];
    $types = 'si';
    if ($type !== '') {
      $sql .= " AND doc_type=?";
      $params[] = $type;
      $types .= 's';
    }
    $sql .= " ORDER BY uploaded_at DESC";
    $stmt = $db->prepare($sql);
    if ($stmt) {
      $stmt->bind_param($types, ...$params);
      $stmt->execute();
      $res = $stmt->get_result();
      while ($row = $res->fetch_assoc()) { $rows[] = $row; }
      $stmt->close();
    }
  } else {
    $useLegacyVehDocs = tmm_has_column($db, $schema, 'vehicle_documents', 'plate_number') && tmm_has_column($db, $schema, 'vehicle_documents', 'file_path');
    if ($useLegacyVehDocs && $plate !== '') {
      $typeCol = tmm_has_column($db, $schema, 'vehicle_documents', 'doc_type') ? 'doc_type' : (tmm_has_column($db, $schema, 'vehicle_documents', 'document_type') ? 'document_type' : '');
      $idCol = tmm_has_column($db, $schema, 'vehicle_documents', 'doc_id') ? 'doc_id' : (tmm_has_column($db, $schema, 'vehicle_documents', 'id') ? 'id' : 'id');
      $uploadedCol = tmm_has_column($db, $schema, 'vehicle_documents', 'uploaded_at') ? 'uploaded_at' : 'uploaded_at';
      $sql = "SELECT {$idCol} AS id, plate_number, " . ($typeCol !== '' ? "{$typeCol}" : "''") . " AS type, file_path, {$uploadedCol} AS uploaded_at, 0 AS is_verified, NULL AS verified_by, NULL AS verified_at FROM vehicle_documents WHERE plate_number=?";
      $params = [$plate];
      $types = 's';
      if ($type !== '' && $typeCol !== '') {
        $sql .= " AND {$typeCol}=?";
        $params[] = $type;
        $types .= 's';
      }
      $sql .= " ORDER BY {$uploadedCol} DESC";
      $stmt = $db->prepare($sql);
      if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) { $rows[] = $row; }
        $stmt->close();
      }
    } elseif ($plate !== '' && tmm_has_column($db, $schema, 'documents', 'plate_number')) {
      $sql = "SELECT id AS id, plate_number, type, file_path, uploaded_at, verified AS is_verified, NULL AS verified_by, NULL AS verified_at FROM documents WHERE plate_number=?";
      $params = [$plate];
      $types = 's';
      if ($type !== '') { $sql .= " AND type=?"; $params[] = strtolower($type) === 'orcr' ? 'or' : strtolower($type); $types .= 's'; }
      $sql .= " ORDER BY uploaded_at DESC";
      $stmt = $db->prepare($sql);
      if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) { $rows[] = $row; }
        $stmt->close();
      }
    }
  }
}

echo json_encode(['ok' => true, 'data' => $rows]);
?>
