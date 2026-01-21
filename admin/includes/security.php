<?php
function tmm_scan_file_for_viruses($path) {
  if (!is_file($path)) return true;
  $scanner = getenv('TMM_AV_SCANNER');
  if (!$scanner) return true;
  if (!function_exists('exec')) return true;
  $scanner = trim((string)$scanner);
  if ($scanner === '') return true;
  if (DIRECTORY_SEPARATOR === '\\' && is_file($scanner) === false && strpos($scanner, '\\') !== false) {
    return true;
  }
  $cmd = (DIRECTORY_SEPARATOR === '\\')
    ? ('"' . str_replace('"', '\"', $scanner) . '" ' . escapeshellarg($path))
    : (escapeshellcmd($scanner) . ' ' . escapeshellarg($path));
  $output = [];
  $code = 0;
  @exec($cmd, $output, $code);
  if ($code === 0) return true;
  $out = strtolower(trim(implode("\n", (array)$output)));
  if ($out !== '' && (str_contains($out, 'not recognized') || str_contains($out, 'not found'))) return true;
  if (in_array($code, [127, 9009], true)) return true;
  return false;
}
