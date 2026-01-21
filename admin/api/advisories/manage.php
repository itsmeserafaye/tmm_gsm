<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

try {
    require_role(['SuperAdmin', 'Admin']);

    $db = db();
    
    // Ensure table exists
    $db->query("CREATE TABLE IF NOT EXISTS public_advisories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT,
        type ENUM('Normal', 'Urgent', 'Route Update', 'info', 'warning', 'alert') DEFAULT 'Normal',
        is_active TINYINT(1) DEFAULT 1,
        posted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    $action = $_REQUEST['action'] ?? '';

    // List all advisories
    if ($action === 'list') {
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
    }

    // Create advisory
    elseif ($action === 'create') {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $type = trim($_POST['type'] ?? 'Normal');
        
        if (empty($title) || empty($content)) {
            echo json_encode(['ok' => false, 'error' => 'Title and content required']);
            exit;
        }

        if (!in_array($type, ['Normal', 'Urgent', 'Route Update', 'info', 'warning', 'alert'])) {
            $type = 'Normal';
        }

        $stmt = $db->prepare("INSERT INTO public_advisories (title, content, type, is_active) VALUES (?, ?, ?, 1)");
        $stmt->bind_param('sss', $title, $content, $type);
        
        if ($stmt->execute()) {
            echo json_encode(['ok' => true, 'id' => $db->insert_id, 'message' => 'Advisory created']);
        } else {
            echo json_encode(['ok' => false, 'error' => $stmt->error]);
        }
        $stmt->close();
    }

    // Update advisory
    elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $type = trim($_POST['type'] ?? 'Normal');
        $is_active = (int)($_POST['is_active'] ?? 1);
        
        if (!$id || empty($title) || empty($content)) {
            echo json_encode(['ok' => false, 'error' => 'ID, title and content required']);
            exit;
        }

        $stmt = $db->prepare("UPDATE public_advisories SET title=?, content=?, type=?, is_active=? WHERE id=?");
        $stmt->bind_param('sssii', $title, $content, $type, $is_active, $id);
        
        if ($stmt->execute()) {
            echo json_encode(['ok' => true, 'message' => 'Advisory updated']);
        } else {
            echo json_encode(['ok' => false, 'error' => $stmt->error]);
        }
        $stmt->close();
    }

    // Delete advisory
    elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        
        if (!$id) {
            echo json_encode(['ok' => false, 'error' => 'ID required']);
            exit;
        }

        $stmt = $db->prepare("DELETE FROM public_advisories WHERE id=?");
        $stmt->bind_param('i', $id);
        
        if ($stmt->execute()) {
            echo json_encode(['ok' => true, 'message' => 'Advisory deleted']);
        } else {
            echo json_encode(['ok' => false, 'error' => $stmt->error]);
        }
        $stmt->close();
    }

    else {
        echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
?>
