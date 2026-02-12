<?php
function tmm_import_normalize_header(string $h): string {
  $h = preg_replace('/^\xEF\xBB\xBF/', '', $h);
  $h = trim($h);
  $h = strtolower($h);
  $h = preg_replace('/[^a-z0-9]+/', '_', $h);
  $h = trim($h, '_');
  return $h;
}

function tmm_import_get_uploaded_csv(string $field = 'file'): array {
  if (!isset($_FILES[$field])) return [null, 'missing_file'];
  $f = $_FILES[$field];
  if (!is_array($f)) return [null, 'invalid_file'];
  if (($f['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) return [null, 'upload_error'];
  $tmp = (string)($f['tmp_name'] ?? '');
  if ($tmp === '' || !is_uploaded_file($tmp)) return [null, 'invalid_upload'];
  return [$tmp, null];
}

function tmm_import_read_csv(string $tmpPath, int $maxRows = 20000): array {
  $fh = fopen($tmpPath, 'r');
  if (!$fh) return [null, null, 'open_failed'];
  $headersRaw = fgetcsv($fh);
  if (!$headersRaw || !is_array($headersRaw)) { fclose($fh); return [null, null, 'missing_header']; }
  $headers = [];
  foreach ($headersRaw as $h) $headers[] = tmm_import_normalize_header((string)$h);
  $rows = [];
  $i = 0;
  while (($line = fgetcsv($fh)) !== false) {
    if (!is_array($line)) continue;
    $row = [];
    foreach ($headers as $idx => $key) {
      if ($key === '') continue;
      $row[$key] = isset($line[$idx]) ? trim((string)$line[$idx]) : '';
    }
    $rows[] = $row;
    $i++;
    if ($i >= $maxRows) break;
  }
  fclose($fh);
  return [$headers, $rows, null];
}

