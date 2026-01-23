<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
$db = db();
$plate = trim((string)($_POST['plate_number'] ?? ($_POST['plate_no'] ?? '')));
$vehicleId = isset($_POST['vehicle_id']) ? (int)$_POST['vehicle_id'] : 0;
header('Content-Type: application/json');
require_permission('module1.vehicles.write');

if ($vehicleId <= 0 && $plate === '') {
    echo json_encode(['error' => 'missing_vehicle']);
    exit;
}

$exists = null;
if ($vehicleId > 0) {
    $chk = $db->prepare("SELECT id, plate_number FROM vehicles WHERE id=?");
    $chk->bind_param('i', $vehicleId);
    $chk->execute();
    $exists = $chk->get_result()->fetch_assoc();
} else {
    $chk = $db->prepare("SELECT id, plate_number FROM vehicles WHERE plate_number=?");
    $chk->bind_param('s', $plate);
    $chk->execute();
    $exists = $chk->get_result()->fetch_assoc();
}
if (!$exists) {
    http_response_code(404);
    echo json_encode(['error' => 'vehicle_not_found']);
    exit;
}
$vehicleId = (int)($exists['id'] ?? 0);
$plate = (string)($exists['plate_number'] ?? $plate);

$uploads_dir = __DIR__ . '/../../uploads';
if (!is_dir($uploads_dir)) {
    mkdir($uploads_dir, 0777, true);
}

$uploaded = [];
$errors = [];
$details = [];

function tmm_vehicle_docs_schema(mysqli $db): array {
    static $schema = null;
    if (is_array($schema)) return $schema;
    $schema = ['exists' => false, 'cols' => [], 'types' => []];
    $check = $db->query("SHOW TABLES LIKE 'vehicle_documents'");
    if (!$check || !$check->fetch_row()) return $schema;
    $schema['exists'] = true;
    $res = $db->query("SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vehicle_documents'");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $col = (string)($r['COLUMN_NAME'] ?? '');
            if ($col !== '') $schema['cols'][$col] = true;
            if ($col !== '') $schema['types'][$col] = (string)($r['COLUMN_TYPE'] ?? '');
        }
    }
    return $schema;
}

function tmm_try_insert_vehicle_doc(mysqli $db, int $vehicleId, string $plate, string $docType, string $filename, array &$errors, array &$details, string $field): bool {
    $schema = tmm_vehicle_docs_schema($db);
    if (empty($schema['exists'])) {
        $errors[] = "$field: db_insert_failed";
        $details[$field] = 'vehicle_documents_table_missing';
        return false;
    }
    $cols = (array)($schema['cols'] ?? []);

    $idCol = null;
    if (isset($cols['vehicle_id'])) $idCol = 'vehicle_id';
    elseif (isset($cols['plate_number'])) $idCol = 'plate_number';

    $typeCol = null;
    if (isset($cols['doc_type'])) $typeCol = 'doc_type';
    elseif (isset($cols['document_type'])) $typeCol = 'document_type';
    elseif (isset($cols['type'])) $typeCol = 'type';

    $pathCol = null;
    if (isset($cols['file_path'])) $pathCol = 'file_path';
    elseif (isset($cols['document_path'])) $pathCol = 'document_path';
    elseif (isset($cols['doc_path'])) $pathCol = 'doc_path';
    elseif (isset($cols['path'])) $pathCol = 'path';

    if ($idCol === null || $typeCol === null || $pathCol === null) {
        $errors[] = "$field: db_insert_failed";
        $details[$field] = 'vehicle_documents_schema_not_supported';
        return false;
    }

    $extraCols = [];
    $extraTypes = '';
    $extraParams = [];
    if ($idCol === 'vehicle_id' && isset($cols['is_verified'])) {
        $extraCols[] = 'is_verified';
    } elseif ($idCol === 'plate_number' && isset($cols['is_verified'])) {
        $extraCols[] = 'is_verified';
    } elseif (isset($cols['verified'])) {
        $extraCols[] = 'verified';
    }
    foreach ($extraCols as $c) {
        $extraTypes .= 'i';
        $extraParams[] = 0;
    }

    $sql = "INSERT INTO vehicle_documents ($idCol, $typeCol, $pathCol" . ($extraCols ? (", " . implode(", ", $extraCols)) : "") . ") VALUES (?,?,?" . ($extraCols ? ("," . implode(",", array_fill(0, count($extraCols), "?"))) : "") . ")";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        $errors[] = "$field: db_insert_failed";
        $details[$field] = 'db_prepare_failed';
        return false;
    }

    $typeMeta = (string)(($schema['types'] ?? [])[$typeCol] ?? '');
    $enumValues = [];
    if ($typeMeta !== '' && stripos($typeMeta, "enum(") === 0) {
        if (preg_match_all("/'([^']*)'/", $typeMeta, $m)) {
            $enumValues = $m[1] ?? [];
        }
    }

    $variants = [$docType];
    $tryAdd = function (string $v) use (&$variants): void {
        foreach ($variants as $e) { if (strcasecmp($e, $v) === 0) return; }
        $variants[] = $v;
    };
    if ($field === 'or' || $field === 'cr' || $field === 'orcr') {
        $tryAdd('OR/CR');
        $tryAdd('OR');
        $tryAdd('CR');
        $tryAdd('orcr');
        $tryAdd('or');
        $tryAdd('cr');
    } elseif ($field === 'insurance') {
        $tryAdd('INSURANCE');
        $tryAdd('insurance');
    } elseif ($field === 'emission') {
        $tryAdd('EMISSION');
        $tryAdd('emission');
    } else {
        $tryAdd('OTHERS');
        $tryAdd('others');
    }

    if ($enumValues) {
        $mapped = [];
        foreach ($variants as $v) {
            foreach ($enumValues as $ev) {
                if (strcasecmp($ev, $v) === 0) { $mapped[] = $ev; break; }
            }
        }
        if ($mapped) $variants = array_values(array_unique($mapped));
        else $variants = [$enumValues[0]];
    }

    $typesBase = ($idCol === 'vehicle_id') ? 'iss' : 'sss';
    $idVal = ($idCol === 'vehicle_id') ? $vehicleId : $plate;
    $lastErr = '';
    foreach ($variants as $v) {
        $types = $typesBase . $extraTypes;
        $params = array_merge([$idVal, $v, $filename], $extraParams);
        $stmt->bind_param($types, ...$params);
        $ok = $stmt->execute();
        if ($ok) {
            $stmt->close();
            return true;
        }
        $lastErr = $stmt->error ?: 'execute_failed';
    }
    $stmt->close();
    $errors[] = "$field: db_insert_failed";
    $details[$field] = $lastErr;
    return false;
}

foreach (['or', 'cr', 'deed', 'orcr', 'insurance', 'emission', 'others'] as $field) {
    if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
            $errors[] = "$field: Invalid file type ($ext)";
            continue;
        }
        
        $filename = $plate . '_' . $field . '_' . time() . '.' . $ext;
        $dest = $uploads_dir . '/' . $filename;
        
        if (!move_uploaded_file($_FILES[$field]['tmp_name'], $dest)) {
            $errors[] = "$field: Failed to move file";
            continue;
        }

        $safe = tmm_scan_file_for_viruses($dest);
        if (!$safe) {
            if (is_file($dest)) { @unlink($dest); }
            $errors[] = "$field: File failed security scan";
            continue;
        }

        $uploaded[] = $filename;
        $docType = 'Others';
        $legacyType = 'deed';
        if ($field === 'or' || $field === 'cr' || $field === 'orcr') { $docType = 'ORCR'; $legacyType = ($field === 'cr' ? 'cr' : 'or'); }
        elseif ($field === 'insurance') { $docType = 'Insurance'; $legacyType = 'insurance'; }
        elseif ($field === 'emission') { $docType = 'Emission'; $legacyType = 'others'; }
        elseif ($field === 'deed') { $docType = 'Others'; $legacyType = 'deed'; }

        $okInsert = tmm_try_insert_vehicle_doc($db, $vehicleId, $plate, $docType, $filename, $errors, $details, $field);
        if (!$okInsert) {
            if (is_file($dest)) { @unlink($dest); }
            continue;
        }

        $stmtLegacy = $db->prepare("INSERT INTO documents (plate_number, type, file_path) VALUES (?, ?, ?)");
        if ($stmtLegacy) {
            $stmtLegacy->bind_param('sss', $plate, $legacyType, $filename);
            $stmtLegacy->execute();
            $stmtLegacy->close();
        }
    }
}

if (empty($uploaded) && empty($errors)) {
    echo json_encode(['error' => 'No files selected']);
} elseif (!empty($errors)) {
    $errOut = [];
    foreach ($errors as $e) {
        $parts = explode(':', $e, 2);
        $f = trim((string)($parts[0] ?? ''));
        if ($f !== '' && isset($details[$f]) && $details[$f] !== '') {
            $errOut[] = $f . ': db_insert_failed (' . (string)$details[$f] . ')';
        } else {
            $errOut[] = $e;
        }
    }
    echo json_encode(['ok' => false, 'error' => implode(', ', $errOut), 'details' => $details, 'uploaded' => $uploaded]);
} else {
    echo json_encode(['ok' => true, 'files' => $uploaded, 'vehicle_id' => $vehicleId, 'plate_number' => $plate]);
}
