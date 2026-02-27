<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/enforcement.php';
require_once __DIR__ . '/../../includes/security.php';

$db = db();
header('Content-Type: application/json');
require_permission('module5.assign_vehicle');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}

$terminalId = (int)($_POST['terminal_id'] ?? 0);
$vehicleId = (int)($_POST['vehicle_id'] ?? 0);

if ($terminalId <= 0 || $vehicleId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_fields']);
  exit;
}

$stmtT = $db->prepare("SELECT id, name, capacity FROM terminals WHERE id=? LIMIT 1");
if (!$stmtT) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_prepare_failed']); exit; }
$stmtT->bind_param('i', $terminalId);
$stmtT->execute();
$term = $stmtT->get_result()->fetch_assoc();
$stmtT->close();
if (!$term) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'terminal_not_found']); exit; }

$stmtV = $db->prepare("SELECT id, plate_number, operator_id, inspection_status, vehicle_type, route_id, status FROM vehicles WHERE id=? LIMIT 1");
if (!$stmtV) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_prepare_failed']); exit; }
$stmtV->bind_param('i', $vehicleId);
$stmtV->execute();
$veh = $stmtV->get_result()->fetch_assoc();
$stmtV->close();
if (!$veh) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'vehicle_not_found']); exit; }

$plate = (string)($veh['plate_number'] ?? '');
$operatorId = (int)($veh['operator_id'] ?? 0);
$inspectionStatus = (string)($veh['inspection_status'] ?? '');
$vehicleType = (string)($veh['vehicle_type'] ?? '');
$vehicleRoute = (string)($veh['route_id'] ?? '');
$vehicleStatus = (string)($veh['status'] ?? '');

if ($operatorId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'vehicle_not_linked_to_operator']);
  exit;
}

$block = tmm_enforcement_get_block_reasons($db, ['vehicle_id' => $vehicleId, 'operator_id' => $operatorId, 'plate_number' => $plate]);
if (!$block['ok']) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => (string)($block['error'] ?? 'blocked_by_enforcement'), 'reasons' => $block['reasons'] ?? []]);
  exit;
}

$hasCol = function (string $table, string $col) use ($db): bool {
  $stmt = $db->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1");
  if (!$stmt) return false;
  $stmt->bind_param('ss', $table, $col);
  $stmt->execute();
  $res = $stmt->get_result();
  $ok = (bool) ($res && $res->fetch_row());
  $stmt->close();
  return $ok;
};

$verifiedOk = true;
$vehDocsHasVehicleId = $hasCol('vehicle_documents', 'vehicle_id');
$vehDocsHasPlate = $hasCol('vehicle_documents', 'plate_number');
$vehDocsTypeCol = $hasCol('vehicle_documents', 'doc_type') ? 'doc_type' : ($hasCol('vehicle_documents', 'document_type') ? 'document_type' : ($hasCol('vehicle_documents', 'type') ? 'type' : ''));
$vehDocsVerifiedCol = $hasCol('vehicle_documents', 'is_verified') ? 'is_verified' : ($hasCol('vehicle_documents', 'verified') ? 'verified' : '');

$useVehDocs = ($vehDocsTypeCol !== '' && $vehDocsVerifiedCol !== '' && ($vehDocsHasVehicleId || $vehDocsHasPlate));
$useLegacyDocs = $hasCol('documents', 'plate_number') && $hasCol('documents', 'type') && $hasCol('documents', 'verified');

if ($useVehDocs) {
  $idCol = $vehDocsHasVehicleId ? 'vehicle_id' : 'plate_number';
  $stmtD = null;
  $stmtD = $db->prepare("SELECT
    EXISTS (SELECT 1 FROM vehicle_documents vd WHERE vd.$idCol=? AND UPPER(vd.$vehDocsTypeCol) IN ('CR','ORCR') AND COALESCE(vd.$vehDocsVerifiedCol,0)=1) AS has_cr,
    EXISTS (SELECT 1 FROM vehicle_documents vd2 WHERE vd2.$idCol=? AND UPPER(vd2.$vehDocsTypeCol) IN ('OR','ORCR') AND COALESCE(vd2.$vehDocsVerifiedCol,0)=1) AS has_or");
  if ($stmtD) {
    if ($vehDocsHasVehicleId) {
      $stmtD->bind_param('ii', $vehicleId, $vehicleId);
    } else {
      $stmtD->bind_param('ss', $plate, $plate);
    }
    $stmtD->execute();
    $rowD = $stmtD->get_result()->fetch_assoc();
    $stmtD->close();
    $verifiedOk = (int)($rowD['has_cr'] ?? 0) === 1 && (int)($rowD['has_or'] ?? 0) === 1;
  }
} elseif ($useLegacyDocs && $plate !== '') {
  $stmtD = $db->prepare("SELECT
    EXISTS (SELECT 1 FROM documents d WHERE d.plate_number=? AND d.type IN ('cr','orcr') AND COALESCE(d.verified,0)=1) AS has_cr,
    EXISTS (SELECT 1 FROM documents d2 WHERE d2.plate_number=? AND d2.type IN ('or','orcr') AND COALESCE(d2.verified,0)=1) AS has_or");
  if ($stmtD) {
    $stmtD->bind_param('ss', $plate, $plate);
    $stmtD->execute();
    $rowD = $stmtD->get_result()->fetch_assoc();
    $stmtD->close();
    $verifiedOk = (int)($rowD['has_cr'] ?? 0) === 1 && (int)($rowD['has_or'] ?? 0) === 1;
  }
}
if ($useVehDocs && $useLegacyDocs && $plate !== '') {
  $verifiedOkVeh = $verifiedOk;
  $verifiedOkLegacy = false;
  $stmtD2 = $db->prepare("SELECT
    EXISTS (SELECT 1 FROM documents d WHERE d.plate_number=? AND d.type IN ('cr','orcr') AND COALESCE(d.verified,0)=1) AS has_cr,
    EXISTS (SELECT 1 FROM documents d2 WHERE d2.plate_number=? AND d2.type IN ('or','orcr') AND COALESCE(d2.verified,0)=1) AS has_or");
  if ($stmtD2) {
    $stmtD2->bind_param('ss', $plate, $plate);
    $stmtD2->execute();
    $rowD2 = $stmtD2->get_result()->fetch_assoc();
    $stmtD2->close();
    $verifiedOkLegacy = (int)($rowD2['has_cr'] ?? 0) === 1 && (int)($rowD2['has_or'] ?? 0) === 1;
  }
  $verifiedOk = $verifiedOkVeh || $verifiedOkLegacy;
}
if (!$verifiedOk) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'vehicle_docs_not_verified']);
  exit;
}

$hasTable = function (string $table) use ($db): bool {
  $stmt = $db->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1");
  if (!$stmt) return false;
  $stmt->bind_param('s', $table);
  $stmt->execute();
  $res = $stmt->get_result();
  $ok = (bool) ($res && $res->fetch_row());
  $stmt->close();
  return $ok;
};

if ($hasTable('franchise_applications') && $hasTable('routes') && $hasTable('terminal_routes')) {
  $routeDbIds = [];
  $stmtF = $db->prepare("SELECT DISTINCT route_id FROM franchise_applications WHERE operator_id=? AND route_id IS NOT NULL AND route_id>0 AND status IN ('Approved','LTFRB-Approved')");
  if ($stmtF) {
    $stmtF->bind_param('i', $operatorId);
    $stmtF->execute();
    $resF = $stmtF->get_result();
    while ($resF && ($r = $resF->fetch_assoc())) {
      $rid = (int)($r['route_id'] ?? 0);
      if ($rid > 0) $routeDbIds[] = $rid;
    }
    $stmtF->close();
  }
  $routeDbIds = array_values(array_unique($routeDbIds));
  if ($routeDbIds) {
    $idList = implode(',', array_map('intval', $routeDbIds));
    $routeRefs = [];
    $resR = $db->query("SELECT route_id, route_code FROM routes WHERE id IN ($idList)");
    if ($resR) {
      while ($r = $resR->fetch_assoc()) {
        $rid = trim((string)($r['route_id'] ?? ''));
        $rcode = trim((string)($r['route_code'] ?? ''));
        if ($rid !== '') $routeRefs[] = $rid;
        if ($rcode !== '') $routeRefs[] = $rcode;
      }
    }
    $routeRefs = array_values(array_unique(array_filter($routeRefs, fn($x) => $x !== '')));
    if ($routeRefs) {
      $in = implode(',', array_map(function ($s) use ($db) {
        return "'" . $db->real_escape_string($s) . "'";
      }, $routeRefs));
      $okTerm = false;
      $stmtTr = $db->prepare("SELECT 1 FROM terminal_routes WHERE terminal_id=? AND route_id IN ($in) LIMIT 1");
      if ($stmtTr) {
        $stmtTr->bind_param('i', $terminalId);
        $stmtTr->execute();
        $resTr = $stmtTr->get_result();
        $okTerm = (bool)($resTr && $resTr->fetch_row());
        $stmtTr->close();
      }
      if (!$okTerm) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'terminal_not_connected_to_operator_route']);
        exit;
      }
    }
  }
}

$capacity = (int)($term['capacity'] ?? 0);
if ($capacity > 0) {
  $stmtC = $db->prepare("SELECT COUNT(*) AS c FROM terminal_assignments WHERE terminal_id=?");
  if ($stmtC) {
    $stmtC->bind_param('i', $terminalId);
    $stmtC->execute();
    $c = (int)($stmtC->get_result()->fetch_assoc()['c'] ?? 0);
    $stmtC->close();
    if ($c >= $capacity) {
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'terminal_capacity_full']);
      exit;
    }
  }
}

$routeId = $vehicleRoute !== '' ? $vehicleRoute : null;

$vehicleTypeNorm = trim($vehicleType) !== '' ? $vehicleType : null;

if ($vehicleTypeNorm !== null) {
  $stmtAllowed = $db->prepare("SELECT DISTINCT r.vehicle_type
                               FROM terminal_routes tr
                               JOIN routes r ON r.route_id=tr.route_id OR r.route_code=tr.route_id
                               WHERE tr.terminal_id=? AND r.vehicle_type IS NOT NULL AND r.vehicle_type<>''");
  if ($stmtAllowed) {
    $stmtAllowed->bind_param('i', $terminalId);
    $stmtAllowed->execute();
    $resAllowed = $stmtAllowed->get_result();
    $allowedTypes = [];
    while ($resAllowed && ($rowA = $resAllowed->fetch_assoc())) {
      $t = (string)($rowA['vehicle_type'] ?? '');
      if ($t !== '') $allowedTypes[] = $t;
    }
    $stmtAllowed->close();
    if ($allowedTypes) {
      $okType = false;
      foreach ($allowedTypes as $t) {
        if (strcasecmp($t, $vehicleTypeNorm) === 0) { $okType = true; break; }
      }
      if (!$okType) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'vehicle_type_not_allowed_for_terminal']);
        exit;
      }
    }
  }
}

$colTA = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='terminal_assignments'");
$taCols = [];
if ($colTA) {
  while ($c = $colTA->fetch_assoc()) {
    $taCols[(string)($c['COLUMN_NAME'] ?? '')] = true;
  }
}
$assignmentIdCol = isset($taCols['assignment_id']) ? 'assignment_id' : (isset($taCols['id']) ? 'id' : '');
$plateCol = isset($taCols['plate_number']) ? 'plate_number' : (isset($taCols['plate_no']) ? 'plate_no' : (isset($taCols['plate']) ? 'plate' : 'plate_number'));
$terminalNameCol = isset($taCols['terminal_name']) ? 'terminal_name' : (isset($taCols['terminal']) ? 'terminal' : 'terminal_name');
$statusCol = isset($taCols['status']) ? 'status' : (isset($taCols['assignment_status']) ? 'assignment_status' : 'status');
$assignedAtCol = isset($taCols['assigned_at']) ? 'assigned_at' : (isset($taCols['created_at']) ? 'created_at' : 'assigned_at');

$hasTerminalId = isset($taCols['terminal_id']);
$hasVehicleId = isset($taCols['vehicle_id']);
$hasRouteId = isset($taCols['route_id']);
$hasPlate = isset($taCols[$plateCol]);
$hasTerminalName = isset($taCols[$terminalNameCol]);
$hasStatus = isset($taCols[$statusCol]);
$hasAssignedAt = isset($taCols[$assignedAtCol]);

if (!$hasPlate) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'terminal_assignments_schema_not_supported']);
  exit;
}

$db->begin_transaction();
try {
  // Ensure terminal has at least one legal permit; if not, require upload now
  $hasPermit = null;
  try {
    $hasPermit = false;
    $foundAny = false;

    $chkDocs = $db->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='facility_documents' LIMIT 1");
    if ($chkDocs && $chkDocs->fetch_row()) {
      $cols = [];
      $colRes = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='facility_documents'");
      if ($colRes) while ($c = $colRes->fetch_assoc()) $cols[(string)($c['COLUMN_NAME'] ?? '')] = true;
      $tidCol = isset($cols['terminal_id']) ? 'terminal_id' : (isset($cols['facility_id']) ? 'facility_id' : '');
      $typeCol = isset($cols['doc_type']) ? 'doc_type' : (isset($cols['type']) ? 'type' : (isset($cols['document_type']) ? 'document_type' : ''));
      if ($tidCol !== '' && $typeCol !== '') {
        $foundAny = true;
        $stmtPc = $db->prepare("SELECT COUNT(*) AS c FROM facility_documents WHERE $tidCol=? AND LOWER(COALESCE($typeCol,'')) LIKE '%permit%'");
        if ($stmtPc) {
          $stmtPc->bind_param('i', $terminalId);
          $stmtPc->execute();
          $rowPc = $stmtPc->get_result()->fetch_assoc();
          $stmtPc->close();
          if (((int)($rowPc['c'] ?? 0)) > 0) $hasPermit = true;
        }
      }
    }

    $chkPerm = $db->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='terminal_permits' LIMIT 1");
    if (!$hasPermit && $chkPerm && $chkPerm->fetch_row()) {
      $cols = [];
      $colRes = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='terminal_permits'");
      if ($colRes) while ($c = $colRes->fetch_assoc()) $cols[(string)($c['COLUMN_NAME'] ?? '')] = true;
      $tidCol = isset($cols['terminal_id']) ? 'terminal_id' : (isset($cols['facility_id']) ? 'facility_id' : '');
      if ($tidCol !== '') {
        $foundAny = true;
        $stmtPc = $db->prepare("SELECT COUNT(*) AS c FROM terminal_permits WHERE $tidCol=?");
        if ($stmtPc) {
          $stmtPc->bind_param('i', $terminalId);
          $stmtPc->execute();
          $rowPc = $stmtPc->get_result()->fetch_assoc();
          $stmtPc->close();
          if (((int)($rowPc['c'] ?? 0)) > 0) $hasPermit = true;
        }
      }
    }

    if (!$foundAny) $hasPermit = null;
  } catch (Throwable $e) {
    $hasPermit = null;
  }
  $uploadProvided = isset($_FILES['permit_file']) && is_array($_FILES['permit_file']) && ($_FILES['permit_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
  if ($hasPermit === false && !$uploadProvided) {
    $db->rollback();
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'permit_required']);
    exit;
  }
  if ($uploadProvided) {
    $ext = strtolower(pathinfo($_FILES['permit_file']['name'], PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg','jpeg','png','pdf'], true)) {
      $uploads_dir = __DIR__ . '/../../uploads';
      if (!is_dir($uploads_dir)) @mkdir($uploads_dir, 0777, true);
      $fname = 'terminal_' . $terminalId . '_permit_' . time() . '.' . $ext;
      $dest = rtrim($uploads_dir, '/\\') . DIRECTORY_SEPARATOR . $fname;
      if (move_uploaded_file($_FILES['permit_file']['tmp_name'], $dest) && tmm_scan_file_for_viruses($dest)) {
        $chk = $db->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='terminal_permits' LIMIT 1");
        if ($chk && $chk->fetch_row()) {
          $cols = [];
          $types = [];
          $colRes = $db->query("SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='terminal_permits'");
          if ($colRes) {
            while ($r = $colRes->fetch_assoc()) {
              $cn = (string)($r['COLUMN_NAME'] ?? '');
              $ct = (string)($r['COLUMN_TYPE'] ?? '');
              if ($cn !== '') $cols[$cn] = true;
              if ($cn !== '') $types[$cn] = $ct;
            }
          }
          $tidCol = isset($cols['terminal_id']) ? 'terminal_id' : '';
          $pathCol = isset($cols['file_path']) ? 'file_path' : (isset($cols['document_path']) ? 'document_path' : (isset($cols['doc_path']) ? 'doc_path' : (isset($cols['path']) ? 'path' : '')));
          if ($tidCol !== '' && $pathCol !== '') {
            $extraCols = [];
            $extraTypes = '';
            $extraBind = [];
            $docTypeCol = isset($cols['doc_type']) ? 'doc_type' : (isset($cols['document_type']) ? 'document_type' : (isset($cols['type']) ? 'type' : ''));
            if ($docTypeCol !== '') {
              $extraCols[] = $docTypeCol;
              $extraTypes .= 's';
              $dtMeta = (string)($types[$docTypeCol] ?? '');
              $val = 'MOA';
              if ($dtMeta !== '' && stripos($dtMeta, 'enum(') === 0) {
                if (preg_match_all("/'([^']*)'/", $dtMeta, $m) && !empty($m[1])) {
                  $match = null;
                  foreach ($m[1] as $ev) {
                    if (strcasecmp($ev, 'MOA') === 0) { $match = $ev; break; }
                  }
                  if ($match === null) $match = $m[1][0];
                  $val = $match;
                }
              }
              $extraBind[] = $val;
            }
            if (isset($cols['status'])) {
              $extraCols[] = 'status';
              $extraTypes .= 's';
              $extraBind[] = 'Pending';
            }
            $placeholders = '?,?';
            if ($extraCols) $placeholders .= ',' . implode(',', array_fill(0, count($extraCols), '?'));
            $sqlIns = "INSERT INTO terminal_permits ($tidCol, $pathCol" . ($extraCols ? (", " . implode(", ", $extraCols)) : "") . ") VALUES ($placeholders)";
            $stmtP = $db->prepare($sqlIns);
            if ($stmtP) {
              $bindTypes = 'is' . $extraTypes;
              $bind = array_merge([$terminalId, $fname], $extraBind);
              $stmtP->bind_param($bindTypes, ...$bind);
              $stmtP->execute();
              $stmtP->close();
            }
          }
        }
      } else {
        if (is_file($dest ?? '')) @unlink($dest);
      }
    }
  }
  $termName = (string)($term['name'] ?? '');
  $assignmentIdValue = null;
  if ($assignmentIdCol !== '') {
    $stmtMeta = $db->prepare("SELECT IS_NULLABLE, COLUMN_DEFAULT, EXTRA
                              FROM information_schema.COLUMNS
                              WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='terminal_assignments' AND COLUMN_NAME=?
                              LIMIT 1");
    if (!$stmtMeta) throw new Exception('db_prepare_failed: assignment_id_meta');
    $stmtMeta->bind_param('s', $assignmentIdCol);
    $stmtMeta->execute();
    $meta = $stmtMeta->get_result()->fetch_assoc();
    $stmtMeta->close();
    $isNullable = (string)($meta['IS_NULLABLE'] ?? '');
    $colDefault = $meta['COLUMN_DEFAULT'] ?? null;
    $extra = (string)($meta['EXTRA'] ?? '');
    $autoInc = stripos($extra, 'auto_increment') !== false;
    $needsValue = !$autoInc && strtoupper($isNullable) === 'NO' && $colDefault === null;
    if ($needsValue) {
      $stmtNext = $db->prepare("SELECT COALESCE(MAX($assignmentIdCol),0)+1 AS next_id FROM terminal_assignments FOR UPDATE");
      if (!$stmtNext) throw new Exception('db_prepare_failed: assignment_id_next');
      $stmtNext->execute();
      $rowNext = $stmtNext->get_result()->fetch_assoc();
      $stmtNext->close();
      $assignmentIdValue = (int)($rowNext['next_id'] ?? 0);
      if ($assignmentIdValue <= 0) $assignmentIdValue = 1;
    }
  }

  if ($hasVehicleId) {
    $stmtDel = $db->prepare("DELETE FROM terminal_assignments WHERE vehicle_id=? AND COALESCE($plateCol,'')<>?");
    if (!$stmtDel) throw new Exception('db_prepare_failed');
    $stmtDel->bind_param('is', $vehicleId, $plate);
    if (!$stmtDel->execute()) { $err = $stmtDel->error ?: 'execute_failed'; $stmtDel->close(); throw new Exception('delete_conflict_failed: ' . $err); }
    $stmtDel->close();

    $stmtDel2 = $db->prepare("DELETE FROM terminal_assignments WHERE $plateCol=? AND (vehicle_id IS NULL OR vehicle_id<>?)");
    if (!$stmtDel2) throw new Exception('db_prepare_failed');
    $stmtDel2->bind_param('si', $plate, $vehicleId);
    if (!$stmtDel2->execute()) { $err = $stmtDel2->error ?: 'execute_failed'; $stmtDel2->close(); throw new Exception('delete_conflict_failed: ' . $err); }
    $stmtDel2->close();
  }

  $cols = [$plateCol];
  $vals = ['?'];
  $types = 's';
  $bind = [$plate];
  if ($assignmentIdCol !== '' && $assignmentIdValue !== null) {
    $cols[] = $assignmentIdCol;
    $vals[] = '?';
    $types .= 'i';
    $bind[] = $assignmentIdValue;
  }

  if ($hasTerminalName) {
    $cols[] = $terminalNameCol;
    $vals[] = '?';
    $types .= 's';
    $bind[] = $termName;
  }
  if ($hasStatus) {
    $cols[] = $statusCol;
    $vals[] = "'Authorized'";
  }
  if ($hasAssignedAt) {
    $cols[] = $assignedAtCol;
    $vals[] = 'NOW()';
  }

  if ($hasRouteId) {
    $cols[] = 'route_id';
    $vals[] = '?';
    $types .= 's';
    $bind[] = $routeId !== null ? (string)$routeId : '';
  }
  if ($hasTerminalId) {
    $cols[] = 'terminal_id';
    $vals[] = '?';
    $types .= 'i';
    $bind[] = $terminalId;
  }
  if ($hasVehicleId) {
    $cols[] = 'vehicle_id';
    $vals[] = '?';
    $types .= 'i';
    $bind[] = $vehicleId;
  }

  $setParts = [];
  if ($hasTerminalId) $setParts[] = "terminal_id=VALUES(terminal_id)";
  $setParts[] = "$plateCol=VALUES($plateCol)";
  if ($hasTerminalName) $setParts[] = "$terminalNameCol=VALUES($terminalNameCol)";
  if ($hasVehicleId) $setParts[] = "vehicle_id=VALUES(vehicle_id)";
  if ($hasRouteId) $setParts[] = "route_id=VALUES(route_id)";
  if ($hasStatus) $setParts[] = "$statusCol='Authorized'";
  if ($hasAssignedAt) $setParts[] = "$assignedAtCol=NOW()";

  $sql = "INSERT INTO terminal_assignments (" . implode(',', $cols) . ")
          VALUES (" . implode(',', $vals) . ")
          ON DUPLICATE KEY UPDATE " . implode(', ', $setParts);

  $stmtUp = $db->prepare($sql);
  if (!$stmtUp) throw new Exception('db_prepare_failed: ' . ($db->error ?: 'prepare_failed'));
  $stmtUp->bind_param($types, ...$bind);
  if (!$stmtUp->execute()) { $err = $stmtUp->error ?: 'execute_failed'; $stmtUp->close(); throw new Exception('insert_failed: ' . $err); }
  $stmtUp->close();

  $db->commit();
  echo json_encode(['ok' => true]);
} catch (Throwable $e) {
  $db->rollback();
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => 'db_error',
    'details' => [
      'message' => $e->getMessage(),
      'mysqli_errno' => $db->errno ?? null,
      'mysqli_error' => $db->error ?? null,
    ]
  ]);
}
