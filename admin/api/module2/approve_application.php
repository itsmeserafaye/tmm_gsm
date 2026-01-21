<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

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
$expiryDate = trim((string)($_POST['expiry_date'] ?? ''));
$remarks = trim((string)($_POST['remarks'] ?? ''));

if ($appId <= 0 || $ltfrbRefNo === '' || $decisionOrderNo === '' || $expiryDate === '') {
  echo json_encode(['ok' => false, 'error' => 'missing_required_fields']);
  exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiryDate)) {
  echo json_encode(['ok' => false, 'error' => 'invalid_expiry_date']);
  exit;
}

$db->begin_transaction();
try {
  $stmtA = $db->prepare("SELECT application_id, operator_id, vehicle_count, franchise_ref_number, status FROM franchise_applications WHERE application_id=? FOR UPDATE");
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

  $st = (string)($app['status'] ?? '');
  if (!in_array($st, ['Endorsed','LGU-Endorsed','Approved','LTFRB-Approved'], true)) {
    $db->rollback();
    echo json_encode(['ok' => false, 'error' => 'invalid_status']);
    exit;
  }

  $operatorId = (int)($app['operator_id'] ?? 0);
  $need = (int)($app['vehicle_count'] ?? 0);
  if ($need <= 0) $need = 1;
  if ($operatorId <= 0) {
    $db->rollback();
    echo json_encode(['ok' => false, 'error' => 'missing_operator_id']);
    exit;
  }

  $stmtVeh = $db->prepare("SELECT v.id, v.plate_number,
                                  MAX(CASE WHEN vd.doc_type='ORCR' AND COALESCE(vd.is_verified,0)=1 THEN 1 ELSE 0 END) AS orcr_ok
                           FROM vehicles v
                           LEFT JOIN vehicle_documents vd ON vd.vehicle_id=v.id AND vd.doc_type='ORCR'
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
    $hasOrcr = ((int)($r['orcr_ok'] ?? 0)) === 1;
    if ($hasOrcr) $okCount++;
    else if ($plate !== '') $missing[] = $plate;
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

  $stmtF = $db->prepare("INSERT INTO franchises (application_id, ltfrb_ref_no, decision_order_no, expiry_date, status)
                          VALUES (?, ?, ?, ?, 'Active')
                          ON DUPLICATE KEY UPDATE ltfrb_ref_no=VALUES(ltfrb_ref_no), decision_order_no=VALUES(decision_order_no), expiry_date=VALUES(expiry_date), status='Active'");
  if (!$stmtF) throw new Exception('db_prepare_failed');
  $stmtF->bind_param('isss', $appId, $ltfrbRefNo, $decisionOrderNo, $expiryDate);
  if (!$stmtF->execute()) throw new Exception('insert_failed');
  $stmtF->close();

  $stmtU = $db->prepare("UPDATE franchise_applications
                          SET status='LTFRB-Approved',
                              approved_at=NOW(),
                              franchise_ref_number=?,
                              remarks=CASE WHEN ?<>'' THEN ? ELSE remarks END
                          WHERE application_id=?");
  if (!$stmtU) throw new Exception('db_prepare_failed');
  $stmtU->bind_param('sssi', $ltfrbRefNo, $remarks, $remarks, $appId);
  $stmtU->execute();
  $stmtU->close();

  $db->commit();
  echo json_encode(['ok' => true, 'message' => 'Application approved', 'application_id' => $appId, 'ltfrb_ref_no' => $ltfrbRefNo]);
} catch (Throwable $e) {
  $db->rollback();
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_error']);
}
