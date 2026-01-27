<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

try {
    $db = db();
    require_role(['SuperAdmin', 'Admin', 'Franchise Officer']);

    // Ensure table exists (it should be created by commuter portal, but just in case)
    $db->query("CREATE TABLE IF NOT EXISTS commuter_complaints (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ref_number VARCHAR(64) UNIQUE,
        user_id INT DEFAULT NULL,
        route_id VARCHAR(64) DEFAULT NULL,
        plate_number VARCHAR(32) DEFAULT NULL,
        location VARCHAR(255) DEFAULT NULL,
        complaint_type VARCHAR(64),
        description TEXT,
        media_path VARCHAR(255) DEFAULT NULL,
        status ENUM('Submitted', 'Under Review', 'Resolved', 'Dismissed') DEFAULT 'Submitted',
        ai_tags VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    $statusFilter = $_GET['status'] ?? '';
    
    // Join with routes and terminals for integrated details
    $sql = "SELECT c.*, r.route_name, t.name AS terminal_name
            FROM commuter_complaints c
            LEFT JOIN routes r ON c.route_id = r.route_id
            LEFT JOIN terminals t ON t.id = c.terminal_id";
    
    if ($statusFilter) {
        $sql .= " WHERE c.status = '" . $db->real_escape_string($statusFilter) . "'";
    }
    $sql .= " ORDER BY c.created_at DESC";

    $res = $db->query($sql);
    $items = [];
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $rootUrl = '';
    $pos = strpos($script, '/admin/');
    if ($pos !== false) $rootUrl = substr($script, 0, $pos);
    if ($rootUrl === '/') $rootUrl = '';
    while ($row = $res->fetch_assoc()) {
        $items[] = [
            'id' => (int)$row['id'],
            'ref_number' => $row['ref_number'],
            'user_id' => $row['user_id'],
            'type' => $row['complaint_type'],
            'description' => $row['description'],
            'status' => $row['status'],
            'ai_tags' => $row['ai_tags'],
            'created_at' => $row['created_at'],
            'media_url' => $row['media_path'] ? ($rootUrl . '/citizen/commuter/' . $row['media_path']) : null,
            // Integrated data
            'route_name' => $row['route_name'] ?? 'N/A',
            'plate_number' => $row['plate_number'] ?? '',
            'location' => $row['location'] ?? '',
            'terminal_id' => isset($row['terminal_id']) ? (int)$row['terminal_id'] : 0,
            'terminal_name' => $row['terminal_name'] ?? ''
        ];
    }

    echo json_encode(['ok' => true, 'data' => $items]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
