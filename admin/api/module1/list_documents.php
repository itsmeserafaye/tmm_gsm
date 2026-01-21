<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
header('Content-Type: application/json');
require_any_permission(['module1.view','module1.vehicles.write']);

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
}

echo json_encode(['ok' => true, 'data' => $rows]);
?>
