<?php
if (function_exists('session_status') && session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

// Standalone DB connection to avoid legacy schema migration issues
require_once __DIR__ . '/../../includes/env.php';
tmm_load_env(__DIR__ . '/../../.env');
require_once __DIR__ . '/../../includes/recaptcha.php';

function get_db()
{
    static $conn;
    if ($conn)
        return $conn;

    $host = trim((string) getenv('TMM_DB_HOST'));
    $user = trim((string) getenv('TMM_DB_USER'));
    $pass = (string) getenv('TMM_DB_PASS');
    $name = trim((string) getenv('TMM_DB_NAME'));

    if ($host === '')
        $host = 'localhost';
    if ($user === '')
        $user = 'tmm_tmmgosergfvx';
    if ($name === '')
        $name = 'tmm_tmm';

    $candidates = [
        [$host, $user, $pass, $name],
    ];
    if (strtolower($host) === 'localhost') {
        $candidates[] = [$host, 'root', '', $name];
        $candidates[] = [$host, 'root', '', 'tmm'];
        $candidates[] = [$host, 'root', '', 'tmm_tmm'];
    }

    $lastErr = '';
    foreach ($candidates as $c) {
        [$h, $u, $p, $n] = $c;
        try {
            $try = @new mysqli($h, $u, $p, $n);
            if (!$try->connect_error) {
                $conn = $try;
                break;
            }
            $lastErr = (string) $try->connect_error;
        } catch (Throwable $e) {
            $lastErr = $e->getMessage();
        }
    }

    if (!$conn || $conn->connect_error) {
        $err = $conn && $conn->connect_error ? (string) $conn->connect_error : $lastErr;
        die(json_encode(['ok' => false, 'error' => 'DB Connection Error: ' . $err]));
    }

    $conn->set_charset('utf8mb4');
    return $conn;
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$db = get_db();

function commuter_get_setting(mysqli $db, string $key, string $default = ''): string
{
    $stmt = $db->prepare("SELECT setting_value FROM app_settings WHERE setting_key=? LIMIT 1");
    if (!$stmt)
        return $default;
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $val = $row ? (string) ($row['setting_value'] ?? '') : '';
    return $val !== '' ? $val : $default;
}

function commuter_enforce_session_timeout(mysqli $db): void
{
    if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Commuter')
        return;
    $min = (int) trim(commuter_get_setting($db, 'session_timeout', '30'));
    if ($min <= 0)
        $min = 30;
    if ($min > 1440)
        $min = 1440;
    $ttl = $min * 60;
    $now = time();
    $last = (int) ($_SESSION['commuter_last_activity'] ?? 0);
    if ($last > 0 && ($now - $last) > $ttl) {
        $_SESSION = [];
        @session_unset();
        @session_destroy();
        return;
    }
    $_SESSION['commuter_last_activity'] = $now;
}

commuter_enforce_session_timeout($db);

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
$hasLocation = false;
$hasDeviceId = false;
$hasIp = false;
$hasContactEmail = false;
$hasContactPhone = false;
$hasTerminalId = false;
if ($cols) {
    while ($c = $cols->fetch_assoc()) {
        if ($c['Field'] === 'route_id')
            $hasRouteId = true;
        if ($c['Field'] === 'plate_number')
            $hasPlateNumber = true;
        if ($c['Field'] === 'location')
            $hasLocation = true;
        if ($c['Field'] === 'device_id')
            $hasDeviceId = true;
        if ($c['Field'] === 'ip_address')
            $hasIp = true;
        if ($c['Field'] === 'contact_email')
            $hasContactEmail = true;
        if ($c['Field'] === 'contact_phone')
            $hasContactPhone = true;
        if ($c['Field'] === 'terminal_id')
            $hasTerminalId = true;
    }
}
if (!$hasRouteId) {
    $db->query("ALTER TABLE commuter_complaints ADD COLUMN route_id VARCHAR(64) DEFAULT NULL");
}
if (!$hasPlateNumber) {
    $db->query("ALTER TABLE commuter_complaints ADD COLUMN plate_number VARCHAR(32) DEFAULT NULL");
}
if (!$hasLocation) {
    $db->query("ALTER TABLE commuter_complaints ADD COLUMN location VARCHAR(255) DEFAULT NULL");
}
if (!$hasDeviceId) {
    $db->query("ALTER TABLE commuter_complaints ADD COLUMN device_id VARCHAR(80) DEFAULT NULL");
}
if (!$hasIp) {
    $db->query("ALTER TABLE commuter_complaints ADD COLUMN ip_address VARCHAR(64) DEFAULT NULL");
}
if (!$hasContactEmail) {
    $db->query("ALTER TABLE commuter_complaints ADD COLUMN contact_email VARCHAR(190) DEFAULT NULL");
}
if (!$hasContactPhone) {
    $db->query("ALTER TABLE commuter_complaints ADD COLUMN contact_phone VARCHAR(64) DEFAULT NULL");
}
if (!$hasTerminalId) {
    $db->query("ALTER TABLE commuter_complaints ADD COLUMN terminal_id INT DEFAULT NULL");
}

$action = $_REQUEST['action'] ?? '';

// Check Login State
$isLoggedIn = !empty($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'Commuter';
$userId = $isLoggedIn ? (int) $_SESSION['user_id'] : null;

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

if ($action === 'get_recaptcha_site_key') {
    $cfg = recaptcha_config($db);
    $siteKey = (string)($cfg['site_key'] ?? '');
    echo json_encode(['ok' => true, 'site_key' => $siteKey]);
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
    $res = $db->query("SELECT id, name, location, capacity, city, address FROM terminals WHERE (type IS NULL OR type <> 'Parking') ORDER BY name ASC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $terminals[] = $row;
        }
    }
    echo json_encode(['ok' => true, 'data' => $terminals]);
    exit;
}

if ($action === 'get_advisories') {
    $advisories = [];

    $db->query("CREATE TABLE IF NOT EXISTS public_advisories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT,
        type ENUM('Normal', 'Urgent', 'Route Update', 'info', 'warning', 'alert') DEFAULT 'Normal',
        is_active TINYINT(1) DEFAULT 1,
        posted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    $typeMap = function ($t) {
        $t = strtolower(trim((string) $t));
        if ($t === 'alert' || $t === 'warning' || $t === 'info')
            return $t;
        if ($t === 'urgent')
            return 'alert';
        if ($t === 'route update')
            return 'warning';
        return 'info';
    };

    $getAiSuggestion = function ($title, $content, $type) {
        $text = strtolower($title . ' ' . $content);
        if ($type === 'alert' || strpos($text, 'heavy') !== false || strpos($text, 'delay') !== false) {
            return "Consider finding an alternative route or delaying your trip by 30-60 minutes to avoid congestion.";
        }
        if ($type === 'warning' || strpos($text, 'moderate') !== false) {
            return "Allow extra travel time. Check if nearby terminals have shorter queues.";
        }
        if (strpos($text, 'accident') !== false || strpos($text, 'collision') !== false) {
            return "Expect significant delays in this area. Emergency services may be present.";
        }
        if (strpos($text, 'weather') !== false || strpos($text, 'rain') !== false) {
            return "Roads may be slippery. Please travel safely and bring umbrella/raincoat.";
        }
        return "Plan your trip ahead. Monitor this advisory for further updates.";
    };

    $hasIsActive = false;
    $col = $db->query("SHOW COLUMNS FROM public_advisories LIKE 'is_active'");
    if ($col && $col->num_rows > 0) {
        $hasIsActive = true;
    }

    $manualSql = "SELECT id, title, content, type, posted_at FROM public_advisories";
    if ($hasIsActive) {
        $manualSql .= " WHERE is_active=1";
    }
    $manualSql .= " ORDER BY posted_at DESC LIMIT 10";
    $res = $db->query($manualSql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $row['type'] = $typeMap($row['type'] ?? '');
            $row['source'] = 'admin';
            $row['suggestion'] = $getAiSuggestion($row['title'], $row['content'], $row['type']);
            $advisories[] = $row;
        }
    }

    $hours = (int) ($_GET['hours'] ?? 24);
    if ($hours < 6)
        $hours = 6;
    if ($hours > 72)
        $hours = 72;

    $predictive = [];
    $tbl = $db->query("SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='puv_demand_observations'");
    $hasObs = $tbl && ((int) ($tbl->fetch_assoc()['c'] ?? 0) > 0);

    $buildPredictive = function (string $areaType, string $labelKind) use ($db, $hours) {
        $since = date('Y-m-d H:i:s', time() - 7 * 86400);
        $labels = [];
        if ($areaType === 'terminal') {
            $res = $db->query("SELECT id AS ref, name AS label FROM terminals ORDER BY name ASC");
        } else {
            $res = $db->query("SELECT route_id AS ref, route_name AS label FROM routes WHERE status='Active' ORDER BY route_name ASC");
        }
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $ref = (string) ($r['ref'] ?? '');
                if ($ref !== '') {
                    $labels[$ref] = (string) ($r['label'] ?? $ref);
                }
            }
        }
        if (empty($labels)) {
            return [];
        }

        $avgAll = [];
        $stmtAll = $db->prepare("SELECT area_ref, AVG(demand_count) AS avg_demand FROM puv_demand_observations WHERE area_type=? AND observed_at >= ? GROUP BY area_ref");
        if ($stmtAll) {
            $stmtAll->bind_param('ss', $areaType, $since);
            $stmtAll->execute();
            $rs = $stmtAll->get_result();
            while ($row = $rs->fetch_assoc()) {
                $avgAll[(string) ($row['area_ref'] ?? '')] = (float) ($row['avg_demand'] ?? 0);
            }
            $stmtAll->close();
        }

        $avgByHour = [];
        $stmtH = $db->prepare("SELECT area_ref, HOUR(observed_at) AS h, AVG(demand_count) AS avg_demand FROM puv_demand_observations WHERE area_type=? AND observed_at >= ? GROUP BY area_ref, HOUR(observed_at)");
        if ($stmtH) {
            $stmtH->bind_param('ss', $areaType, $since);
            $stmtH->execute();
            $rs = $stmtH->get_result();
            while ($row = $rs->fetch_assoc()) {
                $ref = (string) ($row['area_ref'] ?? '');
                $h = (int) ($row['h'] ?? -1);
                if ($ref === '' || $h < 0 || $h > 23)
                    continue;
                if (!isset($avgByHour[$ref]))
                    $avgByHour[$ref] = [];
                $avgByHour[$ref][$h] = (float) ($row['avg_demand'] ?? 0);
            }
            $stmtH->close();
        }

        $candidates = [];
        $now = time();
        foreach ($labels as $ref => $label) {
            $baseline = (float) ($avgAll[$ref] ?? 0);
            if ($baseline <= 0)
                continue;
            $bestPred = 0.0;
            $bestTs = null;
            for ($i = 0; $i < $hours; $i++) {
                $t = $now + ($i * 3600);
                $h = (int) date('G', $t);
                $pred = (float) (($avgByHour[$ref][$h] ?? $baseline));
                if ($pred > $bestPred) {
                    $bestPred = $pred;
                    $bestTs = $t;
                }
            }
            if ($bestTs === null)
                continue;
            $ratio = $baseline > 0 ? ($bestPred / $baseline) : 0;
            if ($ratio < 1.25 && ($bestPred - $baseline) < 8)
                continue;

            $sev = 'medium';
            if ($ratio >= 2.0)
                $sev = 'critical';
            elseif ($ratio >= 1.6)
                $sev = 'high';

            $candidates[] = [
                'ref' => $ref,
                'label' => $label,
                'baseline' => $baseline,
                'predicted' => $bestPred,
                'peak_ts' => $bestTs,
                'severity' => $sev,
                'ratio' => $ratio,
            ];
        }

        usort($candidates, function ($a, $b) {
            $sevRank = ['critical' => 3, 'high' => 2, 'medium' => 1];
            $sa = $sevRank[$a['severity']] ?? 0;
            $sb = $sevRank[$b['severity']] ?? 0;
            if ($sa !== $sb)
                return $sb <=> $sa;
            return ($b['ratio'] ?? 0) <=> ($a['ratio'] ?? 0);
        });

        $out = [];
        foreach (array_slice($candidates, 0, 3) as $c) {
            $type = $c['severity'] === 'critical' ? 'alert' : ($c['severity'] === 'high' ? 'warning' : 'info');
            $when = date('M d, g:i A', (int) $c['peak_ts']);
            $title = $type === 'alert' ? "High Crowd Alert: {$c['label']}" : ($type === 'warning' ? "Crowd Advisory: {$c['label']}" : "Travel Advisory: {$c['label']}");
            $content = "{$labelKind} demand is expected to be higher than usual around {$when}. Expect longer queues and wait times. Consider traveling before/after peak if possible.";
            $out[] = [
                'id' => 'ai_' . $areaType . '_' . md5($c['ref'] . '|' . $c['peak_ts'] . '|' . $c['severity']),
                'title' => $title,
                'content' => $content,
                'type' => $type,
                'source' => 'predictive',
                'posted_at' => date('Y-m-d H:i:s'),
            ];
        }
        return $out;
    };

    if ($hasObs) {
        foreach ($buildPredictive('terminal', 'Terminal') as $p) {
            $predictive[] = $p;
        }
        foreach ($buildPredictive('route', 'Route') as $p) {
            $predictive[] = $p;
        }
    }

    foreach ($predictive as $p) {
        $advisories[] = $p;
    }

    if (empty($advisories)) {
        $advisories[] = [
            'id' => 'default',
            'title' => '✅ All Systems Normal',
            'content' => 'No active advisories at the moment. All routes and terminals are operating normally.',
            'type' => 'info',
            'source' => 'system',
            'posted_at' => date('Y-m-d H:i:s')
        ];
    }

    usort($advisories, function ($a, $b) {
        return strcmp((string) ($b['posted_at'] ?? ''), (string) ($a['posted_at'] ?? ''));
    });

    $meta = [
        'server_time' => date('c'),
        'api_build' => date('c', (int) @filemtime(__FILE__)),
    ];
    echo json_encode(['ok' => true, 'data' => $advisories, 'meta' => $meta]);
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
    $hour = (int) date('H');
    if ($hour >= 7 && $hour <= 9)
        $crowding = 'High';
    elseif ($hour >= 17 && $hour <= 19)
        $crowding = 'High';
    elseif ($hour >= 10 && $hour <= 16)
        $crowding = 'Moderate';

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
    $terminalId = isset($_POST['terminal_id']) ? (int)$_POST['terminal_id'] : 0;
    $deviceId = trim((string)($_POST['device_id'] ?? ''));
    $contactEmail = trim((string)($_POST['contact_email'] ?? ''));
    $contactPhone = trim((string)($_POST['contact_phone'] ?? ''));
    $remoteIp = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));

    if (!$type || !$desc) {
        echo json_encode(['ok' => false, 'error' => 'Type and description are required']);
        exit;
    }

    $cfg = recaptcha_config($db);
    $siteKey = (string)($cfg['site_key'] ?? '');
    $secretKey = (string)($cfg['secret_key'] ?? '');
    if (!$isLoggedIn && $siteKey !== '') {
        $token = trim((string)($_POST['recaptcha_token'] ?? ''));
        if ($token === '') {
            echo json_encode(['ok' => false, 'error' => 'captcha_required']);
            exit;
        }
        if ($secretKey === '') {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'captcha_not_configured']);
            exit;
        }
        $v = recaptcha_verify($secretKey, $token, $remoteIp);
        if (empty($v['ok'])) {
            echo json_encode(['ok' => false, 'error' => 'captcha_failed']);
            exit;
        }
    }

    if (!$isLoggedIn) {
        $maxPerDay = 5;
        $start = date('Y-m-d 00:00:00');
        $end = date('Y-m-d 23:59:59');
        if ($deviceId !== '') {
            $st = $db->prepare("SELECT COUNT(*) AS c FROM commuter_complaints WHERE device_id=? AND created_at BETWEEN ? AND ?");
            if ($st) {
                $st->bind_param('sss', $deviceId, $start, $end);
                $st->execute();
                $row = $st->get_result()->fetch_assoc();
                $st->close();
                if ((int)($row['c'] ?? 0) >= $maxPerDay) {
                    echo json_encode(['ok' => false, 'error' => 'rate_limited']);
                    exit;
                }
            }
        } elseif ($remoteIp !== '') {
            $st = $db->prepare("SELECT COUNT(*) AS c FROM commuter_complaints WHERE ip_address=? AND created_at BETWEEN ? AND ?");
            if ($st) {
                $st->bind_param('sss', $remoteIp, $start, $end);
                $st->execute();
                $row = $st->get_result()->fetch_assoc();
                $st->close();
                if ((int)($row['c'] ?? 0) >= $maxPerDay) {
                    echo json_encode(['ok' => false, 'error' => 'rate_limited']);
                    exit;
                }
            }
        }
    }

    $mediaPath = null;
    if (isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['media']['name'], PATHINFO_EXTENSION);
        $filename = 'complaint_' . time() . '_' . uniqid() . '.' . $ext;
        $targetDir = __DIR__ . '/uploads/';
        if (!is_dir($targetDir))
            mkdir($targetDir, 0777, true);

        if (move_uploaded_file($_FILES['media']['tmp_name'], $targetDir . $filename)) {
            $mediaPath = 'uploads/' . $filename;
        }
    }

    // AI Auto-tagging simulation
    $aiTags = [];
    if (stripos($desc, 'speed') !== false)
        $aiTags[] = 'Speeding';
    if (stripos($desc, 'rude') !== false)
        $aiTags[] = 'Behavior';
    if (stripos($desc, 'overcharge') !== false)
        $aiTags[] = 'Overcharging';
    $aiTagsStr = implode(',', $aiTags);

    $ref = 'COM-' . strtoupper(uniqid());

    // Store full description including location if not separate
    // $fullDesc = "Location: $location\n\n$desc"; 
    // We now have a dedicated location column, but we'll keep it in description for backward compatibility if needed, 
    // or just store it separately. Let's store separately.

    $terminalName = '';
    if ($terminalId > 0) {
        $st = $db->prepare("SELECT name FROM terminals WHERE id=? LIMIT 1");
        if ($st) {
            $st->bind_param('i', $terminalId);
            $st->execute();
            $rr = $st->get_result()->fetch_assoc();
            $st->close();
            $terminalName = trim((string)($rr['name'] ?? ''));
        }
    }
    if ($location === '' && $terminalName !== '') {
        $location = $terminalName;
    }

    if ($isLoggedIn) {
        $userIdInt = (int)$userId;
        $stmt = $db->prepare("INSERT INTO commuter_complaints (ref_number, user_id, complaint_type, description, media_path, ai_tags, route_id, plate_number, location, terminal_id, device_id, ip_address, contact_email, contact_phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            echo json_encode(['ok' => false, 'error' => 'Database error']);
            exit;
        }
        $stmt->bind_param('sissssssisssss', $ref, $userIdInt, $type, $desc, $mediaPath, $aiTagsStr, $routeId, $plate, $location, $terminalId, $deviceId, $remoteIp, $contactEmail, $contactPhone);
    } else {
        $stmt = $db->prepare("INSERT INTO commuter_complaints (ref_number, complaint_type, description, media_path, ai_tags, route_id, plate_number, location, terminal_id, device_id, ip_address, contact_email, contact_phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            echo json_encode(['ok' => false, 'error' => 'Database error']);
            exit;
        }
        $stmt->bind_param('ssssssssisssss', $ref, $type, $desc, $mediaPath, $aiTagsStr, $routeId, $plate, $location, $terminalId, $deviceId, $remoteIp, $contactEmail, $contactPhone);
    }

    if ($stmt->execute()) {
        echo json_encode(['ok' => true, 'ref_number' => $ref, 'ai_tags' => $aiTags]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Database error']);
    }
    exit;
}

if ($action === 'get_complaint_status') {
    if (!$isLoggedIn) {
        echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
        exit;
    }
    $ref = $_GET['ref_number'] ?? '';
    if (!$ref) {
        echo json_encode(['ok' => false, 'error' => 'Reference number is required']);
        exit;
    }

    $uid = (int) $userId;
    $stmt = $db->prepare("SELECT c.ref_number, c.status, c.created_at, c.description, c.complaint_type, c.route_id, c.plate_number, c.location, c.media_path, c.terminal_id, t.name AS terminal_name
                          FROM commuter_complaints c
                          LEFT JOIN terminals t ON t.id=c.terminal_id
                          WHERE c.ref_number = ? AND c.user_id = ?");
    $stmt->bind_param('si', $ref, $uid);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        echo json_encode(['ok' => true, 'data' => $row]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Complaint not found']);
    }
    exit;
}

if ($action === 'deactivate_account') {
    if (!$isLoggedIn) {
        echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
        exit;
    }

    $uid = (int)$userId;
    // Update status to Inactive
    $stmt = $db->prepare("UPDATE rbac_users SET status='Inactive' WHERE id=?");
    if (!$stmt) {
        echo json_encode(['ok' => false, 'error' => 'Database error']);
        exit;
    }
    $stmt->bind_param('i', $uid);
    if ($stmt->execute()) {
        // Log out
        $_SESSION = [];
        @session_unset();
        @session_destroy();
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Failed to deactivate account']);
    }
    exit;
}

if ($action === 'get_my_complaints') {
    if (!$isLoggedIn) {
        echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
        exit;
    }

    $stmt = $db->prepare("SELECT c.ref_number, c.complaint_type, c.status, c.created_at, c.description, c.route_id, c.plate_number, c.location, c.media_path, c.terminal_id, t.name AS terminal_name
                          FROM commuter_complaints c
                          LEFT JOIN terminals t ON t.id=c.terminal_id
                          WHERE c.user_id = ?
                          ORDER BY c.created_at DESC");
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

if ($action === 'get_profile') {
    if (!$isLoggedIn) {
        echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
        exit;
    }
    $stmt = $db->prepare("SELECT u.first_name, u.last_name, u.email, p.mobile, p.house_number, p.street, p.barangay, p.address_line
                          FROM rbac_users u
                          LEFT JOIN user_profiles p ON p.user_id = u.id
                          WHERE u.id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $data = $res->fetch_assoc();
    echo json_encode(['ok' => true, 'data' => $data]);
    exit;
}

if ($action === 'update_profile') {
    if (!$isLoggedIn) {
        echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
        exit;
    }

    $fname = trim($input['first_name'] ?? '');
    $lname = trim($input['last_name'] ?? '');
    $mobile = trim($input['mobile'] ?? '');
    $house = trim($input['house_number'] ?? '');
    $street = trim($input['street'] ?? '');
    $brgy = trim($input['barangay'] ?? '');
    $password = $input['password'] ?? '';
    $confirm = $input['confirm_password'] ?? '';

    if ($fname === '' || $lname === '') {
        echo json_encode(['ok' => false, 'error' => 'First and Last Name are required']);
        exit;
    }

    if ($password !== '' && $password !== $confirm) {
        echo json_encode(['ok' => false, 'error' => 'Passwords do not match']);
        exit;
    }

    $db->begin_transaction();
    try {
        // Update User
        if ($password !== '') {
            if (strlen($password) < 6) throw new Exception("Password must be at least 6 characters");
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE rbac_users SET first_name=?, last_name=?, password_hash=? WHERE id=?");
            $stmt->bind_param('sssi', $fname, $lname, $hash, $userId);
        } else {
            $stmt = $db->prepare("UPDATE rbac_users SET first_name=?, last_name=? WHERE id=?");
            $stmt->bind_param('ssi', $fname, $lname, $userId);
        }
        if (!$stmt->execute()) throw new Exception("Failed to update user info");

        // Update Profile
        // Construct address_line for backward compatibility/display
        $addressLine = trim("$house $street, $brgy", " ,");
        
        $stmt = $db->prepare("INSERT INTO user_profiles (user_id, mobile, house_number, street, barangay, address_line) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE mobile=?, house_number=?, street=?, barangay=?, address_line=?");
        $stmt->bind_param('issssssssss', $userId, $mobile, $house, $street, $brgy, $addressLine, $mobile, $house, $street, $brgy, $addressLine);
        if (!$stmt->execute()) throw new Exception("Failed to update profile details");

        $db->commit();

        // Update Session
        $_SESSION['name'] = "$fname $lname";

        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        $db->rollback();
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Invalid action']);
?>
