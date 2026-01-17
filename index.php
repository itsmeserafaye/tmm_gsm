<?php
require_once __DIR__ . '/admin/includes/db.php';
require_once __DIR__ . '/includes/recaptcha.php';

$db = db();

$baseUrl = str_replace('\\', '/', (string)dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/index.php')));
$baseUrl = $baseUrl === '/' ? '' : rtrim($baseUrl, '/');

$recaptchaCfg = recaptcha_config($db);
$recaptchaSiteKey = (string)($recaptchaCfg['site_key'] ?? '');

if (!empty($_SESSION['operator_user_id'])) {
  header('Location: ' . $baseUrl . '/citizen/operator/index.php');
  exit;
}
if (!empty($_SESSION['user_id'])) {
  $role = (string)($_SESSION['role'] ?? '');
  if ($role === 'Commuter') {
    header('Location: ' . $baseUrl . '/citizen/commuter/index.php');
  } else {
    header('Location: ' . $baseUrl . '/admin/index.php');
  }
  exit;
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>GoServePH - Welcome</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/gsm_login/Login/styles.css">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
<body class="bg-custom-bg min-h-screen flex flex-col">
  <header class="py-3">
    <div class="container mx-auto px-6">
      <div class="flex justify-between items-center">
        <div class="flex items-center space-x-4">
          <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center shadow-lg">
            <img src="<?php echo htmlspecialchars($baseUrl); ?>/gsm_login/Login/images/GSM_logo.png" alt="GSM Logo" class="h-10 w-auto">
          </div>
          <div class="leading-tight">
            <h1 class="text-xl lg:text-2xl font-bold" style="font-weight: 700;">
              <span class="brand-go">Go</span><span class="brand-serve">Serve</span><span class="brand-ph">PH</span>
            </h1>
            <div class="text-xs text-slate-500">Abot-Kamay mo ang Serbisyong Publiko</div>
          </div>
        </div>
        <div class="flex items-center gap-8">
          <nav class="hidden md:flex items-center gap-6 text-sm font-semibold text-slate-600">
            <a href="#home" class="hover:text-custom-secondary">Home</a>
            <a href="#systems" class="hover:text-custom-secondary">Systems</a>
          </nav>
          <div class="text-right">
            <div class="text-sm">
              <div id="currentDateTime" class="font-semibold"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </header>

  <main class="flex-1">
    <section id="home" class="container mx-auto px-6 pt-6">
      <div class="relative overflow-hidden rounded-2xl shadow-2xl">
        <div class="absolute inset-0 bg-center bg-cover" style="background-image: url('<?php echo htmlspecialchars($baseUrl); ?>/gsm_login/Login/images/gsmbg.png');"></div>
        <div class="absolute inset-0 bg-white/65"></div>
        <div class="relative px-6 py-14 md:py-20">
          <div class="flex flex-col items-center text-center">
            <div class="w-20 h-20 md:w-24 md:h-24 bg-white rounded-full flex items-center justify-center shadow-lg mb-5">
              <img src="<?php echo htmlspecialchars($baseUrl); ?>/gsm_login/Login/images/GSM_logo.png" alt="GoServePH" class="h-16 md:h-20 w-auto">
            </div>
            <div class="text-5xl md:text-6xl font-extrabold tracking-tight">
              <span class="brand-go">Go</span><span class="brand-serve">Serve</span><span class="brand-ph">PH</span>
            </div>
            <div class="mt-2 text-lg md:text-xl font-semibold text-slate-700">
              Serbisyong Publiko, Abot-Kamay mo
            </div>
            <div class="mt-6">
              <a href="#systems" class="inline-flex items-center gap-2 bg-custom-secondary text-white px-5 py-3 rounded-xl font-semibold btn-primary">
                View systems
                <i class="fas fa-arrow-down text-sm"></i>
              </a>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section id="systems" class="container mx-auto px-6 py-12">
      <div class="text-center">
        <h2 class="text-3xl md:text-4xl font-bold text-slate-900">All Government Systems</h2>
        <div class="mt-2 text-sm text-slate-600">Click any system to access its dedicated portal</div>
      </div>

      <div class="mt-10 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-2xl shadow-lg border border-slate-100 p-6 hover:shadow-2xl transition-all">
          <div class="flex items-center justify-center">
            <div class="w-16 h-16 rounded-2xl bg-slate-50 flex items-center justify-center">
              <i class="fas fa-user-shield text-custom-secondary text-2xl"></i>
            </div>
          </div>
          <div class="mt-5 text-center">
            <div class="text-lg font-bold text-slate-900">Staff Portal</div>
            <div class="mt-1 text-sm text-slate-600">Administration and management access.</div>
            <a href="<?php echo htmlspecialchars($baseUrl); ?>/gsm_login/index.php?mode=staff" class="mt-5 inline-flex items-center justify-center gap-2 text-custom-secondary font-semibold hover:underline">
              Access System <i class="fas fa-arrow-right text-xs"></i>
            </a>
          </div>
        </div>

        <div class="bg-white rounded-2xl shadow-lg border border-slate-100 p-6 hover:shadow-2xl transition-all">
          <div class="flex items-center justify-center">
            <div class="w-16 h-16 rounded-2xl bg-slate-50 flex items-center justify-center">
              <i class="fas fa-bus text-custom-secondary text-2xl"></i>
            </div>
          </div>
          <div class="mt-5 text-center">
            <div class="text-lg font-bold text-slate-900">Operator Portal</div>
            <div class="mt-1 text-sm text-slate-600">PUV operator services. Plate number required.</div>
            <div class="mt-5 flex items-center justify-center gap-4">
              <a href="<?php echo htmlspecialchars($baseUrl); ?>/gsm_login/index.php?mode=operator" class="inline-flex items-center justify-center gap-2 text-custom-secondary font-semibold hover:underline">
                Access System <i class="fas fa-arrow-right text-xs"></i>
              </a>
              <button type="button" id="btnOperatorRegisterOpen" class="text-custom-secondary font-semibold hover:underline">Register</button>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-2xl shadow-lg border border-slate-100 p-6 hover:shadow-2xl transition-all">
          <div class="flex items-center justify-center">
            <div class="w-16 h-16 rounded-2xl bg-slate-50 flex items-center justify-center">
              <i class="fas fa-users text-custom-secondary text-2xl"></i>
            </div>
          </div>
          <div class="mt-5 text-center">
            <div class="text-lg font-bold text-slate-900">Commuter Portal</div>
            <div class="mt-1 text-sm text-slate-600">Citizen services for commuters. No plate number required.</div>
            <div class="mt-5 flex items-center justify-center gap-4">
              <a href="<?php echo htmlspecialchars($baseUrl); ?>/gsm_login/index.php?mode=commuter" class="inline-flex items-center justify-center gap-2 text-custom-secondary font-semibold hover:underline">
                Access System <i class="fas fa-arrow-right text-xs"></i>
              </a>
              <button type="button" id="showRegister" class="text-custom-secondary font-semibold hover:underline">Register</button>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="container mx-auto px-6 pb-12">
      <div class="relative overflow-hidden rounded-2xl shadow-xl">
        <div class="absolute inset-0 bg-center bg-cover" style="background-image: url('<?php echo htmlspecialchars($baseUrl); ?>/gsm_login/Login/images/gsmbg.png');"></div>
        <div class="absolute inset-0 bg-white/80"></div>
        <div class="relative px-6 py-12">
          <div class="text-center">
            <div class="text-2xl md:text-3xl font-bold text-slate-900">Simple Access Process</div>
          </div>
          <div class="mt-10 grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="text-center">
              <div class="mx-auto w-12 h-12 rounded-full bg-custom-primary text-white flex items-center justify-center font-bold">1</div>
              <div class="mt-3 font-semibold text-slate-900">Browse</div>
              <div class="mt-1 text-sm text-slate-600">Find the service you need</div>
            </div>
            <div class="text-center">
              <div class="mx-auto w-12 h-12 rounded-full bg-custom-secondary text-white flex items-center justify-center font-bold">2</div>
              <div class="mt-3 font-semibold text-slate-900">Click</div>
              <div class="mt-1 text-sm text-slate-600">Go directly to the system</div>
            </div>
            <div class="text-center">
              <div class="mx-auto w-12 h-12 rounded-full bg-custom-accent text-white flex items-center justify-center font-bold">3</div>
              <div class="mt-3 font-semibold text-slate-900">Use</div>
              <div class="mt-1 text-sm text-slate-600">Access the specific service</div>
            </div>
          </div>
        </div>
      </div>
    </section>
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
            <button type="button" id="footerTerms" class="text-xs hover:underline">TERMS OF SERVICE</button>
            <span>|</span>
            <button type="button" id="footerPrivacy" class="text-xs hover:underline">PRIVACY POLICY</button>
          </div>
        </div>
      </div>
    </div>
  </footer>

  <div class="fixed bottom-6 right-6 z-50">
    <div class="bg-white border border-slate-200 shadow-lg rounded-lg p-1 flex items-center gap-1">
      <button type="button" id="langEN" class="px-3 py-1.5 rounded-md text-xs font-bold bg-custom-primary text-white">EN</button>
      <button type="button" id="langFIL" class="px-3 py-1.5 rounded-md text-xs font-bold text-slate-700 hover:bg-slate-100">FIL</button>
    </div>
  </div>

    <div id="registerFormContainer" class="fixed inset-0 bg-black/40 flex items-start justify-center pt-20 px-4 hidden overflow-y-auto z-50">
        <div class="bg-white rounded-2xl shadow-2xl p-6 max-w-2xl w-full glass-card form-compact max-h-[80vh] overflow-y-auto">
            <div class="sticky top-0 bg-white/95 backdrop-blur border-b border-gray-200 z-10 -mx-6 px-6 py-3 text-center">
                <h2 class="text-xl md:text-2xl font-semibold text-custom-secondary">Create your GoServePH account</h2>
                <div class="mt-1 text-xs text-gray-500">
                    Registering as an operator?
                    <button type="button" id="openOperatorRegisterFromCitizen" class="text-custom-secondary font-semibold hover:underline">Register here</button>
                </div>
            </div>
            <form id="registerForm" class="space-y-5 pt-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm mb-1">First Name<span class="required-asterisk">*</span></label>
                        <input type="text" name="firstName" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Last Name<span class="required-asterisk">*</span></label>
                        <input type="text" name="lastName" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Middle Name<span id="middleAsterisk" class="required-asterisk">*</span></label>
                        <input type="text" id="middleName" name="middleName" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <label class="inline-flex items-center mt-2 text-sm">
                            <input type="checkbox" id="noMiddleName" class="mr-2"> No middle name
                        </label>
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Suffix</label>
                        <input type="text" name="suffix" placeholder="Jr., Sr., III (optional)" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Birthdate<span class="required-asterisk">*</span></label>
                        <input type="date" name="birthdate" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Email Address<span class="required-asterisk">*</span></label>
                        <input type="email" name="regEmail" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Mobile Number<span class="required-asterisk">*</span></label>
                        <input type="tel" name="mobile" required class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="09XXXXXXXXX">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm mb-1">Address<span class="required-asterisk">*</span></label>
                        <input type="text" name="address" required class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="Lot/Unit, Building, Subdivision">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">House #<span class="required-asterisk">*</span></label>
                        <input type="text" name="houseNumber" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Street<span class="required-asterisk">*</span></label>
                        <input type="text" name="street" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm mb-1">Barangay<span class="required-asterisk">*</span></label>
                        <input type="text" name="barangay" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Password<span class="required-asterisk">*</span></label>
                        <div class="relative">
                            <input type="password" id="regPassword" name="regPassword" minlength="10" required class="w-full px-3 py-2 border border-gray-300 rounded-lg pr-10" aria-describedby="pwdChecklist">
                            <button type="button" class="absolute inset-y-0 right-0 px-3 text-gray-500 hover:text-gray-700 toggle-password" aria-label="Toggle password visibility" data-target="regPassword">
                                <i class="far fa-eye"></i>
                            </button>
                        </div>
                        <ul id="pwdChecklist" class="text-xs text-gray-600 mt-2 space-y-1">
                            <li class="req-item" data-check="length"><span class="req-dot"></span> At least 10 characters</li>
                            <li class="req-item" data-check="upper"><span class="req-dot"></span> Has uppercase letter</li>
                            <li class="req-item" data-check="lower"><span class="req-dot"></span> Has lowercase letter</li>
                            <li class="req-item" data-check="number"><span class="req-dot"></span> Has a number</li>
                            <li class="req-item" data-check="special"><span class="req-dot"></span> Has a special character</li>
                        </ul>
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Confirm Password<span class="required-asterisk">*</span></label>
                        <div class="relative">
                            <input type="password" id="confirmPassword" name="confirmPassword" minlength="10" required class="w-full px-3 py-2 border border-gray-300 rounded-lg pr-10">
                            <button type="button" class="absolute inset-y-0 right-0 px-3 text-gray-500 hover:text-gray-700 toggle-password" aria-label="Toggle confirm password visibility" data-target="confirmPassword">
                                <i class="far fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <?php if ($recaptchaSiteKey !== ''): ?>
                <div>
                    <div id="citizenRecaptcha" data-sitekey="<?php echo htmlspecialchars($recaptchaSiteKey); ?>"></div>
                </div>
                <?php endif; ?>

                <div class="space-y-2">
                    <div class="flex items-center text-sm">
                        <label class="inline-flex items-center">
                            <input type="checkbox" id="agreeTerms" class="mr-2" required>
                            <span>I have read, understood, and agreed to the</span>
                        </label>
                        <button type="button" id="openTerms" class="ml-2 text-custom-secondary hover:underline">Terms of Use</button>
                    </div>
                    <div class="flex items-center text-sm">
                        <label class="inline-flex items-center">
                            <input type="checkbox" id="agreePrivacy" class="mr-2" required>
                            <span>I have read, understood, and agreed to the</span>
                        </label>
                        <button type="button" id="openPrivacy" class="ml-2 text-custom-secondary hover:underline">Data Privacy Policy</button>
                    </div>
                    <p class="text-xs text-gray-600">By clicking on the register button below, I hereby agree to both the Terms of Use and Data Privacy Policy</p>
                </div>

                <div class="flex justify-end space-x-3 pt-2">
                    <button type="button" id="cancelRegister" class="bg-red-500 text-white px-4 py-2 rounded-lg">Cancel</button>
                    <button type="submit" class="bg-custom-secondary text-white px-4 py-2 rounded-lg">Register</button>
                </div>
            </form>
        </div>
    </div>

    <!-- OTP Modal -->
    <div id="otpModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center p-4 z-50">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-6">
            <h3 class="text-xl font-semibold mb-2 text-center">Two-Factor Verification</h3>
            <p class="text-sm text-gray-600 mb-4 text-center">Please check your registered email for your OTP. You have <span id="otpTimer" class="font-semibold text-custom-secondary">03:00</span> to enter it.</p>
            <form id="otpForm" class="space-y-4">
                <div>
                    <label class="block text-sm mb-2 text-center">Enter OTP</label>
                    <div class="flex justify-center space-x-2" id="otpInputs">
                        <input type="text" class="otp-input" inputmode="numeric" maxlength="1" aria-label="Digit 1">
                        <input type="text" class="otp-input" inputmode="numeric" maxlength="1" aria-label="Digit 2">
                        <input type="text" class="otp-input" inputmode="numeric" maxlength="1" aria-label="Digit 3">
                        <input type="text" class="otp-input" inputmode="numeric" maxlength="1" aria-label="Digit 4">
                        <input type="text" class="otp-input" inputmode="numeric" maxlength="1" aria-label="Digit 5">
                        <input type="text" class="otp-input" inputmode="numeric" maxlength="1" aria-label="Digit 6">
                    </div>
                </div>
                <div id="otpError" class="text-red-500 text-sm hidden">Invalid or expired OTP.</div>
                <div class="flex justify-between items-center">
                    <button type="button" id="cancelOtp" class="px-4 py-2 rounded-lg bg-red-500 text-white">Cancel</button>
                    <div class="space-x-2">
                        <button type="button" id="resendOtp" class="px-4 py-2 rounded-lg bg-gray-200 text-gray-800" disabled>Resend OTP</button>
                        <button type="submit" id="submitOtp" class="px-4 py-2 rounded-lg bg-custom-secondary text-white">Verify</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div id="forgotPasswordModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center p-4 z-50">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-6">
            <h3 class="text-xl font-semibold mb-2 text-center">Reset Password</h3>
            <p class="text-sm text-gray-600 mb-4 text-center">Enter your email. We will send a 6-digit OTP to reset your password.</p>
            <form id="forgotPasswordForm" class="space-y-4">
                <div>
                    <label class="block text-sm mb-1">Email Address</label>
                    <input type="email" id="fpEmail" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="you@email.com" required>
                </div>
                <div class="flex justify-end">
                    <button type="button" id="fpSendOtp" class="px-4 py-2 rounded-lg bg-custom-secondary text-white font-semibold">Send OTP</button>
                </div>

                <div id="fpStep2" class="hidden space-y-4 pt-2 border-t">
                    <div>
                        <label class="block text-sm mb-2 text-center">Enter OTP</label>
                        <div class="flex justify-center space-x-2" id="fpOtpInputs">
                            <input type="text" class="otp-input" inputmode="numeric" maxlength="1" aria-label="Digit 1">
                            <input type="text" class="otp-input" inputmode="numeric" maxlength="1" aria-label="Digit 2">
                            <input type="text" class="otp-input" inputmode="numeric" maxlength="1" aria-label="Digit 3">
                            <input type="text" class="otp-input" inputmode="numeric" maxlength="1" aria-label="Digit 4">
                            <input type="text" class="otp-input" inputmode="numeric" maxlength="1" aria-label="Digit 5">
                            <input type="text" class="otp-input" inputmode="numeric" maxlength="1" aria-label="Digit 6">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm mb-1">New Password</label>
                        <div class="relative">
                            <input type="password" id="fpNewPassword" minlength="10" class="w-full px-3 py-2 border border-gray-300 rounded-lg pr-10" required>
                            <button type="button" class="absolute inset-y-0 right-0 px-3 text-gray-500 hover:text-gray-700 toggle-password" data-target="fpNewPassword">
                                <i class="far fa-eye"></i>
                            </button>
                        </div>
                        <ul id="fpPwdChecklist" class="text-xs text-gray-600 mt-2 space-y-1">
                            <li class="req-item" data-check="length"><span class="req-dot"></span> At least 10 characters</li>
                            <li class="req-item" data-check="upper"><span class="req-dot"></span> Has uppercase letter</li>
                            <li class="req-item" data-check="lower"><span class="req-dot"></span> Has lowercase letter</li>
                            <li class="req-item" data-check="number"><span class="req-dot"></span> Has a number</li>
                            <li class="req-item" data-check="special"><span class="req-dot"></span> Has a special character</li>
                        </ul>
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Confirm New Password</label>
                        <div class="relative">
                            <input type="password" id="fpConfirmPassword" minlength="10" class="w-full px-3 py-2 border border-gray-300 rounded-lg pr-10" required>
                            <button type="button" class="absolute inset-y-0 right-0 px-3 text-gray-500 hover:text-gray-700 toggle-password" data-target="fpConfirmPassword">
                                <i class="far fa-eye"></i>
                            </button>
                        </div>
                        <div id="fpConfirmError" class="text-red-500 text-sm mt-1 hidden">Passwords do not match.</div>
                    </div>
                    <div class="flex justify-between items-center">
                        <button type="button" id="fpCancel" class="px-4 py-2 rounded-lg bg-red-500 text-white">Cancel</button>
                        <button type="submit" id="fpSubmit" class="px-4 py-2 rounded-lg bg-custom-secondary text-white">Reset Password</button>
                    </div>
                </div>
            </form>
            <div class="flex justify-end pt-3">
                <button type="button" id="fpClose" class="px-4 py-2 rounded-lg bg-gray-200 text-gray-800">Close</button>
            </div>
        </div>
    </div>

    <div id="operatorLoginModal" class="fixed inset-0 bg-black/40 hidden items-start justify-center pt-20 px-4 overflow-y-auto z-50">
        <div class="bg-white rounded-2xl shadow-2xl p-6 max-w-md w-full glass-card form-compact">
            <div class="sticky top-0 bg-white/95 backdrop-blur border-b border-gray-200 z-10 -mx-6 px-6 py-3 text-center">
                <h2 class="text-xl md:text-2xl font-semibold text-custom-secondary">Operator Login</h2>
                <div class="mt-1 text-xs text-gray-500">
                    No operator account yet?
                    <button type="button" id="openOperatorRegisterFromLogin" class="text-custom-secondary font-semibold hover:underline">Register as operator</button>
                </div>
            </div>
            <form id="operatorLoginForm" method="post" class="space-y-4 pt-4" autocomplete="on">
                <input type="hidden" name="login_type" value="operator">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div>
                    <label class="block text-sm mb-1">Plate Number<span class="required-asterisk">*</span></label>
                    <input type="text" id="opLoginPlate" name="plate_number" required class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="ABC-1234">
                </div>
                <div>
                    <label class="block text-sm mb-1">Email Address<span class="required-asterisk">*</span></label>
                    <input type="email" id="opLoginEmail" name="email" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                </div>
                <div>
                    <label class="block text-sm mb-1">Password<span class="required-asterisk">*</span></label>
                    <div class="relative">
                        <input type="password" id="opLoginPassword" name="password" required class="w-full px-3 py-2 border border-gray-300 rounded-lg pr-10">
                        <button type="button" class="absolute inset-y-0 right-0 px-3 text-gray-500 hover:text-gray-700 toggle-password" aria-label="Toggle password visibility" data-target="opLoginPassword">
                            <i class="far fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="flex items-center justify-between text-xs text-gray-600 pt-1">
                    <label class="inline-flex items-center">
                        <input type="checkbox" id="rememberMe" class="mr-2">
                        <span>Remember me</span>
                    </label>
                    <button type="button" id="openForgotPassword" class="text-custom-secondary hover:underline">Forgot password?</button>
                </div>
                <div class="flex justify-end space-x-3 pt-3">
                    <button type="button" id="btnOperatorLoginCancel" class="bg-red-500 text-white px-4 py-2 rounded-lg">Cancel</button>
                    <button type="submit" id="btnOperatorLoginSubmit" class="bg-custom-secondary text-white px-4 py-2 rounded-lg">Login</button>
                </div>
            </form>
        </div>
    </div>

    <div id="operatorRegisterModal" class="fixed inset-0 bg-black/40 hidden items-start justify-center pt-20 px-4 overflow-y-auto z-50">
        <div class="bg-white rounded-2xl shadow-2xl p-6 max-w-md w-full glass-card form-compact">
            <div class="sticky top-0 bg-white/95 backdrop-blur border-b border-gray-200 z-10 -mx-6 px-6 py-3 text-center">
                <h2 class="text-xl md:text-2xl font-semibold text-custom-secondary">Register as Operator</h2>
            </div>
            <form id="operatorRegisterForm" class="space-y-4 pt-4">
                <div>
                    <label class="block text-sm mb-1">Plate Number<span class="required-asterisk">*</span></label>
                    <input type="text" name="plate_number" required class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="ABC-1234">
                </div>
                <div>
                    <label class="block text-sm mb-1">Full Name<span class="required-asterisk">*</span></label>
                    <input type="text" name="full_name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="Juan Dela Cruz">
                </div>
                <div>
                    <label class="block text-sm mb-1">Email Address<span class="required-asterisk">*</span></label>
                    <input type="email" name="email" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                </div>
                <div>
                    <label class="block text-sm mb-1">Password<span class="required-asterisk">*</span></label>
                    <div class="relative">
                        <input type="password" id="opRegPassword" name="password" minlength="10" required class="w-full px-3 py-2 border border-gray-300 rounded-lg pr-10" aria-describedby="opPwdChecklist">
                        <button type="button" class="absolute inset-y-0 right-0 px-3 text-gray-500 hover:text-gray-700 toggle-password" aria-label="Toggle password visibility" data-target="opRegPassword">
                            <i class="far fa-eye"></i>
                        </button>
                    </div>
                    <ul id="opPwdChecklist" class="text-xs text-gray-600 mt-2 space-y-1">
                        <li class="req-item" data-check="length"><span class="req-dot"></span> At least 10 characters</li>
                        <li class="req-item" data-check="upper"><span class="req-dot"></span> Has uppercase letter</li>
                        <li class="req-item" data-check="lower"><span class="req-dot"></span> Has lowercase letter</li>
                        <li class="req-item" data-check="number"><span class="req-dot"></span> Has a number</li>
                        <li class="req-item" data-check="special"><span class="req-dot"></span> Has a special character</li>
                    </ul>
                </div>
                <div>
                    <label class="block text-sm mb-1">Confirm Password<span class="required-asterisk">*</span></label>
                    <div class="relative">
                        <input type="password" id="opRegConfirmPassword" name="confirm_password" minlength="10" required class="w-full px-3 py-2 border border-gray-300 rounded-lg pr-10">
                        <button type="button" class="absolute inset-y-0 right-0 px-3 text-gray-500 hover:text-gray-700 toggle-password" aria-label="Toggle password visibility" data-target="opRegConfirmPassword">
                            <i class="far fa-eye"></i>
                        </button>
                    </div>
                </div>
                <?php if ($recaptchaSiteKey !== ''): ?>
                <div>
                    <div id="operatorRecaptcha" data-sitekey="<?php echo htmlspecialchars($recaptchaSiteKey); ?>"></div>
                </div>
                <?php endif; ?>
                <div class="flex justify-end space-x-3 pt-2">
                    <button type="button" id="btnOperatorRegisterCancel" class="bg-red-500 text-white px-4 py-2 rounded-lg">Cancel</button>
                    <button type="submit" id="btnOperatorRegisterSubmit" class="bg-custom-secondary text-white px-4 py-2 rounded-lg">Register</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Terms of Service Modal -->
    <div id="termsModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center p-4 z-50">
        <div class="bg-white rounded-xl shadow-2xl max-w-3xl w-full max-h-[80vh] overflow-y-auto">
            <div class="sticky top-0 bg-white border-b px-6 py-4 flex items-center justify-between">
                <h3 class="text-lg font-semibold">GoServePH Terms of Service Agreement</h3>
                <button type="button" id="closeTerms" class="text-gray-500 hover:text-gray-700"><i class="fas fa-times"></i></button>
            </div>
            <div class="px-6 py-4 space-y-4 text-sm leading-6">
                <p><strong>Welcome to GoServePH!</strong></p>
                <p>This GoServePH Services Agreement ("Agreement") is a binding legal contract for the use of our software systems—which handle data input, monitoring, processing, and analytics—("Services") between GoServePH ("us," "our," or "we") and you, the registered user ("you" or "user").</p>
                <p>This Agreement details the terms and conditions for using our Services. By accessing or using any GoServePH Services, you agree to these terms. If you don't understand any part of this Agreement, please contact us at info@goserveph.com.</p>
                <h4 class="font-semibold">OVERVIEW OF THIS AGREEMENT</h4>
                <p>This document outlines the terms for your use of the GoServePH system:</p>
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr><th class="py-1 pr-4">Section</th><th class="py-1">Topic</th></tr>
                    </thead>
                    <tbody>
                        <tr><td class="py-1 pr-4">Section A</td><td class="py-1">General Account Setup and Use</td></tr>
                        <tr><td class="py-1 pr-4">Section B</td><td class="py-1">Technology, Intellectual Property, and Licensing</td></tr>
                        <tr><td class="py-1 pr-4">Section C</td><td class="py-1">Payment Terms, Fees, and Billing</td></tr>
                        <tr><td class="py-1 pr-4">Section D</td><td class="py-1">Data Usage, Privacy, and Security</td></tr>
                        <tr><td class="py-1 pr-4">Section E</td><td class="py-1">Additional Legal Terms and Disclaimers</td></tr>
                    </tbody>
                </table>
                <h4 class="font-semibold">SECTION A: GENERAL TERMS</h4>
                <p><strong>1. Your Account and Registration</strong></p>
                <p>a. Account Creation: To use our Services, you must create an Account. Your representative (Representative) must provide us with required details, including your entity's name, address, contact person, email, phone number, relevant ID/tax number, and the nature of your business/activities.</p>
                <p>b. Review and Approval: We reserve the right to review and approve your application, which typically takes at least two (2) business days. We can deny or reject any application at our discretion.</p>
                <p>c. Eligibility: Only businesses, institutions, and other entities based in the Philippines are eligible to apply for a GoServePH Account.</p>
                <p>d. Representative Authority: You confirm that your Representative has the full authority to provide your information and legally bind your entity to this Agreement. We may ask for proof of this authority.</p>
                <p>e. Validation: We may require additional documentation at any time (e.g., business licenses, IDs) to verify your entity's ownership, control, and the information you provided.</p>
                <p><strong>2. Services and Support</strong></p>
                <p>We provide support for general account inquiries and issues that prevent the proper use of the system ("System Errors"). Support includes resources available through our in-app Ticketing System and website documentation ("Documentation"). For further questions, contact us at support@goserveph.com.</p>
                <p><strong>3. Service Rules and Restrictions</strong></p>
                <p>a. Lawful Use: You must use the Services lawfully and comply with all applicable Philippine laws, rules, and regulations ("Laws") regarding your use of the Services and the transactions you facilitate ("Transactions").</p>
                <p>b. Prohibited Activities: You may not use the Services to facilitate illegal transactions, or for personal/household use. Specifically, you must not, nor allow others to:</p>
                <ul class="list-disc pl-5">
                    <li>Access non-public systems or data.</li>
                    <li>Copy, resell, or distribute the Services, Documentation, or system content.</li>
                    <li>Use, transfer, or access data you do not own or have no documented rights to use.</li>
                    <li>Act as a service agent for the Services.</li>
                    <li>Transfer your rights under this Agreement.</li>
                    <li>Bypass technical limitations or enable disabled features.</li>
                    <li>Reverse engineer the Services (except where legally permitted).</li>
                    <li>Interfere with the normal operation of the Services or impose an unreasonably large load on the system.</li>
                </ul>
                <p><strong>4. Electronic Notices and Consent</strong></p>
                <p>a. Electronic Consent: By registering, you provide your electronic signature and consent to receive all notices and disclosures from us electronically (via our website, email, or text message), which has the same legal effect as a physical signature.</p>
                <p>b. Delivery: We are not liable for non-receipt of notices due to issues beyond our control (e.g., network outages, incorrect contact details, firewall restrictions). Notices posted or emailed are considered received within 24 hours.</p>
                <p>c. Text Messages: You authorize us to use text messages to verify your account control (like two-step verification) and provide critical updates. Standard carrier charges may apply.</p>
                <p>d. Withdrawing Consent: You can withdraw your consent to electronic notices only by terminating your Account.</p>
                <p><strong>5. Termination</strong></p>
                <p>a. Agreement Term: This Agreement starts upon registration and continues until terminated by you or us.</p>
                <p>b. Termination by You: You can terminate by emailing a closure request to info@goserveph.com. Your Account will be closed within 120 business days of receipt.</p>
                <p>c. Termination by Us: We may terminate this Agreement, suspend your Account, or close it at any time, for any reason, by providing you notice. Immediate suspension or termination may occur if:</p>
                <ul class="list-disc pl-5">
                    <li>You pose a significant fraud or credit risk.</li>
                    <li>You use the Services in a prohibited manner or violate this Agreement.</li>
                    <li>Law requires us to do so.</li>
                </ul>
                <p>d. Effect of Termination: Upon termination:</p>
                <ul class="list-disc pl-5">
                    <li>All licenses granted to you end.</li>
                    <li>We may delete your data and information (though we have no obligation to do so).</li>
                    <li>We are not liable to you for any damages related to the termination, suspension, or data deletion.</li>
                    <li>You remain liable for any outstanding fees, fines, or financial obligations incurred before termination.</li>
                </ul>
                <h4 class="font-semibold">SECTION B: TECHNOLOGY</h4>
                <p><strong>1. System Access and Updates</strong></p>
                <p>We provide access to the web system and/or mobile application ("Application"). You must only use the Application as described in the Documentation. We will update the Application and Documentation periodically, which may add or remove features, and we will notify you of material changes.</p>
                <p><strong>2. Ownership of Intellectual Property (IP)</strong></p>
                <p>a. Your Data: You retain ownership of all your master data, raw transactional data, and generated reports gathered from the system.</p>
                <p>b. GoServePH IP: We exclusively own all rights, titles, and interests in the patents, copyrights, trademarks, system designs, and documentation ("GoServePH IP"). All rights in GoServePH IP not expressly granted to you are reserved by us.</p>
                <p>c. Ideas: If you submit comments or ideas for system improvements ("Ideas"), you agree that we are free to use these Ideas without any attribution or compensation to you.</p>
                <p><strong>3. License Coverage</strong></p>
                <p>We grant you a non-exclusive and non-transferable license to electronically access and use the GoServePH IP only as described in this Agreement. We are not selling the IP to you, and you cannot sublicense it. We may revoke this license if you violate the Agreement.</p>
                <p><strong>4. References to Our Relationship</strong></p>
                <p>During the term of this Agreement, both you and we may publicly identify the other party as the service provider or client, respectively. If you object to us identifying you as a client, you must notify us at info@goserveph.com. Upon termination, both parties must remove all public references to the relationship.</p>
                <h4 class="font-semibold">SECTION C: PAYMENT TERMS AND CONDITIONS</h4>
                <p><strong>1. Service Fees</strong></p>
                <p>We will charge the Fees for set-up, access, support, penalties, and other transactions as described on the GoServePH website. We may revise the Fees at any time, with at least 30 days' notice before the revisions apply to you.</p>
                <p><strong>2. Payment Terms and Schedule</strong></p>
                <p>a. Billing: Your monthly bill for the upcoming month is generated by the system on the 21st day of the current month and is due after 5 days. Billing is based on the number of registered users ("End-User") as of the 20th day.</p>
                <p>b. Payment Method: All payments must be settled via our third-party Payment System Provider, PayPal. You agree to abide by all of PayPal's terms, and we are not responsible for any issues with their service.</p>
                <p><strong>3. Taxes</strong></p>
                <p>Fees exclude applicable taxes. You are solely responsible for remitting all taxes for your business to the appropriate Philippine tax and revenue authorities.</p>
                <p><strong>4. Payment Processing</strong></p>
                <p>We are not a bank and do not offer services regulated by the Bangko Sentral ng Pilipinas. We reserve the right to reject your application or terminate your Account if you are ineligible to use PayPal services.</p>
                <p><strong>5. Processing Disputes and Refunds</strong></p>
                <p>You must report disputes and refund requests by emailing us at billing@goserveph.com. Disputes will only be investigated if reported within 60 days from the billing date. If a refund is warranted, it will be issued as a credit memo for use on future bills.</p>
                <h4 class="font-semibold">SECTION D: DATA USAGE, PRIVACY AND SECURITY</h4>
                <p><strong>1. Data Usage Overview</strong></p>
                <p>Data security is a top priority. This section outlines our obligations when handling information.</p>
                <p>'PERSONAL DATA' is information that relates to and can identify a person.</p>
                <p>'USER DATA' is information that describes your business, operations, products, or services.</p>
                <p>'GoServePH DATA' is transactional data over our infrastructure, fraud analysis info, aggregated data, and other information originating from the Services.</p>
                <p>'DATA' means all of the above.</p>
                <p>We use Data to provide Services, mitigate fraud, and improve our systems. We do not provide Personal Data to unaffiliated parties for marketing purposes.</p>
                <p><strong>2. Data Protection and Privacy</strong></p>
                <p>a. Confidentiality: You will protect all Data received via the Services and only use it in connection with this Agreement. Neither party may use Personal Data for marketing without express consent. We may disclose Data if required by legal instruments (e.g., subpoena).</p>
                <p>b. Privacy Compliance: You affirm that you comply with all Laws governing the privacy and protection of the Data you provide to or access through the Services. You are responsible for obtaining all necessary consents from End-Users to allow us to collect, use, and disclose their Data.</p>
                <p>c. Data Processing Roles: You shall be the data controller, and we shall be the data intermediary. We will process the Personal Data only according to this Agreement and will implement appropriate measures to protect it.</p>
                <p>d. Data Mining: You may not mine the database or any part of it without our express consent.</p>
                <p><strong>3. Security Controls</strong></p>
                <p>We are responsible for protecting your Data using commercially reasonable administrative, technical, and physical security measures. However, no system is impenetrable. You agree that you are responsible for implementing your own firewall, anti-virus, anti-phishing, and other security measures ("Security Controls"). We may suspend your Account to maintain the integrity of the Services, and you waive the right to claim losses that result from such actions.</p>
                <h4 class="font-semibold">SECTION E: ADDITIONAL LEGAL TERMS</h4>
                <p><strong>1. Right to Amend</strong></p>
                <p>We can change or add to these terms at any time by posting the changes on our website. Your continued use of the Services constitutes your acceptance of the modified Agreement.</p>
                <p><strong>2. Assignment</strong></p>
                <p>You cannot assign this Agreement or your Account rights to anyone else without our prior written consent. We can assign this Agreement without your consent.</p>
                <p><strong>3. Force Majeure</strong></p>
                <p>Neither party will be liable for delays or non-performance caused by events beyond reasonable control, such as utility failures, acts of nature, or war. This does not excuse your obligation to pay fees.</p>
                <p><strong>4. Representations and Warranties</strong></p>
                <p>By agreeing, you warrant that:</p>
                <ul class="list-disc pl-5">
                    <li>You are eligible to use the Services and have the authority to enter this Agreement.</li>
                    <li>All information you provide is accurate and complete.</li>
                    <li>You will comply with all Laws.</li>
                    <li>You will not use the Services for fraudulent or illegal purposes.</li>
                </ul>
                <p><strong>5. No Warranties</strong></p>
                <p>We provide the Services and GoServePH IP “AS IS” and “AS AVAILABLE,” without any express, implied, or statutory warranties of title, merchantability, fitness for a particular purpose, or non-infringement.</p>
                <p><strong>6. Limitation of Liability</strong></p>
                <p>We shall not be responsible or liable to you for any indirect, punitive, incidental, special, consequential, or exemplary damages resulting from your use or inability to use the Services, lost profits, personal injury, or property damage. We are not liable for damages arising from:</p>
                <ul class="list-disc pl-5">
                    <li>Hacking, tampering, or unauthorized access to your Account.</li>
                    <li>Your failure to implement Security Controls.</li>
                    <li>Use of the Services inconsistent with the Documentation.</li>
                    <li>Bugs, viruses, or interruptions to the Services.</li>
                </ul>
                <p>This Agreement and all incorporated policies constitute the entire agreement between you and GoServePH.</p>
            </div>
            <div class="border-t px-6 py-3 flex justify-end">
                <button type="button" id="closeTermsBottom" class="px-4 py-2 rounded-lg bg-custom-secondary text-white">Close</button>
            </div>
        </div>
    </div>

    <!-- Privacy Policy Modal -->
    <div id="privacyModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center p-4 z-50">
        <div class="bg-white rounded-xl shadow-2xl max-w-3xl w-full max-h-[80vh] overflow-y-auto">
            <div class="sticky top-0 bg-white border-b px-6 py-4 flex items-center justify-between">
                <h3 class="text-lg font-semibold">GoServePH Data Privacy Policy</h3>
                <button type="button" id="closePrivacy" class="text-gray-500 hover:text-gray-700"><i class="fas fa-times"></i></button>
            </div>
            <div class="px-6 py-4 space-y-4 text-sm leading-6">
                <p><strong>Protecting the information you and your users handle through our system is our highest priority.</strong> This policy outlines how GoServePH manages, secures, and uses your data.</p>
                <h4 class="font-semibold">1. How We Define and Use Data</h4>
                <p>In this policy, we define the types of data that flow through the GoServePH system:</p>
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr><th class="py-1 pr-4">Term</th><th class="py-1">Definition</th></tr>
                    </thead>
                    <tbody>
                        <tr><td class="py-1 pr-4">Personal Data</td><td class="py-1">Any information that can identify a specific person, whether directly or indirectly, shared or accessible through the Services.</td></tr>
                        <tr><td class="py-1 pr-4">User Data</td><td class="py-1">Information that describes your business operations, services, or internal activities.</td></tr>
                        <tr><td class="py-1 pr-4">GoServePH Data</td><td class="py-1">Details about transactions and activity on our platform, information used for fraud detection, aggregated data, and any non-personal information generated by our system.</td></tr>
                        <tr><td class="py-1 pr-4">DATA</td><td class="py-1">Used broadly to refer to all the above: Personal Data, User Data, and GoServePH Data.</td></tr>
                    </tbody>
                </table>
                <h4 class="font-semibold">Our Commitment to Data Use</h4>
                <p>We analyze and manage data only for the following critical purposes:</p>
                <ul class="list-disc pl-5">
                    <li>To provide, maintain, and improve the GoServePH Services for you and all other users.</li>
                    <li>To detect and mitigate fraud, financial loss, or other harm to you or other users.</li>
                    <li>To develop and enhance our products, systems, and tools.</li>
                </ul>
                <p>We will not sell or share Personal Data with unaffiliated parties for their marketing purposes. By using our system, you consent to our use of your Data in this manner.</p>
                <h4 class="font-semibold">2. Data Protection and Compliance</h4>
                <p><strong>Confidentiality</strong></p>
                <p>We commit to using Data only as permitted by our agreement or as specifically directed by you. You, in turn, must protect all Data you access through GoServePH and use it only in connection with our Services. Neither party may use Personal Data to market to third parties without explicit consent.</p>
                <p>We will only disclose Data when legally required to do so, such as through a subpoena, court order, or search warrant.</p>
                <p><strong>Privacy Compliance and Responsibilities</strong></p>
                <p><em>Your Legal Duty:</em> You affirm that you are, and will remain, compliant with all applicable Philippine laws (including the Data Privacy Act of 2012) governing the collection, protection, and use of the Data you provide to us.</p>
                <p><em>Consent:</em> You are responsible for obtaining all necessary rights and consents from your End-Users to allow us to collect, use, and store their Personal Data.</p>
                <p><em>End-User Disclosure:</em> You must clearly inform your End-Users that GoServePH processes transactions for you and may receive their Personal Data as part of that process.</p>
                <p><strong>Data Processing Roles</strong></p>
                <p>When we process Personal Data on your behalf, we operate under the following legal roles:</p>
                <ul class="list-disc pl-5">
                    <li>You are the Data Controller (you determine why and how the data is processed).</li>
                    <li>We are the Data Intermediary (we process data strictly according to your instructions).</li>
                </ul>
                <p>As the Data Intermediary, we commit to:</p>
                <ul class="list-disc pl-5">
                    <li>Implementing appropriate security measures to protect the Personal Data we process.</li>
                    <li>Not retaining Personal Data longer than necessary to fulfill the purposes set out in our agreement.</li>
                </ul>
                <p>You acknowledge that we rely entirely on your instructions. Therefore, we are not liable for any claims resulting from our actions that were based directly or indirectly on your instructions.</p>
                <p><strong>Prohibited Activities</strong></p>
                <p>You are strictly prohibited from data mining the GoServePH database or any portion of it without our express written permission.</p>
                <p><strong>Breach Notification</strong></p>
                <p>If we become aware of an unauthorized acquisition, disclosure, change, or loss of Personal Data on our systems (a "Breach"), we will notify you and provide sufficient information to help you mitigate any negative impact, consistent with our legal obligations.</p>
                <h4 class="font-semibold">3. Account Deactivation and Data Deletion</h4>
                <p><strong>Initiating Deactivation</strong></p>
                <p>If you wish to remove your personal information from our systems, you must go to your Edit Profile page and click the 'Deactivate Account' button. This action initiates the data deletion and account deactivation process.</p>
                <p><strong>Data Retention</strong></p>
                <p>Upon deactivation, all of your Personal Identifying Information will be deleted from our systems.</p>
                <p><em>Important Note:</em> Due to the nature of our role as a Government Services Management System, and for legal, accounting, and audit purposes, we are required to retain some of your non-personal account activity history and transactional records. You will receive a confirmation email once your request has been fully processed.</p>
                <h4 class="font-semibold">4. Security Controls and Responsibilities</h4>
                <p><strong>Our Security</strong></p>
                <p>We are responsible for implementing commercially reasonable administrative, technical, and physical procedures to protect Data from unauthorized access, loss, or modification. We comply with all applicable Laws in handling Data.</p>
                <p><strong>Your Security Controls</strong></p>
                <p>You acknowledge that no security system is perfect. You agree to implement your own necessary security measures ("Security Controls"), which must include:</p>
                <ul class="list-disc pl-5">
                    <li>Firewall and anti-virus systems.</li>
                    <li>Anti-phishing systems.</li>
                    <li>End-User and device management policies.</li>
                    <li>Data handling protocols.</li>
                </ul>
                <p>We reserve the right to suspend your Account or the Services if necessary to maintain system integrity and security, or to prevent harm. You waive any right to claim losses that result from a Breach or any action we take to prevent harm.</p>
            </div>
            <div class="border-t px-6 py-3 flex justify-end">
                <button type="button" id="closePrivacyBottom" class="px-4 py-2 rounded-lg bg-custom-secondary text-white">Close</button>
            </div>
        </div>
    </div>

  <script>
    const BASE_URL = <?php echo json_encode($baseUrl); ?>;
    const RECAPTCHA_SITE_KEY = <?php echo json_encode($recaptchaSiteKey); ?>;
    let citizenRecaptchaWidgetId = null;

    function tryRenderCitizenRecaptcha() {
        const citizenRecaptcha = document.getElementById('citizenRecaptcha');
        if (!citizenRecaptcha || !window.grecaptcha || citizenRecaptchaWidgetId !== null) return;
        const siteKey = citizenRecaptcha.getAttribute('data-sitekey') || '';
        if (!siteKey) return;
        citizenRecaptchaWidgetId = window.grecaptcha.render(citizenRecaptcha, { sitekey: siteKey });
    }

    document.addEventListener('DOMContentLoaded', function() {
        initializePage();
        setupEventListeners();
        updateDateTime();
        setInterval(updateDateTime, 1000);
    });

    function initializePage() {
        addLoadingStates();
        initializeFormValidation();
        addSmoothScrolling();
        try {
            const remembered = localStorage.getItem('gsm_remember') === '1';
            const email = localStorage.getItem('gsm_email') || '';
            const rememberEl = document.getElementById('rememberMe');
            const emailEl = document.getElementById('email');
            if (rememberEl) rememberEl.checked = remembered;
            if (remembered && emailEl && email) emailEl.value = email;
            const opEmail = localStorage.getItem('gsm_operator_email') || '';
            const opPlate = localStorage.getItem('gsm_operator_plate') || '';
            const opLoginEmail = document.getElementById('opLoginEmail');
            const opLoginPlate = document.getElementById('opLoginPlate');
            if (remembered && opLoginEmail && opEmail) opLoginEmail.value = opEmail;
            if (remembered && opLoginPlate && opPlate) opLoginPlate.value = opPlate;
        } catch (e) {}
    }

    function setupEventListeners() {
        const rememberEl = document.getElementById('rememberMe');
        const loginForm = document.getElementById('loginForm');
        if (loginForm) {
            loginForm.addEventListener('submit', () => {
                try {
                    const remember = !!(rememberEl && rememberEl.checked);
                    if (remember) {
                        localStorage.setItem('gsm_remember', '1');
                        localStorage.setItem('gsm_email', String((document.getElementById('email') && document.getElementById('email').value) || '').trim());
                    } else {
                        localStorage.removeItem('gsm_remember');
                        localStorage.removeItem('gsm_email');
                        localStorage.removeItem('gsm_operator_email');
                        localStorage.removeItem('gsm_operator_plate');
                    }
                } catch (e) {}
            });
        }

        const opLoginModal = document.getElementById('operatorLoginModal');
        const opLoginOpen = document.getElementById('btnOperatorLoginOpen');
        const opLoginCancel = document.getElementById('btnOperatorLoginCancel');
        const opLoginPlate = document.getElementById('opLoginPlate');
        const opLoginForm = document.getElementById('operatorLoginForm');

        function openOpLogin() {
            if (!opLoginModal) return;
            opLoginModal.classList.remove('hidden');
            opLoginModal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
            if (opLoginPlate) opLoginPlate.focus();
        }
        function closeOpLogin() {
            if (!opLoginModal) return;
            opLoginModal.classList.add('hidden');
            opLoginModal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
            if (opLoginForm) opLoginForm.reset();
        }
        if (opLoginOpen) opLoginOpen.addEventListener('click', openOpLogin);
        if (opLoginCancel) opLoginCancel.addEventListener('click', closeOpLogin);
        if (opLoginModal) {
            opLoginModal.addEventListener('click', (e) => { if (e.target === opLoginModal) closeOpLogin(); });
        }
        if (opLoginForm) {
            opLoginForm.addEventListener('submit', () => {
                try {
                    const remember = !!(rememberEl && rememberEl.checked);
                    if (!remember) return;
                    const opEmail = String((document.getElementById('opLoginEmail') && document.getElementById('opLoginEmail').value) || '').trim();
                    const opPlate = String((document.getElementById('opLoginPlate') && document.getElementById('opLoginPlate').value) || '').trim();
                    if (opEmail) localStorage.setItem('gsm_operator_email', opEmail);
                    if (opPlate) localStorage.setItem('gsm_operator_plate', opPlate);
                } catch (e) {}
            });
        }

        const opRegModal = document.getElementById('operatorRegisterModal');
        const opRegOpen = document.getElementById('btnOperatorRegisterOpen');
        const opRegCancel = document.getElementById('btnOperatorRegisterCancel');
        const opRegForm = document.getElementById('operatorRegisterForm');
        const operatorRecaptcha = document.getElementById('operatorRecaptcha');
        let operatorRecaptchaWidgetId = null;
        function tryRenderOpRecaptcha() {
            if (!operatorRecaptcha || !window.grecaptcha || operatorRecaptchaWidgetId !== null) return;
            const siteKey = operatorRecaptcha.getAttribute('data-sitekey') || '';
            if (!siteKey) return;
            operatorRecaptchaWidgetId = window.grecaptcha.render(operatorRecaptcha, { sitekey: siteKey });
        }

        function openOpReg() {
            if (!opRegModal) return;
            opRegModal.classList.remove('hidden');
            opRegModal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
            tryRenderOpRecaptcha();
        }
        function closeOpReg() {
            if (!opRegModal) return;
            opRegModal.classList.add('hidden');
            opRegModal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
            if (opRegForm) opRegForm.reset();
            if (window.grecaptcha && operatorRecaptchaWidgetId !== null) window.grecaptcha.reset(operatorRecaptchaWidgetId);
        }
        if (opRegOpen) opRegOpen.addEventListener('click', openOpReg);
        if (opRegCancel) opRegCancel.addEventListener('click', closeOpReg);
        if (opRegModal) {
            opRegModal.addEventListener('click', (e) => { if (e.target === opRegModal) closeOpReg(); });
        }
        if (opRegForm) {
            const opPwd = document.getElementById('opRegPassword');
            const opConfirm = document.getElementById('opRegConfirmPassword');
            if (opPwd) {
                opPwd.addEventListener('input', function(){
                    validateRegPassword(this);
                    updatePasswordChecklistFor('opPwdChecklist', this.value);
                    if (opConfirm && opConfirm.value) { validateConfirmPasswordFor('opRegPassword', 'opRegConfirmPassword', true, 'op-confirm-error'); }
                });
                opPwd.addEventListener('blur', function(){
                    validateRegPassword(this, true);
                    updatePasswordChecklistFor('opPwdChecklist', this.value);
                    if (opConfirm && opConfirm.value) { validateConfirmPasswordFor('opRegPassword', 'opRegConfirmPassword', true, 'op-confirm-error'); }
                });
            }
            if (opConfirm) {
                opConfirm.addEventListener('input', function(){ validateConfirmPasswordFor('opRegPassword', 'opRegConfirmPassword', true, 'op-confirm-error'); });
                opConfirm.addEventListener('blur', function(){ validateConfirmPasswordFor('opRegPassword', 'opRegConfirmPassword', true, 'op-confirm-error'); });
            }
            opRegForm.addEventListener('submit', function (e) {
                e.preventDefault();
                const fd = new FormData(opRegForm);
                const payload = Object.fromEntries(fd.entries());
                const pwd = String(payload.password || '');
                const cpwd = String(payload.confirm_password || '');
                const pwdEl = document.getElementById('opRegPassword');
                if (!validateRegPassword(pwdEl, true)) { showNotification('Password does not meet requirements.', 'warning'); return; }
                if (!validateConfirmPasswordFor('opRegPassword', 'opRegConfirmPassword', true, 'op-confirm-error')) { return; }
                tryRenderOpRecaptcha();
                const captchaConfigured = !!operatorRecaptcha;
                const captchaResponse = (window.grecaptcha && operatorRecaptchaWidgetId !== null) ? window.grecaptcha.getResponse(operatorRecaptchaWidgetId) : '';
                if (captchaConfigured && !window.grecaptcha) { showNotification('reCAPTCHA failed to load. Please check internet connection.', 'warning'); return; }
                if (captchaConfigured && !captchaResponse) { showNotification('Please complete the reCAPTCHA.', 'warning'); return; }
                payload.recaptcha_token = captchaResponse || '';
                const submit = document.getElementById('btnOperatorRegisterSubmit');
                if (submit) { submit.disabled = true; }
                fetch(`${BASE_URL}/gsm_login/Login/operator_register.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                })
                .then(r => r.json())
                .then(res => {
                    if (!res || !res.ok) { showNotification((res && res.message) ? res.message : 'Operator registration failed', 'error'); return; }
                    const otpRequired = !!(res.data && res.data.otp_required);
                    if (otpRequired) {
                        window.__otpContext = { email: (res.data.email || payload.email || ''), purpose: 'operator_register', user_type: 'operator' };
                        showNotification(res.message || 'OTP sent. Please verify.', 'success');
                        closeOpReg();
                        openOtpModal(res.data && res.data.otp_expires_in ? parseInt(res.data.otp_expires_in, 10) : 180);
                        return;
                    }
                    showNotification(res.message || 'Operator registration successful', 'success');
                    closeOpReg();
                })
                .catch(() => { showNotification('Network error. Please try again.', 'error'); })
                .finally(() => { if (submit) submit.disabled = false; });
            });
        }

        const opFromCitizen = document.getElementById('openOperatorRegisterFromCitizen');
        if (opFromCitizen) {
            opFromCitizen.addEventListener('click', () => {
                if (typeof hideRegisterForm === 'function') hideRegisterForm();
                openOpReg();
            });
        }

        const opFromLogin = document.getElementById('openOperatorRegisterFromLogin');
        if (opFromLogin) {
            opFromLogin.addEventListener('click', () => {
                closeOpLogin();
                openOpReg();
            });
        }

        const socialButtons = document.querySelectorAll('.social-btn');
        socialButtons.forEach(button => {
            button.addEventListener('click', handleSocialLogin);
        });
        
        const showRegister = document.getElementById('showRegister');
        if (showRegister) {
            showRegister.addEventListener('click', showRegisterForm);
        }
        const cancelRegister = document.getElementById('cancelRegister');
        if (cancelRegister) {
            cancelRegister.addEventListener('click', hideRegisterForm);
        }
        const registerForm = document.getElementById('registerForm');
        if (registerForm) {
            registerForm.addEventListener('submit', handleRegisterSubmit);
        }

        const langEN = document.getElementById('langEN');
        const langFIL = document.getElementById('langFIL');
        const applyLang = (lang) => {
            try { localStorage.setItem('gsm_lang', lang); } catch (e) {}
            if (langEN) langEN.className = 'px-3 py-1.5 rounded-md text-xs font-bold ' + (lang === 'EN' ? 'bg-custom-primary text-white' : 'text-slate-700 hover:bg-slate-100');
            if (langFIL) langFIL.className = 'px-3 py-1.5 rounded-md text-xs font-bold ' + (lang === 'FIL' ? 'bg-custom-primary text-white' : 'text-slate-700 hover:bg-slate-100');
        };
        if (langEN) langEN.addEventListener('click', () => applyLang('EN'));
        if (langFIL) langFIL.addEventListener('click', () => applyLang('FIL'));
        try {
            const saved = localStorage.getItem('gsm_lang');
            applyLang(saved === 'FIL' ? 'FIL' : 'EN');
        } catch (e) { applyLang('EN'); }
        
        // Registration Password Logic
        const regPassword = document.getElementById('regPassword');
        const confirmPassword = document.getElementById('confirmPassword');
        if (regPassword) {
            regPassword.addEventListener('input', function(){
                validateRegPassword(this);
                updatePasswordChecklist(this.value);
                const cp = document.getElementById('confirmPassword');
                if (cp && cp.value) { validateConfirmPassword(true); }
            });
            regPassword.addEventListener('blur', function(){
                validateRegPassword(this, true);
                updatePasswordChecklist(this.value);
                const cp = document.getElementById('confirmPassword');
                if (cp && cp.value) { validateConfirmPassword(true); }
            });
        }
        if (confirmPassword) {
            confirmPassword.addEventListener('input', function(){ validateConfirmPassword(true); });
            confirmPassword.addEventListener('blur', function(){ validateConfirmPassword(true); });
        }
        
        const toggles = document.querySelectorAll('.toggle-password');
        toggles.forEach(btn => {
            btn.addEventListener('click', () => {
                const targetId = btn.getAttribute('data-target');
                const input = document.getElementById(targetId);
                if (!input) return;
                const isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                const icon = btn.querySelector('i');
                if (icon) {
                    icon.classList.toggle('fa-eye');
                    icon.classList.toggle('fa-eye-slash');
                }
            });
        });
        
        const noMiddleName = document.getElementById('noMiddleName');
        if (noMiddleName) {
            noMiddleName.addEventListener('change', function() {
                const middle = document.getElementById('middleName');
                const asterisk = document.getElementById('middleAsterisk');
                if (!middle) return;
                middle.disabled = this.checked;
                middle.required = !this.checked;
                if (asterisk) {
                    asterisk.style.display = this.checked ? 'none' : 'inline';
                }
                if (this.checked) middle.value = '';
            });
        }

        // Terms modal wiring
        const openTerms = document.getElementById('openTerms');
        const footerTerms = document.getElementById('footerTerms');
        const termsModal = document.getElementById('termsModal');
        const closeTerms = document.getElementById('closeTerms');
        const closeTermsBottom = document.getElementById('closeTermsBottom');
        
        // Privacy modal wiring
        const openPrivacy = document.getElementById('openPrivacy');
        const footerPrivacy = document.getElementById('footerPrivacy');
        const privacyModal = document.getElementById('privacyModal');
        const closePrivacy = document.getElementById('closePrivacy');
        const closePrivacyBottom = document.getElementById('closePrivacyBottom');
        
        function showTerms() {
            if (!termsModal) return;
            termsModal.classList.remove('hidden');
            termsModal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
        }
        function hideTerms() {
            if (!termsModal) return;
            termsModal.classList.add('hidden');
            termsModal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
        }
        
        if (openTerms) openTerms.addEventListener('click', showTerms);
        if (footerTerms) footerTerms.addEventListener('click', showTerms);
        if (closeTerms) closeTerms.addEventListener('click', hideTerms);
        if (closeTermsBottom) closeTermsBottom.addEventListener('click', hideTerms);
        if (termsModal) {
            termsModal.addEventListener('click', (e) => {
                if (e.target === termsModal) hideTerms();
            });
        }

        function showPrivacy() {
            if (!privacyModal) return;
            privacyModal.classList.remove('hidden');
            privacyModal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
        }
        function hidePrivacy() {
            if (!privacyModal) return;
            privacyModal.classList.add('hidden');
            privacyModal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
        }
        
        if (openPrivacy) openPrivacy.addEventListener('click', showPrivacy);
        if (footerPrivacy) footerPrivacy.addEventListener('click', showPrivacy);
        if (closePrivacy) closePrivacy.addEventListener('click', hidePrivacy);
        if (closePrivacyBottom) closePrivacyBottom.addEventListener('click', hidePrivacy);
        if (privacyModal) {
            privacyModal.addEventListener('click', (e) => {
                if (e.target === privacyModal) hidePrivacy();
            });
        }

        const otpForm = document.getElementById('otpForm');
        const resend = document.getElementById('resendOtp');
        const cancelOtp = document.getElementById('cancelOtp');
        const otpModal = document.getElementById('otpModal');
        if (cancelOtp) cancelOtp.addEventListener('click', closeOtpModal);
        if (resend) resend.addEventListener('click', () => {
            const ctx = window.__otpContext || {};
            const email = String(ctx.email || '');
            const purpose = String(ctx.purpose || 'register');
            const userType = String(ctx.user_type || '');
            if (!email) { showNotification('Missing email for OTP. Please register again.', 'error'); return; }
            resend.disabled = true;
            fetch(`${BASE_URL}/gsm_login/Login/otp_send.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, purpose, user_type: userType })
            })
            .then(r => r.json())
            .then(res => {
                if (!res || !res.ok) { showNotification((res && res.message) ? res.message : 'Failed to resend OTP', 'error'); resend.disabled = false; return; }
                showNotification(res.message || 'A new OTP has been sent to your email.', 'info');
                const expiresIn = res.data && res.data.expires_in ? parseInt(res.data.expires_in, 10) : 180;
                startOtpTimer(expiresIn);
            })
            .catch(() => { resend.disabled = false; });
        });
        if (otpForm) otpForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const inputs = Array.from(document.querySelectorAll('#otpInputs .otp-input'));
            const code = inputs.map(i => i.value).join('');
            const error = document.getElementById('otpError');
            if (!code || code.length !== 6) { error.textContent = 'Please enter the 6-digit OTP.'; error.classList.remove('hidden'); return; }
            if (document.getElementById('submitOtp').disabled) { error.textContent = 'OTP expired. Please resend a new OTP.'; error.classList.remove('hidden'); return; }
            const ctx = window.__otpContext || {};
            const email = String(ctx.email || '');
            const purpose = String(ctx.purpose || 'register');
            const userType = String(ctx.user_type || '');
            if (!email) { error.textContent = 'Missing email for OTP. Please register again.'; error.classList.remove('hidden'); return; }
            error.classList.add('hidden');
            const submitBtn = document.getElementById('submitOtp');
            if (submitBtn) submitBtn.disabled = true;
            fetch(`${BASE_URL}/gsm_login/Login/otp_verify.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, purpose, code, user_type: userType })
            })
            .then(r => r.json())
            .then(res => {
                if (!res || !res.ok) { error.textContent = (res && res.message) ? res.message : 'Invalid or expired OTP.'; error.classList.remove('hidden'); if (submitBtn) submitBtn.disabled = false; return; }
                showNotification(res.message || 'OTP verified. You may login now.', 'success');
                window.__otpContext = null;
                closeOtpModal();
            })
            .catch(() => { if (submitBtn) submitBtn.disabled = false; });
        });
        if (otpModal) otpModal.addEventListener('click', (e) => { if (e.target === otpModal) closeOtpModal(); });

        const openForgot = document.getElementById('openForgotPassword');
        const fpModal = document.getElementById('forgotPasswordModal');
        const fpClose = document.getElementById('fpClose');
        const fpCancel = document.getElementById('fpCancel');
        const fpSendOtp = document.getElementById('fpSendOtp');
        const fpForm = document.getElementById('forgotPasswordForm');
        const fpStep2 = document.getElementById('fpStep2');
        const fpEmail = document.getElementById('fpEmail');
        const fpNewPassword = document.getElementById('fpNewPassword');
        const fpConfirmPassword = document.getElementById('fpConfirmPassword');
        const fpConfirmError = document.getElementById('fpConfirmError');
        const fpOtpInputs = fpModal ? Array.from(fpModal.querySelectorAll('#fpOtpInputs .otp-input')) : [];
        let fpUserType = 'operator';
        function fpOpen() {
            if (!fpModal) return;
            fpModal.classList.remove('hidden');
            fpModal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
            if (fpEmail) fpEmail.value = String((document.getElementById('email') && document.getElementById('email').value) || '').trim();
            fpUserType = 'operator';
            if (fpStep2) fpStep2.classList.add('hidden');
            if (fpConfirmError) fpConfirmError.classList.add('hidden');
            fpOtpInputs.forEach(i => i.value = '');
            if (fpNewPassword) fpNewPassword.value = '';
            if (fpConfirmPassword) fpConfirmPassword.value = '';
            if (fpNewPassword) updatePasswordChecklistFor('fpPwdChecklist', fpNewPassword.value || '');
        }
        function fpCloseModal() {
            if (!fpModal) return;
            fpModal.classList.add('hidden');
            fpModal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
        }
        if (openForgot) openForgot.addEventListener('click', fpOpen);
        if (fpClose) fpClose.addEventListener('click', fpCloseModal);
        if (fpCancel) fpCancel.addEventListener('click', fpCloseModal);
        if (fpModal) fpModal.addEventListener('click', (e) => { if (e.target === fpModal) fpCloseModal(); });
        if (fpOtpInputs.length) setupOtpInputs(fpOtpInputs);
        if (fpNewPassword) {
            fpNewPassword.addEventListener('input', function(){ updatePasswordChecklistFor('fpPwdChecklist', this.value || ''); });
            fpNewPassword.addEventListener('blur', function(){ updatePasswordChecklistFor('fpPwdChecklist', this.value || ''); });
        }
        if (fpConfirmPassword) {
            fpConfirmPassword.addEventListener('input', function(){
                const ok = (this.value || '').trim() === ((fpNewPassword && fpNewPassword.value) ? fpNewPassword.value.trim() : '');
                if (fpConfirmError) fpConfirmError.classList.toggle('hidden', ok);
            });
        }
        if (fpSendOtp) fpSendOtp.addEventListener('click', () => {
            const emailVal = fpEmail ? String(fpEmail.value || '').trim() : '';
            if (!emailVal) { showNotification('Please enter your email.', 'warning'); return; }
            fpSendOtp.disabled = true;
            fpSendOtp.textContent = 'Sending...';
            fetch(`${BASE_URL}/gsm_login/Login/password_reset.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'request', email: emailVal, user_type: fpUserType })
            })
            .then(r => r.json())
            .then(res => {
                if (!res || !res.ok) { showNotification((res && res.message) ? res.message : 'Failed to send OTP.', 'error'); return; }
                showNotification(res.message || 'OTP sent. Please check your email.', 'success');
                if (fpStep2) fpStep2.classList.remove('hidden');
            })
            .finally(() => { fpSendOtp.disabled = false; fpSendOtp.textContent = 'Send OTP'; });
        });
        if (fpForm) fpForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const emailVal = fpEmail ? String(fpEmail.value || '').trim() : '';
            const code = fpOtpInputs.map(i => i.value).join('');
            const np = fpNewPassword ? fpNewPassword.value : '';
            const cp = fpConfirmPassword ? fpConfirmPassword.value : '';
            if (!emailVal) { showNotification('Please enter your email.', 'warning'); return; }
            if (!code || code.length !== 6) { showNotification('Please enter the 6-digit OTP.', 'warning'); return; }
            if (!validateRegPassword(fpNewPassword, true)) { showNotification('Password does not meet requirements.', 'warning'); return; }
            if (np.trim() !== cp.trim()) { if (fpConfirmError) fpConfirmError.classList.remove('hidden'); showNotification('Passwords do not match.', 'error'); return; }
            const btn = document.getElementById('fpSubmit');
            if (btn) showLoadingState(btn);
            fetch(`${BASE_URL}/gsm_login/Login/password_reset.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'confirm', email: emailVal, user_type: fpUserType, code, new_password: np, confirm_password: cp })
            })
            .then(r => r.json())
            .then(res => {
                if (!res || !res.ok) { showNotification((res && res.message) ? res.message : 'Reset failed.', 'error'); return; }
                showNotification(res.message || 'Password updated.', 'success');
                fpCloseModal();
            })
            .finally(() => { if (btn) { btn.innerHTML = 'Reset Password'; btn.disabled = false; } });
        });
    }

    function updateDateTime() {
        const now = new Date();
        const options = {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: true
        };
        const dateTimeElement = document.getElementById('currentDateTime');
        if (dateTimeElement) {
            dateTimeElement.textContent = now.toLocaleDateString('en-US', options).toUpperCase();
        }
    }

    function addLoadingStates() {
        const buttons = document.querySelectorAll('button');
        buttons.forEach(button => {
            button.addEventListener('click', function() {
                if (this.hasAttribute('data-no-loading')) return;
                if (this.type === 'submit' || this.classList.contains('social-btn')) {
                    if (this.classList.contains('social-btn')) {
                         showLoadingState(this);
                    }
                }
            });
        });
    }

    function showLoadingState(button) {
        const originalText = button.innerHTML;
        button.innerHTML = '<span class="loading"></span> Processing...';
        button.disabled = true;
        setTimeout(() => {
            button.innerHTML = originalText;
            button.disabled = false;
        }, 2000);
    }

    function initializeFormValidation() {
        const inputs = document.querySelectorAll('input[required]');
        inputs.forEach(input => {
            input.addEventListener('input', function() {
                validateField(this);
            });
        });
    }

    function validateField(field) {
        const value = field.value.trim();
        const fieldName = field.name;
        field.classList.remove('border-red-500', 'ring-red-500');
        field.classList.add('border-gray-300', 'ring-custom-secondary');
        const existingError = field.parentNode.querySelector('.error-message');
        if (existingError) {
            existingError.remove();
        }
        if (fieldName === 'email') {
            validateEmail(field);
        }
    }

    function validateEmail(input) {
        const email = input.value.trim();
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (email && !emailRegex.test(email)) {
            showFieldError(input, 'Please enter a valid email address');
            return false;
        }
        clearEmailError(input);
        return true;
    }

    function clearEmailError(input) {
        input.classList.remove('border-red-500', 'ring-red-500');
        input.classList.add('border-gray-300', 'ring-custom-secondary');
        const errorMessage = input.parentNode.querySelector('.error-message');
        if (errorMessage) errorMessage.remove();
    }

    function validatePassword(input) {
        const password = input.value.trim();
        if (password && password.length < 6) {
            showFieldError(input, 'Password must be at least 6 characters long');
            return false;
        }
        clearPasswordError(input);
        return true;
    }

    function clearPasswordError(input) {
        input.classList.remove('border-red-500', 'ring-red-500');
        input.classList.add('border-gray-300', 'ring-custom-secondary');
        const errorMessage = input.parentNode.querySelector('.error-message');
        if (errorMessage) errorMessage.remove();
    }

    function showFieldError(field, message) {
        field.classList.remove('border-gray-300', 'ring-custom-secondary');
        field.classList.add('border-red-500', 'ring-red-500');
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message text-red-500 text-sm mt-1';
        errorDiv.textContent = message;
        field.parentNode.appendChild(errorDiv);
    }

    function handleSocialLogin(event) {
        const button = event.target.closest('button');
        const buttonText = button.textContent.trim();
        showLoadingState(button);
        setTimeout(() => {
            if (buttonText.includes('Google')) {
                showNotification('Google login initiated...', 'info');
            }
        }, 1000);
    }

    function showRegisterForm() {
        const container = document.getElementById('registerFormContainer');
        const mainCard = document.querySelector('.glass-card');
        if (container && mainCard) {
            container.classList.remove('hidden');
            mainCard.classList.add('opacity-40');
        }
        tryRenderCitizenRecaptcha();
    }

    function hideRegisterForm() {
        const container = document.getElementById('registerFormContainer');
        const mainCard = document.querySelector('.glass-card');
        if (container && mainCard) {
            container.classList.add('hidden');
            mainCard.classList.remove('opacity-40');
        }
        if (window.grecaptcha && citizenRecaptchaWidgetId !== null) window.grecaptcha.reset(citizenRecaptchaWidgetId);
    }

    function handleRegisterSubmit(event) {
        event.preventDefault();
        if (!validateRegPassword(document.getElementById('regPassword'), true)) return;
        if (!validateConfirmPassword(true)) return;

        tryRenderCitizenRecaptcha();
        const captchaResponse = (window.grecaptcha && citizenRecaptchaWidgetId !== null) ? window.grecaptcha.getResponse(citizenRecaptchaWidgetId) : '';
        if (RECAPTCHA_SITE_KEY && !window.grecaptcha) {
            showNotification('reCAPTCHA failed to load. Please check internet connection.', 'warning');
            return;
        }
        if (RECAPTCHA_SITE_KEY && !captchaResponse) {
            showNotification('Please complete the reCAPTCHA.', 'warning');
            return;
        }
        
        if (!document.getElementById('agreeTerms').checked || !document.getElementById('agreePrivacy').checked) {
            showNotification('You must agree to the Terms and Privacy Policy.', 'warning');
            return;
        }
        
        const form = event.target;
        const fd = new FormData(form);
        const payload = Object.fromEntries(fd.entries());
        payload.email = payload.regEmail || '';
        payload.password = payload.regPassword || '';
        payload.recaptcha_token = captchaResponse || '';

        fetch(`${BASE_URL}/gsm_login/Login/register.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(res => {
            if (!res || !res.ok) {
                showNotification((res && res.message) ? res.message : 'Registration failed', 'error');
                return;
            }
            const otpRequired = !!(res.data && res.data.otp_required);
            if (otpRequired) {
                window.__otpContext = { email: (res.data.email || payload.email || ''), purpose: 'register', user_type: 'staff' };
                showNotification(res.message || 'OTP sent. Please verify.', 'success');
                hideRegisterForm();
                if (window.grecaptcha && citizenRecaptchaWidgetId !== null) window.grecaptcha.reset(citizenRecaptchaWidgetId);
                openOtpModal(res.data && res.data.otp_expires_in ? parseInt(res.data.otp_expires_in, 10) : 180);
                return;
            }
            showNotification(res.message || 'Registration submitted!', 'success');
            hideRegisterForm();
            if (window.grecaptcha && citizenRecaptchaWidgetId !== null) window.grecaptcha.reset(citizenRecaptchaWidgetId);
        })
        .catch(() => {
            showNotification('Network error. Please try again.', 'error');
        });
    }

    function validateRegPassword(inputEl, showMessage = false) {
        if (!inputEl) return false;
        const value = inputEl.value || '';
        const isValid = /[A-Z]/.test(value) && /[a-z]/.test(value) && /\d/.test(value) && /[^A-Za-z0-9]/.test(value) && value.length >= 10;
        const parent = inputEl.parentNode;
        const existing = parent.querySelector('.pwd-error');
        if (existing) existing.remove();
        inputEl.classList.remove('border-red-500', 'ring-red-500');
        if (!isValid && showMessage) {
            inputEl.classList.add('border-red-500', 'ring-red-500');
        }
        return isValid;
    }

    function validateConfirmPassword(showMessage = false) {
        return validateConfirmPasswordFor('regPassword', 'confirmPassword', showMessage, 'confirm-error');
    }

    function validateConfirmPasswordFor(pwdId, confirmId, showMessage, errorClass) {
        const pwd = document.getElementById(pwdId);
        const confirm = document.getElementById(confirmId);
        if (!pwd || !confirm) return false;
        const matches = (confirm.value || '').trim() === (pwd.value || '').trim();
        const wrapper = confirm.parentNode;
        const existing = wrapper.parentNode.querySelector('.' + errorClass);
        if (existing && existing.previousElementSibling !== wrapper) {
            existing.remove();
        }
        confirm.classList.remove('border-red-500', 'ring-red-500');
        const msgExisting = wrapper.parentNode.querySelector('.' + errorClass);
        if (matches) {
            if (msgExisting) msgExisting.remove();
            return true;
        }
        if (!matches && showMessage) {
            confirm.classList.add('border-red-500', 'ring-red-500');
            let msg = wrapper.parentNode.querySelector('.' + errorClass);
            if (!msg) {
                msg = document.createElement('div');
                msg.className = errorClass + ' text-red-500 text-sm mt-1';
                if (wrapper.nextSibling) {
                    wrapper.parentNode.insertBefore(msg, wrapper.nextSibling);
                } else {
                    wrapper.parentNode.appendChild(msg);
                }
            }
            msg.textContent = 'Passwords do not match.';
        }
        return matches;
    }

    function updatePasswordChecklistFor(listId, value) {
        const checks = {
            length: value.length >= 10,
            upper: /[A-Z]/.test(value),
            lower: /[a-z]/.test(value),
            number: /\d/.test(value),
            special: /[^A-Za-z0-9]/.test(value)
        };
        const list = document.getElementById(listId);
        if (!list) return;
        Object.keys(checks).forEach(key => {
            const item = list.querySelector(`.req-item[data-check="${key}"]`);
            if (!item) return;
            if (checks[key]) {
                item.classList.add('met');
            } else {
                item.classList.remove('met');
            }
        });
    }

    function updatePasswordChecklist(value) {
        updatePasswordChecklistFor('pwdChecklist', value);
    }

    function showNotification(message, type = 'info') {
        const existingNotifications = document.querySelectorAll('.notification');
        existingNotifications.forEach(notification => notification.remove());
        const notification = document.createElement('div');
        notification.className = `notification fixed top-4 right-4 z-50 px-6 py-4 rounded-lg shadow-lg transform transition-all duration-300 translate-x-full`;
        switch (type) {
            case 'success': notification.classList.add('bg-green-500', 'text-white'); break;
            case 'error': notification.classList.add('bg-red-500', 'text-white'); break;
            case 'warning': notification.classList.add('bg-yellow-500', 'text-white'); break;
            default: notification.classList.add('bg-blue-500', 'text-white');
        }
        notification.innerHTML = `
            <div class="flex items-center space-x-2">
                <i class="fas fa-${getNotificationIcon(type)}"></i>
                <span>${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        document.body.appendChild(notification);
        setTimeout(() => { notification.classList.remove('translate-x-full'); }, 100);
        setTimeout(() => {
            if (notification.parentNode) {
                notification.classList.add('translate-x-full');
                setTimeout(() => { if (notification.parentNode) notification.remove(); }, 300);
            }
        }, 5000);
    }

    function getNotificationIcon(type) {
        switch (type) {
            case 'success': return 'check-circle';
            case 'error': return 'exclamation-circle';
            case 'warning': return 'exclamation-triangle';
            default: return 'info-circle';
        }
    }

    function addSmoothScrolling() {
        const links = document.querySelectorAll('a[href^="#"]');
        links.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    }

    function openOtpModal(expiresInSeconds = 180) {
        const modal = document.getElementById('otpModal');
        const resend = document.getElementById('resendOtp');
        const error = document.getElementById('otpError');
        const submit = document.getElementById('submitOtp');
        if (!modal) return;
        error.classList.add('hidden');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.classList.add('overflow-hidden');
        startOtpTimer(expiresInSeconds);
        resend.disabled = true;
        submit.disabled = false;
        const inputs = Array.from(document.querySelectorAll('#otpInputs .otp-input'));
        inputs.forEach(i => i.value = '');
        setupOtpInputs(inputs);
        if (inputs[0]) inputs[0].focus();
    }

    function closeOtpModal() {
        const modal = document.getElementById('otpModal');
        if (!modal) return;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.classList.remove('overflow-hidden');
        stopOtpTimer();
    }

    let otpIntervalId = null;
    let otpExpiresAt = null;

    function startOtpTimer(seconds) {
        otpExpiresAt = Date.now() + seconds * 1000;
        updateOtpTimer();
        if (otpIntervalId) clearInterval(otpIntervalId);
        otpIntervalId = setInterval(updateOtpTimer, 1000);
    }

    function stopOtpTimer() {
        if (otpIntervalId) clearInterval(otpIntervalId);
        otpIntervalId = null;
    }

    function updateOtpTimer() {
        const timerEl = document.getElementById('otpTimer');
        const resend = document.getElementById('resendOtp');
        const submit = document.getElementById('submitOtp');
        const remaining = Math.max(0, Math.floor((otpExpiresAt - Date.now()) / 1000));
        const mm = String(Math.floor(remaining / 60)).padStart(2, '0');
        const ss = String(remaining % 60).padStart(2, '0');
        if (timerEl) timerEl.textContent = `${mm}:${ss}`;
        if (remaining === 0) {
            if (resend) resend.disabled = false;
            if (submit) submit.disabled = true;
            stopOtpTimer();
        }
    }

    // Setup OTP inputs
    function setupOtpInputs(inputs) {
        inputs.forEach((input, idx) => {
            input.addEventListener('input', (e) => {
                const value = e.target.value.replace(/\D/g, '').slice(0,1);
                e.target.value = value;
                if (value && idx < inputs.length - 1) inputs[idx + 1].focus();
            });
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !e.target.value && idx > 0) {
                    inputs[idx - 1].focus();
                }
            });
            input.addEventListener('paste', (e) => {
                const text = (e.clipboardData || window.clipboardData).getData('text');
                if (!text) return;
                const digits = text.replace(/\D/g, '').slice(0, inputs.length).split('');
                inputs.forEach((i, iIdx) => { i.value = digits[iIdx] || ''; });
                e.preventDefault();
                const nextIndex = Math.min(digits.length, inputs.length - 1);
                inputs[nextIndex].focus();
            });
        });
    }
  </script>
</body>
</html>
