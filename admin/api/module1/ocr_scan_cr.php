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

function ocr_norm_engine(string $s): string {
  $s = strtoupper($s);
  $s = preg_replace('/[^A-Z0-9\\-]/', '', $s);
  $s = preg_replace('/\\-+/', '-', $s);
  return trim($s ?? '');
}

function ocr_norm_vin(string $s): string {
  $s = strtoupper($s);
  $s = preg_replace('/[^A-Z0-9]/', '', $s);
  $s = strtr($s, ['O' => '0', 'I' => '1', 'Q' => '0']);
  return trim($s ?? '');
}

function ocr_extract_by_keywords(string $text, array $keys, string $pattern): ?string {
  foreach ($keys as $k) {
    $rx = '/(?:' . $k . ')\\s*(?:[:\\-\\|]|\\s)\\s*' . $pattern . '/i';
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
  $months = [
    'JANUARY' => 1, 'JAN' => 1,
    'FEBRUARY' => 2, 'FEB' => 2,
    'MARCH' => 3, 'MAR' => 3,
    'APRIL' => 4, 'APR' => 4,
    'MAY' => 5,
    'JUNE' => 6, 'JUN' => 6,
    'JULY' => 7, 'JUL' => 7,
    'AUGUST' => 8, 'AUG' => 8,
    'SEPTEMBER' => 9, 'SEP' => 9, 'SEPT' => 9,
    'OCTOBER' => 10, 'OCT' => 10,
    'NOVEMBER' => 11, 'NOV' => 11,
    'DECEMBER' => 12, 'DEC' => 12,
  ];
  $s2 = strtoupper(trim((string)$raw));
  $s2 = preg_replace('/\s+/', ' ', $s2);
  if (preg_match('/\b([A-Z]{3,9})\s+(\d{1,2}),\s*(\d{4})\b/', $s2, $m)) {
    $mo = $months[$m[1]] ?? 0;
    $d = (int)$m[2];
    $y = (int)$m[3];
    if ($mo > 0 && checkdate($mo, $d, $y)) return sprintf('%04d-%02d-%02d', $y, $mo, $d);
  }
  if (preg_match('/\b(\d{1,2})\s+([A-Z]{3,9})\s+(\d{4})\b/', $s2, $m)) {
    $d = (int)$m[1];
    $mo = $months[$m[2]] ?? 0;
    $y = (int)$m[3];
    if ($mo > 0 && checkdate($mo, $d, $y)) return sprintf('%04d-%02d-%02d', $y, $mo, $d);
  }
  return null;
}

function ocr_extract_fields(string $raw): array {
  $rawUp = strtoupper($raw);
  $lines = preg_split("/\\r?\\n+/", $rawUp) ?: [];
  $lines = array_values(array_filter(array_map(function ($ln) {
    $ln = preg_replace("/[\\t\\r]+/", " ", (string)$ln);
    $ln = preg_replace("/[ ]{2,}/", " ", $ln);
    return trim($ln ?? '');
  }, $lines), function ($ln) { return $ln !== ''; }));

  $textFlat = preg_replace("/\\s+/", " ", $rawUp);

  $extractLineValue = function (array $keys) use ($lines): ?string {
    foreach ($lines as $ln) {
      foreach ($keys as $k) {
        if (!preg_match('/\\b' . $k . '\\b/i', $ln)) continue;
        $v = preg_replace('/^.*?\\b' . $k . '\\b\\s*(?:[:\\-\\|]|\\s)\\s*/i', '', $ln);
        $v = trim((string)$v);
        if ($v !== '' && strlen($v) <= 140) return $v;
      }
    }
    return null;
  };

  $plateLine = $extractLineValue(['PLATE\\s*NUMBER', 'PLATE\\s*NO', 'PLATE']);
  $plate = ocr_extract_plate($plateLine ? $plateLine : $textFlat);

  $engineLine = $extractLineValue(['ENGINE\\s*NUMBER', 'ENGINE\\s*NO', 'ENGINE\\s*#', 'ENGINE\\s*NUM']);
  $engine = $engineLine ? ocr_norm_engine($engineLine) : null;
  if ($engine !== null) {
    $engine = preg_replace('/(CHASSIS|VIN|NUMBER|MODEL|YEAR|FUEL|COLOR|OWNER).*$/', '', $engine);
    $engine = trim((string)$engine);
  }

  $chassisLine = $extractLineValue(['CHASSIS\\s*NUMBER', 'CHASSIS\\s*NO', 'CHASSIS\\s*#', 'VIN']);
  $vin = $chassisLine ? ocr_norm_vin($chassisLine) : '';
  if ($vin === '' || strlen($vin) < 17) {
    $cand = ocr_norm_vin($textFlat);
    if (preg_match('/[A-HJ-NPR-Z0-9]{17}/', $cand, $m)) $vin = (string)$m[0];
  }
  $chassis = ($vin !== '' && strlen($vin) === 17) ? $vin : null;

  $make = $extractLineValue(['MAKE']);
  if ($make) {
    $make = preg_replace('/\\b(MODEL|YEAR|FUEL|COLOR|ENGINE|CHASSIS|OWNER)\\b.*$/', '', $make);
    $make = trim((string)$make);
  }
  $series = $extractLineValue(['MODEL', 'SERIES']);
  if ($series) {
    $series = preg_replace('/\\b(YEAR|FUEL|COLOR|ENGINE|CHASSIS|OWNER)\\b.*$/', '', $series);
    $series = trim((string)$series);
  }

  $year = $extractLineValue(['YEAR\\s*MODEL']);
  if (!$year) $year = ocr_extract_by_keywords($textFlat, ['YEAR\\s*MODEL', 'YEAR'], '([0-9]{4})');
  $fuel = $extractLineValue(['FUEL\\s*TYPE', 'FUEL']);
  if ($fuel) {
    $fuel = preg_replace('/\\b(TYPE|COLOR|ENGINE|CHASSIS|OWNER)\\b.*$/', '', $fuel);
    $fuel = trim((string)$fuel);
  }
  $color = $extractLineValue(['COLOR', 'COLOUR']);
  if ($color) {
    $color = preg_replace('/\\b(ENGINE|CHASSIS|OWNER)\\b.*$/', '', $color);
    $color = trim((string)$color);
  }

  $crNo = $extractLineValue(['CR\\s*NUMBER', 'CR\\s*NO', 'CERTIFICATE\\s*OF\\s*REGISTRATION\\s*NO', 'CERTIFICATE\\s*NO', 'REGISTRATION\\s*NO']);
  if (!$crNo) $crNo = ocr_extract_by_keywords($textFlat, ['CR\\s*NUMBER', 'CR\\s*NO'], '([A-Z0-9\\-\\/]{4,40})');
  $crIssueRaw = $extractLineValue(['CR\\s*ISSUE\\s*DATE', 'CR\\s*DATE', 'DATE\\s*ISSUED', 'ISSUE\\s*DATE', 'DATE\\s*OF\\s*ISSUE']);
  if (!$crIssueRaw) $crIssueRaw = ocr_extract_by_keywords($textFlat, ['CR\\s*ISSUE\\s*DATE', 'CR\\s*DATE', 'DATE\\s*ISSUED'], '([A-Z0-9,\\-\\/\\. ]{8,24})');
  $crIssueDate = ocr_parse_date_to_ymd($crIssueRaw);
  $owner = $extractLineValue(['REGISTERED\\s*OWNER']);
  if (!$owner) $owner = $extractLineValue(['OWNER\\s*NAME']);
  if ($owner) {
    $owner = preg_replace('/\\b(OWNER\\s*ADDRESS|ADDRESS|NOTE)\\b.*$/', '', $owner);
    $owner = trim((string)$owner);
  }

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
  $root = realpath(__DIR__ . '/../../../');
  if ($root) {
    $win = $root . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'tesseract' . DIRECTORY_SEPARATOR . 'tesseract.exe';
    if (is_file($win)) return $win;
    $nix = $root . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'tesseract' . DIRECTORY_SEPARATOR . 'tesseract';
    if (is_file($nix)) return $nix;
  }
  return 'tesseract';
}

function ocr_find_tessdata_dir(): string {
  $fromEnv = trim((string)getenv('TMM_TESSDATA_DIR'));
  if ($fromEnv !== '') return $fromEnv;
  $root = realpath(__DIR__ . '/../../../');
  if ($root) {
    $t = $root . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'tesseract' . DIRECTORY_SEPARATOR . 'tessdata';
    if (is_dir($t) && is_file($t . DIRECTORY_SEPARATOR . 'eng.traineddata')) return $t;
  }
  $prefix = trim((string)getenv('TESSDATA_PREFIX'));
  if ($prefix !== '') {
    $guess = rtrim($prefix, "/\\") . DIRECTORY_SEPARATOR . 'tessdata';
    return $guess;
  }
  return '';
}

function ocr_run_tesseract(string $inputPath, int $psm): array {
  $bin = ocr_find_tesseract_path();
  $tessdata = ocr_find_tessdata_dir();
  $tessArg = '';
  if ($tessdata !== '') {
    $tessArg = ' --tessdata-dir "' . str_replace('"', '\"', $tessdata) . '"';
  }
  $psm = $psm > 0 ? $psm : 6;
  $cmd = '"' . str_replace('"', '\"', $bin) . '" "' . str_replace('"', '\"', $inputPath) . '" stdout -l eng --oem 1 --psm ' . (int)$psm . ' -c preserve_interword_spaces=1' . $tessArg . ' 2>&1';
  $out = @shell_exec($cmd);
  $out = is_string($out) ? $out : '';
  $outTrim = trim($out);
  if ($outTrim === '') return ['ok' => false, 'text' => '', 'error' => 'ocr_empty_output'];
  if (stripos($outTrim, 'not recognized') !== false || stripos($outTrim, 'No such file') !== false) {
    return ['ok' => false, 'text' => '', 'error' => 'tesseract_not_found'];
  }
  if (preg_match('/\b(error|failed|cannot|could not|unable)\b/i', $outTrim) && !preg_match('/\b(PLATE|ENGINE|CHASSIS|CERTIFICATE|REGISTRATION|OWNER)\b/i', $outTrim)) {
    return ['ok' => false, 'text' => $outTrim, 'error' => 'tesseract_error'];
  }
  return ['ok' => true, 'text' => $outTrim, 'error' => '', 'psm' => $psm];
}

function ocr_count_filled(array $fields): int {
  $filled = 0;
  foreach ($fields as $v) {
    if (is_string($v) && trim($v) !== '') $filled++;
  }
  return $filled;
}

function ocr_best_tesseract(string $inputPath): array {
  $psms = [6, 4, 11, 3];
  $best = ['ok' => false, 'text' => '', 'error' => 'ocr_empty_output', 'psm' => 0];
  $bestScore = -1;
  foreach ($psms as $psm) {
    $r = ocr_run_tesseract($inputPath, $psm);
    if (!$r['ok']) {
      if (!$best['ok'] && $best['error'] === 'ocr_empty_output') $best = $r + ['psm' => $psm];
      continue;
    }
    $fields = ocr_extract_fields(ocr_norm_text((string)$r['text']));
    $score = ocr_count_filled($fields);
    if ($score > $bestScore) {
      $bestScore = $score;
      $best = $r + ['psm' => $psm];
    }
    if ($bestScore >= 3) break;
  }
  return $best;
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

  $r = ocr_best_tesseract($tmpPath);
  if (is_file($tmpPath)) @unlink($tmpPath);

  if (!$r['ok']) {
    $msg = $r['error'] === 'tesseract_error'
      ? 'OCR could not read this file. Try uploading a clear CR image (JPG/PNG).'
      : 'OCR failed';
    ocr_send(false, $msg, ['error' => $r['error'], 'raw_text_preview' => substr(ocr_norm_text((string)$r['text']), 0, 800)], 400);
  }

  $raw = ocr_norm_text((string)$r['text']);
  $fields = ocr_extract_fields($raw);
  $filled = ocr_count_filled($fields);
  if ($filled === 0) {
    ocr_send(false, 'No details were extracted. Try a clearer image or adjust OCR.', [
      'error' => 'no_fields_extracted',
      'raw_text_preview' => substr($raw, 0, 800),
      'fields' => $fields
    ], 400);
  }

  ocr_send(true, 'ok', [
    'engine' => 'tesseract',
    'psm' => (int)($r['psm'] ?? 0),
    'raw_text_preview' => substr($raw, 0, 800),
    'fields' => $fields
  ]);
} catch (Throwable $e) {
  ocr_send(false, 'OCR failed', ['error' => 'server_error'], 500);
}
