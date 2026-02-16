<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../../includes/mailer.php';
require_once __DIR__ . '/../../../includes/edit_permission.php';

header('Content-Type: application/json');

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
  }
  require_role(['SuperAdmin']);
  $db = db();

  $targetId = (int)($_POST['target_user_id'] ?? 0);
  if ($targetId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'missing_target']);
    exit;
  }
  $editorId = (int)($_SESSION['user_id'] ?? 0);
  if ($editorId <= 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
  }

  $resU = $db->prepare("SELECT email, first_name, last_name FROM rbac_users WHERE id=? LIMIT 1");
  $resU->bind_param('i', $targetId);
  $resU->execute();
  $rowU = $resU->get_result()->fetch_assoc();
  $resU->close();
  if (!$rowU) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'target_not_found']);
    exit;
  }
  $emailTo = strtolower(trim((string)$rowU['email'] ?? ''));
  if ($emailTo === '' || !filter_var($emailTo, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_email']);
    exit;
  }

  $check = ep_can_request($db, $editorId, $targetId, 3, 5);
  if (!($check['ok'] ?? false)) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => $check['error'] ?? 'rate_limited', 'wait_seconds' => (int)($check['wait_seconds'] ?? 0)]);
    exit;
  }

  $req = ep_request($db, $editorId, $targetId, 24);
  if (!($req['ok'] ?? false)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $req['error'] ?? 'request_failed']);
    exit;
  }

  $requestId = (int)$req['request_id'];
  $token = (string)$req['token'];
  $expiresAt = (string)$req['expires_at'];

  $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
  $rootUrl = '';
  $pos = strpos($scriptName, '/admin/');
  if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
  if ($rootUrl === '/') $rootUrl = '';
  $origin = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
  $link = $origin . $rootUrl . '/admin/api/settings/validate_edit_permission.php?r=' . urlencode((string)$requestId) . '&t=' . urlencode($token);

  $editorName = (string)($_SESSION['name'] ?? 'An administrator');
  $targetName = trim((string)($rowU['first_name'] ?? '') . ' ' . (string)($rowU['last_name'] ?? ''));
  if ($targetName === '') $targetName = $emailTo;
  $expiresTs = strtotime($expiresAt);
  $minutesLeft = $expiresTs !== false ? max(1, (int)ceil(($expiresTs - time()) / 60)) : (24 * 60);

  $subject = 'Authorize Account Edit Request';
  $html = '<div style="font-family:Inter,Arial,sans-serif;max-width:640px;margin:0 auto;padding:24px;background:#f8fafc;color:#0f172a">'
    . '<div style="background:#4f46e5;color:#fff;padding:16px 20px;border-radius:12px 12px 0 0">'
    . '<h2 style="margin:0;font-size:18px">Transport & Mobility Management</h2>'
    . '<p style="margin:4px 0 0;font-size:12px;opacity:.9">Permission Request</p>'
    . '</div>'
    . '<div style="background:#fff;border:1px solid #e2e8f0;border-top:none;padding:20px;border-radius:0 0 12px 12px">'
    . '<p>Hi ' . htmlspecialchars($targetName) . ',</p>'
    . '<p><strong>' . htmlspecialchars($editorName) . '</strong> is requesting permission to edit your account details in TMM.</p>'
    . '<p>To approve this request, click the button below. This authorization link is valid for 24 hours.</p>'
    . '<p style="text-align:center;margin:24px 0">'
    . '<a href="' . htmlspecialchars($link) . '" style="display:inline-block;background:#4f46e5;color:#fff;text-decoration:none;padding:12px 18px;border-radius:10px;font-weight:700">Authorize Edit</a>'
    . '</p>'
    . '<p style="font-size:12px;color:#475569">If you did not expect this email, you can ignore it. This link expires in approximately ' . $minutesLeft . ' minutes.</p>'
    . '</div>'
    . '<p style="margin-top:20px;font-size:12px;color:#64748b">© ' . date('Y') . ' TMM</p>'
    . '</div>';
  $text = "Authorize account edit request.\n\nApprove: $link\n\nThis link is valid for 24 hours.\n";

  $sent = false;
  $errMsg = '';
  try {
    $mail = tmm_mailer($db);
    $mail->clearAllRecipients();
    $mail->Subject = $subject;
    $mail->Body = $html;
    $mail->AltBody = $text;
    $mail->addAddress($emailTo);
    for ($i = 0; $i < 3; $i++) {
      try {
        $mail->send();
        $sent = true;
        break;
      } catch (Throwable $e) {
        $errMsg = $e->getMessage();
        usleep(200000);
      }
    }
  } catch (Throwable $e) {
    $errMsg = $e->getMessage();
  }
  if (!$sent) {
    ep_log($db, $requestId, 'send_failed', $errMsg);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'send_failed']);
    exit;
  }
  ep_log($db, $requestId, 'email_sent');

  echo json_encode([
    'ok' => true,
    'request_id' => $requestId,
    'expires_at' => $expiresAt,
    'email' => $emailTo,
    'resend_after_seconds' => 300
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'server_error']);
}
?>
