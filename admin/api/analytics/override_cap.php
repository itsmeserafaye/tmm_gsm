<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
require_role(['Admin']);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['ok'=>false,'error'=>'invalid_method']);
  exit;
}

$routeId = trim($_POST['route_id'] ?? '');
$cap = isset($_POST['cap']) ? (int)$_POST['cap'] : null;
$reason = trim($_POST['reason'] ?? '');
$ts = trim($_POST['ts'] ?? '');
if ($routeId === '' || $cap === null) {
  echo json_encode(['ok'=>false,'error'=>'missing_fields']);
  exit;
}
$tsVal = $ts !== '' ? date('Y-m-d H:i:s', strtotime($ts)) : date('Y-m-d H:i:s');
$role = function_exists('current_user_role') ? current_user_role() : 'Admin';
$fullReason = $reason !== '' ? $reason : 'manual override';
$fullReason .= ' by ' . $role;
$conf = 1.0;
$stmt = $db->prepare("INSERT INTO route_cap_schedule(route_id, ts, cap, reason, confidence) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param('ssisd', $routeId, $tsVal, $cap, $fullReason, $conf);
$ok = $stmt->execute();
if ($ok) {
  echo json_encode(['ok'=>true,'route_id'=>$routeId,'cap'=>$cap,'ts'=>$tsVal,'reason'=>$fullReason,'confidence'=>$conf]);
} else {
  echo json_encode(['ok'=>false,'error'=>'db_error']);
}
?> 
