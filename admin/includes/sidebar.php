<?php
$currentPath = isset($currentPath) ? $currentPath : '/dashboard';
$tmm_nav_allowed = function (array $node): bool {
  if (!empty($node['roles']) && is_array($node['roles'])) {
    return in_array(current_user_role(), $node['roles'], true);
  }
  if (!empty($node['anyPermissions']) && is_array($node['anyPermissions'])) {
    return has_any_permission($node['anyPermissions']);
  }
  return true;
};

$visibleSidebarItems = [];
foreach ($sidebarItems as $item) {
  $hasSubs = !empty($item['subItems']);
  if ($hasSubs) {
    $subs = [];
    foreach ($item['subItems'] as $sub) {
      if ($tmm_nav_allowed($sub)) $subs[] = $sub;
    }
    if (!$subs) continue;
    $item['subItems'] = $subs;
    $visibleSidebarItems[] = $item;
  } else {
    if (!$tmm_nav_allowed($item)) continue;
    $visibleSidebarItems[] = $item;
  }
}

$displayName = trim((string)($_SESSION['name'] ?? ''));
$parts = preg_split('/\s+/', $displayName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
$initials = '';
if (count($parts) >= 2) {
  $initials = mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[count($parts) - 1], 0, 1));
} elseif (count($parts) === 1) {
  $initials = mb_strtoupper(mb_substr($parts[0], 0, 2));
}
if ($initials === '') $initials = 'AU';
?>
<div id="sidebar" class="fixed md:static inset-y-0 left-0 z-40 transform -translate-x-full md:translate-x-0 w-64 bg-white dark:bg-slate-900 border-r border-slate-200/50 dark:border-slate-700 flex flex-col min-h-0 transition-transform duration-200">
  <div class="p-6">
    <a href="?page=dashboard" class="flex items-center space-x-3">
      <img src="includes/GSM_logo.png" alt="TMM" class="w-10 h-10 rounded-xl object-cover">
      <div>
        <h1 class="sidebar-label text-xl font-bold dark:text-white">TMM</h1>
        <p class="sidebar-label text-xs text-slate-500">Admin Dashboard</p>
      </div>
    </a>
  </div>
  <hr class="border-slate-200 dark:border-slate-700 mx-2">
  <nav class="flex-1 min-h-0 p-4 space-y-2 overflow-y-auto">
    <?php foreach ($visibleSidebarItems as $item): ?>
      <?php
        $isActive = (isset($item['path']) && $item['path'] === $currentPath);
        $hasSubs = !empty($item['subItems']);
        $expanded = false;
        if ($hasSubs) {
          foreach ($item['subItems'] as $sub) {
            if ($sub['path'] === $currentPath) { $expanded = true; break; }
          }
        }
        $itemClasses = $isActive || $expanded
          ? 'bg-[#4CAF50]/20 text-[#4CAF50] font-semibold'
          : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:text-slate-600 dark:hover:bg-slate-200';
      ?>
      <div>
        <?php if ($hasSubs): ?>
          <?php $targetId = 'subnav-' . $item['id']; ?>
          <button class="w-full flex justify-between items-center p-2 rounded-xl transition-all duration-200 <?php echo $itemClasses; ?>" data-nav-toggle="<?php echo $targetId; ?>">
            <div class="flex items-center space-x-3">
              <i data-lucide="<?php echo $item['icon']; ?>" class="w-5 h-5"></i>
              <span class="sidebar-label text-sm font-medium"><?php echo $item['label']; ?></span>
            </div>
            <i data-lucide="chevron-down" class="sidebar-chevron w-4 h-4 text-slate-500 <?php echo $expanded ? 'rotate-180' : ''; ?>"></i>
          </button>
          <div id="<?php echo $targetId; ?>" class="ml-8 mt-2 space-y-1 border-l border-slate-300 <?php echo $expanded ? '' : 'hidden'; ?>" data-expanded="<?php echo $expanded ? 'true' : 'false'; ?>">
            <?php foreach ($item['subItems'] as $idx => $sub): ?>
              <?php
                $subActive = $sub['path'] === $currentPath;
                $subClasses = $subActive
                  ? 'bg-[#4CAF50]/10 text-[#4CAF50] font-semibold'
                  : 'text-slate-700 dark:text-slate-500 hover:bg-slate-200 dark:hover:text-slate-600 dark:hover:bg-slate-100';
              ?>
              <a href="?page=<?php echo ltrim($sub['path'], '/'); ?>" class="block w-full ml-2 text-sm text-left p-2 rounded-lg <?php echo $subClasses; ?>" <?php echo $idx === 0 ? 'data-first-subitem="1"' : ''; ?>>
                <span class="sidebar-label"><?php echo $sub['label']; ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <a href="?page=<?php echo ltrim($item['path'], '/'); ?>" class="w-full flex items-center p-2 rounded-xl transition-all duration-200 <?php echo $itemClasses; ?>">
            <div class="flex items-center space-x-3">
              <i data-lucide="<?php echo $item['icon']; ?>" class="w-5 h-5"></i>
              <span class="sidebar-label text-sm font-medium"><?php echo $item['label']; ?></span>
            </div>
          </a>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </nav>
  <hr class="border-slate-300 dark:border-slate-700 mx-2">
  <div class="p-4">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-full border border-slate-200 dark:border-slate-600 bg-emerald-600 text-white flex items-center justify-center font-black text-sm tracking-wide">
          <?php echo htmlspecialchars($initials); ?>
        </div>
        <div class="sidebar-label">
          <div class="text-sm font-bold text-slate-800 dark:text-white truncate max-w-[120px]" title="<?php echo htmlspecialchars($_SESSION['name'] ?? 'Admin User'); ?>">
            <?php echo htmlspecialchars($_SESSION['name'] ?? 'Admin User'); ?>
          </div>
          <div class="text-xs font-medium text-slate-500 truncate max-w-[120px]" title="<?php echo htmlspecialchars(current_user_role()); ?>">
            <?php echo htmlspecialchars(current_user_role()); ?>
          </div>
        </div>
      </div>
      <a href="../gsm_login/Login/login.php?logout=true" class="sidebar-label p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/20 rounded-lg transition-all" title="Logout">
        <i data-lucide="log-out" class="w-5 h-5"></i>
      </a>
    </div>
  </div>
</div>
