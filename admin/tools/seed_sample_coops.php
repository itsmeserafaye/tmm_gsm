<?php
require_once __DIR__ . '/../includes/db.php';

$db = db();

function has_col(mysqli $db, string $table, string $col): bool {
  $t = $db->real_escape_string($table);
  $c = $db->real_escape_string($col);
  $res = $db->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
  return (bool)($res && $res->num_rows > 0);
}

$hasConsolidation = has_col($db, 'coops', 'consolidation_status');

$coops = [
  ['name' => 'Caloocan Transport Cooperative', 'addr' => 'Caloocan City, Metro Manila', 'chair' => 'Juan Dela Cruz', 'approval' => 'CAL-COOP-2026-001', 'status' => 'Consolidated'],
  ['name' => 'North Metro PUV Cooperative', 'addr' => 'Caloocan City, Metro Manila', 'chair' => 'Maria Santos', 'approval' => 'NMP-COOP-2026-002', 'status' => 'In Progress'],
  ['name' => 'GoServePH Transport Cooperative', 'addr' => 'Caloocan City, Metro Manila', 'chair' => 'Faye Andres', 'approval' => 'GSP-COOP-2026-003', 'status' => 'Consolidated'],
  ['name' => 'Bagong Silang Transport Cooperative', 'addr' => 'Bagong Silang, Caloocan City', 'chair' => 'Pedro Reyes', 'approval' => 'BSTC-COOP-2026-004', 'status' => 'Not Consolidated'],
  ['name' => 'Camarin Transport Cooperative', 'addr' => 'Camarin, Caloocan City', 'chair' => 'Ana Lopez', 'approval' => 'CTC-COOP-2026-005', 'status' => 'In Progress'],
  ['name' => 'South Caloocan Transport Cooperative', 'addr' => 'South Caloocan, Metro Manila', 'chair' => 'Ramon Villanueva', 'approval' => 'SCTC-COOP-2026-006', 'status' => 'Not Consolidated'],
  ['name' => 'Citywide Modern Jeepney Cooperative', 'addr' => 'Caloocan City, Metro Manila', 'chair' => 'Elena Garcia', 'approval' => 'CMJC-COOP-2026-007', 'status' => 'Consolidated'],
];

if ($hasConsolidation) {
  $stmt = $db->prepare("INSERT INTO coops (coop_name, address, chairperson_name, lgu_approval_number, consolidation_status)
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE address=VALUES(address), chairperson_name=VALUES(chairperson_name), lgu_approval_number=VALUES(lgu_approval_number), consolidation_status=VALUES(consolidation_status)");
  if (!$stmt) {
    fwrite(STDERR, "DB prepare failed: {$db->error}\n");
    exit(2);
  }
  foreach ($coops as $c) {
    $stmt->bind_param('sssss', $c['name'], $c['addr'], $c['chair'], $c['approval'], $c['status']);
    $stmt->execute();
  }
  $stmt->close();
} else {
  $stmt = $db->prepare("INSERT INTO coops (coop_name, address, chairperson_name, lgu_approval_number)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE address=VALUES(address), chairperson_name=VALUES(chairperson_name), lgu_approval_number=VALUES(lgu_approval_number)");
  if (!$stmt) {
    fwrite(STDERR, "DB prepare failed: {$db->error}\n");
    exit(2);
  }
  foreach ($coops as $c) {
    $stmt->bind_param('ssss', $c['name'], $c['addr'], $c['chair'], $c['approval']);
    $stmt->execute();
  }
  $stmt->close();
}

echo "OK: seeded coops\n";

