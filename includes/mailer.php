<?php
require_once __DIR__ . '/env.php';
tmm_load_env(__DIR__ . '/../.env');
if (is_file(__DIR__ . '/../vendor/autoload.php')) {
  require_once __DIR__ . '/../vendor/autoload.php';
}

use PHPMailer\PHPMailer\PHPMailer;

function app_setting(mysqli $db, string $key, string $default = ''): string {
  $stmt = $db->prepare("SELECT setting_value FROM app_settings WHERE setting_key=? LIMIT 1");
  if (!$stmt) return $default;
  $stmt->bind_param('s', $key);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  $val = $row ? (string)($row['setting_value'] ?? '') : '';
  $val = trim($val);
  return $val !== '' ? $val : $default;
}

function tmm_mailer(mysqli $db): PHPMailer {
  $mail = new PHPMailer(true);

  $host = trim((string)getenv('TMM_SMTP_HOST'));
  $user = trim((string)getenv('TMM_SMTP_USER'));
  $pass = (string)getenv('TMM_SMTP_PASS');
  $port = (int)(getenv('TMM_SMTP_PORT') ?: 587);
  $secure = trim((string)getenv('TMM_SMTP_SECURE'));

  if ($host !== '') {
    $mail->isSMTP();
    $mail->Host = $host;
    $mail->Port = $port > 0 ? $port : 587;
    $mail->SMTPAuth = ($user !== '');
    $mail->Timeout = 12;
    if ($user !== '') {
      $mail->Username = $user;
      $mail->Password = $pass;
    }
    if ($secure !== '') {
      $mail->SMTPSecure = $secure;
    } else {
      $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    }
  } else {
    $mail->isMail();
  }

  $envFromEmail = trim((string)getenv('SYSTEM_EMAIL'));
  $envFromName = trim((string)getenv('SYSTEM_NAME'));
  $fromEmail = $envFromEmail !== '' ? $envFromEmail : app_setting($db, 'system_email', 'no-reply@localhost');
  $fromName = $envFromName !== '' ? $envFromName : app_setting($db, 'system_name', 'GoServePH');
  $mail->setFrom($fromEmail, $fromName);
  $mail->isHTML(true);
  $mail->CharSet = 'UTF-8';

  return $mail;
}
