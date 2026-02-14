<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/franchise_gate.php';
require_once __DIR__ . '/../../includes/util.php';

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
$approvedVehicleCount = (int)($_POST['approved_vehicle_count'] ?? 0);
$approvedRouteIdsRaw = trim((string)($_POST['approved_route_ids'] ?? ''));

$parseIds = function (string $raw): array {
  $raw = trim($raw);
  if ($raw === '') return [];
  $raw = preg_replace('/[^0-9,]/', '', $raw);
  $parts = array_values(array_filter(array_map('trim', explode(',', $raw)), fn($x) => $x !== ''));
  $ids = [];
  foreach ($parts as $p) {
    $n = (int)$p;
    if ($n > 0) $ids[] = $n;
  }
  $ids = array_values(array_unique($ids));
  return array_slice($ids, 0, 20);
};

$approvedRouteIds = $parseIds($approvedRouteIdsRaw);

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
  $approvedNeed = $approvedVehicleCount > 0 ? $approvedVehicleCount : $need;
  if ($approvedNeed <= 0) $approvedNeed = 1;
  if ($approvedNeed > 500) $approvedNeed = 500;
  $docNeed = 0;
  $readyNeed = 0;

  $primaryRouteId = $routeDbId;
  if ($approvedRouteIds) $primaryRouteId = (int)$approvedRouteIds[0];
  if ($primaryRouteId <= 0) $primaryRouteId = $routeDbId;
  if ($operatorId <= 0) {
    $db->rollback();
    echo json_encode(['ok' => false, 'error' => 'missing_operator_id']);
    exit;
  }

  if ($approvedRouteIds) {
    $in = implode(',', array_fill(0, count($approvedRouteIds), '?'));
    $types = str_repeat('i', count($approvedRouteIds));
    $sqlChk = "SELECT id FROM routes WHERE status='Active' AND id IN ($in)";
    $stmtChk = $db->prepare($sqlChk);
    if (!$stmtChk) throw new Exception('db_prepare_failed');
    $stmtChk->bind_param($types, ...$approvedRouteIds);
    $stmtChk->execute();
    $resChk = $stmtChk->get_result();
    $found = [];
    while ($resChk && ($r = $resChk->fetch_assoc())) $found[] = (int)($r['id'] ?? 0);
    $stmtChk->close();
    sort($found);
    $wanted = $approvedRouteIds;
    sort($wanted);
    if ($found !== $wanted) {
      $db->rollback();
      echo json_encode(['ok' => false, 'error' => 'invalid_assigned_routes']);
      exit;
    }
  }

  $hasCol = function (string $table, string $col) use ($db): bool {
    $table = trim($table);
    $col = trim($col);
    if ($table === '' || $col === '') return false;
    $res = $db->query("SHOW COLUMNS FROM `{$table}` LIKE '" . $db->real_escape_string($col) . "'");
    return $res && ($res->num_rows ?? 0) > 0;
  };

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
  $routeIdsCsv = $approvedRouteIds ? implode(',', $approvedRouteIds) : (string)$routeDbId;
  $approvedByUserId = (int)($_SESSION['user_id'] ?? 0);
  $approvedByName = trim((string)($_SESSION['name'] ?? ($_SESSION['full_name'] ?? '')));
  if ($approvedByName === '') $approvedByName = trim((string)($_SESSION['email'] ?? ($_SESSION['user_email'] ?? '')));
  if ($approvedByName === '') $approvedByName = 'Admin';
  $stmtU = $db->prepare("UPDATE franchise_applications
                          SET status=?,
                              approved_at=NOW(),
                              approved_by_user_id=?,
                              approved_by_name=?,
                              franchise_ref_number=?,
                              approved_vehicle_count=?,
                              approved_route_ids=?,
                              route_ids=?,
                              remarks=CASE WHEN ?<>'' THEN ? ELSE remarks END
                          WHERE application_id=?");
  if (!$stmtU) throw new Exception('db_prepare_failed');
  $stmtU->bind_param('sississsssi', $nextStatus, $approvedByUserId, $approvedByName, $ltfrbRefNo, $approvedNeed, $routeIdsCsv, $routeIdsCsv, $remarks, $remarks, $appId);
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
  tmm_audit_event($db, 'FRANCHISE_APPLICATION_APPROVED', 'FranchiseApplication', (string)$appId, ['approved_vehicle_count' => $approvedNeed, 'approved_route_ids' => $routeIdsCsv, 'authority_type' => $authorityTypeRaw]);
  echo json_encode(['ok' => true, 'message' => 'Application approved', 'application_id' => $appId, 'ltfrb_ref_no' => $ltfrbRefNo]);
} catch (Throwable $e) {
  $db->rollback();
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_error']);
}
