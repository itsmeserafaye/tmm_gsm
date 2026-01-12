<?php
function tmm_scan_file_for_viruses($path) {
  if (!is_file($path)) return true;
  $scanner = getenv('TMM_AV_SCANNER');
  if (!$scanner) return true;
  $cmd = escapeshellcmd($scanner) . ' ' . escapeshellarg($path);
  $output = [];
  $code = 0;
  @exec($cmd, $output, $code);
  if ($code === 0) return true;
  return false;
}

