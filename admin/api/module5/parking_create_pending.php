<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');
require_permission('module5.parking_fees');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}

$parkingAreaId = isset($_POST['parking_area_id']) && $_POST['parking_area_id'] !== '' ? (int)$_POST['parking_area_id'] : null;
$terminalIdIn = isset($_POST['terminal_id']) && $_POST['terminal_id'] !== '' ? (int)$_POST['terminal_id'] : null;
$plate = strtoupper(trim((string)($_POST['vehicle_plate'] ?? '')));
$plate = $plate !== '' ? $plate : strtoupper(trim((string)($_POST['plate_no'] ?? ($_POST['plate_number'] ?? ''))));
$amount = (float)($_POST['amount'] ?? 0);
$durationHours = (int)($_POST['duration_hours'] ?? 0);
$chargeType = trim((string)($_POST['charge_type'] ?? 'Usage Fee'));
$paymentMethod = trim((string)($_POST['payment_method'] ?? ''));

if ($plate === '' || $amount <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'invalid_fields']);
  exit;
}
if (strcasecmp($paymentMethod, 'GCash') !== 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'treasury_only_allowed_for_gcash']);
  exit;
}

$terminalId = $terminalIdIn !== null && $terminalIdIn > 0 ? $terminalIdIn : null;
if ($terminalId === null && $parkingAreaId !== null && $parkingAreaId > 0) {
  $stmtT = $db->prepare("SELECT terminal_id FROM parking_areas WHERE id=? LIMIT 1");
  if ($stmtT) {
    $stmtT->bind_param('i', $parkingAreaId);
    $stmtT->execute();
    $rowT = $stmtT->get_result()->fetch_assoc();
    $stmtT->close();
    if ($rowT && isset($rowT['terminal_id']) && $rowT['terminal_id'] !== null && $rowT['terminal_id'] !== '') {
      $terminalId = (int)$rowT['terminal_id'];
    }
  }
}

if ($terminalId > 0 && $plate !== '') {
  $stmtV = $db->prepare("SELECT id FROM vehicles WHERE plate_number=? OR REPLACE(plate_number,'-','')=? LIMIT 1");
  if ($stmtV) {
    $plateClean = preg_replace('/[^A-Z0-9]/', '', $plate);
    $stmtV->bind_param('ss', $plate, $plateClean);
    $stmtV->execute();
    $veh = $stmtV->get_result()->fetch_assoc();
    $stmtV->close();
    
    if ($veh) {
      $vehicleId = (int)$veh['id'];
      $stmtAssign = $db->prepare("SELECT terminal_id FROM terminal_assignments WHERE vehicle_id=?");
      if ($stmtAssign) {
        $stmtAssign->bind_param('i', $vehicleId);
        $stmtAssign->execute();
        $resAssign = $stmtAssign->get_result();
        $assignedTerminals = [];
        while ($rowA = $resAssign->fetch_assoc()) {
          $assignedTerminals[] = (int)$rowA['terminal_id'];
        }
        $stmtAssign->close();
        
        if (!empty($assignedTerminals)) {
          if (!in_array($terminalId, $assignedTerminals, true)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'vehicle_restricted_to_assigned_terminals']);
            exit;
          }
        }
      }
    }
  }
}

$schema = '';
$schRes = $db->query("SELECT DATABASE() AS db");
if ($schRes) $schema = (string)(($schRes->fetch_assoc()['db'] ?? '') ?: '');

$colMeta = function (string $table, string $col) use ($db, $schema): array {
  if ($schema === '') return ['exists' => false];
  $stmt = $db->prepare("SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
                        FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1");
  if (!$stmt) return ['exists' => false];
  $stmt->bind_param('sss', $schema, $table, $col);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!$row) return ['exists' => false];
  return [
    'exists' => true,
    'type' => (string)($row['COLUMN_TYPE'] ?? ''),
    'nullable' => (string)($row['IS_NULLABLE'] ?? ''),
    'default' => $row['COLUMN_DEFAULT'] ?? null,
  ];
};

$parseEnum = function (string $colType): array {
  $colType = trim($colType);
  if (stripos($colType, 'enum(') !== 0) return [];
  if (!preg_match_all("/'([^']*)'/", $colType, $m)) return [];
  $vals = $m[1] ?? [];
  $out = [];
  foreach ($vals as $v) {
    $v = (string)$v;
    if ($v !== '') $out[] = $v;
  }
  return $out;
};

$pickEnum = function (array $allowed, array $prefer, string $fallback) : string {
  if (!$allowed) return $fallback;
  foreach ($prefer as $want) {
    foreach ($allowed as $a) {
      if (strcasecmp((string)$a, (string)$want) === 0) return (string)$a;
    }
  }
  return (string)$allowed[0];
};

if ($terminalId !== null && $terminalId > 0) {
  $stmtT = $db->prepare("SELECT id FROM terminals WHERE id=? LIMIT 1");
  if ($stmtT) {
    $stmtT->bind_param('i', $terminalId);
    $stmtT->execute();
    $trow = $stmtT->get_result()->fetch_assoc();
    $stmtT->close();
    if (!$trow) {
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'terminal_not_found']);
      exit;
    }
  }
}

$cols = [];
$placeholders = [];
$types = '';
$params = [];

$hasParkingArea = ($colMeta('parking_transactions', 'parking_area_id')['exists'] ?? false);
$hasTerminalIdCol = ($colMeta('parking_transactions', 'terminal_id')['exists'] ?? false);
$hasAmount = ($colMeta('parking_transactions', 'amount')['exists'] ?? false);
$hasTxnType = ($colMeta('parking_transactions', 'transaction_type')['exists'] ?? false);
$hasVehPlate = ($colMeta('parking_transactions', 'vehicle_plate')['exists'] ?? false);

if (!$hasAmount || !$hasTxnType || !$hasVehPlate) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'parking_transactions_schema_mismatch']);
  exit;
}

if ($parkingAreaId !== null && $parkingAreaId > 0 && $hasParkingArea) {
  $cols[] = 'parking_area_id'; $placeholders[] = '?'; $types .= 'i'; $params[] = $parkingAreaId;
}
if ($terminalId !== null && $terminalId > 0 && $hasTerminalIdCol) {
  $cols[] = 'terminal_id'; $placeholders[] = '?'; $types .= 'i'; $params[] = $terminalId;
}

$cols[] = 'amount'; $placeholders[] = '?'; $types .= 'd'; $params[] = $amount;
$cols[] = 'transaction_type'; $placeholders[] = '?'; $types .= 's'; $params[] = $chargeType !== '' ? $chargeType : 'Usage Fee';
$cols[] = 'vehicle_plate'; $placeholders[] = '?'; $types .= 's'; $params[] = $plate;

$pmMeta = $colMeta('parking_transactions', 'payment_method');
if (($pmMeta['exists'] ?? false)) {
  $pmAllowed = $parseEnum((string)($pmMeta['type'] ?? ''));
  $pm = $pickEnum($pmAllowed, ['GCash', 'Gcash', 'GCASH'], 'GCash');
  $cols[] = 'payment_method'; $placeholders[] = '?'; $types .= 's'; $params[] = $pm;
}

$stMeta = $colMeta('parking_transactions', 'status');
if (($stMeta['exists'] ?? false)) {
  $stAllowed = $parseEnum((string)($stMeta['type'] ?? ''));
  $pending = $pickEnum($stAllowed, ['Pending Payment', 'Pending', 'Unpaid', 'For Payment', 'Processing'], 'Pending Payment');
  $cols[] = 'status'; $placeholders[] = '?'; $types .= 's'; $params[] = $pending;
}

$durMeta = $colMeta('parking_transactions', 'duration_hours');
if (($durMeta['exists'] ?? false)) {
  $cols[] = 'duration_hours'; $placeholders[] = '?'; $types .= 'i'; $params[] = max(0, $durationHours);
}

$refMeta = $colMeta('parking_transactions', 'reference_no');
if (($refMeta['exists'] ?? false) && strtoupper((string)($refMeta['nullable'] ?? '')) === 'NO' && $refMeta['default'] === null) {
  $cols[] = 'reference_no'; $placeholders[] = '?'; $types .= 's'; $params[] = '';
}

$sql = "INSERT INTO parking_transactions (" . implode(',', $cols) . ") VALUES (" . implode(',', $placeholders) . ")";
$stmt = $db->prepare($sql);
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}

if ($types !== '') $stmt->bind_param($types, ...$params);
$ok = $stmt->execute();
$newId = (int)$db->insert_id;
$stmt->close();

if (!$ok || $newId <= 0) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'insert_failed', 'db_errno' => (int)($db->errno ?? 0), 'db_error' => (string)($db->error ?? '')]);
  exit;
}

echo json_encode(['ok' => true, 'transaction_id' => $newId]);
