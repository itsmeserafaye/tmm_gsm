<?php
if (php_sapi_name() !== 'cli') {
    die("CLI only");
}
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/analytics_helper.php';

$db = db();
$mode = $argv[1] ?? 'hourly'; // default to hourly

echo "Running Scheduler in mode: $mode\n";

// 1. Identify active routes/terminals
// Get pairs that have assignments or recent logs
// We join terminal_assignments with terminals to get ID
$sql = "
    SELECT DISTINCT t.name as terminal_name, ta.route_id, t.id as terminal_id
    FROM terminal_assignments ta
    JOIN terminals t ON t.name = ta.terminal_name
    WHERE ta.route_id IS NOT NULL AND ta.route_id != ''
    UNION
    SELECT DISTINCT t.name as terminal_name, v.route_id, t.id as terminal_id
    FROM terminal_logs l
    JOIN vehicles v ON v.plate_number = l.vehicle_plate
    JOIN terminals t ON t.id = l.terminal_id
    WHERE l.created_at >= NOW() - INTERVAL 7 DAY
";

$res = $db->query($sql);
if (!$res) {
    die("DB Error: " . $db->error . "\n");
}

$pairs = [];
while ($r = $res->fetch_assoc()) {
    $pairs[] = $r;
}

echo "Found " . count($pairs) . " active terminal-route pairs.\n";

$horizon = ($mode === 'nightly') ? 1440 : 240; // 24h vs 4h

foreach ($pairs as $p) {
    $tid = (int)$p['terminal_id'];
    $rid = $p['route_id'];
    if (!$rid) continue;
    
    echo "Forecasting for Terminal $tid / Route $rid ... ";
    try {
        $res = run_forecast_job($db, $tid, $rid, $horizon, 60);
        if ($res['ok']) {
            echo "OK (" . $res['inserted'] . " rows)\n";
        } else {
            echo "Failed\n";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

// 2. Compute Caps
echo "Computing Dynamic Caps...\n";
$capsRes = run_compute_caps_job($db, '', $horizon, 0.7, 0.6, false);
if ($capsRes['ok']) {
    echo "Caps Updated: " . $capsRes['inserted'] . " inserted.\n";
} else {
    echo "Caps Update Failed: " . ($capsRes['error'] ?? 'unknown') . "\n";
}

echo "Done.\n";
?>