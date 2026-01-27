﻿﻿﻿﻿﻿﻿﻿﻿﻿﻿﻿﻿﻿﻿<?php
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
    <style>
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        @keyframes pulse-glow {
            0%, 100% { box-shadow: 0 0 20px rgba(74, 144, 226, 0.3); }
            50% { box-shadow: 0 0 40px rgba(74, 144, 226, 0.6); }
        }
        @keyframes slide-in-left {
            from { opacity: 0; transform: translateX(-50px); }
            to { opacity: 1; transform: translateX(0); }
        }
        @keyframes slide-in-right {
            from { opacity: 0; transform: translateX(50px); }
            to { opacity: 1; transform: translateX(0); }
        }
        @keyframes fade-in-up {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-float { animation: float 6s ease-in-out infinite; }
        .animate-pulse-glow { animation: pulse-glow 3s ease-in-out infinite; }
        .animate-slide-in-left { animation: slide-in-left 0.8s ease-out; }
        .animate-slide-in-right { animation: slide-in-right 0.8s ease-out; }
        .animate-fade-in-up { animation: fade-in-up 0.8s ease-out; }
        .delay-100 { animation-delay: 0.1s; }
        .delay-200 { animation-delay: 0.2s; }
        .delay-300 { animation-delay: 0.3s; }
        .delay-400 { animation-delay: 0.4s; }
        .delay-500 { animation-delay: 0.5s; }
        .delay-600 { animation-delay: 0.6s; }
        .gradient-text {
            background: linear-gradient(135deg, #5ba3f5, #66bb6a);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
    </style>
</head>

<body class="bg-custom-bg min-h-screen flex flex-col" style="background-color: #fbfbfb;">
    <header class="fixed top-0 left-0 right-0 py-4 bg-gradient-to-r from-white via-blue-50 to-blue-100 border-b-4 border-gradient-to-r from-blue-500 via-blue-600 to-blue-700 shadow-lg z-50" style="background: linear-gradient(to right, #ffffff, #e8f4f8, #d6eaf5); border-bottom: 4px solid #4a90e2;">
        <div class="container mx-auto px-6">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center shadow-xl p-0.5" style="background: linear-gradient(to bottom right, #4a90e2, #357abd);">
                        <div class="w-full h-full bg-white rounded-full flex items-center justify-center">
                            <img src="<?php echo htmlspecialchars($baseUrl); ?>/includes/TRANSPORT%20%26%20MOBILITY%20MANAGEMENT%20(3).png"
                                alt="TMM Logo" class="h-10 w-auto">
                        </div>
                    </div>
                    <div class="leading-tight">
                        <h1 class="text-xl lg:text-2xl font-bold" style="font-weight: 700;">
                            <span class="text-slate-800">Transport & Mobility</span> <span
                                class="bg-gradient-to-r from-blue-600 to-blue-700 bg-clip-text text-transparent" style="background: linear-gradient(to right, #4a90e2, #357abd); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Management</span>
                        </h1>
                        <div class="text-xs font-semibold bg-gradient-to-r from-blue-600 to-blue-700 bg-clip-text text-transparent" style="background: linear-gradient(to right, #4a90e2, #357abd); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Transport & Mobility Management System</div>
                    </div>
                </div>
                <div class="flex items-center gap-8">
                    <div class="text-right">
                        <div class="text-sm">
                            <div id="currentDateTime" class="font-semibold bg-gradient-to-r from-blue-600 to-blue-700 bg-clip-text text-transparent" style="background: linear-gradient(to right, #4a90e2, #357abd); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="flex-1 pt-24">
        <section id="home" class="container mx-auto px-6 pt-6">
            <div class="relative overflow-hidden rounded-3xl shadow-2xl" style="background: linear-gradient(135deg, #5ba3f5 0%, #4a90e2 50%, #2e7bc4 100%);">
                <!-- Animated overlay -->
                <div class="absolute inset-0 opacity-30">
                    <div class="absolute top-0 left-0 w-96 h-96 bg-white rounded-full mix-blend-overlay filter blur-3xl animate-pulse" style="animation-duration: 4s;"></div>
                    <div class="absolute bottom-0 right-0 w-96 h-96 bg-green-200 rounded-full mix-blend-overlay filter blur-3xl animate-pulse" style="animation-duration: 6s; animation-delay: 1s;"></div>
                    <div class="absolute top-1/2 left-1/2 w-64 h-64 bg-orange-200 rounded-full mix-blend-overlay filter blur-3xl animate-pulse" style="animation-duration: 5s; animation-delay: 2s;"></div>
                </div>
                
                <div class="relative px-6 py-16 md:py-24">
                    <div class="flex flex-col items-center text-center">
                        <div class="w-24 h-24 md:w-28 md:h-28 bg-white/90 backdrop-blur-sm rounded-full flex items-center justify-center shadow-2xl mb-6 animate-float animate-pulse-glow">
                            <img src="<?php echo htmlspecialchars($baseUrl); ?>/includes/TRANSPORT%20%26%20MOBILITY%20MANAGEMENT%20(3).png"
                                alt="TMM" class="h-20 md:h-24 w-auto">
                        </div>
                        <div class="text-5xl md:text-7xl font-extrabold tracking-tight text-white drop-shadow-lg animate-fade-in-up">
                            Transport & Mobility<br /><span class="gradient-text" style="color: #66bb6a; background: linear-gradient(135deg, #66bb6a, #81c784); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Management</span>
                        </div>
                        <div class="mt-4 text-xl md:text-2xl font-semibold text-white/90 drop-shadow animate-fade-in-up delay-200">
                            Your Gateway to Smart Transportation Solutions
                        </div>
                        <div class="mt-8 flex gap-4 animate-fade-in-up delay-300">
                            <a href="#systems"
                                class="inline-flex items-center gap-2 bg-white text-blue-700 px-6 py-3 rounded-xl font-bold shadow-xl hover:shadow-2xl transform hover:scale-105 transition-all duration-300 hover:-translate-y-1" style="background: #ffffff; color: #2e7bc4;">
                                Explore Portals
                                <i class="fas fa-arrow-down text-sm"></i>
                            </a>
                            <a href="#features"
                                class="inline-flex items-center gap-2 bg-white/20 backdrop-blur-sm text-white border-2 border-white/50 px-6 py-3 rounded-xl font-bold shadow-xl hover:bg-white/30 transform hover:scale-105 transition-all duration-300 hover:-translate-y-1" style="background: rgba(255, 255, 255, 0.2); border-color: rgba(255, 255, 255, 0.6);">
                                Learn More
                                <i class="fas fa-info-circle text-sm"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="systems" class="container mx-auto px-6 py-12">
            <div class="text-center animate-fade-in-up">
                <h2 class="text-3xl md:text-4xl font-bold text-slate-900">TMM PORTALS</h2>
                <div class="mt-2 text-sm text-slate-600">Click any system to access its dedicated portal</div>
            </div>

            <div class="mt-10 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Staff Portal -->
                <div class="group relative bg-white rounded-3xl shadow-xl border-2 border-transparent p-8 hover:border-blue-500 hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-2 animate-fade-in-up delay-100" style="background: #ffffff; border-color: transparent;">
                    <div class="absolute inset-0 bg-gradient-to-br from-blue-50 to-blue-100 rounded-3xl opacity-0 group-hover:opacity-100 transition-opacity duration-300" style="background: linear-gradient(to bottom right, #e8f4f8, #d1e9f6);"></div>
                    <div class="absolute -top-3 -right-3 w-16 h-16 bg-blue-500 rounded-full opacity-20 group-hover:opacity-30 transition-opacity duration-300" style="background: #5ba3f5;"></div>
                    <div class="relative">
                        <div class="flex items-center justify-center">
                            <div class="w-20 h-20 rounded-2xl bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center shadow-lg transform group-hover:scale-110 group-hover:rotate-6 transition-all duration-300" style="background: linear-gradient(to bottom right, #5ba3f5, #2e7bc4);">
                                <i class="fas fa-user-shield text-white text-3xl"></i>
                            </div>
                        </div>
                        <div class="mt-6 text-center">
                            <div class="text-xl font-bold text-slate-900">Staff Portal</div>
                            <div class="mt-2 text-sm text-slate-600">Administration and management access for authorized personnel.</div>
                            <a href="<?php echo htmlspecialchars($baseUrl); ?>/gsm_login/index.php?mode=staff"
                                class="mt-6 inline-flex items-center justify-center gap-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white px-5 py-2.5 rounded-xl font-semibold shadow-md hover:shadow-xl transform hover:scale-105 transition-all duration-300" style="background: linear-gradient(to right, #5ba3f5, #2e7bc4);">
                                Access System <i class="fas fa-arrow-right text-xs"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Operator Portal -->
                <div class="group relative bg-white rounded-3xl shadow-xl border-2 border-transparent p-8 hover:border-orange-400 hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-2 animate-fade-in-up delay-200" style="background: #ffffff; border-color: transparent;">
                    <div class="absolute inset-0 bg-gradient-to-br from-orange-50 to-orange-100 rounded-3xl opacity-0 group-hover:opacity-100 transition-opacity duration-300" style="background: linear-gradient(to bottom right, #fff3e0, #ffe0b2);"></div>
                    <div class="absolute -top-3 -right-3 w-16 h-16 bg-orange-500 rounded-full opacity-20 group-hover:opacity-30 transition-opacity duration-300" style="background: #ff9800;"></div>
                    <div class="relative">
                        <div class="flex items-center justify-center">
                            <div class="w-20 h-20 rounded-2xl bg-gradient-to-br from-orange-500 to-orange-600 flex items-center justify-center shadow-lg transform group-hover:scale-110 group-hover:rotate-6 transition-all duration-300" style="background: linear-gradient(to bottom right, #ff9800, #f57c00);">
                                <i class="fas fa-bus text-white text-3xl"></i>
                            </div>
                        </div>
                        <div class="mt-6 text-center">
                            <div class="text-xl font-bold text-slate-900">Operator Portal</div>
                            <div class="mt-2 text-sm text-slate-600">PUV operator services and fleet management tools.</div>
                            <div class="mt-6 flex items-center justify-center gap-3">
                                <a href="<?php echo htmlspecialchars($baseUrl); ?>/gsm_login/index.php?mode=operator"
                                    class="inline-flex items-center justify-center gap-2 bg-gradient-to-r from-orange-500 to-orange-600 text-white px-4 py-2.5 rounded-xl font-semibold shadow-md hover:shadow-xl transform hover:scale-105 transition-all duration-300" style="background: linear-gradient(to right, #ff9800, #f57c00);">
                                    Login <i class="fas fa-arrow-right text-xs"></i>
                                </a>
                                <button type="button" id="btnOperatorRegisterOpen"
                                    class="inline-flex items-center gap-2 border-2 border-orange-500 text-orange-600 px-4 py-2.5 rounded-xl font-semibold hover:bg-orange-50 transition-all duration-300" style="border-color: #ff9800; color: #f57c00;">Register</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Commuter Portal -->
                <div class="group relative bg-white rounded-3xl shadow-xl border-2 border-transparent p-8 hover:border-green-500 hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-2 animate-fade-in-up delay-300" style="background: #ffffff; border-color: transparent;">
                    <div class="absolute inset-0 bg-gradient-to-br from-green-50 to-green-100 rounded-3xl opacity-0 group-hover:opacity-100 transition-opacity duration-300" style="background: linear-gradient(to bottom right, #e8f5e9, #c5e1a5);"></div>
                    <div class="absolute -top-3 -right-3 w-16 h-16 bg-green-500 rounded-full opacity-20 group-hover:opacity-30 transition-opacity duration-300" style="background: #66bb6a;"></div>
                    <div class="relative">
                        <div class="flex items-center justify-center">
                            <div class="w-20 h-20 rounded-2xl bg-gradient-to-br from-green-500 to-green-600 flex items-center justify-center shadow-lg transform group-hover:scale-110 group-hover:rotate-6 transition-all duration-300" style="background: linear-gradient(to bottom right, #66bb6a, #43a047);">
                                <i class="fas fa-users text-white text-3xl"></i>
                            </div>
                        </div>
                        <div class="mt-6 text-center">
                            <div class="text-xl font-bold text-slate-900">
                                Public Portal
                                <span class="ml-2 px-2 py-0.5 text-xs font-bold bg-green-100 text-green-700 rounded-full align-middle">OPEN ACCESS</span>
                            </div>
                            <div class="mt-2 text-sm text-slate-600">Citizen services and real-time transit information. No login required.</div>
                            <div class="mt-6 flex items-center justify-center gap-3">
                                <a href="<?php echo htmlspecialchars($baseUrl); ?>/citizen/commuter/index.php"
                                    class="inline-flex items-center justify-center gap-2 bg-gradient-to-r from-green-500 to-green-600 text-white px-4 py-2.5 rounded-xl font-semibold shadow-md hover:shadow-xl transform hover:scale-105 transition-all duration-300" style="background: linear-gradient(to right, #66bb6a, #43a047);">
                                    Enter as Guest <i class="fas fa-arrow-right text-xs"></i>
                                </a>
                                <button type="button" id="showRegister"
                                    class="inline-flex items-center gap-2 border-2 border-green-500 text-green-600 px-4 py-2.5 rounded-xl font-semibold hover:bg-green-50 transition-all duration-300" style="border-color: #66bb6a; color: #43a047;">Register (Optional)</button>
                            </div>
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
                <!-- Feature 1 -->
                <div class="group relative bg-white rounded-2xl shadow-lg border-2 border-transparent p-6 hover:border-blue-400 hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-1" style="background: #ffffff;">
                    <div class="absolute inset-0 bg-gradient-to-br from-blue-50 to-blue-100 rounded-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-300" style="background: linear-gradient(to bottom right, #e3f2fd, #bbdefb);"></div>
                    <div class="relative">
                        <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center shadow-md transform group-hover:scale-110 transition-transform duration-300" style="background: linear-gradient(to bottom right, #4a90e2, #357abd);">
                            <i class="fas fa-id-card text-white text-xl"></i>
                        </div>
                        <div class="mt-4 font-bold text-slate-900">Franchise & Operator Records</div>
                        <div class="mt-1 text-sm text-slate-600">Maintain operators, vehicles, routes, and applications in one place.</div>
                    </div>
                </div>
                
                <!-- Feature 2 -->
                <div class="group relative bg-white rounded-2xl shadow-lg border-2 border-transparent p-6 hover:border-gray-400 hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-1" style="background: #ffffff;">
                    <div class="absolute inset-0 bg-gradient-to-br from-gray-50 to-gray-100 rounded-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-300" style="background: linear-gradient(to bottom right, #f5f5f5, #e0e0e0);"></div>
                    <div class="relative">
                        <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-gray-500 to-gray-600 flex items-center justify-center shadow-md transform group-hover:scale-110 transition-transform duration-300" style="background: linear-gradient(to bottom right, #9aa5b1, #7a8591);">
                            <i class="fas fa-receipt text-white text-xl"></i>
                        </div>
                        <div class="mt-4 font-bold text-slate-900">Ticketing & Treasury Processing</div>
                        <div class="mt-1 text-sm text-slate-600">Issue tickets, validate, and record official receipts for settlement.</div>
                    </div>
                </div>
                
                <!-- Feature 3 -->
                <div class="group relative bg-white rounded-2xl shadow-lg border-2 border-transparent p-6 hover:border-green-400 hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-1" style="background: #ffffff;">
                    <div class="absolute inset-0 bg-gradient-to-br from-green-50 to-green-100 rounded-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-300" style="background: linear-gradient(to bottom right, #e8f5e9, #c5e1a5);"></div>
                    <div class="relative">
                        <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-green-500 to-green-600 flex items-center justify-center shadow-md transform group-hover:scale-110 transition-transform duration-300" style="background: linear-gradient(to bottom right, #66bb6a, #43a047);">
                            <i class="fas fa-clipboard-check text-white text-xl"></i>
                        </div>
                        <div class="mt-4 font-bold text-slate-900">Inspection Workflows</div>
                        <div class="mt-1 text-sm text-slate-600">Track inspection steps and ensure compliance with structured records.</div>
                    </div>
                </div>
                
                <!-- Feature 4 -->
                <div class="group relative bg-white rounded-2xl shadow-lg border-2 border-transparent p-6 hover:border-orange-400 hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-1" style="background: #ffffff;">
                    <div class="absolute inset-0 bg-gradient-to-br from-orange-50 to-orange-100 rounded-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-300" style="background: linear-gradient(to bottom right, #fff3e0, #ffe0b2);"></div>
                    <div class="relative">
                        <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-orange-500 to-orange-600 flex items-center justify-center shadow-md transform group-hover:scale-110 transition-transform duration-300" style="background: linear-gradient(to bottom right, #ff9800, #f57c00);">
                            <i class="fas fa-square-parking text-white text-xl"></i>
                        </div>
                        <div class="mt-4 font-bold text-slate-900">Terminal & Parking Operations</div>
                        <div class="mt-1 text-sm text-slate-600">Manage terminals, parking slots, and facility usage efficiently.</div>
                    </div>
                </div>
                
                <!-- Feature 5 -->
                <div class="group relative bg-white rounded-2xl shadow-lg border-2 border-transparent p-6 hover:border-blue-400 hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-1" style="background: #ffffff;">
                    <div class="absolute inset-0 bg-gradient-to-br from-blue-50 to-blue-100 rounded-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-300" style="background: linear-gradient(to bottom right, #e8f4f8, #d1e9f6);"></div>
                    <div class="relative">
                        <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-blue-600 to-blue-700 flex items-center justify-center shadow-md transform group-hover:scale-110 transition-transform duration-300" style="background: linear-gradient(to bottom right, #2e7bc4, #1976d2);">
                            <i class="fas fa-shield-halved text-white text-xl"></i>
                        </div>
                        <div class="mt-4 font-bold text-slate-900">Role-Based Access</div>
                        <div class="mt-1 text-sm text-slate-600">Keep modules protected with permissions and activity monitoring.</div>
                    </div>
                </div>
                
                <!-- Feature 6 -->
                <div class="group relative bg-white rounded-2xl shadow-lg border-2 border-transparent p-6 hover:border-gray-400 hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-1" style="background: #ffffff;">
                    <div class="absolute inset-0 bg-gradient-to-br from-gray-50 to-gray-100 rounded-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-300" style="background: linear-gradient(to bottom right, #f5f5f5, #e8eaed);"></div>
                    <div class="relative">
                        <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-gray-600 to-gray-700 flex items-center justify-center shadow-md transform group-hover:scale-110 transition-transform duration-300" style="background: linear-gradient(to bottom right, #78909c, #546e7a);">
                            <i class="fas fa-chart-line text-white text-xl"></i>
                        </div>
                        <div class="mt-4 font-bold text-slate-900">Analytics</div>
                        <div class="mt-1 text-sm text-slate-600">Use dashboards and reports to support planning and decisions.</div>
                    </div>
                </div>
            </div>
        </section>


        <!-- Streamlined Access Section -->
        <section class="container mx-auto px-6 pb-12">
            <div class="text-center mb-10 animate-fade-in-up">
                <h2 class="text-3xl md:text-4xl font-bold text-slate-900">How It Works</h2>
                <p class="mt-2 text-slate-600 max-w-2xl mx-auto">Get started with TMM in three simple steps</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 relative">
                <!-- Connector Line -->
                <div class="hidden md:block absolute top-20 left-1/4 right-1/4 h-0.5 bg-gradient-to-r from-blue-200 via-gray-200 to-green-200" style="background: linear-gradient(to right, #bbdefb, #e0e0e0, #c5e1a5);"></div>

                <!-- Step 1 -->
                <div class="relative animate-fade-in-up delay-100">
                    <div class="bg-white rounded-2xl shadow-lg p-8 hover:shadow-2xl transition-all duration-500 border-2 border-transparent hover:border-blue-200 transform hover:-translate-y-2">
                        <div class="flex justify-center mb-6">
                            <div class="relative">
                                <div class="absolute inset-0 rounded-full animate-pulse" style="background: linear-gradient(135deg, #5ba3f5, #2e7bc4); opacity: 0.2;"></div>
                                <div class="w-20 h-20 rounded-full flex items-center justify-center shadow-lg relative z-10 transform transition-transform duration-500 hover:scale-110 hover:rotate-12" style="background: linear-gradient(135deg, #5ba3f5, #2e7bc4);">
                                    <i class="fas fa-th-large text-white text-2xl"></i>
                                </div>
                                <div class="absolute -top-2 -right-2 w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center text-white font-bold text-sm shadow-md z-20 animate-pulse" style="background: #4a90e2;">1</div>
                            </div>
                        </div>
                        <h3 class="text-xl font-bold text-slate-900 text-center mb-3">Select Portal</h3>
                        <p class="text-slate-600 text-center text-sm leading-relaxed">Choose the dedicated portal for your role: Staff, Operator, or Public Portal</p>
                        <div class="mt-4 flex justify-center">
                            <div class="w-12 h-1 rounded-full" style="background: linear-gradient(to right, #5ba3f5, #2e7bc4);"></div>
                        </div>
                    </div>
                </div>

                <!-- Step 2 -->
                <div class="relative animate-fade-in-up delay-300">
                    <div class="bg-white rounded-2xl shadow-lg p-8 hover:shadow-2xl transition-all duration-500 border-2 border-transparent hover:border-gray-200 transform hover:-translate-y-2">
                        <div class="flex justify-center mb-6">
                            <div class="relative">
                                <div class="absolute inset-0 rounded-full animate-pulse" style="background: linear-gradient(135deg, #9aa5b1, #78909c); opacity: 0.2; animation-delay: 0.5s;"></div>
                                <div class="w-20 h-20 rounded-full flex items-center justify-center shadow-lg relative z-10 transform transition-transform duration-500 hover:scale-110 hover:rotate-12" style="background: linear-gradient(135deg, #9aa5b1, #78909c);">
                                    <i class="fas fa-user-check text-white text-2xl"></i>
                                </div>
                                <div class="absolute -top-2 -right-2 w-8 h-8 bg-gray-500 rounded-full flex items-center justify-center text-white font-bold text-sm shadow-md z-20 animate-pulse" style="background: #9aa5b1; animation-delay: 0.5s;">2</div>
                            </div>
                        </div>
                        <h3 class="text-xl font-bold text-slate-900 text-center mb-3">Authenticate</h3>
                        <p class="text-slate-600 text-center text-sm leading-relaxed">Log in with your credentials or access public services as a guest</p>
                        <div class="mt-4 flex justify-center">
                            <div class="w-12 h-1 rounded-full" style="background: linear-gradient(to right, #9aa5b1, #78909c);"></div>
                        </div>
                    </div>
                </div>

                <!-- Step 3 -->
                <div class="relative animate-fade-in-up delay-500">
                    <div class="bg-white rounded-2xl shadow-lg p-8 hover:shadow-2xl transition-all duration-500 border-2 border-transparent hover:border-green-200 transform hover:-translate-y-2">
                        <div class="flex justify-center mb-6">
                            <div class="relative">
                                <div class="absolute inset-0 rounded-full animate-pulse" style="background: linear-gradient(135deg, #66bb6a, #43a047); opacity: 0.2; animation-delay: 1s;"></div>
                                <div class="w-20 h-20 rounded-full flex items-center justify-center shadow-lg relative z-10 transform transition-transform duration-500 hover:scale-110 hover:rotate-12" style="background: linear-gradient(135deg, #66bb6a, #43a047);">
                                    <i class="fas fa-rocket text-white text-2xl"></i>
                                </div>
                                <div class="absolute -top-2 -right-2 w-8 h-8 bg-green-500 rounded-full flex items-center justify-center text-white font-bold text-sm shadow-md z-20 animate-pulse" style="background: #4caf50; animation-delay: 1s;">3</div>
                            </div>
                        </div>
                        <h3 class="text-xl font-bold text-slate-900 text-center mb-3">Start Managing</h3>
                        <p class="text-slate-600 text-center text-sm leading-relaxed">Access your dashboard and manage applications or view real-time data</p>
                        <div class="mt-4 flex justify-center">
                            <div class="w-12 h-1 rounded-full" style="background: linear-gradient(to right, #66bb6a, #43a047);"></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

    </main>

    <footer class="bg-gradient-to-r from-blue-600 to-blue-700 text-white py-6 mt-8" style="background: linear-gradient(to right, #357abd, #4a90e2);">
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


    <div id="registerFormContainer" class="fixed inset-0 bg-black/40 flex items-start justify-center pt-20 px-4 hidden overflow-y-auto">
        <div class="bg-white rounded-2xl shadow-2xl p-6 max-w-2xl w-full glass-card form-compact max-h-[80vh] overflow-y-auto" style="background: #ffffff !important;">
            <div class="sticky top-0 bg-white/95 backdrop-blur border-b border-gray-200 z-10 -mx-6 px-6 py-3 text-center" style="background: #ffffff !important;">
                <h2 class="text-xl md:text-2xl font-semibold text-custom-secondary">Create your GoServePH account</h2>
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

                <div>
                    <div class="g-recaptcha" data-sitekey="<?php echo htmlspecialchars($recaptchaSiteKey); ?>"></div>
                </div>

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

    <div id="operatorRegisterModal" class="fixed inset-0 bg-black/40 flex items-start justify-center pt-20 px-4 hidden overflow-y-auto z-50">
        <div class="bg-white rounded-2xl shadow-2xl p-6 max-w-2xl w-full glass-card form-compact max-h-[80vh] overflow-y-auto" style="background: #ffffff !important;">
            <div class="sticky top-0 bg-white/95 backdrop-blur border-b border-gray-200 z-10 -mx-6 px-6 py-3 text-center" style="background: #ffffff !important;">
                <h2 class="text-xl md:text-2xl font-semibold text-custom-secondary">Operator Registration</h2>
                <button type="button" id="btnOperatorRegisterClose" class="text-gray-500 hover:text-gray-700 absolute right-6 top-6"><i class="fas fa-times"></i></button>
            </div>
            <form id="operatorRegisterForm" class="space-y-5 pt-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm mb-1">Plate Number<span class="required-asterisk">*</span></label>
                        <input type="text" name="plate_number" required minlength="7" maxlength="8" pattern="^[A-Za-z]{3}\\-[0-9]{3,4}$" autocapitalize="characters" data-tmm-mask="plate"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg uppercase" placeholder="ABC-1234">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Full Name<span class="required-asterisk">*</span></label>
                        <input type="text" name="full_name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="Juan Dela Cruz">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm mb-1">Email Address<span class="required-asterisk">*</span></label>
                        <input type="email" name="email" required class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="you@email.com">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Password<span class="required-asterisk">*</span></label>
                        <div class="relative">
                            <input type="password" id="opRegPassword" name="password" minlength="10" required class="w-full px-3 py-2 border border-gray-300 rounded-lg pr-10">
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
                            <button type="button" class="absolute inset-y-0 right-0 px-3 text-gray-500 hover:text-gray-700 toggle-password" aria-label="Toggle confirm password visibility" data-target="opRegConfirmPassword">
                                <i class="far fa-eye"></i>
                            </button>
                        </div>
                        <div id="op-confirm-error" class="text-red-500 text-sm mt-1 hidden">Passwords do not match.</div>
                    </div>
                </div>

                <div id="opRecaptcha" data-sitekey="<?php echo htmlspecialchars($recaptchaSiteKey); ?>"></div>

                <div class="flex justify-end space-x-3 pt-2">
                    <button type="button" id="btnOperatorRegisterCancel" class="bg-red-500 text-white px-4 py-2 rounded-lg">Cancel</button>
                    <button type="submit" id="btnOperatorRegisterSubmit" class="bg-custom-secondary text-white px-4 py-2 rounded-lg font-semibold">Register</button>
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
                <p>This TMM Services Agreement ("Agreement") is a binding legal contract for the use of our software systems—which handle data input, monitoring, processing, and analytics—("Services") between TMM ("us," "our," or "we") and you, the registered user ("you" or "user").</p>
                <p>This Agreement details the terms and conditions for using our Services. By accessing or using any TMM Services, you agree to these terms. If you don't understand any part of this Agreement, please contact us at helpdesk@tmm.gov.ph.</p>
                <h4 class="font-semibold">OVERVIEW OF THIS AGREEMENT</h4>
                <p>This document outlines the terms for your use of the TMM system:</p>
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
                <p>c. Eligibility: Only businesses, institutions, and other entities based in the Philippines are eligible to apply for a TMM Account.</p>
                <p>d. Representative Authority: You confirm that your Representative has the full authority to provide your information and legally bind your entity to this Agreement. We may ask for proof of this authority.</p>
                <p>e. Validation: We may require additional documentation at any time (e.g., business licenses, IDs) to verify your entity's ownership, control, and the information you provided.</p>
                <p><strong>2. Services and Support</strong></p>
                <p>We provide support for general account inquiries and issues that prevent the proper use of the system ("System Errors"). Support includes resources available through our in-app Ticketing System and website documentation ("Documentation"). For further questions, contact us at helpdesk@tmm.gov.ph.</p>
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
                <p>b. Termination by You: You can terminate by emailing a closure request to helpdesk@tmm.gov.ph. Your Account will be closed within 120 business days of receipt.</p>
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
                <p>b. TMM IP: We exclusively own all rights, titles, and interests in the patents, copyrights, trademarks, system designs, and documentation ("TMM IP"). All rights in TMM IP not expressly granted to you are reserved by us.</p>
                <p>c. Ideas: If you submit comments or ideas for system improvements ("Ideas"), you agree that we are free to use these Ideas without any attribution or compensation to you.</p>
                <p><strong>3. License Coverage</strong></p>
                <p>We grant you a non-exclusive and non-transferable license to electronically access and use the TMM IP only as described in this Agreement. We are not selling the IP to you, and you cannot sublicense it. We may revoke this license if you violate the Agreement.</p>
                <p><strong>4. References to Our Relationship</strong></p>
                <p>During the term of this Agreement, both you and we may publicly identify the other party as the service provider or client, respectively. If you object to us identifying you as a client, you must notify us at helpdesk@tmm.gov.ph. Upon termination, both parties must remove all public references to the relationship.</p>
                <h4 class="font-semibold">SECTION C: PAYMENT TERMS AND CONDITIONS</h4>
                <p><strong>1. Service Fees</strong></p>
                <p>We will charge the Fees for set-up, access, support, penalties, and other transactions as described on the TMM website. We may revise the Fees at any time, with at least 30 days' notice before the revisions apply to you.</p>
                <p><strong>2. Payment Terms and Schedule</strong></p>
                <p>a. Billing: Your monthly bill for the upcoming month is generated by the system on the 21st day of the current month and is due after 5 days. Billing is based on the number of registered users ("End-User") as of the 20th day.</p>
                <p>b. Payment Method: All payments must be settled via our third-party Payment System Provider, PayPal. You agree to abide by all of PayPal's terms, and we are not responsible for any issues with their service.</p>
                <p><strong>3. Taxes</strong></p>
                <p>Fees exclude applicable taxes. You are solely responsible for remitting all taxes for your business to the appropriate Philippine tax and revenue authorities.</p>
                <p><strong>4. Payment Processing</strong></p>
                <p>We are not a bank and do not offer services regulated by the Bangko Sentral ng Pilipinas. We reserve the right to reject your application or terminate your Account if you are ineligible to use PayPal services.</p>
                <p><strong>5. Processing Disputes and Refunds</strong></p>
                <p>You must report disputes and refund requests by emailing us at helpdesk@tmm.gov.ph. Disputes will only be investigated if reported within 60 days from the billing date. If a refund is warranted, it will be issued as a credit memo for use on future bills.</p>
                <h4 class="font-semibold">SECTION D: DATA USAGE, PRIVACY AND SECURITY</h4>
                <p><strong>1. Data Usage Overview</strong></p>
                <p>Data security is a top priority. This section outlines our obligations when handling information.</p>
                <p>'PERSONAL DATA' is information that relates to and can identify a person.</p>
                <p>'USER DATA' is information that describes your business, operations, products, or services.</p>
                <p>'TMM DATA' is transactional data over our infrastructure, fraud analysis info, aggregated data, and other information originating from the Services.</p>
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
                <p>We provide the Services and TMM IP “AS IS” and “AS AVAILABLE,” without any express, implied, or statutory warranties of title, merchantability, fitness for a particular purpose, or non-infringement.</p>
                <p><strong>6. Limitation of Liability</strong></p>
                <p>We shall not be responsible or liable to you for any indirect, punitive, incidental, special, consequential, or exemplary damages resulting from your use or inability to use the Services, lost profits, personal injury, or property damage. We are not liable for damages arising from:</p>
                <ul class="list-disc pl-5">
                    <li>Hacking, tampering, or unauthorized access to your Account.</li>
                    <li>Your failure to implement Security Controls.</li>
                    <li>Use of the Services inconsistent with the Documentation.</li>
                    <li>Bugs, viruses, or interruptions to the Services.</li>
                </ul>
                <p>This Agreement and all incorporated policies constitute the entire agreement between you and TMM.</p>
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
                <p><strong>Protecting the information you and your users handle through our system is our highest priority.</strong> This policy outlines how TMM manages, secures, and uses your data.</p>
                <h4 class="font-semibold">1. How We Define and Use Data</h4>
                <p>In this policy, we define the types of data that flow through the TMM system:</p>
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr><th class="py-1 pr-4">Term</th><th class="py-1">Definition</th></tr>
                    </thead>
                    <tbody>
                        <tr><td class="py-1 pr-4">Personal Data</td><td class="py-1">Any information that can identify a specific person, whether directly or indirectly, shared or accessible through the Services.</td></tr>
                        <tr><td class="py-1 pr-4">User Data</td><td class="py-1">Information that describes your business operations, services, or internal activities.</td></tr>
                        <tr><td class="py-1 pr-4">TMM Data</td><td class="py-1">Details about transactions and activity on our platform, information used for fraud detection, aggregated data, and any non-personal information generated by our system.</td></tr>
                        <tr><td class="py-1 pr-4">DATA</td><td class="py-1">Used broadly to refer to all the above: Personal Data, User Data, and TMM Data.</td></tr>
                    </tbody>
                </table>
                <h4 class="font-semibold">Our Commitment to Data Use</h4>
                <p>We analyze and manage data only for the following critical purposes:</p>
                <ul class="list-disc pl-5">
                    <li>To provide, maintain, and improve the TMM Services for you and all other users.</li>
                    <li>To detect and mitigate fraud, financial loss, or other harm to you or other users.</li>
                    <li>To develop and enhance our products, systems, and tools.</li>
                </ul>
                <p>We will not sell or share Personal Data with unaffiliated parties for their marketing purposes. By using our system, you consent to our use of your Data in this manner.</p>
                <h4 class="font-semibold">2. Data Protection and Compliance</h4>
                <p><strong>Confidentiality</strong></p>
                <p>We commit to using Data only as permitted by our agreement or as specifically directed by you. You, in turn, must protect all Data you access through TMM and use it only in connection with our Services. Neither party may use Personal Data to market to third parties without explicit consent.</p>
                <p>We will only disclose Data when legally required to do so, such as through a subpoena, court order, or search warrant.</p>
                <p><strong>Privacy Compliance and Responsibilities</strong></p>
                <p><em>Your Legal Duty:</em> You affirm that you are, and will remain, compliant with all applicable Philippine laws (including the Data Privacy Act of 2012) governing the collection, protection, and use of the Data you provide to us.</p>
                <p><em>Consent:</em> You are responsible for obtaining all necessary rights and consents from your End-Users to allow us to collect, use, and store their Personal Data.</p>
                <p><em>End-User Disclosure:</em> You must clearly inform your End-Users that TMM processes transactions for you and may receive their Personal Data as part of that process.</p>
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
                <p>You are strictly prohibited from data mining the TMM database or any portion of it without our express written permission.</p>
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
                <h4 class="font-semibold">5. Contact Us / Data Protection Officer</h4>
                <p>If you have any questions, concerns, or wish to exercise your rights regarding your personal data, please contact our Data Protection Officer (DPO):</p>
                <ul class="list-none pl-5">
                    <li><strong>Email:</strong> govserve.tmm@gmail.com</li>
                    <li><strong>Phone:</strong> (02) 8123-4567</li>
                </ul>
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
            const closeRegBtn = document.getElementById('cancelRegister');
            
            if (showRegBtn && regModal) {
                showRegBtn.addEventListener('click', () => {
                    regModal.classList.remove('hidden');
                    document.body.style.overflow = 'hidden';
                });
            }
            if (closeRegBtn && regModal) {
                closeRegBtn.addEventListener('click', () => {
                    regModal.classList.add('hidden');
                    document.body.style.overflow = '';
                    try { if (window.grecaptcha && typeof window.grecaptcha.reset === 'function') window.grecaptcha.reset(); } catch (e) {}
                });
            }
            if (regModal) {
                regModal.addEventListener('click', (e) => {
                    if (e.target === regModal) {
                        regModal.classList.add('hidden');
                        document.body.style.overflow = '';
                        try { if (window.grecaptcha && typeof window.grecaptcha.reset === 'function') window.grecaptcha.reset(); } catch (e) {}
                    }
                });
            }

            const opRegModal = document.getElementById('operatorRegisterModal');
            const opRegBtn = document.getElementById('btnOperatorRegisterOpen');
            const opRegCancel = document.getElementById('btnOperatorRegisterCancel');
            const opRegClose = document.getElementById('btnOperatorRegisterClose');
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
                    try { if (window.grecaptcha && operatorRecaptchaWidgetId !== null) window.grecaptcha.reset(operatorRecaptchaWidgetId); } catch (e) {}
                });
            }
            if (opRegClose && opRegModal) {
                opRegClose.addEventListener('click', () => {
                    opRegModal.classList.add('hidden');
                    opRegModal.classList.remove('flex');
                    document.body.style.overflow = '';
                    try { if (window.grecaptcha && operatorRecaptchaWidgetId !== null) window.grecaptcha.reset(operatorRecaptchaWidgetId); } catch (e) {}
                });
            }
            if (opRegModal) {
                opRegModal.addEventListener('click', (e) => {
                    if (e.target === opRegModal) {
                        opRegModal.classList.add('hidden');
                        opRegModal.classList.remove('flex');
                        document.body.style.overflow = '';
                        try { if (window.grecaptcha && operatorRecaptchaWidgetId !== null) window.grecaptcha.reset(operatorRecaptchaWidgetId); } catch (e) {}
                    }
                });
            }

            const registerForm = document.getElementById('registerForm');
            if (registerForm) {
                registerForm.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const f = e.target;
                    const pwd = (f.regPassword ? String(f.regPassword.value || '') : '').trim();
                    const confirmPwd = (f.confirmPassword ? String(f.confirmPassword.value || '') : '').trim();
                    if (pwd === '' || confirmPwd === '' || pwd !== confirmPwd) {
                        alert('Passwords do not match.');
                        return;
                    }
                    const agreeTerms = document.getElementById('agreeTerms');
                    const agreePrivacy = document.getElementById('agreePrivacy');
                    if ((agreeTerms && !agreeTerms.checked) || (agreePrivacy && !agreePrivacy.checked)) {
                        alert('You must agree to the Terms and Data Privacy Policy.');
                        return;
                    }
                    let captchaToken = '';
                    const captchaEl = f.querySelector('.g-recaptcha');
                    const captchaSiteKey = captchaEl ? String(captchaEl.getAttribute('data-sitekey') || '').trim() : '';
                    if (captchaEl && captchaSiteKey) {
                        const ta = captchaEl.querySelector('textarea[name="g-recaptcha-response"], textarea.g-recaptcha-response');
                        captchaToken = ta ? String(ta.value || '') : '';
                        if (!captchaToken && window.grecaptcha && typeof window.grecaptcha.getResponse === 'function') {
                            captchaToken = String(window.grecaptcha.getResponse() || '');
                        }
                        if (!captchaToken) {
                            alert('Please complete the reCAPTCHA.');
                            return;
                        }
                    }
                    const payload = {
                        firstName: (f.firstName ? String(f.firstName.value || '') : '').trim(),
                        lastName: (f.lastName ? String(f.lastName.value || '') : '').trim(),
                        middleName: (f.middleName ? String(f.middleName.value || '') : '').trim(),
                        suffix: (f.suffix ? String(f.suffix.value || '') : '').trim(),
                        birthdate: (f.birthdate ? String(f.birthdate.value || '') : '').trim(),
                        regEmail: (f.regEmail ? String(f.regEmail.value || '') : '').trim(),
                        email: (f.regEmail ? String(f.regEmail.value || '') : '').trim(),
                        mobile: (f.mobile ? String(f.mobile.value || '') : '').trim(),
                        address: (f.address ? String(f.address.value || '') : '').trim(),
                        houseNumber: (f.houseNumber ? String(f.houseNumber.value || '') : '').trim(),
                        street: (f.street ? String(f.street.value || '') : '').trim(),
                        barangay: (f.barangay ? String(f.barangay.value || '') : '').trim(),
                        regPassword: pwd,
                        password: pwd,
                        confirmPassword: confirmPwd,
                        recaptcha_token: captchaToken
                    };
                    const url = (BASE_URL || '') + '/gsm_login/Login/register.php';
                    try {
                        const res = await fetch(url, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                            body: JSON.stringify(payload)
                        });
                        const data = await res.json().catch(() => null);
                        if (!data || !data.ok) {
                            alert((data && data.message) ? data.message : 'Registration failed.');
                            return;
                        }
                        alert(data.message || 'Registration submitted.');
                        regModal && regModal.classList.add('hidden');
                        document.body.style.overflow = '';
                        try { if (window.grecaptcha && typeof window.grecaptcha.reset === 'function') window.grecaptcha.reset(); } catch (e) {}
                    } catch (err) {
                        alert('Network error. Please try again.');
                    }
                });
            }

            const operatorRegisterForm = document.getElementById('operatorRegisterForm');
            if (operatorRegisterForm) {
                operatorRegisterForm.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const f = e.target;
                    const pwd = (f.password ? String(f.password.value || '') : '').trim();
                    const confirmPwd = (f.confirm_password ? String(f.confirm_password.value || '') : '').trim();
                    const err = document.getElementById('op-confirm-error');
                    if (err) err.classList.add('hidden');
                    if (pwd === '' || confirmPwd === '' || pwd !== confirmPwd) {
                        if (err) err.classList.remove('hidden');
                        alert('Passwords do not match.');
                        return;
                    }
                    let captchaToken = '';
                    const opCaptchaEl = document.getElementById('opRecaptcha');
                    const opCaptchaSiteKey = opCaptchaEl ? String(opCaptchaEl.getAttribute('data-sitekey') || '').trim() : '';
                    if (opCaptchaEl && opCaptchaSiteKey) {
                        tryRenderOpRecaptcha();
                        captchaToken = (window.grecaptcha && operatorRecaptchaWidgetId !== null) ? String(window.grecaptcha.getResponse(operatorRecaptchaWidgetId) || '') : '';
                        if (!captchaToken) {
                            alert('Please complete the reCAPTCHA.');
                            return;
                        }
                    }
                    const payload = {
                        plate_number: (f.plate_number ? String(f.plate_number.value || '') : '').trim(),
                        full_name: (f.full_name ? String(f.full_name.value || '') : '').trim(),
                        email: (f.email ? String(f.email.value || '') : '').trim(),
                        password: pwd,
                        confirm_password: confirmPwd,
                        recaptcha_token: captchaToken
                    };
                    const url = (BASE_URL || '') + '/gsm_login/Login/operator_register.php';
                    try {
                        const res = await fetch(url, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                            body: JSON.stringify(payload)
                        });
                        const data = await res.json().catch(() => null);
                        if (!data || !data.ok) {
                            alert((data && data.message) ? data.message : 'Registration failed.');
                            return;
                        }
                        const redirect = data && data.data && data.data.redirect ? String(data.data.redirect) : '';
                        if (redirect) {
                            if (redirect.indexOf('citizen/operator') !== -1) {
                                window.location.href = (BASE_URL || '') + '/citizen/operator/index.php';
                            } else {
                                window.location.href = redirect;
                            }
                            return;
                        }
                        alert(data.message || 'Registration successful. Please login as operator.');
                        opRegModal && opRegModal.classList.add('hidden');
                        opRegModal && opRegModal.classList.remove('flex');
                        document.body.style.overflow = '';
                        try { if (window.grecaptcha && operatorRecaptchaWidgetId !== null) window.grecaptcha.reset(operatorRecaptchaWidgetId); } catch (e) {}
                    } catch (err) {
                        alert('Network error. Please try again.');
                    }
                });
            }

            // Terms & Privacy
            const termsModal = document.getElementById('termsModal');
            const privacyModal = document.getElementById('privacyModal');
            const openTerms = document.getElementById('openTerms');
            const openPrivacy = document.getElementById('openPrivacy');
            const footerTerms = document.getElementById('footerTerms');
            const footerPrivacy = document.getElementById('footerPrivacy');
            const closeTerms = document.getElementById('closeTerms');
            const closeTermsBottom = document.getElementById('closeTermsBottom');
            const closePrivacy = document.getElementById('closePrivacy');
            const closePrivacyBottom = document.getElementById('closePrivacyBottom');

            if(footerTerms && termsModal) footerTerms.addEventListener('click', () => termsModal.classList.remove('hidden', 'flex'));
            if(footerTerms && termsModal) footerTerms.addEventListener('click', () => { termsModal.classList.remove('hidden'); termsModal.classList.add('flex'); });
            if(openTerms && termsModal) openTerms.addEventListener('click', () => { termsModal.classList.remove('hidden'); termsModal.classList.add('flex'); });
            if(closeTerms) closeTerms.addEventListener('click', () => termsModal.classList.add('hidden'));
            if(closeTermsBottom) closeTermsBottom.addEventListener('click', () => termsModal.classList.add('hidden'));

            if(footerPrivacy && privacyModal) footerPrivacy.addEventListener('click', () => { privacyModal.classList.remove('hidden'); privacyModal.classList.add('flex'); });
            if(openPrivacy && privacyModal) openPrivacy.addEventListener('click', () => { privacyModal.classList.remove('hidden'); privacyModal.classList.add('flex'); });
            if(closePrivacy) closePrivacy.addEventListener('click', () => privacyModal.classList.add('hidden'));
            if(closePrivacyBottom) closePrivacyBottom.addEventListener('click', () => privacyModal.classList.add('hidden'));

        });

        function updateDateTime() {
            const now = new Date();
            const el = document.getElementById('currentDateTime');
            if (el) el.textContent = now.toLocaleString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' });
        }

        let operatorRecaptchaWidgetId = null;
        function tryRenderOpRecaptcha() {
            const el = document.getElementById('opRecaptcha');
            if (!el || !window.grecaptcha || operatorRecaptchaWidgetId !== null) return;
            const siteKey = el.getAttribute('data-sitekey');
            if (siteKey) operatorRecaptchaWidgetId = window.grecaptcha.render(el, { sitekey: siteKey });
        }
    </script>
</body>
</html>
