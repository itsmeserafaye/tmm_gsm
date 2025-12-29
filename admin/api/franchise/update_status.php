<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
header('Content-Type: application/json');
$id = (int)($_POST['application_id'] ?? 0);
$status = trim($_POST['status'] ?? '');
$permit = trim($_POST['permit_number'] ?? '');
if ($id <= 0 || $status === '') { echo json_encode(['error'=>'missing_fields']); exit; }
$stmt = $db->prepare("UPDATE franchise_applications SET status=? WHERE application_id=?");
$stmt->bind_param('si', $status, $id);
$ok = $stmt->execute();
if (!$ok) { echo json_encode(['error'=>'update_failed']); exit; }
if ($status === 'Endorsed') {
  $stmt2 = $db->prepare("INSERT INTO endorsement_records (application_id, issued_date, permit_number) VALUES (?, CURDATE(), ?)");
  $stmt2->bind_param('is', $id, $permit);
  $stmt2->execute();
}
echo json_encode(['ok'=>true, 'application_id'=>$id, 'status'=>$status, 'permit_number'=>$permit]);
?> 
