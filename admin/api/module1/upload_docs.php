<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
$db = db();
$plate = trim((string) ($_POST['plate_number'] ?? ($_POST['plate_no'] ?? '')));
$vehicleId = isset($_POST['vehicle_id']) ? (int) $_POST['vehicle_id'] : 0;
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
$vehicleId = (int) ($exists['id'] ?? 0);
$plate = (string) ($exists['plate_number'] ?? $plate);

$uploads_dir = __DIR__ . '/../../uploads';
if (!is_dir($uploads_dir)) {
    mkdir($uploads_dir, 0777, true);
}

$uploaded = [];
$errors = [];
$details = [];

function tmm_docs_has_expiry(mysqli $db): bool
{
    $res = $db->query("SHOW TABLES LIKE 'documents'");
    if (!$res || !$res->fetch_row())
        return false;
    $col = $db->query("SHOW COLUMNS FROM documents LIKE 'expiry_date'");
    return $col && $col->num_rows > 0;
}

function tmm_docs_ensure_expiry(mysqli $db): bool
{
    if (tmm_docs_has_expiry($db))
        return true;
    $res = $db->query("SHOW TABLES LIKE 'documents'");
    if (!$res || !$res->fetch_row())
        return false;
    return (bool) $db->query("ALTER TABLE documents ADD COLUMN expiry_date DATE NULL");
}

function tmm_update_vehicle_status_from_docs(mysqli $db, int $vehicleId, string $plate): void
{
    return;
}

function tmm_vehicle_docs_schema(mysqli $db): array
{
    static $schema = null;
    if (is_array($schema))
        return $schema;
    $schema = ['exists' => false, 'cols' => [], 'types' => []];
    $check = $db->query("SHOW TABLES LIKE 'vehicle_documents'");
    if (!$check || !$check->fetch_row())
        return $schema;
    $schema['exists'] = true;
    $res = $db->query("SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vehicle_documents'");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $col = (string) ($r['COLUMN_NAME'] ?? '');
            if ($col !== '')
                $schema['cols'][$col] = true;
            if ($col !== '')
                $schema['types'][$col] = (string) ($r['COLUMN_TYPE'] ?? '');
        }
    }
    return $schema;
}

function tmm_try_insert_vehicle_doc(mysqli $db, int $vehicleId, string $plate, string $docType, string $filename, array &$errors, array &$details, string $field): bool
{
    $schema = tmm_vehicle_docs_schema($db);
    if (empty($schema['exists'])) {
        $errors[] = "$field: db_insert_failed";
        $details[$field] = 'vehicle_documents_table_missing';
        return false;
    }
    $cols = (array) ($schema['cols'] ?? []);

    $idCol = null;
    if (isset($cols['vehicle_id']))
        $idCol = 'vehicle_id';
    elseif (isset($cols['plate_number']))
        $idCol = 'plate_number';

    $typeCol = null;
    if (isset($cols['doc_type']))
        $typeCol = 'doc_type';
    elseif (isset($cols['document_type']))
        $typeCol = 'document_type';
    elseif (isset($cols['type']))
        $typeCol = 'type';

    $pathCol = null;
    if (isset($cols['file_path']))
        $pathCol = 'file_path';
    elseif (isset($cols['document_path']))
        $pathCol = 'document_path';
    elseif (isset($cols['doc_path']))
        $pathCol = 'doc_path';
    elseif (isset($cols['path']))
        $pathCol = 'path';

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

    $typeMeta = (string) (($schema['types'] ?? [])[$typeCol] ?? '');
    $enumValues = [];
    if ($typeMeta !== '' && stripos($typeMeta, "enum(") === 0) {
        if (preg_match_all("/'([^']*)'/", $typeMeta, $m)) {
            $enumValues = $m[1] ?? [];
        }
    }

    // Use only the exact document type provided - no variants to prevent duplicates
    $variants = [$docType];

    // If enum exists, try to match the exact type or find case-insensitive match
    if ($enumValues) {
        $mapped = [];
        foreach ($variants as $v) {
            foreach ($enumValues as $ev) {
                if (strcasecmp($ev, $v) === 0) {
                    $mapped[] = $ev;
                    break;
                }
            }
        }
        if ($mapped) {
            $variants = array_values(array_unique($mapped));
        } else {
            // If no match found, use first enum value as fallback
            $variants = [$enumValues[0]];
        }
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

foreach (['or', 'cr', 'insurance', 'others'] as $field) {
    if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
        $orExpiry = null;
        if ($field === 'or') {
            $raw = trim((string) ($_POST['or_expiry_date'] ?? ''));
            if ($raw === '' || !preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $raw)) {
                $errors[] = "$field: expiry_date_required";
                continue;
            }
            $orExpiry = $raw;
            tmm_docs_ensure_expiry($db);
        }

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
            if (is_file($dest)) {
                @unlink($dest);
            }
            $errors[] = "$field: File failed security scan";
            continue;
        }

        $uploaded[] = $filename;

        // Map field to document type - use exact types only
        $docType = 'Others';
        if ($field === 'or') {
            $docType = 'OR';
        } elseif ($field === 'cr') {
            $docType = 'CR';
        } elseif ($field === 'insurance') {
            $docType = 'Insurance';
        } elseif ($field === 'others') {
            $docType = 'Others';
        }

        // Only insert into vehicle_documents table (not documents table to avoid duplicates)
        $okInsert = tmm_try_insert_vehicle_doc($db, $vehicleId, $plate, $docType, $filename, $errors, $details, $field);
        if (!$okInsert) {
            if (is_file($dest)) {
                @unlink($dest);
            }
            continue;
        }
    }
}

if (empty($uploaded) && empty($errors)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'no_files_selected']);
} elseif (!empty($errors)) {
    $errOut = [];
    foreach ($errors as $e) {
        $parts = explode(':', $e, 2);
        $f = trim((string) ($parts[0] ?? ''));
        if ($f !== '' && isset($details[$f]) && $details[$f] !== '') {
            $errOut[] = $f . ': db_insert_failed (' . (string) $details[$f] . ')';
        } else {
            $errOut[] = $e;
        }
    }
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => implode(', ', $errOut), 'details' => $details, 'uploaded' => $uploaded]);
} else {
    tmm_update_vehicle_status_from_docs($db, $vehicleId, $plate);
    echo json_encode(['ok' => true, 'files' => $uploaded, 'vehicle_id' => $vehicleId, 'plate_number' => $plate]);
}
