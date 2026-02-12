<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/util.php';
 
$db = db();
header('Content-Type: application/json');
require_any_permission(['module5.read','module5.manage_terminal','module5.parking_fees']);
 
$type = trim((string)($_GET['type'] ?? 'Terminal'));
$type = $type === 'Parking' ? 'Parking' : 'Terminal';
 
$q = trim((string)($_GET['q'] ?? ''));
$limit = (int)($_GET['limit'] ?? 200);
if ($limit <= 0) $limit = 200;
if ($limit > 500) $limit = 500;
 
$where = $type === 'Parking' ? "t.type='Parking'" : "t.type<>'Parking'";
 
$params = [];
$types = '';
$qLike = '';
if ($q !== '') {
  $qLike = '%' . $q . '%';
  $where .= " AND (t.name LIKE ? OR COALESCE(t.category,'') LIKE ? OR COALESCE(t.city,'') LIKE ?)";
  $types .= 'sss';
  $params[] = $qLike;
  $params[] = $qLike;
  $params[] = $qLike;
}
 
$sql = "SELECT
          t.id,
          t.name,
          COALESCE(t.capacity,0) AS capacity,
          COALESCE(t.category,'') AS category,
          COALESCE(t.city,'') AS city,
          SUM(CASE WHEN ps.status='Occupied' THEN 1 ELSE 0 END) AS occupied_slots,
          SUM(CASE WHEN ps.status='Free' THEN 1 ELSE 0 END) AS free_slots,
          COUNT(ps.slot_id) AS total_slots,
          (SELECT COUNT(*) FROM terminal_queue tq WHERE tq.terminal_id=t.id AND tq.status='Queued') AS queue_len,
          (SELECT COUNT(*) FROM terminal_queue tq WHERE tq.terminal_id=t.id AND tq.status='Queued' AND tq.priority='Priority') AS queue_priority_len
        FROM terminals t
        LEFT JOIN parking_slots ps ON ps.terminal_id=t.id
        WHERE {$where}
        GROUP BY t.id
        ORDER BY t.name ASC
        LIMIT {$limit}";
 
if ($types !== '') {
  $stmt = $db->prepare($sql);
  if (!$stmt) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_prepare_failed']); exit; }
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();
} else {
  $res = $db->query($sql);
}
 
$rows = [];
if ($res) {
  while ($r = $res->fetch_assoc()) {
    $cap = (int)($r['capacity'] ?? 0);
    $occ = (int)($r['occupied_slots'] ?? 0);
    $total = (int)($r['total_slots'] ?? 0);
    $effectiveTotal = $total > 0 ? $total : ($cap > 0 ? $cap : 0);
    if ($cap > $effectiveTotal) $effectiveTotal = $cap;
    $free = $effectiveTotal > 0 ? max(0, $effectiveTotal - $occ) : (int)($r['free_slots'] ?? 0);
    $cong = $effectiveTotal > 0 ? round(($occ / $effectiveTotal) * 100, 1) : 0.0;
 
    $rows[] = [
      'id' => (int)($r['id'] ?? 0),
      'name' => (string)($r['name'] ?? ''),
      'category' => (string)($r['category'] ?? ''),
      'city' => (string)($r['city'] ?? ''),
      'capacity' => $cap,
      'occupied' => $occ,
      'free' => $free,
      'total' => $effectiveTotal,
      'congestion_pct' => $cong,
      'queue_len' => (int)($r['queue_len'] ?? 0),
      'queue_priority_len' => (int)($r['queue_priority_len'] ?? 0),
    ];
  }
}
if (isset($stmt) && $stmt) $stmt->close();
 
echo json_encode(['ok' => true, 'data' => $rows, 'type' => $type]);
?>
