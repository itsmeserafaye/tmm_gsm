<?php
require_once __DIR__ . '/../includes/db.php';
$db = db();
$names = ["TEST_API_COOP", "TEST_E2E_COOP"];
$in = "'" . implode("','", array_map([$db, 'real_escape_string'], $names)) . "'";
$sql = "DELETE FROM coops WHERE coop_name IN ($in)";
$db->query($sql);
echo "Deleted " . $db->affected_rows . " test coops\n";
