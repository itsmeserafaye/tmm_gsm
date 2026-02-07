<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
header('Content-Type: application/json');
require_any_permission(['module3.issue','module3.read']);

$db = db();

$items = [];
$res = $db->query("SELECT plate_number FROM vehicles WHERE COALESCE(NULLIF(plate_number,''),'')<>'' ORDER BY plate_number ASC LIMIT 5000");
if ($res) {
  while ($r = $res->fetch_assoc()) {
    $p = strtoupper(trim((string)($r['plate_number'] ?? '')));
    if ($p === '') continue;
    $items[] = $p;
  }
}

echo json_encode(['ok' => true, 'data' => $items]);

