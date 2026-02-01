<?php
function tmm_load_env(string $path): void {
  static $loaded = false;
  if ($loaded) return;

  if (!is_file($path)) return;
  $lines = @file($path, FILE_IGNORE_NEW_LINES);
  if (!is_array($lines)) return;
  $loaded = true;

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

    $existing = getenv($key);
    if ($existing !== false && (string)$existing !== '') continue;

    putenv($key . '=' . $value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
  }
}

function tmm_load_env_default(): void {
  $fromEnv = trim((string)getenv('TMM_ENV_FILE'));
  if ($fromEnv !== '') {
    tmm_load_env($fromEnv);
    if (trim((string)getenv('TMM_DB_HOST')) !== '' || trim((string)getenv('TMM_SMTP_HOST')) !== '') return;
  }

  $projectRoot = realpath(__DIR__ . '/..');
  if ($projectRoot !== false) {
    tmm_load_env($projectRoot . '/.env');
    if (trim((string)getenv('TMM_DB_HOST')) !== '' || trim((string)getenv('TMM_SMTP_HOST')) !== '') return;

    $parent = realpath($projectRoot . '/..');
    if ($parent !== false) {
      tmm_load_env($parent . '/.env');
    }
  }
}

