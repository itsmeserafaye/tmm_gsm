<?php
function tmm_export_format(): string {
  $f = strtolower(trim((string)($_GET['format'] ?? 'csv')));
  if ($f === 'xls' || $f === 'excel' || $f === 'xlsx') return 'excel';
  return 'csv';
}

function tmm_send_export_headers(string $format, string $basename): void {
  $basename = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $basename);
  if ($basename === '') $basename = 'export';
  if ($format === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $basename . '.xls"');
  } else {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $basename . '.csv"');
  }
  header('Pragma: no-cache');
  header('Expires: 0');
}

function tmm_export_escape_excel(string $v): string {
  return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function tmm_export_write_csv(array $headers, iterable $rows): void {
  $out = fopen('php://output', 'w');
  fputcsv($out, $headers);
  foreach ($rows as $row) {
    $line = [];
    foreach ($headers as $k) $line[] = $row[$k] ?? '';
    fputcsv($out, $line);
  }
  fclose($out);
}

function tmm_export_write_excel(array $headers, iterable $rows): void {
  echo "<table border=\"1\">";
  echo "<tr>";
  foreach ($headers as $h) echo "<th>" . tmm_export_escape_excel((string)$h) . "</th>";
  echo "</tr>";
  foreach ($rows as $row) {
    echo "<tr>";
    foreach ($headers as $k) echo "<td>" . tmm_export_escape_excel((string)($row[$k] ?? '')) . "</td>";
    echo "</tr>";
  }
  echo "</table>";
}

function tmm_export_from_result(string $format, array $headers, mysqli_result $res, callable $rowMap): void {
  if ($format === 'excel') {
    echo "<table border=\"1\">";
    echo "<tr>";
    foreach ($headers as $h) echo "<th>" . tmm_export_escape_excel((string)$h) . "</th>";
    echo "</tr>";
    while ($r = $res->fetch_assoc()) {
      $row = $rowMap($r);
      echo "<tr>";
      foreach ($headers as $k) echo "<td>" . tmm_export_escape_excel((string)($row[$k] ?? '')) . "</td>";
      echo "</tr>";
    }
    echo "</table>";
    return;
  }

  $out = fopen('php://output', 'w');
  fputcsv($out, $headers);
  while ($r = $res->fetch_assoc()) {
    $row = $rowMap($r);
    $line = [];
    foreach ($headers as $k) $line[] = $row[$k] ?? '';
    fputcsv($out, $line);
  }
  fclose($out);
}
