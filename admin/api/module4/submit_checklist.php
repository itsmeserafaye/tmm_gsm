<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
header('Content-Type: application/json');
require_permission('module4.inspections.manage');
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
$items = isset($_POST['items']) && is_array($_POST['items']) ? $_POST['items'] : [];
if ($scheduleId <= 0 || !$items) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_fields']);
  exit;
}
$sch = $db->prepare("SELECT schedule_id, status, plate_number FROM inspection_schedules WHERE schedule_id=?");
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
$allowedScheduleStatuses = ['Scheduled','Rescheduled','Completed'];
if ($scheduleStatus !== '' && !in_array($scheduleStatus, $allowedScheduleStatuses, true)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'schedule_not_ready']);
  exit;
}
$plate = trim((string)($srow['plate_number'] ?? ''));
$vehPlate = $plate !== '' ? $tmm_resolve_plate($db, $plate) : '';

function normalize_item_status($raw) {
  $v = strtoupper(trim((string)$raw));
  if ($v === 'PASS' || $v === 'PASSED') return 'Pass';
  if ($v === 'FAIL' || $v === 'FAILED') return 'Fail';
  if ($v === 'NA' || $v === 'N/A') return 'NA';
  return '';
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
$db->begin_transaction();
try {
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
      throw new Exception('insert_failed');
    }
    $resultId = (int)$db->insert_id;
  }

$labels = [
  'LIGHTS' => 'Lights & Horn',
  'BRAKES' => 'Brakes',
  'EMISSION' => 'Emission & Smoke Test',
  'TIRES' => 'Tires & Wipers',
  'INTERIOR' => 'Interior Safety',
  'DOCS' => 'Documents & Plate'
];
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
    $label = $labels[$codeStr] ?? $codeStr;
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
    $vehOperationalStatus = 'Suspended';
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
      $frOk = false;
      if ($franchiseId !== '') {
        $stmtF = $db->prepare("SELECT status FROM franchise_applications WHERE franchise_ref_number=? LIMIT 1");
        if ($stmtF) {
          $stmtF->bind_param('s', $franchiseId);
          $stmtF->execute();
          $fr = $stmtF->get_result()->fetch_assoc();
          $stmtF->close();
          $frOk = ($fr && (($fr['status'] ?? '') === 'Endorsed'));
        }
      }
      $vehOperationalStatus = $frOk ? 'Active' : 'Suspended';
    } else {
      $vehOperationalStatus = 'Suspended';
    }

    $hasPassedAt = false;
    $chkCol = $db->query("SHOW COLUMNS FROM vehicles LIKE 'inspection_passed_at'");
    if ($chkCol && $chkCol->num_rows > 0) $hasPassedAt = true;

    if ($hasPassedAt) {
      $passedAt = ($vehInspection === 'Passed') ? date('Y-m-d H:i:s') : null;
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
  }

  $db->commit();
  echo json_encode(['ok' => true, 'overall_status' => $overall, 'result_id' => $resultId]);
} catch (Throwable $e) {
  $db->rollback();
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_error']);
}
?> 
