<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

try {
  $db = db();
  require_role(['SuperAdmin']);

  $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
  $docKey = isset($_GET['doc_key']) ? trim((string)$_GET['doc_key']) : '';
  if ($userId <= 0 || $docKey === '') {
    http_response_code(400);
    echo 'Invalid parameters';
    exit;
  }

  $stmt = $db->prepare("SELECT file_path FROM operator_portal_documents WHERE user_id=? AND doc_key=? LIMIT 1");
  if (!$stmt) {
    http_response_code(500);
    echo 'Database error';
    exit;
  }
  $stmt->bind_param('is', $userId, $docKey);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$row) {
    http_response_code(404);
    echo 'Document not found';
    exit;
  }

  $filePath = trim((string)($row['file_path'] ?? ''));
  if ($filePath === '') {
    http_response_code(404);
    echo 'File path not set';
    exit;
  }

  $filePathNorm = str_replace('\\', '/', $filePath);
  if (preg_match('#^https?://#i', $filePathNorm)) {
    header('Location: ' . $filePathNorm, true, 302);
    exit;
  }

  $rootDir = dirname(__DIR__, 3);
  $fullPath = null;

  if (strpos($filePathNorm, 'gsm_login/') === 0) {
    $rel = substr($filePathNorm, strlen('gsm_login/'));
    $fullPath = $rootDir . DIRECTORY_SEPARATOR . 'gsm_login' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($rel, '/'));
  } elseif (strpos($filePathNorm, 'uploads/') === 0) {
    $fullPath = $rootDir . DIRECTORY_SEPARATOR . 'gsm_login' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $filePathNorm);
  } else {
    $fullPath = $rootDir . DIRECTORY_SEPARATOR . 'gsm_login' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($filePathNorm, '/'));
  }

  if (!$fullPath || !is_file($fullPath)) {
    http_response_code(404);
    echo 'File not found';
    exit;
  }

  $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
  $mime = 'application/octet-stream';
  if ($ext === 'pdf') {
    $mime = 'application/pdf';
  } elseif ($ext === 'jpg' || $ext === 'jpeg') {
    $mime = 'image/jpeg';
  } elseif ($ext === 'png') {
    $mime = 'image/png';
  } elseif ($ext === 'csv') {
    $mime = 'text/csv';
  } elseif ($ext === 'xls') {
    $mime = 'application/vnd.ms-excel';
  } elseif ($ext === 'xlsx') {
    $mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
  }

  if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) {
      $detected = finfo_file($finfo, $fullPath);
      finfo_close($finfo);
      if ($detected) $mime = $detected;
    }
  }

  header('Content-Type: ' . $mime);
  header('Content-Length: ' . (string)filesize($fullPath));
  header('X-Content-Type-Options: nosniff');
  header('Content-Disposition: inline; filename="' . basename($fullPath) . '"');

  readfile($fullPath);
} catch (Throwable $e) {
  http_response_code(500);
  echo 'Error loading document';
}

