<?php
require_once __DIR__ . '/admin/includes/db.php';
$db = db();

$rows = [];
$sql = "SELECT s.schedule_id, s.plate_number AS schedule_plate, v.plate_number AS vehicle_plate
        FROM inspection_schedules s
        JOIN vehicles v
          ON REPLACE(REPLACE(UPPER(v.plate_number), '-', ''), ' ', '') = REPLACE(REPLACE(UPPER(s.plate_number), '-', ''), ' ', '')
        WHERE s.plate_number <> '' AND v.plate_number <> s.plate_number
        ORDER BY s.schedule_id DESC
        LIMIT 10";
$res = $db->query($sql);
while ($res && ($r = $res->fetch_assoc())) $rows[] = $r;

$cntMismatchExact = 0;
$res2 = $db->query("SELECT COUNT(*) AS c
                    FROM inspection_schedules s
                    LEFT JOIN vehicles v ON v.plate_number=s.plate_number
                    WHERE s.plate_number<>'' AND v.plate_number IS NULL");
if ($res2 && ($r2 = $res2->fetch_assoc())) $cntMismatchExact = (int)($r2['c'] ?? 0);

header('Content-Type: text/plain; charset=utf-8');
echo "schedules_missing_exact_vehicle_match=" . $cntMismatchExact . "\n";
echo "normalized_mismatch_examples=" . count($rows) . "\n";
foreach ($rows as $r) {
  echo (int)$r['schedule_id'] . "\t" . (string)$r['schedule_plate'] . "\t" . (string)$r['vehicle_plate'] . "\n";
}

