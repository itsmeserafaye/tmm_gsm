<?php
require_once __DIR__ . '/../../includes/operator_portal.php';
$baseUrl = str_replace('\\', '/', (string) dirname(dirname(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/citizen/operator/index.php')))));
$baseUrl = $baseUrl === '/' ? '' : rtrim($baseUrl, '/');
operator_portal_require_login($baseUrl . '/index.php');
if (empty($_SESSION['operator_csrf'])) {
    $_SESSION['operator_csrf'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Operator Portal - TMM</title>
    <link rel="icon" type="image/jpeg" href="images/logo.jpg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        primary: '#f97316', // Orange-500
                        'primary-light': '#ffedd5', // Orange-100
                        'primary-dark': '#c2410c', // Orange-700
                        secondary: '#22c55e', // Green-500
                        'secondary-light': '#dcfce7', // Green-100
                        'secondary-dark': '#15803d', // Green-700
                        slate: {
                            850: '#1e293b', // Custom dark slate
                        }
                    },
                    boxShadow: {
                        'soft': '0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -1px rgba(0, 0, 0, 0.02)',
                        'glow': '0 0 15px rgba(249, 115, 22, 0.3)',
                    }
                }
            }
        }
    </script>
    <!-- Tesseract.js for OCR -->
    <script src="js/tesseract.min.js"></script>
    <script src="<?php echo htmlspecialchars($baseUrl); ?>/tmm_form_enhancements.js" defer></script>

    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .sidebar-link.active {
            background: linear-gradient(90deg, rgba(249, 115, 22, 0.1) 0%, transparent 100%);
            border-left: 3px solid #f97316;
            color: #f97316;
        }

        .sidebar-link:hover:not(.active) {
            background-color: #f8fafc;
            color: #334155;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        .animate-fade-in {
            animation: fadeIn 0.3s ease-out;
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
    </style>
</head>

<body class="bg-slate-50 text-slate-800 min-h-screen flex overflow-hidden">
    <div id="toastContainer" class="fixed top-4 right-4 z-[80] space-y-2 pointer-events-none"></div>

    <!-- SIDEBAR -->
    <aside class="w-64 bg-white border-r border-slate-200 hidden md:flex flex-col z-20 shadow-soft">
        <div class="p-6 flex items-center gap-3 border-b border-slate-100">
            <img src="images/logo.jpg" alt="Logo"
                class="w-10 h-10 rounded-xl shadow-sm object-cover ring-2 ring-slate-100">
            <div>
                <h1 class="text-lg font-bold tracking-tight text-slate-900">Operator<span
                        class="text-primary">Portal</span></h1>
                <p class="text-[10px] text-slate-500 font-medium uppercase tracking-wider">Management</p>
            </div>
        </div>

        <nav class="flex-1 p-4 space-y-1 overflow-y-auto">
            <button onclick="showSection('dashboard')" id="nav-dashboard"
                class="sidebar-link active w-full flex items-center gap-3 px-4 py-3 text-sm font-medium text-slate-600 rounded-r-lg transition-all duration-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z">
                    </path>
                </svg>
                Dashboard
            </button>
            <button onclick="showSection('applications')" id="nav-applications"
                class="sidebar-link w-full flex items-center gap-3 px-4 py-3 text-sm font-medium text-slate-600 rounded-r-lg transition-all duration-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                    </path>
                </svg>
                Applications
            </button>
            <button onclick="showSection('fleet')" id="nav-fleet"
                class="sidebar-link w-full flex items-center gap-3 px-4 py-3 text-sm font-medium text-slate-600 rounded-r-lg transition-all duration-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                </svg>
                My Fleet
            </button>
            <button onclick="showSection('violations')" id="nav-violations"
                class="sidebar-link w-full flex items-center gap-3 px-4 py-3 text-sm font-medium text-slate-600 rounded-r-lg transition-all duration-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                    </path>
                </svg>
                Violations
            </button>
            <button onclick="showSection('payments')" id="nav-payments"
                class="sidebar-link w-full flex items-center gap-3 px-4 py-3 text-sm font-medium text-slate-600 rounded-r-lg transition-all duration-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z">
                    </path>
                </svg>
                Payments & Fees
            </button>
            <button onclick="showSection('inspections')" id="nav-inspections"
                class="sidebar-link w-full flex items-center gap-3 px-4 py-3 text-sm font-medium text-slate-600 rounded-r-lg transition-all duration-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Inspections
            </button>
            <button onclick="showSection('downloads')" id="nav-downloads"
                class="sidebar-link w-full flex items-center gap-3 px-4 py-3 text-sm font-medium text-slate-600 rounded-r-lg transition-all duration-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 4v10m0 0l-3-3m3 3l3-3M5 20h14"></path>
                </svg>
                Downloads
            </button>

            <div class="mt-4 p-3 rounded-xl bg-slate-50 border border-slate-100">
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Active Plate</div>
                <select id="plateSelect"
                    class="w-full px-3 py-2 rounded-lg bg-white border border-slate-200 text-sm font-semibold text-slate-700 focus:ring-2 focus:ring-primary outline-none">
                    <option>Loading...</option>
                </select>
                <div class="mt-2 text-[11px] text-slate-500">Used when submitting new applications.</div>
            </div>
        </nav>

        <div class="p-4 border-t border-slate-100">
            <div class="bg-slate-50 p-3 rounded-xl flex items-center gap-3 cursor-pointer hover:bg-slate-100 transition"
                onclick="showProfileModal()">
                <div class="w-10 h-10 rounded-full overflow-hidden border-2 border-white shadow-sm">
                    <img src="https://ui-avatars.com/api/?name=Operator+User&background=random" alt="Profile">
                </div>
                <div class="overflow-hidden">
                    <p class="text-sm font-bold text-slate-800 truncate" id="sidebarName">Operator</p>
                    <p class="text-xs text-slate-500 truncate" id="sidebarSub">View Profile</p>
                </div>
            </div>
            <a href="logout.php"
                class="mt-3 w-full inline-flex items-center justify-center gap-2 py-2.5 rounded-xl bg-white border border-slate-200 text-slate-600 text-sm font-bold hover:bg-slate-50 hover:text-slate-800 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h6a2 2 0 012 2v1">
                    </path>
                </svg>
                Logout
            </a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <div class="flex-1 flex flex-col h-screen overflow-hidden relative">

        <!-- Top Mobile Header -->
        <header class="bg-white border-b border-slate-200 p-4 flex items-center justify-between md:hidden z-20">
            <div class="flex items-center gap-2">
                <img src="images/logo.jpg" alt="Logo" class="w-8 h-8 rounded-lg">
                <span class="font-bold text-slate-800">Operator Portal</span>
            </div>
            <button
                onclick="document.querySelector('aside').classList.toggle('hidden'); document.querySelector('aside').classList.toggle('absolute'); document.querySelector('aside').classList.toggle('h-full');"
                class="text-slate-500">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16">
                    </path>
                </svg>
            </button>
        </header>

        <!-- Content Area -->
        <main class="flex-1 overflow-y-auto bg-slate-50 p-4 md:p-8 scroll-smooth">
            <div class="max-w-6xl mx-auto space-y-8 animate-fade-in">

                <!-- DASHBOARD -->
                <section id="dashboard" class="space-y-8">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-2xl font-bold text-slate-900">Dashboard</h2>
                            <p class="text-slate-500 text-sm">Welcome back, here's what's happening today.</p>
                        </div>
                        <div class="hidden md:flex items-center gap-4">
                            <!-- Notifications Bell -->
                            <div class="relative">
                                <button onclick="toggleNotifications()"
                                    class="relative p-2 text-slate-400 hover:text-primary transition rounded-full hover:bg-slate-100">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9">
                                        </path>
                                    </svg>
                                    <span id="notifBadge"
                                        class="absolute top-1 right-1 w-2.5 h-2.5 bg-red-500 rounded-full border-2 border-white hidden"></span>
                                </button>
                                <!-- Notification Dropdown -->
                                <div id="notifDropdown"
                                    class="hidden absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-xl border border-slate-100 z-50 overflow-hidden">
                                    <div
                                        class="p-3 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                                        <span
                                            class="text-xs font-bold text-slate-500 uppercase tracking-wider">Notifications</span>
                                        <button onclick="loadNotifications()"
                                            class="text-[10px] text-primary font-bold hover:underline">Refresh</button>
                                    </div>
                                    <div id="notifList" class="max-h-64 overflow-y-auto divide-y divide-slate-50">
                                        <div class="p-4 text-center text-slate-400 italic text-xs">No new notifications
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <span
                                class="text-xs font-medium bg-white px-3 py-1 rounded-full border border-slate-200 text-slate-500 shadow-sm">
                                <span class="w-2 h-2 bg-green-500 rounded-full inline-block mr-1"></span> System
                                Operational
                            </span>
                        </div>
                    </div>

                    <div id="approvalBanner" class="hidden bg-amber-50 border border-amber-200 rounded-2xl p-5">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                            <div>
                                <div class="text-xs font-bold uppercase tracking-wider text-amber-700">Account Verification</div>
                                <div class="mt-1 text-sm font-semibold text-slate-800" id="approvalBannerTitle">Your operator account is pending approval.</div>
                                <div class="mt-1 text-xs text-slate-600" id="approvalBannerSub">Upload your documents so the admin/LGU can verify your account.</div>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" onclick="openVerificationModal()"
                                    class="px-4 py-2.5 rounded-xl bg-primary text-white text-sm font-bold hover:bg-primary-dark transition">Upload Documents</button>
                                <button type="button" onclick="loadVerificationStatus(true)"
                                    class="px-4 py-2.5 rounded-xl bg-white border border-amber-200 text-amber-700 text-sm font-bold hover:bg-amber-100 transition">Refresh</button>
                            </div>
                        </div>
                        <div id="approvalBannerRemarks" class="hidden mt-3 text-xs font-semibold text-rose-700 bg-rose-50 border border-rose-200 rounded-xl p-3"></div>
                    </div>

                    <!-- KPI Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div
                            class="bg-white p-6 rounded-2xl border border-slate-100 shadow-soft hover:shadow-lg transition group relative overflow-hidden">
                            <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition">
                                <svg class="w-24 h-24 text-primary" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z" />
                                    <path fill-rule="evenodd"
                                        d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z"
                                        clip-rule="evenodd" />
                                </svg>
                            </div>
                            <p class="text-sm font-semibold text-slate-400 uppercase tracking-wider">Pending
                                Applications</p>
                            <h3 class="text-4xl font-bold text-slate-800 mt-2" id="statPending">--</h3>
                            <div
                                class="mt-4 flex items-center text-xs font-medium text-orange-600 bg-orange-50 w-fit px-2 py-1 rounded-lg">
                                <span>Requires Action</span>
                            </div>
                        </div>

                        <div
                            class="bg-white p-6 rounded-2xl border border-slate-100 shadow-soft hover:shadow-lg transition group relative overflow-hidden">
                            <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition">
                                <svg class="w-24 h-24 text-secondary" fill="currentColor" viewBox="0 0 20 20">
                                    <path
                                        d="M8 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM15 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z" />
                                    <path
                                        d="M3 4a1 1 0 00-1 1v10a1 1 0 001 1h1.05a2.5 2.5 0 014.9 0H10a1 1 0 001-1V5a1 1 0 00-1-1H3zM14 7a1 1 0 00-1 1v6.05A2.5 2.5 0 0115.95 16H17a1 1 0 001-1v-5a1 1 0 00-.293-.707l-2-2A1 1 0 0015 7h-1z" />
                                </svg>
                            </div>
                            <p class="text-sm font-semibold text-slate-400 uppercase tracking-wider">Active Fleet</p>
                            <h3 class="text-4xl font-bold text-slate-800 mt-2" id="statVehicles">--</h3>
                            <div
                                class="mt-4 flex items-center text-xs font-medium text-green-600 bg-green-50 w-fit px-2 py-1 rounded-lg">
                                <span>On the Road</span>
                            </div>
                        </div>

                        <div
                            class="bg-white p-6 rounded-2xl border border-slate-100 shadow-soft hover:shadow-lg transition group relative overflow-hidden">
                            <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition">
                                <svg class="w-24 h-24 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                                        clip-rule="evenodd" />
                                </svg>
                            </div>
                            <p class="text-sm font-semibold text-slate-400 uppercase tracking-wider">Compliance Alerts
                            </p>
                            <h3 class="text-4xl font-bold text-slate-800 mt-2" id="statAlerts">--</h3>
                            <div
                                class="mt-4 flex items-center text-xs font-medium text-red-600 bg-red-50 w-fit px-2 py-1 rounded-lg">
                                <span>Needs Attention</span>
                            </div>
                        </div>
                    </div>

                    <!-- AI Insights Feed -->
                    <div
                        class="bg-gradient-to-br from-slate-900 to-slate-800 rounded-2xl p-6 md:p-8 text-white shadow-lg relative overflow-hidden">
                        <div class="relative z-10">
                            <div class="flex items-center justify-between mb-6">
                                <div class="flex items-center space-x-3">
                                    <div class="p-2 bg-white/10 rounded-lg backdrop-blur-sm">
                                        <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-bold">AI Demand & Dispatch Insights</h3>
                                        <p class="text-xs text-slate-400">Real-time forecasting engine</p>
                                    </div>
                                </div>
                                <span
                                    class="bg-primary/20 text-primary text-xs font-bold px-3 py-1 rounded-full border border-primary/20">LIVE</span>
                            </div>

                            <div id="aiInsightsContainer" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <p class="text-slate-400 text-sm">Loading insights...</p>
                            </div>
                        </div>

                        <!-- Abstract shapes -->
                        <div
                            class="absolute -top-24 -right-24 w-64 h-64 bg-primary rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob">
                        </div>
                        <div
                            class="absolute -bottom-24 -left-24 w-64 h-64 bg-secondary rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-2000">
                        </div>
                    </div>
                </section>

                <!-- APPLICATIONS -->
                <section id="applications" class="hidden space-y-8">
                    <div>
                        <h2 class="text-2xl font-bold text-slate-900">New Application</h2>
                        <p class="text-slate-500 text-sm">Submit documents for franchises, inspections, or terminal
                            enrollment.</p>
                    </div>

                    <div class="bg-white rounded-2xl shadow-soft border border-slate-100 overflow-hidden">
                        <div class="p-1 bg-gradient-to-r from-primary to-secondary"></div>
                        <div class="p-6 md:p-8">
                            <form id="appForm" class="space-y-6" onsubmit="submitApp(event)">

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="space-y-2">
                                        <label class="block text-sm font-semibold text-slate-700">Application
                                            Type</label>
                                        <div class="relative">
                                            <select id="appTypeSelect" name="type" onchange="toggleAppFields()"
                                                class="w-full pl-4 pr-10 py-3 bg-slate-50 rounded-xl border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-primary outline-none transition appearance-none"
                                                required>
                                                <option value="">Select Type...</option>
                                                <option value="Franchise Endorsement">Franchise Endorsement</option>
                                                <option value="Vehicle Inspection">Vehicle Inspection Request</option>
                                                <option value="Terminal Enrollment">Terminal Enrollment</option>
                                            </select>
                                            <div class="absolute right-3 top-3.5 pointer-events-none text-slate-400">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                </svg>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Dynamic Fields -->
                                    <div id="routeField" class="hidden space-y-2">
                                        <label class="block text-sm font-semibold text-slate-700">Select Route</label>
                                        <div class="relative">
                                            <select name="route_id" id="routeSelect"
                                                class="w-full pl-4 pr-10 py-3 bg-slate-50 rounded-xl border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-primary outline-none transition appearance-none">
                                                <option value="">Loading routes...</option>
                                            </select>
                                            <div class="absolute right-3 top-3.5 pointer-events-none text-slate-400">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                </svg>
                                            </div>
                                        </div>
                                    </div>

                                    <div id="dateField" class="hidden space-y-2">
                                        <label class="block text-sm font-semibold text-slate-700">Preferred Inspection Date</label>
                                        <input type="datetime-local" name="schedule_date"
                                            class="w-full px-4 py-3 bg-slate-50 rounded-xl border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-primary outline-none transition">
                                    </div>
                                </div>

                                <!-- AI Document Check -->
                                <div class="bg-blue-50/50 rounded-xl border border-blue-100 p-6">
                                    <div class="flex items-start gap-4">
                                        <div class="p-3 bg-blue-100 text-blue-600 rounded-lg shrink-0">
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                                                </path>
                                            </svg>
                                        </div>
                                        <div class="flex-1">
                                            <h4 class="text-sm font-bold text-blue-900">AI Document Pre-Check</h4>
                                            <p class="text-xs text-blue-700 mt-1 mb-4">Upload your OR/CR or relevant
                                                permits. Our AI will verify readability instantly.</p>

                                            <div class="relative group">
                                                <input name="document" type="file"
                                                    class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                                                    accept="image/*,application/pdf" onchange="checkDocument(this)">
                                                <div
                                                    class="border-2 border-dashed border-blue-200 rounded-xl p-6 text-center bg-white group-hover:border-blue-400 transition">
                                                    <p class="text-sm font-medium text-slate-600" id="docStatus">Click
                                                        or drag file here to upload</p>
                                                    <p class="text-xs text-slate-400 mt-1">Supports JPG, PNG, PDF</p>
                                                </div>
                                            </div>

                                            <div id="aiAnalysisResult" class="mt-4 hidden animate-fade-in">
                                                <div class="bg-white p-4 rounded-xl border border-blue-100 shadow-sm">
                                                    <div class="flex justify-between items-center mb-2">
                                                        <span
                                                            class="text-xs font-bold uppercase tracking-wider text-slate-400">Analysis
                                                            Result</span>
                                                        <span id="aiStatusBadge"
                                                            class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-500">PENDING</span>
                                                    </div>
                                                    <p id="aiTextPreview"
                                                        class="text-sm text-slate-600 font-mono bg-slate-50 p-3 rounded-lg border border-slate-100 line-clamp-3">
                                                        Scanning...</p>
                                                    <p class="mt-2 text-xs font-bold" id="aiStatusMsg"></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <label class="block text-sm font-semibold text-slate-700">Additional Notes</label>
                                    <textarea name="notes" rows="4"
                                        class="w-full p-4 bg-slate-50 rounded-xl border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-primary outline-none transition"
                                        placeholder="Any specific details about your request..."></textarea>
                                </div>

                                <div class="flex justify-end pt-4">
                                    <button type="submit"
                                        class="bg-gradient-to-r from-primary to-primary-dark text-white px-8 py-3 rounded-xl font-bold shadow-lg hover:shadow-orange-500/30 transform hover:-translate-y-0.5 transition-all duration-200">Submit
                                        Application</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl shadow-soft border border-slate-100 overflow-hidden">
                        <div class="p-6 md:p-8">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <h3 class="text-lg font-bold text-slate-900">Recent Submissions</h3>
                                    <p class="text-xs text-slate-500">Your last 20 applications and their status.</p>
                                </div>
                                <button type="button" onclick="loadApplications()"
                                    class="text-sm font-bold text-primary hover:text-primary-dark transition">Refresh</button>
                            </div>
                            <div class="overflow-x-auto rounded-xl border border-slate-200">
                                <table class="w-full text-left text-sm">
                                    <thead class="bg-slate-50 border-b border-slate-200">
                                        <tr>
                                            <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                                Date</th>
                                            <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                                Plate</th>
                                            <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                                Type</th>
                                            <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                                Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="appsTable" class="divide-y divide-slate-100">
                                        <tr>
                                            <td colspan="4" class="p-6 text-center text-slate-400 italic">Loading...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- FLEET -->
                <section id="fleet" class="hidden space-y-8">
                    <div>
                        <h2 class="text-2xl font-bold text-slate-900">Fleet Management</h2>
                        <p class="text-slate-500 text-sm">Monitor compliance and status of your registered vehicles.</p>
                        <div class="mt-4">
                            <button onclick="showOperatorRecordModal()"
                                class="bg-slate-900 text-white px-4 py-2 rounded-lg text-sm font-bold shadow-md hover:bg-black transition inline-flex items-center gap-2 mr-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                Submit Operator Record
                            </button>
                            <button onclick="showAddVehicleModal()"
                                class="bg-primary text-white px-4 py-2 rounded-lg text-sm font-bold shadow-md hover:bg-orange-600 transition inline-flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v16m8-8H4"></path>
                                </svg>
                                Submit Vehicle Encoding
                            </button>
                            <button onclick="generateDeclaredFleetPreview()"
                                class="bg-white text-slate-800 px-4 py-2 rounded-lg text-sm font-bold shadow-md hover:bg-slate-50 transition inline-flex items-center gap-2 ml-2 border border-slate-200">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12h6m-6 4h6M9 8h6M7 20h10a2 2 0 002-2V6a2 2 0 00-2-2H7a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                                Generate Declared Fleet
                            </button>
                            <button onclick="showTransferRequestModal()"
                                class="bg-white text-slate-800 px-4 py-2 rounded-lg text-sm font-bold shadow-md hover:bg-slate-50 transition inline-flex items-center gap-2 ml-2 border border-slate-200">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M7 16l-4-4m0 0l4-4m-4 4h18M17 8l4 4m0 0l-4 4m4-4H3"></path>
                                </svg>
                                Create Transfer Request
                            </button>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl shadow-soft border border-slate-100 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead class="bg-slate-50 border-b border-slate-200">
                                    <tr>
                                        <th class="p-5 font-semibold text-xs text-slate-500 uppercase tracking-wider">
                                            Plate Number</th>
                                        <th class="p-5 font-semibold text-xs text-slate-500 uppercase tracking-wider">
                                            Status</th>
                                        <th class="p-5 font-semibold text-xs text-slate-500 uppercase tracking-wider">
                                            Inspection</th>
                                        <th class="p-5 font-semibold text-xs text-slate-500 uppercase tracking-wider">
                                            Validity</th>
                                        <th class="p-5 font-semibold text-xs text-slate-500 uppercase tracking-wider">
                                            Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="fleetTable" class="divide-y divide-slate-100">
                                    <!-- Populated via JS -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- VIOLATIONS -->
                <section id="violations" class="hidden space-y-8">
                    <div>
                         <h2 class="text-2xl font-bold text-slate-900">Violations</h2>
                         <p class="text-slate-500 text-sm">Review traffic citations and settlement status.</p>
                    </div>
                    <div class="bg-white rounded-2xl shadow-soft border border-slate-100 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead class="bg-slate-50 border-b border-slate-200">
                                    <tr>
                                        <th class="p-5 font-semibold text-xs text-slate-500 uppercase tracking-wider">Date</th>
                                        <th class="p-5 font-semibold text-xs text-slate-500 uppercase tracking-wider">Ticket No</th>
                                        <th class="p-5 font-semibold text-xs text-slate-500 uppercase tracking-wider">Plate</th>
                                        <th class="p-5 font-semibold text-xs text-slate-500 uppercase tracking-wider">Violation</th>
                                        <th class="p-5 font-semibold text-xs text-slate-500 uppercase tracking-wider">Amount</th>
                                        <th class="p-5 font-semibold text-xs text-slate-500 uppercase tracking-wider">Status</th>
                                    </tr>
                                </thead>
                                <tbody id="violationsTable" class="divide-y divide-slate-100">
                                    <tr><td colspan="6" class="p-8 text-center text-slate-400 italic">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- PAYMENTS -->
                <section id="payments" class="hidden space-y-8">
                    <div>
                         <h2 class="text-2xl font-bold text-slate-900">Payments & Fees</h2>
                         <p class="text-slate-500 text-sm">Track assessed fees and upload proof of payment.</p>
                    </div>
                     <div class="bg-white rounded-2xl shadow-soft border border-slate-100 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead class="bg-slate-50 border-b border-slate-200">
                                    <tr>
                                        <th class="p-5 font-semibold text-xs text-slate-500 uppercase tracking-wider">Date</th>
                                        <th class="p-5 font-semibold text-xs text-slate-500 uppercase tracking-wider">Plate</th>
                                        <th class="p-5 font-semibold text-xs text-slate-500 uppercase tracking-wider">Type</th>
                                        <th class="p-5 font-semibold text-xs text-slate-500 uppercase tracking-wider">Amount</th>
                                        <th class="p-5 font-semibold text-xs text-slate-500 uppercase tracking-wider">Status</th>
                                        <th class="p-5 font-semibold text-xs text-slate-500 uppercase tracking-wider">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="feesTable" class="divide-y divide-slate-100">
                                    <tr><td colspan="6" class="p-8 text-center text-slate-400 italic">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- INSPECTIONS -->
                <section id="inspections" class="hidden space-y-8">
                    <div>
                        <h2 class="text-2xl font-bold text-slate-900">Inspections</h2>
                        <p class="text-slate-500 text-sm">View inspection status and requests.</p>
                    </div>
                    <div class="bg-white rounded-2xl shadow-soft border border-slate-100 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead class="bg-slate-50 border-b border-slate-200">
                                    <tr>
                                        <th class="p-5 font-semibold text-xs text-slate-500 uppercase tracking-wider">Plate</th>
                                        <th class="p-5 font-semibold text-xs text-slate-500 uppercase tracking-wider">Inspection Status</th>
                                        <th class="p-5 font-semibold text-xs text-slate-500 uppercase tracking-wider">Last Passed</th>
                                        <th class="p-5 font-semibold text-xs text-slate-500 uppercase tracking-wider">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="inspectionsTable" class="divide-y divide-slate-100">
                                    <tr><td colspan="4" class="p-8 text-center text-slate-400 italic">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- DOWNLOADS -->
                <section id="downloads" class="hidden space-y-8">
                    <div>
                        <h2 class="text-2xl font-bold text-slate-900">Downloads</h2>
                        <p class="text-slate-500 text-sm">Download approved documents and certificates.</p>
                    </div>
                    <div class="bg-white rounded-2xl shadow-soft border border-slate-100 overflow-hidden">
                        <div class="p-6 md:p-8">
                            <div id="downloadsList" class="space-y-3">
                                <div class="p-4 text-center text-slate-400 italic text-xs">Loading...</div>
                            </div>
                        </div>
                    </div>
                </section>

            </div>
        </main>
    </div>

    <!-- Profile Modal (Refined) -->
    <div id="profileModal"
        class="fixed inset-0 bg-black/60 z-50 hidden flex items-center justify-center p-4 backdrop-blur-sm transition-all duration-300">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full p-0 overflow-hidden animate-fade-in relative">

            <!-- Header Background -->
            <div class="h-32 bg-gradient-to-r from-primary to-secondary relative">
                <button onclick="closeProfileModal()"
                    class="absolute top-4 right-4 text-white/80 hover:text-white bg-black/10 hover:bg-black/20 rounded-full p-1 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>

            <!-- Avatar & Info -->
            <div class="px-8 pb-8 -mt-16 text-center relative z-10">
                <div class="w-32 h-32 bg-white rounded-full mx-auto p-1 shadow-lg mb-4">
                    <img src="https://ui-avatars.com/api/?name=Operator+User&background=random&size=128" alt="User"
                        id="profAvatar" class="w-full h-full rounded-full object-cover">
                </div>
                <h3 class="text-2xl font-bold text-slate-800" id="profHeaderName">Loading...</h3>
                <p class="text-sm font-medium text-primary bg-orange-50 inline-block px-3 py-1 rounded-full mt-1"
                    id="profHeaderAssoc">Loading...</p>
            </div>

            <div class="px-8 pb-8">
                <!-- VIEW MODE -->
                <div id="viewMode" class="space-y-6">
                    <div class="space-y-4">
                        <div
                            class="flex items-center justify-between p-4 bg-slate-50 rounded-xl border border-slate-100">
                            <div>
                                <span class="block text-xs font-bold text-slate-400 uppercase tracking-wider">Full
                                    Name</span>
                                <span class="font-semibold text-slate-700" id="viewName">--</span>
                            </div>
                            <svg class="w-5 h-5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                        </div>
                        <div
                            class="flex items-center justify-between p-4 bg-slate-50 rounded-xl border border-slate-100">
                            <div>
                                <span class="block text-xs font-bold text-slate-400 uppercase tracking-wider">Email
                                    Address</span>
                                <span class="font-semibold text-slate-700" id="viewEmail">--</span>
                            </div>
                            <svg class="w-5 h-5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                                </path>
                            </svg>
                        </div>
                        <div
                            class="flex items-center justify-between p-4 bg-slate-50 rounded-xl border border-slate-100">
                            <div>
                                <span class="block text-xs font-bold text-slate-400 uppercase tracking-wider">Contact
                                    Number</span>
                                <span class="font-semibold text-slate-700" id="viewContact">--</span>
                            </div>
                            <svg class="w-5 h-5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z">
                                </path>
                            </svg>
                        </div>
                    </div>
                    <button onclick="enableEditMode()"
                        class="w-full py-3 bg-slate-800 text-white rounded-xl font-bold hover:bg-slate-900 transition flex items-center justify-center gap-2 shadow-lg">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                            </path>
                        </svg>
                        Edit Profile
                    </button>
                </div>

                <!-- EDIT MODE -->
                <form id="editMode" class="hidden space-y-5" onsubmit="promptPassword(event)">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Full Name</label>
                            <input type="text" id="editName" name="name"
                                class="w-full px-4 py-3 bg-slate-50 rounded-xl border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-primary outline-none transition"
                                required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Email Address</label>
                            <input type="email" id="editEmail" name="email"
                                pattern="^(?!.*\.\.)[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[A-Za-z]{2,}$"
                                class="w-full px-4 py-3 bg-slate-50 rounded-xl border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-primary outline-none transition"
                                placeholder="juan.delacruz@email.com"
                                required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Contact Number</label>
                            <input type="tel" id="editContact" name="contact_info" inputmode="tel" minlength="7" maxlength="20"
                                pattern="^(\+639\d{9}|09\d{9}|(\+63|0)9\d{2}[- ]?\d{3}[- ]?\d{4}|0[2-8]\d{7,8})$"
                                class="w-full px-4 py-3 bg-slate-50 rounded-xl border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-primary outline-none transition"
                                placeholder="09171234567 or +639171234567"
                                required>
                        </div>

                        <div class="pt-4 border-t border-slate-100 hidden">
                            <!-- Moved to Security Modal -->
                        </div>
                    </div>

                    <div class="flex gap-4 pt-2">
                        <button type="button" onclick="cancelEditMode()"
                            class="flex-1 py-3 bg-white border border-slate-200 text-slate-600 rounded-xl text-sm font-bold hover:bg-slate-50 hover:text-slate-800 transition">Cancel</button>
                        <button type="submit"
                            class="flex-1 py-3 bg-primary text-white rounded-xl text-sm font-bold hover:bg-primary-dark shadow-lg shadow-orange-500/20 transition">Save
                            Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Operator Record Submission Modal -->
    <div id="operatorRecordModal"
        class="fixed inset-0 bg-black/60 z-50 hidden flex items-center justify-center p-4 backdrop-blur-sm transition-all duration-300">
        <div class="bg-white rounded-2xl shadow-xl max-w-lg w-full p-6 animate-fade-in">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h3 class="text-lg font-bold text-slate-800">Submit Operator Record</h3>
                    <p class="text-xs text-slate-500 mt-1">Submit your operator details for admin verification and approval.</p>
                </div>
                <button type="button" onclick="document.getElementById('operatorRecordModal').classList.add('hidden')"
                    class="text-slate-400 hover:text-slate-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <form onsubmit="submitOperatorRecord(event)" class="space-y-4" novalidate>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Operator Type</label>
                        <select name="operator_type" id="opRecType" class="w-full px-4 py-3 bg-slate-50 rounded-xl border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-primary outline-none transition text-sm font-semibold">
                            <option value="Individual">Individual</option>
                            <option value="Cooperative">Cooperative</option>
                            <option value="Corporation">Corporation</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Contact No</label>
                        <input type="tel" name="contact_no" id="opRecContact" inputmode="tel" minlength="7" maxlength="20"
                            pattern="^(\\+639\\d{9}|09\\d{9}|(\\+63|0)9\\d{2}[- ]?\\d{3}[- ]?\\d{4}|0[2-8]\\d{7,8})$"
                            class="w-full px-4 py-3 bg-slate-50 rounded-xl border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-primary outline-none transition text-sm font-semibold"
                            placeholder="09171234567 or +639171234567">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Registered Name</label>
                    <input type="text" name="registered_name" id="opRecRegisteredName"
                        class="w-full px-4 py-3 bg-slate-50 rounded-xl border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-primary outline-none transition text-sm font-semibold"
                        placeholder="Registered name (if applicable)">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Operator Name</label>
                    <input type="text" name="name" id="opRecName"
                        class="w-full px-4 py-3 bg-slate-50 rounded-xl border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-primary outline-none transition text-sm font-semibold"
                        placeholder="Name to display in records">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Address</label>
                    <input type="text" name="address" id="opRecAddress"
                        class="w-full px-4 py-3 bg-slate-50 rounded-xl border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-primary outline-none transition text-sm font-semibold"
                        placeholder="Complete address">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Coop / Association Name (optional)</label>
                    <input type="text" name="coop_name" id="opRecCoop"
                        class="w-full px-4 py-3 bg-slate-50 rounded-xl border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-primary outline-none transition text-sm font-semibold"
                        placeholder="e.g., XYZ Cooperative">
                </div>
                <button type="submit"
                    class="w-full py-3 bg-slate-900 text-white rounded-xl font-bold shadow-lg hover:shadow-slate-900/30 transition">Submit
                    for Verification</button>
            </form>
        </div>
    </div>

    <!-- Add Vehicle Modal -->
    <div id="addVehicleModal"
        class="fixed inset-0 bg-black/60 z-50 hidden flex items-center justify-center p-4 backdrop-blur-sm transition-all duration-300">
        <div class="bg-white rounded-2xl shadow-xl max-w-2xl w-full p-6 animate-fade-in">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h3 class="text-lg font-bold text-slate-800">Vehicle Encoding & Documents</h3>
                    <p class="text-xs text-slate-500 mt-1">Submit vehicle details and OR/CR documents for admin verification.</p>
                </div>
                <button onclick="document.getElementById('addVehicleModal').classList.add('hidden')"
                    class="text-slate-400 hover:text-slate-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>
            <form onsubmit="submitNewVehicle(event)" class="space-y-4" enctype="multipart/form-data" novalidate>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Plate Number</label>
                        <input type="text" name="plate_number" minlength="7" maxlength="8" pattern="^[A-Za-z]{3}\\-[0-9]{3,4}$" autocapitalize="characters" data-tmm-mask="plate"
                            class="w-full px-4 py-3 bg-slate-50 rounded-xl border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-primary outline-none transition uppercase text-sm font-semibold"
                            placeholder="ABC-1234" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Vehicle Type</label>
                        <input type="text" name="vehicle_type"
                            class="w-full px-4 py-3 bg-slate-50 rounded-xl border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-primary outline-none transition text-sm font-semibold"
                            placeholder="e.g., Jeepney" required>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Make</label>
                        <input type="text" name="make" maxlength="40"
                            class="w-full px-4 py-3 bg-slate-50 rounded-xl border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-primary outline-none transition text-sm font-semibold"
                            placeholder="e.g., Toyota">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Model</label>
                        <input type="text" name="model" maxlength="40"
                            class="w-full px-4 py-3 bg-slate-50 rounded-xl border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-primary outline-none transition text-sm font-semibold"
                            placeholder="e.g., Hiace">
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Year</label>
                        <input type="tel" name="year_model" inputmode="numeric" minlength="4" maxlength="4" pattern="^[0-9]{4}$" data-tmm-filter="digits"
                            class="w-full px-4 py-3 bg-slate-50 rounded-xl border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-primary outline-none transition text-sm font-semibold"
                            placeholder="2018">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Fuel Type</label>
                        <input type="text" name="fuel_type" maxlength="20"
                            class="w-full px-4 py-3 bg-slate-50 rounded-xl border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-primary outline-none transition text-sm font-semibold"
                            placeholder="Diesel">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Color</label>
                        <input type="text" name="color" maxlength="20"
                            class="w-full px-4 py-3 bg-slate-50 rounded-xl border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-primary outline-none transition text-sm font-semibold"
                            placeholder="White">
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Engine No</label>
                        <input type="text" name="engine_no" minlength="5" maxlength="20" pattern="^[A-Z0-9\\-]{5,20}$" autocapitalize="characters" data-tmm-uppercase="1" data-tmm-filter="alnumdash"
                            class="w-full px-4 py-3 bg-slate-50 rounded-xl border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-primary outline-none transition text-sm font-semibold uppercase"
                            placeholder="1NZFE-12345">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Chassis No (VIN)</label>
                        <input type="text" name="chassis_no" minlength="17" maxlength="17" pattern="^[A-HJ-NPR-Z0-9]{17}$" autocapitalize="characters" data-tmm-uppercase="1" data-tmm-filter="vin"
                            class="w-full px-4 py-3 bg-slate-50 rounded-xl border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-primary outline-none transition text-sm font-semibold uppercase"
                            placeholder="NCP12345678901234">
                    </div>
                </div>
                <div class="p-4 rounded-xl bg-slate-50 border border-slate-100">
                    <div class="text-xs font-bold text-slate-500 uppercase">OR/CR Metadata (Optional)</div>
                    <div class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">OR Number</label>
                            <input type="text" name="or_number" inputmode="numeric" minlength="6" maxlength="12" pattern="^[0-9]{6,12}$" data-tmm-filter="digits"
                                class="w-full px-4 py-3 bg-white rounded-xl border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-primary outline-none transition text-sm font-semibold"
                                placeholder="123456">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">CR Number</label>
                            <input type="text" name="cr_number" minlength="6" maxlength="20" pattern="^[A-Z0-9\\-]{6,20}$" autocapitalize="characters" data-tmm-uppercase="1" data-tmm-filter="alnumdash"
                                class="w-full px-4 py-3 bg-white rounded-xl border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-primary outline-none transition text-sm font-semibold uppercase"
                                placeholder="ABCD-123456">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">CR Issue Date</label>
                            <input type="date" name="cr_issue_date"
                                class="w-full px-4 py-3 bg-white rounded-xl border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-primary outline-none transition text-sm font-semibold">
                        </div>
                        <div class="sm:col-span-3">
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Registered Owner</label>
                            <input type="text" name="registered_owner" maxlength="150"
                                class="w-full px-4 py-3 bg-white rounded-xl border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-primary outline-none transition text-sm font-semibold"
                                placeholder="Name as it appears on CR">
                        </div>
                    </div>
                </div>
                <div class="p-4 rounded-xl bg-slate-50 border border-slate-100">
                    <div class="text-xs font-bold text-slate-500 uppercase">Required Documents</div>
                    <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">CR (Required)</label>
                            <input type="file" name="cr" accept=".pdf,.jpg,.jpeg,.png" required
                                class="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-primary-light file:text-primary hover:file:bg-orange-200">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">OR (Optional)</label>
                            <input type="file" name="or" accept=".pdf,.jpg,.jpeg,.png"
                                class="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-primary-light file:text-primary hover:file:bg-orange-200">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">OR Expiry Date</label>
                            <input type="date" name="or_expiry_date"
                                class="w-full px-4 py-3 bg-white rounded-xl border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-primary outline-none transition text-sm font-semibold">
                        </div>
                    </div>
                </div>
                <button type="submit"
                    class="w-full py-3 bg-primary text-white rounded-xl font-bold shadow-lg hover:shadow-orange-500/30 transition">Submit
                    for Verification</button>
            </form>
        </div>
    </div>

    <div id="declaredFleetModal"
        class="fixed inset-0 bg-black/60 z-50 hidden flex items-center justify-center p-4 backdrop-blur-sm transition-all duration-300">
        <div class="bg-white rounded-2xl shadow-xl max-w-4xl w-full p-6 animate-fade-in">
            <div class="flex items-start justify-between gap-4 mb-4">
                <div>
                    <h3 class="text-lg font-bold text-slate-800">Declared Fleet Preview</h3>
                    <p class="text-xs text-slate-500 mt-1">Review the generated file first. Upload is only enabled after confirmation.</p>
                </div>
                <button type="button" onclick="closeDeclaredFleetModal()" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div id="declaredFleetBody"></div>
        </div>
    </div>

    <div id="transferRequestModal"
        class="fixed inset-0 bg-black/60 z-50 hidden flex items-center justify-center p-4 backdrop-blur-sm transition-all duration-300">
        <div class="bg-white rounded-2xl shadow-xl max-w-xl w-full p-6 animate-fade-in">
            <div class="flex items-start justify-between gap-4 mb-4">
                <div>
                    <h3 class="text-lg font-bold text-slate-800">Create Ownership Transfer Request</h3>
                    <p class="text-xs text-slate-500 mt-1">Select one of your linked vehicles and provide the new owner name.</p>
                </div>
                <button type="button" onclick="closeTransferRequestModal()" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <form id="transferRequestForm" onsubmit="submitTransferRequest(event)" class="space-y-4" enctype="multipart/form-data" novalidate>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Vehicle</label>
                    <select name="vehicle_id" id="transferVehicleSelect" required
                        class="w-full px-4 py-3 bg-slate-50 rounded-xl border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-primary outline-none transition text-sm font-semibold">
                        <option value="">Loading…</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">New Owner (Text Only)</label>
                    <input type="text" name="to_operator_name" minlength="3" maxlength="255" required
                        class="w-full px-4 py-3 bg-slate-50 rounded-xl border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-primary outline-none transition text-sm font-semibold"
                        placeholder="Full name / Cooperative / Corporation name">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Transfer Type</label>
                        <select name="transfer_type"
                            class="w-full px-4 py-3 bg-slate-50 rounded-xl border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-primary outline-none transition text-sm font-semibold">
                            <option value="Sale">Sale</option>
                            <option value="Donation">Donation</option>
                            <option value="Inheritance">Inheritance</option>
                            <option value="Reassignment" selected>Reassignment</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">LTO Reference No (optional)</label>
                        <input type="text" name="lto_reference_no" maxlength="128"
                            class="w-full px-4 py-3 bg-slate-50 rounded-xl border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-primary outline-none transition text-sm font-semibold"
                            placeholder="e.g., LTO-REF-2026-000123">
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">OR/CR (optional)</label>
                        <input type="file" name="orcr_doc" accept=".pdf,.jpg,.jpeg,.png"
                            class="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-primary-light file:text-primary hover:file:bg-orange-200">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Deed / Authorization (required)</label>
                        <input type="file" name="deed_doc" accept=".pdf,.jpg,.jpeg,.png" required
                            class="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-primary-light file:text-primary hover:file:bg-orange-200">
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" onclick="closeTransferRequestModal()"
                        class="px-4 py-2.5 rounded-xl bg-white border border-slate-200 text-slate-600 text-sm font-bold hover:bg-slate-50 transition">Cancel</button>
                    <button type="submit" id="btnSubmitTransfer"
                        class="px-4 py-2.5 rounded-xl bg-slate-900 text-white text-sm font-bold hover:bg-black transition">Submit</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Upload Payment Modal -->
    <div id="paymentModal"
        class="fixed inset-0 bg-black/60 z-50 hidden flex items-center justify-center p-4 backdrop-blur-sm transition-all duration-300">
        <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6 animate-fade-in">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-slate-800">Upload Payment Proof</h3>
                <button onclick="document.getElementById('paymentModal').classList.add('hidden')"
                    class="text-slate-400 hover:text-slate-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>
            <form onsubmit="submitPayment(event)" class="space-y-4">
                <input type="hidden" id="payFeeId" name="fee_id">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Proof of Payment (Image/PDF)</label>
                    <input type="file" name="payment_proof"
                        class="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-primary-light file:text-primary hover:file:bg-orange-200"
                        accept="image/*,application/pdf" required>
                </div>
                <button type="submit"
                    class="w-full py-3 bg-secondary text-white rounded-xl font-bold shadow-lg hover:shadow-green-500/30 transition">Upload
                    Proof</button>
            </form>
        </div>
    </div>

    <div id="verificationModal" class="fixed inset-0 bg-black/60 z-50 hidden flex items-center justify-center p-4 backdrop-blur-sm transition-all duration-300">
        <div class="bg-white rounded-2xl shadow-xl max-w-xl w-full p-6 md:p-7 animate-fade-in">
            <div class="flex items-start justify-between gap-4 mb-4">
                <div>
                    <h3 class="text-lg font-bold text-slate-800">Upload Verification Documents</h3>
                    <p class="text-xs text-slate-500 mt-1" id="verifHint">Upload the required documents based on your operator type.</p>
                </div>
                <button type="button" onclick="closeVerificationModal()" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <div id="verifStatusBox" class="hidden mb-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="text-xs font-bold uppercase tracking-wider text-slate-500">Current Status</div>
                <div class="mt-1 text-sm font-semibold text-slate-800" id="verifStatusText">--</div>
                <div class="mt-2 text-xs text-slate-600" id="verifRemarksText"></div>
            </div>

            <form id="verificationForm" class="space-y-4" onsubmit="submitVerificationDocs(event)">
                <div id="verifInputs" class="space-y-3"></div>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" onclick="closeVerificationModal()" class="px-4 py-2.5 rounded-xl bg-white border border-slate-200 text-slate-600 text-sm font-bold hover:bg-slate-50 transition">Cancel</button>
                    <button type="submit" id="btnVerifSubmit" class="px-4 py-2.5 rounded-xl bg-primary text-white text-sm font-bold hover:bg-primary-dark transition">Submit</button>
                </div>
            </form>
        </div>
    </div>
    <div id="passwordConfirmModal" class="fixed inset-0 bg-black/60 z-50 hidden flex items-center justify-center p-4 backdrop-blur-sm transition-all duration-300">
        <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-8 animate-fade-in text-center">
            <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4 text-slate-500">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z">
                    </path>
                </svg>
            </div>
            <h3 class="text-xl font-bold text-slate-900 mb-2">Security Check</h3>
            <p class="text-sm text-slate-500 mb-6">For your security, please enter your current password to confirm these
                changes.</p>

            <form onsubmit="confirmSaveProfile(event)" class="space-y-4">
                <input type="password" id="currentPassConfirm" placeholder="Current Password"
                    class="w-full px-4 py-3 bg-slate-50 rounded-xl border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-slate-800 outline-none text-center font-bold tracking-widest"
                    required>

                <div class="text-left pt-2 border-t border-slate-100">
                    <p class="text-xs text-slate-800 font-bold uppercase mb-2">Change Password (Optional)</p>
                    <div class="space-y-3">
                        <input type="password" id="newPass" placeholder="New Password"
                            class="w-full px-4 py-3 bg-slate-50 rounded-xl border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-slate-800 outline-none text-sm">
                        <input type="password" id="confirmPass" placeholder="Confirm Password"
                            class="w-full px-4 py-3 bg-slate-50 rounded-xl border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-slate-800 outline-none text-sm">
                    </div>
                </div>

                <div class="flex gap-3">
                    <button type="button" onclick="document.getElementById('passwordConfirmModal').classList.add('hidden')"
                        class="flex-1 py-3 text-sm font-bold text-slate-500 hover:text-slate-700 transition">Cancel</button>
                    <button type="submit"
                        class="flex-1 py-3 bg-slate-900 text-white rounded-xl text-sm font-bold hover:bg-black shadow-lg transition">Confirm
                        Update</button>
                </div>
            </form>
        </div>
    </div>
    </div>

    <script>
        const csrfToken = <?php echo json_encode((string) ($_SESSION['operator_csrf'] ?? '')); ?>;
        const appBaseUrl = <?php echo json_encode((string) $baseUrl); ?>;

        function toast(message, variant = 'info') {
            const container = document.getElementById('toastContainer');
            if (!container) return;
            const el = document.createElement('div');
            const bg = variant === 'success' ? 'bg-emerald-600' : (variant === 'error' ? 'bg-rose-600' : (variant === 'warning' ? 'bg-amber-600' : 'bg-slate-800'));
            el.className = 'pointer-events-auto text-white text-sm font-semibold px-4 py-3 rounded-xl shadow-lg border border-white/10 ' + bg;
            el.textContent = message;
            container.appendChild(el);
            setTimeout(() => { el.classList.add('opacity-0'); el.classList.add('transition'); }, 2800);
            setTimeout(() => { try { el.remove(); } catch (e) { } }, 3400);
        }

        function escapeHtml(s) {
            return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        }

        async function apiGet(action) {
            const res = await fetch('api.php?action=' + encodeURIComponent(action), { headers: { 'Accept': 'application/json' } });
            return await res.json();
        }

        async function apiPost(formData) {
            const res = await fetch('api.php', {
                method: 'POST',
                headers: { 'X-CSRF-Token': csrfToken },
                body: formData
            });
            return await res.json();
        }

        async function initSession() {
            const data = await apiGet('get_session');
            if (!data || !data.ok) return;
            const sel = document.getElementById('plateSelect');
            const plates = Array.isArray(data.data.plates) ? data.data.plates : [];
            const active = data.data.active_plate || '';
            if (sel) {
                sel.innerHTML = '';
                if (!plates.length) {
                    const opt = document.createElement('option');
                    opt.textContent = 'No assigned plates';
                    opt.value = '';
                    sel.appendChild(opt);
                } else {
                    plates.forEach(p => {
                        const opt = document.createElement('option');
                        opt.value = p;
                        opt.textContent = p;
                        if (p === active) opt.selected = true;
                        sel.appendChild(opt);
                    });
                }
                sel.addEventListener('change', async function () {
                    const v = this.value || '';
                    if (!v) return;
                    const fd = new FormData();
                    fd.append('action', 'set_active_plate');
                    fd.append('plate_number', v);
                    const r = await apiPost(fd);
                    if (r && r.ok) {
                        toast('Active plate set to ' + v, 'success');
                        loadStats();
                        loadApplications();
                    } else {
                        toast(r && r.error ? r.error : 'Failed to change plate', 'error');
                    }
                }, { once: true });
            }
        }

        // --- Navigation ---
        function showSection(id) {
            ['dashboard', 'applications', 'fleet', 'violations', 'payments', 'inspections', 'downloads'].forEach(s => {
                const el = document.getElementById(s);
                if (el) el.classList.add('hidden');
                const btn = document.getElementById('nav-' + s);
                if (btn) btn.classList.remove('active');
            });
            const target = document.getElementById(id);
            if (target) target.classList.remove('hidden');
            const activeBtn = document.getElementById('nav-' + id);
            if (activeBtn) activeBtn.classList.add('active');

            if (id === 'dashboard') loadStats();
            if (id === 'applications') loadApplications();
            if (id === 'fleet') loadFleet();
            if (id === 'violations') loadViolations();
            if (id === 'payments') loadFees();
            if (id === 'inspections') loadInspections();
            if (id === 'downloads') loadDownloads();
        }

        function closeProfileModal() {
            document.getElementById('profileModal').classList.add('hidden');
            setTimeout(cancelEditMode, 300); // Wait for fade out
        }

        function showProfileModal() {
            document.getElementById('profileModal').classList.remove('hidden');
            fetchProfile();
        }

        // --- Data Loading ---
        async function loadStats() {
            const data = await apiGet('get_dashboard_stats');
            if (data.ok) {
                document.getElementById('statPending').innerText = data.data.pending_apps;
                document.getElementById('statVehicles').innerText = data.data.active_vehicles;
                document.getElementById('statAlerts').innerText = data.data.compliance_alerts;
            }

            // Load AI Insights
            const dataAI = await apiGet('get_ai_insights');
            if (dataAI.ok) {
                const container = document.getElementById('aiInsightsContainer');
                const items = Array.isArray(dataAI.data) ? dataAI.data : [];
                if (!items.length) {
                    container.innerHTML = '<p class="text-slate-400 text-sm">No insights available.</p>';
                    return;
                }
                container.innerHTML = items.map(i => {
                    const type = (i.type || 'low').toString().toLowerCase();
                    const badge = type === 'high' ? 'bg-red-500/20 text-red-200' : (type === 'medium' ? 'bg-amber-500/20 text-amber-200' : 'bg-emerald-500/20 text-emerald-200');
                    const rp = Array.isArray(i.route_plan) ? i.route_plan.slice(0, 2) : [];
                    const rpHtml = rp.length ? ('<div class="mt-2 text-[11px] text-slate-300"><div class="font-bold text-slate-200 mb-1">Suggested by route</div>' + rp.map(r => '<div>' + escapeHtml((r.route_name || r.route_id || 'Route')) + ': +' + escapeHtml(r.suggested_extra_units || 0) + '</div>').join('') + '</div>') : '';
                    return `
                        <div class="bg-white/5 p-4 rounded-xl border border-white/10 hover:bg-white/10 transition cursor-default">
                            <div class="flex justify-between items-start gap-3 mb-2">
                                <h4 class="font-bold text-sm text-orange-100">${escapeHtml(i.title || '')}</h4>
                                <span class="text-[10px] px-2 py-1 rounded-full font-bold ${badge}">${escapeHtml(type.toUpperCase())}</span>
                            </div>
                            <p class="text-xs text-slate-300 leading-relaxed">${escapeHtml(i.desc || '')}</p>
                            ${rpHtml}
                        </div>
                    `;
                }).join('');
            }
        }

        async function loadFleet() {
            const data = await apiGet('get_fleet_status');
            if (data.ok) {
                const tbody = document.getElementById('fleetTable');
                if (data.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" class="p-8 text-center text-slate-400 italic">No vehicles found in your fleet.</td></tr>';
                    return;
                }
                window.quickRequestInspection = async function (plate) {
                    const sel = document.getElementById('plateSelect');
                    if (sel && plate) {
                        sel.value = plate;
                        const fd = new FormData();
                        fd.append('action', 'set_active_plate');
                        fd.append('plate_number', plate);
                        const r = await apiPost(fd);
                        if (!(r && r.ok)) {
                            toast(r && r.error ? r.error : 'Failed to set active plate', 'error');
                            return;
                        }
                    }
                    const appType = document.getElementById('appTypeSelect');
                    if (appType) {
                        appType.value = 'Vehicle Inspection';
                        toggleAppFields();
                    }
                    showSection('applications');
                    toast('Inspection request form prepared for ' + plate, 'success');
                };
                window.requestVehicleLink = async function (plate) {
                    const ok = confirm('Submit a link request for ' + plate + ' to your operator record?');
                    if (!ok) return;
                    const fd = new FormData();
                    fd.append('action', 'puv_request_vehicle_link');
                    fd.append('plate_number', plate);
                    const r = await apiPost(fd);
                    if (r && r.ok) {
                        toast(r.message || 'Submitted', 'success');
                    } else {
                        toast(r && (r.error || r.message) ? (r.error || r.message) : 'Failed', 'error');
                    }
                };
                tbody.innerHTML = data.data.map(v => `
                    <tr class="hover:bg-slate-50 group transition">
                        <td class="p-5 font-bold text-slate-700">${v.plate_number}</td>
                        <td class="p-5">
                            <span class="px-3 py-1 rounded-full text-xs font-bold ${v.status === 'Active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}">
                                ${v.status}
                            </span>
                        </td>
                        <td class="p-5 text-slate-600 text-sm">${v.inspection_status || 'N/A'}</td>
                        <td class="p-5 text-slate-500 text-xs">${v.inspection_last_date || '-'}</td>
                        <td class="p-5">
                            <div class="flex flex-wrap gap-3 items-center">
                                ${v.inspection_status && v.inspection_status === 'Passed'
                            ? '<span class="text-xs font-bold text-emerald-600">Compliant</span>'
                            : `<button type="button" onclick="quickRequestInspection('${v.plate_number}')" class="text-xs font-bold text-primary hover:text-primary-dark transition">Request Inspection</button>`
                        }
                                ${v.record_status && v.record_status === 'Linked'
                            ? '<span class="text-xs font-bold text-emerald-600">Linked</span>'
                            : `<button type="button" onclick="requestVehicleLink('${v.plate_number}')" class="text-xs font-bold text-slate-700 hover:text-slate-900 transition">Request Link</button>`
                        }
                            </div>
                        </td>
                    </tr>
                `).join('');
            }
        }

        async function loadApplications() {
            const tbody = document.getElementById('appsTable');
            if (!tbody) return;
            tbody.innerHTML = '<tr><td colspan="4" class="p-6 text-center text-slate-400 italic">Loading...</td></tr>';
            const data = await apiGet('get_applications');
            if (!data || !data.ok) {
                tbody.innerHTML = '<tr><td colspan="4" class="p-6 text-center text-slate-400 italic">Failed to load applications.</td></tr>';
                return;
            }
            const rows = Array.isArray(data.data) ? data.data : [];
            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="4" class="p-6 text-center text-slate-400 italic">No applications yet.</td></tr>';
                return;
            }
            tbody.innerHTML = rows.map(r => {
                const status = (r.status || 'Pending').toString();
                const badge = status === 'Approved' ? 'bg-emerald-100 text-emerald-700' : (status === 'Rejected' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700');
                const d = (r.created_at || '').toString().slice(0, 10);
                return `
                    <tr class="hover:bg-slate-50 transition">
                        <td class="p-4 text-xs text-slate-500">${escapeHtml(d || '-')}</td>
                        <td class="p-4 font-bold text-slate-700">${escapeHtml(r.plate_number || '')}</td>
                        <td class="p-4 text-sm text-slate-600">${escapeHtml(r.type || '')}</td>
                        <td class="p-4"><span class="px-3 py-1 rounded-full text-xs font-bold ${badge}">${escapeHtml(status)}</span></td>
                    </tr>
                `;
            }).join('');
        }

        async function loadViolations() {
            const tbody = document.getElementById('violationsTable');
            if (!tbody) return;
            tbody.innerHTML = '<tr><td colspan="6" class="p-6 text-center text-slate-400 italic">Loading...</td></tr>';
            const data = await apiGet('get_violations');
            if (!data || !data.ok || !data.data.length) {
                tbody.innerHTML = '<tr><td colspan="6" class="p-6 text-center text-slate-400 italic">No violations found.</td></tr>';
                return;
            }
            tbody.innerHTML = data.data.map(r => `
                <tr class="hover:bg-slate-50 transition">
                    <td class="p-4 text-xs text-slate-500">${escapeHtml(r.date || '-')}</td>
                    <td class="p-4 font-bold text-slate-700">${escapeHtml(r.ticket_no || '')}</td>
                    <td class="p-4 font-mono text-sm text-slate-600">${escapeHtml(r.plate || '')}</td>
                    <td class="p-4 text-sm text-slate-800">${escapeHtml(r.violation || '')}</td>
                    <td class="p-4 font-bold text-rose-600">${escapeHtml(r.amount || '0.00')}</td>
                    <td class="p-4"><span class="px-2 py-1 rounded text-[10px] font-bold uppercase ${r.status !== 'Paid' ? 'bg-red-100 text-red-600' : 'bg-green-100 text-green-600'}">${escapeHtml(r.status || '')}</span></td>
                </tr>
             `).join('');
        }

        async function loadFees() {
            const tbody = document.getElementById('feesTable');
            if (!tbody) return;
            tbody.innerHTML = '<tr><td colspan="6" class="p-6 text-center text-slate-400 italic">Loading...</td></tr>';
            const data = await apiGet('get_fees');
            if (!data || !data.ok || !data.data.length) {
                tbody.innerHTML = '<tr><td colspan="6" class="p-6 text-center text-slate-400 italic">No fees assessed.</td></tr>';
                return;
            }
            window.openPayModal = function (id) {
                document.getElementById('payFeeId').value = id;
                document.getElementById('paymentModal').classList.remove('hidden');
            };
            tbody.innerHTML = data.data.map(r => `
                <tr class="hover:bg-slate-50 transition">
                    <td class="p-4 text-xs text-slate-500">${escapeHtml(r.created_at || '-')}</td>
                    <td class="p-4 font-mono text-sm">${escapeHtml(r.plate_number || '-')}</td>
                    <td class="p-4 text-sm font-semibold">${escapeHtml(r.type || '')}</td>
                    <td class="p-4 font-bold text-slate-800">${escapeHtml(r.amount || '0.00')}</td>
                    <td class="p-4"><span class="px-2 py-1 rounded text-[10px] font-bold uppercase ${r.status === 'Pending' ? 'bg-amber-100 text-amber-600' : (r.status === 'Paid' ? 'bg-green-100 text-green-600' : 'bg-blue-100 text-blue-600')}">${escapeHtml(r.status || '')}</span></td>
                    <td class="p-4">
                        ${r.status === 'Pending'
                    ? `<button onclick="openPayModal(${r.id})" class="text-xs font-bold text-white bg-secondary px-3 py-1 rounded hover:bg-green-600 transition">Pay</button>`
                    : ''}
                    </td>
                </tr>
             `).join('');
        }

        async function loadInspections() {
            const tbody = document.getElementById('inspectionsTable');
            if (!tbody) return;
            tbody.innerHTML = '<tr><td colspan="4" class="p-8 text-center text-slate-400 italic">Loading...</td></tr>';
            const data = await apiGet('get_fleet_status');
            if (!data || !data.ok || !Array.isArray(data.data) || !data.data.length) {
                tbody.innerHTML = '<tr><td colspan="4" class="p-8 text-center text-slate-400 italic">No vehicles found.</td></tr>';
                return;
            }
            tbody.innerHTML = data.data.map(v => {
                const st = v.inspection_status || 'Pending';
                const last = v.inspection_last_date || '-';
                const action = st === 'Passed'
                    ? '<span class="text-xs font-bold text-emerald-600">Compliant</span>'
                    : `<button type="button" onclick="quickRequestInspection('${v.plate_number}')" class="text-xs font-bold text-primary hover:text-primary-dark transition">Request Inspection</button>`;
                return `
                    <tr class="hover:bg-slate-50 transition">
                        <td class="p-5 font-bold text-slate-700">${escapeHtml(v.plate_number || '')}</td>
                        <td class="p-5 text-slate-600 text-sm">${escapeHtml(st)}</td>
                        <td class="p-5 text-slate-500 text-xs">${escapeHtml(last)}</td>
                        <td class="p-5">${action}</td>
                    </tr>
                `;
            }).join('');
        }

        async function loadDownloads() {
            const container = document.getElementById('downloadsList');
            if (!container) return;
            container.innerHTML = '<div class="p-4 text-center text-slate-400 italic text-xs">Loading...</div>';
            const data = await apiGet('get_downloads');
            if (!data || !data.ok) {
                container.innerHTML = '<div class="p-4 text-center text-slate-400 italic text-xs">Failed to load downloads.</div>';
                return;
            }
            const items = Array.isArray(data.data) ? data.data : [];
            if (!items.length) {
                container.innerHTML = '<div class="p-4 text-center text-slate-400 italic text-xs">No downloads available yet.</div>';
                return;
            }
            container.innerHTML = items.map(it => {
                const title = escapeHtml(it.title || '');
                const meta = escapeHtml(it.meta || '');
                const href = (it.href || '').toString();
                const right = href ? `<a class="text-xs font-bold text-primary hover:text-primary-dark transition" target="_blank" rel="noopener" href="${escapeHtml(href)}">Download</a>` : `<span class="text-[10px] font-bold text-slate-400">${escapeHtml(it.value || '')}</span>`;
                return `
                    <div class="flex items-center justify-between gap-4 p-4 rounded-xl border border-slate-200 bg-slate-50">
                        <div>
                            <div class="text-sm font-bold text-slate-800">${title}</div>
                            <div class="text-xs text-slate-500 font-semibold mt-1">${meta}</div>
                        </div>
                        <div class="shrink-0">${right}</div>
                    </div>
                `;
            }).join('');
        }

        async function submitNewVehicle(e) {
            e.preventDefault();
            const btn = e.target.querySelector('button');
            const oldText = btn.innerText;
            btn.innerText = 'Submitting...'; btn.disabled = true;

            const formData = new FormData(e.target);
            formData.append('action', 'add_vehicle');

            const res = await apiPost(formData);
            btn.innerText = oldText; btn.disabled = false;

            if (res.ok) {
                toast(res.message, 'success');
                document.getElementById('addVehicleModal').classList.add('hidden');
                e.target.reset();
                loadFleet(); // or loadApplications primarily since it goes to pending apps
                loadApplications();
            } else {
                toast(res.error || 'Failed', 'error');
            }
        }

        function showAddVehicleModal() {
            document.getElementById('addVehicleModal').classList.remove('hidden');
        }

        function showOperatorRecordModal() {
            const modal = document.getElementById('operatorRecordModal');
            if (!modal) return;
            const t = document.getElementById('opRecType');
            const rn = document.getElementById('opRecRegisteredName');
            const n = document.getElementById('opRecName');
            const a = document.getElementById('opRecAddress');
            const c = document.getElementById('opRecContact');
            const coop = document.getElementById('opRecCoop');
            if (t && currentProfileData && currentProfileData.operator_type) t.value = currentProfileData.operator_type;
            if (rn && currentProfileData && currentProfileData.association_name && !rn.value) rn.value = currentProfileData.association_name;
            if (n && currentProfileData && currentProfileData.name && !n.value) n.value = currentProfileData.name;
            if (c && currentProfileData && currentProfileData.contact_info && !c.value) c.value = currentProfileData.contact_info;
            if (coop && currentProfileData && currentProfileData.association_name && !coop.value) coop.value = currentProfileData.association_name;
            modal.classList.remove('hidden');
        }

        async function submitOperatorRecord(e) {
            e.preventDefault();
            const btn = e.target.querySelector('button[type="submit"]');
            const oldText = btn ? btn.innerText : '';
            if (btn) { btn.innerText = 'Submitting...'; btn.disabled = true; }

            const fd = new FormData(e.target);
            fd.append('action', 'puv_submit_operator_record');
            const res = await apiPost(fd);

            if (btn) { btn.innerText = oldText; btn.disabled = false; }

            if (res && res.ok) {
                toast(res.message || 'Submitted', 'success');
                document.getElementById('operatorRecordModal').classList.add('hidden');
                e.target.reset();
            } else {
                toast((res && (res.error || res.message)) ? (res.error || res.message) : 'Failed', 'error');
            }
        }

        function closeDeclaredFleetModal() {
            const m = document.getElementById('declaredFleetModal');
            const b = document.getElementById('declaredFleetBody');
            if (b) b.innerHTML = '';
            if (m) m.classList.add('hidden');
        }

        async function generateDeclaredFleetPreview() {
            const modal = document.getElementById('declaredFleetModal');
            const body = document.getElementById('declaredFleetBody');
            if (!modal || !body) return;
            modal.classList.remove('hidden');
            body.innerHTML = `<div class="p-6 text-center text-slate-500 font-semibold">Generating…</div>`;

            const fd = new FormData();
            fd.append('action', 'puv_generate_declared_fleet');
            const res = await apiPost(fd);
            if (!res || !res.ok) {
                body.innerHTML = `<div class="p-6 text-center text-rose-600 font-semibold">${escapeHtml((res && (res.error || res.message)) ? (res.error || res.message) : 'Failed')}</div>`;
                return;
            }

            const token = res.token || '';
            const files = res.files || {};
            const pdfFile = files.pdf || '';
            const excelFile = files.excel || '';
            const rows = Array.isArray(res.rows) ? res.rows : [];
            const previewRows = rows.slice(0, 25);
            const op = res.operator || {};
            const sys = res.system || {};
            const summary = res.summary || {};
            const breakdown = summary.breakdown || {};

            const pdfUrl = pdfFile ? (appBaseUrl + '/admin/uploads/' + encodeURIComponent(String(pdfFile))) : '';
            const excelUrl = excelFile ? (appBaseUrl + '/admin/uploads/' + encodeURIComponent(String(excelFile))) : '';

            const breakdownLines = Object.keys(breakdown).sort((a, b) => {
                const av = Number(breakdown[a] || 0);
                const bv = Number(breakdown[b] || 0);
                if (bv !== av) return bv - av;
                return String(a).localeCompare(String(b));
            }).map((k) => `<div class="text-xs font-semibold text-slate-700">- ${escapeHtml(k)}: ${escapeHtml(breakdown[k])}</div>`).join('');

            body.innerHTML = `
              <div class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                  <div class="p-3 rounded-lg bg-slate-50 border border-slate-200">
                    <div class="font-black text-slate-900">${escapeHtml((sys.lgu_name || sys.name || '').toString()) || 'LGU PUV Management System'}</div>
                    <div class="mt-1 font-semibold text-slate-600">DECLARED FLEET REPORT</div>
                    <div class="mt-2 text-slate-700">
                      <div><span class="font-bold">Operator:</span> ${escapeHtml(op.name || '')}</div>
                      <div><span class="font-bold">Operator Type:</span> ${escapeHtml(op.type || '')}</div>
                      <div><span class="font-bold">Operator ID:</span> ${escapeHtml(op.code || op.id || '')}</div>
                    </div>
                  </div>
                  <div class="p-3 rounded-lg bg-slate-50 border border-slate-200">
                    <div class="font-black text-slate-900">Fleet Summary</div>
                    <div class="mt-1 text-xs font-semibold text-slate-600">Total Vehicles: ${escapeHtml(summary.total_vehicles || rows.length)}</div>
                    <div class="mt-2 space-y-1">${breakdownLines || `<div class="text-xs font-semibold text-slate-600">No breakdown data.</div>`}</div>
                  </div>
                </div>

                <div class="p-4 rounded-xl bg-slate-50 border border-slate-200">
                  <div class="flex flex-wrap gap-2 items-center">
                    ${pdfUrl ? `<a class="px-3 py-2 rounded-lg text-xs font-bold bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 transition" href="${escapeHtml(pdfUrl)}" target="_blank">Open PDF</a>` : ``}
                    ${excelUrl ? `<a class="px-3 py-2 rounded-lg text-xs font-bold bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 transition" href="${escapeHtml(excelUrl)}" target="_blank">Open Excel (CSV)</a>` : ``}
                  </div>
                  <label class="mt-3 flex items-start gap-2 text-xs font-semibold text-slate-700">
                    <input type="checkbox" class="mt-0.5 w-4 h-4" data-fleet-confirm="1">
                    <span>I reviewed the generated file and confirm it is correct.</span>
                  </label>
                  <div class="mt-3 flex flex-wrap gap-2">
                    <button type="button" class="px-3 py-2 rounded-lg text-xs font-bold bg-primary text-white hover:bg-orange-600 transition" data-fleet-upload="pdf" data-fleet-token="${escapeHtml(token)}" disabled>Upload PDF</button>
                    <button type="button" class="px-3 py-2 rounded-lg text-xs font-bold bg-slate-900 text-white hover:bg-black transition" data-fleet-upload="excel" data-fleet-token="${escapeHtml(token)}" disabled>Upload Excel</button>
                    <button type="button" class="px-3 py-2 rounded-lg text-xs font-bold bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 transition" onclick="closeDeclaredFleetModal()">Close</button>
                  </div>
                </div>

                <div class="text-xs font-bold text-slate-600">Previewing ${escapeHtml(previewRows.length)} of ${escapeHtml(rows.length)} vehicles</div>
                <div class="overflow-x-auto rounded-xl border border-slate-200">
                  <table class="min-w-full text-xs">
                    <thead class="bg-slate-50">
                      <tr class="text-left">
                        <th class="px-3 py-2 font-black">Plate</th>
                        <th class="px-3 py-2 font-black">Type</th>
                        <th class="px-3 py-2 font-black">Make</th>
                        <th class="px-3 py-2 font-black">Model</th>
                        <th class="px-3 py-2 font-black">Year</th>
                        <th class="px-3 py-2 font-black">Engine</th>
                        <th class="px-3 py-2 font-black">Chassis</th>
                        <th class="px-3 py-2 font-black">OR No</th>
                        <th class="px-3 py-2 font-black">CR No</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                      ${previewRows.map((r) => `
                        <tr>
                          <td class="px-3 py-2 font-bold">${escapeHtml(r.plate_number || '')}</td>
                          <td class="px-3 py-2">${escapeHtml(r.vehicle_type || '')}</td>
                          <td class="px-3 py-2">${escapeHtml(r.make || '')}</td>
                          <td class="px-3 py-2">${escapeHtml(r.model || '')}</td>
                          <td class="px-3 py-2">${escapeHtml(r.year_model || '')}</td>
                          <td class="px-3 py-2">${escapeHtml(r.engine_no || '')}</td>
                          <td class="px-3 py-2">${escapeHtml(r.chassis_no || '')}</td>
                          <td class="px-3 py-2">${escapeHtml(r.or_number || '')}</td>
                          <td class="px-3 py-2">${escapeHtml(r.cr_number || '')}</td>
                        </tr>
                      `).join('')}
                    </tbody>
                  </table>
                </div>
              </div>
            `;

            const confirmEl = body.querySelector('[data-fleet-confirm="1"]');
            const uploadBtns = Array.from(body.querySelectorAll('[data-fleet-upload]'));
            const setUploadEnabled = (enabled) => {
                uploadBtns.forEach((x) => {
                    const fmt = x.getAttribute('data-fleet-upload') || '';
                    if (fmt === 'pdf' && !pdfFile) { x.disabled = true; return; }
                    if (fmt === 'excel' && !excelFile) { x.disabled = true; return; }
                    x.disabled = !enabled;
                });
            };
            setUploadEnabled(false);
            if (confirmEl) confirmEl.addEventListener('change', () => setUploadEnabled(!!confirmEl.checked));

            uploadBtns.forEach((btnUp) => {
                btnUp.addEventListener('click', async () => {
                    const fmt = btnUp.getAttribute('data-fleet-upload') || 'pdf';
                    const tok = btnUp.getAttribute('data-fleet-token') || '';
                    const old = btnUp.innerText;
                    btnUp.disabled = true;
                    btnUp.innerText = 'Uploading…';
                    const fd2 = new FormData();
                    fd2.append('action', 'puv_generate_declared_fleet');
                    fd2.append('commit', '1');
                    fd2.append('token', tok);
                    fd2.append('format', fmt);
                    const res2 = await apiPost(fd2);
                    if (res2 && res2.ok) {
                        toast('Declared Fleet uploaded for review.', 'success');
                        btnUp.innerText = old;
                        closeDeclaredFleetModal();
                    } else {
                        toast((res2 && (res2.error || res2.message)) ? (res2.error || res2.message) : 'Upload failed', 'error');
                        btnUp.innerText = old;
                        btnUp.disabled = false;
                    }
                });
            });
        }

        function closeTransferRequestModal() {
            const m = document.getElementById('transferRequestModal');
            if (m) m.classList.add('hidden');
        }

        async function showTransferRequestModal() {
            const modal = document.getElementById('transferRequestModal');
            const sel = document.getElementById('transferVehicleSelect');
            const form = document.getElementById('transferRequestForm');
            if (!modal || !sel || !form) return;
            modal.classList.remove('hidden');
            sel.innerHTML = `<option value="">Loading…</option>`;
            const data = await apiGet('puv_get_owned_vehicles');
            if (!data || !data.ok) {
                sel.innerHTML = `<option value="">Failed to load vehicles</option>`;
                return;
            }
            const rows = Array.isArray(data.data) ? data.data : [];
            if (!rows.length) {
                sel.innerHTML = `<option value="">No linked vehicles</option>`;
                return;
            }
            sel.innerHTML = `<option value="">Select vehicle…</option>` + rows.map((r) => {
                const id = String(r.vehicle_id || '');
                const plate = String(r.plate_number || '');
                return `<option value="${escapeHtml(id)}">${escapeHtml(plate)}</option>`;
            }).join('');
        }

        async function submitTransferRequest(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSubmitTransfer');
            const old = btn ? btn.innerText : '';
            if (btn) { btn.disabled = true; btn.innerText = 'Submitting…'; }

            const fd = new FormData(e.target);
            fd.append('action', 'puv_create_transfer_request');
            const res = await apiPost(fd);

            if (btn) { btn.disabled = false; btn.innerText = old; }
            if (res && res.ok) {
                toast(res.message || 'Submitted', 'success');
                e.target.reset();
                closeTransferRequestModal();
            } else {
                toast((res && (res.error || res.message)) ? (res.error || res.message) : 'Failed', 'error');
            }
        }

        async function submitPayment(e) {
            e.preventDefault();
            const btn = e.target.querySelector('button');
            const oldText = btn.innerText;
            btn.innerText = 'Uploading...'; btn.disabled = true;

            const formData = new FormData(e.target);
            formData.append('action', 'upload_payment');

            const res = await apiPost(formData);
            btn.innerText = oldText; btn.disabled = false;

            if (res.ok) {
                toast(res.message, 'success');
                document.getElementById('paymentModal').classList.add('hidden');
                e.target.reset();
                loadFees();
            } else {
                toast(res.error || 'Failed', 'error');
            }
        }

        // Notifications Logic
        function toggleNotifications() {
            const el = document.getElementById('notifDropdown');
            el.classList.toggle('hidden');
            if (!el.classList.contains('hidden')) loadNotifications();
        }

        async function loadNotifications() {
            const list = document.getElementById('notifList');
            if (!list) return;
            list.innerHTML = '<div class="p-4 text-center text-xs text-slate-400">Loading...</div>';
            const data = await apiGet('get_notifications');
            if (!data || !data.ok || !data.data.length) {
                list.innerHTML = '<div class="p-4 text-center text-xs text-slate-400 italic">No new notifications</div>';
                document.getElementById('notifBadge').classList.add('hidden');
                return;
            }

            // Check unread
            const hasUnread = data.data.some(n => n.is_read == 0);
            if (hasUnread) document.getElementById('notifBadge').classList.remove('hidden');
            else document.getElementById('notifBadge').classList.add('hidden');

            list.innerHTML = data.data.map(n => `
                <div class="p-3 hover:bg-slate-50 transition cursor-pointer ${n.is_read == 0 ? 'bg-blue-50/30' : ''}" onclick="markRead(${n.id})">
                    <p class="text-xs font-bold text-slate-700">${escapeHtml(n.title)}</p>
                    <p class="text-[10px] text-slate-500 mt-1 line-clamp-2">${escapeHtml(n.message)}</p>
                    <p class="text-[9px] text-slate-400 mt-1 text-right">${escapeHtml(n.created_at)}</p>
                </div>
             `).join('');
        }

        async function markRead(id) {
            const fd = new FormData();
            fd.append('action', 'mark_notification_read');
            fd.append('id', id);
            await apiPost(fd);
            loadNotifications(); // Refresh list to clear highlight
        }

        // --- Profile Management ---
        let currentProfileData = {};

        async function fetchProfile() {
            const data = await apiGet('get_profile');
            if (data.ok) {
                currentProfileData = data.data;

                // Populate Header
                document.getElementById('profHeaderName').innerText = data.data.name || 'Unknown';
                document.getElementById('profHeaderAssoc').innerText = data.data.association_name || 'Individual Operator';
                const sidebarName = document.getElementById('sidebarName');
                const sidebarSub = document.getElementById('sidebarSub');
                if (sidebarName) sidebarName.textContent = data.data.name || 'Operator';
                if (sidebarSub) sidebarSub.textContent = data.data.plate_number ? ('Active: ' + data.data.plate_number) : 'View Profile';

                // Populate View Mode
                document.getElementById('viewName').innerText = data.data.name || '-';
                document.getElementById('viewEmail').innerText = data.data.email || '-';
                document.getElementById('viewContact').innerText = data.data.contact_info || '-';

                // Populate Edit Mode Inputs
                document.getElementById('editName').value = data.data.name || '';
                document.getElementById('editEmail').value = data.data.email || '';
                document.getElementById('editContact').value = data.data.contact_info || '';
            }
        }

        function enableEditMode() {
            document.getElementById('viewMode').classList.add('hidden');
            document.getElementById('editMode').classList.remove('hidden');
        }

        function cancelEditMode() {
            document.getElementById('editMode').classList.add('hidden');
            document.getElementById('viewMode').classList.remove('hidden');
            document.getElementById('editMode').reset();
            // Restore values
            if (currentProfileData) {
                document.getElementById('editName').value = currentProfileData.name || '';
                document.getElementById('editEmail').value = currentProfileData.email || '';
                document.getElementById('editContact').value = currentProfileData.contact_info || '';
            }
        }

        function promptPassword(e) {
            e.preventDefault();
            // Show modal immediately, validation happens on confirm
            document.getElementById('passwordConfirmModal').classList.remove('hidden');
        }

        async function confirmSaveProfile(e) {
            e.preventDefault();
            const currentPass = document.getElementById('currentPassConfirm').value;
            const newPass = document.getElementById('newPass').value;
            const confirmPass = document.getElementById('confirmPass').value;

            if (!currentPass) {
                toast('Please enter your current password.', 'warning');
                return;
            }

            if (newPass && newPass !== confirmPass) {
                toast('New passwords do not match.', 'error');
                return;
            }

            const formData = new FormData(document.getElementById('editMode'));
            formData.append('action', 'update_profile');
            formData.append('current_password', currentPass);
            formData.append('new_password', newPass);

            try {
                const data = await apiPost(formData);

                if (data.ok) {
                    toast('Profile updated successfully!', 'success');
                    document.getElementById('passwordConfirmModal').classList.add('hidden');
                    closeProfileModal();
                    fetchProfile();
                } else {
                    toast(data.error || 'Update failed', 'error');
                }
            } catch (err) {
                console.error(err);
                toast('An error occurred while updating profile.', 'error');
            }
        }

        // --- Application Submission & AI Check ---
        async function checkDocument(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                document.getElementById('docStatus').innerText = file.name;

                // Show AI Analysis UI
                const resultBox = document.getElementById('aiAnalysisResult');
                const previewText = document.getElementById('aiTextPreview');
                const statusBadge = document.getElementById('aiStatusBadge');
                const statusMsg = document.getElementById('aiStatusMsg');

                resultBox.classList.remove('hidden');
                previewText.innerText = 'Scanning document...';
                statusBadge.innerText = 'SCANNING';
                statusBadge.className = 'text-[10px] font-bold px-2 py-0.5 rounded-full bg-blue-100 text-blue-600 animate-pulse';
                statusMsg.innerText = '';

                try {
                    if (file.type === 'application/pdf') {
                        previewText.innerText = 'PDF uploaded. OCR preview is skipped in-browser.';
                        statusBadge.innerText = 'UPLOADED';
                        statusBadge.className = 'text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600';
                        statusMsg.className = 'mt-2 text-xs font-bold text-slate-600';
                        statusMsg.innerText = 'PDF will be submitted as an attachment.';
                        return;
                    }
                    const worker = await Tesseract.createWorker('eng');
                    const ret = await worker.recognize(file);
                    const text = ret.data.text.trim();

                    const cleanText = text.replace(/[^a-zA-Z0-9]/g, '');
                    const isReadable = text.length > 50 && cleanText.length > 20;

                    if (isReadable) {
                        previewText.innerText = text.substring(0, 150) + '...';
                        statusBadge.innerText = 'VERIFIED';
                        statusBadge.className = 'text-[10px] font-bold px-2 py-0.5 rounded-full bg-green-100 text-green-600';
                        statusMsg.className = 'mt-2 text-xs font-bold text-green-600';
                        statusMsg.innerText = '✓ Document appears readable.';
                    } else {
                        previewText.innerText = text.length > 0 ? text.substring(0, 100) + '...' : '[No text detected]';
                        statusBadge.innerText = 'UNCLEAR';
                        statusBadge.className = 'text-[10px] font-bold px-2 py-0.5 rounded-full bg-red-100 text-red-600';
                        statusMsg.className = 'mt-2 text-xs font-bold text-red-600';
                        statusMsg.innerText = '⚠ Warning: Document text is unclear or too short.';
                    }
                    await worker.terminate();
                } catch (err) {
                    console.error(err);
                    previewText.innerText = 'Error scanning document.';
                    statusBadge.innerText = 'ERROR';
                    statusBadge.className = 'text-[10px] font-bold px-2 py-0.5 rounded-full bg-red-100 text-red-600';
                }
            }
        }

        async function submitApp(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            formData.append('action', 'submit_application');

            try {
                const data = await apiPost(formData);
                if (data.ok) {
                    toast('Application submitted! Reference: ' + data.ref, 'success');
                    e.target.reset();
                    document.getElementById('aiAnalysisResult').classList.add('hidden');
                    document.getElementById('docStatus').innerText = 'Click or drag file here to upload';
                    loadStats();
                    loadApplications();
                } else {
                    toast(data.error || 'Submission failed', 'error');
                }
            } catch (err) {
                toast('Submission failed.', 'error');
            }
        }

        function openVerificationModal() {
            const modal = document.getElementById('verificationModal');
            if (!modal) return;
            modal.classList.remove('hidden');
            loadVerificationStatus(false, true);
        }

        function closeVerificationModal() {
            const modal = document.getElementById('verificationModal');
            if (!modal) return;
            modal.classList.add('hidden');
            const form = document.getElementById('verificationForm');
            if (form) form.reset();
        }

        function requiredDocsByType(type) {
            const t = String(type || 'Individual');
            if (t === 'Coop') {
                return [
                    { key: 'cda_registration', label: 'CDA Registration' },
                    { key: 'board_resolution', label: 'Board Resolution' },
                ];
            }
            if (t === 'Corp') {
                return [
                    { key: 'sec_registration', label: 'SEC Registration' },
                    { key: 'authority_to_operate', label: 'Authority to Operate' },
                ];
            }
            return [{ key: 'valid_id', label: 'Valid ID' }];
        }

        function setApprovalBanner(data) {
            const banner = document.getElementById('approvalBanner');
            if (!banner) return;
            const title = document.getElementById('approvalBannerTitle');
            const sub = document.getElementById('approvalBannerSub');
            const remarks = document.getElementById('approvalBannerRemarks');

            const status = data && data.approval_status ? String(data.approval_status) : '';
            const submittedAt = data && data.verification_submitted_at ? String(data.verification_submitted_at) : '';
            const r = data && data.approval_remarks ? String(data.approval_remarks) : '';

            if (status === 'Approved') {
                banner.classList.add('hidden');
                if (remarks) remarks.classList.add('hidden');
                return;
            }
            banner.classList.remove('hidden');

            if (status === 'Rejected') {
                if (title) title.textContent = 'Your operator account verification was rejected.';
                if (sub) sub.textContent = 'Please review remarks, re-upload documents, and submit again.';
            } else if (submittedAt) {
                if (title) title.textContent = 'Your verification is submitted and pending review.';
                if (sub) sub.textContent = 'Admin/LGU will review your documents. Some actions are restricted until approval.';
            } else {
                if (title) title.textContent = 'Your operator account is pending approval.';
                if (sub) sub.textContent = 'Upload your documents so the admin/LGU can verify your account.';
            }

            if (remarks) {
                if (r) {
                    remarks.textContent = r;
                    remarks.classList.remove('hidden');
                } else {
                    remarks.classList.add('hidden');
                }
            }
        }

        async function loadVerificationStatus(showToast = false, alsoRenderModal = false) {
            let data = null;
            try {
                data = await apiGet('get_verification');
            } catch (e) {
                data = null;
            }
            if (!data || !data.ok) {
                if (showToast) toast((data && data.error) ? data.error : 'Failed to load verification status', 'error');
                return;
            }
            setApprovalBanner(data.data || {});

            if (!alsoRenderModal) return;

            const statusBox = document.getElementById('verifStatusBox');
            const statusText = document.getElementById('verifStatusText');
            const remarksText = document.getElementById('verifRemarksText');
            const inputs = document.getElementById('verifInputs');

            const operatorType = (data.data && data.data.operator_type) ? String(data.data.operator_type) : 'Individual';
            const approvalStatus = (data.data && data.data.approval_status) ? String(data.data.approval_status) : 'Pending';
            const submittedAt = (data.data && data.data.verification_submitted_at) ? String(data.data.verification_submitted_at) : '';
            const approvalRemarks = (data.data && data.data.approval_remarks) ? String(data.data.approval_remarks) : '';
            const docs = Array.isArray(data.data && data.data.documents) ? data.data.documents : [];

            if (statusBox) statusBox.classList.remove('hidden');
            if (statusText) statusText.textContent = approvalStatus + (submittedAt ? (' • Submitted: ' + submittedAt.slice(0, 16)) : '');
            if (remarksText) remarksText.textContent = approvalRemarks ? ('Remarks: ' + approvalRemarks) : '';

            if (inputs) {
                const required = requiredDocsByType(operatorType);
                inputs.innerHTML = required.map(req => {
                    const existing = docs.find(d => String(d.doc_key || '') === req.key) || null;
                    const st = existing ? String(existing.status || 'Pending') : 'Missing';
                    const badge = st === 'Valid' ? 'bg-emerald-100 text-emerald-700' : (st === 'Invalid' ? 'bg-rose-100 text-rose-700' : (st === 'Pending' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600'));
                    const remarkLine = existing && existing.remarks ? ('<div class="mt-1 text-[11px] text-rose-700 font-semibold">Remark: ' + escapeHtml(existing.remarks) + '</div>') : '';
                    const reqAttr = (st === 'Valid') ? '' : 'required';
                    return `
                        <div class="p-4 rounded-xl border border-slate-200 bg-slate-50">
                            <div class="flex items-center justify-between gap-3">
                                <div class="text-sm font-bold text-slate-800">${escapeHtml(req.label)}</div>
                                <span class="px-2 py-1 rounded-full text-[10px] font-bold ${badge}">${escapeHtml(st)}</span>
                            </div>
                            ${remarkLine}
                            <div class="mt-3">
                                <input type="file" name="${escapeHtml(req.key)}" accept="image/*,application/pdf"
                                    class="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-primary-light file:text-primary hover:file:bg-orange-200" ${reqAttr}>
                            </div>
                        </div>
                    `;
                }).join('');
            }
        }

        async function submitVerificationDocs(e) {
            e.preventDefault();
            const btn = document.getElementById('btnVerifSubmit');
            const oldText = btn ? btn.textContent : '';
            if (btn) { btn.disabled = true; btn.textContent = 'Submitting...'; }
            try {
                const form = document.getElementById('verificationForm');
                const fd = new FormData(form);
                fd.append('action', 'upload_verification_docs');
                const res = await apiPost(fd);
                if (res && res.ok) {
                    toast('Documents submitted for review.', 'success');
                    closeVerificationModal();
                    await loadVerificationStatus(false, false);
                } else {
                    toast((res && res.error) ? res.error : 'Upload failed', 'error');
                }
            } catch (err) {
                toast('Upload failed', 'error');
            } finally {
                if (btn) { btn.disabled = false; btn.textContent = oldText || 'Submit'; }
            }
        }

        function toggleAppFields() {
            const type = document.getElementById('appTypeSelect').value;
            const routeField = document.getElementById('routeField');
            const dateField = document.getElementById('dateField');

            routeField.classList.add('hidden');
            dateField.classList.add('hidden');

            if (type === 'Franchise Endorsement') {
                routeField.classList.remove('hidden');
                loadRoutes(); // Ensure routes are loaded
            } else if (type === 'Vehicle Inspection') {
                dateField.classList.remove('hidden');
            }
        }

        let routesLoaded = false;
        async function loadRoutes() {
            if (routesLoaded) return;
            const sel = document.getElementById('routeSelect');
            if (!sel) return;
            
            const data = await apiGet('get_routes');
            if (data.ok && Array.isArray(data.data)) {
                sel.innerHTML = '<option value="">Select a Route...</option>';
                data.data.forEach(r => {
                    const opt = document.createElement('option');
                    opt.value = r.id;
                    opt.textContent = `${r.route_code} - ${r.route_name}`;
                    sel.appendChild(opt);
                });
                routesLoaded = true;
            } else {
                sel.innerHTML = '<option value="">Failed to load routes</option>';
            }
        }

        // Initial Load
        (async function init() {
            await initSession();
            await fetchProfile();
            await loadVerificationStatus(false, false);
            loadStats();
            loadApplications();
        })();
    </script>
</body>

</html>
