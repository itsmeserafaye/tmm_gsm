<?php
function tmm_load_env(string $path): void {
  static $loaded = false;
  if ($loaded) return;
  $loaded = true;

  if (!is_file($path)) return;
  $lines = @file($path, FILE_IGNORE_NEW_LINES);
  if (!is_array($lines)) return;

  foreach ($lines as $line) {
    $line = trim((string)$line);
    if ($line === '') continue;
    if (str_starts_with($line, '#')) continue;
    $pos = strpos($line, '=');
    if ($pos === false) continue;

    $key = trim(substr($line, 0, $pos));
    if ($key === '') continue;
    $value = trim(substr($line, $pos + 1));

    if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
      $value = substr($value, 1, -1);
    }

    if (getenv($key) !== false) continue;

    putenv($key . '=' . $value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
  }
}

