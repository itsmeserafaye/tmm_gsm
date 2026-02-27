<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
header('Content-Type: application/json');
require_any_permission(['module5.manage_terminal', 'module5.parking_fees']);

function tmm_table_exists(mysqli $db, string $table): bool {
  $stmt = $db->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1");
  if (!$stmt) return false;
  $stmt->bind_param('s', $table);
  $stmt->execute();
  $ok = (bool)$stmt->get_result()->fetch_row();
  $stmt->close();
  return $ok;
}

function tmm_table_columns(mysqli $db, string $table): array {
  $cols = [];
  $stmt = $db->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
  if (!$stmt) return $cols;
  $stmt->bind_param('s', $table);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) {
    $cn = (string)($row['COLUMN_NAME'] ?? '');
    if ($cn !== '') $cols[$cn] = true;
  }
  $stmt->close();
  return $cols;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

$stmt = $db->prepare("SELECT * FROM terminals WHERE id=?");
$stmt->bind_param('i', $id);
$stmt->execute();
$terminal = $stmt->get_result()->fetch_assoc();
if (!$terminal) {
    echo json_encode(['success' => false, 'message' => 'Not found']);
    exit;
}

$agreement = null;
if (tmm_table_exists($db, 'facility_agreements') && tmm_table_exists($db, 'facility_owners')) {
  $aCols = tmm_table_columns($db, 'facility_agreements');
  $tidCol = isset($aCols['terminal_id']) ? 'terminal_id' : (isset($aCols['facility_id']) ? 'facility_id' : '');
  $typeCol = isset($aCols['agreement_type']) ? 'agreement_type' : (isset($aCols['contract_type']) ? 'contract_type' : '');
  $refCol = isset($aCols['reference_no']) ? 'reference_no' : (isset($aCols['reference_number']) ? 'reference_number' : (isset($aCols['contract_ref_no']) ? 'contract_ref_no' : ''));
  $rentCol = isset($aCols['rent_amount']) ? 'rent_amount' : (isset($aCols['amount']) ? 'amount' : '');
  $freqCol = isset($aCols['rent_frequency']) ? 'rent_frequency' : (isset($aCols['frequency']) ? 'frequency' : '');
  $termsCol = isset($aCols['terms_summary']) ? 'terms_summary' : (isset($aCols['terms']) ? 'terms' : (isset($aCols['contract_terms']) ? 'contract_terms' : ''));
  $statusCol = isset($aCols['status']) ? 'status' : '';

  $startCol = '';
  foreach (['start_date', 'contract_start', 'contract_start_date', 'start'] as $c) { if (isset($aCols[$c])) { $startCol = $c; break; } }
  $endCol = '';
  foreach (['end_date', 'contract_end', 'contract_end_date', 'end'] as $c) { if (isset($aCols[$c])) { $endCol = $c; break; } }

  if ($tidCol !== '') {
    $fields = [
      'fa.id',
      ($typeCol !== '' ? "fa.$typeCol AS agreement_type" : "'' AS agreement_type"),
      ($refCol !== '' ? "fa.$refCol AS reference_no" : "'' AS reference_no"),
      ($rentCol !== '' ? "fa.$rentCol AS rent_amount" : "0 AS rent_amount"),
      ($freqCol !== '' ? "fa.$freqCol AS rent_frequency" : "'' AS rent_frequency"),
      ($termsCol !== '' ? "fa.$termsCol AS terms_summary" : "'' AS terms_summary"),
      ($startCol !== '' ? "fa.$startCol AS start_date" : "NULL AS start_date"),
      ($endCol !== '' ? "fa.$endCol AS end_date" : "NULL AS end_date"),
      ($statusCol !== '' ? "fa.$statusCol AS status" : "'' AS status"),
      (isset($aCols['created_at']) ? "fa.created_at" : "NULL AS created_at"),
      "fo.name AS owner_name",
      "fo.type AS owner_type",
      "fo.contact_info AS owner_contact",
    ];

    $order = '';
    if ($statusCol !== '') $order = "ORDER BY FIELD(fa.$statusCol, 'Active', 'Expiring Soon', 'Expired', 'Terminated'), fa.created_at DESC";
    elseif (isset($aCols['created_at'])) $order = "ORDER BY fa.created_at DESC";
    else $order = "ORDER BY fa.id DESC";

    $sql = "SELECT " . implode(', ', $fields) . " FROM facility_agreements fa JOIN facility_owners fo ON fa.owner_id=fo.id WHERE fa.$tidCol=? $order LIMIT 1";
    $stmtA = $db->prepare($sql);
    if ($stmtA) {
      $stmtA->bind_param('i', $id);
      $stmtA->execute();
      $agreement = $stmtA->get_result()->fetch_assoc() ?: null;
      $stmtA->close();
    }
  }
}

$docs = [];
if (tmm_table_exists($db, 'facility_documents')) {
  $dCols = tmm_table_columns($db, 'facility_documents');
  $tidCol = isset($dCols['terminal_id']) ? 'terminal_id' : (isset($dCols['facility_id']) ? 'facility_id' : '');
  $typeCol = isset($dCols['doc_type']) ? 'doc_type' : (isset($dCols['type']) ? 'type' : '');
  $pathCol = isset($dCols['file_path']) ? 'file_path' : (isset($dCols['path']) ? 'path' : (isset($dCols['document_path']) ? 'document_path' : ''));
  $timeCol = isset($dCols['uploaded_at']) ? 'uploaded_at' : (isset($dCols['created_at']) ? 'created_at' : '');
  if ($tidCol !== '' && $typeCol !== '' && $pathCol !== '') {
    $fields = [
      'id',
      "$typeCol AS doc_type",
      "$pathCol AS file_path",
      ($timeCol !== '' ? "$timeCol AS uploaded_at" : "NULL AS uploaded_at"),
    ];
    $sqlD = "SELECT " . implode(', ', $fields) . " FROM facility_documents WHERE $tidCol=? ORDER BY " . ($timeCol !== '' ? $timeCol : 'id') . " DESC";
    $stmtD = $db->prepare($sqlD);
    if ($stmtD) {
      $stmtD->bind_param('i', $id);
      $stmtD->execute();
      $resD = $stmtD->get_result();
      while ($r = $resD->fetch_assoc()) $docs[] = $r;
      $stmtD->close();
    }
  }
}

if ($agreement) {
    $s = (string)($agreement['start_date'] ?? '');
    $e = (string)($agreement['end_date'] ?? '');
    if ($s && $e) {
        try {
            $d1 = new DateTime($s);
            $d2 = new DateTime($e);
            $diff = $d1->diff($d2);
            $months = ($diff->y * 12) + $diff->m;
            $days = $diff->d;
            $agreement['duration_computed'] = "$months months" . ($days > 0 ? ", $days days" : "");
        } catch (Exception $e) {
            $agreement['duration_computed'] = 'Invalid Dates';
        }
    } else {
        $agreement['duration_computed'] = 'N/A';
    }
}

echo json_encode([
    'success' => true,
    'terminal' => $terminal,
    'agreement' => $agreement,
    'documents' => $docs
]);
?>
