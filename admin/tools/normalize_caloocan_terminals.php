<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$db = db();
require_role(['SuperAdmin']);

function has_column(mysqli $db, string $table, string $col): bool {
  $stmt = $db->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1");
  if (!$stmt) return false;
  $stmt->bind_param('ss', $table, $col);
  $stmt->execute();
  $ok = (bool)($stmt->get_result()->fetch_row());
  $stmt->close();
  return $ok;
}

function table_exists(mysqli $db, string $name): bool {
  $stmt = $db->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1");
  if (!$stmt) return false;
  $stmt->bind_param('s', $name);
  $stmt->execute();
  $ok = (bool)($stmt->get_result()->fetch_row());
  $stmt->close();
  return $ok;
}

$confirm = (string)($_POST['confirm'] ?? '');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Content-Type: text/html; charset=utf-8');
  echo '<div style="max-width:760px;margin:40px auto;font-family:system-ui,Segoe UI,Arial,sans-serif">';
  echo '<h2 style="margin:0 0 12px 0">Normalize Caloocan Terminals</h2>';
  echo '<p style="margin:0 0 16px 0;color:#444">Updates a few placeholder/ambiguous terminal records to clearer Caloocan-based names and addresses, and fixes matching terminal assignment names.</p>';
  echo '<div style="margin:0 0 8px 0;font-weight:700">Confirm</div>';
  echo '<form method="post">';
  echo '<label style="display:block;margin:0 0 6px 0;font-weight:700">Type NORMALIZE_CALOOCAN_TERMINALS to confirm</label>';
  echo '<input name="confirm" style="width:100%;padding:10px;border:1px solid #ccc;border-radius:8px" />';
  echo '<button type="submit" style="margin-top:12px;padding:10px 14px;border:0;border-radius:8px;background:#1d4ed8;color:#fff;font-weight:800">Run</button>';
  echo '</form>';
  echo '</div>';
  exit;
}

header('Content-Type: application/json');
if ($confirm !== 'NORMALIZE_CALOOCAN_TERMINALS') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'confirm_required']);
  exit;
}

if (!table_exists($db, 'terminals')) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'missing_table_terminals']);
  exit;
}

$hasCity = has_column($db, 'terminals', 'city');
$hasType = has_column($db, 'terminals', 'type');
$hasCap = has_column($db, 'terminals', 'capacity');
$hasAddr = has_column($db, 'terminals', 'address');
$hasLoc = has_column($db, 'terminals', 'location');

$updates = [
  [
    'id' => 1,
    'old_name' => 'Central Integrated Terminal',
    'name' => 'Monumento Transport Terminal (Caloocan)',
    'city' => 'Caloocan City',
    'location' => 'Monumento, Caloocan City',
    'address' => 'EDSA / Rizal Ave Ext area, Monumento, Caloocan City',
    'type' => 'Terminal',
    'capacity' => 500,
  ],
  [
    'id' => 2,
    'old_name' => 'North Bound Terminal',
    'name' => 'Monumento Northbound Bus Terminal (Caloocan)',
    'city' => 'Caloocan City',
    'location' => 'Monumento, Caloocan City',
    'address' => 'EDSA Monumento area, Caloocan City',
    'type' => 'Terminal',
    'capacity' => 300,
  ],
  [
    'id' => 3,
    'old_name' => 'Barangay 101 Tricycle Hub',
    'name' => 'Barangay 101 Tricycle Terminal (Caloocan)',
    'city' => 'Caloocan City',
    'location' => 'Barangay 101, Caloocan City',
    'address' => 'Near Barangay 101 area, Caloocan City',
    'type' => 'Terminal',
    'capacity' => 50,
  ],
  [
    'id' => 10,
    'old_name' => 'Sabyan',
    'name' => 'Quirino Highway Transport Stop (Caloocan)',
    'city' => 'Caloocan City',
    'location' => 'Quirino Highway, Caloocan City',
    'address' => 'Quirino Highway area, Caloocan City',
    'type' => 'Terminal',
    'capacity' => 10,
  ],
];

$taNameCol = '';
$taHasId = false;
if (table_exists($db, 'terminal_assignments')) {
  $taHasId = has_column($db, 'terminal_assignments', 'terminal_id');
  if (has_column($db, 'terminal_assignments', 'terminal_name')) $taNameCol = 'terminal_name';
  elseif (has_column($db, 'terminal_assignments', 'terminal')) $taNameCol = 'terminal';
}

$updatedTerminals = 0;
$updatedAssignments = 0;
$syncedAssignmentIds = 0;

$db->begin_transaction();
try {
  foreach ($updates as $u) {
    $sets = [];
    $types = '';
    $params = [];
    if ($hasLoc) { $sets[] = "location=?"; $types .= 's'; $params[] = $u['location']; }
    if ($hasCity) { $sets[] = "city=?"; $types .= 's'; $params[] = $u['city']; }
    if ($hasAddr) { $sets[] = "address=?"; $types .= 's'; $params[] = $u['address']; }
    $sets[] = "name=?"; $types .= 's'; $params[] = $u['name'];
    if ($hasType) { $sets[] = "type=?"; $types .= 's'; $params[] = $u['type']; }
    if ($hasCap) { $sets[] = "capacity=?"; $types .= 'i'; $params[] = (int)$u['capacity']; }
    $types .= 'i';
    $params[] = (int)$u['id'];

    $sql = "UPDATE terminals SET " . implode(', ', $sets) . " WHERE id=?";
    $stmt = $db->prepare($sql);
    if (!$stmt) throw new Exception('db_prepare_failed');
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) { $msg = $stmt->error ?: 'db_error'; $stmt->close(); throw new Exception($msg); }
    if ($stmt->affected_rows > 0) $updatedTerminals += $stmt->affected_rows;
    $stmt->close();

    if ($taNameCol !== '') {
      if ($taHasId) {
        $sqlTa = "UPDATE terminal_assignments SET $taNameCol=? WHERE $taNameCol=? AND (terminal_id IS NULL OR terminal_id=0)";
      } else {
        $sqlTa = "UPDATE terminal_assignments SET $taNameCol=? WHERE $taNameCol=?";
      }
      $stmtTa = $db->prepare($sqlTa);
      if ($stmtTa) {
        $stmtTa->bind_param('ss', $u['name'], $u['old_name']);
        if (!$stmtTa->execute()) { $msg = $stmtTa->error ?: 'db_error'; $stmtTa->close(); throw new Exception($msg); }
        if ($stmtTa->affected_rows > 0) $updatedAssignments += $stmtTa->affected_rows;
        $stmtTa->close();
      }
    }
  }

  if ($taHasId && $taNameCol !== '') {
    $sqlSync = "UPDATE terminal_assignments ta
                JOIN terminals t ON t.name=ta.$taNameCol
                SET ta.terminal_id=t.id
                WHERE (ta.terminal_id IS NULL OR ta.terminal_id=0) AND COALESCE(ta.$taNameCol,'')<>''";
    $ok = $db->query($sqlSync);
    if ($ok) $syncedAssignmentIds = (int)$db->affected_rows;
  }

  $db->commit();
  echo json_encode([
    'ok' => true,
    'updated_terminals' => $updatedTerminals,
    'updated_assignments' => $updatedAssignments,
    'synced_assignment_terminal_ids' => $syncedAssignmentIds,
  ]);
} catch (Throwable $e) {
  $db->rollback();
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_error', 'message' => $e->getMessage()]);
}

