<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');

require_permission('module1.write');

$docId = isset($_POST['doc_id']) ? (int) $_POST['doc_id'] : 0;
$isVerified = isset($_POST['is_verified']) ? (int) $_POST['is_verified'] : -1;
$docStatusRaw = trim((string)($_POST['doc_status'] ?? ''));
$remarks = trim((string)($_POST['remarks'] ?? ''));
if ($docId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_doc_id']);
  exit;
}
$docStatus = '';
if ($docStatusRaw !== '') {
  $t = strtolower($docStatusRaw);
  if ($t === 'verified') $docStatus = 'Verified';
  elseif ($t === 'rejected') $docStatus = 'Rejected';
  elseif ($t === 'pending') $docStatus = 'Pending';
}
if ($docStatus === '' && ($isVerified === 0 || $isVerified === 1)) {
  $docStatus = $isVerified === 1 ? 'Verified' : 'Pending';
}
if ($docStatus === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'invalid_doc_status']);
  exit;
}
$isVerified = $docStatus === 'Verified' ? 1 : 0;

$stmtD = $db->prepare("SELECT doc_id, operator_id, doc_type FROM operator_documents WHERE doc_id=? LIMIT 1");
if (!$stmtD) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmtD->bind_param('i', $docId);
$stmtD->execute();
$doc = $stmtD->get_result()->fetch_assoc();
$stmtD->close();
if (!$doc) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'error' => 'doc_not_found']);
  exit;
}

$operatorId = (int) ($doc['operator_id'] ?? 0);
if ($operatorId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'invalid_operator_id']);
  exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$stmtU = $db->prepare("UPDATE operator_documents SET doc_status=?, remarks=?, is_verified=?, verified_by=CASE WHEN ?=1 THEN ? ELSE NULL END, verified_at=CASE WHEN ?=1 THEN NOW() ELSE NULL END WHERE doc_id=?");
if (!$stmtU) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmtU->bind_param('ssiiiii', $docStatus, $remarks, $isVerified, $isVerified, $userId, $isVerified, $docId);
$stmtU->execute();
$stmtU->close();

$stmtO = $db->prepare("SELECT operator_type, verification_status, workflow_status FROM operators WHERE id=? LIMIT 1");
if ($stmtO) {
  $stmtO->bind_param('i', $operatorId);
  $stmtO->execute();
  $op = $stmtO->get_result()->fetch_assoc();
  $stmtO->close();
} else {
  $op = null;
}

$opType = (string) (($op['operator_type'] ?? '') ?: 'Individual');
$opStatus = (string) ($op['verification_status'] ?? '');
$wfStatus = (string) ($op['workflow_status'] ?? 'Draft');
if ($opStatus !== 'Inactive' && $wfStatus !== 'Inactive') {
  $required = [];
  if ($opType === 'Cooperative') {
    $required = ['CDA', 'Others'];
  } elseif ($opType === 'Corporation') {
    $required = ['SEC', 'Others'];
  } else {
    $required = ['GovID'];
  }

  $stmtV = $db->prepare("SELECT doc_type,
                                SUM(CASE WHEN doc_status='Verified' THEN 1 ELSE 0 END) AS verified_cnt,
                                SUM(CASE WHEN doc_status='Rejected' THEN 1 ELSE 0 END) AS rejected_cnt,
                                COUNT(*) AS total_cnt
                         FROM operator_documents
                         WHERE operator_id=?
                         GROUP BY doc_type");
  $byType = [];
  if ($stmtV) {
    $stmtV->bind_param('i', $operatorId);
    $stmtV->execute();
    $res = $stmtV->get_result();
    while ($r = $res->fetch_assoc()) {
      $t = (string) ($r['doc_type'] ?? '');
      $byType[$t] = [
        'verified' => ((int)($r['verified_cnt'] ?? 0)) > 0,
        'rejected' => ((int)($r['rejected_cnt'] ?? 0)) > 0,
        'total' => (int)($r['total_cnt'] ?? 0),
      ];
    }
    $stmtV->close();
  }

  $hasAnyDocs = false;
  foreach ($byType as $t => $info) { if (($info['total'] ?? 0) > 0) { $hasAnyDocs = true; break; } }

  $hasRejected = false;
  $allVerified = true;
  foreach ($required as $t) {
    if (!isset($byType[$t]) || ($byType[$t]['total'] ?? 0) <= 0) { $allVerified = false; continue; }
    if (!empty($byType[$t]['rejected'])) $hasRejected = true;
    if (empty($byType[$t]['verified'])) $allVerified = false;
  }

  $newWf = 'Draft';
  if ($hasAnyDocs) {
    if ($hasRejected) $newWf = 'Rejected';
    elseif ($allVerified) $newWf = 'Active';
    else $newWf = 'Pending Validation';
  }
  $newVs = ($newWf === 'Active') ? 'Verified' : 'Draft';
  $newLegacy = ($newWf === 'Active') ? 'Approved' : 'Pending';

  $stmtS = $db->prepare("UPDATE operators
                         SET workflow_status=CASE WHEN workflow_status IN ('Inactive') THEN workflow_status ELSE ? END,
                             verification_status=CASE WHEN verification_status='Inactive' THEN 'Inactive' ELSE ? END,
                             status=CASE WHEN status='Inactive' THEN 'Inactive' ELSE ? END
                         WHERE id=?");
  if ($stmtS) {
    $stmtS->bind_param('sssi', $newWf, $newVs, $newLegacy, $operatorId);
    $stmtS->execute();
    $stmtS->close();
  }
}

echo json_encode(['ok' => true, 'doc_id' => $docId, 'operator_id' => $operatorId, 'doc_status' => $docStatus, 'is_verified' => (bool) $isVerified]);
