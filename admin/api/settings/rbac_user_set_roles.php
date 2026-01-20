<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

function json_out($code, $payload) {
  http_response_code($code);
  echo json_encode($payload);
  exit;
}

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(405, ['ok' => false, 'error' => 'method_not_allowed']);
  $db = db();
  require_role(['SuperAdmin']);

  $id = (int)($_POST['id'] ?? 0);
  if ($id <= 0) json_out(400, ['ok' => false, 'error' => 'missing_id']);

  $roleIds = [];
  
  // Debug logging
  $debugLog = __DIR__ . '/../debug_log.txt';
  file_put_contents($debugLog, date('Y-m-d H:i:s') . " - SET ROLES INPUT: " . print_r($_POST, true) . "\n", FILE_APPEND);

  if (isset($_POST['role_ids'])) {
    if (is_array($_POST['role_ids'])) {
      foreach ($_POST['role_ids'] as $rid) {
        $rid = (int)$rid;
        if ($rid > 0) $roleIds[] = $rid;
      }
    } else {
      $rid = (int)$_POST['role_ids'];
      if ($rid > 0) $roleIds[] = $rid;
    }
  }
  
  // Fallback: Check if roles are passed as JSON body (common with some fetch implementations)
  if (empty($roleIds)) {
      $input = json_decode(file_get_contents('php://input'), true);
      if (isset($input['role_ids'])) {
           if (is_array($input['role_ids'])) {
              foreach ($input['role_ids'] as $rid) {
                $rid = (int)$rid;
                if ($rid > 0) $roleIds[] = $rid;
              }
           } else {
               $rid = (int)$input['role_ids'];
               if ($rid > 0) $roleIds[] = $rid;
           }
      }
      if (isset($input['id']) && $id === 0) {
          $id = (int)$input['id'];
      }
  }

  $roleIds = array_values(array_unique($roleIds));
  if (!$roleIds) json_out(400, ['ok' => false, 'error' => 'no_roles', 'received' => $_POST]);

  $stmtD = $db->prepare("DELETE FROM rbac_user_roles WHERE user_id=?");
  if ($stmtD) {
    $stmtD->bind_param('i', $id);
    $stmtD->execute();
    $stmtD->close();
  }

  foreach ($roleIds as $rid) {
    // Use INSERT (not IGNORE) to catch errors
    $st = $db->prepare("INSERT INTO rbac_user_roles(user_id, role_id) VALUES(?,?)");
    if ($st) {
      $st->bind_param('ii', $id, $rid);
      if (!$st->execute()) {
          file_put_contents($debugLog, date('Y-m-d H:i:s') . " - SET ROLES INSERT ERROR: " . $st->error . "\n", FILE_APPEND);
      }
      $st->close();
    } else {
        file_put_contents($debugLog, date('Y-m-d H:i:s') . " - SET ROLES PREPARE ERROR: " . $db->error . "\n", FILE_APPEND);
    }
  }

  json_out(200, ['ok' => true]);
} catch (Exception $e) {
  if (defined('TMM_TEST')) throw $e;
  json_out(400, ['ok' => false, 'error' => $e->getMessage()]);
}
