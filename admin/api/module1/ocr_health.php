<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../../includes/env.php';

header('Content-Type: application/json');

function ocr_health_send(bool $ok, array $data = [], int $code = 200): void {
  http_response_code($code);
  echo json_encode(['ok' => $ok, 'data' => $data]);
  exit;
}

function ocr_health_tesseract_path(): string {
  $fromEnv = trim((string)getenv('TMM_TESSERACT_PATH'));
  if ($fromEnv !== '') return $fromEnv;
  $root = realpath(__DIR__ . '/../../../');
  if ($root) {
    $win = $root . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'tesseract' . DIRECTORY_SEPARATOR . 'tesseract.exe';
    if (is_file($win)) return $win;
    $nix = $root . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'tesseract' . DIRECTORY_SEPARATOR . 'tesseract';
    if (is_file($nix)) return $nix;
  }
  return 'tesseract';
}

try {
  tmm_load_env(__DIR__ . '/../../../.env');
  $db = db();
  require_permission('module1.vehicles.write');

  $shellExecOk = function_exists('shell_exec') && is_callable('shell_exec');
  $disabled = (string)ini_get('disable_functions');
  $blocked = false;
  if ($disabled !== '' && stripos($disabled, 'shell_exec') !== false) $blocked = true;

  $bin = ocr_health_tesseract_path();
  $cmd = '"' . str_replace('"', '\"', $bin) . '" --version 2>&1';
  $out = $shellExecOk && !$blocked ? @shell_exec($cmd) : null;
  $out = is_string($out) ? trim($out) : '';

  $data = [
    'shell_exec_available' => $shellExecOk && !$blocked,
    'tesseract_path' => $bin,
    'tesseract_version' => $out !== '' ? strtok($out, "\n") : '',
    'hint' => $out === '' ? 'Upload portable Tesseract to bin/tesseract and ensure shell_exec is allowed.' : '',
  ];

  ocr_health_send(true, $data);
} catch (Throwable $e) {
  ocr_health_send(false, ['error' => 'server_error'], 500);
}

