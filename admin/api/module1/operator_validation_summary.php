<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('module1.write');

require_once __DIR__ . '/../../includes/db.php';
$db = db();
header('Content-Type: application/json');

$operatorId = (int)($_GET['operator_id'] ?? 0);
if ($operatorId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_operator_id']);
  exit;
}

$sql = "SELECT
  o.workflow_status,
  GROUP_CONCAT(
    DISTINCT
    CASE
      WHEN TRIM(SUBSTRING_INDEX(COALESCE(d.remarks,''), '|', 1)) <> '' THEN TRIM(SUBSTRING_INDEX(COALESCE(d.remarks,''), '|', 1))
      WHEN d.doc_type='GovID' THEN 'Valid Government ID'
      WHEN d.doc_type='BarangayCert' THEN 'Proof of Address'
      WHEN d.doc_type='CDA' THEN 'CDA'
      WHEN d.doc_type='SEC' THEN 'SEC'
      WHEN d.doc_type='Others' THEN 'Others'
      ELSE d.doc_type
    END
    ORDER BY d.doc_type
    SEPARATOR ', '
  ) AS uploaded_labels,
  SUM(CASE WHEN d.doc_id IS NULL THEN 0 ELSE 1 END) AS doc_count
FROM operators o
LEFT JOIN (
  SELECT od.*
  FROM operator_documents od
  JOIN (
    SELECT operator_id,
           doc_type,
           TRIM(SUBSTRING_INDEX(COALESCE(remarks,''), '|', 1)) AS head_label,
           MAX(doc_id) AS doc_id
    FROM operator_documents
    GROUP BY operator_id, doc_type, head_label
  ) x ON x.doc_id=od.doc_id
) d ON d.operator_id=o.id
WHERE o.id=?
GROUP BY o.id
LIMIT 1";

$stmt = $db->prepare($sql);
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmt->bind_param('i', $operatorId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'error' => 'operator_not_found']);
  exit;
}

echo json_encode([
  'ok' => true,
  'data' => [
    'workflow_status' => (string)($row['workflow_status'] ?? ''),
    'uploaded_labels' => (string)($row['uploaded_labels'] ?? ''),
    'doc_count' => (int)($row['doc_count'] ?? 0),
  ],
]);

