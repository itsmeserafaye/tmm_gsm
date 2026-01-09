<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
header('Content-Type: application/json');
$id = (int)($_POST['officer_id'] ?? 0);
$badge = trim($_POST['badge_no'] ?? '');
if ($id <= 0 && $badge === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'missing_identifier']); exit; }
if ($id > 0) {
  $stmt = $db->prepare("SELECT officer_id, name, badge_no, active_status FROM officers WHERE officer_id=?");
  $stmt->bind_param('i', $id);
} else {
  $stmt = $db->prepare("SELECT officer_id, name, badge_no, active_status FROM officers WHERE badge_no=?");
  $stmt->bind_param('s', $badge);
}
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
if (!$row) { echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }
echo json_encode(['ok'=>true,'officer_id'=>$row['officer_id'],'name'=>$row['name'],'badge_no'=>$row['badge_no'],'active'=>((int)$row['active_status']===1)]);
?> 
