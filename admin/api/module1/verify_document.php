<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');
$db = db();
require_permission('module1.vehicles.write');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}

$source = trim((string)($_POST['source'] ?? ''));
$docId = (int)($_POST['doc_id'] ?? 0);
$verified = isset($_POST['is_verified']) ? (int)$_POST['is_verified'] : -1;

if ($docId <= 0 || ($verified !== 0 && $verified !== 1) || ($source !== 'vehicle_documents' && $source !== 'documents')) {
  echo json_encode(['ok' => false, 'error' => 'invalid_input']);
  exit;
}

$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

if ($source === 'vehicle_documents') {
  $stmt = $db->prepare("UPDATE vehicle_documents
                        SET is_verified=?,
                            verified_by=?,
                            verified_at=CASE WHEN ?=1 THEN NOW() ELSE NULL END
                        WHERE doc_id=?");
  if (!$stmt) { echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']); exit; }
  $verifiedBy = $verified === 1 ? ($userId > 0 ? $userId : null) : null;
  $stmt->bind_param('iiii', $verified, $verifiedBy, $verified, $docId);
  $ok = (bool)$stmt->execute();
  $stmt->close();
  echo json_encode(['ok' => $ok]);
  exit;
}

$stmt = $db->prepare("UPDATE documents SET verified=? WHERE id=?");
if (!$stmt) { echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']); exit; }
$stmt->bind_param('ii', $verified, $docId);
$ok = (bool)$stmt->execute();
$stmt->close();

echo json_encode(['ok' => $ok]);

