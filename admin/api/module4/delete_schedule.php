<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
header('Content-Type: application/json');
require_permission('module4.inspections.manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}

$scheduleId = (int)($_POST['schedule_id'] ?? 0);
$force = (int)($_POST['force'] ?? 0);

if ($scheduleId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_schedule_id']);
  exit;
}

$tableExists = function (string $table) use ($db): bool {
  $t = $db->real_escape_string($table);
  $r = $db->query("SHOW TABLES LIKE '$t'");
  return $r && $r->num_rows > 0;
};

$stmt = $db->prepare("SELECT status FROM inspection_schedules WHERE schedule_id=? LIMIT 1");
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmt->bind_param('i', $scheduleId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'error' => 'schedule_not_found']);
  exit;
}

$status = (string)($row['status'] ?? '');
if ($status === 'Completed' && $force !== 1) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'cannot_delete_completed']);
  exit;
}

$db->begin_transaction();
try {
  if ($tableExists('inspection_photos') && $tableExists('inspection_results')) {
    $stmtD = $db->prepare("DELETE p FROM inspection_photos p JOIN inspection_results r ON r.result_id=p.result_id WHERE r.schedule_id=?");
    if ($stmtD) {
      $stmtD->bind_param('i', $scheduleId);
      $stmtD->execute();
      $stmtD->close();
    }
  }

  if ($tableExists('inspection_checklist_items') && $tableExists('inspection_results')) {
    $stmtD = $db->prepare("DELETE c FROM inspection_checklist_items c JOIN inspection_results r ON r.result_id=c.result_id WHERE r.schedule_id=?");
    if ($stmtD) {
      $stmtD->bind_param('i', $scheduleId);
      $stmtD->execute();
      $stmtD->close();
    }
  }

  if ($tableExists('inspection_certificates')) {
    $stmtD = $db->prepare("DELETE FROM inspection_certificates WHERE schedule_id=?");
    if ($stmtD) {
      $stmtD->bind_param('i', $scheduleId);
      $stmtD->execute();
      $stmtD->close();
    }
  }

  if ($tableExists('inspections')) {
    $stmtD = $db->prepare("DELETE FROM inspections WHERE schedule_id=?");
    if ($stmtD) {
      $stmtD->bind_param('i', $scheduleId);
      $stmtD->execute();
      $stmtD->close();
    }
  }

  if ($tableExists('inspection_results')) {
    $stmtD = $db->prepare("DELETE FROM inspection_results WHERE schedule_id=?");
    if ($stmtD) {
      $stmtD->bind_param('i', $scheduleId);
      $stmtD->execute();
      $stmtD->close();
    }
  }

  $stmtD = $db->prepare("DELETE FROM inspection_schedules WHERE schedule_id=?");
  if (!$stmtD) {
    throw new Exception('db_prepare_failed');
  }
  $stmtD->bind_param('i', $scheduleId);
  $stmtD->execute();
  $affected = (int)$stmtD->affected_rows;
  $stmtD->close();

  if ($affected <= 0) {
    throw new Exception('delete_failed');
  }

  $db->commit();
  echo json_encode(['ok' => true]);
} catch (Throwable $e) {
  $db->rollback();
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $e->getMessage() ?: 'delete_failed']);
}

