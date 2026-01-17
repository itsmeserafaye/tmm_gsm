<?php
require_once __DIR__ . '/../admin/includes/db.php';
require_once __DIR__ . '/../includes/recaptcha.php';
$db = db();
$recaptchaCfg = recaptcha_config($db);
$recaptchaSiteKey = (string)($recaptchaCfg['site_key'] ?? '');
$html = file_get_contents(__DIR__ . '/index.html');
if (!is_string($html) || $html === '') {
  http_response_code(500);
  echo 'Login template not found.';
  exit;
}
if ($recaptchaSiteKey === '') {
  $html = str_replace('<div class="g-recaptcha" data-sitekey="YOUR_RECAPTCHA_SITE_KEY"></div>', '', $html);
} else {
  $html = str_replace('YOUR_RECAPTCHA_SITE_KEY', $recaptchaSiteKey, $html);
}
echo $html;

