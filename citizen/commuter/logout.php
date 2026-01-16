<?php
if (function_exists('session_status') && session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

unset($_SESSION['user_id'], $_SESSION['email'], $_SESSION['name'], $_SESSION['role'], $_SESSION['roles'], $_SESSION['permissions']);

$baseUrl = str_replace('\\', '/', (string)dirname(dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/citizen/commuter/logout.php'))));
$baseUrl = $baseUrl === '/' ? '' : rtrim($baseUrl, '/');
header('Location: ' . $baseUrl . '/index.php');
exit;

