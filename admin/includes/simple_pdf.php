<?php

function tmm_simple_pdf_bytes(array $lines, array $opts = []): string
{
  $pageWidth = (int)($opts['page_width'] ?? 595);
  $pageHeight = (int)($opts['page_height'] ?? 842);
  $marginLeft = (int)($opts['margin_left'] ?? 36);
  $startY = (int)($opts['start_y'] ?? 806);
  $leading = (int)($opts['leading'] ?? 10);
  $fontSize = (int)($opts['font_size'] ?? 9);
  $maxLines = (int)($opts['max_lines'] ?? 70);
  if ($maxLines <= 10) $maxLines = 70;

  $pages = [];
  $cur = [];
  foreach ($lines as $ln) {
    $cur[] = (string)$ln;
    if (count($cur) >= $maxLines) {
      $pages[] = $cur;
      $cur = [];
    }
  }
  if ($cur) $pages[] = $cur;
  if (!$pages) $pages[] = ['No records.'];

  $toWin1252 = function ($s) {
    $s = (string)$s;
    if (function_exists('iconv')) {
      $v = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $s);
      if ($v !== false && $v !== null) return $v;
    }
    return $s;
  };

  $pdfEsc = function ($s) use ($toWin1252) {
    $s = $toWin1252($s);
    $s = str_replace("\\", "\\\\", $s);
    $s = str_replace("(", "\\(", $s);
    $s = str_replace(")", "\\)", $s);
    $s = preg_replace("/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F]/", "", $s);
    return $s;
  };

  $objects = [];
  $addObj = function ($body) use (&$objects) {
    $objects[] = (string)$body;
    return count($objects);
  };

  $catalogId = $addObj('');
  $pagesId = $addObj('');
  $fontId = $addObj("<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>");

  $pageObjIds = [];
  foreach ($pages as $pageLines) {
    $content = "BT\n/F1 {$fontSize} Tf\n{$leading} TL\n1 0 0 1 {$marginLeft} {$startY} Tm\n";
    foreach ($pageLines as $ln) {
      $content .= "(" . $pdfEsc($ln) . ") Tj\nT*\n";
    }
    $content .= "ET\n";
    $contentObjId = $addObj("<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream");
    $pageObjId = $addObj("<< /Type /Page /Parent " . $pagesId . " 0 R /MediaBox [0 0 " . $pageWidth . " " . $pageHeight . "] /Resources << /Font << /F1 " . $fontId . " 0 R >> >> /Contents " . $contentObjId . " 0 R >>");
    $pageObjIds[] = $pageObjId;
  }

  $kids = implode(' ', array_map(function ($id) { return $id . " 0 R"; }, $pageObjIds));
  $objects[$pagesId - 1] = "<< /Type /Pages /Count " . count($pageObjIds) . " /Kids [ " . $kids . " ] >>";
  $objects[$catalogId - 1] = "<< /Type /Catalog /Pages " . $pagesId . " 0 R >>";

  $pdf = "%PDF-1.4\n";
  $offsets = [0];
  for ($i = 0; $i < count($objects); $i++) {
    $offsets[] = strlen($pdf);
    $pdf .= ($i + 1) . " 0 obj\n" . $objects[$i] . "\nendobj\n";
  }
  $xrefPos = strlen($pdf);
  $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
  $pdf .= "0000000000 65535 f \n";
  for ($i = 1; $i <= count($objects); $i++) {
    $pdf .= str_pad((string)$offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
  }
  $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root " . $catalogId . " 0 R >>\nstartxref\n" . $xrefPos . "\n%%EOF";

  return $pdf;
}

function tmm_simple_pdf_download(array $lines, string $filename, array $opts = []): void
{
  $pdf = tmm_simple_pdf_bytes($lines, $opts);
  header('Content-Type: application/pdf');
  header('Content-Disposition: attachment; filename="' . $filename . '"');
  header('Content-Length: ' . strlen($pdf));
  echo $pdf;
  exit;
}

