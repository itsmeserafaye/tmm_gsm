<?php
if (function_exists('session_status') && session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once __DIR__ . '/../../includes/operator_portal.php';
operator_portal_clear_session();
$baseUrl = str_replace('\\', '/', (string)dirname(dirname(dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/citizen/operator/logout.php')))));
$baseUrl = $baseUrl === '/' ? '' : rtrim($baseUrl, '/');
header('Location: ' . $baseUrl . '/index.php');
exit;
