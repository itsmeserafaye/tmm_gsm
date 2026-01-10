<?php
// Standalone DB connection to avoid legacy schema migration issues
function get_db() {
    static $conn;
    if ($conn) return $conn;
    $host = '127.0.0.1';
    $user = 'root';
    $pass = '';
    $name = 'tmm';
    $conn = @new mysqli($host, $user, $pass, $name);
    if ($conn->connect_error) {
        // Fallback: Try connecting without DB name and create it
        $conn = @new mysqli($host, $user, $pass);
        if ($conn->connect_error) { die(json_encode(['ok'=>false, 'error'=>'DB Connection Error'])); }
        $conn->query("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $conn->select_db($name);
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

header('Content-Type: application/json');

$db = get_db();

// Ensure commuter_complaints table exists
$db->query("CREATE TABLE IF NOT EXISTS commuter_complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ref_number VARCHAR(64) UNIQUE,
    complaint_type VARCHAR(64),
    description TEXT,
    media_path VARCHAR(255) DEFAULT NULL,
    status ENUM('Submitted', 'Under Review', 'Resolved', 'Dismissed') DEFAULT 'Submitted',
    ai_tags VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

$action = $_REQUEST['action'] ?? '';

if ($action === 'verify_vehicle') {
    $plate = trim($_REQUEST['plate_number'] ?? '');
    if (!$plate) {
        echo json_encode(['ok' => false, 'error' => 'Plate number is required']);
        exit;
    }

    $stmt = $db->prepare("SELECT v.plate_number, v.status, v.operator_name, v.coop_name, v.route_id, t.terminal_name 
                          FROM vehicles v 
                          LEFT JOIN terminal_assignments t ON v.plate_number = t.plate_number 
                          WHERE v.plate_number = ?");
    $stmt->bind_param('s', $plate);
    $stmt->execute();
    $res = $stmt->get_result();
    $vehicle = $res->fetch_assoc();

    if ($vehicle) {
        echo json_encode(['ok' => true, 'data' => $vehicle]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Vehicle not found']);
    }
    exit;
}

if ($action === 'get_travel_info') {
    // Get latest log entries to determine crowding (simple logic)
    // In real app, this would use Module 5 logs + AI Demand Forecasts
    // Mocking AI data for now based on requirement "Data Source: Module 5 + AI Demand Forecasts"
    
    // Check if we have forecast data
    $forecast = [];
    $res = $db->query("SELECT * FROM demand_forecasts ORDER BY ts DESC LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) {
        $forecast = $row;
    }

    // Simple logic for crowding
    $crowding = 'Low'; // Default
    $hour = (int)date('H');
    if ($hour >= 7 && $hour <= 9) $crowding = 'High';
    elseif ($hour >= 17 && $hour <= 19) $crowding = 'High';
    elseif ($hour >= 10 && $hour <= 16) $crowding = 'Moderate';

    $data = [
        'crowding_level' => $crowding,
        'estimated_wait_time' => ($crowding === 'High' ? '15-20 mins' : ($crowding === 'Moderate' ? '10-15 mins' : '5-10 mins')),
        'best_time_to_travel' => '10:00 AM - 3:00 PM (Off-peak)',
        'forecast' => $forecast
    ];
    
    echo json_encode(['ok' => true, 'data' => $data]);
    exit;
}

if ($action === 'submit_complaint') {
    $type = $_POST['type'] ?? '';
    $desc = $_POST['description'] ?? '';
    
    if (!$type || !$desc) {
        echo json_encode(['ok' => false, 'error' => 'Type and description are required']);
        exit;
    }

    $mediaPath = null;
    if (isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['media']['name'], PATHINFO_EXTENSION);
        $filename = 'complaint_' . time() . '_' . uniqid() . '.' . $ext;
        $targetDir = __DIR__ . '/uploads/';
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        
        if (move_uploaded_file($_FILES['media']['tmp_name'], $targetDir . $filename)) {
            $mediaPath = 'uploads/' . $filename;
        }
    }

    // AI Auto-tagging simulation
    $aiTags = [];
    if (stripos($desc, 'speed') !== false) $aiTags[] = 'Speeding';
    if (stripos($desc, 'rude') !== false) $aiTags[] = 'Behavior';
    if (stripos($desc, 'overcharge') !== false) $aiTags[] = 'Overcharging';
    $aiTagsStr = implode(',', $aiTags);

    $ref = 'COM-' . strtoupper(uniqid());
    
    $stmt = $db->prepare("INSERT INTO commuter_complaints (ref_number, complaint_type, description, media_path, ai_tags) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('sssss', $ref, $type, $desc, $mediaPath, $aiTagsStr);
    
    if ($stmt->execute()) {
        echo json_encode(['ok' => true, 'ref_number' => $ref, 'ai_tags' => $aiTags]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Database error']);
    }
    exit;
}

if ($action === 'get_complaint_status') {
    $ref = $_GET['ref_number'] ?? '';
    if (!$ref) {
        echo json_encode(['ok' => false, 'error' => 'Reference number is required']);
        exit;
    }

    $stmt = $db->prepare("SELECT ref_number, status, created_at FROM commuter_complaints WHERE ref_number = ?");
    $stmt->bind_param('s', $ref);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($row = $res->fetch_assoc()) {
        echo json_encode(['ok' => true, 'data' => $row]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Complaint not found']);
    }
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Invalid action']);
?>
