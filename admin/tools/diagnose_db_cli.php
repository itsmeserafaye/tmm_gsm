<?php
require_once __DIR__ . '/../includes/db.php';

echo "mysqli: " . (class_exists('mysqli') ? "yes" : "no") . "\n";
echo "host env: " . (string)getenv('TMM_DB_HOST') . "\n";
echo "user env: " . (string)getenv('TMM_DB_USER') . "\n";
echo "name env: " . (string)getenv('TMM_DB_NAME') . "\n";
echo "pass env: " . ((string)getenv('TMM_DB_PASS') !== '' ? "(set)" : "(empty)") . "\n\n";

$host = trim((string)getenv('TMM_DB_HOST'));
$user = trim((string)getenv('TMM_DB_USER'));
$pass = (string)getenv('TMM_DB_PASS');
$name = trim((string)getenv('TMM_DB_NAME'));
if ($host === '') $host = 'localhost';
if ($user === '') $user = 'root';

$tests = [
  [$host, $user, $pass, $name],
  ['127.0.0.1', $user, $pass, $name],
  [$host, 'root', '', $name],
  ['127.0.0.1', 'root', '', $name],
  [$host, 'root', '', 'tmm'],
  ['127.0.0.1', 'root', '', 'tmm'],
  [$host, 'root', '', null],
  ['127.0.0.1', 'root', '', null],
];

foreach ($tests as $t) {
  [$h, $u, $p, $db] = $t;
  $labelDb = $db === null ? '(no-db)' : $db;
  foreach ([3306, 3307, 3308] as $port) {
    echo "Try $h:$port / $u / $labelDb ... ";
    try {
      $conn = $db === null ? @new mysqli($h, $u, $p, '', $port) : @new mysqli($h, $u, $p, $db, $port);
      if ($conn->connect_error) {
        echo "FAIL: " . $conn->connect_error . "\n";
        continue;
      }
      echo "OK\n";
      $conn->close();
      break;
    } catch (Throwable $e) {
      echo "EX: " . $e->getMessage() . "\n";
    }
  }
}

foreach (['MySQL', 'MariaDB', 'mysql'] as $sock) {
  echo "Try named-pipe .$sock / root / (no-db) ... ";
  try {
    $conn = @new mysqli('.', 'root', '', '', 0, $sock);
    if ($conn->connect_error) {
      echo "FAIL: " . $conn->connect_error . "\n";
    } else {
      echo "OK\n";
      $conn->close();
    }
  } catch (Throwable $e) {
    echo "EX: " . $e->getMessage() . "\n";
  }
}
