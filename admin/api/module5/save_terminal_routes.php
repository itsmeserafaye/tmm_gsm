<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');
require_permission('module5.manage_terminal');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}

$terminalId = (int)($_POST['terminal_id'] ?? 0);
$routesRaw = (string)($_POST['routes'] ?? '');
if ($terminalId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_terminal_id']);
  exit;
}

$routes = json_decode($routesRaw, true);
if (!is_array($routes)) $routes = [];
$routeRefs = [];
foreach ($routes as $r) {
  $s = trim((string)$r);
  if ($s !== '') $routeRefs[] = $s;
}
$routeRefs = array_values(array_unique($routeRefs));
if (count($routeRefs) > 2000) $routeRefs = array_slice($routeRefs, 0, 2000);

$stmtT = $db->prepare("SELECT id FROM terminals WHERE id=? LIMIT 1");
if (!$stmtT) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmtT->bind_param('i', $terminalId);
$stmtT->execute();
$term = $stmtT->get_result()->fetch_assoc();
$stmtT->close();
if (!$term) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'error' => 'terminal_not_found']);
  exit;
}

$hasRoutes = $db->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='routes' LIMIT 1");
$hasTermRoutes = $db->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='terminal_routes' LIMIT 1");
if (!$hasRoutes || !$hasRoutes->fetch_row() || !$hasTermRoutes || !$hasTermRoutes->fetch_row()) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'tables_missing']);
  exit;
}

$valid = [];
if ($routeRefs) {
  $in = implode(',', array_map(function ($s) use ($db) {
    return "'" . $db->real_escape_string($s) . "'";
  }, $routeRefs));
  $res = $db->query("SELECT DISTINCT COALESCE(NULLIF(route_code,''), route_id) AS ref FROM routes WHERE route_id IN ($in) OR route_code IN ($in)");
  if ($res) {
    while ($row = $res->fetch_assoc()) {
      $ref = trim((string)($row['ref'] ?? ''));
      if ($ref !== '') $valid[$ref] = true;
    }
  }
}

$toInsert = [];
foreach ($routeRefs as $r) {
  if (isset($valid[$r])) $toInsert[] = $r;
}

$db->begin_transaction();
try {
  $stmtDel = $db->prepare("DELETE FROM terminal_routes WHERE terminal_id=?");
  if (!$stmtDel) throw new Exception('db_prepare_failed');
  $stmtDel->bind_param('i', $terminalId);
  if (!$stmtDel->execute()) { $err = $stmtDel->error ?: 'execute_failed'; $stmtDel->close(); throw new Exception($err); }
  $stmtDel->close();

  $added = 0;
  if ($toInsert) {
    $stmtIns = $db->prepare("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) VALUES (?, ?)");
    if (!$stmtIns) throw new Exception('db_prepare_failed');
    foreach ($toInsert as $ref) {
      $stmtIns->bind_param('is', $terminalId, $ref);
      if (!$stmtIns->execute()) { $err = $stmtIns->error ?: 'execute_failed'; $stmtIns->close(); throw new Exception($err); }
      if ($stmtIns->affected_rows > 0) $added++;
    }
    $stmtIns->close();
  }

  $db->commit();
  echo json_encode(['ok' => true, 'added' => $added, 'total' => count($toInsert)]);
} catch (Throwable $e) {
  $db->rollback();
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_error', 'message' => $e->getMessage()]);
}
