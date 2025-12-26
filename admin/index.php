<?php
$baseDir = __DIR__;
require_once $baseDir . '/includes/sidebar_items.php';

$page = isset($_GET['page']) ? trim($_GET['page'], '/') : 'dashboard';
$page = preg_replace('/[^a-z0-9\/\-]/i', '', $page);
$pagesRoot = $baseDir . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR;
$pageFile = $pagesRoot . str_replace('/', DIRECTORY_SEPARATOR, $page) . '.php';
if (!is_file($pageFile)) {
  $pageFile = $pagesRoot . 'dashboard.php';
}

$currentPath = '/' . $page;
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
  <link rel="icon" type="image/jpeg" href="/tmm/admin/includes/logo.jpg">
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          colors: {
            primary: '#4CAF50',
            secondary: '#4A90E2',
            accent: '#FDA811',
            bg: '#FBFBFB'
          }
        }
      }
    }
  </script>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="min-h-screen bg-bg dark:bg-slate-800 transition-colors duration-200">
  <div class="flex h-screen overflow-hidden">
    <?php include $baseDir . '/includes/sidebar.php'; ?>
    <div class="flex-1 flex flex-col">
      <?php include $baseDir . '/includes/header.php'; ?>
      <main class="flex-1 overflow-auto p-8 dark:bg-slate-800">
        <?php include $pageFile; ?>
      </main>
    </div>
  </div>
  <script>
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
      localStorage.setItem('theme', next);
      var btnText = document.getElementById('themeState');
      if (btnText) btnText.textContent = next;
    }
    function initSidebar() {
      var collapsed = localStorage.getItem('sidebarCollapsed') === 'true';
      var sidebar = document.getElementById('sidebar');
      var labels = document.querySelectorAll('.sidebar-label');
      var chevrons = document.querySelectorAll('.sidebar-chevron');
      if (collapsed) {
        sidebar.classList.remove('w-64'); sidebar.classList.add('w-16');
        labels.forEach(el => el.classList.add('hidden'));
        chevrons.forEach(el => el.classList.add('hidden'));
      } else {
        sidebar.classList.remove('w-16'); sidebar.classList.add('w-64');
        labels.forEach(el => el.classList.remove('hidden'));
        chevrons.forEach(el => el.classList.remove('hidden'));
      }
    }
    function toggleSidebar() {
      var sidebar = document.getElementById('sidebar');
      var labels = document.querySelectorAll('.sidebar-label');
      var chevrons = document.querySelectorAll('.sidebar-chevron');
      var collapsed = sidebar.classList.contains('w-16');
      if (collapsed) {
        sidebar.classList.remove('w-16'); sidebar.classList.add('w-64');
        labels.forEach(el => el.classList.remove('hidden'));
        chevrons.forEach(el => el.classList.remove('hidden'));
        localStorage.setItem('sidebarCollapsed', 'false');
      } else {
        sidebar.classList.remove('w-64'); sidebar.classList.add('w-16');
        labels.forEach(el => el.classList.add('hidden'));
        chevrons.forEach(el => el.classList.add('hidden'));
        localStorage.setItem('sidebarCollapsed', 'true');
      }
    }
    function setupExpandableNav() {
      document.querySelectorAll('[data-nav-toggle]').forEach(btn => {
        btn.addEventListener('click', function () {
          var target = document.getElementById(this.getAttribute('data-nav-toggle'));
          if (!target) return;
          var expanded = target.getAttribute('data-expanded') === 'true';
          target.setAttribute('data-expanded', expanded ? 'false' : 'true');
          target.classList.toggle('hidden', expanded);
          if (!expanded) {
            var first = target.querySelector('a[data-first-subitem]');
            if (first && !first.classList.contains('bg-accent/10')) {
              window.location.href = first.href;
            }
          }
        });
      });
    }
    document.addEventListener('DOMContentLoaded', function () {
      initTheme();
      initSidebar();
      setupExpandableNav();
      if (window.lucide && window.lucide.createIcons) window.lucide.createIcons();
    });
  </script>
</body>
</html>