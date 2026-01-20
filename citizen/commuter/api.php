<?php
if (function_exists('session_status') && session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

// Standalone DB connection to avoid legacy schema migration issues
require_once __DIR__ . '/../../includes/env.php';
tmm_load_env(__DIR__ . '/../../.env');

function get_db() {
    static $conn;
    if ($conn) return $conn;

    $host = getenv('TMM_DB_HOST') ?: 'localhost';
    $user = getenv('TMM_DB_USER') ?: 'tmm_tmmgosergfvx';
    $pass = getenv('TMM_DB_PASS') ?: 'lVy6QxSxoF5Q9F';
    $name = getenv('TMM_DB_NAME') ?: 'tmm_tmm';

    // Try primary connection
    $conn = @new mysqli($host, $user, $pass, $name);
    
    // If primary fails, try standard local fallback (root/empty) often used in dev
    if ($conn->connect_error) {
         $conn = @new mysqli('localhost', 'root', '', 'tmm');
    }

    if ($conn->connect_error) {
        // Return JSON error so frontend can see it
        die(json_encode(['ok'=>false, 'error'=>'DB Connection Error: ' . $conn->connect_error])); 
    }
    
    $conn->set_charset('utf8mb4');
    return $conn;
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$db = get_db();

// Ensure commuter_complaints table exists with Admin-compatible columns
$db->query("CREATE TABLE IF NOT EXISTS commuter_complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ref_number VARCHAR(64) UNIQUE,
    user_id INT DEFAULT NULL,
    complaint_type VARCHAR(64),
    description TEXT,
    media_path VARCHAR(255) DEFAULT NULL,
    status ENUM('Submitted', 'Under Review', 'Resolved', 'Dismissed') DEFAULT 'Submitted',
    ai_tags VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

// Add linkage columns if they don't exist (to match Admin schema)
$cols = $db->query("SHOW COLUMNS FROM commuter_complaints");
$hasRouteId = false;
$hasPlateNumber = false;
if ($cols) {
    while ($c = $cols->fetch_assoc()) {
        if ($c['Field'] === 'route_id') $hasRouteId = true;
        if ($c['Field'] === 'plate_number') $hasPlateNumber = true;
    }
}
if (!$hasRouteId) { $db->query("ALTER TABLE commuter_complaints ADD COLUMN route_id VARCHAR(64) DEFAULT NULL"); }
if (!$hasPlateNumber) { $db->query("ALTER TABLE commuter_complaints ADD COLUMN plate_number VARCHAR(32) DEFAULT NULL"); }

$action = $_REQUEST['action'] ?? '';

// Check Login State
$isLoggedIn = !empty($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'Commuter';
$userId = $isLoggedIn ? (int)$_SESSION['user_id'] : null;

// ----------------------------------------------------------------------
// AUTH ENDPOINTS
// ----------------------------------------------------------------------

if ($action === 'check_session') {
    echo json_encode([
        'ok' => true,
        'is_logged_in' => $isLoggedIn,
        'user' => $isLoggedIn ? [
            'name' => $_SESSION['name'] ?? 'Commuter',
            'email' => $_SESSION['email'] ?? ''
        ] : null
    ]);
    exit;
}

// ----------------------------------------------------------------------
// PUBLIC ENDPOINTS (No Login Required)
// ----------------------------------------------------------------------

if ($action === 'get_routes') {
    // Fetches list of authorized routes (Matches Admin 'routes' table)
    $routes = [];
    // Only show Active routes to commuters
    $res = $db->query("SELECT route_id, route_name, origin, destination, fare, status FROM routes WHERE status='Active' ORDER BY route_name ASC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $routes[] = $row;
        }
    }
    echo json_encode(['ok' => true, 'data' => $routes]);
    exit;
}

if ($action === 'get_terminals') {
    // Fetches list of terminals (Matches Admin 'terminals' table)
    $terminals = [];
    $res = $db->query("SELECT id, name, location, capacity, city, address FROM terminals ORDER BY name ASC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $terminals[] = $row;
        }
    }
    echo json_encode(['ok' => true, 'data' => $terminals]);
    exit;
}

if ($action === 'get_advisories') {
    // Fetches public advisories (Matches Admin 'public_advisories' table)
    $advisories = [];
    // Check if is_active column exists, otherwise just fetch all or filter by type if needed
    // For now, simply fetch all since is_active might not be present in all schema versions
    $res = $db->query("SELECT id, title, content, type, posted_at FROM public_advisories ORDER BY posted_at DESC LIMIT 20");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $advisories[] = $row;
        }
    }
    echo json_encode(['ok' => true, 'data' => $advisories]);
    exit;
}

if ($action === 'get_fares') {
    // Fetches fare matrix information (Matches Admin 'routes' table)
    $fares = [];
    $res = $db->query("SELECT route_name, fare, origin, destination FROM routes WHERE status='Active' ORDER BY route_name ASC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $fares[] = $row;
        }
    }
    echo json_encode(['ok' => true, 'data' => $fares]);
    exit;
}

// ----------------------------------------------------------------------
// EXISTING ENDPOINTS
// ----------------------------------------------------------------------

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
    $routeId = $_POST['route_id'] ?? null; // ID from Admin routes
    $plate = $_POST['plate_number'] ?? '';
    $location = $_POST['location'] ?? '';
    
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
    
    // Store full description including location if not separate
    // $fullDesc = "Location: $location\n\n$desc"; 
    // We now have a dedicated location column, but we'll keep it in description for backward compatibility if needed, 
    // or just store it separately. Let's store separately.
    
    $stmt = $db->prepare("INSERT INTO commuter_complaints (ref_number, user_id, complaint_type, description, media_path, ai_tags, route_id, plate_number, location) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('sisssssss', $ref, $userId, $type, $desc, $mediaPath, $aiTagsStr, $routeId, $plate, $location);
    
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

    $stmt = $db->prepare("SELECT ref_number, status, created_at, description FROM commuter_complaints WHERE ref_number = ?");
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

if ($action === 'get_my_complaints') {
    if (!$isLoggedIn) {
        echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
        exit;
    }

    $stmt = $db->prepare("SELECT ref_number, complaint_type, status, created_at, description FROM commuter_complaints WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    
    $complaints = [];
    while ($row = $res->fetch_assoc()) {
        $complaints[] = $row;
    }
    
    echo json_encode(['ok' => true, 'data' => $complaints]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Invalid action']);
?>
