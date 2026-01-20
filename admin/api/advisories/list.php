<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

try {
    require_role(['SuperAdmin', 'Admin', 'Terminal Manager', 'Franchise Officer']);

    // Ensure table exists
    $db->query("CREATE TABLE IF NOT EXISTS public_advisories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT,
        type ENUM('Normal', 'Urgent', 'Route Update') DEFAULT 'Normal',
        is_active TINYINT(1) DEFAULT 1,
        posted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    $res = $db->query("SELECT * FROM public_advisories ORDER BY posted_at DESC");
    $items = [];
    while ($row = $res->fetch_assoc()) {
        $items[] = [
            'id' => (int)$row['id'],
            'title' => $row['title'],
            'content' => $row['content'],
            'type' => $row['type'],
            'is_active' => (bool)$row['is_active'],
            'posted_at' => $row['posted_at']
        ];
    }

    echo json_encode(['ok' => true, 'data' => $items]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
