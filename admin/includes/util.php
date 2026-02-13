<?php
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle): bool {
        $haystack = (string)$haystack;
        $needle = (string)$needle;
        if ($needle === '') return true;
        return strpos($haystack, $needle) !== false;
    }
}

function log_msg($msg) {
    if (php_sapi_name() === 'cli') {
        echo $msg;
    }
}
function json_response($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
}
function error_response($code, $error, $extra = []) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(array_merge(['ok' => false, 'error' => $error], $extra));
    if (!defined('TMM_TEST')) {
        exit;
    }
}
function assert_true($condition, $message) {
    if ($condition) {
        echo "[PASS] $message\n";
    } else {
        echo "[FAIL] $message\n";
        exit(1);
    }
}
function get_route_capacity($db, $route_id) {
    $stmtL = $db->prepare("SELECT max_vehicle_limit FROM routes WHERE route_id=?");
    $stmtL->bind_param('s', $route_id);
    $stmtL->execute();
    $row = $stmtL->get_result()->fetch_assoc();
    if (!$row) { return ['limit' => 0, 'count' => 0]; }
    $lim = (int)($row['max_vehicle_limit'] ?? 0);
    $stmtC = $db->prepare("SELECT COUNT(*) AS c FROM terminal_assignments WHERE route_id=? AND status='Authorized'");
    $stmtC->bind_param('s', $route_id);
    $stmtC->execute();
    $cnt = (int)($stmtC->get_result()->fetch_assoc()['c'] ?? 0);
    return ['limit' => $lim, 'count' => $cnt];
}

function tmm_root_url_from_script(): string {
    $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($scriptName === '') return '';
    $pos = strpos($scriptName, '/admin/');
    if ($pos !== false) {
        $root = substr($scriptName, 0, $pos);
        return $root === '/' ? '' : rtrim($root, '/');
    }
    $dir = str_replace('\\', '/', (string)dirname($scriptName));
    $dir = $dir === '/' ? '' : rtrim($dir, '/');
    return $dir;
}

function tmm_public_base_url(): string {
    $override = trim((string)getenv('TMM_PUBLIC_BASE_URL'));
    if ($override !== '') return rtrim($override, '/');
    $https = (string)($_SERVER['HTTPS'] ?? '');
    $isHttps = ($https !== '' && strtolower($https) !== 'off') || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443');
    $scheme = $isHttps ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? ''));
    $host = trim($host);
    if ($host === '') return '';
    $root = tmm_root_url_from_script();
    return $scheme . '://' . $host . $root;
}

function tmm_table_exists(mysqli $db, string $table): bool {
    $table = trim($table);
    if ($table === '') return false;
    $safe = $db->real_escape_string($table);
    $res = $db->query("SHOW TABLES LIKE '{$safe}'");
    return $res && $res->num_rows > 0;
}

function tmm_audit_event(mysqli $db, string $action, string $entityType = '', string $entityKey = '', array $meta = []): void {
    $action = trim($action);
    if ($action === '') return;
    $entityType = trim($entityType);
    $entityKey = trim($entityKey);
    $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    $ua = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $email = trim((string)($_SESSION['email'] ?? ($_SESSION['user_email'] ?? '')));
    $role = trim((string)($_SESSION['role'] ?? ($_SESSION['user_role'] ?? '')));
    $metaJson = '';
    if ($meta) {
        $metaJson = json_encode($meta, JSON_UNESCAPED_SLASHES);
        if ($metaJson === false) $metaJson = '';
        if (strlen($metaJson) > 20000) $metaJson = substr($metaJson, 0, 20000);
    }

    $stmt = $db->prepare("INSERT INTO audit_events (event_time, actor_user_id, actor_email, actor_role, action, entity_type, entity_key, ip_address, user_agent, meta_json)
                          VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) return;
    $stmt->bind_param('issssssss', $userId, $email, $role, $action, $entityType, $entityKey, $ip, $ua, $metaJson);
    $stmt->execute();
    $stmt->close();
}
