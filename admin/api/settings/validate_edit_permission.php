<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../../includes/edit_permission.php';

$db = db();
$rid = (int)($_GET['r'] ?? 0);
$tok = (string)($_GET['t'] ?? '');
$ok = false;
$code = 'invalid';
if ($rid > 0 && $tok !== '') {
  $res = ep_validate($db, $rid, $tok);
  $ok = (bool)($res['ok'] ?? false);
  $code = (string)($res['error'] ?? ($ok ? 'granted' : 'invalid'));
}

header('Content-Type: text/html; charset=UTF-8');
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $ok ? 'Permission Granted' : 'Authorization Error'; ?></title>
  <style>
    body{font-family:Inter,Arial,sans-serif;background:#f1f5f9;color:#0f172a;margin:0}
    .wrap{max-width:680px;margin:40px auto;padding:0 16px}
    .card{background:#fff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden}
    .head{background:#4f46e5;color:#fff;padding:16px 20px}
    .body{padding:24px}
    .ok{color:#065f46}
    .err{color:#b91c1c}
    .note{font-size:12px;color:#475569}
  </style>
  <script>
    setTimeout(function(){ if (window.history.length > 1) window.history.back(); }, 4000);
  </script>
  </head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="head">
        <h2 style="margin:0;font-size:18px">Transport & Mobility Management</h2>
        <p style="margin:4px 0 0;font-size:12px;opacity:.9">Edit Permission</p>
      </div>
      <div class="body">
        <?php if ($ok): ?>
          <h3 class="ok">Permission granted</h3>
          <p>You have successfully authorized the administrator to edit your account information. You may close this tab.</p>
        <?php else: ?>
          <h3 class="err">Authorization failed</h3>
          <p>
          <?php
            if ($code === 'expired') echo 'The authorization link has expired.';
            elseif ($code === 'already_processed') echo 'This request has already been processed.';
            else echo 'The authorization token is invalid.';
          ?>
          </p>
          <p class="note">If you did not expect this request, you can safely ignore this message.</p>
        <?php endif; ?>
      </div>
    </div>
    <p class="note" style="margin-top:16px">This page will attempt to close or go back automatically.</p>
  </div>
</body>
</html>
