<?php
require_once __DIR__ . '/admin/includes/db.php';
require_once __DIR__ . '/includes/rbac.php';

echo "Initializing RBAC Schema...\n";
$db = db();
rbac_ensure_schema($db);
echo "Done.\n";
