<?php
// admin/version_check.php
header('Content-Type: text/plain');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$file = __DIR__ . '/pages/dashboard.php';
echo "Checking file: $file\n";

if (file_exists($file)) {
    echo "File exists.\n";
    echo "MD5 Hash: " . md5_file($file) . "\n";
    echo "Last Modified: " . date("F d Y H:i:s.", filemtime($file)) . "\n";
    
    // Check for specific strings
    $content = file_get_contents($file);
    echo "Contains 'v3.1': " . (strpos($content, 'v3.1') !== false ? 'YES' : 'NO') . "\n";
    echo "Contains 'md:grid-cols-3': " . (strpos($content, 'md:grid-cols-3') !== false ? 'YES' : 'NO') . "\n";
} else {
    echo "File NOT found.\n";
}

echo "\nPHP Version: " . phpversion() . "\n";
echo "Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "\n";
?>