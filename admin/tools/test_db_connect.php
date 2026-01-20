<?php
require_once __DIR__ . '/../includes/db.php';
// Modify db() slightly or just call it and catch output? 
// db() dies on error, so we can't catch it easily unless we modify db.php.
// But we can inspect why it fails by reproducing the logic.

require_once __DIR__ . '/../../includes/env.php';
tmm_load_env(__DIR__ . '/../../.env');

$host = getenv('TMM_DB_HOST') ?: 'localhost';
$user = getenv('TMM_DB_USER') ?: 'root';
$pass = getenv('TMM_DB_PASS') ?: '';
$name = getenv('TMM_DB_NAME') ?: 'tmm';

echo "Env Host: $host\n";
echo "Env User: $user\n";
echo "Env Name: $name\n";

$candidates = [
    [$host, $user, $pass, $name],
    ['localhost', 'root', '', 'tmm'],
    ['localhost', 'root', '', 'tmm_tmm']
];

foreach ($candidates as $c) {
    echo "Trying {$c[0]} / {$c[1]} / {$c[3]} ... ";
    try {
        $conn = new mysqli($c[0], $c[1], $c[2], $c[3]);
        if ($conn->connect_error) {
            echo "Failed: " . $conn->connect_error . "\n";
        } else {
            echo "SUCCESS!\n";
        }
    } catch (Throwable $e) {
        echo "Exception: " . $e->getMessage() . "\n";
    }
}
