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
    <script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
    
    <style>
        body { font-family: 'Inter', sans-serif; }
        .sidebar-link.active {
            background: linear-gradient(90deg, rgba(249,115,22,0.1) 0%, transparent 100%);
            border-left: 3px solid #f97316;
            color: #f97316;
        }
        .sidebar-link:hover:not(.active) {
            background-color: #f8fafc;
            color: #334155;
        }
        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        
        .animate-fade-in { animation: fadeIn 0.3s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex overflow-hidden">

    <!-- SIDEBAR -->
    <aside class="w-64 bg-white border-r border-slate-200 hidden md:flex flex-col z-20 shadow-soft">
        <div class="p-6 flex items-center gap-3 border-b border-slate-100">
            <img src="images/logo.jpg" alt="Logo" class="w-10 h-10 rounded-xl shadow-sm object-cover ring-2 ring-slate-100">
            <div>
                <h1 class="text-lg font-bold tracking-tight text-slate-900">Operator<span class="text-primary">Portal</span></h1>
                <p class="text-[10px] text-slate-500 font-medium uppercase tracking-wider">Management</p>
            </div>
        </div>

        <nav class="flex-1 p-4 space-y-1 overflow-y-auto">
            <button onclick="showSection('dashboard')" id="nav-dashboard" class="sidebar-link active w-full flex items-center gap-3 px-4 py-3 text-sm font-medium text-slate-600 rounded-r-lg transition-all duration-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                Dashboard
            </button>
            <button onclick="showSection('applications')" id="nav-applications" class="sidebar-link w-full flex items-center gap-3 px-4 py-3 text-sm font-medium text-slate-600 rounded-r-lg transition-all duration-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                Applications
            </button>
            <button onclick="showSection('fleet')" id="nav-fleet" class="sidebar-link w-full flex items-center gap-3 px-4 py-3 text-sm font-medium text-slate-600 rounded-r-lg transition-all duration-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                My Fleet
            </button>
        </nav>

        <div class="p-4 border-t border-slate-100">
            <div class="bg-slate-50 p-3 rounded-xl flex items-center gap-3 cursor-pointer hover:bg-slate-100 transition" onclick="showProfileModal()">
                <div class="w-10 h-10 rounded-full overflow-hidden border-2 border-white shadow-sm">
                    <img src="https://ui-avatars.com/api/?name=Operator+User&background=random" alt="Profile">
                </div>
                <div class="overflow-hidden">
                    <p class="text-sm font-bold text-slate-800 truncate">Operator User</p>
                    <p class="text-xs text-slate-500 truncate">View Profile</p>
                </div>
            </div>
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
            <button onclick="document.querySelector('aside').classList.toggle('hidden'); document.querySelector('aside').classList.toggle('absolute'); document.querySelector('aside').classList.toggle('h-full');" class="text-slate-500">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
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
                        <div class="hidden md:block">
                            <span class="text-xs font-medium bg-white px-3 py-1 rounded-full border border-slate-200 text-slate-500 shadow-sm">
                                <span class="w-2 h-2 bg-green-500 rounded-full inline-block mr-1"></span> System Operational
                            </span>
                        </div>
                    </div>

                    <!-- KPI Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-soft hover:shadow-lg transition group relative overflow-hidden">
                            <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition">
                                <svg class="w-24 h-24 text-primary" fill="currentColor" viewBox="0 0 20 20"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z" /><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd" /></svg>
                            </div>
                            <p class="text-sm font-semibold text-slate-400 uppercase tracking-wider">Pending Applications</p>
                            <h3 class="text-4xl font-bold text-slate-800 mt-2" id="statPending">--</h3>
                            <div class="mt-4 flex items-center text-xs font-medium text-orange-600 bg-orange-50 w-fit px-2 py-1 rounded-lg">
                                <span>Requires Action</span>
                            </div>
                        </div>

                        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-soft hover:shadow-lg transition group relative overflow-hidden">
                            <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition">
                                <svg class="w-24 h-24 text-secondary" fill="currentColor" viewBox="0 0 20 20"><path d="M8 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM15 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z" /><path d="M3 4a1 1 0 00-1 1v10a1 1 0 001 1h1.05a2.5 2.5 0 014.9 0H10a1 1 0 001-1V5a1 1 0 00-1-1H3zM14 7a1 1 0 00-1 1v6.05A2.5 2.5 0 0115.95 16H17a1 1 0 001-1v-5a1 1 0 00-.293-.707l-2-2A1 1 0 0015 7h-1z" /></svg>
                            </div>
                            <p class="text-sm font-semibold text-slate-400 uppercase tracking-wider">Active Fleet</p>
                            <h3 class="text-4xl font-bold text-slate-800 mt-2" id="statVehicles">--</h3>
                            <div class="mt-4 flex items-center text-xs font-medium text-green-600 bg-green-50 w-fit px-2 py-1 rounded-lg">
                                <span>On the Road</span>
                            </div>
                        </div>

                        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-soft hover:shadow-lg transition group relative overflow-hidden">
                            <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition">
                                <svg class="w-24 h-24 text-red-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                            </div>
                            <p class="text-sm font-semibold text-slate-400 uppercase tracking-wider">Compliance Alerts</p>
                            <h3 class="text-4xl font-bold text-slate-800 mt-2" id="statAlerts">--</h3>
                            <div class="mt-4 flex items-center text-xs font-medium text-red-600 bg-red-50 w-fit px-2 py-1 rounded-lg">
                                <span>Needs Attention</span>
                            </div>
                        </div>
                    </div>

                    <!-- AI Insights Feed -->
                    <div class="bg-gradient-to-br from-slate-900 to-slate-800 rounded-2xl p-6 md:p-8 text-white shadow-lg relative overflow-hidden">
                        <div class="relative z-10">
                            <div class="flex items-center justify-between mb-6">
                                <div class="flex items-center space-x-3">
                                    <div class="p-2 bg-white/10 rounded-lg backdrop-blur-sm">
                                        <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-bold">AI Demand & Dispatch Insights</h3>
                                        <p class="text-xs text-slate-400">Real-time forecasting engine</p>
                                    </div>
                                </div>
                                <span class="bg-primary/20 text-primary text-xs font-bold px-3 py-1 rounded-full border border-primary/20">LIVE</span>
                            </div>
                            
                            <div id="aiInsightsContainer" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <p class="text-slate-400 text-sm">Loading insights...</p>
                            </div>
                        </div>
                        
                        <!-- Abstract shapes -->
                        <div class="absolute -top-24 -right-24 w-64 h-64 bg-primary rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob"></div>
                        <div class="absolute -bottom-24 -left-24 w-64 h-64 bg-secondary rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-2000"></div>
                    </div>
                </section>

                <!-- APPLICATIONS -->
                <section id="applications" class="hidden space-y-8">
                    <div>
                        <h2 class="text-2xl font-bold text-slate-900">New Application</h2>
                        <p class="text-slate-500 text-sm">Submit documents for franchises, inspections, or terminal enrollment.</p>
                    </div>

                    <div class="bg-white rounded-2xl shadow-soft border border-slate-100 overflow-hidden">
                        <div class="p-1 bg-gradient-to-r from-primary to-secondary"></div>
                        <div class="p-6 md:p-8">
                            <form id="appForm" class="space-y-6" onsubmit="submitApp(event)">
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="space-y-2">
                                        <label class="block text-sm font-semibold text-slate-700">Application Type</label>
                                        <div class="relative">
                                            <select name="type" class="w-full pl-4 pr-10 py-3 bg-slate-50 rounded-xl border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-primary outline-none transition appearance-none" required>
                                                <option value="">Select Type...</option>
                                                <option value="Franchise Endorsement">Franchise Endorsement</option>
                                                <option value="Vehicle Inspection">Vehicle Inspection Request</option>
                                                <option value="Terminal Enrollment">Terminal Enrollment</option>
                                            </select>
                                            <div class="absolute right-3 top-3.5 pointer-events-none text-slate-400">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- AI Document Check -->
                                <div class="bg-blue-50/50 rounded-xl border border-blue-100 p-6">
                                    <div class="flex items-start gap-4">
                                        <div class="p-3 bg-blue-100 text-blue-600 rounded-lg shrink-0">
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                        </div>
                                        <div class="flex-1">
                                            <h4 class="text-sm font-bold text-blue-900">AI Document Pre-Check</h4>
                                            <p class="text-xs text-blue-700 mt-1 mb-4">Upload your OR/CR or relevant permits. Our AI will verify readability instantly.</p>
                                            
                                            <div class="relative group">
                                                <input type="file" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" accept="image/*" onchange="checkDocument(this)">
                                                <div class="border-2 border-dashed border-blue-200 rounded-xl p-6 text-center bg-white group-hover:border-blue-400 transition">
                                                    <p class="text-sm font-medium text-slate-600" id="docStatus">Click or drag file here to upload</p>
                                                    <p class="text-xs text-slate-400 mt-1">Supports JPG, PNG</p>
                                                </div>
                                            </div>

                                            <div id="aiAnalysisResult" class="mt-4 hidden animate-fade-in">
                                                <div class="bg-white p-4 rounded-xl border border-blue-100 shadow-sm">
                                                    <div class="flex justify-between items-center mb-2">
                                                        <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Analysis Result</span>
                                                        <span id="aiStatusBadge" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-500">PENDING</span>
                                                    </div>
                                                    <p id="aiTextPreview" class="text-sm text-slate-600 font-mono bg-slate-50 p-3 rounded-lg border border-slate-100 line-clamp-3">Scanning...</p>
                                                    <p class="mt-2 text-xs font-bold" id="aiStatusMsg"></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <label class="block text-sm font-semibold text-slate-700">Additional Notes</label>
                                    <textarea name="notes" rows="4" class="w-full p-4 bg-slate-50 rounded-xl border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-primary outline-none transition" placeholder="Any specific details about your request..."></textarea>
                                </div>

                                <div class="flex justify-end pt-4">
                                    <button type="submit" class="bg-gradient-to-r from-primary to-primary-dark text-white px-8 py-3 rounded-xl font-bold shadow-lg hover:shadow-orange-500/30 transform hover:-translate-y-0.5 transition-all duration-200">Submit Application</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </section>

                <!-- FLEET -->
                <section id="fleet" class="hidden space-y-8">
                    <div>
                        <h2 class="text-2xl font-bold text-slate-900">Fleet Management</h2>
                        <p class="text-slate-500 text-sm">Monitor compliance and status of your registered vehicles.</p>
                    </div>

                    <div class="bg-white rounded-2xl shadow-soft border border-slate-100 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead class="bg-slate-50 border-b border-slate-200">
                                    <tr>
                                        <th class="p-5 font-semibold text-xs text-slate-500 uppercase tracking-wider">Plate Number</th>
                                        <th class="p-5 font-semibold text-xs text-slate-500 uppercase tracking-wider">Status</th>
                                        <th class="p-5 font-semibold text-xs text-slate-500 uppercase tracking-wider">Inspection</th>
                                        <th class="p-5 font-semibold text-xs text-slate-500 uppercase tracking-wider">Validity</th>
                                        <th class="p-5 font-semibold text-xs text-slate-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="fleetTable" class="divide-y divide-slate-100">
                                    <!-- Populated via JS -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

            </div>
        </main>
    </div>

    <!-- Profile Modal (Refined) -->
    <div id="profileModal" class="fixed inset-0 bg-black/60 z-50 hidden flex items-center justify-center p-4 backdrop-blur-sm transition-all duration-300">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full p-0 overflow-hidden animate-fade-in relative">
            
            <!-- Header Background -->
            <div class="h-32 bg-gradient-to-r from-primary to-secondary relative">
                <button onclick="closeProfileModal()" class="absolute top-4 right-4 text-white/80 hover:text-white bg-black/10 hover:bg-black/20 rounded-full p-1 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <!-- Avatar & Info -->
            <div class="px-8 pb-8 -mt-16 text-center relative z-10">
                <div class="w-32 h-32 bg-white rounded-full mx-auto p-1 shadow-lg mb-4">
                    <img src="https://ui-avatars.com/api/?name=Operator+User&background=random&size=128" alt="User" id="profAvatar" class="w-full h-full rounded-full object-cover">
                </div>
                <h3 class="text-2xl font-bold text-slate-800" id="profHeaderName">Loading...</h3>
                <p class="text-sm font-medium text-primary bg-orange-50 inline-block px-3 py-1 rounded-full mt-1" id="profHeaderAssoc">Loading...</p>
            </div>

            <div class="px-8 pb-8">
                <!-- VIEW MODE -->
                <div id="viewMode" class="space-y-6">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-4 bg-slate-50 rounded-xl border border-slate-100">
                            <div>
                                <span class="block text-xs font-bold text-slate-400 uppercase tracking-wider">Full Name</span>
                                <span class="font-semibold text-slate-700" id="viewName">--</span>
                            </div>
                            <svg class="w-5 h-5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        </div>
                        <div class="flex items-center justify-between p-4 bg-slate-50 rounded-xl border border-slate-100">
                            <div>
                                <span class="block text-xs font-bold text-slate-400 uppercase tracking-wider">Email Address</span>
                                <span class="font-semibold text-slate-700" id="viewEmail">--</span>
                            </div>
                            <svg class="w-5 h-5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        </div>
                        <div class="flex items-center justify-between p-4 bg-slate-50 rounded-xl border border-slate-100">
                            <div>
                                <span class="block text-xs font-bold text-slate-400 uppercase tracking-wider">Contact Number</span>
                                <span class="font-semibold text-slate-700" id="viewContact">--</span>
                            </div>
                            <svg class="w-5 h-5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                        </div>
                    </div>
                    <button onclick="enableEditMode()" class="w-full py-3 bg-slate-800 text-white rounded-xl font-bold hover:bg-slate-900 transition flex items-center justify-center gap-2 shadow-lg">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        Edit Profile
                    </button>
                </div>

                <!-- EDIT MODE -->
                <form id="editMode" class="hidden space-y-5" onsubmit="promptPassword(event)">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Full Name</label>
                            <input type="text" id="editName" name="name" class="w-full px-4 py-3 bg-slate-50 rounded-xl border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-primary outline-none transition" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Email Address</label>
                            <input type="email" id="editEmail" name="email" class="w-full px-4 py-3 bg-slate-50 rounded-xl border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-primary outline-none transition" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Contact Number</label>
                            <input type="text" id="editContact" name="contact" class="w-full px-4 py-3 bg-slate-50 rounded-xl border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-primary outline-none transition" required>
                        </div>
                        
                        <div class="pt-4 border-t border-slate-100">
                            <p class="text-xs text-slate-800 font-bold uppercase mb-3">Security Settings</p>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <input type="password" id="newPass" placeholder="New Password" class="w-full px-4 py-3 bg-slate-50 rounded-xl border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-primary outline-none transition text-sm">
                                </div>
                                <div>
                                    <input type="password" id="confirmPass" placeholder="Confirm Password" class="w-full px-4 py-3 bg-slate-50 rounded-xl border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-primary outline-none transition text-sm">
                                </div>
                            </div>
                            <p class="text-[10px] text-slate-400 mt-2 italic">* Leave blank if you don't want to change your password.</p>
                        </div>
                    </div>

                    <div class="flex gap-4 pt-2">
                        <button type="button" onclick="cancelEditMode()" class="flex-1 py-3 bg-white border border-slate-200 text-slate-600 rounded-xl text-sm font-bold hover:bg-slate-50 hover:text-slate-800 transition">Cancel</button>
                        <button type="submit" class="flex-1 py-3 bg-primary text-white rounded-xl text-sm font-bold hover:bg-primary-dark shadow-lg shadow-orange-500/20 transition">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Password Confirmation Modal (Refined) -->
    <div id="passwordConfirmModal" class="fixed inset-0 bg-black/70 z-[60] hidden flex items-center justify-center p-4 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-8 animate-fade-in text-center">
            <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4 text-slate-500">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
            </div>
            <h3 class="text-xl font-bold text-slate-900 mb-2">Security Check</h3>
            <p class="text-sm text-slate-500 mb-6">For your security, please enter your current password to confirm these changes.</p>
            
            <form onsubmit="confirmSaveProfile(event)" class="space-y-4">
                <input type="password" id="currentPassConfirm" placeholder="Current Password" class="w-full px-4 py-3 bg-slate-50 rounded-xl border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-slate-800 outline-none text-center font-bold tracking-widest" required>
                <div class="flex gap-3">
                    <button type="button" onclick="document.getElementById('passwordConfirmModal').classList.add('hidden')" class="flex-1 py-3 text-sm font-bold text-slate-500 hover:text-slate-700 transition">Cancel</button>
                    <button type="submit" class="flex-1 py-3 bg-slate-900 text-white rounded-xl text-sm font-bold hover:bg-black shadow-lg transition">Confirm Update</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // --- Navigation ---
        function showSection(id) {
            ['dashboard', 'applications', 'fleet'].forEach(s => {
                document.getElementById(s).classList.add('hidden');
                const btn = document.getElementById('nav-' + s);
                if (btn) btn.classList.remove('active');
            });
            document.getElementById(id).classList.remove('hidden');
            const activeBtn = document.getElementById('nav-' + id);
            if (activeBtn) activeBtn.classList.add('active');
            
            if (id === 'dashboard') loadStats();
            if (id === 'fleet') loadFleet();
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
            const res = await fetch('api.php?action=get_dashboard_stats');
            const data = await res.json();
            if (data.ok) {
                document.getElementById('statPending').innerText = data.data.pending_apps;
                document.getElementById('statVehicles').innerText = data.data.active_vehicles;
                document.getElementById('statAlerts').innerText = data.data.compliance_alerts;
            }
            
            // Load AI Insights
            const resAI = await fetch('api.php?action=get_ai_insights');
            const dataAI = await resAI.json();
            if (dataAI.ok) {
                const container = document.getElementById('aiInsightsContainer');
                container.innerHTML = dataAI.data.map(i => `
                    <div class="bg-white/5 p-4 rounded-xl border border-white/10 hover:bg-white/10 transition cursor-default">
                        <div class="flex justify-between items-start mb-2">
                            <h4 class="font-bold text-sm text-orange-100">${i.title}</h4>
                            <span class="text-[10px] px-2 py-1 rounded-full font-bold ${i.type === 'high' ? 'bg-red-500/20 text-red-200' : 'bg-green-500/20 text-green-200'}">${i.type.toUpperCase()}</span>
                        </div>
                        <p class="text-xs text-slate-300 leading-relaxed">${i.desc}</p>
                    </div>
                `).join('');
            }
        }

        async function loadFleet() {
            const res = await fetch('api.php?action=get_fleet_status');
            const data = await res.json();
            if (data.ok) {
                const tbody = document.getElementById('fleetTable');
                if (data.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" class="p-8 text-center text-slate-400 italic">No vehicles found in your fleet.</td></tr>';
                    return;
                }
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
                            <button class="text-slate-400 hover:text-primary transition">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path></svg>
                            </button>
                        </td>
                    </tr>
                `).join('');
            }
        }

        // --- Profile Management ---
        let currentProfileData = {};

        async function fetchProfile() {
            const res = await fetch('api.php?action=get_profile');
            const data = await res.json();
            if (data.ok) {
                currentProfileData = data.data;
                
                // Populate Header
                document.getElementById('profHeaderName').innerText = data.data.name || 'Unknown';
                document.getElementById('profHeaderAssoc').innerText = data.data.association_name || 'Individual Operator';
                
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
            const newPass = document.getElementById('newPass').value;
            const confirmPass = document.getElementById('confirmPass').value;

            if (newPass && newPass !== confirmPass) {
                alert('New passwords do not match!');
                return;
            }
            document.getElementById('passwordConfirmModal').classList.remove('hidden');
        }

        async function confirmSaveProfile(e) {
            e.preventDefault();
            const currentPass = document.getElementById('currentPassConfirm').value;
            
            if (!currentPass) {
                alert('Please enter your current password.');
                return;
            }

            const formData = new FormData(document.getElementById('editMode'));
            formData.append('action', 'update_profile');
            formData.append('current_password', currentPass);
            formData.append('new_password', document.getElementById('newPass').value);

            try {
                const res = await fetch('api.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                
                if (data.ok) {
                    alert('Profile updated successfully!');
                    document.getElementById('passwordConfirmModal').classList.add('hidden');
                    closeProfileModal();
                    fetchProfile(); 
                } else {
                    alert('Error: ' + (data.error || 'Update failed'));
                }
            } catch (err) {
                console.error(err);
                alert('An error occurred while updating profile.');
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
                const res = await fetch('api.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.ok) {
                    alert('Application Submitted! Reference: ' + data.ref);
                    e.target.reset();
                    document.getElementById('aiAnalysisResult').classList.add('hidden');
                    document.getElementById('docStatus').innerText = 'Click or drag file here to upload';
                    loadStats(); 
                } else {
                    alert('Error: ' + data.error);
                }
            } catch (err) {
                alert('Submission failed.');
            }
        }

        // Initial Load
        loadStats();
    </script>
</body>
</html>
