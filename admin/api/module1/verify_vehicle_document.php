<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

$db = db();
require_permission('module1.vehicles.write');

$docId = isset($_POST['doc_id']) ? (int)$_POST['doc_id'] : 0;
$verified = isset($_POST['is_verified']) ? (int)$_POST['is_verified'] : -1;
if ($docId <= 0 || ($verified !== 0 && $verified !== 1)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'invalid_input']);
  exit;
}

$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

$stmt = $db->prepare("UPDATE vehicle_documents SET is_verified=?, verified_by=?, verified_at=CASE WHEN ?=1 THEN NOW() ELSE NULL END WHERE doc_id=?");
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$verifiedBy = $verified === 1 ? ($userId > 0 ? $userId : null) : null;
$stmt->bind_param('iiii', $verified, $verifiedBy, $verified, $docId);
$ok = $stmt->execute();
$stmt->close();

echo json_encode(['ok' => $ok]);

