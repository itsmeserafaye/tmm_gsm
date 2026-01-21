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
    <script src="https://cdn.jsdelivr.net/npm/lucide@latest"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            900: '#0c4a6e',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .fade-in {
            animation: fadeIn 0.3s ease-in-out;
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

        .tab-active {
            border-bottom: 2px solid #0ea5e9;
            color: #0284c7;
            font-weight: 700;
        }

        .tab-inactive {
            color: #64748b;
            font-weight: 500;
        }

        .tab-inactive:hover {
            color: #334155;
        }
    </style>
</head>

<body class="min-h-screen font-sans text-slate-800 bg-[radial-gradient(circle_at_top,#e0f2fe_0%,#f8fafc_35%,#f1f5f9_100%)]">

    <!-- Header -->
    <header class="sticky top-0 z-50 border-b border-slate-200 bg-white/80 backdrop-blur shadow-sm">
        <div class="max-w-6xl mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div
                        class="w-10 h-10 bg-brand-600 rounded-lg flex items-center justify-center text-white shadow-lg">
                        <i data-lucide="bus" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-slate-900 leading-tight">City Transport</h1>
                        <p class="text-xs text-slate-500 font-medium">Public Information Portal</p>
                    </div>
                </div>
                <!-- Desktop Nav -->
                <nav class="hidden md:flex space-x-6 text-sm">
                    <button onclick="switchTab('home')" id="nav-home"
                        class="tab-active py-2 transition-colors">Advisories</button>
                    <button onclick="switchTab('routes')" id="nav-routes"
                        class="tab-inactive py-2 transition-colors">Routes & Fares</button>
                    <button onclick="switchTab('terminals')" id="nav-terminals"
                        class="tab-inactive py-2 transition-colors">Terminals</button>
                    <button onclick="switchTab('complaints')" id="nav-complaints"
                        class="tab-inactive py-2 transition-colors">File Complaint</button>
                </nav>

                <!-- Login/Logout -->
                <div class="hidden md:flex items-center gap-3">
                    <?php if ($isLoggedIn): ?>
                        <div class="text-xs text-slate-500 font-bold">Hi, <?= htmlspecialchars($userName) ?></div>
                        <a href="logout.php" class="text-xs font-bold text-slate-400 hover:text-slate-600">Logout</a>
                    <?php else: ?>
                        <a href="../../gsm_login/index.php"
                            class="px-4 py-2 bg-slate-900 text-white text-xs font-bold rounded-lg hover:bg-slate-800 transition shadow-sm flex items-center gap-2">
                            <i data-lucide="log-in" class="w-3 h-3"></i> Login
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Mobile Menu Button -->
                <button class="md:hidden text-slate-500"
                    onclick="document.getElementById('mobile-menu').classList.toggle('hidden')">
                    <i data-lucide="menu" class="w-6 h-6"></i>
                </button>
            </div>
            <!-- Mobile Menu -->
            <div id="mobile-menu" class="hidden md:hidden mt-4 pb-2 space-y-2 border-t border-slate-100 pt-2">
                <button onclick="switchTab('home')"
                    class="block w-full text-left px-4 py-2 text-sm font-medium hover:bg-slate-50 rounded-lg">Advisories</button>
                <button onclick="switchTab('routes')"
                    class="block w-full text-left px-4 py-2 text-sm font-medium hover:bg-slate-50 rounded-lg">Routes &
                    Fares</button>
                <button onclick="switchTab('terminals')"
                    class="block w-full text-left px-4 py-2 text-sm font-medium hover:bg-slate-50 rounded-lg">Terminals</button>
                <button onclick="switchTab('complaints')"
                    class="block w-full text-left px-4 py-2 text-sm font-medium hover:bg-slate-50 rounded-lg">File
                    Complaint</button>
                <div class="border-t border-slate-100 pt-2 mt-2">
                    <?php if ($isLoggedIn): ?>
                        <div class="px-4 py-2 text-xs font-bold text-slate-500">Hi, <?= htmlspecialchars($userName) ?></div>
                        <a href="logout.php"
                            class="block w-full text-left px-4 py-2 text-sm font-medium text-red-500 hover:bg-slate-50 rounded-lg">Logout</a>
                    <?php else: ?>
                        <a href="../../gsm_login/index.php"
                            class="block w-full text-left px-4 py-2 text-sm font-medium text-brand-600 hover:bg-slate-50 rounded-lg">Login
                            / Sign Up</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-6xl mx-auto px-4 py-8 pb-24">

        <!-- HOME / ADVISORIES -->
        <section id="tab-home" class="fade-in space-y-6">
            <div
                class="bg-gradient-to-r from-brand-600 to-brand-900 rounded-2xl p-8 text-white shadow-xl mb-8 relative overflow-hidden">
                <div class="relative z-10">
                    <h2 class="text-3xl font-bold mb-2">Welcome to City Transport</h2>
                    <p class="text-brand-100 max-w-lg">Official information source for routes, terminals, fares, and
                        service advisories. Plan your commute with confidence.</p>
                </div>
                <div class="absolute right-0 bottom-0 opacity-10 transform translate-x-10 translate-y-10">
                    <i data-lucide="map" class="w-64 h-64"></i>
                </div>
            </div>

            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                    <i data-lucide="bell" class="w-5 h-5 text-brand-600"></i>
                    Service Advisories
                </h3>
                <div class="flex items-center gap-3">
                    <span id="last-updated" class="text-xs font-medium text-slate-400">Loading...</span>
                    <span id="build-tag" class="hidden md:inline text-xs font-medium text-slate-400"></span>
                    <span
                        class="text-xs font-medium text-slate-500 bg-slate-100 px-2 py-1 rounded-full flex items-center gap-1">
                        <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                        Live Updates
                    </span>
                </div>
            </div>

            <div id="advisories-container" class="space-y-4">
                <div class="animate-pulse space-y-4">
                    <div class="h-24 bg-slate-200 rounded-xl"></div>
                    <div class="h-24 bg-slate-200 rounded-xl"></div>
                </div>
            </div>
        </section>

        <!-- ROUTES & FARES -->
        <section id="tab-routes" class="hidden fade-in space-y-6">
            <div class="text-center mb-8">
                <h2 class="text-2xl font-bold text-slate-900">Authorized Routes & Fares</h2>
                <p class="text-slate-500">Official fare matrix and route information.</p>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500 font-bold border-b border-slate-200">
                            <tr>
                                <th class="px-6 py-4">Route Name</th>
                                <th class="px-6 py-4">Origin &rarr; Destination</th>
                                <th class="px-6 py-4">Fare (Base)</th>
                                <th class="px-6 py-4">Status</th>
                            </tr>
                        </thead>
                        <tbody id="routes-table-body" class="divide-y divide-slate-100 text-sm">
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center text-slate-400">Loading routes...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- TERMINALS -->
        <section id="tab-terminals" class="hidden fade-in space-y-6">
            <div class="text-center mb-8">
                <h2 class="text-2xl font-bold text-slate-900">Transport Terminals</h2>
                <p class="text-slate-500">Find authorized loading and unloading zones.</p>
            </div>

            <div id="terminals-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Terminals injected here -->
                <div class="col-span-full text-center py-12 text-slate-400">Loading terminals...</div>
            </div>
        </section>

        <!-- COMPLAINTS -->
        <section id="tab-complaints" class="hidden fade-in space-y-6">
            <div class="max-w-2xl mx-auto">
                <div class="text-center mb-8">
                    <h2 class="text-2xl font-bold text-slate-900">Report an Issue</h2>
                    <p class="text-slate-500">Submit a complaint or track the status of an existing report.</p>
                </div>

                <div class="bg-white rounded-2xl shadow-lg border border-slate-100 overflow-hidden">
                    <div class="flex border-b border-slate-100">
                        <button onclick="toggleComplaintMode('new')" id="btn-mode-new"
                            class="flex-1 py-4 text-sm font-bold text-brand-600 border-b-2 border-brand-600 bg-brand-50/50">New
                            Complaint</button>
                        <button onclick="toggleComplaintMode('track')" id="btn-mode-track"
                            class="flex-1 py-4 text-sm font-bold text-slate-500 hover:text-slate-700 hover:bg-slate-50">Track
                            Status</button>
                        <?php if ($isLoggedIn): ?>
                            <button onclick="toggleComplaintMode('my')" id="btn-mode-my"
                                class="flex-1 py-4 text-sm font-bold text-slate-500 hover:text-slate-700 hover:bg-slate-50">My
                                Reports</button>
                        <?php endif; ?>
                    </div>

                    <!-- New Complaint Form -->
                    <div id="mode-new" class="p-6 md:p-8 space-y-6">
                        <form id="complaintForm" onsubmit="submitComplaint(event)" class="space-y-5">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Incident Type
                                        <span class="text-red-500">*</span></label>
                                    <select name="type" required
                                        class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none bg-white">
                                        <option value="">Select Type...</option>
                                        <option value="Overcharging">Overcharging</option>
                                        <option value="Reckless Driving">Reckless Driving</option>
                                        <option value="Refusal to Load">Refusal to Load</option>
                                        <option value="Discourteous Driver">Discourteous Driver</option>
                                        <option value="Unauthorized Trip">Unauthorized Trip</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Date &
                                        Time</label>
                                    <input type="datetime-local" name="datetime"
                                        class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Route / PUV
                                        Type</label>
                                    <select name="route_id" id="complaint-route-select"
                                        class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none bg-white">
                                        <option value="">Select Route...</option>
                                        <option value="Other">Other / Not Listed</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Plate Number
                                        (Optional)</label>
                                    <input type="text" name="plate_number" placeholder="ABC-1234"
                                        class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none uppercase">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Location of
                                    Incident</label>
                                <input type="text" name="location" placeholder="e.g. Near Central Terminal"
                                    class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Description <span
                                        class="text-red-500">*</span></label>
                                <textarea name="description" rows="3" required placeholder="Describe what happened..."
                                    class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none"></textarea>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Photo Evidence
                                    (Optional)</label>
                                <input type="file" name="media" accept="image/*"
                                    class="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100">
                            </div>

                            <div class="pt-4">
                                <button type="submit" id="btn-submit"
                                    class="w-full bg-brand-600 hover:bg-brand-700 text-white font-bold py-3 rounded-xl shadow-lg shadow-brand-500/30 transition-all transform active:scale-95 flex items-center justify-center gap-2">
                                    <i data-lucide="send" class="w-5 h-5"></i>
                                    Submit Complaint
                                </button>
                                <p class="text-center text-xs text-slate-400 mt-3">Your report helps us improve city
                                    transport. Personal info is optional.</p>
                            </div>
                        </form>
                    </div>

                    <!-- Track Status Form -->
                    <div id="mode-track" class="hidden p-6 md:p-8 space-y-6">
                        <div class="text-center">
                            <div
                                class="inline-flex items-center justify-center w-16 h-16 bg-slate-100 rounded-full mb-4">
                                <i data-lucide="search" class="w-8 h-8 text-slate-400"></i>
                            </div>
                            <h3 class="text-lg font-bold text-slate-900">Track Your Report</h3>
                            <p class="text-sm text-slate-500">Enter the reference number provided when you submitted
                                your complaint.</p>
                        </div>

                        <div class="max-w-md mx-auto space-y-4">
                            <div class="relative">
                                <input type="text" id="trackRef" placeholder="Reference No. (e.g. COM-X1Y2Z3)"
                                    class="w-full pl-12 pr-4 py-3 rounded-xl border border-slate-200 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none uppercase font-bold text-center tracking-widest">
                                <i data-lucide="hash" class="absolute left-4 top-3.5 text-slate-400 w-5 h-5"></i>
                            </div>
                            <button onclick="trackComplaint()"
                                class="w-full bg-slate-800 hover:bg-slate-900 text-white font-bold py-3 rounded-xl transition-colors">
                                Check Status
                            </button>
                        </div>

                        <div id="trackResult" class="hidden bg-slate-50 rounded-xl p-6 border border-slate-200 mt-6">
                            <div class="flex items-start gap-4">
                                <div id="statusIcon"
                                    class="w-10 h-10 rounded-full bg-brand-100 flex items-center justify-center text-brand-600 shrink-0">
                                    <i data-lucide="activity" class="w-5 h-5"></i>
                                </div>
                                <div>
                                    <div class="text-xs font-bold text-slate-500 uppercase mb-1">Current Status</div>
                                    <div id="trackStatus" class="text-xl font-black text-slate-900">Submitted</div>
                                    <div id="trackDate" class="text-sm text-slate-500 mt-1"></div>
                                    <div class="mt-4 pt-4 border-t border-slate-200 text-sm text-slate-600 italic"
                                        id="trackDesc"></div>
                                </div>
                            </div>
                        </div>
                        <div id="trackError"
                            class="hidden text-center text-red-500 font-bold bg-red-50 p-4 rounded-xl border border-red-100">
                        </div>
                    </div>

                    <!-- My Reports (Logged In Only) -->
                    <?php if ($isLoggedIn): ?>
                        <div id="mode-my" class="hidden p-6 md:p-8 space-y-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="font-bold text-lg text-slate-800">My Complaint History</h3>
                                <button onclick="loadMyComplaints()"
                                    class="text-xs font-bold text-brand-600 hover:text-brand-800 flex items-center gap-1">
                                    <i data-lucide="refresh-cw" class="w-3 h-3"></i> Refresh
                                </button>
                            </div>
                            <div id="my-complaints-list" class="space-y-3">
                                <div class="text-center py-8 text-slate-400 italic">Loading your reports...</div>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </section>

    </main>

    <!-- Bottom Mobile Nav -->
    <div
        class="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-slate-200 flex justify-around p-3 z-50 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)]">
        <button onclick="switchTab('home')" class="nav-btn-mobile flex flex-col items-center text-brand-600"
            data-target="home">
            <i data-lucide="home" class="w-5 h-5"></i>
            <span class="text-[10px] font-bold mt-1">Home</span>
        </button>
        <button onclick="switchTab('routes')" class="nav-btn-mobile flex flex-col items-center text-slate-400"
            data-target="routes">
            <i data-lucide="map-pin" class="w-5 h-5"></i>
            <span class="text-[10px] font-bold mt-1">Routes</span>
        </button>
        <button onclick="switchTab('terminals')" class="nav-btn-mobile flex flex-col items-center text-slate-400"
            data-target="terminals">
            <i data-lucide="warehouse" class="w-5 h-5"></i>
            <span class="text-[10px] font-bold mt-1">Terminals</span>
        </button>
        <button onclick="switchTab('complaints')" class="nav-btn-mobile flex flex-col items-center text-slate-400"
            data-target="complaints">
            <i data-lucide="message-square-warning" class="w-5 h-5"></i>
            <span class="text-[10px] font-bold mt-1">Report</span>
        </button>
    </div>

    <script>
        const API_URL = 'api.php';

        // Initialize Icons
        lucide.createIcons();

        // Initial Load
        loadAdvisories();
        populateRouteOptions();

        // Navigation
        function switchTab(tabId) {
            // Hide all sections
            document.querySelectorAll('section[id^="tab-"]').forEach(el => el.classList.add('hidden'));
            document.getElementById('tab-' + tabId).classList.remove('hidden');

            // Update Desktop Nav
            document.querySelectorAll('nav button').forEach(el => {
                el.classList.remove('tab-active');
                el.classList.add('tab-inactive');
            });
            const activeBtn = document.getElementById('nav-' + tabId);
            if (activeBtn) {
                activeBtn.classList.remove('tab-inactive');
                activeBtn.classList.add('tab-active');
            }

            // Update Mobile Nav
            document.querySelectorAll('.nav-btn-mobile').forEach(el => {
                el.classList.remove('text-brand-600');
                el.classList.add('text-slate-400');
            });
            const activeMobile = document.querySelector(`.nav-btn-mobile[data-target="${tabId}"]`);
            if (activeMobile) {
                activeMobile.classList.remove('text-slate-400');
                activeMobile.classList.add('text-brand-600');
            }

            // Lazy Load Data
            if (tabId === 'routes') loadRoutes();
            if (tabId === 'terminals') loadTerminals();

            // Close mobile menu if open
            document.getElementById('mobile-menu').classList.add('hidden');
        }

        // Complaint Mode Toggle
        function toggleComplaintMode(mode) {
            // Reset all
            document.getElementById('mode-new').classList.add('hidden');
            document.getElementById('mode-track').classList.add('hidden');
            if (document.getElementById('mode-my')) document.getElementById('mode-my').classList.add('hidden');

            document.getElementById('btn-mode-new').className = 'flex-1 py-4 text-sm font-bold text-slate-500 hover:text-slate-700 hover:bg-slate-50';
            document.getElementById('btn-mode-track').className = 'flex-1 py-4 text-sm font-bold text-slate-500 hover:text-slate-700 hover:bg-slate-50';
            if (document.getElementById('btn-mode-my')) document.getElementById('btn-mode-my').className = 'flex-1 py-4 text-sm font-bold text-slate-500 hover:text-slate-700 hover:bg-slate-50';

            // Activate selected
            document.getElementById('mode-' + mode).classList.remove('hidden');
            document.getElementById('btn-mode-' + mode).className = 'flex-1 py-4 text-sm font-bold text-brand-600 border-b-2 border-brand-600 bg-brand-50/50';

            if (mode === 'my') loadMyComplaints();
        }

        // Data Loading Functions
        let lastAdvisoryUpdate = null;

        async function loadAdvisories() {
            try {
                console.log('[Advisories] Fetching from API...');
                document.getElementById('last-updated').textContent = 'Updating...';
                const ts = Date.now();
                const res = await fetch(`${API_URL}?action=get_advisories&hours=24&_ts=${ts}`, { cache: 'no-store' });
                const data = await res.json();
                console.log('[Advisories] API Response:', data);

                const container = document.getElementById('advisories-container');
                const buildTag = document.getElementById('build-tag');
                if (buildTag && data.meta && data.meta.api_build) {
                    buildTag.textContent = `API ${new Date(data.meta.api_build).toLocaleString()}`;
                    buildTag.classList.remove('hidden');
                }

                if (data.ok && data.data.length > 0) {
                    console.log('[Advisories] Rendering', data.data.length, 'advisories');
                    container.innerHTML = data.data.map(item => {
                        let icon = 'bell';
                        if (item.type === 'alert') icon = 'alert-octagon';
                        else if (item.type === 'warning') icon = 'alert-triangle';
                        else icon = 'info';

                        const source = (item.source || '').toLowerCase();
                        const sourceLabel = source === 'predictive' ? 'Forecast' : (source === 'admin' ? 'Admin' : 'System');
                        const sourceClass = source === 'predictive' ? 'bg-brand-50 text-brand-700' : (source === 'admin' ? 'bg-slate-100 text-slate-600' : 'bg-emerald-50 text-emerald-700');
                        const title = item.title || 'Advisory';

                        return `
                        <div class="bg-white/90 backdrop-blur p-5 rounded-2xl shadow-sm border border-slate-200 hover:shadow-md transition-shadow">
                            <div class="flex items-start gap-4">
                                <div class="w-10 h-10 rounded-xl ${item.type === 'alert' ? 'bg-red-50 text-red-600' : (item.type === 'warning' ? 'bg-amber-50 text-amber-700' : 'bg-brand-50 text-brand-700')} flex items-center justify-center shrink-0">
                                    <i data-lucide="${icon}" class="w-5 h-5"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="flex justify-between items-start mb-2">
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span class="text-[10px] font-bold uppercase px-2 py-1 rounded ${sourceClass}">${sourceLabel}</span>
                                                <h4 class="font-bold text-lg text-slate-800 truncate">${title}</h4>
                                            </div>
                                        </div>
                                        <span class="text-[10px] font-bold uppercase px-2 py-1 rounded bg-slate-100 text-slate-500">${new Date(item.posted_at).toLocaleString()}</span>
                                    </div>
                                    <p class="text-slate-600 text-sm leading-relaxed">${item.content}</p>
                                </div>
                            </div>
                        </div>
                    `;
                    }).join('');
                    lucide.createIcons();

                    // Update last updated timestamp
                    lastAdvisoryUpdate = new Date();
                    updateLastUpdatedTime();
                    console.log('[Advisories] Render complete');
                } else if (!data.ok) {
                    console.error('[Advisories] API error:', data.error);
                    container.innerHTML = `<div class="text-center py-8 text-red-400 italic">Error: ${data.error || 'Failed to load advisories'}</div>`;
                } else {
                    console.warn('[Advisories] No data returned');
                    container.innerHTML = `<div class="text-center py-8 text-slate-400 italic">No active advisories at the moment.</div>`;
                }

                // Show debug errors if present
                if (data.debug_errors && data.debug_errors.length > 0) {
                    console.warn('[Advisories] Debug errors:', data.debug_errors);
                }
            } catch (e) {
                console.error('[Advisories] Exception:', e);
                document.getElementById('advisories-container').innerHTML = `<div class="text-center py-8 text-red-400 italic">Connection Failed. Please check network.</div>`;
            }
        }

        function updateLastUpdatedTime() {
            const el = document.getElementById('last-updated');
            if (!el || !lastAdvisoryUpdate) return;

            const now = new Date();
            const diff = Math.floor((now - lastAdvisoryUpdate) / 1000); // seconds

            let text = 'Just now';
            if (diff >= 60) {
                const mins = Math.floor(diff / 60);
                text = `${mins} min${mins > 1 ? 's' : ''} ago`;
            } else if (diff > 5) {
                text = `${diff} sec ago`;
            }

            el.textContent = `Updated ${text}`;
        }

        // Auto-refresh advisories every 5 minutes
        setInterval(loadAdvisories, 5 * 60 * 1000);

        // Update "last updated" time every 10 seconds
        setInterval(updateLastUpdatedTime, 10 * 1000);

        async function populateRouteOptions() {
            try {
                const ts = Date.now();
                const res = await fetch(`${API_URL}?action=get_routes&_ts=${ts}`, { cache: 'no-store' });
                const data = await res.json();
                const select = document.getElementById('complaint-route-select');

                if (data.ok && data.data.length > 0) {
                    // Keep the default options
                    let opts = '<option value="">Select Route...</option><option value="Other">Other / Not Listed</option>';
                    opts += data.data.map(r => `<option value="${r.route_id}">${r.route_name}</option>`).join('');
                    select.innerHTML = opts;
                } else if (!data.ok) {
                    console.error("Route fetch error:", data.error);
                }
            } catch (e) {
                console.error("Failed to load routes for dropdown", e);
            }
        }

        async function loadRoutes() {
            const tbody = document.getElementById('routes-table-body');
            if (tbody.getAttribute('data-loaded') === 'true') return;

            try {
                const ts = Date.now();
                const res = await fetch(`${API_URL}?action=get_routes&_ts=${ts}`, { cache: 'no-store' });
                const data = await res.json();

                if (data.ok && data.data.length > 0) {
                    tbody.innerHTML = data.data.map(r => `
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-4 font-bold text-brand-700">${r.route_name}</td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2 text-slate-600">
                                    <span class="font-medium">${r.origin}</span>
                                    <i data-lucide="arrow-right" class="w-3 h-3 text-slate-400"></i>
                                    <span class="font-medium">${r.destination}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 font-bold text-slate-800">₱${parseFloat(r.fare || 0).toFixed(2)}</td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-bold bg-emerald-100 text-emerald-700">Active</span>
                            </td>
                        </tr>
                    `).join('');
                    tbody.setAttribute('data-loaded', 'true');
                    lucide.createIcons();
                } else if (!data.ok) {
                    tbody.innerHTML = `<tr><td colspan="4" class="px-6 py-8 text-center text-red-400 italic">Error: ${data.error}</td></tr>`;
                } else {
                    tbody.innerHTML = `<tr><td colspan="4" class="px-6 py-8 text-center text-slate-400 italic">No routes found.</td></tr>`;
                }
            } catch (e) {
                tbody.innerHTML = `<tr><td colspan="4" class="px-6 py-8 text-center text-red-400">Failed to load routes.</td></tr>`;
            }
        }

        async function loadTerminals() {
            const grid = document.getElementById('terminals-grid');
            if (grid.getAttribute('data-loaded') === 'true') return;

            try {
                const ts = Date.now();
                const res = await fetch(`${API_URL}?action=get_terminals&_ts=${ts}`, { cache: 'no-store' });
                const data = await res.json();

                if (data.ok && data.data.length > 0) {
                    grid.innerHTML = data.data.map(t => `
                        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 hover:border-brand-200 transition-colors group">
                            <div class="flex items-start justify-between mb-4">
                                <div class="p-3 bg-slate-50 rounded-lg group-hover:bg-brand-50 transition-colors">
                                    <i data-lucide="warehouse" class="w-6 h-6 text-slate-400 group-hover:text-brand-500"></i>
                                </div>
                                <span class="text-xs font-bold px-2 py-1 bg-slate-100 rounded text-slate-500">${t.city || 'City'}</span>
                            </div>
                            <h4 class="font-bold text-lg text-slate-900 mb-1">${t.name}</h4>
                            <p class="text-sm text-slate-500 flex items-center gap-1 mb-4">
                                <i data-lucide="map-pin" class="w-3 h-3"></i> ${t.address || 'No address provided'}
                            </p>
                            <div class="pt-4 border-t border-slate-100 flex justify-between items-center text-xs">
                                <span class="text-slate-400">Capacity</span>
                                <span class="font-bold text-slate-700">${t.capacity || '-'} Vehicles</span>
                            </div>
                        </div>
                    `).join('');
                    grid.setAttribute('data-loaded', 'true');
                    lucide.createIcons();
                } else if (!data.ok) {
                    grid.innerHTML = `<div class="col-span-full text-center py-12 text-red-400 italic">Error: ${data.error}</div>`;
                } else {
                    grid.innerHTML = `<div class="col-span-full text-center py-12 text-slate-400 italic">No terminals found.</div>`;
                }
            } catch (e) {
                console.error(e);
                grid.innerHTML = `<div class="col-span-full text-center py-12 text-red-400 italic">Failed to load terminals.</div>`;
            }
        }

        async function submitComplaint(e) {
            e.preventDefault();
            const btn = document.getElementById('btn-submit');
            const originalContent = btn.innerHTML;
            btn.innerHTML = '<i data-lucide="loader-2" class="w-5 h-5 animate-spin"></i> Submitting...';
            btn.disabled = true;
            lucide.createIcons();

            try {
                const formData = new FormData(e.target);
                formData.append('action', 'submit_complaint');

                const res = await fetch(API_URL, { method: 'POST', body: formData });
                const data = await res.json();

                if (data.ok) {
                    // Switch to track mode and show result
                    toggleComplaintMode('track');
                    document.getElementById('trackRef').value = data.ref_number;
                    trackComplaint();
                    e.target.reset();
                    alert(`Complaint Submitted Successfully!\nReference No: ${data.ref_number}`);
                } else {
                    alert('Error: ' + (data.error || 'Submission failed'));
                }
            } catch (err) {
                alert('Connection error. Please try again.');
            } finally {
                btn.innerHTML = originalContent;
                btn.disabled = false;
                lucide.createIcons();
            }
        }

        async function trackComplaint() {
            const ref = document.getElementById('trackRef').value.trim();
            if (!ref) return;

            const resultBox = document.getElementById('trackResult');
            const errorBox = document.getElementById('trackError');

            try {
                const res = await fetch(`${API_URL}?action=get_complaint_status&ref_number=${ref}`);
                const data = await res.json();

                if (data.ok) {
                    errorBox.classList.add('hidden');
                    resultBox.classList.remove('hidden');

                    document.getElementById('trackStatus').innerText = data.data.status;
                    document.getElementById('trackDate').innerText = 'Reported on: ' + new Date(data.data.created_at).toLocaleString();
                    document.getElementById('trackDesc').innerText = data.data.description ? data.data.description.substring(0, 100) + '...' : '';

                    // Color code status
                    const statusColors = {
                        'Submitted': 'bg-slate-100 text-slate-600',
                        'Under Review': 'bg-amber-100 text-amber-700',
                        'Resolved': 'bg-emerald-100 text-emerald-700',
                        'Dismissed': 'bg-red-100 text-red-700'
                    };
                    const colorClass = statusColors[data.data.status] || statusColors['Submitted'];
                    const iconBox = document.getElementById('statusIcon');
                    iconBox.className = `w-10 h-10 rounded-full flex items-center justify-center shrink-0 ${colorClass}`;
                } else {
                    resultBox.classList.add('hidden');
                    errorBox.innerText = data.error || 'Complaint not found';
                    errorBox.classList.remove('hidden');
                }
            } catch (err) {
                console.error(err);
            }
        }

        async function loadMyComplaints() {
            const container = document.getElementById('my-complaints-list');
            container.innerHTML = `<div class="text-center py-8 text-slate-400 italic"><i data-lucide="loader-2" class="w-5 h-5 animate-spin mx-auto mb-2"></i> Loading...</div>`;
            lucide.createIcons();

            try {
                const res = await fetch(`${API_URL}?action=get_my_complaints`);
                const data = await res.json();

                if (data.ok && data.data.length > 0) {
                    container.innerHTML = data.data.map(c => {
                        const statusColors = {
                            'Submitted': 'bg-slate-100 text-slate-600',
                            'Under Review': 'bg-amber-100 text-amber-700',
                            'Resolved': 'bg-emerald-100 text-emerald-700',
                            'Dismissed': 'bg-red-100 text-red-700'
                        };
                        const colorClass = statusColors[c.status] || statusColors['Submitted'];

                        return `
                        <div class="bg-slate-50 rounded-xl p-4 border border-slate-200">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <div class="text-xs font-bold text-slate-400 uppercase">Ref: ${c.ref_number}</div>
                                    <div class="font-bold text-slate-800">${c.complaint_type}</div>
                                </div>
                                <span class="px-2 py-1 rounded text-[10px] font-bold uppercase ${colorClass}">${c.status}</span>
                            </div>
                            <p class="text-sm text-slate-600 line-clamp-2">${c.description}</p>
                            <div class="mt-2 text-xs text-slate-400 text-right">${new Date(c.created_at).toLocaleDateString()}</div>
                        </div>
                        `;
                    }).join('');
                } else {
                    container.innerHTML = `<div class="text-center py-8 text-slate-400 italic">No reports found.</div>`;
                }
            } catch (e) {
                container.innerHTML = `<div class="text-center py-8 text-red-400 italic">Failed to load reports.</div>`;
            }
        }
    </script>
</body>

</html>
