<?php
require_once __DIR__ . '/admin/includes/db.php';
$db = db();

$terminalId = 1;
$areaType = 'terminal';

echo "Seeding data for Terminal ID: $terminalId...\n";

// Generate dates for the last 6 days + today + tomorrow
$dates = [];
for ($i = 6; $i >= -1; $i--) { // -1 is tomorrow (forecast testing)
    $dates[] = date('Y-m-d', strtotime("-$i days"));
}

$inserted = 0;

foreach ($dates as $date) {
    // Simulate operating hours 6 AM to 10 PM
    for ($h = 6; $h <= 22; $h++) {
        $hourStr = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00:00';
        $observedAt = "$date $hourStr";
        
        // Base demand
        $demand = rand(15, 30);
        
        // Morning Rush (7-9 AM)
        if ($h >= 7 && $h <= 9) {
            $demand = rand(65, 95);
        }
        
        // Evening Rush (5-7 PM)
        if ($h >= 17 && $h <= 19) {
            $demand = rand(70, 100);
        }
        
        // Weekend variation (Sat/Sun has less morning rush, more spread out)
        $dow = date('N', strtotime($date));
        if ($dow >= 6) {
            if ($h >= 10 && $h <= 16) {
                $demand += rand(10, 20); // Mid-day shopping traffic
            }
            // Less morning rush
            if ($h >= 7 && $h <= 9) {
                $demand = rand(30, 50);
            }
        }

        // Upsert
        $stmt = $db->prepare("INSERT INTO puv_demand_observations 
            (area_type, area_ref, observed_at, demand_count, source)
            VALUES (?, ?, ?, ?, 'manual')
            ON DUPLICATE KEY UPDATE demand_count=VALUES(demand_count), source='manual'");
        
        $stmt->bind_param('sssi', $areaType, $terminalId, $observedAt, $demand);
        if ($stmt->execute()) {
            $inserted++;
        }
    }
}

echo "Done! Seeded/Updated $inserted hourly observations.\n";
echo "You can now check the Dashboard to see the Forecast and Readiness status.\n";
?>