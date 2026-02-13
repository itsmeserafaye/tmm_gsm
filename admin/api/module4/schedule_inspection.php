<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
header('Content-Type: application/json');
require_permission('module4.schedule');
$scheduleId = (int)($_POST['schedule_id'] ?? 0);
$vehicleId = (int)($_POST['vehicle_id'] ?? 0);
$plate = strtoupper(trim((string)($_POST['plate_number'] ?? ($_POST['plate_no'] ?? ''))));
$plate = preg_replace('/\s+/', '', $plate);
$plateNoDash = preg_replace('/[^A-Z0-9]/', '', $plate);
$plate = $plate !== null ? (string)$plate : '';
$plateNoDash = $plateNoDash !== null ? (string)$plateNoDash : '';
$plateNorm = $plate;
if ($plateNorm !== '' && strpos($plateNorm, '-') === false) {
  if (preg_match('/^([A-Z0-9]+)(\d{3,4})$/', $plateNoDash, $m)) {
    $plateNorm = $m[1] . '-' . $m[2];
  }
}
$scheduledAt = trim((string)($_POST['scheduled_at'] ?? ($_POST['schedule_date'] ?? '')));
$scheduleDate = trim((string)($_POST['schedule_date'] ?? $scheduledAt));
$location = trim($_POST['location'] ?? '');
$inspectorId = isset($_POST['inspector_id']) ? (int)$_POST['inspector_id'] : 0;
$inspectorLabel = trim($_POST['inspector_label'] ?? '');
$inspectionType = trim($_POST['inspection_type'] ?? 'Annual');
$requestedBy = trim($_POST['requested_by'] ?? '');
$contactPerson = trim($_POST['contact_person'] ?? '');
$contactNumber = trim($_POST['contact_number'] ?? '');
$crVerified = !empty($_POST['cr_verified']) ? 1 : 0;
$orVerified = !empty($_POST['or_verified']) ? 1 : 0;
$reinspectOf = isset($_POST['reinspect_of_schedule_id']) ? (int)$_POST['reinspect_of_schedule_id'] : 0;
$correctionDue = trim((string)($_POST['correction_due_date'] ?? ''));
$statusRemarks = trim((string)($_POST['status_remarks'] ?? ($_POST['remarks'] ?? '')));
$statusRemarks = substr($statusRemarks, 0, 255);
if (($vehicleId <= 0 && $plate === '') || $scheduledAt === '' || $location === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_fields']);
  exit;
}
$toMysqlDate = function (string $s): string {
  $v = trim($s);
  if ($v === '') return '';
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return '';
  return $v;
};
$correctionDue = $toMysqlDate($correctionDue);
$toMysqlDatetime = function (string $s): string {
  $v = trim($s);
  $v = str_replace('T', ' ', $v);
  if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $v)) $v .= ':00';
  return $v;
};
$scheduledAt = $toMysqlDatetime((string)$scheduledAt);
$scheduleDate = $toMysqlDatetime((string)$scheduleDate);

$plateDb = $plateNorm !== '' ? $plateNorm : $plate;
if ($vehicleId > 0) {
  $stmtV = $db->prepare("SELECT id, plate_number FROM vehicles WHERE id=? LIMIT 1");
  if (!$stmtV) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
    exit;
  }
  $stmtV->bind_param('i', $vehicleId);
  $stmtV->execute();
  $veh = $stmtV->get_result()->fetch_assoc();
  $stmtV->close();
  if (!$veh) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'vehicle_not_found']);
    exit;
  }
  $plateDb = (string)($veh['plate_number'] ?? '');
  if ($plateDb === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'vehicle_missing_plate']);
    exit;
  }
}
if ($vehicleId <= 0) {
  $stmtV2 = $db->prepare("SELECT id, plate_number FROM vehicles WHERE plate_number=? OR REPLACE(plate_number,'-','')=? LIMIT 1");
  if ($stmtV2) {
    $stmtV2->bind_param('ss', $plateDb, $plateNoDash);
    $stmtV2->execute();
    $veh2 = $stmtV2->get_result()->fetch_assoc();
    $stmtV2->close();
    if ($veh2) {
      $vehicleId = (int)($veh2['id'] ?? 0);
      $plateDb = (string)($veh2['plate_number'] ?? $plateDb);
    } else {
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'vehicle_not_found']);
      exit;
    }
  }
}

if ($vehicleId > 0) {
  $stmtR = $db->prepare("SELECT registration_status, orcr_no, orcr_date FROM vehicle_registrations WHERE vehicle_id=? LIMIT 1");
  if ($stmtR) {
    $stmtR->bind_param('i', $vehicleId);
    $stmtR->execute();
    $reg = $stmtR->get_result()->fetch_assoc();
    $stmtR->close();
    $rs = (string)($reg['registration_status'] ?? '');
    $okReg = $reg && in_array($rs, ['Registered','Recorded'], true) && trim((string)($reg['orcr_no'] ?? '')) !== '' && !empty($reg['orcr_date']);
    if (!$okReg) {
      $crVerified = 0;
      $orVerified = 0;
    }
  }
}
$allowedTypes = ['Annual','Reinspection','Compliance','Special'];
if (!in_array($inspectionType, $allowedTypes, true)) {
  $inspectionType = 'Annual';
}
if ($inspectorId > 0) {
  $inspStmt = $db->prepare("SELECT officer_id, active_status FROM officers WHERE officer_id=?");
  if ($inspStmt) {
    $inspStmt->bind_param('i', $inspectorId);
    $inspStmt->execute();
    $inspRow = $inspStmt->get_result()->fetch_assoc();
    if (!$inspRow || (int)($inspRow['active_status'] ?? 0) !== 1) {
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'inspector_inactive']);
      exit;
    }
  }
}
$hasVerifiedDocs = ($crVerified === 1 && $orVerified === 1);
$hasInspector = ($inspectorId > 0 || $inspectorLabel !== '');
if (!$hasVerifiedDocs) {
  $status = 'Pending Verification';
} elseif ($hasVerifiedDocs && !$hasInspector) {
  $status = 'Pending Assignment';
} else {
  $status = 'Scheduled';
}

$vehIdDb = (int)$vehicleId;
$inspectorIdDb = (int)$inspectorId;

$hasCol = function (string $table, string $col) use ($db): bool {
  $t = $db->real_escape_string($table);
  $c = $db->real_escape_string($col);
  $r = $db->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
  return $r && $r->num_rows > 0;
};
$ensureCol = function (string $col, string $ddl) use ($db, $hasCol): void {
  if (!$hasCol('inspection_schedules', $col)) {
    @$db->query("ALTER TABLE inspection_schedules ADD COLUMN " . $ddl);
  }
};
$ensureCol('correction_due_date', "correction_due_date DATE NULL");
$ensureCol('reinspect_of_schedule_id', "reinspect_of_schedule_id INT NULL");
$ensureCol('status_remarks', "status_remarks VARCHAR(255) NULL");
$ensureCol('overdue_marked_at', "overdue_marked_at DATETIME NULL");

$cols = [
  'plate_number',
  'vehicle_id',
  'scheduled_at',
  'schedule_date',
  'location',
  'inspection_type',
  'requested_by',
  'contact_person',
  'contact_number',
  'inspector_id',
  'inspector_label',
  'status',
  'cr_verified',
  'or_verified',
];
$vals = [
  $plateDb,
  $vehIdDb,
  $scheduledAt,
  $scheduleDate,
  $location,
  $inspectionType,
  $requestedBy,
  $contactPerson,
  $contactNumber,
  $inspectorIdDb,
  $inspectorLabel,
  $status,
  $crVerified,
  $orVerified,
];
$typesStr = 'sisssssssissii';

if ($reinspectOf > 0 && $hasCol('inspection_schedules', 'reinspect_of_schedule_id')) {
  $cols[] = 'reinspect_of_schedule_id';
  $vals[] = $reinspectOf;
  $typesStr .= 'i';
}
if ($correctionDue !== '' && $hasCol('inspection_schedules', 'correction_due_date')) {
  $cols[] = 'correction_due_date';
  $vals[] = $correctionDue;
  $typesStr .= 's';
}

$placeholders = implode(', ', array_fill(0, count($cols), '?'));
$colSql = implode(', ', $cols);
$ensureEnumValue = function (string $table, string $col, string $value) use ($db): void {
  $t = $db->real_escape_string($table);
  $c = $db->real_escape_string($col);
  $res = $db->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
  if (!$res || !$row = $res->fetch_assoc()) return;
  $type = (string)($row['Type'] ?? '');
  if (stripos($type, 'enum(') !== 0) return;
  if (strpos($type, "'" . $value . "'") !== false) return;
  if (!preg_match("/^enum\\((.*)\\)$/i", $type, $m)) return;
  $inner = trim((string)($m[1] ?? ''));
  if ($inner === '') return;
  $newType = "ENUM(" . $inner . ",'" . $db->real_escape_string($value) . "')";
  @$db->query("ALTER TABLE `$t` MODIFY COLUMN `$c` $newType");
};
$ensureEnumValue('inspection_schedules', 'status', 'Overdue');
$ensureEnumValue('inspection_schedules', 'status', 'Overdue / No-Show');

$activeDupStatusesSql = "status NOT IN ('Cancelled','Completed')";
$dupSql = '';
$dupTypes = '';
$dupParams = [];
if ($inspectionType === 'Annual') {
  $dupSql = "SELECT schedule_id FROM inspection_schedules
             WHERE vehicle_id=? AND inspection_type=? AND DATE(COALESCE(schedule_date, scheduled_at))=DATE(?) AND $activeDupStatusesSql";
  $dupTypes = 'iss';
  $dupParams = [$vehIdDb, $inspectionType, $scheduledAt];
} else {
  $dupSql = "SELECT schedule_id FROM inspection_schedules
             WHERE vehicle_id=? AND inspection_type=? AND COALESCE(schedule_date, scheduled_at)=? AND $activeDupStatusesSql";
  $dupTypes = 'iss';
  $dupParams = [$vehIdDb, $inspectionType, $scheduledAt];
}

if ($scheduleId > 0) {
  $stmtDup = $db->prepare($dupSql . " AND schedule_id<>? LIMIT 1");
  if ($stmtDup) {
    $t = $dupTypes . 'i';
    $p = array_merge($dupParams, [$scheduleId]);
    $stmtDup->bind_param($t, ...$p);
    $stmtDup->execute();
    $dup = $stmtDup->get_result()->fetch_assoc();
    $stmtDup->close();
    if ($dup && (int)($dup['schedule_id'] ?? 0) > 0) {
      http_response_code(409);
      echo json_encode(['ok' => false, 'error' => 'duplicate_schedule', 'schedule_id' => (int)($dup['schedule_id'] ?? 0)]);
      exit;
    }
  }
  $stmtS = $db->prepare("SELECT status FROM inspection_schedules WHERE schedule_id=? LIMIT 1");
  if (!$stmtS) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
    exit;
  }
  $stmtS->bind_param('i', $scheduleId);
  $stmtS->execute();
  $sr = $stmtS->get_result()->fetch_assoc();
  $stmtS->close();
  if (!$sr) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'schedule_not_found']);
    exit;
  }
  $curStatus = (string)($sr['status'] ?? '');
  if ($curStatus === 'Completed' || $curStatus === 'Cancelled') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'cannot_update_final_schedule']);
    exit;
  }

  $stmtU = $db->prepare("UPDATE inspection_schedules
                         SET plate_number=?, vehicle_id=?, scheduled_at=?, schedule_date=?, location=?, inspection_type=?,
                             status=?, cr_verified=?, or_verified=?, status_remarks=?, overdue_marked_at=NULL
                         WHERE schedule_id=?");
  if (!$stmtU) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
    exit;
  }
  $stmtU->bind_param('sisssssiisi', $plateDb, $vehIdDb, $scheduledAt, $scheduleDate, $location, $inspectionType, $status, $crVerified, $orVerified, $statusRemarks, $scheduleId);
  $okU = $stmtU->execute();
  $stmtU->close();
  if (!$okU) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'update_failed']);
    exit;
  }
  echo json_encode(['ok' => true, 'schedule_id' => $scheduleId, 'updated' => true]);
  exit;
}

$stmtDup = $db->prepare($dupSql . " LIMIT 1");
if ($stmtDup) {
  $stmtDup->bind_param($dupTypes, ...$dupParams);
  $stmtDup->execute();
  $dup = $stmtDup->get_result()->fetch_assoc();
  $stmtDup->close();
  if ($dup && (int)($dup['schedule_id'] ?? 0) > 0) {
    http_response_code(409);
    echo json_encode(['ok' => false, 'error' => 'duplicate_schedule', 'schedule_id' => (int)($dup['schedule_id'] ?? 0)]);
    exit;
  }
}

$stmt = $db->prepare("INSERT INTO inspection_schedules ({$colSql}) VALUES ({$placeholders})");
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmt->bind_param($typesStr, ...$vals);
$ok = $stmt->execute();
if (!$ok) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'insert_failed']);
  exit;
}
$scheduleId = (int)$stmt->insert_id;
echo json_encode(['ok' => true, 'schedule_id' => $scheduleId]);
?> 
