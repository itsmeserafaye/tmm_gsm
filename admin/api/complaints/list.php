<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

try {
    require_role(['SuperAdmin', 'Admin', 'Franchise Officer']);

    // Ensure table exists (it should be created by commuter portal, but just in case)
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

    $statusFilter = $_GET['status'] ?? '';
    $sql = "SELECT * FROM commuter_complaints";
    if ($statusFilter) {
        $sql .= " WHERE status = '" . $db->real_escape_string($statusFilter) . "'";
    }
    $sql .= " ORDER BY created_at DESC";

    $res = $db->query($sql);
    $items = [];
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
            'media_url' => $row['media_path'] ? '/tmm/citizen/commuter/' . $row['media_path'] : null
        ];
    }

    echo json_encode(['ok' => true, 'data' => $items]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
