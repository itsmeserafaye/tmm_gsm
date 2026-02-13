<?php
// Connect to DB directly to find a schedule
$conn = new mysqli('localhost', 'root', '', 'tmm');
if ($conn->connect_error) {
    die("DB Connection Failed: " . $conn->connect_error);
}

// Find a schedule
$res = $conn->query("SELECT schedule_id FROM inspection_schedules LIMIT 1");
$scheduleId = 0;
if ($res && $row = $res->fetch_assoc()) {
    $scheduleId = $row['schedule_id'];
} else {
    // Create one if needed
    $conn->query("INSERT INTO vehicles (plate_number) VALUES ('TEST1234') ON DUPLICATE KEY UPDATE id=id");
    $conn->query("INSERT INTO inspection_schedules (plate_number, scheduled_at) VALUES ('TEST1234', NOW())");
    $scheduleId = $conn->insert_id;
}

echo "Using Schedule ID: $scheduleId\n";

// Prepare POST data
$postdata = http_build_query(
    array(
        'schedule_id' => $scheduleId,
        'overall_status' => 'Pending',
        'remarks' => 'Test Remark',
        'items' => array('RW_LIGHTS' => 'Pass')
    )
);

$opts = array('http' =>
    array(
        'method'  => 'POST',
        'header'  => 'Content-Type: application/x-www-form-urlencoded',
        'content' => $postdata,
        'ignore_errors' => true
    )
);

$context  = stream_context_create($opts);
$url = 'http://localhost/tmm/admin/api/module4/submit_checklist.php';
echo "Requesting $url...\n";
$result = file_get_contents($url, false, $context);

echo "\n--- RESPONSE START ---\n";
echo $result;
echo "\n--- RESPONSE END ---\n";
?>
