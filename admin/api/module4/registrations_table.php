<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_permission('module4.read');
header('Content-Type: application/json');

$db = db();
$q = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));

$hasCol = function (string $table, string $col) use ($db): bool {
  $t = $db->real_escape_string($table);
  $c = $db->real_escape_string($col);
  $r = $db->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
  return $r && $r->num_rows > 0;
};
$vrHasOrNo = $hasCol('vehicle_registrations', 'or_number');
$vrHasOrDate = $hasCol('vehicle_registrations', 'or_date');
$vrHasOrExp = $hasCol('vehicle_registrations', 'or_expiry_date');
$vrHasRegYear = $hasCol('vehicle_registrations', 'registration_year');

$orNoSel = $vrHasOrNo ? "COALESCE(NULLIF(vr.or_number,''), vr.orcr_no) AS or_number" : "vr.orcr_no AS or_number";
$orDateSel = $vrHasOrDate ? "COALESCE(NULLIF(vr.or_date,''), vr.orcr_date) AS or_date" : "vr.orcr_date AS or_date";
$orExpSel = $vrHasOrExp ? "vr.or_expiry_date AS or_expiry_date" : "'' AS or_expiry_date";
$regYearSel = $vrHasRegYear ? "vr.registration_year AS registration_year" : "'' AS registration_year";

$sql = "SELECT v.id AS vehicle_id, v.plate_number, v.operator_id, v.status AS vehicle_status,
               vr.registration_status, {$orNoSel}, {$orDateSel}, {$orExpSel}, {$regYearSel}, vr.created_at
        FROM vehicles v
        LEFT JOIN vehicle_registrations vr ON vr.vehicle_id=v.id";
$conds = [];
if ($q !== '') {
  $qv = $db->real_escape_string($q);
  $conds[] = "(v.plate_number LIKE '%$qv%' OR v.engine_no LIKE '%$qv%' OR v.chassis_no LIKE '%$qv%')";
}
if ($status === 'Not Registered') {
  $conds[] = "(vr.registration_status IS NULL OR vr.registration_status='')";
} elseif ($status !== '' && in_array($status, ['Registered','Pending','Expired'], true)) {
  $sv = $db->real_escape_string($status);
  $conds[] = "vr.registration_status='$sv'";
}
if ($conds) $sql .= " WHERE " . implode(" AND ", $conds);
$sql .= " ORDER BY COALESCE(vr.created_at, v.created_at) DESC LIMIT 400";

$res = $db->query($sql);
if (!$res) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_query_failed']);
  exit;
}

$rowsHtml = '';
if ($res->num_rows <= 0) {
  $rowsHtml = '<tr><td colspan="5" class="py-12 text-center text-slate-500 font-medium italic">No records.</td></tr>';
} else {
  while ($row = $res->fetch_assoc()) {
    $reg = (string)($row['registration_status'] ?? '');
    $vehSt = (string)($row['vehicle_status'] ?? '');
    $badge = match($reg) {
      'Registered', 'Recorded' => 'bg-emerald-100 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-400 dark:ring-emerald-500/20',
      'Expired' => 'bg-rose-100 text-rose-700 ring-rose-600/20 dark:bg-rose-900/30 dark:text-rose-400 dark:ring-rose-500/20',
      'Pending' => 'bg-amber-100 text-amber-700 ring-amber-600/20 dark:bg-amber-900/30 dark:text-amber-400 dark:ring-amber-500/20',
      default => 'bg-slate-100 text-slate-700 ring-slate-600/20 dark:bg-slate-800 dark:text-slate-400'
    };
    $vehBadge = match($vehSt) {
      'Active' => 'bg-emerald-100 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-400 dark:ring-emerald-500/20',
      'Inactive' => 'bg-amber-100 text-amber-700 ring-amber-600/20 dark:bg-amber-900/30 dark:text-amber-400 dark:ring-amber-500/20',
      'Blocked' => 'bg-rose-100 text-rose-700 ring-rose-600/20 dark:bg-rose-900/30 dark:text-rose-400 dark:ring-rose-500/20',
      default => 'bg-slate-100 text-slate-700 ring-slate-600/20 dark:bg-slate-800 dark:text-slate-400'
    };
    $plate = (string)($row['plate_number'] ?? '');
    $orNo = trim((string)($row['or_number'] ?? ''));
    $od = (string)($row['or_date'] ?? '');
    $oe = (string)($row['or_expiry_date'] ?? '');
    $ry = trim((string)($row['registration_year'] ?? ''));
    $parts = [];
    if ($orNo !== '') $parts[] = $orNo;
    if ($od !== '') $parts[] = $od;
    if ($oe !== '') $parts[] = 'Exp: ' . $oe;
    if ($ry !== '') $parts[] = 'Year: ' . $ry;
    $orText = $parts ? implode(' • ', $parts) : '-';
    $created = (string)($row['created_at'] ?? '');
    if ($created !== '') $created = substr($created, 0, 10);
    $regLabel = ($reg !== '' ? $reg : 'Not Registered');
    $regDisp = htmlspecialchars($regLabel);
    $rowsHtml .= '<tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">';
    $rowsHtml .= '<td class="py-4 px-6"><div class="font-black text-slate-900 dark:text-white">' . htmlspecialchars($plate) . '</div>';
    if ($vehSt === 'Blocked') {
      $rowsHtml .= '<div class="mt-1 inline-flex items-center gap-2 px-2.5 py-1 rounded-lg text-[11px] font-black bg-rose-50 text-rose-700 border border-rose-200">Operation blocked (OR expired)</div>';
    } elseif ($vehSt === 'Inactive') {
      $rowsHtml .= '<div class="mt-1 inline-flex items-center gap-2 px-2.5 py-1 rounded-lg text-[11px] font-black bg-amber-50 text-amber-800 border border-amber-200">Inactive (missing OR)</div>';
    } elseif ($vehSt !== '') {
      $rowsHtml .= '<div class="mt-1"><span class="px-2.5 py-1 rounded-lg text-[11px] font-bold ring-1 ring-inset ' . $vehBadge . '">' . htmlspecialchars($vehSt) . '</span></div>';
    }
    $rowsHtml .= '</td>';
    $rowsHtml .= '<td class="py-4 px-4 hidden md:table-cell text-slate-600 dark:text-slate-300 font-semibold">' . htmlspecialchars($orText) . '</td>';
    $rowsHtml .= '<td class="py-4 px-4"><span class="px-2.5 py-1 rounded-lg text-[11px] font-bold ring-1 ring-inset ' . $badge . '">' . $regDisp . '</span></td>';
    $rowsHtml .= '<td class="py-4 px-4 hidden sm:table-cell text-slate-600 dark:text-slate-300 font-semibold">' . htmlspecialchars($created !== '' ? $created : '-') . '</td>';
    $rowsHtml .= '<td class="py-4 px-4 text-right">';
    $rowsHtml .= '<a href="?page=module4/submodule2&vehicle_id=' . (int)($row['vehicle_id'] ?? 0) . '" class="inline-flex items-center justify-center gap-2 rounded-md bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 dark:hover:bg-slate-600 px-3 py-2 text-xs font-bold text-white transition-colors">Open</a>';
    $rowsHtml .= '</td>';
    $rowsHtml .= '</tr>';
  }
}

echo json_encode(['ok' => true, 'html' => $rowsHtml]);
