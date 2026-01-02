<?php
function db() {
  static $conn;
  if ($conn) return $conn;
  $host = '127.0.0.1';
  $user = 'root';
  $pass = '';
  $name = 'tmm';
  $conn = @new mysqli($host, $user, $pass, $name);
  if ($conn->connect_error) { 
      // Fallback: try connecting without DB name to create it if missing, 
      // though user said it's uploaded, this is just a safety net for connection.
      // Actually, if user said it's uploaded, we assume DB exists.
      die('DB connect error: ' . $conn->connect_error); 
  }
  $conn->set_charset('utf8mb4');
  return $conn;
}
?> 
