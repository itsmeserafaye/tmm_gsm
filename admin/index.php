<?php
ob_start();
$baseUrl = str_replace('\\', '/', (string) dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/admin/index.php')));
$baseUrl = $baseUrl === '/' ? '' : rtrim($baseUrl, '/');
$rootUrl = preg_replace('#/admin$#', '', $baseUrl);
if (php_sapi_name() !== 'cli' && function_exists('session_status') && session_status() !== PHP_SESSION_ACTIVE) {
  @session_start();
}
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

$requestedPage = isset($_GET['page']) ? trim((string)$_GET['page'], '/') : 'dashboard';
$requestedPage = preg_replace('/[^a-z0-9\/\-]/i', '', $requestedPage);

$tmmCanonicalToLegacy = [];
$tmmLegacyToCanonical = [];
$tmmCollectRouteAliases = function (array $node) use (&$tmmCanonicalToLegacy, &$tmmLegacyToCanonical): void {
  if (!isset($node['path'], $node['page']))
    return;
  $canonical = ltrim((string)$node['path'], '/');
  $legacy = trim((string)$node['page'], '/');
  if ($canonical === '' || $legacy === '')
    return;
  $tmmCanonicalToLegacy[$canonical] = $legacy;
  $tmmLegacyToCanonical[$legacy] = $canonical;
};
foreach ($sidebarItems as $item) {
  $tmmCollectRouteAliases($item);
  if (!empty($item['subItems']) && is_array($item['subItems'])) {
    foreach ($item['subItems'] as $sub) {
      if (is_array($sub))
        $tmmCollectRouteAliases($sub);
    }
  }
}

$includePage = $requestedPage;
$currentPath = '/' . $requestedPage;
if (isset($tmmCanonicalToLegacy[$requestedPage])) {
  $includePage = $tmmCanonicalToLegacy[$requestedPage];
} elseif (isset($tmmLegacyToCanonical[$requestedPage])) {
  $currentPath = '/' . $tmmLegacyToCanonical[$requestedPage];
  if (php_sapi_name() !== 'cli' && (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET')) {
    $qs = $_GET;
    $qs['page'] = $tmmLegacyToCanonical[$requestedPage];
    header('Location: ' . $baseUrl . '/index.php?' . http_build_query($qs));
    exit;
  }
}

$includePage = preg_replace('/[^a-z0-9\/\-]/i', '', $includePage);
$pagesRoot = $baseDir . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR;
$pageFile = $pagesRoot . str_replace('/', DIRECTORY_SEPARATOR, $includePage) . '.php';
if (!is_file($pageFile)) {
  $pageFile = $pagesRoot . 'dashboard.php';
  $currentPath = '/dashboard';
}
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
    if (!$tmm_node_allowed($item))
      $tmmDeniedMessage = 'You do not have access to this page.';
    break;
  }
  if (!empty($item['subItems'])) {
    foreach ($item['subItems'] as $sub) {
      if ($sub['path'] === $currentPath) {
        if (!$tmm_node_allowed($sub))
          $tmmDeniedMessage = 'You do not have access to this page.';
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

$formJsVer = 1;
$formJsPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tmm_form_enhancements.js';
$ts = @filemtime($formJsPath);
if ($ts !== false) $formJsVer = (int)$ts;
?>
<!doctype html>
<html lang="en" class="scroll-smooth">

<head>
  <meta charset="UTF-8">
  <title>TMM - Admin Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Transport & Mobility Management System - Admin Dashboard">
  <link rel="icon" type="image/png" href="includes/GSM_logo.png">
  <script>
    (function () {
      try {
        var stored = localStorage.getItem('theme');
        var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        var useDark = stored ? (stored === 'dark') : prefersDark;
        document.documentElement.classList.toggle('dark', useDark);
        document.body && document.body.classList.toggle('dark', useDark);
        document.documentElement.setAttribute('data-theme', useDark ? 'dark' : 'light');
      } catch (e) { }
    })();
  </script>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <link rel="stylesheet" href="includes/unified.css">
  <style>
    @keyframes slideInRight {
      from { opacity: 0; transform: translateX(30px); }
      to { opacity: 1; transform: translateX(0); }
    }
    @keyframes slideInUp {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }
    @keyframes shimmer {
      0% { background-position: -1000px 0; }
      100% { background-position: 1000px 0; }
    }
    @keyframes pulse-soft {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.8; }
    }
    .animate-slide-in { animation: slideInRight 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
    .animate-slide-up { animation: slideInUp 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
    .animate-fade-in { animation: fadeIn 0.4s ease-out; }
    .animate-pulse-soft { animation: pulse-soft 2s ease-in-out infinite; }
    .hover-lift { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
    .hover-lift:hover { transform: translateY(-3px); box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.2); }
    .gradient-border {
      position: relative;
      background: linear-gradient(white, white) padding-box,
                  linear-gradient(135deg, #5ba3f5, #66bb6a) border-box;
      border: 2px solid transparent;
      border-radius: 1rem;
    }
    .dark .gradient-border {
      background: linear-gradient(rgb(30 41 59), rgb(30 41 59)) padding-box,
                  linear-gradient(135deg, #5ba3f5, #66bb6a) border-box;
    }
    .glass-effect {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
    }
    .dark .glass-effect {
      background: rgba(30, 41, 59, 0.95);
    }
    .content-wrapper {
      animation: slideInUp 0.5s ease-out;
    }
  </style>
  <script>
    window.TMM_ROOT_URL = <?php echo json_encode($rootUrl, JSON_UNESCAPED_SLASHES); ?>;
    window.TMM_ADMIN_BASE_URL = <?php echo json_encode($baseUrl, JSON_UNESCAPED_SLASHES); ?>;
  </script>
  <script src="<?php echo htmlspecialchars($rootUrl); ?>/tmm_form_enhancements.js?v=<?php echo (string)$formJsVer; ?>" defer></script>
</head>

<body class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50/40 to-green-50/30 dark:from-slate-950 dark:via-slate-900 dark:to-slate-900 transition-all duration-300 font-sans antialiased">
  <!-- Background Pattern -->
  <div class="fixed inset-0 opacity-[0.03] dark:opacity-[0.02] pointer-events-none" style="background-image: radial-gradient(circle at 1px 1px, rgb(100, 100, 100) 1px, transparent 0); background-size: 40px 40px;"></div>
  
  <div class="flex h-screen overflow-hidden relative z-10">
    <div id="sidebar-overlay" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-30 hidden md:hidden transition-opacity duration-300"></div>
    <?php include $baseDir . '/includes/sidebar.php'; ?>
    <div class="flex-1 flex flex-col min-w-0">
      <?php include $baseDir . '/includes/header.php'; ?>
      <main class="flex-1 overflow-auto p-4 md:p-6 lg:p-8 text-slate-800 dark:text-slate-200">
        <div class="content-wrapper max-w-[1600px] mx-auto">
          <?php
          try {
            include $pageFile;
          } catch (Throwable $e) {
            echo '<div class="p-6 bg-gradient-to-br from-red-50 to-red-100 dark:from-red-900/30 dark:to-red-900/20 border-2 border-red-200 dark:border-red-800 rounded-2xl text-red-700 dark:text-red-400 shadow-lg animate-slide-up">';
            echo '<h2 class="text-lg font-bold mb-3 flex items-center gap-2"><i data-lucide="alert-triangle" class="w-6 h-6"></i> Application Error</h2>';
            echo '<p class="font-mono text-sm break-all bg-white/50 dark:bg-black/20 p-3 rounded-lg">' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '</div>';
          }
          ?>
        </div>
      </main>
    </div>
  </div>
  <div id="tmm-session-toast" class="hidden fixed bottom-6 left-4 right-4 sm:left-auto sm:right-6 sm:w-[440px] z-[100] animate-slide-in">
    <div class="pointer-events-auto px-6 py-5 rounded-2xl shadow-2xl border-2 glass-effect hover-lift" style="border-color: #fbbf24; background: linear-gradient(135deg, rgba(254, 243, 199, 0.98), rgba(253, 230, 138, 0.98)); backdrop-filter: blur(20px);">
      <div class="flex items-start justify-between gap-4">
        <div class="flex items-start gap-4">
          <div class="mt-0.5 p-3 rounded-xl shadow-lg" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
            <i data-lucide="timer" class="w-6 h-6 text-white"></i>
          </div>
          <div class="flex-1">
            <div class="text-base font-black text-amber-900 dark:text-amber-950 mb-1">Session Expiring Soon</div>
            <div id="tmm-session-toast-msg" class="text-sm font-semibold text-amber-800 dark:text-amber-900"></div>
          </div>
        </div>
        <button type="button" id="tmm-session-toast-close" class="p-2 rounded-lg hover:bg-amber-200/50 text-amber-700 hover:text-amber-900 transition-all duration-200">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>
      <div class="mt-5 flex items-center justify-end gap-3">
        <button type="button" id="tmm-session-stay" class="px-6 py-3 rounded-xl font-bold text-white shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200" style="background: linear-gradient(135deg, #f59e0b, #d97706);">Stay Logged In</button>
      </div>
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
      try { window.dispatchEvent(new CustomEvent('themechange', { detail: next })); } catch (e) { }
      try { if (window.tailwind) { /* ensure repaint */ } } catch (e) { }
      setTimeout(function () { location.reload(); }, 0);
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

      (function () {
        var timeoutSec = <?php echo json_encode((int)tmm_session_timeout_seconds()); ?>;
        var warnSecRaw = <?php echo json_encode((int)trim((string)tmm_get_app_setting('session_warning_seconds', '30'))); ?>;
        var warnSec = warnSecRaw > 0 ? warnSecRaw : 30;
        warnSec = Math.max(10, Math.min(120, warnSec));
        warnSec = Math.min(warnSec, Math.max(10, timeoutSec - 5));
        var toast = document.getElementById('tmm-session-toast');
        var toastMsg = document.getElementById('tmm-session-toast-msg');
        var btnStay = document.getElementById('tmm-session-stay');
        var btnClose = document.getElementById('tmm-session-toast-close');
        var logoutUrl = (window.TMM_ROOT_URL || '') + '/gsm_login/Login/login.php?logout=true';
        var pingUrl = (window.TMM_ROOT_URL || '') + '/admin/api/auth/ping.php';
        var lastActivityMs = Date.now();
        var showing = false;
        var tickId = null;

        function hideToast() {
          if (!toast) return;
          toast.classList.add('hidden');
          showing = false;
        }

        function showToast(secLeft) {
          if (!toast || !toastMsg) return;
          showing = true;
          toast.classList.remove('hidden');
          toastMsg.textContent = 'Logging out in ' + String(secLeft) + ' seconds due to inactivity.';
          try { if (window.lucide && window.lucide.createIcons) window.lucide.createIcons(); } catch (e) {}
        }

        function resetActivity() {
          lastActivityMs = Date.now();
          if (showing) hideToast();
        }

        function ping() {
          try {
            return fetch(pingUrl, { headers: { 'Accept': 'application/json' } })
              .then(function (r) { return r.json(); })
              .then(function () { resetActivity(); })
              .catch(function () { resetActivity(); });
          } catch (e) {
            resetActivity();
            return Promise.resolve();
          }
        }

        function tick() {
          var idleSec = Math.floor((Date.now() - lastActivityMs) / 1000);
          var remaining = timeoutSec - idleSec;
          if (remaining <= 0) {
            window.location.href = logoutUrl;
            return;
          }
          if (remaining <= warnSec) {
            showToast(remaining);
          } else if (showing) {
            hideToast();
          }
        }

        ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'click'].forEach(function (evt) {
          document.addEventListener(evt, resetActivity, { passive: true });
        });
        if (btnStay) btnStay.addEventListener('click', function () { ping(); });
        if (btnClose) btnClose.addEventListener('click', function () { hideToast(); });

        tickId = window.setInterval(tick, 1000);
      })();
    });
    window.addEventListener('resize', function () { initSidebar() });
  </script>
</body>

</html>
