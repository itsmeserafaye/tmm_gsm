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
  $schema = '';
  $schRes = $db->query("SELECT DATABASE() AS db");
  if ($schRes) $schema = (string)(($schRes->fetch_assoc()['db'] ?? '') ?: '');

  $hasCol = function (string $table, string $col) use ($db, $schema): bool {
    if ($schema === '') return false;
    $t = $db->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1");
    if (!$t) return false;
    $t->bind_param('sss', $schema, $table, $col);
    $t->execute();
    $res = $t->get_result();
    $ok = (bool)($res && $res->fetch_row());
    $t->close();
    return $ok;
  };

  $idCol = $hasCol('vehicle_documents', 'doc_id') ? 'doc_id' : ($hasCol('vehicle_documents', 'id') ? 'id' : 'doc_id');
  $verCol = $hasCol('vehicle_documents', 'is_verified') ? 'is_verified'
    : ($hasCol('vehicle_documents', 'verified') ? 'verified'
    : ($hasCol('vehicle_documents', 'isApproved') ? 'isApproved' : 'is_verified'));
  $hasVerifiedBy = $hasCol('vehicle_documents', 'verified_by');
  $hasVerifiedAt = $hasCol('vehicle_documents', 'verified_at');

  $setParts = [];
  $types = '';
  $params = [];

  $setParts[] = "`{$verCol}`=?";
  $types .= 'i';
  $params[] = $verified;

  if ($hasVerifiedBy) {
    $setParts[] = "verified_by=CASE WHEN ?=1 THEN ? ELSE NULL END";
    $types .= 'ii';
    $params[] = $verified;
    $params[] = $userId > 0 ? $userId : null;
  }
  if ($hasVerifiedAt) {
    $setParts[] = "verified_at=CASE WHEN ?=1 THEN NOW() ELSE NULL END";
    $types .= 'i';
    $params[] = $verified;
  }

  $sql = "UPDATE vehicle_documents SET " . implode(", ", $setParts) . " WHERE `{$idCol}`=?";
  $types .= 'i';
  $params[] = $docId;

  $stmt = $db->prepare($sql);
  if (!$stmt) { echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']); exit; }
  $stmt->bind_param($types, ...$params);
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
