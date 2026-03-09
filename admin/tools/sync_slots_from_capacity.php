<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_permission('module5.manage_terminal');

$db = db();
$res = $db->query("SELECT id, name, capacity FROM terminals ORDER BY id ASC");
$rows = [];
if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;

$summary = [
  'terminals_scanned' => 0,
  'slots_added' => 0,
  'skipped_no_capacity' => 0,
];

foreach ($rows as $t) {
  $tid = (int)($t['id'] ?? 0);
  $cap = (int)($t['capacity'] ?? 0);
  if ($tid <= 0) continue;
  $summary['terminals_scanned']++;
  if ($cap <= 0) { $summary['skipped_no_capacity']++; continue; }

  $existing = [];
  $stmtSlots = $db->prepare("SELECT slot_no FROM parking_slots WHERE terminal_id=?");
  if ($stmtSlots) {
    $stmtSlots->bind_param('i', $tid);
    $stmtSlots->execute();
    $rs = $stmtSlots->get_result();
    while ($rs && ($r = $rs->fetch_assoc())) {
      $sn = trim((string)($r['slot_no'] ?? ''));
      if ($sn !== '') $existing[$sn] = true;
    }
    $stmtSlots->close();
  }

  $ins = $db->prepare("INSERT IGNORE INTO parking_slots (terminal_id, slot_no, status) VALUES (?, ?, 'Free')");
  if ($ins) {
    for ($i = 1; $i <= $cap; $i++) {
      $sn = (string)$i;
      if (isset($existing[$sn])) continue;
      $ins->bind_param('is', $tid, $sn);
      if ($ins->execute()) $summary['slots_added']++;
    }
    $ins->close();
  }
}

header('Content-Type: application/json');
echo json_encode(['ok' => true, 'summary' => $summary]);
