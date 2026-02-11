<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
header('Content-Type: application/json');

if (php_sapi_name() === 'cli') {
} else {
  require_permission('module4.inspections.manage');
}

$hasCol = function (string $table, string $col) use ($db): bool {
  $t = $db->real_escape_string($table);
  $c = $db->real_escape_string($col);
  $r = $db->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
  return $r && $r->num_rows > 0;
};

$ensureCol = function (string $table, string $col, string $ddl) use ($db, $hasCol): void {
  if (!$hasCol($table, $col)) {
    @$db->query("ALTER TABLE `$table` ADD COLUMN " . $ddl);
  }
};

$ensureEnumValue = function (string $table, string $col, string $value) use ($db): void {
  $t = $db->real_escape_string($table);
  $c = $db->real_escape_string($col);
  $res = $db->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
  if (!$res || !$row = $res->fetch_assoc()) return;
  $type = (string)($row['Type'] ?? '');
  if (stripos($type, 'enum(') !== 0) return;
  if (strpos($type, "'" . $value . "'") !== false) return;
  if (!preg_match("/^enum\\((.*)\\)$/i", $type, $m)) return;
  $inner = (string)($m[1] ?? '');
  $inner = trim($inner);
  if ($inner === '') return;
  $newType = "ENUM(" . $inner . ",'" . $db->real_escape_string($value) . "')";
  @$db->query("ALTER TABLE `$t` MODIFY COLUMN `$c` $newType");
};

$overdueStatus = 'Overdue / No-Show';
$ensureEnumValue('inspection_schedules', 'status', 'Overdue');
$ensureEnumValue('inspection_schedules', 'status', $overdueStatus);
$ensureCol('inspection_schedules', 'status_remarks', "status_remarks VARCHAR(255) NULL");
$ensureCol('inspection_schedules', 'overdue_marked_at', "overdue_marked_at DATETIME NULL");

$sql = "UPDATE inspection_schedules s
        LEFT JOIN inspection_results r ON r.schedule_id=s.schedule_id
        SET s.status='" . $db->real_escape_string($overdueStatus) . "', s.overdue_marked_at=NOW()
        WHERE DATE(COALESCE(s.schedule_date, s.scheduled_at)) < CURDATE()
          AND s.status IN ('Scheduled','Pending Assignment')
          AND r.result_id IS NULL";

$ok = $db->query($sql);
if (!$ok) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_error']);
  exit;
}

echo json_encode(['ok' => true, 'marked' => (int)($db->affected_rows ?? 0)]);
