<?php
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
