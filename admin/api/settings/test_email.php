<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../../includes/mailer.php';

header('Content-Type: application/json');
require_permission('settings.manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}

$db = db();
$to = trim((string)($_SESSION['email'] ?? ''));
if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'invalid_recipient']);
  exit;
}

try {
  $mail = tmm_mailer($db);
  $mail->clearAllRecipients();
  $mail->Subject = 'Test Email (TMM)';
  $mail->Body = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#111">'
    . '<p>This is a test email from TMM Security Settings.</p>'
    . '<p>If you received this message, OTP/MFA email delivery should work.</p>'
    . '</div>';
  $mail->AltBody = "This is a test email from TMM Security Settings.\nIf you received this message, OTP/MFA email delivery should work.\n";
  $mail->addAddress($to);
  $mail->send();
  echo json_encode(['ok' => true]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'send_failed']);
}
?>

