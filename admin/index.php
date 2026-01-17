<?php
$baseUrl = str_replace('\\', '/', (string)dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/admin/index.php')));
$baseUrl = $baseUrl === '/' ? '' : rtrim($baseUrl, '/');
$rootUrl = preg_replace('#/admin$#', '', $baseUrl);
if (php_sapi_name() !== 'cli' && function_exists('session_status') && session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
if (php_sapi_name() !== 'cli' && empty($_SESSION['user_id'])) {
  header('Location: ' . $rootUrl . '/index.php');
  exit;
}

$baseDir = __DIR__;
require_once $baseDir . '/includes/auth.php';
require_once $baseDir . '/includes/sidebar_items.php';

if (php_sapi_name() !== 'cli') {
  $role = current_user_role();
  if ($role === 'Commuter') {
    header('Location: ' . $rootUrl . '/citizen/commuter/index.php');
    exit;
  }
}

$page = isset($_GET['page']) ? trim($_GET['page'], '/') : 'dashboard';
$page = preg_replace('/[^a-z0-9\/\-]/i', '', $page);
$pagesRoot = $baseDir . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR;
$pageFile = $pagesRoot . str_replace('/', DIRECTORY_SEPARATOR, $page) . '.php';
if (!is_file($pageFile)) {
  $pageFile = $pagesRoot . 'dashboard.php';
}

$currentPath = '/' . $page;
$tmm_node_allowed = function (array $node): bool {
  if (!empty($node['roles']) && is_array($node['roles'])) {
    return in_array(current_user_role(), $node['roles'], true);
  }
  if (!empty($node['anyPermissions']) && is_array($node['anyPermissions'])) {
    return has_any_permission($node['anyPermissions']);
  }
  return true;
};

$tmmDeniedMessage = null;
foreach ($sidebarItems as $item) {
  if (isset($item['path']) && $item['path'] === $currentPath) {
    if (!$tmm_node_allowed($item)) $tmmDeniedMessage = 'You do not have access to this page.';
    break;
  }
  if (!empty($item['subItems'])) {
    foreach ($item['subItems'] as $sub) {
      if ($sub['path'] === $currentPath) {
        if (!$tmm_node_allowed($sub)) $tmmDeniedMessage = 'You do not have access to this page.';
        break 2;
      }
    }
  }
}

if ($tmmDeniedMessage !== null) {
  $pageFile = $pagesRoot . 'forbidden.php';
}
$breadcrumb = ['Dashboard'];
foreach ($sidebarItems as $item) {
  if (isset($item['path']) && $item['path'] === $currentPath) {
    $breadcrumb = [$item['label']];
    break;
  }
  if (!empty($item['subItems'])) {
    foreach ($item['subItems'] as $sub) {
      if ($sub['path'] === $currentPath) {
        $breadcrumb = [$item['label'], $sub['label']];
        break 2;
      }
    }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>TMM</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/jpeg" href="includes/logo.jpg">
  <script>
    (function () {
      try {
        var stored = localStorage.getItem('theme');
        var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        var useDark = stored ? (stored === 'dark') : prefersDark;
        document.documentElement.classList.toggle('dark', useDark);
        document.body && document.body.classList.toggle('dark', useDark);
        document.documentElement.setAttribute('data-theme', useDark ? 'dark' : 'light');
      } catch (e) {}
    })();
  </script>
  <!-- Tailwind config removed - using CDN version -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <link rel="stylesheet" href="includes/unified.css">
</head>
 <body class="min-h-screen bg-slate-50 dark:bg-slate-800 transition-colors duration-200 font-sans">
  <div class="flex h-screen overflow-hidden">
    <div id="sidebar-overlay" class="fixed inset-0 bg-black/30 z-30 hidden md:hidden"></div>
    <?php include $baseDir . '/includes/sidebar.php'; ?>
    <div class="flex-1 flex flex-col">
      <?php include $baseDir . '/includes/header.php'; ?>
      <main class="flex-1 overflow-auto p-4 md:p-8 dark:bg-slate-800 text-slate-800 dark:text-slate-200">
        <?php 
        try {
          include $pageFile; 
        } catch (Throwable $e) {
          echo '<div class="p-6 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg text-red-700 dark:text-red-400">';
          echo '<h2 class="text-lg font-bold mb-2 flex items-center gap-2"><i data-lucide="alert-triangle" class="w-5 h-5"></i> Application Error</h2>';
          echo '<p class="font-mono text-sm break-all">' . htmlspecialchars($e->getMessage()) . '</p>';
          echo '</div>';
        }
        ?>
      </main>
    </div>
  </div>
  <script>
    function isMobile() { return window.matchMedia('(max-width: 767px)').matches }
    function initTheme() {
      var stored = localStorage.getItem('theme');
      if (stored) {
        document.documentElement.classList.toggle('dark', stored === 'dark');
      } else {
        var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        document.documentElement.classList.toggle('dark', prefersDark);
        localStorage.setItem('theme', prefersDark ? 'dark' : 'light');
      }
    }
    function toggleTheme() {
      var isDark = document.documentElement.classList.contains('dark');
      var next = isDark ? 'light' : 'dark';
      document.documentElement.classList.toggle('dark', next === 'dark');
      document.body.classList.toggle('dark', next === 'dark');
      document.documentElement.setAttribute('data-theme', next);
      localStorage.setItem('theme', next);
      var btnText = document.getElementById('themeState');
      if (btnText) btnText.textContent = next;
      try { window.dispatchEvent(new CustomEvent('themechange', { detail: next })); } catch(e) {}
      try { if (window.tailwind) { /* ensure repaint */ } } catch(e) {}
      setTimeout(function(){ location.reload(); }, 0);
    }
    function initSidebar() {
      var sidebar = document.getElementById('sidebar');
      var overlay = document.getElementById('sidebar-overlay');
      var labels = document.querySelectorAll('.sidebar-label');
      var chevrons = document.querySelectorAll('.sidebar-chevron');
      if (isMobile()) {
        var open = localStorage.getItem('sidebarOpenMobile') === 'true';
        sidebar.classList.toggle('-translate-x-full', !open);
        overlay.classList.toggle('hidden', !open);
        labels.forEach(el => el.classList.remove('hidden'));
        chevrons.forEach(el => el.classList.remove('hidden'));
      } else {
        var collapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        overlay.classList.add('hidden');
        if (collapsed) {
          sidebar.classList.add('w-16');
          labels.forEach(el => el.classList.add('hidden'));
          chevrons.forEach(el => el.classList.add('hidden'));
        } else {
          sidebar.classList.remove('w-16');
          labels.forEach(el => el.classList.remove('hidden'));
          chevrons.forEach(el => el.classList.remove('hidden'));
        }
        sidebar.classList.remove('-translate-x-full');
      }
    }
    function toggleSidebar() {
      var sidebar = document.getElementById('sidebar');
      var overlay = document.getElementById('sidebar-overlay');
      var labels = document.querySelectorAll('.sidebar-label');
      var chevrons = document.querySelectorAll('.sidebar-chevron');
      if (isMobile()) {
        var open = !overlay.classList.contains('hidden');
        overlay.classList.toggle('hidden', open);
        sidebar.classList.toggle('-translate-x-full', open);
        localStorage.setItem('sidebarOpenMobile', String(!open));
      } else {
        var collapsed = sidebar.classList.contains('w-16');
        if (collapsed) {
          sidebar.classList.remove('w-16');
          labels.forEach(el => el.classList.remove('hidden'));
          chevrons.forEach(el => el.classList.remove('hidden'));
          localStorage.setItem('sidebarCollapsed', 'false');
        } else {
          sidebar.classList.add('w-16');
          labels.forEach(el => el.classList.add('hidden'));
          chevrons.forEach(el => el.classList.add('hidden'));
          localStorage.setItem('sidebarCollapsed', 'true');
        }
      }
    }
    function setupExpandableNav() {
      document.querySelectorAll('[data-nav-toggle]').forEach(btn => {
        btn.addEventListener('click', function () {
          var target = document.getElementById(this.getAttribute('data-nav-toggle'));
          if (!target) return;
          var expanded = target.getAttribute('data-expanded') === 'true';
          
          // Toggle chevron rotation
          var chevron = this.querySelector('.sidebar-chevron');
          if (chevron) {
            chevron.classList.toggle('rotate-180', !expanded);
          }

          target.setAttribute('data-expanded', expanded ? 'false' : 'true');
          target.classList.toggle('hidden', expanded);
          if (!expanded) {
            var first = target.querySelector('a[data-first-subitem]');
            if (first && !first.classList.contains('bg-accent/10')) {
              // Optional: auto-redirect logic
              // window.location.href = first.href;
            }
          }
        });
      });
      var overlay = document.getElementById('sidebar-overlay');
      overlay.addEventListener('click', function () {
        var sidebar = document.getElementById('sidebar');
        overlay.classList.add('hidden');
        sidebar.classList.add('-translate-x-full');
        localStorage.setItem('sidebarOpenMobile', 'false');
      });
    }
    document.addEventListener('DOMContentLoaded', function () {
      initTheme();
      initSidebar();
      setupExpandableNav();
      if (window.lucide && window.lucide.createIcons) window.lucide.createIcons();
    });
    window.addEventListener('resize', function () { initSidebar() });
  </script>
</body>
</html>
