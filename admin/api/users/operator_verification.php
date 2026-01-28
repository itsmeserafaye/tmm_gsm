<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

function ov_send(bool $ok, array $payload = [], int $code = 200): void {
  http_response_code($code);
  echo json_encode(['ok' => $ok] + $payload);
  exit;
}

function ov_required_doc_keys(string $operatorType): array {
  if ($operatorType === 'Coop') return ['cda_registration', 'board_resolution'];
  if ($operatorType === 'Corp') return ['sec_registration', 'authority_to_operate'];
  return ['valid_id'];
}

try {
  $db = db();
  require_role(['SuperAdmin']);

  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $userId = (int)($_GET['user_id'] ?? 0);
    if ($userId > 0) {
      $stmtU = $db->prepare("SELECT id, email, full_name, contact_info, association_name, operator_type, approval_status, verification_submitted_at, approval_remarks, approved_at, approved_by, status, created_at FROM operator_portal_users WHERE id=? LIMIT 1");
      if (!$stmtU) ov_send(false, ['error' => 'db_prepare_failed'], 500);
      $stmtU->bind_param('i', $userId);
      $stmtU->execute();
      $u = $stmtU->get_result()->fetch_assoc();
      $stmtU->close();
      if (!$u) ov_send(false, ['error' => 'not_found'], 404);

      $docs = [];
      $stmtD = $db->prepare("SELECT doc_key, file_path, status, remarks, uploaded_at, reviewed_at, reviewed_by FROM operator_portal_documents WHERE user_id=? ORDER BY doc_key ASC");
      if ($stmtD) {
        $stmtD->bind_param('i', $userId);
        $stmtD->execute();
        $resD = $stmtD->get_result();
        while ($resD && ($r = $resD->fetch_assoc())) {
          $docs[] = [
            'doc_key' => (string)($r['doc_key'] ?? ''),
            'file_path' => (string)($r['file_path'] ?? ''),
            'status' => (string)($r['status'] ?? ''),
            'remarks' => $r['remarks'] ?? null,
            'uploaded_at' => (string)($r['uploaded_at'] ?? ''),
            'reviewed_at' => $r['reviewed_at'] ?? null,
            'reviewed_by' => $r['reviewed_by'] ?? null,
          ];
        }
        $stmtD->close();
      }

      ov_send(true, [
        'user' => $u,
        'documents' => $docs,
        'required_doc_keys' => ov_required_doc_keys((string)($u['operator_type'] ?? 'Individual')),
      ]);
    }

    $q = trim((string)($_GET['q'] ?? ''));
    $status = trim((string)($_GET['status'] ?? ''));
    if ($status !== '' && !in_array($status, ['Active', 'Inactive', 'Locked'], true)) $status = '';
    $approval = trim((string)($_GET['approval_status'] ?? ''));
    if ($approval !== '' && !in_array($approval, ['Pending', 'Approved', 'Rejected'], true)) $approval = '';

    $sql = "
      SELECT u.id, u.email, u.full_name, u.contact_info, u.association_name, u.operator_type,
             u.approval_status, u.verification_submitted_at, u.approval_remarks, u.status, u.created_at,
             SUM(CASE WHEN d.status='Valid' THEN 1 ELSE 0 END) AS docs_valid,
             SUM(CASE WHEN d.status='Invalid' THEN 1 ELSE 0 END) AS docs_invalid,
             SUM(CASE WHEN d.status='Pending' THEN 1 ELSE 0 END) AS docs_pending,
             COUNT(d.id) AS docs_total
      FROM operator_portal_users u
      LEFT JOIN operator_portal_documents d ON d.user_id=u.id
    ";
    $conds = [];
    $params = [];
    $types = '';

    if ($q !== '') {
      $like = '%' . $q . '%';
      $conds[] = "(u.email LIKE ? OR u.full_name LIKE ? OR u.contact_info LIKE ? OR u.association_name LIKE ?)";
      $params = array_merge($params, [$like, $like, $like, $like]);
      $types .= 'ssss';
    }
    if ($status !== '') {
      $conds[] = "u.status=?";
      $params[] = $status;
      $types .= 's';
    }
    if ($approval !== '') {
      $conds[] = "u.approval_status=?";
      $params[] = $approval;
      $types .= 's';
    }
    if ($conds) $sql .= " WHERE " . implode(" AND ", $conds);
    $sql .= " GROUP BY u.id ORDER BY u.created_at DESC LIMIT 500";

    $rows = [];
    if ($params) {
      $stmt = $db->prepare($sql);
      if (!$stmt) ov_send(false, ['error' => 'db_prepare_failed'], 500);
      $stmt->bind_param($types, ...$params);
      $stmt->execute();
      $res = $stmt->get_result();
      while ($res && ($r = $res->fetch_assoc())) $rows[] = $r;
      $stmt->close();
    } else {
      $res = $db->query($sql);
      while ($res && ($r = $res->fetch_assoc())) $rows[] = $r;
    }
    ov_send(true, ['users' => $rows]);
  }

  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ov_send(false, ['error' => 'method_not_allowed'], 405);
  }

  $raw = file_get_contents('php://input');
  $input = json_decode($raw, true);
  if (!is_array($input)) ov_send(false, ['error' => 'invalid_json'], 400);

  $action = trim((string)($input['action'] ?? ''));
  $userId = (int)($input['user_id'] ?? 0);
  if ($userId <= 0) ov_send(false, ['error' => 'invalid_user_id'], 400);

  $stmtU = $db->prepare("SELECT id, operator_type, approval_status, status FROM operator_portal_users WHERE id=? LIMIT 1");
  if (!$stmtU) ov_send(false, ['error' => 'db_prepare_failed'], 500);
  $stmtU->bind_param('i', $userId);
  $stmtU->execute();
  $user = $stmtU->get_result()->fetch_assoc();
  $stmtU->close();
  if (!$user) ov_send(false, ['error' => 'not_found'], 404);

  $adminId = (int)($_SESSION['user_id'] ?? 0);

  if ($action === 'review_document') {
    $docKey = trim((string)($input['doc_key'] ?? ''));
    $docStatus = trim((string)($input['status'] ?? ''));
    $remarks = trim((string)($input['remarks'] ?? ''));
    if ($docKey === '') ov_send(false, ['error' => 'invalid_doc_key'], 400);
    if (!in_array($docStatus, ['Pending', 'Valid', 'Invalid'], true)) ov_send(false, ['error' => 'invalid_status'], 400);

    $stmtC = $db->prepare("SELECT 1 FROM operator_portal_documents WHERE user_id=? AND doc_key=? LIMIT 1");
    if (!$stmtC) ov_send(false, ['error' => 'db_prepare_failed'], 500);
    $stmtC->bind_param('is', $userId, $docKey);
    $stmtC->execute();
    $exists = (bool)($stmtC->get_result()->fetch_row());
    $stmtC->close();
    if (!$exists) ov_send(false, ['error' => 'document_not_found'], 404);

    $now = date('Y-m-d H:i:s');
    $stmt = $db->prepare("UPDATE operator_portal_documents SET status=?, remarks=?, reviewed_at=?, reviewed_by=? WHERE user_id=? AND doc_key=?");
    if (!$stmt) ov_send(false, ['error' => 'db_prepare_failed'], 500);
    $stmt->bind_param('sssiss', $docStatus, $remarks, $now, $adminId, $userId, $docKey);
    $stmt->execute();
    $stmt->close();

    if ($docStatus === 'Invalid') {
      $title = 'Document requires correction';
      $msg = ($remarks !== '') ? ('Your document (' . $docKey . ') was marked invalid: ' . $remarks) : ('Your document (' . $docKey . ') was marked invalid.');
      $type = 'warning';
      $stmtN = $db->prepare("INSERT INTO operator_portal_notifications(user_id, title, message, type) VALUES(?, ?, ?, ?)");
      if ($stmtN) {
        $stmtN->bind_param('isss', $userId, $title, $msg, $type);
        $stmtN->execute();
        $stmtN->close();
      }
    }

    ov_send(true, ['message' => 'Document updated']);
  }

  if ($action === 'set_approval') {
    $approval = trim((string)($input['approval_status'] ?? ''));
    $remarks = trim((string)($input['remarks'] ?? ''));
    if (!in_array($approval, ['Pending', 'Approved', 'Rejected'], true)) ov_send(false, ['error' => 'invalid_approval_status'], 400);

    $operatorType = (string)($user['operator_type'] ?? 'Individual');
    if ($approval === 'Approved') {
      $required = ov_required_doc_keys($operatorType);
      $in = implode(',', array_fill(0, count($required), '?'));
      $types = 'i' . str_repeat('s', count($required));
      $params = array_merge([$userId], $required);
      $sql = "SELECT doc_key, status FROM operator_portal_documents WHERE user_id=? AND doc_key IN ($in)";
      $stmtD = $db->prepare($sql);
      if (!$stmtD) ov_send(false, ['error' => 'db_prepare_failed'], 500);
      $stmtD->bind_param($types, ...$params);
      $stmtD->execute();
      $res = $stmtD->get_result();
      $statusMap = [];
      while ($res && ($r = $res->fetch_assoc())) {
        $statusMap[(string)($r['doc_key'] ?? '')] = (string)($r['status'] ?? '');
      }
      $stmtD->close();
      foreach ($required as $k) {
        if (($statusMap[$k] ?? '') !== 'Valid') {
          ov_send(false, ['error' => 'required_documents_not_valid'], 400);
        }
      }
    }

    $now = date('Y-m-d H:i:s');
    if ($approval === 'Approved') {
      $stmt = $db->prepare("UPDATE operator_portal_users SET approval_status='Approved', approval_remarks=?, approved_at=?, approved_by=? WHERE id=?");
      if (!$stmt) ov_send(false, ['error' => 'db_prepare_failed'], 500);
      $stmt->bind_param('ssii', $remarks, $now, $adminId, $userId);
    } else if ($approval === 'Rejected') {
      $stmt = $db->prepare("UPDATE operator_portal_users SET approval_status='Rejected', approval_remarks=?, approved_at=NULL, approved_by=? WHERE id=?");
      if (!$stmt) ov_send(false, ['error' => 'db_prepare_failed'], 500);
      $stmt->bind_param('sii', $remarks, $adminId, $userId);
    } else {
      $stmt = $db->prepare("UPDATE operator_portal_users SET approval_status='Pending', approval_remarks=? WHERE id=?");
      if (!$stmt) ov_send(false, ['error' => 'db_prepare_failed'], 500);
      $stmt->bind_param('si', $remarks, $userId);
    }
    $stmt->execute();
    $stmt->close();

    $title = $approval === 'Approved' ? 'Operator account approved' : ($approval === 'Rejected' ? 'Operator verification rejected' : 'Operator verification updated');
    $msg = $approval === 'Approved'
      ? 'Your operator account is approved. You can now access full services.'
      : ($approval === 'Rejected'
        ? (($remarks !== '') ? ('Your verification was rejected: ' . $remarks) : 'Your verification was rejected. Please review remarks and resubmit.')
        : (($remarks !== '') ? ('Your verification status was updated: ' . $remarks) : 'Your verification status was updated.'));
    $type = $approval === 'Approved' ? 'success' : ($approval === 'Rejected' ? 'error' : 'info');
    $stmtN = $db->prepare("INSERT INTO operator_portal_notifications(user_id, title, message, type) VALUES(?, ?, ?, ?)");
    if ($stmtN) {
      $stmtN->bind_param('isss', $userId, $title, $msg, $type);
      $stmtN->execute();
      $stmtN->close();
    }

    ov_send(true, ['message' => 'Approval status updated']);
  }

  ov_send(false, ['error' => 'invalid_action'], 400);
} catch (Throwable $e) {
  ov_send(false, ['error' => $e->getMessage()], 500);
}

