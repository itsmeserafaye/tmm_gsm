﻿﻿﻿<?php
require_once __DIR__ . '/admin/includes/db.php';
require_once __DIR__ . '/includes/recaptcha.php';

$db = db();

$baseUrl = str_replace('\\', '/', (string) dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php')));
$baseUrl = $baseUrl === '/' ? '' : rtrim($baseUrl, '/');

$recaptchaCfg = recaptcha_config($db);
$recaptchaSiteKey = (string) ($recaptchaCfg['site_key'] ?? '');

if (!empty($_SESSION['operator_user_id'])) {
    header('Location: ' . $baseUrl . '/citizen/operator/index.php');
    exit;
}
if (!empty($_SESSION['user_id'])) {
    $role = (string) ($_SESSION['role'] ?? '');
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
    <title>TMM - Welcome</title>
    <link rel="icon" type="image/png"
        href="<?php echo htmlspecialchars($baseUrl); ?>/includes/TRANSPORT%20%26%20MOBILITY%20MANAGEMENT%20(3).png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/gsm_login/Login/styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <script src="<?php echo htmlspecialchars($baseUrl); ?>/tmm_form_enhancements.js?v=<?php echo time(); ?>" defer></script>
</head>

<body class="bg-custom-bg min-h-screen flex flex-col">
    <header class="py-3">
        <div class="container mx-auto px-6">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center shadow-lg">
                        <img src="<?php echo htmlspecialchars($baseUrl); ?>/includes/TRANSPORT%20%26%20MOBILITY%20MANAGEMENT%20(3).png"
                            alt="TMM Logo" class="h-10 w-auto">
                    </div>
                    <div class="leading-tight">
                        <h1 class="text-xl lg:text-2xl font-bold" style="font-weight: 700;">
                            <span class="text-slate-800">Transport & Mobility</span> <span
                                class="text-custom-primary">Management</span>
                        </h1>
                        <div class="text-xs text-custom-secondary">Transport & Mobility Management System</div>
                    </div>
                </div>
                <div class="flex items-center gap-8">
                    <div class="text-right">
                        <div class="text-sm">
                            <div id="currentDateTime" class="font-semibold text-slate-600"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="flex-1">
        <section id="home" class="container mx-auto px-6 pt-6">
            <div class="relative overflow-hidden rounded-2xl shadow-2xl bg-gradient-to-br from-slate-50 to-indigo-50">
                <div class="relative px-6 py-14 md:py-20">
                    <div class="flex flex-col items-center text-center">
                        <div
                            class="w-20 h-20 md:w-24 md:h-24 bg-white rounded-full flex items-center justify-center shadow-lg mb-5">
                            <img src="<?php echo htmlspecialchars($baseUrl); ?>/includes/TRANSPORT%20%26%20MOBILITY%20MANAGEMENT%20(3).png"
                                alt="TMM" class="h-16 md:h-20 w-auto" style="height: 5rem; width: auto;">
                        </div>
                        <div class="text-5xl md:text-6xl font-extrabold tracking-tight text-slate-900">
                            Transport & Mobility<br /><span class="text-custom-primary">Management</span>
                        </div>
                        <div class="mt-2 text-lg md:text-xl font-semibold text-custom-secondary">
                            Transport & Mobility Management System
                        </div>
                        <div class="mt-6">
                            <a href="#systems"
                                class="inline-flex items-center gap-2 bg-custom-primary text-white px-5 py-3 rounded-xl font-semibold btn-primary">
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
                <h2 class="text-3xl md:text-4xl font-bold text-slate-900">TMM PORTALS</h2>
                <div class="mt-2 text-sm text-custom-secondary">Click any system to access its dedicated portal</div>
            </div>

            <div class="mt-10 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Staff Portal -->
                <div class="bg-white rounded-2xl shadow-lg border border-slate-100 p-6 hover:shadow-2xl transition-all">
                    <div class="flex items-center justify-center">
                        <div class="w-16 h-16 rounded-2xl bg-slate-50 flex items-center justify-center">
                            <i class="fas fa-user-shield text-custom-primary text-2xl"></i>
                        </div>
                    </div>
                    <div class="mt-5 text-center">
                        <div class="text-lg font-bold text-slate-900">Staff Portal</div>
                        <div class="mt-1 text-sm text-slate-600">Administration and management access.</div>
                        <a href="<?php echo htmlspecialchars($baseUrl); ?>/gsm_login/index.php?mode=staff"
                            class="mt-5 inline-flex items-center justify-center gap-2 text-custom-primary font-semibold hover:underline">
                            Access System <i class="fas fa-arrow-right text-xs"></i>
                        </a>
                    </div>
                </div>

                <!-- Operator Portal -->
                <div class="bg-white rounded-2xl shadow-lg border border-slate-100 p-6 hover:shadow-2xl transition-all">
                    <div class="flex items-center justify-center">
                        <div class="w-16 h-16 rounded-2xl bg-slate-50 flex items-center justify-center">
                            <i class="fas fa-bus text-custom-primary text-2xl"></i>
                        </div>
                    </div>
                    <div class="mt-5 text-center">
                        <div class="text-lg font-bold text-slate-900">Operator Portal</div>
                        <div class="mt-1 text-sm text-slate-600">PUV operator services. Plate number required.</div>
                        <div class="mt-5 flex items-center justify-center gap-4">
                            <a href="<?php echo htmlspecialchars($baseUrl); ?>/gsm_login/index.php?mode=operator"
                                class="inline-flex items-center justify-center gap-2 text-custom-primary font-semibold hover:underline">
                                Access System <i class="fas fa-arrow-right text-xs"></i>
                            </a>
                            <button type="button" id="btnOperatorRegisterOpen"
                                class="text-custom-primary font-semibold hover:underline">Register</button>
                        </div>
                    </div>
                </div>

                <!-- Commuter Portal -->
                <div class="bg-white rounded-2xl shadow-lg border border-slate-100 p-6 hover:shadow-2xl transition-all">
                    <div class="flex items-center justify-center">
                        <div class="w-16 h-16 rounded-2xl bg-slate-50 flex items-center justify-center">
                            <i class="fas fa-users text-custom-primary text-2xl"></i>
                        </div>
                    </div>
                    <div class="mt-5 text-center">
                        <div class="text-lg font-bold text-slate-900">Commuter Portal</div>
                        <div class="mt-1 text-sm text-slate-600">Citizen services for commuters. No plate number
                            required.</div>
                        <div class="mt-5 flex items-center justify-center gap-4">
                            <a href="<?php echo htmlspecialchars($baseUrl); ?>/citizen/commuter/index.php"
                                class="inline-flex items-center justify-center gap-2 text-custom-primary font-semibold hover:underline">
                                Access Portal <i class="fas fa-arrow-right text-xs"></i>
                            </a>
                            <button type="button" id="showRegister"
                                class="text-custom-primary font-semibold hover:underline">Register</button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Key Features Section -->
        <section id="features" class="container mx-auto px-6 pb-12">
            <div class="text-center">
                <h2 class="text-3xl md:text-4xl font-bold text-slate-900">Key Features</h2>
                <div class="mt-2 text-sm text-custom-secondary">Designed for real transport operations and citizen services</div>
            </div>

            <div class="mt-8 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="bg-white rounded-2xl shadow-lg border border-slate-100 p-6">
                    <div class="w-12 h-12 rounded-xl bg-slate-50 flex items-center justify-center">
                        <i class="fas fa-id-card text-custom-primary text-xl"></i>
                    </div>
                    <div class="mt-4 font-bold text-slate-900">Franchise & Operator Records</div>
                    <div class="mt-1 text-sm text-slate-600">Maintain operators, vehicles, routes, and applications in one place.</div>
                </div>
                <div class="bg-white rounded-2xl shadow-lg border border-slate-100 p-6">
                    <div class="w-12 h-12 rounded-xl bg-slate-50 flex items-center justify-center">
                        <i class="fas fa-receipt text-custom-primary text-xl"></i>
                    </div>
                    <div class="mt-4 font-bold text-slate-900">Ticketing & Treasury Processing</div>
                    <div class="mt-1 text-sm text-slate-600">Issue tickets, validate, and record official receipts for settlement.</div>
                </div>
                <div class="bg-white rounded-2xl shadow-lg border border-slate-100 p-6">
                    <div class="w-12 h-12 rounded-xl bg-slate-50 flex items-center justify-center">
                        <i class="fas fa-clipboard-check text-custom-primary text-xl"></i>
                    </div>
                    <div class="mt-4 font-bold text-slate-900">Inspection Workflows</div>
                    <div class="mt-1 text-sm text-slate-600">Track inspection steps and ensure compliance with structured records.</div>
                </div>
                <div class="bg-white rounded-2xl shadow-lg border border-slate-100 p-6">
                    <div class="w-12 h-12 rounded-xl bg-slate-50 flex items-center justify-center">
                        <i class="fas fa-square-parking text-custom-primary text-xl"></i>
                    </div>
                    <div class="mt-4 font-bold text-slate-900">Terminal & Parking Operations</div>
                    <div class="mt-1 text-sm text-slate-600">Manage terminals, parking slots, and facility usage efficiently.</div>
                </div>
                <div class="bg-white rounded-2xl shadow-lg border border-slate-100 p-6">
                    <div class="w-12 h-12 rounded-xl bg-slate-50 flex items-center justify-center">
                        <i class="fas fa-shield-halved text-custom-primary text-xl"></i>
                    </div>
                    <div class="mt-4 font-bold text-slate-900">Role-Based Access</div>
                    <div class="mt-1 text-sm text-slate-600">Keep modules protected with permissions and activity monitoring.</div>
                </div>
                <div class="bg-white rounded-2xl shadow-lg border border-slate-100 p-6">
                    <div class="w-12 h-12 rounded-xl bg-slate-50 flex items-center justify-center">
                        <i class="fas fa-chart-line text-custom-primary text-xl"></i>
                    </div>
                    <div class="mt-4 font-bold text-slate-900">Analytics</div>
                    <div class="mt-1 text-sm text-slate-600">Use dashboards and reports to support planning and decisions.</div>
                </div>
            </div>
        </section>

        <!-- Streamlined Access Section -->
        <section class="container mx-auto px-6 pb-12">
            <div class="relative overflow-hidden rounded-2xl shadow-xl bg-white border border-slate-100">
                <div class="absolute inset-0 bg-gradient-to-r from-blue-50/50 to-green-50/50"></div>
                
                <div class="relative px-6 py-16">
                    <div class="text-center mb-16">
                        <h2 class="text-3xl md:text-4xl font-bold text-custom-primary mb-4">Streamlined Access</h2>
                        <p class="text-slate-600 max-w-2xl mx-auto">Access transportation services securely and efficiently in just three simple steps.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 relative">
                        <!-- Connector Line (Desktop) -->
                        <div class="hidden md:block absolute top-1/2 left-0 w-full h-1 bg-gradient-to-r from-blue-200 to-green-200 -translate-y-1/2 z-0 transform scale-x-75"></div>

                        <!-- Step 1 -->
                        <div class="relative z-10 text-center group">
                            <div class="w-20 h-20 mx-auto bg-white rounded-2xl shadow-lg border-2 border-blue-100 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform duration-300">
                                <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center text-custom-primary">
                                    <i class="fas fa-th-large text-2xl"></i>
                                </div>
                            </div>
                            <h3 class="text-xl font-bold text-slate-800 mb-2">1. Select Portal</h3>
                            <p class="text-slate-600 text-sm">Choose the dedicated portal for your role (Staff, Operator, or Commuter).</p>
                        </div>

                        <!-- Step 2 -->
                        <div class="relative z-10 text-center group">
                            <div class="w-20 h-20 mx-auto bg-white rounded-2xl shadow-lg border-2 border-blue-100 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform duration-300">
                                <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center text-custom-primary">
                                    <i class="fas fa-user-check text-2xl"></i>
                                </div>
                            </div>
                            <h3 class="text-xl font-bold text-slate-800 mb-2">2. Authenticate</h3>
                            <p class="text-slate-600 text-sm">Log in securely or register a new account to verify your identity.</p>
                        </div>

                        <!-- Step 3 -->
                        <div class="relative z-10 text-center group">
                            <div class="w-20 h-20 mx-auto bg-white rounded-2xl shadow-lg border-2 border-green-100 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform duration-300">
                                <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center text-custom-accent">
                                    <i class="fas fa-rocket text-2xl"></i>
                                </div>
                            </div>
                            <h3 class="text-xl font-bold text-slate-800 mb-2">3. Manage</h3>
                            <p class="text-slate-600 text-sm">Access your dashboard, manage applications, or view real-time data.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="bg-custom-primary text-white py-6 mt-8">
        <div class="container mx-auto px-6">
            <div class="flex flex-col lg:flex-row justify-between items-center">
                <div class="text-center lg:text-left mb-2 lg:mb-0">
                    <h3 class="text-lg font-bold mb-1">Transport & Mobility Management</h3>
                    <p class="text-xs opacity-90">
                        For any inquiries, please call 122 or email helpdesk@tmm.gov.ph
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

    <!-- Commuter Registration Modal -->
    <div id="registerFormContainer" class="fixed inset-0 bg-black/40 flex items-start justify-center pt-20 px-4 hidden overflow-y-auto z-50">
        <div class="bg-white rounded-2xl shadow-2xl p-6 max-w-2xl w-full glass-card form-compact max-h-[80vh] overflow-y-auto">
            <div class="sticky top-0 bg-white/95 backdrop-blur border-b border-gray-200 z-10 -mx-6 px-6 py-3 text-center">
                <h2 class="text-xl md:text-2xl font-semibold text-custom-primary">Create your TMM account</h2>
                <div class="mt-1 text-xs text-gray-500">
                    Registering as an operator?
                    <button type="button" id="openOperatorRegisterFromCitizen" class="text-custom-primary font-semibold hover:underline">Register here</button>
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
                        <div class="mt-1 flex items-center">
                            <input type="checkbox" id="noMiddleName" class="w-4 h-4 text-custom-primary border-gray-300 rounded focus:ring-custom-primary">
                            <label for="noMiddleName" class="ml-2 text-xs text-gray-500">I do not have a middle name</label>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Suffix (Optional)</label>
                        <input type="text" name="suffix" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>
                </div>
                <div>
                    <label class="block text-sm mb-1">Email Address<span class="required-asterisk">*</span></label>
                    <input type="email" name="email" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="relative">
                        <label class="block text-sm mb-1">Password<span class="required-asterisk">*</span></label>
                        <input type="password" name="password" id="regPassword" required class="w-full px-3 py-2 border border-gray-300 rounded-lg pr-10">
                        <button type="button" class="absolute inset-y-0 right-0 px-3 text-gray-500 hover:text-gray-700 toggle-password" data-target="regPassword" style="top: 24px;">
                            <i class="far fa-eye"></i>
                        </button>
                    </div>
                    <div class="relative">
                        <label class="block text-sm mb-1">Confirm Password<span class="required-asterisk">*</span></label>
                        <input type="password" name="confirm_password" id="regConfirmPassword" required class="w-full px-3 py-2 border border-gray-300 rounded-lg pr-10">
                        <button type="button" class="absolute inset-y-0 right-0 px-3 text-gray-500 hover:text-gray-700 toggle-password" data-target="regConfirmPassword" style="top: 24px;">
                            <i class="far fa-eye"></i>
                        </button>
                    </div>
                </div>
                <?php if ($recaptchaSiteKey !== ''): ?>
                    <div><div id="citizenRecaptcha" data-sitekey="<?php echo htmlspecialchars($recaptchaSiteKey); ?>"></div></div>
                <?php endif; ?>
                <div class="flex justify-end space-x-3 pt-2">
                    <button type="button" id="closeRegister" class="bg-red-500 text-white px-4 py-2 rounded-lg">Cancel</button>
                    <button type="submit" id="btnRegisterSubmit" class="bg-custom-primary text-white px-4 py-2 rounded-lg">Register</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Operator Registration Modal -->
    <div id="operatorRegisterModal" class="fixed inset-0 bg-black/40 hidden items-start justify-center pt-20 px-4 overflow-y-auto z-50">
        <div class="bg-white rounded-2xl shadow-2xl p-6 max-w-2xl w-full glass-card form-compact max-h-[80vh] overflow-y-auto">
            <div class="sticky top-0 bg-white/95 backdrop-blur border-b border-gray-200 z-10 -mx-6 px-6 py-3 text-center">
                <h2 class="text-xl md:text-2xl font-semibold text-custom-primary">Operator Registration</h2>
            </div>
            <form id="operatorRegisterForm" class="space-y-5 pt-4">
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
                        <label class="block text-sm mb-1">Middle Name</label>
                        <input type="text" name="middleName" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Suffix</label>
                        <input type="text" name="suffix" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>
                </div>
                <div>
                    <label class="block text-sm mb-1">Email Address<span class="required-asterisk">*</span></label>
                    <input type="email" name="email" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                </div>
                <div>
                    <label class="block text-sm mb-1">Vehicle Plate Number<span class="required-asterisk">*</span></label>
                    <input type="text" name="plate_number" required placeholder="ABC-1234" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="relative">
                        <label class="block text-sm mb-1">Password<span class="required-asterisk">*</span></label>
                        <input type="password" name="password" id="opRegPassword" required class="w-full px-3 py-2 border border-gray-300 rounded-lg pr-10">
                        <button type="button" class="absolute inset-y-0 right-0 px-3 text-gray-500 hover:text-gray-700 toggle-password" data-target="opRegPassword" style="top: 24px;">
                            <i class="far fa-eye"></i>
                        </button>
                    </div>
                    <div class="relative">
                        <label class="block text-sm mb-1">Confirm Password<span class="required-asterisk">*</span></label>
                        <input type="password" name="confirm_password" id="opRegConfirmPassword" required class="w-full px-3 py-2 border border-gray-300 rounded-lg pr-10">
                        <button type="button" class="absolute inset-y-0 right-0 px-3 text-gray-500 hover:text-gray-700 toggle-password" data-target="opRegConfirmPassword" style="top: 24px;">
                            <i class="far fa-eye"></i>
                        </button>
                    </div>
                </div>
                <?php if ($recaptchaSiteKey !== ''): ?>
                    <div><div id="operatorRecaptcha" data-sitekey="<?php echo htmlspecialchars($recaptchaSiteKey); ?>"></div></div>
                <?php endif; ?>
                <div class="flex justify-end space-x-3 pt-2">
                    <button type="button" id="btnOperatorRegisterCancel" class="bg-red-500 text-white px-4 py-2 rounded-lg">Cancel</button>
                    <button type="submit" id="btnOperatorRegisterSubmit" class="bg-custom-primary text-white px-4 py-2 rounded-lg">Register</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Terms Modal -->
    <div id="termsModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center p-4 z-50">
        <div class="bg-white rounded-xl shadow-2xl max-w-3xl w-full max-h-[80vh] overflow-y-auto">
            <div class="sticky top-0 bg-white border-b px-6 py-4 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-custom-primary">TMM Terms of Service</h3>
                <button type="button" id="closeTerms" class="text-gray-500 hover:text-gray-700"><i class="fas fa-times"></i></button>
            </div>
            <div class="px-6 py-4 space-y-4 text-sm leading-6">
                <p><strong>Welcome to TMM!</strong></p>
                <p>By accessing or using the Transport & Mobility Management System, you agree to comply with all applicable laws and regulations.</p>
                <!-- Content truncated for brevity -->
            </div>
            <div class="border-t px-6 py-3 flex justify-end">
                <button type="button" id="closeTermsBottom" class="px-4 py-2 rounded-lg bg-custom-primary text-white">Close</button>
            </div>
        </div>
    </div>

    <!-- Privacy Modal -->
    <div id="privacyModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center p-4 z-50">
        <div class="bg-white rounded-xl shadow-2xl max-w-3xl w-full max-h-[80vh] overflow-y-auto">
            <div class="sticky top-0 bg-white border-b px-6 py-4 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-custom-primary">TMM Data Privacy Policy</h3>
                <button type="button" id="closePrivacy" class="text-gray-500 hover:text-gray-700"><i class="fas fa-times"></i></button>
            </div>
            <div class="px-6 py-4 space-y-4 text-sm leading-6">
                <p><strong>Your privacy is important to us.</strong> This policy outlines how TMM manages and protects your data.</p>
                <!-- Content truncated for brevity -->
            </div>
            <div class="border-t px-6 py-3 flex justify-end">
                <button type="button" id="closePrivacyBottom" class="px-4 py-2 rounded-lg bg-custom-primary text-white">Close</button>
            </div>
        </div>
    </div>

    <script>
        const BASE_URL = <?php echo json_encode($baseUrl); ?>;
        const RECAPTCHA_SITE_KEY = <?php echo json_encode($recaptchaSiteKey); ?>;
        
        document.addEventListener('DOMContentLoaded', function () {
            updateDateTime();
            setInterval(updateDateTime, 1000);

            // Toggle Password
            document.querySelectorAll('.toggle-password').forEach(btn => {
                btn.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-target');
                    const input = document.getElementById(targetId);
                    if (input) {
                        if (input.type === 'password') {
                            input.type = 'text';
                            this.innerHTML = '<i class="far fa-eye-slash"></i>';
                        } else {
                            input.type = 'password';
                            this.innerHTML = '<i class="far fa-eye"></i>';
                        }
                    }
                });
            });

            // No Middle Name Logic
            const noMiddleName = document.getElementById('noMiddleName');
            const middleName = document.getElementById('middleName');
            const middleAsterisk = document.getElementById('middleAsterisk');
            if (noMiddleName && middleName) {
                noMiddleName.addEventListener('change', function() {
                    if (this.checked) {
                        middleName.disabled = true;
                        middleName.value = '';
                        middleName.required = false;
                        if(middleAsterisk) middleAsterisk.style.display = 'none';
                    } else {
                        middleName.disabled = false;
                        middleName.required = true;
                        if(middleAsterisk) middleAsterisk.style.display = 'inline';
                    }
                });
            }

            // Register Modals Logic
            const regModal = document.getElementById('registerFormContainer');
            const showRegBtn = document.getElementById('showRegister');
            const closeRegBtn = document.getElementById('closeRegister');
            
            if (showRegBtn && regModal) {
                showRegBtn.addEventListener('click', () => {
                    regModal.classList.remove('hidden');
                    document.body.style.overflow = 'hidden';
                    tryRenderCitizenRecaptcha();
                });
            }
            if (closeRegBtn && regModal) {
                closeRegBtn.addEventListener('click', () => {
                    regModal.classList.add('hidden');
                    document.body.style.overflow = '';
                });
            }

            const opRegModal = document.getElementById('operatorRegisterModal');
            const opRegBtn = document.getElementById('btnOperatorRegisterOpen');
            const opRegCancel = document.getElementById('btnOperatorRegisterCancel');
            const openOpFromCit = document.getElementById('openOperatorRegisterFromCitizen');

            function openOpReg() {
                if(regModal) regModal.classList.add('hidden');
                if(opRegModal) {
                    opRegModal.classList.remove('hidden');
                    opRegModal.classList.add('flex');
                    document.body.style.overflow = 'hidden';
                    tryRenderOpRecaptcha();
                }
            }

            if (opRegBtn) opRegBtn.addEventListener('click', openOpReg);
            if (openOpFromCit) openOpFromCit.addEventListener('click', openOpReg);
            
            if (opRegCancel && opRegModal) {
                opRegCancel.addEventListener('click', () => {
                    opRegModal.classList.add('hidden');
                    opRegModal.classList.remove('flex');
                    document.body.style.overflow = '';
                });
            }

            // Terms & Privacy
            const termsModal = document.getElementById('termsModal');
            const privacyModal = document.getElementById('privacyModal');
            const footerTerms = document.getElementById('footerTerms');
            const footerPrivacy = document.getElementById('footerPrivacy');
            const closeTerms = document.getElementById('closeTerms');
            const closeTermsBottom = document.getElementById('closeTermsBottom');
            const closePrivacy = document.getElementById('closePrivacy');
            const closePrivacyBottom = document.getElementById('closePrivacyBottom');

            if(footerTerms && termsModal) footerTerms.addEventListener('click', () => termsModal.classList.remove('hidden', 'flex'));
            if(footerTerms && termsModal) footerTerms.addEventListener('click', () => { termsModal.classList.remove('hidden'); termsModal.classList.add('flex'); });
            if(closeTerms) closeTerms.addEventListener('click', () => termsModal.classList.add('hidden'));
            if(closeTermsBottom) closeTermsBottom.addEventListener('click', () => termsModal.classList.add('hidden'));

            if(footerPrivacy && privacyModal) footerPrivacy.addEventListener('click', () => { privacyModal.classList.remove('hidden'); privacyModal.classList.add('flex'); });
            if(closePrivacy) closePrivacy.addEventListener('click', () => privacyModal.classList.add('hidden'));
            if(closePrivacyBottom) closePrivacyBottom.addEventListener('click', () => privacyModal.classList.add('hidden'));

        });

        function updateDateTime() {
            const now = new Date();
            const el = document.getElementById('currentDateTime');
            if (el) el.textContent = now.toLocaleString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' });
        }

        let citizenRecaptchaWidgetId = null;
        function tryRenderCitizenRecaptcha() {
            const el = document.getElementById('citizenRecaptcha');
            if (!el || !window.grecaptcha || citizenRecaptchaWidgetId !== null) return;
            const siteKey = el.getAttribute('data-sitekey');
            if (siteKey) citizenRecaptchaWidgetId = window.grecaptcha.render(el, { sitekey: siteKey });
        }

        let operatorRecaptchaWidgetId = null;
        function tryRenderOpRecaptcha() {
            const el = document.getElementById('operatorRecaptcha');
            if (!el || !window.grecaptcha || operatorRecaptchaWidgetId !== null) return;
            const siteKey = el.getAttribute('data-sitekey');
            if (siteKey) operatorRecaptchaWidgetId = window.grecaptcha.render(el, { sitekey: siteKey });
        }
    </script>
</body>
</html>