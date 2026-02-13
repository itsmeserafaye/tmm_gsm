<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module1.read', 'module1.write']);
header('Content-Type: application/json');

$db = db();

$q = trim((string)($_GET['q'] ?? ''));
$vehicleType = trim((string)($_GET['vehicle_type'] ?? ''));
$recordStatus = trim((string)($_GET['record_status'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$highlightPlate = strtoupper(trim((string)($_GET['highlight_plate'] ?? '')));

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';

$schema = '';
$schRes = $db->query("SELECT DATABASE() AS db");
if ($schRes) $schema = (string)(($schRes->fetch_assoc()['db'] ?? '') ?: '');

$hasCol = function (string $table, string $col) use ($db, $schema): bool {
  if ($schema === '') return false;
  $t = $db->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1");
  if (!$t) return false;
  $t->bind_param('sss', $schema, $table, $col);
  $t->execute();
  $res = $t->get_result();
  $ok = (bool)($res && $res->fetch_row());
  $t->close();
  return $ok;
};

$vdHasVehicleId = $hasCol('vehicle_documents', 'vehicle_id');
$vdHasPlate = $hasCol('vehicle_documents', 'plate_number');
$orcrCond = "LOWER(vd.doc_type) IN ('or','cr')";
$vdHasIsVerified = $hasCol('vehicle_documents', 'is_verified');
$vdHasVerifiedLegacy = $hasCol('vehicle_documents', 'verified');
$vdHasExpiry = $hasCol('vehicle_documents', 'expiry_date');
$vdVerifiedCol = $vdHasIsVerified ? 'is_verified' : ($vdHasVerifiedLegacy ? 'verified' : '');
$hasOrcrSql = "0 AS has_orcr";
if ($vdHasVehicleId && $vdHasPlate) {
  $hasOrcrSql = "(SELECT COUNT(*) FROM vehicle_documents vd WHERE (vd.vehicle_id=v.id OR ((vd.vehicle_id IS NULL OR vd.vehicle_id=0) AND vd.plate_number=v.plate_number)) AND $orcrCond) AS has_orcr";
} elseif ($vdHasVehicleId) {
  $hasOrcrSql = "(SELECT COUNT(*) FROM vehicle_documents vd WHERE vd.vehicle_id=v.id AND $orcrCond) AS has_orcr";
} elseif ($vdHasPlate) {
  $hasOrcrSql = "(SELECT COUNT(*) FROM vehicle_documents vd WHERE vd.plate_number=v.plate_number AND $orcrCond) AS has_orcr";
}

$vdMatch = "1=0";
if ($vdHasVehicleId && $vdHasPlate) {
  $vdMatch = "(vd2.vehicle_id=v.id OR ((vd2.vehicle_id IS NULL OR vd2.vehicle_id=0) AND vd2.plate_number=v.plate_number))";
} elseif ($vdHasVehicleId) {
  $vdMatch = "vd2.vehicle_id=v.id";
} elseif ($vdHasPlate) {
  $vdMatch = "vd2.plate_number=v.plate_number";
}
$vdVerifiedExpr = $vdVerifiedCol !== '' ? "COALESCE(vd2.$vdVerifiedCol,0)" : "0";
$expiredExpr = $vdHasExpiry ? "MAX(CASE WHEN UPPER(vd2.doc_type) IN ('OR','INSURANCE') AND vd2.expiry_date IS NOT NULL AND vd2.expiry_date < CURDATE() THEN 1 ELSE 0 END)" : "0";
$docuStatusExpr = ($vdHasVehicleId || $vdHasPlate) ? "(SELECT
  CASE
    WHEN MAX(CASE WHEN UPPER(vd2.doc_type)='CR' THEN 1 ELSE 0 END)=0
      OR MAX(CASE WHEN UPPER(vd2.doc_type)='OR' THEN 1 ELSE 0 END)=0
      OR MAX(CASE WHEN UPPER(vd2.doc_type)='INSURANCE' THEN 1 ELSE 0 END)=0
    THEN 'Pending Upload'
    WHEN $expiredExpr=1 THEN 'Expired'
    WHEN MAX(CASE WHEN UPPER(vd2.doc_type)='CR' THEN $vdVerifiedExpr ELSE 0 END)=1
      AND MAX(CASE WHEN UPPER(vd2.doc_type)='OR' THEN $vdVerifiedExpr ELSE 0 END)=1
      AND MAX(CASE WHEN UPPER(vd2.doc_type)='INSURANCE' THEN $vdVerifiedExpr ELSE 0 END)=1
    THEN 'Verified'
    ELSE 'For Review'
  END
  FROM vehicle_documents vd2
  WHERE $vdMatch
)" : "'-'";

$sql = "SELECT v.id AS vehicle_id,
               v.plate_number,
               v.vehicle_type,
               v.operator_id,
               COALESCE(NULLIF(o.name,''), NULLIF(o.full_name,''), NULLIF(v.operator_name,''), '') AS operator_display,
               v.record_status,
               CASE
                 WHEN v.record_status='Linked' AND (COALESCE(v.operator_id,0)=0 OR o.id IS NULL) THEN 'Encoded'
                 ELSE v.record_status
               END AS record_status_effective,
               $docuStatusExpr AS docu_status,
               v.inspection_status,
               v.franchise_id,
               vr.registration_status,
               vr.orcr_no,
               vr.orcr_date,
               fa.status AS franchise_app_status,
               $hasOrcrSql
        FROM vehicles v
        LEFT JOIN operators o ON o.id=v.operator_id
        LEFT JOIN vehicle_registrations vr ON vr.vehicle_id=v.id
        LEFT JOIN franchise_applications fa ON fa.franchise_ref_number=v.franchise_id
        WHERE 1=1";

$params = [];
$types = '';

if ($q !== '') {
  $sql .= " AND (v.plate_number LIKE ? OR v.operator_name LIKE ? OR o.name LIKE ? OR o.full_name LIKE ?)";
  $like = "%$q%";
  $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
  $types .= 'ssss';
}
if ($vehicleType !== '' && $vehicleType !== 'Vehicle type') {
  $sql .= " AND v.vehicle_type=?";
  $params[] = $vehicleType;
  $types .= 's';
}
if ($recordStatus !== '' && $recordStatus !== 'Record status') {
  if ($recordStatus === 'Linked') {
    $sql .= " AND v.record_status='Linked' AND COALESCE(v.operator_id,0)<>0 AND o.id IS NOT NULL";
  } elseif ($recordStatus === 'Encoded') {
    $sql .= " AND (v.record_status='Encoded' OR (v.record_status='Linked' AND (COALESCE(v.operator_id,0)=0 OR o.id IS NULL)))";
  } else {
    $sql .= " AND v.record_status=?";
    $params[] = $recordStatus;
    $types .= 's';
  }
}
if ($status !== '' && $status !== 'Status') {
  if ($status === 'Linked') {
    $sql .= " AND v.record_status='Linked' AND COALESCE(v.operator_id,0)<>0 AND o.id IS NOT NULL";
  } elseif ($status === 'Unlinked') {
    $sql .= " AND (v.record_status='Encoded' OR (v.record_status='Linked' AND (COALESCE(v.operator_id,0)=0 OR o.id IS NULL)))";
  } elseif ($status === 'Archived') {
    $sql .= " AND v.record_status='Archived'";
  } elseif ($status === 'Declared') {
    $sql .= " AND (CASE WHEN v.record_status='Linked' AND (COALESCE(v.operator_id,0)=0 OR o.id IS NULL) THEN 'Encoded' ELSE v.record_status END)='Encoded' AND v.record_status<>'Archived'";
  } elseif ($status === 'Pending Inspection') {
    $sql .= " AND v.record_status='Linked' AND COALESCE(v.operator_id,0)<>0 AND o.id IS NOT NULL AND COALESCE(v.inspection_status,'')<>'Passed' AND v.record_status<>'Archived'";
  } elseif ($status === 'Inspected') {
    $sql .= " AND COALESCE(v.inspection_status,'')='Passed'
              AND NOT (COALESCE(vr.registration_status,'') IN ('Registered','Recorded') AND COALESCE(NULLIF(vr.orcr_no,''),'')<>'' AND vr.orcr_date IS NOT NULL)
              AND v.record_status<>'Archived'";
  } elseif ($status === 'Registered') {
    $sql .= " AND COALESCE(v.inspection_status,'')='Passed'
              AND (COALESCE(vr.registration_status,'') IN ('Registered','Recorded') AND COALESCE(NULLIF(vr.orcr_no,''),'')<>'' AND vr.orcr_date IS NOT NULL)
              AND NOT (COALESCE(fa.status,'') IN ('Approved','LTFRB-Approved'))
              AND v.record_status<>'Archived'";
  } elseif ($status === 'Active') {
    $sql .= " AND COALESCE(v.inspection_status,'')='Passed'
              AND (COALESCE(vr.registration_status,'') IN ('Registered','Recorded') AND COALESCE(NULLIF(vr.orcr_no,''),'')<>'' AND vr.orcr_date IS NOT NULL)
              AND (COALESCE(fa.status,'') IN ('Approved','LTFRB-Approved'))
              AND v.record_status<>'Archived'";
  } else {
    $sql .= " AND v.status=?";
    $params[] = $status;
    $types .= 's';
  }
}
$docuStatus = trim((string)($_GET['docu_status'] ?? ''));
if ($docuStatus !== '' && $docuStatus !== 'Docu status') {
  $sql .= " AND $docuStatusExpr=?";
  $params[] = $docuStatus;
  $types .= 's';
}
$sql .= " ORDER BY v.created_at DESC LIMIT 300";

$res = null;
if ($params) {
  $stmt = $db->prepare($sql);
  if (!$stmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
    exit;
  }
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();
} else {
  $res = $db->query($sql);
}

$canWrite = has_permission('module1.vehicles.write');
$canSchedule = has_permission('module4.schedule');

$esc = function ($s): string {
  return htmlspecialchars((string)($s ?? ''), ENT_QUOTES);
};

$html = '';
if ($res && ($res->num_rows ?? 0) > 0) {
  while ($row = $res->fetch_assoc()) {
    $plate = (string)($row['plate_number'] ?? '');
    $plateUp = strtoupper($plate);
    $isHighlight = $highlightPlate !== '' && $highlightPlate === $plateUp;
    $rs = (string)($row['record_status_effective'] ?? ($row['record_status'] ?? ''));
    if ($rs === '') $rs = 'Encoded';
    $insp = (string)($row['inspection_status'] ?? '');
    $frAppSt = (string)($row['franchise_app_status'] ?? '');
    $regSt = (string)($row['registration_status'] ?? '');
    $orcrNo = trim((string)($row['orcr_no'] ?? ''));
    $orcrDate = trim((string)($row['orcr_date'] ?? ''));
    $frOk = in_array($frAppSt, ['Approved', 'LTFRB-Approved'], true);
    $inspOk = $insp === 'Passed';
    $regOk = in_array($regSt, ['Registered', 'Recorded'], true) && $orcrNo !== '' && $orcrDate !== '';
    $st = 'Declared';
    if ($rs === 'Archived') {
      $st = 'Archived';
    } elseif ($frOk && $inspOk && $regOk) {
      $st = 'Active';
    } elseif ($inspOk && $regOk) {
      $st = 'Registered';
    } elseif ($inspOk) {
      $st = 'Inspected';
    } elseif ($rs === 'Linked') {
      $st = 'Pending Inspection';
    }
    $badgeRs = match ($rs) {
      'Linked' => 'bg-blue-100 text-blue-700 ring-blue-600/20 dark:bg-blue-900/30 dark:text-blue-400 dark:ring-blue-500/20',
      'Archived' => 'bg-rose-100 text-rose-700 ring-rose-600/20 dark:bg-rose-900/30 dark:text-rose-400 dark:ring-rose-500/20',
      'Encoded' => 'bg-amber-100 text-amber-700 ring-amber-600/20 dark:bg-amber-900/30 dark:text-amber-400 dark:ring-amber-500/20',
      default => 'bg-slate-100 text-slate-700 ring-slate-600/20 dark:bg-slate-800 dark:text-slate-400'
    };
    $badgeSt = match ($st) {
      'Active' => 'bg-emerald-100 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-400 dark:ring-emerald-500/20',
      'Registered' => 'bg-indigo-100 text-indigo-700 ring-indigo-600/20 dark:bg-indigo-900/30 dark:text-indigo-400 dark:ring-indigo-500/20',
      'Inspected' => 'bg-violet-100 text-violet-700 ring-violet-600/20 dark:bg-violet-900/30 dark:text-violet-400 dark:ring-violet-500/20',
      'Pending Inspection' => 'bg-amber-100 text-amber-700 ring-amber-600/20 dark:bg-amber-900/30 dark:text-amber-400 dark:ring-amber-500/20',
      'Declared' => 'bg-slate-100 text-slate-700 ring-slate-600/20 dark:bg-slate-800 dark:text-slate-300',
      'Archived' => 'bg-rose-100 text-rose-700 ring-rose-600/20 dark:bg-rose-900/30 dark:text-rose-400 dark:ring-rose-500/20',
      default => 'bg-slate-100 text-slate-700 ring-slate-600/20 dark:bg-slate-800 dark:text-slate-400'
    };

    $rowClass = "hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors group";
    if ($isHighlight) $rowClass .= " bg-emerald-50/70 dark:bg-emerald-900/15 ring-1 ring-inset ring-emerald-200/70 dark:ring-emerald-900/30";
    $rowId = $isHighlight ? ' id="veh-row-highlight"' : '';

    $vehicleId = (int)($row['vehicle_id'] ?? 0);
    $operatorDisplay = (string)($row['operator_display'] ?? '');
    $docu = trim((string)($row['docu_status'] ?? ''));
    if ($docu === '') $docu = '-';
    $badgeDocu = match ($docu) {
      'Verified' => 'bg-emerald-100 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-400 dark:ring-emerald-500/20',
      'For Review' => 'bg-amber-100 text-amber-700 ring-amber-600/20 dark:bg-amber-900/30 dark:text-amber-400 dark:ring-amber-500/20',
      'Expired' => 'bg-rose-100 text-rose-700 ring-rose-600/20 dark:bg-rose-900/30 dark:text-rose-400 dark:ring-rose-500/20',
      'Pending Upload' => 'bg-sky-100 text-sky-700 ring-sky-600/20 dark:bg-sky-900/30 dark:text-sky-300 dark:ring-sky-500/20',
      default => 'bg-slate-100 text-slate-700 ring-slate-600/20 dark:bg-slate-800 dark:text-slate-300'
    };

    $html .= '<tr class="' . $esc($rowClass) . '"' . $rowId . '>';
    $html .= '<td class="py-4 px-6"><div class="font-black text-slate-900 dark:text-white">' . $esc($plateUp) . '</div><div class="text-xs text-slate-500 dark:text-slate-400 mt-1">ID: ' . (int)$vehicleId . '</div></td>';
    $html .= '<td class="py-4 px-4 hidden md:table-cell"><span class="inline-flex items-center rounded-lg bg-slate-100 dark:bg-slate-700/50 px-2.5 py-1 text-xs font-bold text-slate-600 dark:text-slate-300 ring-1 ring-inset ring-slate-500/10">' . $esc((string)($row['vehicle_type'] ?? '')) . '</span></td>';
    $html .= '<td class="py-4 px-4 text-slate-600 dark:text-slate-300 font-medium">';
    $html .= $esc($operatorDisplay);
    if ($operatorDisplay === '') $html .= '<span class="text-slate-400 italic">Unlinked</span>';
    $html .= '</td>';
    $html .= '<td class="py-4 px-4 hidden lg:table-cell"><span class="px-2.5 py-1 rounded-lg text-xs font-bold ring-1 ring-inset ' . $esc($badgeSt) . '">' . $esc($st) . '</span></td>';
    $html .= '<td class="py-4 px-4"><span class="px-2.5 py-1 rounded-lg text-xs font-bold ring-1 ring-inset ' . $esc($badgeRs) . '">' . $esc($rs) . '</span></td>';
    $html .= '<td class="py-4 px-4 hidden sm:table-cell"><span class="px-2.5 py-1 rounded-lg text-xs font-bold ring-1 ring-inset ' . $esc($badgeDocu) . '">' . $esc($docu) . '</span></td>';
    $html .= '<td class="py-4 px-4 text-right"><div class="flex items-center justify-end gap-2 opacity-100 md:opacity-0 md:group-hover:opacity-100 transition-opacity">';
    $html .= '<button type="button" class="p-2 rounded-xl bg-slate-100 dark:bg-slate-700/50 text-slate-500 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-all" data-veh-view="1" data-plate="' . $esc($plateUp) . '" title="View Details"><i data-lucide="eye" class="w-4 h-4"></i></button>';
    if ($canWrite) {
      $html .= '<button type="button" class="p-2 rounded-xl bg-slate-100 dark:bg-slate-700/50 text-slate-500 hover:text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-all" data-veh-docs="1" data-vehicle-id="' . (int)$vehicleId . '" data-plate="' . $esc($plateUp) . '" title="Upload / View Docs"><i data-lucide="upload-cloud" class="w-4 h-4"></i></button>';
      if ($canSchedule && $st === 'Pending Inspection' && $vehicleId > 0) {
        $html .= '<a href="?page=module4/submodule3&vehicle_id=' . rawurlencode((string)$vehicleId) . '" class="p-2 rounded-xl bg-slate-100 dark:bg-slate-700/50 text-slate-500 hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/20 transition-all inline-flex items-center justify-center" title="Schedule Inspection"><i data-lucide="calendar-check" class="w-4 h-4"></i></a>';
      }
    }
    $html .= '</div></td>';
    $html .= '</tr>';
  }
} else {
  $html = '<tr><td colspan="7" class="py-12 text-center text-slate-500 font-medium italic">No vehicles found.</td></tr>';
}

echo json_encode(['ok' => true, 'html' => $html]);
?>
