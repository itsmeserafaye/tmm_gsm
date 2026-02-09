<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');
require_permission('analytics.view');

$db = db();

$db->query("CREATE TABLE IF NOT EXISTS ai_weight_tuning_runs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  started_at DATETIME NOT NULL,
  finished_at DATETIME DEFAULT NULL,
  status VARCHAR(40) NOT NULL,
  prev_weights JSON DEFAULT NULL,
  new_weights JSON DEFAULT NULL,
  metrics JSON DEFAULT NULL,
  error TEXT DEFAULT NULL,
  INDEX idx_started (started_at),
  INDEX idx_status (status)
) ENGINE=InnoDB");

$limit = (int)($_GET['limit'] ?? 5);
if ($limit < 1) $limit = 1;
if ($limit > 20) $limit = 20;

$rows = [];
$res = $db->query("SELECT id, started_at, finished_at, status, prev_weights, new_weights, metrics, error
                   FROM ai_weight_tuning_runs
                   ORDER BY id DESC
                   LIMIT {$limit}");
if ($res) {
  while ($r = $res->fetch_assoc()) {
    $rows[] = [
      'id' => (int)($r['id'] ?? 0),
      'started_at' => (string)($r['started_at'] ?? ''),
      'finished_at' => $r['finished_at'] ? (string)$r['finished_at'] : null,
      'status' => (string)($r['status'] ?? ''),
      'prev_weights' => $r['prev_weights'] ? json_decode((string)$r['prev_weights'], true) : null,
      'new_weights' => $r['new_weights'] ? json_decode((string)$r['new_weights'], true) : null,
      'metrics' => $r['metrics'] ? json_decode((string)$r['metrics'], true) : null,
      'error' => $r['error'] ? (string)$r['error'] : null,
    ];
  }
}

echo json_encode([
  'ok' => true,
  'latest' => $rows[0] ?? null,
  'runs' => $rows,
]);

