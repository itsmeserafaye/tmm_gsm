<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

// Only SuperAdmin can view audit logs
if (current_user_role() !== 'SuperAdmin') {
  echo '<div class="mx-auto max-w-3xl px-4 py-10">';
  echo '<div class="rounded-lg border border-rose-200 bg-rose-50 p-6 text-rose-700">';
  echo '<div class="text-lg font-black">Access Denied</div>';
  echo '<div class="mt-1 text-sm font-bold">Only administrators can view activity logs.</div>';
  echo '</div>';
  echo '</div>';
  return;
}

$db = db();
$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';
$q = trim($_GET['q'] ?? '');
$page = max(1, intval($_GET['p'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build Query
$where = "1=1";
$types = "";
$params = [];

if ($q !== '') {
    $where .= " AND (email LIKE ? OR ip_address LIKE ?)";
    $types .= "ss";
    $params[] = "%$q%";
    $params[] = "%$q%";
}

// Count total
$total = 0;
$stmt = $db->prepare("SELECT COUNT(*) as c FROM rbac_login_audit WHERE $where");
if ($q !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) $total = $row['c'];
$stmt->close();

$totalPages = ceil($total / $limit);

// Fetch Data
$logs = [];
$stmt = $db->prepare("SELECT * FROM rbac_login_audit WHERE $where ORDER BY created_at DESC LIMIT ? OFFSET ?");
if ($q !== '') {
    $types .= "ii";
    $params[] = $limit;
    $params[] = $offset;
    $stmt->bind_param($types, ...$params);
} else {
    $stmt->bind_param("ii", $limit, $offset);
}
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $logs[] = $row;
}
$stmt->close();
?>

<div class="mx-auto max-w-6xl px-4 py-8 space-y-8">
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
    <div>
      <h1 class="text-3xl font-black text-slate-800 dark:text-white flex items-center gap-3">
        <div class="p-3 bg-indigo-500/10 rounded-2xl">
          <i data-lucide="activity" class="w-8 h-8 text-indigo-500"></i>
        </div>
        Activity Logs
      </h1>
      <p class="mt-2 text-slate-500 dark:text-slate-400 font-medium ml-14">Monitor system access and security events.</p>
    </div>
    <div class="flex items-center gap-2">
      <a href="<?php echo htmlspecialchars($rootUrl ?? '', ENT_QUOTES); ?>/admin/api/settings/export_login_audit.php?<?php echo http_build_query(['q'=>$q,'format'=>'csv']); ?>"
        class="rounded-md bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/40 font-bold py-2.5 px-4 transition-all flex items-center gap-2">
        <i data-lucide="download" class="w-4 h-4"></i>
        CSV
      </a>
      <a href="<?php echo htmlspecialchars($rootUrl ?? '', ENT_QUOTES); ?>/admin/api/settings/export_login_audit.php?<?php echo http_build_query(['q'=>$q,'format'=>'excel']); ?>"
        class="rounded-md bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/40 font-bold py-2.5 px-4 transition-all flex items-center gap-2">
        <i data-lucide="file-spreadsheet" class="w-4 h-4"></i>
        Excel
      </a>
    </div>
  </div>

  <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <div class="px-8 py-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
      <div class="flex items-center gap-3">
        <div class="p-2 bg-slate-100 dark:bg-slate-900/30 rounded-xl">
          <i data-lucide="history" class="w-5 h-5 text-slate-500 dark:text-slate-300"></i>
        </div>
        <div>
          <h2 class="text-lg font-black text-slate-800 dark:text-white">Login History</h2>
          <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Authentication Events</p>
        </div>
      </div>
      
      <form class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
        <input type="hidden" name="page" value="users/activity">
        <input name="q" value="<?php echo htmlspecialchars($q); ?>" type="text" placeholder="Search email or IP..." class="w-full sm:w-80 rounded-md border-0 bg-white dark:bg-slate-900/50 py-2.5 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-blue-500 transition-all">
        <button type="submit" class="rounded-md bg-slate-900 hover:bg-slate-800 text-white font-bold py-2.5 px-4 transition-all flex items-center justify-center gap-2">
          <i data-lucide="search" class="w-4 h-4"></i>
          Search
        </button>
      </form>
    </div>

    <div class="p-6">
      <div class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
          <thead class="bg-slate-50 dark:bg-slate-900/30">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Time</th>
              <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">User / Email</th>
              <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Event</th>
              <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">IP Address</th>
              <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Device</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-800">
            <?php if (empty($logs)): ?>
              <tr><td colspan="5" class="px-6 py-10 text-center text-sm font-bold text-slate-500">No activity logs found.</td></tr>
            <?php else: foreach ($logs as $log): ?>
              <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                <td class="px-4 py-4 whitespace-nowrap text-sm font-bold text-slate-500">
                  <?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?>
                </td>
                <td class="px-4 py-4">
                  <div class="font-black text-slate-900 dark:text-white"><?php echo htmlspecialchars($log['email']); ?></div>
                  <?php if ($log['user_id']): ?>
                    <div class="text-xs text-slate-400">ID: <?php echo $log['user_id']; ?></div>
                  <?php endif; ?>
                </td>
                <td class="px-4 py-4">
                  <?php if ($log['ok']): ?>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-md bg-emerald-100 text-emerald-700 text-xs font-bold ring-1 ring-emerald-600/20">
                      <i data-lucide="check-circle" class="w-3 h-3 mr-1"></i> Success
                    </span>
                  <?php else: ?>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-md bg-rose-100 text-rose-700 text-xs font-bold ring-1 ring-rose-600/20">
                      <i data-lucide="x-circle" class="w-3 h-3 mr-1"></i> Failed
                    </span>
                  <?php endif; ?>
                </td>
                <td class="px-4 py-4 whitespace-nowrap text-sm font-mono font-bold text-slate-600 dark:text-slate-300">
                  <?php echo htmlspecialchars($log['ip_address']); ?>
                </td>
                <td class="px-4 py-4 text-xs text-slate-500 max-w-xs truncate" title="<?php echo htmlspecialchars($log['user_agent']); ?>">
                  <?php echo htmlspecialchars($log['user_agent']); ?>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
      <div class="flex items-center justify-between mt-4">
        <div class="text-xs font-bold text-slate-500">
          Page <?php echo $page; ?> of <?php echo $totalPages; ?>
        </div>
        <div class="flex gap-2">
          <?php if ($page > 1): ?>
            <a href="?page=users/activity&q=<?php echo urlencode($q); ?>&p=<?php echo $page - 1; ?>" class="px-3 py-2 rounded-md bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold transition-all">Previous</a>
          <?php endif; ?>
          <?php if ($page < $totalPages): ?>
            <a href="?page=users/activity&q=<?php echo urlencode($q); ?>&p=<?php echo $page + 1; ?>" class="px-3 py-2 rounded-md bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold transition-all">Next</a>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
