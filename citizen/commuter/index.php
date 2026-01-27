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
$userName = $_SESSION['name'] ?? 'Guest';
$userInitials = strtoupper(substr($userName, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <title>City Transport Hub</title>
    <link rel="icon" type="image/jpeg" href="images/logo.jpg">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/lucide@latest/dist/umd/lucide.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#f5f3ff', // violet-50
                            100: '#ede9fe', // violet-100
                            200: '#ddd6fe',
                            300: '#c4b5fd',
                            400: '#a78bfa',
                            500: '#8b5cf6', // violet-500
                            600: '#7c3aed', // violet-600
                            700: '#6d28d9',
                            800: '#5b21b6',
                            900: '#4c1d95',
                        },
                        accent: {
                            50: '#fdf4ff', // fuchsia-50
                            100: '#fae8ff',
                            200: '#f5d0fe',
                            300: '#f0abfc',
                            400: '#e879f9',
                            500: '#d946ef', // fuchsia-500
                            600: '#c026d3',
                        },
                        dark: {
                            900: '#0f172a',
                            800: '#1e293b',
                            700: '#334155',
                        }
                    },
                    boxShadow: {
                        'soft': '0 4px 20px -2px rgba(0, 0, 0, 0.05)',
                        'glow': '0 0 15px rgba(12, 141, 228, 0.3)',
                    }
                }
            }
        }
    </script>
    <style>
        body {
            background-color: #f8fafc;
            /* Dot pattern background */
            background-image: radial-gradient(#cbd5e1 1px, transparent 1px);
            background-size: 24px 24px;
        }

        .glass-panel {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.6);
        }

        .sidebar-active {
            background: linear-gradient(135deg, #7c3aed 0%, #c026d3 100%);
            color: white;
            box-shadow: 0 8px 16px -4px rgba(124, 58, 237, 0.4);
        }

        .sidebar-item:hover:not(.sidebar-active) {
            background-color: #f1f5f9;
            color: #0f172a;
        }

        /* Hide scrollbar but allow scroll */
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }

        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        .fade-enter {
            animation: fadeEnter 0.3s ease-out forwards;
        }

        @keyframes fadeEnter {
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

<body class="text-slate-600 h-screen overflow-hidden flex flex-col md:flex-row">

    <!-- DESKTOP SIDEBAR -->
    <aside class="hidden md:flex flex-col w-72 h-screen glass-panel border-r border-slate-200 z-50">
        <div class="p-8">
            <div class="flex items-center gap-3 mb-10">
                <div class="w-10 h-10 rounded-xl bg-brand-600 text-white flex items-center justify-center shadow-glow">
                    <i data-lucide="bus" class="w-6 h-6"></i>
                </div>
                <div>
                    <h1 class="font-bold text-xl text-slate-900 leading-none">CityLoop</h1>
                    <span class="text-[10px] font-bold text-brand-500 tracking-wider uppercase">Transport Hub</span>
                </div>
            </div>

            <nav class="space-y-2">
                <button onclick="nav('dashboard')" id="side-dashboard"
                    class="sidebar-active sidebar-item w-full flex items-center gap-4 px-4 py-3.5 rounded-2xl transition-all duration-300 group">
                    <i data-lucide="layout-grid" class="w-5 h-5"></i>
                    <span class="font-semibold text-sm">Dashboard</span>
                </button>
                <button onclick="nav('routes')" id="side-routes"
                    class="sidebar-item w-full flex items-center gap-4 px-4 py-3.5 rounded-2xl transition-all duration-300 text-slate-500 group">
                    <i data-lucide="map" class="w-5 h-5"></i>
                    <span class="font-semibold text-sm">Routes & Fares</span>
                </button>
                <button onclick="nav('terminals')" id="side-terminals"
                    class="sidebar-item w-full flex items-center gap-4 px-4 py-3.5 rounded-2xl transition-all duration-300 text-slate-500 group">
                    <i data-lucide="warehouse" class="w-5 h-5"></i>
                    <span class="font-semibold text-sm">Terminals</span>
                </button>
                <button onclick="nav('complaints')" id="side-complaints"
                    class="sidebar-item w-full flex items-center gap-4 px-4 py-3.5 rounded-2xl transition-all duration-300 text-slate-500 group">
                    <i data-lucide="message-square-warning" class="w-5 h-5"></i>
                    <span class="font-semibold text-sm">Help Center</span>
                </button>
            </nav>
        </div>

        <div class="mt-auto p-6 border-t border-slate-100">
            <?php if ($isLoggedIn): ?>
                <div class="bg-slate-50 p-4 rounded-2xl flex items-center gap-3 border border-slate-100">
                    <div
                        class="w-10 h-10 rounded-full bg-brand-100 text-brand-700 flex items-center justify-center font-bold text-sm">
                        <?= $userInitials ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-slate-900 truncate"><?= htmlspecialchars($userName) ?></p>
                        <p class="text-xs text-slate-400">Commuter</p>
                    </div>
                    <a href="logout.php" class="text-slate-400 hover:text-red-500 transition-colors">
                        <i data-lucide="log-out" class="w-5 h-5"></i>
                    </a>
                </div>
            <?php else: ?>
                <a href="../../gsm_login/index.php"
                    class="flex items-center justify-center gap-2 w-full bg-slate-900 text-white py-3 rounded-xl font-bold text-sm hover:bg-slate-800 transition-colors shadow-lg">
                    <span>Login / Sign Up</span>
                    <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </a>
            <?php endif; ?>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 h-screen overflow-y-auto overflow-x-hidden relative">
        <!-- Mobile Header -->
        <div
            class="md:hidden sticky top-0 z-40 glass-panel px-6 py-4 flex items-center justify-between border-b border-slate-200/50">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-brand-600 text-white flex items-center justify-center">
                    <i data-lucide="bus" class="w-4 h-4"></i>
                </div>
                <span class="font-bold text-slate-900">CityLoop</span>
            </div>
            <?php if ($isLoggedIn): ?>
                <div
                    class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-xs font-bold text-slate-600">
                    <?= $userInitials ?>
                </div>
            <?php else: ?>
                <a href="../../gsm_login/index.php" class="text-xs font-bold text-brand-600">Login</a>
            <?php endif; ?>
        </div>

        <div class="p-4 md:p-10 max-w-7xl mx-auto pb-24 md:pb-10">

            <!-- VIEW: DASHBOARD -->
            <div id="view-dashboard" class="fade-enter space-y-6">
                <!-- Welcome & Status Header -->
                <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
                    <div>
                        <div class="text-sm font-medium text-slate-400 mb-1" id="current-date">...</div>
                        <h2 class="text-3xl font-bold text-slate-900">
                            <span id="greeting">Hello</span>, <?= htmlspecialchars($userName) ?>
                        </h2>
                    </div>
                    <div
                        class="flex items-center gap-2 bg-white px-4 py-2 rounded-full shadow-soft border border-slate-100">
                        <span class="relative flex h-3 w-3">
                            <span
                                class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                        </span>
                        <span class="text-xs font-bold text-slate-600 uppercase tracking-wide">System Operational</span>
                    </div>
                </div>

                <!-- Widgets Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                    <!-- Weather/Crowd Widget -->
                    <div
                        class="md:col-span-2 bg-gradient-to-br from-brand-500 to-brand-700 rounded-3xl p-8 text-white shadow-lg relative overflow-hidden group">
                        <div
                            class="absolute top-0 right-0 p-8 opacity-10 transform group-hover:scale-110 transition-transform duration-700">
                            <i data-lucide="cloud-sun" class="w-40 h-40"></i>
                        </div>
                        <div class="relative z-10">
                            <h3 class="text-brand-100 font-medium text-sm uppercase tracking-wider mb-8">Commuter
                                Forecast</h3>
                            <div class="flex items-end gap-4 mb-2">
                                <span class="text-5xl font-bold" id="crowd-level">Normal</span>
                            </div>
                            <p class="text-brand-100 max-w-sm" id="forecast-desc">Traffic flow is currently smooth.
                                Expect moderate build-up around 5:00 PM.</p>
                        </div>
                    </div>

                    <!-- Quick Action Widget -->
                    <div
                        class="bg-white rounded-3xl p-6 shadow-soft border border-slate-100 flex flex-col justify-between">
                        <div>
                            <div
                                class="w-12 h-12 rounded-2xl bg-orange-100 text-orange-600 flex items-center justify-center mb-4">
                                <i data-lucide="alert-triangle" class="w-6 h-6"></i>
                            </div>
                            <h3 class="font-bold text-lg text-slate-900">Report Issue</h3>
                            <p class="text-sm text-slate-500 mt-2">Encountered a problem? Let us know immediately.</p>
                        </div>
                        <button onclick="nav('complaints')"
                            class="mt-6 w-full py-3 bg-slate-900 text-white rounded-xl font-bold text-sm hover:bg-slate-800 transition-colors">
                            File Report
                        </button>
                    </div>

                </div>

                <!-- Advisories Feed -->
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-bold text-lg text-slate-800">Live Advisories</h3>
                        <button onclick="loadAdvisories()"
                            class="text-slate-400 hover:text-brand-600 transition-colors">
                            <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                        </button>
                    </div>
                    <div id="advisories-feed" class="space-y-4">
                        <!-- Loading State -->
                        <div class="animate-pulse flex space-x-4 bg-white p-6 rounded-3xl border border-slate-100">
                            <div class="rounded-full bg-slate-200 h-10 w-10"></div>
                            <div class="flex-1 space-y-4 py-1">
                                <div class="h-4 bg-slate-200 rounded w-3/4"></div>
                                <div class="space-y-2">
                                    <div class="h-4 bg-slate-200 rounded"></div>
                                    <div class="h-4 bg-slate-200 rounded w-5/6"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- VIEW: ROUTES -->
            <div id="view-routes" class="hidden fade-enter space-y-6">
                <div class="bg-white rounded-3xl p-8 shadow-soft border border-slate-100">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                        <div>
                            <h2 class="text-2xl font-bold text-slate-900">Route Matrix</h2>
                            <p class="text-slate-500">Official fare guide and active routes.</p>
                        </div>
                        <div class="relative w-full md:w-auto">
                            <i data-lucide="search" class="absolute left-3 top-3 w-4 h-4 text-slate-400"></i>
                            <input type="text" placeholder="Search routes..."
                                class="w-full md:w-64 pl-10 pr-4 py-2.5 rounded-xl bg-slate-50 border-none text-sm font-medium focus:ring-2 focus:ring-brand-500/20 outline-none">
                        </div>
                    </div>

                    <!-- Mobile Card View -->
                    <div class="md:hidden space-y-4" id="routes-mobile-list">
                        <!-- Injected -->
                    </div>

                    <!-- Desktop Table View -->
                    <div class="hidden md:block overflow-hidden rounded-xl border border-slate-100">
                        <table class="w-full text-left">
                            <thead class="bg-slate-50 text-xs font-bold text-slate-500 uppercase">
                                <tr>
                                    <th class="px-6 py-4">Route Name</th>
                                    <th class="px-6 py-4">Origin / Destination</th>
                                    <th class="px-6 py-4">Fare</th>
                                    <th class="px-6 py-4 text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody id="routes-desktop-list" class="divide-y divide-slate-100 text-sm">
                                <!-- Injected -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- VIEW: TERMINALS -->
            <div id="view-terminals" class="hidden fade-enter">
                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-slate-900">Transport Terminals</h2>
                    <p class="text-slate-500">Find nearest loading zones and hubs.</p>
                </div>
                <div id="terminals-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <!-- Injected -->
                </div>
            </div>

            <!-- VIEW: COMPLAINTS -->
            <div id="view-complaints" class="hidden fade-enter max-w-3xl mx-auto">
                <div class="bg-white rounded-3xl shadow-soft border border-slate-100 overflow-hidden">
                    <div class="p-8 border-b border-slate-100 bg-slate-50/50">
                        <h2 class="text-2xl font-bold text-slate-900">Help Center</h2>
                        <p class="text-slate-500">Report incidents or track your ticket status.</p>
                    </div>

                    <div class="grid grid-cols-2 border-b border-slate-100">
                        <button onclick="toggleComplaintTab('new')" id="tab-btn-new"
                            class="py-4 text-sm font-bold text-brand-600 border-b-2 border-brand-600 bg-white">New
                            Report</button>
                        <button onclick="toggleComplaintTab('track')" id="tab-btn-track"
                            class="py-4 text-sm font-bold text-slate-500 bg-slate-50 hover:bg-slate-100">Track
                            Status</button>
                    </div>

                    <div class="p-8">
                        <!-- New Report Form -->
                        <form id="form-new-complaint" onsubmit="submitComplaint(event)" class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <label class="text-xs font-bold uppercase text-slate-500">Issue Type</label>
                                    <select name="type" required
                                        class="w-full px-4 py-3 rounded-xl bg-slate-50 border-transparent focus:bg-white focus:ring-2 focus:ring-brand-500/20 outline-none transition-all font-medium">
                                        <option value="">Select...</option>
                                        <option value="Overcharging">Overcharging</option>
                                        <option value="Reckless Driving">Reckless Driving</option>
                                        <option value="Refusal to Load">Refusal to Load</option>
                                        <option value="Discourteous Driver">Discourteous Driver</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div class="space-y-2">
                                    <label class="text-xs font-bold uppercase text-slate-500">Route (Optional)</label>
                                    <select name="route_id" id="complaint-route-select"
                                        class="w-full px-4 py-3 rounded-xl bg-slate-50 border-transparent focus:bg-white focus:ring-2 focus:ring-brand-500/20 outline-none transition-all font-medium">
                                        <option value="">Select Route...</option>
                                    </select>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label class="text-xs font-bold uppercase text-slate-500">What happened?</label>
                                <textarea name="description" rows="4" required
                                    class="w-full px-4 py-3 rounded-xl bg-slate-50 border-transparent focus:bg-white focus:ring-2 focus:ring-brand-500/20 outline-none transition-all resize-none"
                                    placeholder="Describe the incident details..."></textarea>
                            </div>

                            <div class="space-y-2">
                                <label class="text-xs font-bold uppercase text-slate-500">Evidence (Photo)</label>
                                <input type="file" name="media" accept="image/*"
                                    class="w-full text-sm text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100">
                            </div>

                            <button type="submit" id="btn-submit"
                                class="w-full py-4 bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-xl shadow-lg shadow-brand-500/30 transition-all flex items-center justify-center gap-2">
                                <span>Submit Report</span>
                                <i data-lucide="send" class="w-4 h-4"></i>
                            </button>
                        </form>

                        <!-- Track Form -->
                        <div id="form-track-complaint" class="hidden space-y-6 text-center py-8">
                            <div
                                class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto text-slate-400">
                                <i data-lucide="search" class="w-8 h-8"></i>
                            </div>
                            <h3 class="font-bold text-slate-900">Track Ticket</h3>
                            <div class="max-w-xs mx-auto space-y-4">
                                <input type="text" id="trackRef" placeholder="COM-XXXXXX"
                                    class="w-full px-4 py-3 text-center uppercase tracking-widest font-mono font-bold rounded-xl bg-slate-50 border border-slate-200 focus:border-brand-500 outline-none">
                                <button onclick="trackComplaint()"
                                    class="w-full py-3 bg-slate-900 text-white font-bold rounded-xl hover:bg-slate-800 transition-colors">Search</button>
                            </div>
                            <div id="trackResult"
                                class="hidden mt-8 text-left bg-slate-50 p-6 rounded-2xl border border-slate-200">
                                <!-- Result injected -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- MOBILE BOTTOM NAV -->
    <nav
        class="md:hidden fixed bottom-4 left-4 right-4 bg-slate-900/90 backdrop-blur-md text-white rounded-2xl p-2 z-50 shadow-2xl flex justify-around items-center border border-white/10">
        <button onclick="nav('dashboard')" class="nav-mobile-btn p-3 rounded-xl bg-white/10 text-brand-300"
            data-target="dashboard">
            <i data-lucide="layout-grid" class="w-5 h-5"></i>
        </button>
        <button onclick="nav('routes')" class="nav-mobile-btn p-3 rounded-xl text-slate-400 hover:bg-white/5"
            data-target="routes">
            <i data-lucide="map" class="w-5 h-5"></i>
        </button>
        <button onclick="nav('terminals')" class="nav-mobile-btn p-3 rounded-xl text-slate-400 hover:bg-white/5"
            data-target="terminals">
            <i data-lucide="warehouse" class="w-5 h-5"></i>
        </button>
        <button onclick="nav('complaints')" class="nav-mobile-btn p-3 rounded-xl text-slate-400 hover:bg-white/5"
            data-target="complaints">
            <i data-lucide="message-square-warning" class="w-5 h-5"></i>
        </button>
    </nav>

    <script>
        const API_URL = 'api.php';

        // Navigation Logic
        function nav(viewId) {
            // Update Views
            document.querySelectorAll('[id^="view-"]').forEach(el => el.classList.add('hidden'));
            document.getElementById('view-' + viewId).classList.remove('hidden');
            window.scrollTo({ top: 0 });

            // Update Desktop Sidebar
            document.querySelectorAll('.sidebar-item').forEach(el => {
                el.classList.remove('sidebar-active', 'text-white');
                el.classList.add('text-slate-500');
            });
            const activeSide = document.getElementById('side-' + viewId);
            activeSide.classList.remove('text-slate-500');
            activeSide.classList.add('sidebar-active');

            // Update Mobile Nav
            document.querySelectorAll('.nav-mobile-btn').forEach(el => {
                el.classList.remove('bg-white/10', 'text-brand-300');
                el.classList.add('text-slate-400');
            });
            const activeMob = document.querySelector(`.nav-mobile-btn[data-target="${viewId}"]`);
            if (activeMob) {
                activeMob.classList.remove('text-slate-400');
                activeMob.classList.add('bg-white/10', 'text-brand-300');
            }

            // Lazy Load
            if (viewId === 'routes') loadRoutes();
            if (viewId === 'terminals') loadTerminals();
        }

        // Utils
        function icons() {
            if (window.lucide) window.lucide.createIcons();
        }

        async function fetchAPI(action, params = {}) {
            const url = new URL(API_URL, window.location.href);
            url.searchParams.append('action', action);
            url.searchParams.append('_ts', Date.now());
            Object.keys(params).forEach(k => url.searchParams.append(k, params[k]));

            try {
                const res = await fetch(url);
                return await res.json();
            } catch (e) {
                console.error(e);
                return { ok: false, error: 'Network error' };
            }
        }

        // Dashboard Data
        async function loadAdvisories() {
            const feed = document.getElementById('advisories-feed');
            feed.innerHTML = '<div class="text-center py-4 text-slate-400 italic">Checking for updates...</div>';

            const data = await fetchAPI('get_advisories');
            if (data.ok && data.data.length) {
                feed.innerHTML = data.data.map(item => {
                    const isAlert = item.type === 'alert' || item.type === 'warning';
                    const icon = isAlert ? 'alert-triangle' : 'info';
                    const color = isAlert ? 'text-red-600 bg-red-50' : 'text-brand-600 bg-brand-50';
                    const border = isAlert ? 'border-red-100' : 'border-brand-100';
                    const time = new Date(item.posted_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

                    let suggestionHtml = '';
                    if (item.suggestion) {
                        suggestionHtml = `
                        <div class="mt-3 pt-3 border-t border-slate-100 flex items-start gap-2">
                            <div class="bg-brand-50 text-brand-600 p-1.5 rounded-lg shrink-0">
                                <i data-lucide="lightbulb" class="w-4 h-4"></i>
                            </div>
                            <div>
                                <span class="block text-[10px] font-bold uppercase text-brand-400 tracking-wider">AI Insight</span>
                                <p class="text-sm font-medium text-brand-800">${item.suggestion}</p>
                            </div>
                        </div>`;
                    }

                    return `
                    <div class="bg-white p-5 rounded-2xl border ${border} shadow-sm transition-transform hover:scale-[1.01]">
                        <div class="flex gap-4">
                            <div class="w-10 h-10 rounded-xl ${color} flex items-center justify-center shrink-0">
                                <i data-lucide="${icon}" class="w-5 h-5"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between items-start">
                                    <h4 class="font-bold text-slate-900 truncate pr-2">${item.title}</h4>
                                    <span class="text-[10px] font-bold text-slate-400 bg-slate-100 px-2 py-1 rounded-full">${time}</span>
                                </div>
                                <p class="text-sm text-slate-600 mt-1 leading-relaxed line-clamp-2">${item.content}</p>
                            </div>
                        </div>
                        ${suggestionHtml}
                    </div>`;
                }).join('');
            } else {
                feed.innerHTML = `
                <div class="bg-white p-8 rounded-3xl border border-dashed border-slate-200 text-center">
                    <div class="w-12 h-12 bg-slate-50 rounded-full flex items-center justify-center mx-auto text-slate-300 mb-3">
                        <i data-lucide="check" class="w-6 h-6"></i>
                    </div>
                    <p class="text-slate-500 font-medium">All systems normal.</p>
                </div>`;
            }
            icons();
        }

        async function loadRoutes() {
            const desktop = document.getElementById('routes-desktop-list');
            const mobile = document.getElementById('routes-mobile-list');
            if (desktop.getAttribute('data-loaded')) return;

            const data = await fetchAPI('get_routes');
            if (data.ok && data.data.length) {
                // Desktop
                desktop.innerHTML = data.data.map(r => `
                    <tr class="hover:bg-slate-50/80 transition-colors">
                        <td class="px-6 py-4 font-bold text-slate-800">${r.route_name}</td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-2 text-slate-500 text-xs font-medium uppercase tracking-wide">
                                <span>${r.origin}</span>
                                <i data-lucide="arrow-right" class="w-3 h-3"></i>
                                <span>${r.destination}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 font-mono font-bold text-slate-700">₱${parseFloat(r.fare).toFixed(2)}</td>
                        <td class="px-6 py-4 text-right"><span class="w-2 h-2 rounded-full bg-emerald-500 inline-block"></span></td>
                    </tr>
                `).join('');

                // Mobile
                mobile.innerHTML = data.data.map(r => `
                    <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100">
                        <div class="flex justify-between items-start mb-3">
                            <h4 class="font-bold text-slate-900">${r.route_name}</h4>
                            <span class="font-mono font-bold text-brand-600 bg-brand-50 px-2 py-1 rounded-lg text-sm">₱${parseFloat(r.fare).toFixed(2)}</span>
                        </div>
                        <div class="flex items-center gap-2 text-xs text-slate-500 bg-slate-50 p-3 rounded-xl">
                            <span class="font-medium">${r.origin}</span>
                            <i data-lucide="arrow-right" class="w-3 h-3"></i>
                            <span class="font-medium">${r.destination}</span>
                        </div>
                    </div>
                `).join('');

                desktop.setAttribute('data-loaded', 'true');

                // Populate Select
                const sel = document.getElementById('complaint-route-select');
                if (sel) {
                    sel.innerHTML = '<option value="">Select Route...</option>' + data.data.map(r => `<option value="${r.route_id}">${r.route_name}</option>`).join('');
                }
            }
            icons();
        }

        async function loadTerminals() {
            const grid = document.getElementById('terminals-grid');
            if (grid.getAttribute('data-loaded')) return;

            const data = await fetchAPI('get_terminals');
            if (data.ok && data.data.length) {
                grid.innerHTML = data.data.map(t => `
                    <div class="group bg-white p-6 rounded-3xl border border-slate-100 shadow-sm hover:shadow-lg hover:border-brand-200 transition-all duration-300 cursor-default">
                        <div class="flex justify-between items-start mb-6">
                            <div class="w-12 h-12 rounded-2xl bg-slate-50 text-slate-400 flex items-center justify-center group-hover:bg-brand-600 group-hover:text-white transition-colors duration-300">
                                <i data-lucide="warehouse" class="w-6 h-6"></i>
                            </div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 bg-slate-100 px-2 py-1 rounded-lg">${t.city || 'HUB'}</span>
                        </div>
                        <h3 class="font-bold text-lg text-slate-900 mb-1">${t.name}</h3>
                        <p class="text-sm text-slate-500 line-clamp-2 mb-4 h-10">${t.address || 'Location unavailable'}</p>
                        <div class="flex items-center gap-2 text-xs font-bold text-slate-400 border-t border-slate-100 pt-4">
                            <i data-lucide="bus" class="w-3 h-3"></i>
                            <span>Capacity: ${t.capacity || 'N/A'}</span>
                        </div>
                    </div>
                `).join('');
                grid.setAttribute('data-loaded', 'true');
            }
            icons();
        }

        // Complaints Logic
        function toggleComplaintTab(mode) {
            document.getElementById('form-new-complaint').classList.add('hidden');
            document.getElementById('form-track-complaint').classList.add('hidden');

            document.getElementById('tab-btn-new').className = 'py-4 text-sm font-bold text-slate-500 bg-slate-50 hover:bg-slate-100';
            document.getElementById('tab-btn-track').className = 'py-4 text-sm font-bold text-slate-500 bg-slate-50 hover:bg-slate-100';

            if (mode === 'new') {
                document.getElementById('form-new-complaint').classList.remove('hidden');
                document.getElementById('tab-btn-new').className = 'py-4 text-sm font-bold text-brand-600 border-b-2 border-brand-600 bg-white';
            } else {
                document.getElementById('form-track-complaint').classList.remove('hidden');
                document.getElementById('tab-btn-track').className = 'py-4 text-sm font-bold text-brand-600 border-b-2 border-brand-600 bg-white';
            }
        }

        async function submitComplaint(e) {
            e.preventDefault();
            const btn = document.getElementById('btn-submit');
            const orig = btn.innerHTML;
            btn.innerHTML = 'Sending...';
            btn.disabled = true;

            try {
                const fd = new FormData(e.target);
                fd.append('action', 'submit_complaint');
                const res = await fetch(API_URL, { method: 'POST', body: fd });
                const data = await res.json();
                if (data.ok) {
                    toggleComplaintTab('track');
                    document.getElementById('trackRef').value = data.ref_number;
                    trackComplaint();
                    e.target.reset();
                } else {
                    alert(data.error || 'Error submitting');
                }
            } catch (err) {
                alert('Connection Error');
            } finally {
                btn.innerHTML = orig;
                btn.disabled = false;
                icons();
            }
        }

        async function trackComplaint() {
            const ref = document.getElementById('trackRef').value;
            if (!ref) return;

            const resDiv = document.getElementById('trackResult');
            const data = await fetchAPI('get_complaint_status', { ref_number: ref });

            resDiv.classList.remove('hidden');
            if (data.ok) {
                const statusColor = data.data.status === 'Resolved' ? 'text-emerald-600' : 'text-brand-600';
                resDiv.innerHTML = `
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-2 h-2 rounded-full bg-current ${statusColor}"></div>
                        <span class="font-bold text-lg ${statusColor}">${data.data.status}</span>
                    </div>
                    <p class="text-sm text-slate-600 italic bg-white p-3 rounded-xl border border-slate-100">"${data.data.description}"</p>
                    <div class="mt-2 text-xs text-slate-400 font-bold text-right">${new Date(data.data.created_at).toLocaleDateString()}</div>
                `;
            } else {
                resDiv.innerHTML = `<p class="text-red-500 font-bold text-center">Ticket not found.</p>`;
            }
        }

        // Init
        window.addEventListener('DOMContentLoaded', () => {
            const h = new Date().getHours();
            const g = h < 12 ? 'Good Morning' : (h < 18 ? 'Good Afternoon' : 'Good Evening');
            document.getElementById('greeting').innerText = g;

            document.getElementById('current-date').innerText = new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });

            icons();
            loadAdvisories();
            loadForecast();
        });
    </script>
</body>

</html>