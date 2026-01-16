<?php
function commuter_portal_require_login(string $redirectTo): void {
  if (function_exists('session_status') && session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
  if (empty($_SESSION['user_id']) || (string)($_SESSION['role'] ?? '') !== 'Commuter') {
    header('Location: ' . $redirectTo);
    exit;
  }
}

