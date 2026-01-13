<?php
require_once __DIR__ . '/../includes/db.php';
$db = db();

$db->begin_transaction();
try {
  $opId = null;
  $stmt = $db->prepare("SELECT id FROM operators WHERE full_name='TEST_E2E_OP' LIMIT 1");
  if ($stmt) {
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $opId = $row ? (int)($row['id'] ?? 0) : null;
    $stmt->close();
  }

  $stmt = $db->prepare("DELETE FROM franchise_applications WHERE operator_name='TEST_E2E_OP' OR (operator_id IS NOT NULL AND operator_id=?)");
  if ($stmt) {
    $opIdParam = $opId ?: 0;
    $stmt->bind_param('i', $opIdParam);
    $stmt->execute();
    $stmt->close();
  }

  if ($opId) {
    $stmt = $db->prepare("DELETE FROM operators WHERE id=?");
    if ($stmt) {
      $stmt->bind_param('i', $opId);
      $stmt->execute();
      $stmt->close();
    }
  }

  $db->commit();
  echo "Removed TEST_E2E_OP from franchise_applications" . ($opId ? " and operators" : "") . ".\n";
} catch (Throwable $e) {
  $db->rollback();
  echo "Failed: " . $e->getMessage() . "\n";
  exit(1);
}

