<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/util.php';

$db = db();
header('Content-Type: application/json');
require_any_permission(['module3.analytics', 'module3.read', 'module3.issue']);

$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
$limit = (int)($_GET['limit'] ?? 10);
if ($limit <= 0) $limit = 10;
if ($limit > 50) $limit = 50;

$hasCol = function (string $table, string $col) use ($db): bool {
  $stmt = $db->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1");
  if (!$stmt) return false;
  $stmt->bind_param('ss', $table, $col);
  $stmt->execute();
  $res = $stmt->get_result();
  $ok = (bool)($res && $res->fetch_row());
  $stmt->close();
  return $ok;
};

$vtHasSeverity = $hasCol('violation_types', 'severity');
$vtHasCategory = $hasCol('violation_types', 'category');

$sql = "SELECT
  v.violation_type AS violation_code,
  COALESCE(vt.description,'') AS description," .
  ($vtHasSeverity ? " COALESCE(NULLIF(vt.severity,''),'') AS severity," : " '' AS severity,") .
  ($vtHasCategory ? " COALESCE(NULLIF(vt.category,''),'') AS category," : " '' AS category,") .
  " COUNT(*) AS total_count,
  COALESCE(SUM(v.amount),0) AS total_amount
FROM violations v
LEFT JOIN violation_types vt ON vt.violation_code=v.violation_type";

$conds = [];
$params = [];
$types = '';

if ($from !== '' && preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $from)) {
  $conds[] = "COALESCE(v.violation_date, v.created_at) >= ?";
  $params[] = $from . " 00:00:00";
  $types .= 's';
}
if ($to !== '' && preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $to)) {
  $conds[] = "COALESCE(v.violation_date, v.created_at) <= ?";
  $params[] = $to . " 23:59:59";
  $types .= 's';
}
if ($conds) $sql .= " WHERE " . implode(" AND ", $conds);
$sql .= " GROUP BY v.violation_type ORDER BY total_count DESC, total_amount DESC LIMIT " . (int)$limit;

$rows = [];
if ($params) {
  $stmt = $db->prepare($sql);
  if (!$stmt) error_response(500, 'db_prepare_failed');
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($res && ($r = $res->fetch_assoc())) $rows[] = $r;
  $stmt->close();
} else {
  $res = $db->query($sql);
  if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
}

echo json_encode(['ok' => true, 'data' => $rows]);

