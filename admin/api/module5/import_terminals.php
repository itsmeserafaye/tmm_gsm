<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/import.php';
$db = db();
header('Content-Type: application/json');
require_permission('module5.manage_terminal');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}

[$tmp, $err] = tmm_import_get_uploaded_csv('file');
if ($err) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => $err]);
  exit;
}

[, $rows, $err2] = tmm_import_read_csv($tmp);
if ($err2) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => $err2]);
  exit;
}

$stmtFind = $db->prepare("SELECT id FROM terminals WHERE id=? LIMIT 1");
$stmtFindByName = $db->prepare("SELECT id FROM terminals WHERE name=? LIMIT 1");
$stmtIns = $db->prepare("INSERT INTO terminals (name, location, address, capacity, type, city, category) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmtUpd = $db->prepare("UPDATE terminals SET name=?, location=?, address=?, capacity=?, type=?, city=?, category=? WHERE id=?");
if (!$stmtFind || !$stmtFindByName || !$stmtIns || !$stmtUpd) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}

$inserted = 0;
$updated = 0;
$skipped = 0;
$errors = [];

$db->begin_transaction();
try {
  foreach ($rows as $idx => $r) {
    $idRaw = trim((string)($r['terminal_id'] ?? ''));
    $name = trim((string)($r['name'] ?? ''));
    if ($name === '' && $idRaw === '') { $skipped++; continue; }

    $location = trim((string)($r['location'] ?? ''));
    $address = trim((string)($r['address'] ?? ''));
    $capacityRaw = trim((string)($r['capacity'] ?? '0'));
    $capacity = is_numeric($capacityRaw) ? (int)$capacityRaw : 0;
    $type = trim((string)($r['type'] ?? 'Terminal'));
    if ($type === '') $type = 'Terminal';
    $city = trim((string)($r['city'] ?? ''));
    $category = trim((string)($r['category'] ?? ''));

    $targetId = 0;
    if ($idRaw !== '' && ctype_digit($idRaw)) {
      $id = (int)$idRaw;
      $stmtFind->bind_param('i', $id);
      $stmtFind->execute();
      $found = $stmtFind->get_result()->fetch_assoc();
      $targetId = (int)($found['id'] ?? 0);
    }
    if ($targetId <= 0 && $name !== '') {
      $stmtFindByName->bind_param('s', $name);
      $stmtFindByName->execute();
      $found = $stmtFindByName->get_result()->fetch_assoc();
      $targetId = (int)($found['id'] ?? 0);
    }

    if ($targetId > 0) {
      $stmtUpd->bind_param('sssisssi', $name, $location, $address, $capacity, $type, $city, $category, $targetId);
      $ok = $stmtUpd->execute();
      if (!$ok) {
        $errors[] = ['row' => $idx + 2, 'error' => 'update_failed'];
        $skipped++;
        continue;
      }
      $updated++;
    } else {
      if ($name === '') { $skipped++; continue; }
      $stmtIns->bind_param('sssisss', $name, $location, $address, $capacity, $type, $city, $category);
      $ok = $stmtIns->execute();
      if (!$ok) {
        $errors[] = ['row' => $idx + 2, 'error' => 'insert_failed'];
        $skipped++;
        continue;
      }
      $inserted++;
    }
  }
  $db->commit();
} catch (Throwable $e) {
  $db->rollback();
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'import_failed']);
  exit;
}

echo json_encode([
  'ok' => true,
  'inserted' => $inserted,
  'updated' => $updated,
  'skipped' => $skipped,
  'errors' => $errors
]);

