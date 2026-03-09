<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_permission('module5.manage_terminal');

$db = db();
header('Content-Type: application/json');

function tmm_table_exists(mysqli $db, string $table): bool {
  $stmt = $db->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1");
  if (!$stmt) return false;
  $stmt->bind_param('s', $table);
  $stmt->execute();
  $ok = (bool)($stmt->get_result()->fetch_row());
  $stmt->close();
  return $ok;
}

function tmm_col(mysqli $db, string $table, string $column): ?array {
  $stmt = $db->prepare("SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY, EXTRA
                        FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1");
  if (!$stmt) return null;
  $stmt->bind_param('ss', $table, $column);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  return $row ?: null;
}

if (!tmm_table_exists($db, 'parking_slots')) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'parking_slots_missing']);
  exit;
}

$col = tmm_col($db, 'parking_slots', 'slot_id');
$looksGood = false;
if ($col) {
  $key = strtoupper((string)($col['COLUMN_KEY'] ?? ''));
  $extra = strtolower((string)($col['EXTRA'] ?? ''));
  $looksGood = ($key === 'PRI' && strpos($extra, 'auto_increment') !== false);
}
if ($looksGood) {
  echo json_encode(['ok' => true, 'status' => 'already_ok']);
  exit;
}

$refPayments = 0;
$refEvents = 0;
if (tmm_table_exists($db, 'parking_payments')) {
  $res = $db->query("SELECT COUNT(*) AS c FROM parking_payments WHERE COALESCE(slot_id,0) <> 0");
  if ($res) { $row = $res->fetch_assoc(); $refPayments = (int)($row['c'] ?? 0); }
}
if (tmm_table_exists($db, 'parking_slot_events')) {
  $res = $db->query("SELECT COUNT(*) AS c FROM parking_slot_events WHERE COALESCE(slot_id,0) <> 0");
  if ($res) { $row = $res->fetch_assoc(); $refEvents = (int)($row['c'] ?? 0); }
}
if ($refPayments > 0 || $refEvents > 0) {
  http_response_code(409);
  echo json_encode([
    'ok' => false,
    'error' => 'cannot_migrate_with_existing_slot_refs',
    'parking_payments_nonzero_slot_id' => $refPayments,
    'parking_slot_events_nonzero_slot_id' => $refEvents
  ]);
  exit;
}

$stamp = date('Ymd_His');
$oldName = "parking_slots_old_$stamp";
$newName = "parking_slots_new_$stamp";

$db->begin_transaction();
try {
  $db->query("DROP TABLE IF EXISTS `$newName`");
  $create = $db->query("CREATE TABLE `$newName` (
      slot_id INT AUTO_INCREMENT PRIMARY KEY,
      terminal_id INT NOT NULL,
      slot_no VARCHAR(64) NOT NULL,
      status ENUM('Free','Occupied') DEFAULT 'Free',
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY uniq_terminal_slot (terminal_id, slot_no),
      KEY idx_terminal (terminal_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
  if (!$create) throw new Exception('create_failed');

  $copy = $db->query("INSERT INTO `$newName` (terminal_id, slot_no, status)
                      SELECT terminal_id,
                             LEFT(TRIM(COALESCE(slot_no,'')), 64) AS slot_no,
                             CASE WHEN LOWER(TRIM(COALESCE(status,'')))='occupied' THEN 'Occupied' ELSE 'Free' END AS status
                      FROM parking_slots
                      WHERE terminal_id IS NOT NULL AND COALESCE(TRIM(slot_no),'') <> ''
                      GROUP BY terminal_id, LEFT(TRIM(COALESCE(slot_no,'')), 64)");
  if ($copy === false) throw new Exception('copy_failed');
  $copied = (int)$db->affected_rows;

  $rename = $db->query("RENAME TABLE parking_slots TO `$oldName`, `$newName` TO parking_slots");
  if (!$rename) throw new Exception('rename_failed');

  $db->commit();
  echo json_encode([
    'ok' => true,
    'status' => 'rebuilt',
    'copied_rows' => $copied,
    'old_table' => $oldName
  ]);
} catch (Throwable $e) {
  $db->rollback();
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'migration_failed']);
}
