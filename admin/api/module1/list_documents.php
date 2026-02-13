<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
header('Content-Type: application/json');
require_any_permission(['module1.view', 'module1.vehicles.write']);

$schema = '';
$schRes = $db->query("SELECT DATABASE() AS db");
if ($schRes) {
  $schema = (string) (($schRes->fetch_assoc()['db'] ?? '') ?: '');
}
function tmm_has_column(mysqli $db, string $schema, string $table, string $col): bool
{
  if ($schema === '')
    return false;
  $t = $db->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1");
  if (!$t)
    return false;
  $t->bind_param('sss', $schema, $table, $col);
  $t->execute();
  $res = $t->get_result();
  $ok = (bool) ($res && $res->fetch_row());
  $t->close();
  return $ok;
}

$plate = trim((string) ($_GET['plate'] ?? ($_GET['plate_number'] ?? '')));
$vehicleId = isset($_GET['vehicle_id']) ? (int) $_GET['vehicle_id'] : 0;
$type = trim((string) ($_GET['type'] ?? ''));
if ($type !== '') {
  $t = strtolower(trim($type));
  if ($t === 'or')
    $type = 'OR';
  elseif ($t === 'cr')
    $type = 'CR';
  elseif ($t === 'orcr' || $t === 'or/cr')
    $type = 'ORCR';
  elseif ($t === 'insurance')
    $type = 'Insurance';
  elseif ($t === 'deed')
    $type = 'Others';
  elseif ($t === 'others')
    $type = 'Others';
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
  $vehicleId = (int) $vehRow['id'];
  if ($plate === '')
    $plate = (string) ($vehRow['plate_number'] ?? '');
}

$rows = [];
if ($vehicleId > 0) {
  $vdHasVehicleId = tmm_has_column($db, $schema, 'vehicle_documents', 'vehicle_id');
  $vdHasPlate = tmm_has_column($db, $schema, 'vehicle_documents', 'plate_number');
  $vdHasFilePath = tmm_has_column($db, $schema, 'vehicle_documents', 'file_path')
    || tmm_has_column($db, $schema, 'vehicle_documents', 'document_path')
    || tmm_has_column($db, $schema, 'vehicle_documents', 'doc_path')
    || tmm_has_column($db, $schema, 'vehicle_documents', 'path');
  $vdTypeCol = tmm_has_column($db, $schema, 'vehicle_documents', 'doc_type') ? 'doc_type'
    : (tmm_has_column($db, $schema, 'vehicle_documents', 'document_type') ? 'document_type'
    : (tmm_has_column($db, $schema, 'vehicle_documents', 'type') ? 'type' : ''));
  $vdIdCol = tmm_has_column($db, $schema, 'vehicle_documents', 'doc_id') ? 'doc_id'
    : (tmm_has_column($db, $schema, 'vehicle_documents', 'id') ? 'id' : '');
  $vdPathCol = tmm_has_column($db, $schema, 'vehicle_documents', 'file_path') ? 'file_path'
    : (tmm_has_column($db, $schema, 'vehicle_documents', 'document_path') ? 'document_path'
    : (tmm_has_column($db, $schema, 'vehicle_documents', 'doc_path') ? 'doc_path'
    : (tmm_has_column($db, $schema, 'vehicle_documents', 'path') ? 'path' : '')));
  $vdUploadedCol = tmm_has_column($db, $schema, 'vehicle_documents', 'uploaded_at') ? 'uploaded_at'
    : (tmm_has_column($db, $schema, 'vehicle_documents', 'created_at') ? 'created_at'
    : (tmm_has_column($db, $schema, 'vehicle_documents', 'date_uploaded') ? 'date_uploaded' : 'uploaded_at'));
  $vdVerifiedCol = tmm_has_column($db, $schema, 'vehicle_documents', 'is_verified') ? 'is_verified'
    : (tmm_has_column($db, $schema, 'vehicle_documents', 'verified') ? 'verified'
    : (tmm_has_column($db, $schema, 'vehicle_documents', 'isApproved') ? 'isApproved' : ''));
  $vdHasVerifiedBy = tmm_has_column($db, $schema, 'vehicle_documents', 'verified_by');
  $vdHasVerifiedAt = tmm_has_column($db, $schema, 'vehicle_documents', 'verified_at');
  $vdHasExpiry = tmm_has_column($db, $schema, 'vehicle_documents', 'expiry_date');

  $useVehicleDocs = $vdIdCol !== '' && $vdTypeCol !== '' && $vdPathCol !== '' && ($vdHasVehicleId || $vdHasPlate) && $vdHasFilePath;
  if ($useVehicleDocs) {
    $where = $vdHasVehicleId ? "vehicle_id=?" : "plate_number=?";
    $params = [$plate, $vdHasVehicleId ? $vehicleId : $plate];
    $types = $vdHasVehicleId ? 'si' : 'ss';

    $sql = "SELECT {$vdIdCol} AS id,
                   ? AS plate_number,
                   UPPER({$vdTypeCol}) AS type,
                   {$vdPathCol} AS file_path,
                   {$vdUploadedCol} AS uploaded_at,
                   " . ($vdVerifiedCol !== '' ? "COALESCE({$vdVerifiedCol},0)" : "0") . " AS is_verified,
                   " . ($vdHasVerifiedBy ? "verified_by" : "NULL") . " AS verified_by,
                   " . ($vdHasVerifiedAt ? "verified_at" : "NULL") . " AS verified_at,
                   " . ($vdHasExpiry ? "expiry_date" : "NULL") . " AS expiry_date
            FROM vehicle_documents
            WHERE {$where}";

    if ($type !== '' && $vdTypeCol !== '') {
      $sql .= " AND UPPER({$vdTypeCol})=?";
      $params[] = strtoupper($type);
      $types .= 's';
    }
    $sql .= " ORDER BY {$vdUploadedCol} DESC";

    $stmt = $db->prepare($sql);
    if ($stmt) {
      $stmt->bind_param($types, ...$params);
      $stmt->execute();
      $res = $stmt->get_result();
      while ($res && ($row = $res->fetch_assoc())) {
        $row['source'] = 'vehicle_documents';
        $rows[] = $row;
      }
      $stmt->close();
    }

    $seenPaths = [];
    foreach ($rows as $r) {
      $p = trim((string)($r['file_path'] ?? ''));
      if ($p !== '') $seenPaths[$p] = true;
    }

    if (tmm_has_column($db, $schema, 'documents', 'plate_number')) {
      $docsHasExpiry = tmm_has_column($db, $schema, 'documents', 'expiry_date');
      $sql2 = "SELECT id, plate_number, type, file_path, uploaded_at, verified AS is_verified" . ($docsHasExpiry ? ", expiry_date" : ", NULL AS expiry_date") . " FROM documents WHERE plate_number=?";
      $params2 = [$plate];
      $types2 = 's';
      if ($type !== '') {
        $sql2 .= " AND type=?";
        $params2[] = strtolower($type) === 'orcr' ? 'or' : strtolower($type);
        $types2 .= 's';
      }
      $sql2 .= " ORDER BY uploaded_at DESC";
      $stmt2 = $db->prepare($sql2);
      if ($stmt2) {
        $stmt2->bind_param($types2, ...$params2);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        while ($res2 && ($row = $res2->fetch_assoc())) {
          $fp = trim((string)($row['file_path'] ?? ''));
          if ($fp !== '' && isset($seenPaths[$fp])) continue;
          $row['type'] = strtoupper((string)($row['type'] ?? ''));
          $row['verified_by'] = null;
          $row['verified_at'] = null;
          $row['source'] = 'documents';
          $rows[] = $row;
        }
        $stmt2->close();
      }
    }
  } else {
    $useLegacyVehDocs = tmm_has_column($db, $schema, 'vehicle_documents', 'plate_number') && tmm_has_column($db, $schema, 'vehicle_documents', 'file_path');
    if ($useLegacyVehDocs && $plate !== '') {
      $typeCol = tmm_has_column($db, $schema, 'vehicle_documents', 'doc_type') ? 'doc_type' : (tmm_has_column($db, $schema, 'vehicle_documents', 'document_type') ? 'document_type' : '');
      $idCol = tmm_has_column($db, $schema, 'vehicle_documents', 'doc_id') ? 'doc_id' : (tmm_has_column($db, $schema, 'vehicle_documents', 'id') ? 'id' : 'id');
      $uploadedCol = tmm_has_column($db, $schema, 'vehicle_documents', 'uploaded_at') ? 'uploaded_at' : 'uploaded_at';
      $expCol = tmm_has_column($db, $schema, 'vehicle_documents', 'expiry_date') ? 'expiry_date' : '';
      $sql = "SELECT {$idCol} AS id, plate_number, " . ($typeCol !== '' ? "{$typeCol}" : "''") . " AS type, file_path, {$uploadedCol} AS uploaded_at, 0 AS is_verified, NULL AS verified_by, NULL AS verified_at" . ($expCol !== '' ? (", {$expCol} AS expiry_date") : ", NULL AS expiry_date") . " FROM vehicle_documents WHERE plate_number=?";
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
        while ($row = $res->fetch_assoc()) {
          $row['type'] = strtoupper((string)($row['type'] ?? ''));
          $row['source'] = 'vehicle_documents';
          $rows[] = $row;
        }
        $stmt->close();
      }
    } elseif ($plate !== '' && tmm_has_column($db, $schema, 'documents', 'plate_number')) {
      $sql = "SELECT id AS id, plate_number, type, file_path, uploaded_at, verified AS is_verified, NULL AS verified_by, NULL AS verified_at FROM documents WHERE plate_number=?";
      $params = [$plate];
      $types = 's';
      if ($type !== '') {
        $sql .= " AND type=?";
        $params[] = strtolower($type) === 'orcr' ? 'or' : strtolower($type);
        $types .= 's';
      }
      $sql .= " ORDER BY uploaded_at DESC";
      $stmt = $db->prepare($sql);
      if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
          $row['type'] = strtoupper((string)($row['type'] ?? ''));
          $row['source'] = 'documents';
          $rows[] = $row;
        }
        $stmt->close();
      }
    }
  }
}

usort($rows, function ($a, $b) {
  $ta = (string) ($a['uploaded_at'] ?? '');
  $tb = (string) ($b['uploaded_at'] ?? '');
  return strcmp($tb, $ta);
});

echo json_encode(['ok' => true, 'data' => $rows]);
?>
