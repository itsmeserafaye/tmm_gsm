<?php
$currentPath = isset($currentPath) ? $currentPath : '/dashboard';
?>
<div id="sidebar" class="fixed md:static inset-y-0 left-0 z-40 transform -translate-x-full md:translate-x-0 w-64 bg-white dark:bg-slate-900 border-r border-slate-200/50 dark:border-slate-700 flex flex-col transition-transform duration-200">
  <div class="p-6">
    <a href="?page=dashboard" class="flex items-center space-x-3">
      <img src="/tmm/admin/includes/logo.jpg" alt="TMM" class="w-10 h-10 rounded-xl object-cover">
      <div>
        <h1 class="sidebar-label text-xl font-bold dark:text-white">TMM</h1>
        <p class="sidebar-label text-xs text-slate-500">Admin Dashboard</p>
      </div>
    </a>
  </div>
  <hr class="border-slate-200 dark:border-slate-700 mx-2">
  <nav class="flex-1 p-4 space-y-2 overflow-y-auto">
    <?php foreach ($sidebarItems as $item): ?>
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
                <?php echo $sub['label']; ?>
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
  <div class="flex items-center p-4">
    <img src="/tmm/admin/includes/user.png" alt="Admin" class="w-10 h-10 rounded-full">
    <div class="sidebar-label ml-3">
      <div class="text-sm font-semibold dark:text-white">ADMIN</div>
      <div class="text-xs text-slate-500">Administrator</div>
    </div>
  </div>
</div>
