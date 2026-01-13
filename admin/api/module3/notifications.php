<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
header('Content-Type: application/json');
$db = db();
require_permission('analytics.view');
$target = isset($_GET['target']) ? strtolower(trim($_GET['target'])) : '';
if ($target !== 'inspection' && $target !== 'parking') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Invalid target']);
  exit;
}
$afterId = isset($_GET['after_id']) ? (int)$_GET['after_id'] : 0;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
if ($limit <= 0 || $limit > 200) { $limit = 50; }
$sql = "SELECT tn.*, o.name AS officer_name, o.badge_no
        FROM ticket_notifications tn
        LEFT JOIN officers o ON tn.filter_officer_id = o.officer_id
        WHERE tn.target_module = ?
          AND tn.id > ?
        ORDER BY tn.id ASC
        LIMIT ?";
$stmt = $db->prepare($sql);
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Database error']);
  exit;
}
$stmt->bind_param('sii', $target, $afterId, $limit);
$stmt->execute();
$res = $stmt->get_result();
$items = [];
if ($res) {
  while ($row = $res->fetch_assoc()) {
    $items[] = [
      'id' => (int)$row['id'],
      'target_module' => $row['target_module'],
      'filter_period' => $row['filter_period'],
      'filter_status' => $row['filter_status'],
      'filter_officer_id' => $row['filter_officer_id'] !== null ? (int)$row['filter_officer_id'] : null,
      'filter_q' => $row['filter_q'],
      'ticket_count' => (int)$row['ticket_count'],
      'last_ticket_date' => $row['last_ticket_date'],
      'created_at' => $row['created_at'],
      'officer_name' => $row['officer_name'],
      'badge_no' => $row['badge_no'],
    ];
  }
}
echo json_encode(['ok' => true, 'target' => $target, 'items' => $items]);
