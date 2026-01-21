<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['SuperAdmin']);
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/util.php';

$db = db();
$rootUrl = tmm_root_url_from_script();

function tmm_q1(mysqli $db, string $sql): string {
  $res = $db->query($sql);
  if (!$res) return '';
  $row = $res->fetch_assoc();
  if (!$row) return '';
  $vals = array_values($row);
  return isset($vals[0]) ? (string)$vals[0] : '';
}

$connectedDb = tmm_q1($db, "SELECT DATABASE()");
$connectedUser = tmm_q1($db, "SELECT USER()");
$currentUser = tmm_q1($db, "SELECT CURRENT_USER()");

$tables = [
  'operators',
  'operator_documents',
  'vehicles',
  'vehicle_documents',
  'routes',
  'terminal_assignments',
];

$canCreate = null;
$canAlter = null;
$privErr = '';
try {
  $probe = "__tmm_priv_probe_" . bin2hex(random_bytes(4));
  $okCreate = $db->query("CREATE TABLE {$probe} (id INT PRIMARY KEY) ENGINE=InnoDB");
  if ($okCreate) {
    $canCreate = true;
    $okAlter = $db->query("ALTER TABLE {$probe} ADD COLUMN c2 INT DEFAULT 0");
    $canAlter = $okAlter ? true : false;
    $db->query("DROP TABLE {$probe}");
  } else {
    $canCreate = false;
    $canAlter = false;
    $privErr = (string)$db->error;
  }
} catch (Throwable $e) {
  $canCreate = false;
  $canAlter = false;
  $privErr = $e->getMessage();
}

$didRepair = false;
$repairErrors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'repair') {
  $didRepair = true;
  $sqls = [
    "CREATE TABLE IF NOT EXISTS vehicles (
      id INT AUTO_INCREMENT PRIMARY KEY,
      plate_number VARCHAR(32) UNIQUE,
      vehicle_type VARCHAR(64),
      operator_id INT DEFAULT NULL,
      operator_name VARCHAR(128) DEFAULT NULL,
      coop_name VARCHAR(128) DEFAULT NULL,
      franchise_id VARCHAR(64) DEFAULT NULL,
      route_id VARCHAR(64) DEFAULT NULL,
      engine_no VARCHAR(100) DEFAULT NULL,
      chassis_no VARCHAR(100) DEFAULT NULL,
      make VARCHAR(100) DEFAULT NULL,
      model VARCHAR(100) DEFAULT NULL,
      year_model VARCHAR(8) DEFAULT NULL,
      fuel_type VARCHAR(64) DEFAULT NULL,
      inspection_status VARCHAR(20) DEFAULT 'Pending',
      inspection_cert_ref VARCHAR(64) DEFAULT NULL,
      inspection_passed_at DATETIME DEFAULT NULL,
      status VARCHAR(32) DEFAULT 'Active',
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB",
    "CREATE TABLE IF NOT EXISTS vehicle_documents (
      doc_id INT AUTO_INCREMENT PRIMARY KEY,
      vehicle_id INT NOT NULL,
      doc_type ENUM('ORCR','Insurance','Others') DEFAULT 'Others',
      file_path VARCHAR(255) NOT NULL,
      uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      INDEX (vehicle_id),
      FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
    ) ENGINE=InnoDB",
  ];

  foreach ($sqls as $sql) {
    if (!$db->query($sql)) {
      $repairErrors[] = (string)$db->error;
    }
  }
}
?>

<div class="mx-auto max-w-4xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-6">
  <div class="flex flex-col gap-2 border-b border-slate-200 dark:border-slate-700 pb-6">
    <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">PUV Database Schema Status</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400 max-w-3xl">Shows which database the app is using and whether required tables exist. If your phpMyAdmin DB doesn’t match what you see here, the website is connected to a different database.</p>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Database</div>
      <div class="mt-2 text-sm font-black text-slate-900 dark:text-white break-all"><?php echo htmlspecialchars($connectedDb ?: '-'); ?></div>
    </div>
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">User()</div>
      <div class="mt-2 text-sm font-black text-slate-900 dark:text-white break-all"><?php echo htmlspecialchars($connectedUser ?: '-'); ?></div>
    </div>
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Current_User()</div>
      <div class="mt-2 text-sm font-black text-slate-900 dark:text-white break-all"><?php echo htmlspecialchars($currentUser ?: '-'); ?></div>
    </div>
  </div>

  <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between gap-4">
      <div class="font-black">Required Tables</div>
      <a href="?page=module1/schema_status" class="text-xs font-bold text-blue-700 dark:text-blue-300 hover:underline">Refresh</a>
    </div>
    <div class="p-6 space-y-3">
      <?php foreach ($tables as $t): ?>
        <?php $ok = tmm_table_exists($db, $t); ?>
        <div class="flex items-center justify-between rounded-xl border border-slate-200 dark:border-slate-700 px-4 py-3">
          <div class="font-bold"><?php echo htmlspecialchars($t); ?></div>
          <div class="text-xs font-black px-2.5 py-1 rounded-lg <?php echo $ok ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400'; ?>">
            <?php echo $ok ? 'OK' : 'MISSING'; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
      <div class="font-black">Create/Alter Privileges</div>
      <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">If CREATE/ALTER is not allowed, the app cannot auto-create the vehicles table on your server.</div>
    </div>
    <div class="p-6 space-y-3">
      <div class="flex items-center justify-between rounded-xl border border-slate-200 dark:border-slate-700 px-4 py-3">
        <div class="font-bold">CREATE TABLE</div>
        <div class="text-xs font-black px-2.5 py-1 rounded-lg <?php echo $canCreate ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400'; ?>">
          <?php echo $canCreate ? 'ALLOWED' : 'DENIED'; ?>
        </div>
      </div>
      <div class="flex items-center justify-between rounded-xl border border-slate-200 dark:border-slate-700 px-4 py-3">
        <div class="font-bold">ALTER TABLE</div>
        <div class="text-xs font-black px-2.5 py-1 rounded-lg <?php echo $canAlter ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400'; ?>">
          <?php echo $canAlter ? 'ALLOWED' : 'DENIED'; ?>
        </div>
      </div>
      <?php if (!$canCreate || !$canAlter): ?>
        <div class="rounded-xl bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800 p-4 text-sm font-semibold text-rose-700 dark:text-rose-300 break-words">
          <?php echo htmlspecialchars($privErr ?: 'Insufficient privileges.'); ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
      <div class="font-black">Repair</div>
      <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">Attempts to create the vehicles and vehicle_documents tables (safe if they already exist).</div>
    </div>
    <div class="p-6 space-y-4">
      <?php if ($didRepair): ?>
        <?php if (!$repairErrors): ?>
          <div class="rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 p-4 text-sm font-semibold text-emerald-700 dark:text-emerald-300">Repair completed.</div>
        <?php else: ?>
          <div class="rounded-xl bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800 p-4 text-sm font-semibold text-rose-700 dark:text-rose-300">
            <?php foreach ($repairErrors as $e): ?>
              <div><?php echo htmlspecialchars($e); ?></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>

      <form method="post" class="flex items-center gap-3">
        <input type="hidden" name="action" value="repair">
        <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-md bg-blue-700 hover:bg-blue-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-all active:scale-[0.98]">Run Repair</button>
        <a href="<?php echo htmlspecialchars($rootUrl); ?>/admin/index.php?page=module1/submodule2" class="text-sm font-semibold text-slate-700 dark:text-slate-200 hover:underline">Go to Vehicle Encoding</a>
      </form>
    </div>
  </div>
</div>

