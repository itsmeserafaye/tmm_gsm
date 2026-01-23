<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/util.php';

$db = db();
header('Content-Type: application/json');
require_any_permission(['module1.vehicles.write', 'module1.write']);

$operatorId = isset($_POST['operator_id']) ? (int)$_POST['operator_id'] : 0;
if ($operatorId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_operator_id']);
  exit;
}

$stmtO = $db->prepare("SELECT id, name, full_name, operator_type, workflow_status, verification_status FROM operators WHERE id=? LIMIT 1");
if (!$stmtO) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmtO->bind_param('i', $operatorId);
$stmtO->execute();
$op = $stmtO->get_result()->fetch_assoc();
$stmtO->close();
if (!$op) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'error' => 'operator_not_found']);
  exit;
}

$nameSlugBase = trim((string)($op['name'] ?? ''));
if ($nameSlugBase === '') $nameSlugBase = trim((string)($op['full_name'] ?? ''));
if ($nameSlugBase === '') $nameSlugBase = 'operator_' . $operatorId;
$nameSlug = preg_replace('/[^a-z0-9]+/i', '_', $nameSlugBase);
$nameSlug = trim((string)$nameSlug, '_');
if ($nameSlug === '') $nameSlug = 'operator_' . $operatorId;

$uploadsDir = __DIR__ . '/../../uploads';
if (!is_dir($uploadsDir)) {
  mkdir($uploadsDir, 0777, true);
}

$uploaded = [];
$errors = [];

$fields = [
  'gov_id' => ['type' => 'GovID', 'label' => 'Valid Government ID'],
  'proof_address' => ['type' => 'BarangayCert', 'label' => 'Proof of Address'],
  'cda_registration' => ['type' => 'CDA', 'label' => 'CDA Registration Certificate'],
  'cda_good_standing' => ['type' => 'CDA', 'label' => 'CDA Certificate of Good Standing'],
  'sec_certificate' => ['type' => 'SEC', 'label' => 'SEC Certificate of Registration'],
  'corp_articles_bylaws' => ['type' => 'SEC', 'label' => 'Articles of Incorporation / By-laws'],
  'board_resolution' => ['type' => 'Others', 'label' => 'Board Resolution'],
  'nbi_clearance' => ['type' => 'Others', 'label' => 'NBI Clearance'],
  'authorization_letter' => ['type' => 'Others', 'label' => 'Authorization Letter'],
  'members_list' => ['type' => 'Others', 'label' => 'List of Members'],
  'coop_articles_bylaws' => ['type' => 'Others', 'label' => 'Articles of Cooperation / By-laws'],
  'mayors_permit' => ['type' => 'Others', 'label' => "Mayor's Permit"],
  'business_permit' => ['type' => 'Others', 'label' => 'Business Permit'],

  'id_doc' => ['type' => 'GovID', 'label' => null],
  'cda_doc' => ['type' => 'CDA', 'label' => null],
  'sec_doc' => ['type' => 'SEC', 'label' => null],
  'barangay_doc' => ['type' => 'BarangayCert', 'label' => null],
  'others_doc' => ['type' => 'Others', 'label' => null],
];

foreach ($fields as $field => $cfg) {
  $docType = (string)($cfg['type'] ?? '');
  $label = isset($cfg['label']) ? $cfg['label'] : null;
  if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) continue;
  $ext = strtolower(pathinfo((string)$_FILES[$field]['name'], PATHINFO_EXTENSION));
  if (!in_array($ext, ['jpg','jpeg','png','pdf'], true)) {
    $errors[] = "$field: invalid_file_type";
    continue;
  }

  $fieldSlug = preg_replace('/[^a-z0-9]+/i', '_', (string)$field);
  $fieldSlug = trim((string)$fieldSlug, '_');
  if ($fieldSlug === '') $fieldSlug = 'doc';
  $filename = $nameSlug . '_' . strtolower($docType) . '_' . $fieldSlug . '_' . time() . '_' . random_int(1000, 9999) . '.' . $ext;
  $dest = $uploadsDir . '/' . $filename;
  if (!move_uploaded_file($_FILES[$field]['tmp_name'], $dest)) {
    $errors[] = "$field: move_failed";
    continue;
  }

  $safe = tmm_scan_file_for_viruses($dest);
  if (!$safe) {
    if (is_file($dest)) @unlink($dest);
    $errors[] = "$field: security_scan_failed";
    continue;
  }

  $stmt = $db->prepare("INSERT INTO operator_documents (operator_id, doc_type, file_path, doc_status, remarks, is_verified) VALUES (?, ?, ?, 'Pending', ?, 0)");
  if (!$stmt) {
    if (is_file($dest)) @unlink($dest);
    $errors[] = "$field: db_prepare_failed";
    continue;
  }
  $stmt->bind_param('isss', $operatorId, $docType, $filename, $label);
  if (!$stmt->execute()) {
    $stmt->close();
    if (is_file($dest)) @unlink($dest);
    $errors[] = "$field: db_insert_failed";
    continue;
  }
  $stmt->close();

  $uploaded[] = $filename;
}

if ($errors) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'upload_failed', 'details' => $errors, 'files' => $uploaded]);
  exit;
}

if ($uploaded) {
  $opType = (string)(($op['operator_type'] ?? '') ?: 'Individual');
  $wfStatus = (string)($op['workflow_status'] ?? 'Draft');
  $vsStatus = (string)($op['verification_status'] ?? 'Draft');
  if ($wfStatus !== 'Inactive' && $vsStatus !== 'Inactive') {
    $slots = [];
    if ($opType === 'Cooperative') {
      $slots = [
        ['doc_type' => 'CDA', 'keywords' => ['registration']],
        ['doc_type' => 'CDA', 'keywords' => ['good standing', 'good_standing', 'standing']],
        ['doc_type' => 'Others', 'keywords' => ['board resolution', 'resolution']],
      ];
    } elseif ($opType === 'Corporation') {
      $slots = [
        ['doc_type' => 'SEC', 'keywords' => ['certificate', 'registration']],
        ['doc_type' => 'SEC', 'keywords' => ['articles', 'by-laws', 'bylaws', 'incorporation']],
        ['doc_type' => 'Others', 'keywords' => ['board resolution', 'resolution']],
      ];
    } else {
      $slots = [
        ['doc_type' => 'GovID', 'keywords' => ['gov', 'id', 'driver', 'license', 'umid', 'philsys']],
      ];
    }

    $hasUploadedAt = false;
    $chk = $db->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='operator_documents' AND COLUMN_NAME='uploaded_at' LIMIT 1");
    if ($chk && $chk->fetch_row()) $hasUploadedAt = true;
    $orderBy = $hasUploadedAt ? "uploaded_at DESC, doc_id DESC" : "doc_id DESC";
    $docs = [];
    $stmtV = $db->prepare("SELECT doc_id, doc_type, doc_status, remarks FROM operator_documents WHERE operator_id=? ORDER BY $orderBy");
    if ($stmtV) {
      $stmtV->bind_param('i', $operatorId);
      $stmtV->execute();
      $res = $stmtV->get_result();
      while ($r = $res->fetch_assoc()) $docs[] = $r;
      $stmtV->close();
    }

    $matchRemarks = function (string $remarks, array $keywords): bool {
      $t = strtolower($remarks);
      foreach ($keywords as $kw) {
        $k = strtolower((string)$kw);
        if ($k !== '' && strpos($t, $k) !== false) return true;
      }
      return false;
    };

    $slotPresent = array_fill(0, count($slots), false);
    $used = [];
    for ($i = 0; $i < count($slots); $i++) {
      $s = $slots[$i];
      foreach ($docs as $drow) {
        $did = (int)($drow['doc_id'] ?? 0);
        if ($did <= 0 || isset($used[$did])) continue;
        if ((string)($drow['doc_type'] ?? '') !== (string)$s['doc_type']) continue;
        $rem = (string)($drow['remarks'] ?? '');
        $keywords = (array)($s['keywords'] ?? []);
        if ($keywords && $rem !== '' && $matchRemarks($rem, $keywords)) {
          $used[$did] = true;
          $slotPresent[$i] = true;
          break;
        }
      }
    }
    for ($i = 0; $i < count($slots); $i++) {
      if ($slotPresent[$i]) continue;
      $s = $slots[$i];
      foreach ($docs as $drow) {
        $did = (int)($drow['doc_id'] ?? 0);
        if ($did <= 0 || isset($used[$did])) continue;
        if ((string)($drow['doc_type'] ?? '') !== (string)$s['doc_type']) continue;
        $used[$did] = true;
        $slotPresent[$i] = true;
        break;
      }
    }
    $allPresent = true;
    foreach ($slotPresent as $ok) { if (!$ok) { $allPresent = false; break; } }

    $hasRejectedRequired = false;
    for ($i = 0; $i < count($slots); $i++) {
      $s = $slots[$i];
      foreach ($docs as $drow) {
        if ((string)($drow['doc_status'] ?? '') !== 'Rejected') continue;
        if ((string)($drow['doc_type'] ?? '') !== (string)$s['doc_type']) continue;
        $rem = (string)($drow['remarks'] ?? '');
        $keywords = (array)($s['keywords'] ?? []);
        if (!$keywords) { $hasRejectedRequired = true; break 2; }
        if ($rem !== '' && $matchRemarks($rem, $keywords)) { $hasRejectedRequired = true; break 2; }
      }
    }

    $newWf = 'Draft';
    if (count($docs) > 0) {
      if ($hasRejectedRequired) $newWf = 'Returned';
      elseif ($allPresent) $newWf = 'Pending Validation';
      else $newWf = 'Incomplete';
    }

    $stmtS = $db->prepare("UPDATE operators
                           SET workflow_status=CASE
                             WHEN workflow_status IN ('Inactive','Active') THEN workflow_status
                             ELSE ?
                           END,
                           status=CASE WHEN status='Inactive' THEN 'Inactive' ELSE 'Pending' END,
                           verification_status=CASE WHEN verification_status='Inactive' THEN 'Inactive' ELSE 'Draft' END
                           WHERE id=?");
    if ($stmtS) {
      $stmtS->bind_param('si', $newWf, $operatorId);
      $stmtS->execute();
      $stmtS->close();
    }
  }
}

echo json_encode(['ok' => true, 'operator_id' => $operatorId, 'files' => $uploaded]);
if ($uploaded) {
  tmm_audit_event($db, 'operator.docs.upload', 'operator', (string)$operatorId, ['files' => $uploaded]);
}
