<?php
header('Content-Type: application/json');
require_once 'db.php';

$conn = db();

$ref = $_GET['ref'] ?? '';

if (empty($ref)) {
    echo json_encode(['success' => false, 'message' => 'Reference number is required']);
    exit;
}

$ref = $conn->real_escape_string($ref);

$sql = "SELECT reference_number, status, created_at, complaint_type FROM complaints WHERE reference_number = '$ref'";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $data = $result->fetch_assoc();
    echo json_encode(['success' => true, 'data' => $data]);
} else {
    echo json_encode(['success' => false, 'message' => 'Complaint not found']);
}
?>