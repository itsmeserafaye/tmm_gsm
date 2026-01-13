<?php
if (function_exists('session_status') && session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$_SESSION = [];
if (ini_get('session.use_cookies')) {
  $p = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool)$p['secure'], (bool)$p['httponly']);
}
@session_destroy();
$baseUrl = str_replace('\\', '/', (string)dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/logout.php')));
$baseUrl = $baseUrl === '/' ? '' : rtrim($baseUrl, '/');
header('Location: ' . $baseUrl . '/index.php');
exit;

