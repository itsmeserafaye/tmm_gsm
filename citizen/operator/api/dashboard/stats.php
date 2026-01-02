<?php
require_once __DIR__ . '/../common.php';
$apps = 0;
$violations = 0;
$renewals = 0;
$r1 = $db->query("SELECT COUNT(*) AS c FROM franchise_applications WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)");
$apps += (int)($r1->fetch_assoc()['c'] ?? 0);
$r2 = $db->query("SELECT COUNT(*) AS c FROM inspection_schedules WHERE scheduled_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)");
$apps += (int)($r2->fetch_assoc()['c'] ?? 0);
$r3 = $db->query("SELECT COUNT(*) AS c FROM terminal_permits WHERE created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)");
$apps += (int)($r3->fetch_assoc()['c'] ?? 0);
$r4 = $db->query("SELECT COUNT(*) AS c FROM tickets WHERE status IN ('Pending','Validated')");
$violations = (int)($r4->fetch_assoc()['c'] ?? 0);
$r5 = $db->query("SELECT COUNT(*) AS c FROM terminal_permits WHERE status IN ('Approved','Pending') AND expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
$renewals = (int)($r5->fetch_assoc()['c'] ?? 0);
json_ok(['applications' => $apps, 'violations' => $violations, 'renewals' => $renewals]);
