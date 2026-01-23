<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');

require_permission('module1.write');

$docId = isset($_POST['doc_id']) ? (int) $_POST['doc_id'] : 0;
$isVerified = isset($_POST['is_verified']) ? (int) $_POST['is_verified'] : -1;
$docStatusRaw = trim((string)($_POST['doc_status'] ?? ''));
$remarksProvided = array_key_exists('remarks', $_POST);
$remarks = $remarksProvided ? trim((string)($_POST['remarks'] ?? '')) : '';
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

$stmtD = $db->prepare("SELECT doc_id, operator_id, doc_type, remarks, doc_status FROM operator_documents WHERE doc_id=? LIMIT 1");
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
$existingRemarks = (string)($doc['remarks'] ?? '');
$finalRemarks = $existingRemarks;
if ($remarksProvided) {
  if ($docStatus === 'Rejected' && $remarks !== '') {
    $base = trim((string)$existingRemarks);
    if ($base !== '') {
      $finalRemarks = $base . ' | Reason: ' . $remarks;
    } else {
      $finalRemarks = 'Reason: ' . $remarks;
    }
  } else {
    $finalRemarks = $remarks;
  }
}
$stmtU = $db->prepare("UPDATE operator_documents SET doc_status=?, remarks=?, is_verified=?, verified_by=CASE WHEN ?=1 THEN ? ELSE NULL END, verified_at=CASE WHEN ?=1 THEN NOW() ELSE NULL END WHERE doc_id=?");
if (!$stmtU) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmtU->bind_param('ssiiiii', $docStatus, $finalRemarks, $isVerified, $isVerified, $userId, $isVerified, $docId);
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
  $slots = [];
  if ($opType === 'Cooperative') {
    $slots = [
      ['doc_type' => 'CDA', 'label' => 'CDA Registration Certificate', 'keywords' => ['registration']],
      ['doc_type' => 'CDA', 'label' => 'CDA Certificate of Good Standing', 'keywords' => ['good standing', 'good_standing', 'standing']],
      ['doc_type' => 'Others', 'label' => 'Board Resolution', 'keywords' => ['board resolution', 'resolution']],
    ];
  } elseif ($opType === 'Corporation') {
    $slots = [
      ['doc_type' => 'SEC', 'label' => 'SEC Certificate of Registration', 'keywords' => ['certificate', 'registration']],
      ['doc_type' => 'SEC', 'label' => 'Articles of Incorporation / By-laws', 'keywords' => ['articles', 'by-laws', 'bylaws', 'incorporation']],
      ['doc_type' => 'Others', 'label' => 'Board Resolution', 'keywords' => ['board resolution', 'resolution']],
    ];
  } else {
    $slots = [
      ['doc_type' => 'GovID', 'label' => 'Valid Government ID', 'keywords' => ['gov', 'id', 'driver', 'license', 'umid', 'philsys']],
    ];
  }

  $hasUploadedAt = false;
  $chk = $db->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='operator_documents' AND COLUMN_NAME='uploaded_at' LIMIT 1");
  if ($chk && $chk->fetch_row()) $hasUploadedAt = true;
  $orderBy = $hasUploadedAt ? "uploaded_at DESC, doc_id DESC" : "doc_id DESC";
  $stmtV = $db->prepare("SELECT doc_id, doc_type, doc_status, remarks FROM operator_documents WHERE operator_id=? ORDER BY $orderBy");
  $docs = [];
  if ($stmtV) {
    $stmtV->bind_param('i', $operatorId);
    $stmtV->execute();
    $res = $stmtV->get_result();
    while ($r = $res->fetch_assoc()) $docs[] = $r;
    $stmtV->close();
  }

  $hasAnyDocs = count($docs) > 0;
  $used = [];
  $slotOk = array_fill(0, count($slots), false);
  $matchRemarks = function (string $remarks, array $keywords): bool {
    $t = strtolower($remarks);
    foreach ($keywords as $kw) {
      $k = strtolower((string)$kw);
      if ($k !== '' && strpos($t, $k) !== false) return true;
    }
    return false;
  };
  for ($i = 0; $i < count($slots); $i++) {
    $s = $slots[$i];
    foreach ($docs as $drow) {
      $did = (int)($drow['doc_id'] ?? 0);
      if ($did <= 0 || isset($used[$did])) continue;
      if ((string)($drow['doc_type'] ?? '') !== (string)$s['doc_type']) continue;
      if ((string)($drow['doc_status'] ?? '') !== 'Verified') continue;
      $rem = (string)($drow['remarks'] ?? '');
      if ($rem !== '' && $matchRemarks($rem, (array)($s['keywords'] ?? []))) {
        $used[$did] = true;
        $slotOk[$i] = true;
        break;
      }
    }
  }
  for ($i = 0; $i < count($slots); $i++) {
    if ($slotOk[$i]) continue;
    $s = $slots[$i];
    foreach ($docs as $drow) {
      $did = (int)($drow['doc_id'] ?? 0);
      if ($did <= 0 || isset($used[$did])) continue;
      if ((string)($drow['doc_type'] ?? '') !== (string)$s['doc_type']) continue;
      if ((string)($drow['doc_status'] ?? '') !== 'Verified') continue;
      $used[$did] = true;
      $slotOk[$i] = true;
      break;
    }
  }
  $allVerified = true;
  foreach ($slotOk as $ok) { if (!$ok) { $allVerified = false; break; } }

  $requiredTypes = [];
  foreach ($slots as $s) $requiredTypes[(string)$s['doc_type']] = true;
  $hasRejected = false;
  $anyVerifiedRequired = false;
  foreach ($docs as $drow) {
    $dt = (string)($drow['doc_type'] ?? '');
    if (!isset($requiredTypes[$dt])) continue;
    $st = (string)($drow['doc_status'] ?? '');
    $rem = (string)($drow['remarks'] ?? '');
    if ($st === 'Verified') {
      if ($dt !== 'Others') {
        $anyVerifiedRequired = true;
      } else {
        foreach ($slots as $s) {
          if ((string)$s['doc_type'] !== 'Others') continue;
          if ($rem !== '' && $matchRemarks($rem, (array)($s['keywords'] ?? []))) { $anyVerifiedRequired = true; break; }
        }
      }
    }
    if ($st !== 'Rejected') continue;
    if ($dt !== 'Others') { $hasRejected = true; }
    else {
      if ($rem === '') { $hasRejected = true; }
      else {
        foreach ($slots as $s) {
          if ((string)$s['doc_type'] !== 'Others') continue;
          if ($matchRemarks($rem, (array)($s['keywords'] ?? []))) { $hasRejected = true; break; }
        }
      }
    }
    if ($hasRejected && $anyVerifiedRequired) break;
  }

  $newWf = 'Draft';
  if ($hasAnyDocs) {
    if ($hasRejected && $anyVerifiedRequired) $newWf = 'Returned';
    elseif ($allVerified) $newWf = 'Active';
    else $newWf = 'Incomplete';
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
