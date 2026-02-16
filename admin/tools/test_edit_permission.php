<?php
if (php_sapi_name() !== 'cli') {
  http_response_code(403);
  echo "forbidden\n";
  exit;
}
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../../includes/edit_permission.php';

$db = db();
$editor = (int)($argv[1] ?? 1);
$target = (int)($argv[2] ?? 2);

echo "Requesting edit permission editor=$editor target=$target\n";
$req = ep_request($db, $editor, $target, 24);
if (!($req['ok'] ?? false)) {
  var_export($req);
  exit(2);
}
echo "Request ID: {$req['request_id']}\n";
echo "Expires At: {$req['expires_at']}\n";

$validate = ep_validate($db, (int)$req['request_id'], (string)$req['token']);
echo "Validate: " . json_encode($validate) . "\n";

$auth = ep_authorized($db, $editor, $target);
echo "Authorized? " . json_encode($auth) . "\n";

sleep(1);
echo "Ping...\n";
$ping = ep_ping($db, $editor, $target);
echo "Ping: " . json_encode($ping) . "\n";

echo "Done.\n";
