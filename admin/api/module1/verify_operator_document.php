<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');

require_permission('module1.write');

$docId = isset($_POST['doc_id']) ? (int) $_POST['doc_id'] : 0;
$isVerified = isset($_POST['is_verified']) ? (int) $_POST['is_verified'] : 0;
if ($docId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_doc_id']);
  exit;
}
$isVerified = $isVerified ? 1 : 0;

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
$stmtU = $db->prepare("UPDATE operator_documents SET is_verified=?, verified_by=?, verified_at=IF(?, NOW(), NULL) WHERE doc_id=?");
if (!$stmtU) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmtU->bind_param('iiii', $isVerified, $userId, $isVerified, $docId);
$stmtU->execute();
$stmtU->close();

$stmtO = $db->prepare("SELECT operator_type, verification_status FROM operators WHERE id=? LIMIT 1");
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
if ($opStatus !== 'Inactive') {
  $required = [];
  if ($opType === 'Cooperative') {
    $required = ['CDA', 'Others'];
  } elseif ($opType === 'Corporation') {
    $required = ['SEC', 'Others'];
  } else {
    $required = ['GovID'];
  }

  $stmtV = $db->prepare("SELECT doc_type, MAX(is_verified) AS v FROM operator_documents WHERE operator_id=? GROUP BY doc_type");
  $verifiedByType = [];
  if ($stmtV) {
    $stmtV->bind_param('i', $operatorId);
    $stmtV->execute();
    $res = $stmtV->get_result();
    while ($r = $res->fetch_assoc()) {
      $t = (string) ($r['doc_type'] ?? '');
      $verifiedByType[$t] = ((int) ($r['v'] ?? 0)) === 1;
    }
    $stmtV->close();
  }

  $allOk = true;
  foreach ($required as $t) {
    if (empty($verifiedByType[$t])) { $allOk = false; break; }
  }

  $newStatus = $allOk ? 'Verified' : 'Draft';
  $newLegacy = $allOk ? 'Approved' : 'Pending';
  $stmtS = $db->prepare("UPDATE operators SET verification_status=?, status=? WHERE id=?");
  if ($stmtS) {
    $stmtS->bind_param('ssi', $newStatus, $newLegacy, $operatorId);
    $stmtS->execute();
    $stmtS->close();
  }
}

echo json_encode(['ok' => true, 'doc_id' => $docId, 'operator_id' => $operatorId, 'is_verified' => (bool) $isVerified]);
