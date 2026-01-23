<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/export.php';

$db = db();
require_permission('reports.export');

$format = tmm_export_format();
tmm_send_export_headers($format, 'operator_document_validation');

$q = trim((string)($_GET['q'] ?? ''));
$type = trim((string)($_GET['operator_type'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));

$sql = "SELECT
  o.id AS operator_id,
  o.operator_type,
  COALESCE(NULLIF(o.registered_name,''), NULLIF(o.name,''), o.full_name) AS operator_name,
  o.workflow_status,
  o.status,
  o.verification_status,
  MAX(CASE WHEN d.doc_type='GovID' THEN d.is_verified ELSE 0 END) AS govid_verified,
  MAX(CASE WHEN d.doc_type='CDA' THEN d.is_verified ELSE 0 END) AS cda_verified,
  MAX(CASE WHEN d.doc_type='SEC' THEN d.is_verified ELSE 0 END) AS sec_verified,
  MAX(CASE WHEN d.doc_type='BarangayCert' THEN d.is_verified ELSE 0 END) AS brgy_verified,
  MAX(CASE WHEN d.doc_type='Others' THEN d.is_verified ELSE 0 END) AS others_verified,
  SUM(CASE WHEN d.doc_id IS NULL THEN 0 ELSE 1 END) AS doc_count,
  o.created_at
FROM operators o
LEFT JOIN operator_documents d ON d.operator_id=o.id
WHERE 1=1";

$params = [];
$types = '';

if ($q !== '') {
  $sql .= " AND (o.registered_name LIKE ? OR o.name LIKE ? OR o.full_name LIKE ?)";
  $like = "%$q%";
  $params[] = $like; $params[] = $like; $params[] = $like;
  $types .= 'sss';
}
if ($type !== '' && $type !== 'Operator type') {
  $sql .= " AND o.operator_type=?";
  $params[] = $type;
  $types .= 's';
}
if ($status !== '' && $status !== 'Status') {
  $sql .= " AND o.workflow_status=?";
  $params[] = $status;
  $types .= 's';
}

$sql .= " GROUP BY o.id ORDER BY o.created_at DESC";

if ($params) {
  $stmt = $db->prepare($sql);
  if (!$stmt) { http_response_code(500); echo 'db_prepare_failed'; exit; }
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();
} else {
  $res = $db->query($sql);
}

$headers = ['operator_id','operator_type','operator_name','workflow_status','status','verification_status','doc_count','govid_verified','cda_verified','sec_verified','brgy_verified','others_verified','created_at'];
tmm_export_from_result($format, $headers, $res, function ($r) {
  return [
    'operator_id' => $r['operator_id'] ?? '',
    'operator_type' => $r['operator_type'] ?? '',
    'operator_name' => $r['operator_name'] ?? '',
    'workflow_status' => $r['workflow_status'] ?? '',
    'status' => $r['status'] ?? '',
    'verification_status' => $r['verification_status'] ?? '',
    'doc_count' => $r['doc_count'] ?? '',
    'govid_verified' => $r['govid_verified'] ?? '',
    'cda_verified' => $r['cda_verified'] ?? '',
    'sec_verified' => $r['sec_verified'] ?? '',
    'brgy_verified' => $r['brgy_verified'] ?? '',
    'others_verified' => $r['others_verified'] ?? '',
    'created_at' => $r['created_at'] ?? '',
  ];
});
