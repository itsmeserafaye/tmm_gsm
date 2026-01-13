<?php
require_once __DIR__ . '/admin/includes/db.php';
require_once __DIR__ . '/includes/rbac.php';

$db = db();
rbac_ensure_schema($db);

$baseUrl = str_replace('\\', '/', (string)dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/index.php')));
$baseUrl = $baseUrl === '/' ? '' : rtrim($baseUrl, '/');

if (!isset($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!empty($_SESSION['user_id'])) {
  header('Location: ' . $baseUrl . '/admin/index.php');
  exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['csrf_token'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    $error = 'Invalid request. Please try again.';
  } else {
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
      $error = 'Please enter your email and password.';
    } else {
      $user = rbac_get_user_by_email($db, $email);
      $ok = false;
      if ($user && (($user['status'] ?? '') === 'Active') && password_verify($password, (string)($user['password_hash'] ?? ''))) {
        $ok = true;
        session_regenerate_id(true);
        $userId = (int)$user['id'];
        $roles = rbac_get_user_roles($db, $userId);
        $perms = rbac_get_user_permissions($db, $userId);
        $primaryRole = rbac_primary_role($roles);

        $_SESSION['user_id'] = $userId;
        $_SESSION['email'] = $user['email'];
        $_SESSION['name'] = trim((string)($user['first_name'] ?? '') . ' ' . (string)($user['last_name'] ?? ''));
        $_SESSION['role'] = $primaryRole;
        $_SESSION['roles'] = $roles;
        $_SESSION['permissions'] = $perms;

        $stmt = $db->prepare("UPDATE rbac_users SET last_login_at=NOW() WHERE id=?");
        if ($stmt) {
          $stmt->bind_param('i', $userId);
          $stmt->execute();
          $stmt->close();
        }
        rbac_write_login_audit($db, $userId, $email, true);
        header('Location: ' . $baseUrl . '/admin/index.php');
        exit;
      }
      rbac_write_login_audit($db, $user ? (int)$user['id'] : null, $email !== '' ? $email : null, false);
      if (!$ok) {
        $error = 'Invalid email or password.';
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Government Services Management System - Login</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/gsm_login/Login/styles.css">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-custom-bg min-h-screen flex flex-col">
  <header class="py-2">
    <div class="container mx-auto px-6">
      <div class="flex justify-between items-center">
        <div class="flex items-center space-x-4">
          <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center shadow-lg">
            <img src="<?php echo htmlspecialchars($baseUrl); ?>/gsm_login/Login/images/GSM_logo.png" alt="GSM Logo" class="h-10 w-auto">
          </div>
          <h1 class="text-3xl lg:text-4xl font-bold" style="font-weight: 700;">
            <span class="brand-go">Go</span><span class="brand-serve">Serve</span><span class="brand-ph">PH</span>
          </h1>
        </div>
        <div class="text-right">
          <div class="text-sm">
            <div id="currentDateTime" class="font-semibold"></div>
          </div>
        </div>
      </div>
    </div>
  </header>

  <main class="container mx-auto px-6 pt-4 pb-12 flex-1">
    <div class="grid lg:grid-cols-2 gap-12 items-center">
      <div class="text-center lg:text-left mt-2">
        <h2 class="text-4xl lg:text-5xl font-bold mb-4 animated-gradient ml-2 lg:ml-4">
          Abot-Kamay mo ang Serbisyong Publiko!
        </h2>
      </div>

      <div class="bg-white rounded-2xl shadow-2xl p-6 max-w-sm mx-auto w-full glass-card glow-on-hover mt-8">
        <?php if ($error !== ''): ?>
          <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
            <?php echo htmlspecialchars($error); ?>
          </div>
        <?php endif; ?>

        <form method="post" class="space-y-5 form-compact" autocomplete="on">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
          <div>
            <input
              type="email"
              id="email"
              name="email"
              placeholder="Enter e-mail address"
              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-custom-secondary focus:border-transparent transition-all duration-200"
              required
            >
          </div>
          <div>
            <input
              type="password"
              id="password"
              name="password"
              placeholder="Enter password"
              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-custom-secondary focus:border-transparent transition-all duration-200"
              required
            >
          </div>
          <button
            type="submit"
            class="w-full bg-custom-secondary text-white py-3 px-6 rounded-lg font-semibold btn-primary"
          >
            Login
          </button>
          <div class="text-center">
            <p class="text-gray-600 text-sm">
              Accounts are issued by the City Government ICT Office.
            </p>
          </div>
        </form>
      </div>
    </div>
  </main>

  <footer class="bg-custom-primary text-white py-4 mt-8">
    <div class="container mx-auto px-6">
      <div class="flex flex-col lg:flex-row justify-between items-center">
        <div class="text-center lg:text-left mb-2 lg:mb-0">
          <h3 class="text-lg font-bold mb-1">Government Services Management System</h3>
          <p class="text-xs opacity-90">
            For any inquiries, please call 122 or email helpdesk@gov.ph
          </p>
        </div>
        <div class="flex items-center space-x-4">
          <div class="flex space-x-3">
            <span class="text-xs">TERMS OF SERVICE</span>
            <span>|</span>
            <span class="text-xs">PRIVACY POLICY</span>
          </div>
        </div>
      </div>
    </div>
  </footer>

  <script>
    (function () {
      function updateDateTime() {
        var now = new Date();
        var options = {
          weekday: 'long',
          year: 'numeric',
          month: 'long',
          day: 'numeric',
          hour: '2-digit',
          minute: '2-digit',
          second: '2-digit',
          hour12: true
        };
        var el = document.getElementById('currentDateTime');
        if (el) el.textContent = now.toLocaleDateString('en-US', options).toUpperCase();
      }
      updateDateTime();
      setInterval(updateDateTime, 1000);

      // Toggle Password Visibility
      var togglePassword = document.getElementById('togglePassword');
      var password = document.getElementById('password');
      if (togglePassword && password) {
        togglePassword.addEventListener('click', function() {
          var type = password.getAttribute('type') === 'password' ? 'text' : 'password';
          password.setAttribute('type', type);
          this.querySelector('i').classList.toggle('fa-eye');
          this.querySelector('i').classList.toggle('fa-eye-slash');
        });
      }
    })();
  </script>
</body>
</html>
