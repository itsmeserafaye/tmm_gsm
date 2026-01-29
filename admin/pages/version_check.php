<?php
// Disable all caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

echo "<h1>VERSION DIAGNOSTIC TOOL</h1>";
echo "<p>Current Server Time: " . date('Y-m-d H:i:s') . "</p>";

// Note: This file is in admin/pages/, so dashboard is in the same directory
$file = __DIR__ . '/dashboard.php';

if (!file_exists($file)) {
    echo "<h2 style='color:red'>ERROR: admin/pages/dashboard.php DOES NOT EXIST on this server.</h2>";
    exit;
}

$modTime = filemtime($file);
echo "<p>Dashboard File Last Modified: " . date('Y-m-d H:i:s', $modTime) . "</p>";

$content = file_get_contents($file);

// Check for specific version tag
if (strpos($content, 'v3.2') !== false) {
    echo "<h2 style='color:green'>SUCCESS: v3.2 Code Found!</h2>";
    echo "<p>The code on the server IS updated. If you don't see changes in the dashboard, it is 100% a browser cache issue.</p>";
} elseif (strpos($content, 'v3.1') !== false) {
    echo "<h2 style='color:orange'>PARTIAL: v3.1 Code Found.</h2>";
    echo "<p>This is an older version, but fairly recent.</p>";
} else {
    echo "<h2 style='color:red'>FAILURE: New Version Tag NOT Found.</h2>";
    echo "<p>The code on the server is OLD. The deployment (git push/pull) did not update this file.</p>";
}

// Check for the grid layout change
if (strpos($content, 'md:grid-cols-3') !== false) {
    echo "<h2 style='color:green'>SUCCESS: Grid Layout Code Found!</h2>";
} else {
    echo "<h2 style='color:red'>FAILURE: Grid Layout Code NOT Found.</h2>";
}

echo "<hr>";
echo "<h3>First 2000 characters of file:</h3>";
echo "<pre>" . htmlspecialchars(substr($content, 0, 2000)) . "</pre>";
?>
