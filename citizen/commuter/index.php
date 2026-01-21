<?php
if (function_exists('session_status') && session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// No login required for public portal
$baseUrl = str_replace('\\', '/', (string) dirname(dirname(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/citizen/commuter/index.php')))));
$baseUrl = $baseUrl === '/' ? '' : rtrim($baseUrl, '/');

$isLoggedIn = !empty($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'Commuter';
$userName = $_SESSION['name'] ?? 'Commuter';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>City Transport Portal - Public Information</title>
    <link rel="icon" type="image/jpeg" href="images/logo.jpg">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/lucide@latest/dist/umd/lucide.min.js"></script>
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6', // Primary Blue
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                            950: '#172554',
                        },
                        accent: {
                            500: '#0ea5e9', // Sky Blue
                            600: '#0284c7',
                        }
                    },
                    backgroundImage: {
                        'hero-pattern': "url(\"data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E\")",
                    }
                }
            }
        }
    </script>
    <style>
        .fade-in {
            animation: fadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f5f9; 
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1; 
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8; 
        }

        .nav-item {
            position: relative;
        }
        .nav-item::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background-color: #3b82f6;
            transition: width 0.3s ease;
        }
        .nav-item.active::after {
            width: 100%;
        }
        .nav-item.active {
            color: #1e3a8a;
            font-weight: 600;
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
    </style>
</head>

<body class="min-h-screen font-sans text-slate-600 bg-slate-50 selection:bg-brand-100 selection:text-brand-900">

    <!-- Top Navigation Bar -->
    <header class="sticky top-0 z-50 bg-white/90 backdrop-blur-md border-b border-slate-200 shadow-sm transition-all duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <!-- Logo -->
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-brand-600 to-brand-800 rounded-xl flex items-center justify-center text-white shadow-lg shadow-brand-500/30 transform transition hover:scale-105 duration-300">
                        <i data-lucide="bus" class="w-6 h-6"></i>
                    </div>
                    <div class="flex flex-col">
                        <span class="font-bold text-lg text-slate-900 leading-tight tracking-tight">City Transport</span>
                        <span class="text-[10px] uppercase tracking-wider font-bold text-brand-600">Commuter Portal</span>
                    </div>
                </div>

                <!-- Desktop Menu -->
                <nav class="hidden md:flex space-x-8">
                    <button onclick="switchTab('home')" id="nav-home" class="nav-item active py-2 text-sm font-medium text-slate-500 hover:text-slate-900 transition-colors">Advisories</button>
                    <button onclick="switchTab('routes')" id="nav-routes" class="nav-item py-2 text-sm font-medium text-slate-500 hover:text-slate-900 transition-colors">Routes</button>
                    <button onclick="switchTab('terminals')" id="nav-terminals" class="nav-item py-2 text-sm font-medium text-slate-500 hover:text-slate-900 transition-colors">Terminals</button>
                    <button onclick="switchTab('complaints')" id="nav-complaints" class="nav-item py-2 text-sm font-medium text-slate-500 hover:text-slate-900 transition-colors">Help & Report</button>
                </nav>

                <!-- Auth/Profile -->
                <div class="hidden md:flex items-center gap-4">
                    <?php if ($isLoggedIn): ?>
                        <div class="flex items-center gap-3 pl-4 border-l border-slate-200">
                            <div class="text-right hidden lg:block">
                                <div class="text-xs text-slate-400 font-medium">Signed in as</div>
                                <div class="text-sm font-bold text-slate-800"><?= htmlspecialchars($userName) ?></div>
                            </div>
                            <div class="w-8 h-8 bg-brand-100 rounded-full flex items-center justify-center text-brand-700 font-bold border-2 border-white shadow-sm">
                                <?= strtoupper(substr($userName, 0, 1)) ?>
                            </div>
                            <a href="logout.php" title="Logout" class="text-slate-400 hover:text-red-500 transition-colors">
                                <i data-lucide="log-out" class="w-5 h-5"></i>
                            </a>
                        </div>
                    <?php else: ?>
                        <a href="../../gsm_login/index.php" class="group relative px-5 py-2.5 bg-slate-900 text-white text-sm font-bold rounded-full hover:bg-slate-800 transition-all shadow-md hover:shadow-lg flex items-center gap-2 overflow-hidden">
                            <span class="relative z-10">Login</span>
                            <i data-lucide="arrow-right" class="w-4 h-4 relative z-10 group-hover:translate-x-1 transition-transform"></i>
                            <div class="absolute inset-0 bg-gradient-to-r from-brand-600 to-accent-600 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Mobile Menu Toggle -->
                <button class="md:hidden p-2 text-slate-500 hover:bg-slate-100 rounded-lg transition-colors" onclick="document.getElementById('mobile-menu').classList.toggle('hidden')">
                    <i data-lucide="menu" class="w-6 h-6"></i>
                </button>
            </div>
        </div>

        <!-- Mobile Menu Dropdown -->
        <div id="mobile-menu" class="hidden md:hidden bg-white border-b border-slate-100 shadow-lg">
            <div class="px-4 pt-2 pb-4 space-y-1">
                <button onclick="switchTab('home')" class="block w-full text-left px-3 py-3 rounded-lg text-base font-medium text-slate-700 hover:bg-slate-50 hover:text-brand-600">Advisories</button>
                <button onclick="switchTab('routes')" class="block w-full text-left px-3 py-3 rounded-lg text-base font-medium text-slate-700 hover:bg-slate-50 hover:text-brand-600">Routes & Fares</button>
                <button onclick="switchTab('terminals')" class="block w-full text-left px-3 py-3 rounded-lg text-base font-medium text-slate-700 hover:bg-slate-50 hover:text-brand-600">Terminals</button>
                <button onclick="switchTab('complaints')" class="block w-full text-left px-3 py-3 rounded-lg text-base font-medium text-slate-700 hover:bg-slate-50 hover:text-brand-600">File Complaint</button>
                <div class="border-t border-slate-100 my-2 pt-2">
                    <?php if ($isLoggedIn): ?>
                        <div class="px-3 py-2 flex items-center justify-between">
                            <span class="font-bold text-slate-700"><?= htmlspecialchars($userName) ?></span>
                            <a href="logout.php" class="text-sm text-red-500 font-medium">Logout</a>
                        </div>
                    <?php else: ?>
                        <a href="../../gsm_login/index.php" class="block w-full text-center px-3 py-3 mt-2 bg-brand-600 text-white rounded-lg font-bold shadow-sm">Login / Sign Up</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 pb-24">

        <!-- SECTION: ADVISORIES (HOME) -->
        <section id="tab-home" class="fade-in">
            <!-- Hero Banner -->
            <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-brand-900 via-brand-800 to-brand-900 text-white shadow-2xl mb-10">
                <div class="absolute inset-0 bg-hero-pattern opacity-10"></div>
                <div class="absolute right-0 bottom-0 opacity-20 transform translate-x-10 translate-y-10">
                    <i data-lucide="navigation" class="w-80 h-80 text-white"></i>
                </div>
                
                <div class="relative z-10 px-8 py-12 md:px-12 md:py-16">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 backdrop-blur-sm border border-white/20 text-xs font-bold uppercase tracking-wide mb-4 text-brand-100">
                        <span class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></span>
                        System Online
                    </div>
                    <h1 class="text-3xl md:text-5xl font-black mb-4 tracking-tight leading-tight">
                        <span id="greeting-text">Welcome,</span> <br/>
                        <span class="text-brand-300">Commuter.</span>
                    </h1>
                    <p class="text-brand-100 text-lg max-w-xl leading-relaxed mb-8">
                        Get real-time updates on traffic, routes, and terminal status. Your official source for city transport information.
                    </p>
                    
                    <div class="flex flex-wrap gap-4">
                        <button onclick="switchTab('routes')" class="px-6 py-3 bg-white text-brand-900 font-bold rounded-xl shadow-lg hover:bg-brand-50 transition-colors flex items-center gap-2">
                            <i data-lucide="map" class="w-5 h-5"></i> View Routes
                        </button>
                        <button onclick="switchTab('terminals')" class="px-6 py-3 bg-brand-700/50 backdrop-blur border border-brand-500 text-white font-bold rounded-xl hover:bg-brand-700/70 transition-colors flex items-center gap-2">
                            <i data-lucide="warehouse" class="w-5 h-5"></i> Find Terminals
                        </button>
                    </div>
                </div>
            </div>

            <!-- Advisories Feed -->
            <div class="flex items-end justify-between mb-6">
                <div>
                    <h2 class="text-2xl font-bold text-slate-900">Live Updates</h2>
                    <p class="text-slate-500 text-sm mt-1">Real-time alerts and demand forecasts</p>
                </div>
                <div class="flex items-center gap-2 text-xs font-medium text-slate-400 bg-white px-3 py-1.5 rounded-full shadow-sm border border-slate-100">
                    <i data-lucide="clock" class="w-3 h-3"></i>
                    <span id="last-updated">Updating...</span>
                </div>
            </div>

            <div id="advisories-container" class="grid grid-cols-1 gap-4">
                <!-- Skeleton Loader -->
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100 animate-pulse">
                    <div class="flex gap-4">
                        <div class="w-12 h-12 bg-slate-200 rounded-xl"></div>
                        <div class="flex-1 space-y-3">
                            <div class="h-4 bg-slate-200 rounded w-1/3"></div>
                            <div class="h-16 bg-slate-200 rounded w-full"></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- SECTION: ROUTES -->
        <section id="tab-routes" class="hidden fade-in">
            <div class="text-center max-w-2xl mx-auto mb-10">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-brand-100 text-brand-600 mb-4">
                    <i data-lucide="map" class="w-6 h-6"></i>
                </div>
                <h2 class="text-3xl font-bold text-slate-900 mb-2">Authorized Routes</h2>
                <p class="text-slate-500">Official fare matrix and route directions. Please pay only the exact amount.</p>
            </div>

            <div class="bg-white rounded-2xl shadow-xl shadow-slate-200/50 border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-xs uppercase text-slate-500 font-bold tracking-wider">
                                <th class="px-6 py-5">Route Information</th>
                                <th class="px-6 py-5 text-center">Fare</th>
                                <th class="px-6 py-5 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody id="routes-table-body" class="divide-y divide-slate-100 text-sm">
                            <tr><td colspan="3" class="px-6 py-12 text-center text-slate-400">Loading routes...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- SECTION: TERMINALS -->
        <section id="tab-terminals" class="hidden fade-in">
            <div class="text-center max-w-2xl mx-auto mb-10">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-emerald-100 text-emerald-600 mb-4">
                    <i data-lucide="warehouse" class="w-6 h-6"></i>
                </div>
                <h2 class="text-3xl font-bold text-slate-900 mb-2">Transport Hubs</h2>
                <p class="text-slate-500">Locate authorized terminals for loading and unloading.</p>
            </div>

            <div id="terminals-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Injected via JS -->
                <div class="col-span-full py-20 flex flex-col items-center justify-center text-slate-400">
                    <i data-lucide="loader-2" class="w-8 h-8 animate-spin mb-3 text-brand-500"></i>
                    <span>Loading terminals...</span>
                </div>
            </div>
        </section>

        <!-- SECTION: COMPLAINTS -->
        <section id="tab-complaints" class="hidden fade-in">
            <div class="max-w-3xl mx-auto">
                <div class="text-center mb-10">
                    <h2 class="text-3xl font-bold text-slate-900 mb-3">Customer Support</h2>
                    <p class="text-slate-500">We take your feedback seriously. Report issues or track your existing complaints.</p>
                </div>

                <div class="bg-white rounded-2xl shadow-xl shadow-slate-200/50 border border-slate-200 overflow-hidden">
                    <!-- Tabs -->
                    <div class="flex border-b border-slate-100 bg-slate-50/50">
                        <button onclick="toggleComplaintMode('new')" id="btn-mode-new" class="flex-1 py-4 text-sm font-bold text-brand-600 border-b-2 border-brand-600 bg-white transition-colors">
                            New Report
                        </button>
                        <button onclick="toggleComplaintMode('track')" id="btn-mode-track" class="flex-1 py-4 text-sm font-bold text-slate-500 hover:text-slate-700 hover:bg-slate-50 transition-colors">
                            Track Status
                        </button>
                        <?php if ($isLoggedIn): ?>
                            <button onclick="toggleComplaintMode('my')" id="btn-mode-my" class="flex-1 py-4 text-sm font-bold text-slate-500 hover:text-slate-700 hover:bg-slate-50 transition-colors">
                                History
                            </button>
                        <?php endif; ?>
                    </div>

                    <!-- Form: New Complaint -->
                    <div id="mode-new" class="p-6 md:p-8">
                        <form id="complaintForm" onsubmit="submitComplaint(event)" class="space-y-6">
                            
                            <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 flex items-start gap-3">
                                <i data-lucide="info" class="w-5 h-5 text-blue-600 shrink-0 mt-0.5"></i>
                                <div class="text-sm text-blue-800">
                                    <p class="font-bold mb-1">Privacy Notice</p>
                                    <p>Your report is confidential. Personal details are optional but help us contact you for resolution.</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <label class="text-xs font-bold text-slate-700 uppercase tracking-wide">Incident Type <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <select name="type" required class="w-full pl-4 pr-10 py-3 rounded-xl border border-slate-200 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 outline-none appearance-none bg-white transition-all">
                                            <option value="">Select Category...</option>
                                            <option value="Overcharging">Overcharging / Overpricing</option>
                                            <option value="Reckless Driving">Reckless Driving</option>
                                            <option value="Refusal to Load">Refusal to Convey Passengers</option>
                                            <option value="Discourteous Driver">Discourteous / Rude Behavior</option>
                                            <option value="Unauthorized Trip">Unauthorized / Cutting Trip</option>
                                            <option value="Other">Other Concern</option>
                                        </select>
                                        <i data-lucide="chevron-down" class="absolute right-3 top-3.5 w-4 h-4 text-slate-400 pointer-events-none"></i>
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <label class="text-xs font-bold text-slate-700 uppercase tracking-wide">Date & Time</label>
                                    <input type="datetime-local" name="datetime" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 outline-none transition-all">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <label class="text-xs font-bold text-slate-700 uppercase tracking-wide">Route</label>
                                    <div class="relative">
                                        <select name="route_id" id="complaint-route-select" class="w-full pl-4 pr-10 py-3 rounded-xl border border-slate-200 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 outline-none appearance-none bg-white transition-all">
                                            <option value="">Select Route...</option>
                                            <option value="Other">Other / Not Listed</option>
                                        </select>
                                        <i data-lucide="chevron-down" class="absolute right-3 top-3.5 w-4 h-4 text-slate-400 pointer-events-none"></i>
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <label class="text-xs font-bold text-slate-700 uppercase tracking-wide">Vehicle Plate No.</label>
                                    <input type="text" name="plate_number" placeholder="ABC 1234" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 outline-none uppercase font-mono placeholder:font-sans transition-all">
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label class="text-xs font-bold text-slate-700 uppercase tracking-wide">Location</label>
                                <div class="relative">
                                    <input type="text" name="location" placeholder="e.g. Near Public Market Main Entrance" class="w-full pl-10 pr-4 py-3 rounded-xl border border-slate-200 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 outline-none transition-all">
                                    <i data-lucide="map-pin" class="absolute left-3 top-3.5 w-4 h-4 text-slate-400"></i>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label class="text-xs font-bold text-slate-700 uppercase tracking-wide">Description <span class="text-red-500">*</span></label>
                                <textarea name="description" rows="4" required placeholder="Please describe the incident in detail..." class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 outline-none resize-none transition-all"></textarea>
                            </div>

                            <div class="space-y-2">
                                <label class="text-xs font-bold text-slate-700 uppercase tracking-wide">Photo Evidence (Optional)</label>
                                <div class="flex items-center justify-center w-full">
                                    <label class="flex flex-col items-center justify-center w-full h-24 border-2 border-slate-200 border-dashed rounded-xl cursor-pointer bg-slate-50 hover:bg-white hover:border-brand-300 transition-all">
                                        <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                            <i data-lucide="camera" class="w-6 h-6 text-slate-400 mb-1"></i>
                                            <p class="text-xs text-slate-500">Click to upload image</p>
                                        </div>
                                        <input type="file" name="media" accept="image/*" class="hidden" />
                                    </label>
                                </div>
                            </div>

                            <button type="submit" id="btn-submit" class="w-full bg-brand-600 hover:bg-brand-700 text-white font-bold py-4 rounded-xl shadow-lg shadow-brand-600/20 transition-all transform active:scale-[0.99] flex items-center justify-center gap-2">
                                <i data-lucide="send" class="w-5 h-5"></i>
                                Submit Report
                            </button>
                        </form>
                    </div>

                    <!-- Form: Track -->
                    <div id="mode-track" class="hidden p-8 md:p-12 text-center">
                        <div class="max-w-md mx-auto">
                            <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-6">
                                <i data-lucide="search" class="w-8 h-8 text-slate-400"></i>
                            </div>
                            <h3 class="text-xl font-bold text-slate-900 mb-2">Track Report Status</h3>
                            <p class="text-slate-500 mb-8">Enter the Reference Number provided to you.</p>

                            <div class="relative mb-4">
                                <input type="text" id="trackRef" placeholder="COM-XXXXXX" class="w-full pl-12 pr-4 py-4 rounded-xl border border-slate-200 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 outline-none text-center font-mono text-lg uppercase tracking-widest transition-all">
                                <i data-lucide="hash" class="absolute left-4 top-4.5 w-5 h-5 text-slate-400"></i>
                            </div>
                            
                            <button onclick="trackComplaint()" class="w-full bg-slate-900 hover:bg-slate-800 text-white font-bold py-4 rounded-xl shadow-lg transition-colors mb-8">
                                Check Status
                            </button>

                            <!-- Result Area -->
                            <div id="trackResult" class="hidden text-left bg-slate-50 rounded-2xl p-6 border border-slate-200">
                                <div class="flex items-center gap-4 mb-4">
                                    <div id="statusIcon" class="w-12 h-12 rounded-xl bg-white border border-slate-100 flex items-center justify-center shadow-sm"></div>
                                    <div>
                                        <div class="text-xs font-bold text-slate-400 uppercase">Current Status</div>
                                        <div id="trackStatus" class="text-xl font-black text-slate-900"></div>
                                    </div>
                                </div>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between py-2 border-b border-slate-200 border-dashed">
                                        <span class="text-slate-500">Date Reported</span>
                                        <span id="trackDate" class="font-medium text-slate-900"></span>
                                    </div>
                                    <div class="pt-2">
                                        <span class="text-slate-500 block mb-1">Details</span>
                                        <p id="trackDesc" class="text-slate-700 italic bg-white p-3 rounded-lg border border-slate-100"></p>
                                    </div>
                                </div>
                            </div>
                            
                            <div id="trackError" class="hidden bg-red-50 text-red-600 p-4 rounded-xl border border-red-100 font-bold text-sm"></div>
                        </div>
                    </div>

                    <!-- List: My History -->
                    <?php if ($isLoggedIn): ?>
                        <div id="mode-my" class="hidden p-6 md:p-8">
                            <div class="flex justify-between items-center mb-6">
                                <h3 class="font-bold text-lg text-slate-800">My Reports</h3>
                                <button onclick="loadMyComplaints()" class="p-2 text-slate-400 hover:text-brand-600 hover:bg-brand-50 rounded-lg transition-colors">
                                    <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                                </button>
                            </div>
                            <div id="my-complaints-list" class="space-y-3">
                                <!-- Loaded via JS -->
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

    </main>

    <!-- Bottom Navigation (Mobile) -->
    <nav class="md:hidden fixed bottom-0 inset-x-0 bg-white border-t border-slate-200 pb-safe z-50">
        <div class="grid grid-cols-4 h-16">
            <button onclick="switchTab('home')" class="nav-btn-mobile flex flex-col items-center justify-center space-y-1 text-brand-600" data-target="home">
                <i data-lucide="home" class="w-5 h-5"></i>
                <span class="text-[10px] font-bold">Home</span>
            </button>
            <button onclick="switchTab('routes')" class="nav-btn-mobile flex flex-col items-center justify-center space-y-1 text-slate-400" data-target="routes">
                <i data-lucide="map" class="w-5 h-5"></i>
                <span class="text-[10px] font-bold">Routes</span>
            </button>
            <button onclick="switchTab('terminals')" class="nav-btn-mobile flex flex-col items-center justify-center space-y-1 text-slate-400" data-target="terminals">
                <i data-lucide="warehouse" class="w-5 h-5"></i>
                <span class="text-[10px] font-bold">Terminals</span>
            </button>
            <button onclick="switchTab('complaints')" class="nav-btn-mobile flex flex-col items-center justify-center space-y-1 text-slate-400" data-target="complaints">
                <i data-lucide="message-square" class="w-5 h-5"></i>
                <span class="text-[10px] font-bold">Help</span>
            </button>
        </div>
    </nav>

    <script>
        const API_URL = 'api.php';

        // --- UTILS ---
        function safeCreateIcons() {
            try {
                if (window.lucide && typeof window.lucide.createIcons === 'function') {
                    window.lucide.createIcons();
                }
            } catch (e) { }
        }

        async function fetchJsonSafe(url) {
            const res = await fetch(url, { cache: 'no-store' });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            try { return await res.json(); } catch(e) { throw new Error('Invalid JSON'); }
        }

        // --- INIT ---
        window.addEventListener('DOMContentLoaded', () => {
            setGreeting();
            safeCreateIcons();
            loadAdvisories();
            populateRouteOptions();
        });

        function setGreeting() {
            const h = new Date().getHours();
            let g = 'Welcome,';
            if (h < 12) g = 'Good Morning,';
            else if (h < 18) g = 'Good Afternoon,';
            else g = 'Good Evening,';
            
            const el = document.getElementById('greeting-text');
            if(el) el.textContent = g;
        }

        // --- NAVIGATION ---
        function switchTab(tabId) {
            // Hide all sections
            document.querySelectorAll('section[id^="tab-"]').forEach(el => el.classList.add('hidden'));
            document.getElementById('tab-' + tabId).classList.remove('hidden');

            // Desktop Nav Active State
            document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
            const dBtn = document.getElementById('nav-' + tabId);
            if(dBtn) dBtn.classList.add('active');

            // Mobile Nav Active State
            document.querySelectorAll('.nav-btn-mobile').forEach(el => {
                el.classList.remove('text-brand-600');
                el.classList.add('text-slate-400');
            });
            const mBtn = document.querySelector(`.nav-btn-mobile[data-target="${tabId}"]`);
            if(mBtn) {
                mBtn.classList.remove('text-slate-400');
                mBtn.classList.add('text-brand-600');
            }

            // Lazy Load
            if (tabId === 'routes') loadRoutes();
            if (tabId === 'terminals') loadTerminals();

            // Mobile menu close
            document.getElementById('mobile-menu').classList.add('hidden');
            
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function toggleComplaintMode(mode) {
            ['new', 'track', 'my'].forEach(m => {
                const el = document.getElementById('mode-' + m);
                if(el) el.classList.add('hidden');
                
                const btn = document.getElementById('btn-mode-' + m);
                if(btn) {
                    btn.className = 'flex-1 py-4 text-sm font-bold text-slate-500 hover:text-slate-700 hover:bg-slate-50 transition-colors';
                }
            });

            document.getElementById('mode-' + mode).classList.remove('hidden');
            const activeBtn = document.getElementById('btn-mode-' + mode);
            if(activeBtn) {
                activeBtn.className = 'flex-1 py-4 text-sm font-bold text-brand-600 border-b-2 border-brand-600 bg-white transition-colors';
            }

            if (mode === 'my') loadMyComplaints();
        }

        // --- DATA LOADING ---
        let lastAdvisoryUpdate = null;

        async function loadAdvisories() {
            try {
                document.getElementById('last-updated').textContent = 'Updating...';
                const ts = Date.now();
                const data = await fetchJsonSafe(`${API_URL}?action=get_advisories&hours=24&_ts=${ts}`);
                const container = document.getElementById('advisories-container');

                if (data.ok && data.data.length > 0) {
                    container.innerHTML = data.data.map(item => {
                        let color = 'blue'; // default
                        let icon = 'info';
                        
                        if (item.type === 'alert') { color = 'red'; icon = 'alert-octagon'; }
                        else if (item.type === 'warning') { color = 'amber'; icon = 'alert-triangle'; }
                        
                        const isPredictive = (item.source || '') === 'predictive';
                        const sourceLabel = isPredictive ? 'Forecast' : 'Advisory';
                        const timeStr = new Date(item.posted_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});

                        return `
                        <div class="group bg-white rounded-2xl p-5 border-l-4 border-${color}-500 shadow-sm hover:shadow-md transition-all duration-300 border-y border-r border-slate-100">
                            <div class="flex items-start gap-4">
                                <div class="w-10 h-10 rounded-full bg-${color}-50 text-${color}-600 flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform">
                                    <i data-lucide="${icon}" class="w-5 h-5"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex justify-between items-start mb-1">
                                        <div class="flex items-center gap-2">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-${color}-50 text-${color}-700 tracking-wide">${sourceLabel}</span>
                                            <h3 class="font-bold text-slate-800 truncate">${item.title}</h3>
                                        </div>
                                        <span class="text-xs font-medium text-slate-400 whitespace-nowrap">${timeStr}</span>
                                    </div>
                                    <p class="text-sm text-slate-600 leading-relaxed">${item.content}</p>
                                </div>
                            </div>
                        </div>
                        `;
                    }).join('');
                    safeCreateIcons();
                    lastAdvisoryUpdate = new Date();
                    updateLastUpdatedTime();
                } else {
                    container.innerHTML = `
                        <div class="bg-white rounded-2xl p-8 text-center border border-slate-100 shadow-sm">
                            <div class="inline-flex items-center justify-center w-12 h-12 bg-green-50 text-green-600 rounded-full mb-3">
                                <i data-lucide="check-circle-2" class="w-6 h-6"></i>
                            </div>
                            <h3 class="font-bold text-slate-800">All Systems Normal</h3>
                            <p class="text-sm text-slate-500 mt-1">There are no active service advisories at this time.</p>
                        </div>
                    `;
                    safeCreateIcons();
                }
            } catch (e) {
                console.error(e);
            }
        }

        function updateLastUpdatedTime() {
            const el = document.getElementById('last-updated');
            if (!el || !lastAdvisoryUpdate) return;
            const s = Math.floor((new Date() - lastAdvisoryUpdate) / 1000);
            el.textContent = s < 60 ? 'Just now' : Math.floor(s/60) + 'm ago';
        }
        setInterval(updateLastUpdatedTime, 10000);
        setInterval(loadAdvisories, 300000);

        async function loadRoutes() {
            const tbody = document.getElementById('routes-table-body');
            if (tbody.getAttribute('data-loaded')) return;
            
            try {
                const data = await fetchJsonSafe(`${API_URL}?action=get_routes&_ts=${Date.now()}`);
                if (data.ok && data.data.length > 0) {
                    tbody.innerHTML = data.data.map(r => `
                        <tr class="group hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-900 group-hover:text-brand-600 transition-colors">${r.route_name}</div>
                                <div class="flex items-center gap-2 text-xs text-slate-500 mt-1">
                                    <span>${r.origin}</span>
                                    <i data-lucide="arrow-right" class="w-3 h-3"></i>
                                    <span>${r.destination}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center font-mono font-bold text-slate-700 bg-slate-50/50">
                                ₱${parseFloat(r.fare).toFixed(2)}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-600 border border-emerald-100">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5"></span>
                                    Active
                                </span>
                            </td>
                        </tr>
                    `).join('');
                    tbody.setAttribute('data-loaded', 'true');
                    safeCreateIcons();
                } else {
                    tbody.innerHTML = `<tr><td colspan="3" class="p-8 text-center text-slate-400 italic">No routes available</td></tr>`;
                }
            } catch (e) {
                tbody.innerHTML = `<tr><td colspan="3" class="p-8 text-center text-red-400">Failed to load routes</td></tr>`;
            }
        }

        async function loadTerminals() {
            const grid = document.getElementById('terminals-grid');
            if (grid.getAttribute('data-loaded')) return;

            try {
                const data = await fetchJsonSafe(`${API_URL}?action=get_terminals&_ts=${Date.now()}`);
                if (data.ok && data.data.length > 0) {
                    grid.innerHTML = data.data.map(t => `
                        <div class="group bg-white rounded-2xl p-6 shadow-sm border border-slate-200 hover:border-brand-300 hover:shadow-lg hover:shadow-brand-500/10 transition-all duration-300">
                            <div class="flex justify-between items-start mb-4">
                                <div class="w-10 h-10 rounded-xl bg-slate-50 text-slate-400 flex items-center justify-center group-hover:bg-brand-500 group-hover:text-white transition-colors">
                                    <i data-lucide="map-pin" class="w-5 h-5"></i>
                                </div>
                                <span class="px-2 py-1 rounded-lg bg-slate-100 text-xs font-bold text-slate-500">${t.city || 'City'}</span>
                            </div>
                            <h3 class="font-bold text-lg text-slate-900 mb-2 group-hover:text-brand-600 transition-colors">${t.name}</h3>
                            <p class="text-sm text-slate-500 mb-4 line-clamp-2">${t.address || 'Location details not available'}</p>
                            <div class="pt-4 border-t border-slate-100 flex items-center gap-2 text-xs font-medium text-slate-400">
                                <i data-lucide="bus" class="w-3 h-3"></i>
                                <span>Capacity: <span class="text-slate-700 font-bold">${t.capacity || 'N/A'}</span> units</span>
                            </div>
                        </div>
                    `).join('');
                    grid.setAttribute('data-loaded', 'true');
                    safeCreateIcons();
                } else {
                    grid.innerHTML = `<div class="col-span-full text-center py-12 text-slate-400">No terminals found</div>`;
                }
            } catch (e) {
                console.error(e);
            }
        }

        async function populateRouteOptions() {
            try {
                const data = await fetchJsonSafe(`${API_URL}?action=get_routes&_ts=${Date.now()}`);
                const sel = document.getElementById('complaint-route-select');
                if (data.ok && data.data.length && sel) {
                    let html = '<option value="">Select Route...</option><option value="Other">Other / Not Listed</option>';
                    html += data.data.map(r => `<option value="${r.route_id}">${r.route_name}</option>`).join('');
                    sel.innerHTML = html;
                }
            } catch(e) {}
        }

        // --- COMPLAINTS ---
        async function submitComplaint(e) {
            e.preventDefault();
            const btn = document.getElementById('btn-submit');
            const orig = btn.innerHTML;
            btn.innerHTML = '<i data-lucide="loader-2" class="w-5 h-5 animate-spin"></i> Sending...';
            btn.disabled = true;
            safeCreateIcons();

            try {
                const fd = new FormData(e.target);
                fd.append('action', 'submit_complaint');
                const res = await fetch(API_URL, {method:'POST', body:fd});
                const data = await res.json();
                
                if(data.ok) {
                    toggleComplaintMode('track');
                    document.getElementById('trackRef').value = data.ref_number;
                    trackComplaint();
                    e.target.reset();
                    // alert(`Success! Ref: ${data.ref_number}`);
                } else {
                    alert(data.error || 'Failed');
                }
            } catch(e) {
                alert('Connection error');
            } finally {
                btn.innerHTML = orig;
                btn.disabled = false;
                safeCreateIcons();
            }
        }

        async function trackComplaint() {
            const ref = document.getElementById('trackRef').value.trim();
            if(!ref) return;
            
            try {
                const data = await fetchJsonSafe(`${API_URL}?action=get_complaint_status&ref_number=${ref}`);
                const resBox = document.getElementById('trackResult');
                const errBox = document.getElementById('trackError');
                
                if(data.ok) {
                    errBox.classList.add('hidden');
                    resBox.classList.remove('hidden');
                    
                    document.getElementById('trackStatus').innerText = data.data.status;
                    document.getElementById('trackDate').innerText = new Date(data.data.created_at).toLocaleString();
                    document.getElementById('trackDesc').innerText = data.data.description;
                    
                    const iconBox = document.getElementById('statusIcon');
                    let icon = 'clock';
                    let color = 'text-slate-400';
                    
                    if(data.data.status === 'Resolved') { icon = 'check-check'; color = 'text-emerald-500'; }
                    else if(data.data.status === 'Under Review') { icon = 'eye'; color = 'text-amber-500'; }
                    
                    iconBox.innerHTML = `<i data-lucide="${icon}" class="w-6 h-6 ${color}"></i>`;
                    safeCreateIcons();
                } else {
                    resBox.classList.add('hidden');
                    errBox.innerText = data.error || 'Not found';
                    errBox.classList.remove('hidden');
                }
            } catch(e) {}
        }

        async function loadMyComplaints() {
            const list = document.getElementById('my-complaints-list');
            list.innerHTML = '<div class="text-center py-4 text-slate-400"><i data-lucide="loader-2" class="w-5 h-5 animate-spin mx-auto"></i></div>';
            safeCreateIcons();
            
            try {
                const data = await fetchJsonSafe(`${API_URL}?action=get_my_complaints`);
                if(data.ok && data.data.length) {
                    list.innerHTML = data.data.map(c => {
                        let badgeColor = 'bg-slate-100 text-slate-600';
                        if(c.status === 'Resolved') badgeColor = 'bg-emerald-100 text-emerald-700';
                        if(c.status === 'Under Review') badgeColor = 'bg-amber-100 text-amber-700';
                        
                        return `
                        <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-sm">
                            <div class="flex justify-between mb-2">
                                <span class="font-bold text-slate-800">${c.complaint_type}</span>
                                <span class="text-[10px] font-bold uppercase px-2 py-1 rounded ${badgeColor}">${c.status}</span>
                            </div>
                            <p class="text-xs text-slate-500 mb-2 line-clamp-1">${c.description}</p>
                            <div class="text-[10px] font-mono text-slate-400 text-right">${new Date(c.created_at).toLocaleDateString()}</div>
                        </div>`;
                    }).join('');
                } else {
                    list.innerHTML = '<div class="text-center py-4 text-slate-400 text-sm">No history found.</div>';
                }
            } catch(e) {
                list.innerHTML = '<div class="text-center py-4 text-red-400 text-sm">Load failed.</div>';
            }
        }
    </script>
</body>
</html>
