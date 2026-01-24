<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../../includes/env.php';

header('Content-Type: application/json');

function ocr_send(bool $ok, string $message, array $data = [], int $code = 200): void {
  http_response_code($code);
  echo json_encode(['ok' => $ok, 'message' => $message, 'data' => $data]);
  exit;
}

function ocr_norm_text(string $s): string {
  $s = preg_replace("/[\\t\\r]+/", " ", $s);
  $s = preg_replace("/[ ]{2,}/", " ", $s);
  return trim($s ?? '');
}

function ocr_extract_plate(string $text): ?string {
  $candidates = [];
  if (preg_match_all('/\\b([A-Z]{3})\\s*\\-?\\s*(\\d{3,4})\\b/', $text, $m, PREG_SET_ORDER)) {
    foreach ($m as $mm) {
      $candidates[] = strtoupper($mm[1] . '-' . $mm[2]);
    }
  }
  foreach ($candidates as $p) {
    if (preg_match('/^[A-Z]{3}\\-[0-9]{3,4}$/', $p)) return $p;
  }
  return $candidates ? $candidates[0] : null;
}

function ocr_extract_by_keywords(string $text, array $keys, string $pattern): ?string {
  foreach ($keys as $k) {
    $rx = '/(?:' . $k . ')\\s*[:\\-]?\\s*' . $pattern . '/i';
    if (preg_match($rx, $text, $m)) {
      $v = trim((string)($m[1] ?? ''));
      if ($v !== '') return $v;
    }
  }
  return null;
}

function ocr_parse_date_to_ymd(?string $raw): ?string {
  if (!is_string($raw)) return null;
  $s = strtoupper(trim($raw));
  if ($s === '') return null;
  $s = preg_replace('/[^0-9\/\-\.\s]/', '', $s);
  $s = trim((string)$s);
  if ($s === '') return null;

  if (preg_match('/\b(\d{4})\s*[\-\/\.]\s*(\d{1,2})\s*[\-\/\.]\s*(\d{1,2})\b/', $s, $m)) {
    $y = (int)$m[1]; $mo = (int)$m[2]; $d = (int)$m[3];
    if (checkdate($mo, $d, $y)) return sprintf('%04d-%02d-%02d', $y, $mo, $d);
  }
  if (preg_match('/\b(\d{1,2})\s*[\-\/\.]\s*(\d{1,2})\s*[\-\/\.]\s*(\d{4})\b/', $s, $m)) {
    $a = (int)$m[1]; $b = (int)$m[2]; $y = (int)$m[3];
    $mo = $a; $d = $b;
    if (!checkdate($mo, $d, $y) && checkdate($b, $a, $y)) { $mo = $b; $d = $a; }
    if (checkdate($mo, $d, $y)) return sprintf('%04d-%02d-%02d', $y, $mo, $d);
  }
  return null;
}

function ocr_extract_fields(string $raw): array {
  $t = strtoupper($raw);
  $t = str_replace(["\n", "\r"], " ", $t);
  $t = preg_replace("/[ ]{2,}/", " ", $t);

  $plate = ocr_extract_plate($t);
  $engine = ocr_extract_by_keywords($t, ['ENGINE\\s*NO', 'ENGINE\\s*NUMBER', 'ENGINE'], '([A-Z0-9\\-]{5,20})');
  $chassis = ocr_extract_by_keywords($t, ['CHASSIS\\s*NO', 'CHASSIS\\s*NUMBER', 'CHASSIS', 'VIN'], '([A-HJ-NPR-Z0-9]{17})');
  $make = ocr_extract_by_keywords($t, ['MAKE'], '([A-Z0-9\\- ]{2,30})');
  $series = ocr_extract_by_keywords($t, ['SERIES', 'MODEL'], '([A-Z0-9\\- ]{2,30})');
  $year = ocr_extract_by_keywords($t, ['YEAR\\s*MODEL', 'YEAR'], '([0-9]{4})');
  $fuel = ocr_extract_by_keywords($t, ['FUEL', 'FUEL\\s*TYPE'], '([A-Z]{3,12})');
  $color = ocr_extract_by_keywords($t, ['COLOR', 'COLOUR'], '([A-Z ]{3,20})');

  $crNo = ocr_extract_by_keywords($t, ['CR\\s*NO', 'CERTIFICATE\\s*OF\\s*REGISTRATION\\s*NO', 'CERTIFICATE\\s*NO', 'REGISTRATION\\s*NO'], '([A-Z0-9\\-\\/]{4,40})');
  $crIssueRaw = ocr_extract_by_keywords($t, ['CR\\s*ISSUE\\s*DATE', 'DATE\\s*ISSUED', 'ISSUE\\s*DATE', 'DATE\\s*OF\\s*ISSUE'], '([0-9\\-\\/\\. ]{8,16})');
  $crIssueDate = ocr_parse_date_to_ymd($crIssueRaw);
  $owner = ocr_extract_by_keywords($t, ['REGISTERED\\s*OWNER', 'OWNER\\s*NAME', 'OWNER'], '([A-Z0-9\\-\\., ]{5,80})');

  $out = [
    'plate_no' => $plate ? $plate : null,
    'engine_no' => $engine ? $engine : null,
    'chassis_no' => $chassis ? $chassis : null,
    'make' => $make ? trim($make) : null,
    'model' => $series ? trim($series) : null,
    'year_model' => $year ? $year : null,
    'fuel_type' => $fuel ? $fuel : null,
    'color' => $color ? trim($color) : null,
    'cr_number' => $crNo ? trim($crNo) : null,
    'cr_issue_date' => $crIssueDate,
    'registered_owner' => $owner ? trim($owner) : null,
  ];

  foreach ($out as $k => $v) {
    if (!is_string($v) || trim($v) === '') $out[$k] = null;
  }
  return $out;
}

function ocr_find_tesseract_path(): string {
  $fromEnv = trim((string)getenv('TMM_TESSERACT_PATH'));
  if ($fromEnv !== '') return $fromEnv;
  return 'tesseract';
}

function ocr_run_tesseract(string $inputPath): array {
  $bin = ocr_find_tesseract_path();
  $cmd = '"' . str_replace('"', '\"', $bin) . '" "' . str_replace('"', '\"', $inputPath) . '" stdout -l eng --psm 6 2>&1';
  $out = @shell_exec($cmd);
  $out = is_string($out) ? $out : '';
  $outTrim = trim($out);
  if ($outTrim === '') return ['ok' => false, 'text' => '', 'error' => 'ocr_empty_output'];
  if (stripos($outTrim, 'not recognized') !== false || stripos($outTrim, 'No such file') !== false) {
    return ['ok' => false, 'text' => '', 'error' => 'tesseract_not_found'];
  }
  return ['ok' => true, 'text' => $outTrim, 'error' => ''];
}

try {
  tmm_load_env(__DIR__ . '/../../../.env');
  $db = db();
  require_permission('module1.vehicles.write');

  if ($_SERVER['REQUEST_METHOD'] !== 'POST') ocr_send(false, 'Method not allowed', [], 405);

  if (!isset($_FILES['cr']) || !is_array($_FILES['cr'])) ocr_send(false, 'CR file is required', [], 400);
  if ((int)($_FILES['cr']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) ocr_send(false, 'CR upload failed', [], 400);

  $ext = strtolower(pathinfo((string)($_FILES['cr']['name'] ?? ''), PATHINFO_EXTENSION));
  if (!in_array($ext, ['jpg','jpeg','png','pdf'], true)) ocr_send(false, 'Invalid file type', [], 400);

  $tmpDir = sys_get_temp_dir();
  $tmpName = 'tmm_cr_' . bin2hex(random_bytes(6)) . '.' . $ext;
  $tmpPath = rtrim($tmpDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $tmpName;
  if (!move_uploaded_file((string)$_FILES['cr']['tmp_name'], $tmpPath)) ocr_send(false, 'Failed to read upload', [], 400);

  $safe = tmm_scan_file_for_viruses($tmpPath);
  if (!$safe) {
    if (is_file($tmpPath)) @unlink($tmpPath);
    ocr_send(false, 'File failed security scan', [], 400);
  }

  $engine = strtolower(trim((string)getenv('TMM_OCR_ENGINE')));
  if ($engine === '') $engine = 'tesseract';

  if ($engine !== 'tesseract') {
    if (is_file($tmpPath)) @unlink($tmpPath);
    ocr_send(false, 'OCR engine not configured', ['engine' => $engine], 400);
  }

  $r = ocr_run_tesseract($tmpPath);
  if (is_file($tmpPath)) @unlink($tmpPath);

  if (!$r['ok']) {
    ocr_send(false, 'OCR failed', ['error' => $r['error']], 400);
  }

  $raw = ocr_norm_text((string)$r['text']);
  $fields = ocr_extract_fields($raw);

  ocr_send(true, 'ok', [
    'engine' => 'tesseract',
    'raw_text_preview' => substr($raw, 0, 800),
    'fields' => $fields
  ]);
} catch (Throwable $e) {
  ocr_send(false, 'OCR failed', ['error' => 'server_error'], 500);
}
