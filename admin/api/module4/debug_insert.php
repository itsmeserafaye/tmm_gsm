<?php
// Standalone DB connection logic for debugging
// Based on admin/includes/db.php logic
$host = 'localhost';
$user = 'root';
$pass = '';
$name = 'tmm'; // Try 'tmm' first as per db.php fallback

$candidates = [
    ['localhost', 'root', '', 'tmm'],
    ['localhost', 'root', '', 'tmm_db'],
    ['127.0.0.1', 'root', '', 'tmm'],
    ['127.0.0.1', 'root', '', 'tmm_db']
];

$lastErr = '';
$connected = false;
$conn = null;

foreach ($candidates as $c) {
    list($h, $u, $p, $n) = $c;
    echo "Trying $h / $u / $n ...\n";
    try {
        $conn = @new mysqli($h, $u, $p, $n);
        if (!$conn->connect_error) {
            $connected = true;
            echo "Connected!\n";
            break;
        }
        $lastErr = $conn->connect_error;
        echo "Failed: $lastErr\n";
    } catch (Throwable $e) {
        $lastErr = $e->getMessage();
        echo "Exception: $lastErr\n";
    }
}

if (!$connected) {
    die("Connection failed: " . $lastErr . "\n");
}

function db() { global $conn; return $conn; }
$db = $conn;

echo "Debugging INSERT...\n";

// 1. Get a valid schedule_id
$res = $db->query("SELECT schedule_id FROM inspection_schedules LIMIT 1");
if (!$res || $res->num_rows === 0) {
    die("No schedules found.\n");
}
$row = $res->fetch_assoc();
$scheduleId = (int)$row['schedule_id'];
echo "Found Schedule ID: $scheduleId\n";

// 2. Check if result already exists for this ID (should be empty for insert test, but might exist)
$res2 = $db->query("SELECT result_id FROM inspection_results WHERE schedule_id=$scheduleId");
if ($res2 && $res2->num_rows > 0) {
    echo "Result exists for this schedule. Deleting it for test...\n";
    $db->query("DELETE FROM inspection_results WHERE schedule_id=$scheduleId");
}

// 3. Try INSERT
$stmt = $db->prepare("INSERT INTO inspection_results (schedule_id, overall_status, remarks) VALUES (?,?,?)");
if (!$stmt) {
    die("Prepare failed: " . $db->error . "\n");
}
$overall = 'Pending';
$remarks = 'Debug Insert Test';
$stmt->bind_param('iss', $scheduleId, $overall, $remarks);

if ($stmt->execute()) {
    echo "INSERT SUCCESS! ID: " . $stmt->insert_id . "\n";
    // Clean up
    $db->query("DELETE FROM inspection_results WHERE result_id=" . $stmt->insert_id);
} else {
    echo "INSERT FAILED: " . $stmt->error . "\n";
}
?>
