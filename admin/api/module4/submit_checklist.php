<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
header('Content-Type: application/json');
require_permission('module4.inspect');
$tmm_norm_plate = function (string $plate): string {
  $p = strtoupper(trim($plate));
  $p = preg_replace('/[^A-Z0-9]/', '', $p);
  return $p !== null ? $p : '';
};
$tmm_resolve_plate = function (mysqli $db, string $plate) use ($tmm_norm_plate): string {
  $clean = strtoupper(trim($plate));
  $norm = $tmm_norm_plate($clean);
  if ($norm === '') return $clean;
  $stmt = $db->prepare("SELECT plate_number FROM vehicles WHERE REPLACE(REPLACE(UPPER(plate_number), '-', ''), ' ', '') = ? LIMIT 1");
  if ($stmt) {
    $stmt->bind_param('s', $norm);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row && isset($row['plate_number']) && (string)$row['plate_number'] !== '') return (string)$row['plate_number'];
  }
  return $clean;
};
$scheduleId = (int)($_POST['schedule_id'] ?? 0);
$remarks = trim($_POST['remarks'] ?? '');
$remarks = substr((string)$remarks, 0, 255);
$items = isset($_POST['items']) && is_array($_POST['items']) ? $_POST['items'] : [];
$labelsFromPost = isset($_POST['labels']) && is_array($_POST['labels']) ? $_POST['labels'] : [];
if ($scheduleId <= 0 || !$items) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_fields']);
  exit;
}
$sch = $db->prepare("SELECT schedule_id, status, plate_number, vehicle_id FROM inspection_schedules WHERE schedule_id=?");
if (!$sch) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$sch->bind_param('i', $scheduleId);
$sch->execute();
$srow = $sch->get_result()->fetch_assoc();
if (!$srow) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'error' => 'schedule_not_found']);
  exit;
}
$scheduleStatus = (string)($srow['status'] ?? '');
$allowedScheduleStatuses = ['Scheduled','Rescheduled','Pending Verification','Pending Assignment','Overdue','Overdue / No-Show','Completed'];
if ($scheduleStatus !== '' && !in_array($scheduleStatus, $allowedScheduleStatuses, true)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'schedule_not_ready']);
  exit;
}
$plate = trim((string)($srow['plate_number'] ?? ''));
$vehPlate = $plate !== '' ? $tmm_resolve_plate($db, $plate) : '';
$vehicleId = (int)($srow['vehicle_id'] ?? 0);
if ($vehicleId <= 0 && $vehPlate !== '') {
  $stmtVid = $db->prepare("SELECT id FROM vehicles WHERE plate_number=? LIMIT 1");
  if ($stmtVid) {
    $stmtVid->bind_param('s', $vehPlate);
    $stmtVid->execute();
    $vr = $stmtVid->get_result()->fetch_assoc();
    $stmtVid->close();
    $vehicleId = (int)($vr['id'] ?? 0);
  }
}

if (!function_exists('normalize_item_status')) {
  function normalize_item_status($raw) {
    $v = strtoupper(trim((string)$raw));
    if ($v === 'PASS' || $v === 'PASSED') return 'Pass';
    if ($v === 'FAIL' || $v === 'FAILED') return 'Fail';
    if ($v === 'NA' || $v === 'N/A') return 'NA';
    return '';
  }
}

$allowed = ['Passed', 'Failed', 'Pending', 'For Reinspection'];
$overallFromPost = trim($_POST['overall_status'] ?? '');
$overall = '';
if ($overallFromPost !== '') {
  foreach ($allowed as $opt) {
    if (strcasecmp($overallFromPost, $opt) === 0) {
      $overall = $opt;
      break;
    }
  }
}
if ($overall === '') {
  $seenFail = false;
  $seenUnknown = false;
  foreach ($items as $code => $status) {
    $v = normalize_item_status($status);
    if ($v === 'Fail') {
      $seenFail = true;
    } elseif ($v === '') {
      $seenUnknown = true;
    }
  }
  if ($seenFail) {
    $overall = 'Failed';
  } elseif ($seenUnknown) {
    $overall = 'Pending';
  } else {
    $overall = 'Passed';
  }
} else {
  $seenFail = false;
  $seenUnknown = false;
  foreach ($items as $code => $status) {
    $v = normalize_item_status($status);
    if ($v === 'Fail') {
      $seenFail = true;
    } elseif ($v === '') {
      $seenUnknown = true;
    }
  }
  if ($seenFail) {
    $overall = 'Failed';
  } elseif ($seenUnknown && $overall === 'Passed') {
    $overall = 'Pending';
  }
}

$requiredCodes = [
  'RW_LIGHTS',
  'RW_HORN',
  'RW_BRAKES',
  'RW_STEER',
  'RW_TIRES',
  'RW_WIPERS',
  'RW_MIRRORS',
  'RW_LEAKS',
  'PS_DOORS',
];

$normalized = [];
foreach ($items as $code => $status) {
  $c = strtoupper(trim((string)$code));
  if ($c === '') continue;
  $v = normalize_item_status($status);
  if ($v === '') $v = 'NA';
  $normalized[$c] = $v;
}

$missingRequired = [];
foreach ($requiredCodes as $rc) {
  if (!isset($normalized[$rc]) || $normalized[$rc] === 'NA') {
    $missingRequired[] = $rc;
  }
}
if ($missingRequired) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'required_items_missing', 'required' => $missingRequired]);
  exit;
}

if ($overall === 'Passed') {
  foreach ($requiredCodes as $rc) {
    if (($normalized[$rc] ?? 'NA') !== 'Pass') {
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'passed_requires_required_pass', 'item_code' => $rc]);
      exit;
    }
  }
  foreach ($normalized as $c => $v) {
    if ($v === 'Fail') {
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'passed_has_fail', 'item_code' => $c]);
      exit;
    }
  }
} elseif ($overall === 'Failed') {
  $anyFail = false;
  foreach ($normalized as $v) {
    if ($v === 'Fail') { $anyFail = true; break; }
  }
  if (!$anyFail) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'failed_requires_fail_item']);
    exit;
  }
}
$db->begin_transaction();
try {
  $existingRes = $db->prepare("SELECT result_id FROM inspection_results WHERE schedule_id=? ORDER BY submitted_at DESC LIMIT 1");
  $existingRes = $db->prepare("SELECT result_id FROM inspection_results WHERE schedule_id=? ORDER BY submitted_at DESC LIMIT 1");
  $resultId = 0;
  if ($existingRes) {
    $existingRes->bind_param('i', $scheduleId);
    $existingRes->execute();
    $er = $existingRes->get_result()->fetch_assoc();
    $resultId = (int)($er['result_id'] ?? 0);
  }
  if ($resultId > 0) {
    $upRes = $db->prepare("UPDATE inspection_results SET overall_status=?, remarks=?, submitted_at=CURRENT_TIMESTAMP WHERE result_id=?");
    if (!$upRes) {
      throw new Exception('db_prepare_failed');
    }
    $upRes->bind_param('ssi', $overall, $remarks, $resultId);
    if (!$upRes->execute()) {
      throw new Exception('update_failed');
    }
    $delItems = $db->prepare("DELETE FROM inspection_checklist_items WHERE result_id=?");
    if ($delItems) {
      $delItems->bind_param('i', $resultId);
      $delItems->execute();
    }
  } else {
    $resStmt = $db->prepare("INSERT INTO inspection_results (schedule_id, overall_status, remarks) VALUES (?,?,?)");
    if (!$resStmt) {
      throw new Exception('db_prepare_failed');
    }
    $resStmt->bind_param('iss', $scheduleId, $overall, $remarks);
    if (!$resStmt->execute()) {
      $dbErr = $resStmt->error;
      $dbErrNo = $resStmt->errno;
      file_put_contents('c:/xampp/htdocs/tmm/debug_submit.txt', date('Y-m-d H:i:s') . " - Insert Failed: [$dbErrNo] $dbErr\nParams: $scheduleId, $overall, $remarks\n", FILE_APPEND);
      throw new Exception("insert_failed: [$dbErrNo] $dbErr");
    }
    $resultId = (int)$db->insert_id;
  }

$itemStmt = $db->prepare("INSERT INTO inspection_checklist_items (result_id, item_code, item_label, status) VALUES (?,?,?,?)");
if ($itemStmt) {
  foreach ($items as $code => $status) {
    $codeStr = strtoupper(trim((string)$code));
    $statusStr = normalize_item_status($status);
    if ($codeStr === '') {
      continue;
    }
    if ($statusStr === '') {
      $statusStr = 'NA';
    }
    $label = $codeStr;
    if (isset($labelsFromPost[$code]) || isset($labelsFromPost[$codeStr])) {
      $rawLabel = isset($labelsFromPost[$code]) ? $labelsFromPost[$code] : $labelsFromPost[$codeStr];
      $labelClean = trim((string)$rawLabel);
      if ($labelClean !== '') $label = substr($labelClean, 0, 128);
    }
    $itemStmt->bind_param('isss', $resultId, $codeStr, $label, $statusStr);
    $itemStmt->execute();
  }
}

  $newScheduleStatus = $overall === 'Pending' ? 'Scheduled' : 'Completed';
  $upSch = $db->prepare("UPDATE inspection_schedules SET status=? WHERE schedule_id=?");
  if ($upSch) {
    $upSch->bind_param('si', $newScheduleStatus, $scheduleId);
    $upSch->execute();
  }

  if ($vehPlate !== '' && $plate !== '' && $vehPlate !== $plate) {
    $upPlate = $db->prepare("UPDATE inspection_schedules SET plate_number=? WHERE schedule_id=?");
    if ($upPlate) {
      $upPlate->bind_param('si', $vehPlate, $scheduleId);
      $upPlate->execute();
      $upPlate->close();
    }
  }

  if ($vehPlate !== '') {
    $vehInspection = $overall;
    $vehOperationalStatus = null;
    $franchiseId = '';
    $stmtV = $db->prepare("SELECT franchise_id FROM vehicles WHERE plate_number=? LIMIT 1");
    if ($stmtV) {
      $stmtV->bind_param('s', $vehPlate);
      $stmtV->execute();
      $vr = $stmtV->get_result()->fetch_assoc();
      $stmtV->close();
      $franchiseId = trim((string)($vr['franchise_id'] ?? ''));
    }

    if ($vehInspection === 'Passed') {
      $today = date('Y-m-d');
      $frOk = false;
      if ($franchiseId !== '') {
        $stmtF = $db->prepare("SELECT status FROM franchise_applications WHERE franchise_ref_number=? LIMIT 1");
        if ($stmtF) {
          $stmtF->bind_param('s', $franchiseId);
          $stmtF->execute();
          $fr = $stmtF->get_result()->fetch_assoc();
          $stmtF->close();
          $frSt = (string)($fr['status'] ?? '');
          $frOk = (bool)$fr && in_array($frSt, ['Approved','LTFRB-Approved'], true);
        }
      }
      $regOk = false;
      $docsOk = ['cr' => false, 'insurance' => false];
      if ($vehicleId > 0) {
        $stmtR = $db->prepare("SELECT registration_status, orcr_no, orcr_date FROM vehicle_registrations WHERE vehicle_id=? LIMIT 1");
        if ($stmtR) {
          $stmtR->bind_param('i', $vehicleId);
          $stmtR->execute();
          $rr = $stmtR->get_result()->fetch_assoc();
          $stmtR->close();
          $rs = (string)($rr['registration_status'] ?? '');
          $regOk = ($rr && in_array($rs, ['Registered','Recorded'], true) && trim((string)($rr['orcr_no'] ?? '')) !== '' && !empty($rr['orcr_date']));
        }
      }
      $docHasExpiry = false;
      $chkExp = $db->query("SHOW TABLES LIKE 'documents'");
      if ($chkExp && $chkExp->fetch_row()) {
        $c1 = $db->query("SHOW COLUMNS FROM documents LIKE 'expiry_date'");
        $docHasExpiry = (bool)($c1 && $c1->num_rows > 0);
        $expSel = $docHasExpiry ? 'expiry_date' : 'NULL';
        $stmtD = $db->prepare("SELECT LOWER(type) AS t, {$expSel} AS exp FROM documents WHERE plate_number=? AND LOWER(type) IN ('cr','insurance')");
        if ($stmtD) {
          $stmtD->bind_param('s', $vehPlate);
          $stmtD->execute();
          $resD = $stmtD->get_result();
          while ($row = $resD->fetch_assoc()) {
            $t = (string)($row['t'] ?? '');
            $exp = (string)($row['exp'] ?? '');
            if ($t === 'cr') {
              $docsOk['cr'] = true;
            } elseif ($t === 'insurance') {
              if ($exp === '' || $exp >= $today) $docsOk['insurance'] = true;
            }
          }
          $stmtD->close();
        }
      }

      $vd = $db->query("SHOW TABLES LIKE 'vehicle_documents'");
      if ($vd && $vd->fetch_row() && !$docsOk['insurance']) {
        $colsRes = $db->query("SHOW COLUMNS FROM vehicle_documents");
        $cols = [];
        while ($colsRes && ($r = $colsRes->fetch_assoc())) {
          $cols[strtolower((string)($r['Field'] ?? ''))] = true;
        }
        $idCol = isset($cols['vehicle_id']) ? 'vehicle_id' : (isset($cols['plate_number']) ? 'plate_number' : null);
        $typeCol = isset($cols['doc_type']) ? 'doc_type' : (isset($cols['document_type']) ? 'document_type' : (isset($cols['type']) ? 'type' : null));
        $expCol = isset($cols['expiry_date']) ? 'expiry_date' : (isset($cols['expiration_date']) ? 'expiration_date' : null);
        if ($idCol && $typeCol) {
          $idIsInt = $idCol === 'vehicle_id';
          $sql = "SELECT {$typeCol} AS t" . ($expCol ? ", {$expCol} AS exp" : ", NULL AS exp") .
                 " FROM vehicle_documents WHERE {$idCol}=? AND UPPER({$typeCol})='INSURANCE'";
          $stmtVD = $db->prepare($sql);
          if ($stmtVD) {
            if ($idIsInt) $stmtVD->bind_param('i', $vehicleId);
            else $stmtVD->bind_param('s', $vehPlate);
            $stmtVD->execute();
            $resVD = $stmtVD->get_result();
            while ($row = $resVD->fetch_assoc()) {
              $exp = (string)($row['exp'] ?? '');
              if ($exp === '' || $exp >= $today) $docsOk['insurance'] = true;
            }
            $stmtVD->close();
          }
        }
      }

      if ($frOk && $regOk && $docsOk['cr'] && $docsOk['insurance']) $vehOperationalStatus = 'Active';
      else if ($regOk && $docsOk['cr'] && $docsOk['insurance']) $vehOperationalStatus = 'Registered';
      else $vehOperationalStatus = 'Inspected';
    } else {
      $vehOperationalStatus = 'Pending Inspection';
    }

    $hasPassedAt = false;
    $chkCol = $db->query("SHOW COLUMNS FROM vehicles LIKE 'inspection_passed_at'");
    if ($chkCol && $chkCol->num_rows > 0) $hasPassedAt = true;

    $passedAt = ($vehInspection === 'Passed') ? date('Y-m-d H:i:s') : null;
    if ($vehOperationalStatus !== null) {
      if ($hasPassedAt) {
        $upVeh = $db->prepare("UPDATE vehicles SET inspection_status=?, status=?, inspection_passed_at=? WHERE plate_number=?");
        if ($upVeh) {
          $upVeh->bind_param('ssss', $vehInspection, $vehOperationalStatus, $passedAt, $vehPlate);
          $upVeh->execute();
          $upVeh->close();
        }
      } else {
        $upVeh = $db->prepare("UPDATE vehicles SET inspection_status=?, status=? WHERE plate_number=?");
        if ($upVeh) {
          $upVeh->bind_param('sss', $vehInspection, $vehOperationalStatus, $vehPlate);
          $upVeh->execute();
          $upVeh->close();
        }
      }
    } else {
      if ($hasPassedAt) {
        $upVeh = $db->prepare("UPDATE vehicles SET inspection_status=?, inspection_passed_at=? WHERE plate_number=?");
        if ($upVeh) {
          $upVeh->bind_param('sss', $vehInspection, $passedAt, $vehPlate);
          $upVeh->execute();
          $upVeh->close();
        }
      } else {
        $upVeh = $db->prepare("UPDATE vehicles SET inspection_status=? WHERE plate_number=?");
        if ($upVeh) {
          $upVeh->bind_param('ss', $vehInspection, $vehPlate);
          $upVeh->execute();
          $upVeh->close();
        }
      }
    }
  }

  if ($vehicleId > 0) {
    $insRes = ($overall === 'Passed') ? 'Passed' : (($overall === 'Failed') ? 'Failed' : '');
    if ($insRes !== '') {
      $stmtIns = $db->prepare("INSERT INTO inspections (vehicle_id, schedule_id, result, remarks, inspected_at)
                               VALUES (?, ?, ?, ?, NOW())
                               ON DUPLICATE KEY UPDATE result=VALUES(result), remarks=VALUES(remarks), inspected_at=NOW()");
      if ($stmtIns) {
        $stmtIns->bind_param('iiss', $vehicleId, $scheduleId, $insRes, $remarks);
        $stmtIns->execute();
        $stmtIns->close();
      }
    }
  }

  $db->commit();
  echo json_encode(['ok' => true, 'overall_status' => $overall, 'result_id' => $resultId, 'vehicle_requirements' => $docsOk ?? null]);
} catch (Throwable $e) {
  $db->rollback();
  file_put_contents(__DIR__ . '/db_errors.log', date('Y-m-d H:i:s') . " - " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_error: ' . $e->getMessage()]);
}
?> 
