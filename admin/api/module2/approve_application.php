<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/franchise_gate.php';

$db = db();
header('Content-Type: application/json');
require_permission('module2.franchises.manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}

$appId = (int)($_POST['application_id'] ?? 0);
$ltfrbRefNo = trim((string)($_POST['ltfrb_ref_no'] ?? ''));
$decisionOrderNo = trim((string)($_POST['decision_order_no'] ?? ''));
$authorityTypeRaw = strtoupper(trim((string)($_POST['authority_type'] ?? '')));
$issueDate = trim((string)($_POST['issue_date'] ?? ''));
$expiryDate = trim((string)($_POST['expiry_date'] ?? ''));
$remarks = trim((string)($_POST['remarks'] ?? ''));

if ($appId <= 0 || $ltfrbRefNo === '' || $decisionOrderNo === '' || $authorityTypeRaw === '' || $issueDate === '') {
  echo json_encode(['ok' => false, 'error' => 'missing_required_fields']);
  exit;
}
if (!preg_match('/^[0-9][0-9\-\/]{2,39}$/', $ltfrbRefNo)) {
  echo json_encode(['ok' => false, 'error' => 'invalid_ltfrb_ref_no']);
  exit;
}
if (!preg_match('/^[0-9]{3,40}$/', $decisionOrderNo)) {
  echo json_encode(['ok' => false, 'error' => 'invalid_decision_order_no']);
  exit;
}
if (!in_array($authorityTypeRaw, ['PA','CPC'], true)) {
  echo json_encode(['ok' => false, 'error' => 'invalid_authority_type']);
  exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $issueDate)) {
  echo json_encode(['ok' => false, 'error' => 'invalid_issue_date']);
  exit;
}
if ($authorityTypeRaw === 'CPC') {
  if ($expiryDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiryDate)) {
    echo json_encode(['ok' => false, 'error' => 'invalid_expiry_date']);
    exit;
  }
} else {
  $ts = strtotime($issueDate);
  if ($ts === false) {
    echo json_encode(['ok' => false, 'error' => 'invalid_issue_date']);
    exit;
  }
  $expiryDate = date('Y-m-d', strtotime('-1 day', strtotime('+1 year', $ts)));
}

$db->begin_transaction();
try {
  $stmtA = $db->prepare("SELECT application_id, operator_id, route_id, vehicle_count, franchise_ref_number, status FROM franchise_applications WHERE application_id=? FOR UPDATE");
  if (!$stmtA) throw new Exception('db_prepare_failed');
  $stmtA->bind_param('i', $appId);
  $stmtA->execute();
  $app = $stmtA->get_result()->fetch_assoc();
  $stmtA->close();
  if (!$app) {
    $db->rollback();
    echo json_encode(['ok' => false, 'error' => 'application_not_found']);
    exit;
  }

  $st = trim((string)($app['status'] ?? ''));
  if (!in_array($st, ['Endorsed','LGU-Endorsed','Approved','LTFRB-Approved','PA Issued','CPC Issued'], true)) {
    $db->rollback();
    echo json_encode(['ok' => false, 'error' => 'invalid_status']);
    exit;
  }

  $operatorId = (int)($app['operator_id'] ?? 0);
  $routeDbId = (int)($app['route_id'] ?? 0);
  $need = (int)($app['vehicle_count'] ?? 0);
  if ($need <= 0) $need = 1;
  if ($operatorId <= 0) {
    $db->rollback();
    echo json_encode(['ok' => false, 'error' => 'missing_operator_id']);
    exit;
  }

  $gate = tmm_can_endorse_application($db, $operatorId, $routeDbId, $need, $appId);
  if (!$gate['ok']) {
    $db->rollback();
    echo json_encode($gate);
    exit;
  }

  $hasCol = function (string $table, string $col) use ($db): bool {
    $table = trim($table);
    $col = trim($col);
    if ($table === '' || $col === '') return false;
    $res = $db->query("SHOW COLUMNS FROM `{$table}` LIKE '" . $db->real_escape_string($col) . "'");
    return $res && ($res->num_rows ?? 0) > 0;
  };

  $vdTypeCol = $hasCol('vehicle_documents', 'doc_type') ? 'doc_type'
    : ($hasCol('vehicle_documents', 'document_type') ? 'document_type'
    : ($hasCol('vehicle_documents', 'type') ? 'type' : 'doc_type'));
  $vdVerifiedCol = $hasCol('vehicle_documents', 'is_verified') ? 'is_verified'
    : ($hasCol('vehicle_documents', 'verified') ? 'verified'
    : ($hasCol('vehicle_documents', 'isApproved') ? 'isApproved' : 'is_verified'));
  $vdHasVehicleId = $hasCol('vehicle_documents', 'vehicle_id');
  $vdHasPlate = $hasCol('vehicle_documents', 'plate_number');

  $orcrCond = "LOWER(vd.`{$vdTypeCol}`) IN ('orcr','or/cr')";
  $orCond = "LOWER(vd.`{$vdTypeCol}`)='or'";
  $crCond = "LOWER(vd.`{$vdTypeCol}`)='cr'";
  $insCond = "LOWER(vd.`{$vdTypeCol}`) IN ('insurance','ins')";
  $verCond = "COALESCE(vd.`{$vdVerifiedCol}`,0)=1";
  $docsHasExpiry = $hasCol('documents', 'expiry_date');
  $legacyOrValidCond = $docsHasExpiry ? "(d.expiry_date IS NULL OR d.expiry_date >= CURDATE())" : "1=1";
  $join = $vdHasVehicleId && $vdHasPlate
    ? "(vd.vehicle_id=v.id OR ((vd.vehicle_id IS NULL OR vd.vehicle_id=0) AND vd.plate_number=v.plate_number))"
    : ($vdHasVehicleId ? "vd.vehicle_id=v.id" : ($vdHasPlate ? "vd.plate_number=v.plate_number" : "0=1"));

  $stmtVeh = $db->prepare("SELECT v.id, v.plate_number,
                                 MAX(CASE WHEN {$orcrCond} AND {$verCond} THEN 1 ELSE 0 END) AS orcr_ok,
                                 MAX(CASE WHEN {$orCond} AND {$verCond} THEN 1 ELSE 0 END) AS or_ok,
                                 MAX(CASE WHEN {$crCond} AND {$verCond} THEN 1 ELSE 0 END) AS cr_ok,
                                 MAX(CASE WHEN LOWER(d.type)='or' AND COALESCE(d.verified,0)=1 AND {$legacyOrValidCond} THEN 1 ELSE 0 END) AS legacy_or_ok,
                                 MAX(CASE WHEN LOWER(d.type)='cr' AND COALESCE(d.verified,0)=1 THEN 1 ELSE 0 END) AS legacy_cr_ok,
                                 MAX(CASE WHEN LOWER(d.type) IN ('orcr','or/cr') AND COALESCE(d.verified,0)=1 THEN 1 ELSE 0 END) AS legacy_orcr_ok
                          FROM vehicles v
                          LEFT JOIN vehicle_documents vd ON {$join}
                          LEFT JOIN documents d ON d.plate_number=v.plate_number
                          WHERE v.operator_id=?
                            AND (COALESCE(v.record_status,'') <> 'Archived')
                          GROUP BY v.id, v.plate_number
                          ORDER BY v.created_at DESC");
  if (!$stmtVeh) throw new Exception('db_prepare_failed');
  $stmtVeh->bind_param('i', $operatorId);
  $stmtVeh->execute();
  $resVeh = $stmtVeh->get_result();
  $okCount = 0;
  $missing = [];
  $totalLinked = 0;
  while ($r = $resVeh->fetch_assoc()) {
    $totalLinked++;
    $plate = (string)($r['plate_number'] ?? '');
    $orcrOk = ((int)($r['orcr_ok'] ?? 0)) === 1 || ((int)($r['legacy_orcr_ok'] ?? 0)) === 1;
    $orOk = ((int)($r['or_ok'] ?? 0)) === 1 || ((int)($r['legacy_or_ok'] ?? 0)) === 1;
    $crOk = ((int)($r['cr_ok'] ?? 0)) === 1 || ((int)($r['legacy_cr_ok'] ?? 0)) === 1;
    $pass = $orcrOk || ($orOk && $crOk);
    if ($pass) {
      $okCount++;
    } else if ($plate !== '') {
      $missing[] = $plate;
    }
  }
  $stmtVeh->close();

  if ($totalLinked <= 0) {
    $db->rollback();
    echo json_encode(['ok' => false, 'error' => 'no_linked_vehicles']);
    exit;
  }
  if ($okCount < $need) {
    $db->rollback();
    echo json_encode(['ok' => false, 'error' => 'orcr_required_for_approval', 'need' => $need, 'have' => $okCount, 'missing_plates' => array_slice($missing, 0, 25)]);
    exit;
  }

  $hasRegs = (bool)($db->query("SHOW TABLES LIKE 'vehicle_registrations'")?->fetch_row());
  if ($hasRegs) {
    if (!$hasCol('vehicle_registrations', 'registration_status')) { @$db->query("ALTER TABLE vehicle_registrations ADD COLUMN registration_status VARCHAR(32) NULL"); }
    if (!$hasCol('vehicle_registrations', 'orcr_no')) { @$db->query("ALTER TABLE vehicle_registrations ADD COLUMN orcr_no VARCHAR(64) NULL"); }
    if (!$hasCol('vehicle_registrations', 'orcr_date')) { @$db->query("ALTER TABLE vehicle_registrations ADD COLUMN orcr_date DATE NULL"); }
  }
  $stmtReady = $db->prepare("SELECT v.plate_number, COALESCE(v.record_status,'') AS record_status, COALESCE(v.inspection_status,'') AS inspection_status,
                                    COALESCE(vr.registration_status,'') AS registration_status,
                                    COALESCE(NULLIF(vr.orcr_no,''),'') AS orcr_no,
                                    vr.orcr_date,
                                    MAX(CASE WHEN {$insCond} AND {$verCond} THEN 1 ELSE 0 END) AS ins_ok,
                                    MAX(CASE WHEN LOWER(d.type)='insurance' AND COALESCE(d.verified,0)=1 AND {$legacyOrValidCond} THEN 1 ELSE 0 END) AS legacy_ins_ok
                             FROM vehicles v
                             LEFT JOIN vehicle_documents vd ON {$join}
                             LEFT JOIN documents d ON d.plate_number=v.plate_number
                             " . ($hasRegs ? "LEFT JOIN vehicle_registrations vr ON vr.vehicle_id=v.id" : "LEFT JOIN (SELECT NULL AS vehicle_id, '' AS registration_status, '' AS orcr_no, NULL AS orcr_date) vr ON 1=0") . "
                             WHERE v.operator_id=?
                               AND COALESCE(v.record_status,'') <> 'Archived'
                             GROUP BY v.plate_number, v.record_status, v.inspection_status, vr.registration_status, vr.orcr_no, vr.orcr_date
                             ORDER BY v.created_at DESC");
  $readyCount = 0;
  $missingInspection = [];
  $missingDocs = [];
  if ($stmtReady) {
    $stmtReady->bind_param('i', $operatorId);
    $stmtReady->execute();
    $resReady = $stmtReady->get_result();
    while ($resReady && ($r = $resReady->fetch_assoc())) {
      $plate = (string)($r['plate_number'] ?? '');
      if ($plate === '') continue;
      $isLinked = (string)($r['record_status'] ?? '') === 'Linked';
      $inspOk = (string)($r['inspection_status'] ?? '') === 'Passed';
      $insOk = ((int)($r['ins_ok'] ?? 0)) === 1 || ((int)($r['legacy_ins_ok'] ?? 0)) === 1;
      $regOk = true;
      if ($hasRegs) {
        $rs = (string)($r['registration_status'] ?? '');
        $orcrNo = (string)($r['orcr_no'] ?? '');
        $orcrDate = $r['orcr_date'] ?? null;
        $regOk = in_array($rs, ['Registered','Recorded'], true) && trim($orcrNo) !== '' && !empty($orcrDate);
      }
      $ok = $isLinked && $inspOk && $regOk && $insOk;
      if ($ok) {
        $readyCount++;
      } else {
        if (!$inspOk) $missingInspection[] = $plate;
        if (!$regOk || !$insOk) $missingDocs[] = $plate;
      }
    }
    $stmtReady->close();
  }
  if ($readyCount < $need) {
    $db->rollback();
    echo json_encode([
      'ok' => false,
      'error' => 'vehicles_not_ready',
      'need' => $need,
      'have' => $readyCount,
      'missing_inspection' => array_slice(array_values(array_unique($missingInspection)), 0, 25),
      'missing_docs' => array_slice(array_values(array_unique($missingDocs)), 0, 25),
    ]);
    exit;
  }

  $stmtDup = $db->prepare("SELECT application_id FROM franchises WHERE ltfrb_ref_no=? LIMIT 1");
  if ($stmtDup) {
    $stmtDup->bind_param('s', $ltfrbRefNo);
    $stmtDup->execute();
    $dup = $stmtDup->get_result()->fetch_assoc();
    $stmtDup->close();
    if ($dup && (int)$dup['application_id'] !== $appId) {
      $db->rollback();
      echo json_encode(['ok' => false, 'error' => 'duplicate_ltfrb_ref_no']);
      exit;
    }
  }

  $stmtF = $db->prepare("INSERT INTO franchises (application_id, ltfrb_ref_no, decision_order_no, authority_type, issue_date, expiry_date, status)
                          VALUES (?, ?, ?, ?, ?, ?, 'Active')
                          ON DUPLICATE KEY UPDATE
                            ltfrb_ref_no=VALUES(ltfrb_ref_no),
                            decision_order_no=VALUES(decision_order_no),
                            authority_type=VALUES(authority_type),
                            issue_date=VALUES(issue_date),
                            expiry_date=VALUES(expiry_date),
                            status='Active'");
  if (!$stmtF) throw new Exception('db_prepare_failed');
  $stmtF->bind_param('isssss', $appId, $ltfrbRefNo, $decisionOrderNo, $authorityTypeRaw, $issueDate, $expiryDate);
  if (!$stmtF->execute()) throw new Exception('insert_failed');
  $stmtF->close();

  $nextStatus = $authorityTypeRaw === 'PA' ? 'PA Issued' : 'CPC Issued';
  $stmtU = $db->prepare("UPDATE franchise_applications
                          SET status=?,
                              approved_at=NOW(),
                              franchise_ref_number=?,
                              remarks=CASE WHEN ?<>'' THEN ? ELSE remarks END
                          WHERE application_id=?");
  if (!$stmtU) throw new Exception('db_prepare_failed');
  $stmtU->bind_param('ssssi', $nextStatus, $ltfrbRefNo, $remarks, $remarks, $appId);
  $stmtU->execute();
  $stmtU->close();

  $hasRegs = (bool)($db->query("SHOW TABLES LIKE 'vehicle_registrations'")->fetch_row());
  if ($hasRegs) {
    if (!$hasCol('vehicle_registrations', 'registration_status')) { @$db->query("ALTER TABLE vehicle_registrations ADD COLUMN registration_status VARCHAR(32) NULL"); }
    if (!$hasCol('vehicle_registrations', 'orcr_no')) { @$db->query("ALTER TABLE vehicle_registrations ADD COLUMN orcr_no VARCHAR(64) NULL"); }
    if (!$hasCol('vehicle_registrations', 'orcr_date')) { @$db->query("ALTER TABLE vehicle_registrations ADD COLUMN orcr_date DATE NULL"); }
    $stmtAct = $db->prepare("UPDATE vehicles v
                             LEFT JOIN vehicle_registrations vr ON vr.vehicle_id=v.id
                             SET v.status='Active'
                             WHERE v.operator_id=?
                               AND COALESCE(v.record_status,'') <> 'Archived'
                               AND COALESCE(v.inspection_status,'')='Passed'
                               AND COALESCE(vr.registration_status,'') IN ('Registered','Recorded')
                               AND COALESCE(NULLIF(vr.orcr_no,''),'') <> ''
                               AND vr.orcr_date IS NOT NULL");
    if ($stmtAct) {
      $stmtAct->bind_param('i', $operatorId);
      $stmtAct->execute();
      $stmtAct->close();
    }
  }

  $db->commit();
  echo json_encode(['ok' => true, 'message' => 'Application approved', 'application_id' => $appId, 'ltfrb_ref_no' => $ltfrbRefNo]);
} catch (Throwable $e) {
  $db->rollback();
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_error']);
}
