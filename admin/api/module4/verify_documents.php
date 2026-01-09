<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
require_role(['Inspector','Admin']);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['ok'=>false,'error'=>'invalid_method']);
  exit;
}

$plate = strtoupper(trim($_POST['plate_number'] ?? ''));
if ($plate === '') {
  echo json_encode(['ok'=>false,'error'=>'missing_plate']);
  exit;
}

$stmtCR = $db->prepare("SELECT id FROM documents WHERE plate_number=? AND type='cr' ORDER BY uploaded_at DESC LIMIT 1");
$stmtCR->bind_param('s', $plate);
$stmtCR->execute();
$rowCR = $stmtCR->get_result()->fetch_assoc();

$stmtOR = $db->prepare("SELECT id FROM documents WHERE plate_number=? AND type='or' ORDER BY uploaded_at DESC LIMIT 1");
$stmtOR->bind_param('s', $plate);
$stmtOR->execute();
$rowOR = $stmtOR->get_result()->fetch_assoc();

if (!$rowCR || !$rowOR) {
  echo json_encode(['ok'=>false,'error'=>'missing_docs']);
  exit;
}

$crId = (int)$rowCR['id'];
$orId = (int)$rowOR['id'];

$stmtU1 = $db->prepare("UPDATE documents SET verified=1 WHERE id=?");
$stmtU1->bind_param('i', $crId);
$ok1 = $stmtU1->execute();

$stmtU2 = $db->prepare("UPDATE documents SET verified=1 WHERE id=?");
$stmtU2->bind_param('i', $orId);
$ok2 = $stmtU2->execute();

if (!$ok1 || !$ok2) {
  echo json_encode(['ok'=>false,'error'=>'db_error']);
  exit;
}

echo json_encode(['ok'=>true,'plate_number'=>$plate,'cr_verified'=>1,'or_verified'=>1]);
?> 
