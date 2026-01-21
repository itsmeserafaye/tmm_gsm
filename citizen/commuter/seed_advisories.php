<?php
// Quick seed script to add sample advisories
if (function_exists('session_status') && session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

// Only allow from localhost or admin
$isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1', 'localhost']);
$isAdmin = !empty($_SESSION['role']) && $_SESSION['role'] === 'SuperAdmin';

if (!$isLocal && !$isAdmin) {
    die('Access Denied');
}

require_once __DIR__ . '/../../includes/env.php';
tmm_load_env(__DIR__ . '/../../.env');

$host = getenv('TMM_DB_HOST') ?: 'localhost';
$user = getenv('TMM_DB_USER') ?: 'tmm_tmmgosergfvx';
$pass = getenv('TMM_DB_PASS') ?: 'lVy6QxSxoF5Q9F';
$name = getenv('TMM_DB_NAME') ?: 'tmm_tmm';

$db = @new mysqli($host, $user, $pass, $name);
if (!$db || $db->connect_error) {
    $db = @new mysqli('localhost', 'root', '', 'tmm');
}

if ($db->connect_error) {
    die('Database Connection Error: ' . $db->connect_error);
}

$db->set_charset('utf8mb4');

// Create table if not exists
$db->query("CREATE TABLE IF NOT EXISTS public_advisories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    type ENUM('Normal', 'Urgent', 'Route Update', 'info', 'warning', 'alert') DEFAULT 'Normal',
    is_active TINYINT(1) DEFAULT 1,
    posted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

// Clear existing advisories
$db->query("DELETE FROM public_advisories");

// Insert sample advisories
$samples = [
    [
        'title' => '⚠️ Route 5 Temporary Detour',
        'content' => 'Route 5 will be using temporary detour roads until January 25 due to road maintenance on Makati Avenue. Passengers may experience 10-15 minutes additional travel time.',
        'type' => 'warning'
    ],
    [
        'title' => '🚨 Heavy Traffic Alert - Peak Hours',
        'content' => 'Heavy traffic expected on Edsa from 5:00 PM to 8:00 PM today. All routes using Edsa may experience delays. Consider traveling earlier or later if possible.',
        'type' => 'alert'
    ],
    [
        'title' => '✅ New Express Route 12X Available',
        'content' => 'Express Route 12X now available! Direct service from North Terminal to South Terminal. Faster and more comfortable. Check our Routes & Fares section for details.',
        'type' => 'info'
    ],
    [
        'title' => '🌧️ Weather Advisory',
        'content' => 'Light rain expected in the afternoon. Please allow extra time for travel and be cautious on wet roads.',
        'type' => 'warning'
    ],
    [
        'title' => '📢 Service Update - Central Terminal',
        'content' => 'Central Terminal will undergo facility upgrades from January 22-24. Limited services available. Check with terminal staff for assistance.',
        'type' => 'Normal'
    ]
];

foreach ($samples as $item) {
    $stmt = $db->prepare("INSERT INTO public_advisories (title, content, type, is_active) VALUES (?, ?, ?, 1)");
    $stmt->bind_param('sss', $item['title'], $item['content'], $item['type']);
    $stmt->execute();
    $stmt->close();
}

$db->close();

echo "✅ Sample advisories have been added successfully!\n\n";
echo "Go to: http://yoursite.com/citizen/commuter/ to see the advisories\n\n";
echo "To add more advisories:\n";
echo "1. Login to Admin Panel\n";
echo "2. Navigate to Settings > Manage Advisories\n";
echo "3. Create new advisory with title and content\n";
?>
