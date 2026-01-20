<?php
if (function_exists('session_status') && session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

unset($_SESSION['user_id'], $_SESSION['email'], $_SESSION['name'], $_SESSION['role'], $_SESSION['roles'], $_SESSION['permissions']);

header('Location: index.php');
exit;
