<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
$plate = trim($_GET['plate'] ?? '');
$v = null;
if ($plate !== '') {
  $stmt = $db->prepare("SELECT plate_number, vehicle_type, operator_name, coop_name, franchise_id, route_id, status, created_at FROM vehicles WHERE plate_number=?");
  $stmt->bind_param('s', $plate);
  $stmt->execute();
  $v = $stmt->get_result()->fetch_assoc();
}
?>
<div class="mx-1 mt-1 p-4 md:p-6 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 rounded-lg">
  <h1 class="text-2xl font-bold mb-2">Vehicle Details</h1>
  <?php if (!$v): ?>
    <p class="text-sm">Vehicle not found.</p>
  <?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div class="p-4 border rounded dark:border-slate-700">
        <h2 class="text-lg font-semibold mb-3">Profile</h2>
        <div class="text-sm space-y-1">
          <div><span class="font-semibold">Plate:</span> <?php echo htmlspecialchars($v['plate_number']); ?></div>
          <div><span class="font-semibold">Type:</span> <?php echo htmlspecialchars($v['vehicle_type']); ?></div>
          <div><span class="font-semibold">Operator:</span> <?php echo htmlspecialchars($v['operator_name']); ?></div>
          <div><span class="font-semibold">COOP:</span> <?php echo htmlspecialchars($v['coop_name'] ?? ''); ?></div>
          <div><span class="font-semibold">Franchise ID:</span> <?php echo htmlspecialchars($v['franchise_id'] ?? ''); ?></div>
          <div><span class="font-semibold">Route ID:</span> <?php echo htmlspecialchars($v['route_id'] ?? ''); ?></div>
          <div><span class="font-semibold">Status:</span> <span class="px-2 py-1 rounded bg-green-100 text-green-700"><?php echo htmlspecialchars($v['status']); ?></span></div>
        </div>
      </div>
      <div class="p-4 border rounded dark:border-slate-700">
        <h2 class="text-lg font-semibold mb-3">Documents</h2>
        <div class="text-sm space-y-2">
          <?php
            $stmtD = $db->prepare("SELECT type, file_path, uploaded_at FROM documents WHERE plate_number=? ORDER BY uploaded_at DESC");
            $stmtD->bind_param('s', $plate);
            $stmtD->execute();
            $resD = $stmtD->get_result();
            if ($resD->num_rows === 0) echo '<div>No documents uploaded.</div>';
            while ($d = $resD->fetch_assoc()) {
              echo '<div><span class=\"font-semibold\">'.htmlspecialchars($d['type']).':</span> <a class=\"underline\" href=\"/tmm/admin/'.htmlspecialchars($d['file_path']).'\" target=\"_blank\">View</a> <span class=\"text-xs\">('.htmlspecialchars($d['uploaded_at']).')</span></div>';
            }
          ?>
        </div>
      </div>
    </div>
    <div class="p-4 border rounded dark:border-slate-700 mt-6">
      <h2 class="text-lg font-semibold mb-3">Terminal Assignment</h2>
      <div class="text-sm">
        <?php
          $stmtA = $db->prepare("SELECT route_id, terminal_name, status, assigned_at FROM terminal_assignments WHERE plate_number=?");
          $stmtA->bind_param('s', $plate);
          $stmtA->execute();
          $a = $stmtA->get_result()->fetch_assoc();
          if (!$a) {
            echo 'No assignment yet.';
          } else {
            echo 'Route: '.htmlspecialchars($a['route_id']).' • Terminal: '.htmlspecialchars($a['terminal_name']).' • Status: '.htmlspecialchars($a['status']).' • Assigned: '.htmlspecialchars($a['assigned_at']);
          }
        ?>
      </div>
    </div>
  <?php endif; ?>
</div>
