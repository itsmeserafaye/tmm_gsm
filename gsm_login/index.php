<?php
require_once __DIR__ . '/../admin/includes/db.php';
require_once __DIR__ . '/../includes/recaptcha.php';
$db = db();
$recaptchaCfg = recaptcha_config($db);
$recaptchaSiteKey = (string) ($recaptchaCfg['site_key'] ?? '');
$html = file_get_contents(__DIR__ . '/index.html');
if (!is_string($html) || $html === '') {
  http_response_code(500);
  echo 'Login template not found.';
  exit;
}
$mode = $_GET['mode'] ?? 'commuter';

$msTitles = [
  'staff' => 'Staff Portal Access',
  'operator' => 'Operator Dashboard',
  'commuter' => 'Public Portal'
];
$msDescs = [
  'staff' => 'Secure login for authorized personnel and administrators.',
  'operator' => 'Manage your fleet, franchises, and vehicle records.',
  'commuter' => 'Access public transport information and services.'
];

$title = $msTitles[$mode] ?? 'Login to your portal';
$desc = $msDescs[$mode] ?? 'Use your account credentials to access services.';

// Replace the specific text content in the HTML
$html = str_replace(
  [
    'Login to your portal',
    'Use your account credentials to access Staff/Commuter or Operator services.'
  ],
  [
    htmlspecialchars($title),
    htmlspecialchars($desc)
  ],
  $html
);

if ($recaptchaSiteKey === '') {
  $html = str_replace('<div class="g-recaptcha" data-sitekey="YOUR_RECAPTCHA_SITE_KEY"></div>', '', $html);
  $html = str_replace('<div id="opRecaptcha" data-sitekey="YOUR_RECAPTCHA_SITE_KEY"></div>', '', $html);
} else {
  $html = str_replace('YOUR_RECAPTCHA_SITE_KEY', $recaptchaSiteKey, $html);
}
echo $html;
