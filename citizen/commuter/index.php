<?php
require_once __DIR__ . '/../../includes/commuter_portal.php';
$baseUrl = str_replace('\\', '/', (string)dirname(dirname(dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/citizen/commuter/index.php')))));
$baseUrl = $baseUrl === '/' ? '' : rtrim($baseUrl, '/');
commuter_portal_require_login($baseUrl . '/index.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commuter Portal - TMM</title>
    <link rel="icon" type="image/jpeg" href="images/logo.jpg">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="js/tesseract.min.js"></script> <!-- Local fallback from npm install -->
    <script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script> <!-- CDN for Worker ease -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#f97316', // Orange-500
                        secondary: '#22c55e', // Green-500
                        'primary-dark': '#ea580c', // Orange-600
                        'secondary-dark': '#16a34a', // Green-600
                    }
                }
            }
        }
    </script>
    <style>
        .fade-in { animation: fadeIn 0.3s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    </style>
</head>
<body class="bg-slate-50 min-h-screen font-sans text-slate-800">

    <!-- Header -->
    <header class="bg-white shadow-md sticky top-0 z-50">
        <div class="max-w-4xl mx-auto px-4 py-4 flex items-center justify-between">
            <div class="flex items-center space-x-2">
                <img src="images/logo.jpg" alt="Logo" class="w-10 h-10 rounded-lg shadow-lg object-cover">
                <h1 class="text-xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-primary to-primary-dark">Commuter<span class="text-secondary">Portal</span></h1>
            </div>
            <div class="hidden md:flex items-center gap-3">
                <div class="text-xs font-bold text-slate-500"><?php echo htmlspecialchars((string)($_SESSION['name'] ?? '')); ?></div>
                <a href="logout.php" class="px-3 py-2 rounded-xl bg-white border border-slate-200 text-slate-600 text-xs font-bold hover:bg-slate-50 transition">Logout</a>
            </div>
            <nav class="hidden md:flex space-x-6 text-sm font-medium text-slate-600">
                <button onclick="showSection('verify')" class="hover:text-primary transition-colors">Verify Vehicle</button>
                <button onclick="showSection('travel')" class="hover:text-primary transition-colors">Travel Info</button>
                <button onclick="showSection('complaint')" class="hover:text-primary transition-colors">Complaints</button>
            </nav>
            <div class="md:hidden">
                <!-- Mobile menu button placeholder -->
                <button class="text-slate-500 hover:text-primary">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-md md:max-w-4xl mx-auto px-4 py-8 pb-20">
        
        <!-- Welcome / Verify Section -->
        <section id="verify-section" class="fade-in space-y-6">
            <div class="text-center space-y-2 mb-8">
                <h2 class="text-2xl font-bold text-slate-800">Safe Commute, Verified.</h2>
                <p class="text-slate-500">Enter a plate number to verify vehicle legitimacy.</p>
            </div>

            <div class="bg-white rounded-2xl shadow-xl p-6 border-t-4 border-primary">
                <form id="verifyForm" onsubmit="verifyVehicle(event)" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Plate Number</label>
                        <div class="flex gap-2">
                            <input type="text" id="plateInput" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none text-lg uppercase tracking-wider" placeholder="ABC-1234" required>
                            <button type="button" onclick="document.getElementById('plateScanner').click()" class="bg-slate-200 hover:bg-slate-300 text-slate-600 rounded-xl px-4 flex items-center justify-center transition-colors" title="Scan Plate from Image">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            </button>
                            <input type="file" id="plateScanner" accept="image/*" class="hidden" onchange="scanPlate(this)">
                        </div>
                        <div id="scanStatus" class="hidden text-xs text-primary font-bold mt-1 flex items-center">
                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            Scanning image...
                        </div>
                    </div>
                    <button type="submit" class="w-full bg-gradient-to-r from-primary to-primary-dark text-white font-bold py-3 rounded-xl shadow-lg hover:shadow-primary/30 transform hover:-translate-y-0.5 transition-all">
                        Check Status
                    </button>
                </form>

                <!-- Result Card -->
                <div id="vehicleResult" class="hidden mt-6 p-4 bg-slate-50 rounded-xl border border-slate-100">
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-sm text-slate-500">Status</span>
                        <span id="vStatus" class="px-3 py-1 rounded-full text-xs font-bold bg-green-100 text-green-700">ACTIVE</span>
                    </div>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-slate-500 text-sm">Operator</span>
                            <span id="vOperator" class="font-medium text-slate-800 text-right">-</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500 text-sm">Coop</span>
                            <span id="vCoop" class="font-medium text-slate-800 text-right">-</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500 text-sm">Route</span>
                            <span id="vRoute" class="font-medium text-slate-800 text-right">-</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500 text-sm">Terminal</span>
                            <span id="vTerminal" class="font-medium text-slate-800 text-right">-</span>
                        </div>
                    </div>
                </div>
                <div id="verifyError" class="hidden mt-4 text-center text-red-500 text-sm font-medium"></div>
            </div>
        </section>

        <!-- Travel Info Section -->
        <section id="travel-section" class="hidden fade-in space-y-6">
            <div class="text-center space-y-2 mb-8">
                <h2 class="text-2xl font-bold text-slate-800">Smart Travel Insights</h2>
                <p class="text-slate-500">AI-powered predictions for your commute.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl p-6 text-white shadow-lg">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-bold opacity-90">Crowding Level</h3>
                        <svg class="w-6 h-6 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    </div>
                    <div id="tCrowding" class="text-3xl font-bold mb-1">Loading...</div>
                    <p class="text-sm opacity-80">Current estimated density</p>
                </div>

                <div class="bg-white rounded-2xl p-6 shadow-md border border-slate-100">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-bold text-slate-700">Wait Time</h3>
                        <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <div id="tWait" class="text-3xl font-bold text-slate-800 mb-1">-- mins</div>
                    <p class="text-sm text-slate-500">Estimated queuing time</p>
                </div>

                <div class="bg-white rounded-2xl p-6 shadow-md border border-slate-100 md:col-span-2">
                    <div class="flex items-center space-x-3 mb-2">
                        <div class="p-2 bg-yellow-100 text-yellow-600 rounded-lg">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        </div>
                        <h3 class="font-bold text-slate-700">AI Suggestion</h3>
                    </div>
                    <p id="tSuggestion" class="text-slate-600">Loading suggestion...</p>
                </div>
            </div>
        </section>

        <!-- Complaint Section -->
        <section id="complaint-section" class="hidden fade-in space-y-6">
            <div class="text-center space-y-2 mb-8">
                <h2 class="text-2xl font-bold text-slate-800">Report an Issue</h2>
                <p class="text-slate-500">Help us improve public transport.</p>
            </div>

            <!-- Tabs for Complaint: New vs Track -->
            <div class="flex rounded-xl bg-slate-200 p-1 mb-6">
                <button onclick="toggleComplaintTab('new')" id="tab-new" class="flex-1 py-2 text-sm font-bold rounded-lg bg-white text-slate-800 shadow-sm transition-all">New Complaint</button>
                <button onclick="toggleComplaintTab('track')" id="tab-track" class="flex-1 py-2 text-sm font-bold rounded-lg text-slate-500 hover:text-slate-700 transition-all">Track Status</button>
            </div>

            <!-- New Complaint Form -->
            <div id="new-complaint-form">
                <form id="complaintForm" onsubmit="submitComplaint(event)" class="space-y-4 bg-white p-6 rounded-2xl shadow-md border border-slate-100">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Complaint Type</label>
                        <select name="type" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none bg-white">
                            <option value="Overcharging">Overcharging</option>
                            <option value="Reckless Driving">Reckless Driving</option>
                            <option value="Refusal to Load">Refusal to Load</option>
                            <option value="No Permit">No Permit Displayed</option>
                            <option value="Rude Behavior">Rude Behavior</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                        <textarea name="description" rows="3" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none" placeholder="Describe what happened..." required></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Photo/Video (Optional)</label>
                        <input type="file" name="media" accept="image/*,video/*" class="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-secondary/10 file:text-secondary hover:file:bg-secondary/20">
                    </div>
                    <button type="submit" class="w-full bg-secondary text-white font-bold py-3 rounded-xl shadow-lg hover:bg-secondary-dark transform hover:-translate-y-0.5 transition-all">
                        Submit Report
                    </button>
                </form>
            </div>

            <!-- Track Complaint Form -->
            <div id="track-complaint-form" class="hidden">
                <div class="bg-white p-6 rounded-2xl shadow-md border border-slate-100">
                    <div class="flex space-x-2 mb-4">
                        <input type="text" id="trackRef" class="flex-1 px-4 py-3 rounded-xl border border-slate-200 focus:border-secondary focus:ring-2 focus:ring-secondary/20 outline-none uppercase" placeholder="Enter Reference No. (e.g. COM-123)">
                        <button onclick="trackComplaint()" class="bg-slate-800 text-white px-6 rounded-xl font-bold hover:bg-slate-700">Track</button>
                    </div>
                    <div id="trackResult" class="hidden mt-4 p-4 bg-slate-50 rounded-xl border border-slate-100">
                        <div class="text-sm text-slate-500 mb-1">Status</div>
                        <div id="trackStatus" class="text-lg font-bold text-slate-800">Submitted</div>
                        <div id="trackDate" class="text-xs text-slate-400 mt-2"></div>
                    </div>
                    <div id="trackError" class="hidden mt-4 text-center text-red-500 text-sm"></div>
                </div>
            </div>
        </section>

    </main>

    <!-- Bottom Nav (Mobile) -->
    <div class="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-slate-200 flex justify-around p-3 z-50">
        <button onclick="showSection('verify')" class="flex flex-col items-center text-primary">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span class="text-xs mt-1">Verify</span>
        </button>
        <button onclick="showSection('travel')" class="flex flex-col items-center text-slate-400 hover:text-primary">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
            <span class="text-xs mt-1">Travel</span>
        </button>
        <button onclick="showSection('complaint')" class="flex flex-col items-center text-slate-400 hover:text-primary">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            <span class="text-xs mt-1">Report</span>
        </button>
    </div>

    <script>
        // Navigation Logic
        function showSection(sectionId) {
            // Hide all sections
            document.getElementById('verify-section').classList.add('hidden');
            document.getElementById('travel-section').classList.add('hidden');
            document.getElementById('complaint-section').classList.add('hidden');
            
            // Show selected
            document.getElementById(sectionId + '-section').classList.remove('hidden');
            
            // Mobile Nav Active State (Simple Implementation)
            const btns = document.querySelectorAll('.fixed.bottom-0 button');
            btns.forEach(b => b.classList.remove('text-primary'));
            if(sectionId === 'verify') btns[0].classList.add('text-primary');
            if(sectionId === 'travel') btns[1].classList.add('text-primary');
            if(sectionId === 'complaint') btns[2].classList.add('text-primary');

            // Load data if needed
            if(sectionId === 'travel') loadTravelInfo();
        }

        function toggleComplaintTab(tab) {
            if(tab === 'new') {
                document.getElementById('new-complaint-form').classList.remove('hidden');
                document.getElementById('track-complaint-form').classList.add('hidden');
                document.getElementById('tab-new').classList.replace('text-slate-500', 'text-slate-800');
                document.getElementById('tab-new').classList.add('bg-white', 'shadow-sm');
                document.getElementById('tab-track').classList.replace('text-slate-800', 'text-slate-500');
                document.getElementById('tab-track').classList.remove('bg-white', 'shadow-sm');
            } else {
                document.getElementById('new-complaint-form').classList.add('hidden');
                document.getElementById('track-complaint-form').classList.remove('hidden');
                document.getElementById('tab-track').classList.replace('text-slate-500', 'text-slate-800');
                document.getElementById('tab-track').classList.add('bg-white', 'shadow-sm');
                document.getElementById('tab-new').classList.replace('text-slate-800', 'text-slate-500');
                document.getElementById('tab-new').classList.remove('bg-white', 'shadow-sm');
            }
        }

        // API Interactions
        const API_URL = 'api.php';

        // OCR Functions
        async function scanPlate(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const status = document.getElementById('scanStatus');
                status.classList.remove('hidden');
                
                try {
                    const worker = await Tesseract.createWorker('eng');
                    const ret = await worker.recognize(file);
                    console.log(ret.data.text);
                    
                    // Simple regex to find plate number pattern (XXX-0000 or XXX 0000)
                    const text = ret.data.text;
                    const plateMatch = text.match(/[A-Z]{3}[\s-]?[0-9]{3,4}/i);
                    
                    if (plateMatch) {
                        let plate = plateMatch[0].replace(/\s/g, '-').toUpperCase();
                        if (!plate.includes('-')) {
                            // Insert dash if missing
                            plate = plate.slice(0, 3) + '-' + plate.slice(3);
                        }
                        document.getElementById('plateInput').value = plate;
                        // Auto-verify
                        document.querySelector('#verifyForm button[type="submit"]').click();
                    } else {
                        alert('Could not detect a clear plate number. Please type it manually.');
                    }
                    await worker.terminate();
                } catch (err) {
                    console.error(err);
                    alert('Error scanning image.');
                } finally {
                    status.classList.add('hidden');
                    input.value = ''; // Reset
                }
            }
        }

        async function analyzeComplaintImage(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                if (!file.type.startsWith('image/')) return;

                const ocrBox = document.getElementById('ocrAnalysis');
                const ocrText = document.getElementById('ocrText');
                
                ocrBox.classList.remove('hidden');
                ocrText.innerText = 'Analyzing image...';
                
                try {
                    const worker = await Tesseract.createWorker('eng');
                    const ret = await worker.recognize(file);
                    const text = ret.data.text.trim();
                    
                    if (text.length > 5) {
                        ocrText.innerText = text.substring(0, 100) + (text.length > 100 ? '...' : '');
                        
                        // Auto-fill description if empty
                        const desc = document.querySelector('textarea[name="description"]');
                        if (!desc.value) {
                            desc.value = `[AI Scanned Text]: ${text}`;
                        } else {
                            desc.value += `\n\n[AI Scanned Text]: ${text}`;
                        }
                    } else {
                        ocrBox.classList.add('hidden');
                    }
                    await worker.terminate();
                } catch (err) {
                    console.error(err);
                    ocrBox.classList.add('hidden');
                }
            }
        }

        async function verifyVehicle(e) {
            e.preventDefault();
            const plate = document.getElementById('plateInput').value;
            const btn = e.target.querySelector('button');
            const originalText = btn.innerText;
            btn.innerText = 'Checking...';
            btn.disabled = true;

            try {
                const formData = new FormData();
                formData.append('action', 'verify_vehicle');
                formData.append('plate_number', plate);
                
                const res = await fetch(API_URL, { method: 'POST', body: formData });
                const data = await res.json();

                if(data.ok) {
                    document.getElementById('verifyError').classList.add('hidden');
                    document.getElementById('vehicleResult').classList.remove('hidden');
                    
                    const v = data.data;
                    document.getElementById('vStatus').innerText = v.status;
                    document.getElementById('vStatus').className = `px-3 py-1 rounded-full text-xs font-bold ${v.status === 'Active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`;
                    document.getElementById('vOperator').innerText = v.operator_name || 'N/A';
                    document.getElementById('vCoop').innerText = v.coop_name || 'N/A';
                    document.getElementById('vRoute').innerText = v.route_id || 'N/A';
                    document.getElementById('vTerminal').innerText = v.terminal_name || 'Not Assigned';
                } else {
                    document.getElementById('vehicleResult').classList.add('hidden');
                    document.getElementById('verifyError').innerText = data.error || 'Vehicle not found.';
                    document.getElementById('verifyError').classList.remove('hidden');
                }
            } catch (err) {
                console.error(err);
                alert('Connection error');
            } finally {
                btn.innerText = originalText;
                btn.disabled = false;
            }
        }

        async function loadTravelInfo() {
            try {
                const res = await fetch(API_URL + '?action=get_travel_info');
                const data = await res.json();
                
                if(data.ok) {
                    const info = data.data;
                    document.getElementById('tCrowding').innerText = info.crowding_level;
                    document.getElementById('tWait').innerText = info.estimated_wait_time;
                    document.getElementById('tSuggestion').innerText = `Best time to travel: ${info.best_time_to_travel}`;
                }
            } catch (err) {
                console.error(err);
            }
        }

        async function submitComplaint(e) {
            e.preventDefault();
            const btn = e.target.querySelector('button');
            const originalText = btn.innerText;
            btn.innerText = 'Submitting...';
            btn.disabled = true;

            try {
                const formData = new FormData(e.target);
                formData.append('action', 'submit_complaint');

                const res = await fetch(API_URL, { method: 'POST', body: formData });
                const data = await res.json();

                if(data.ok) {
                    alert(`Complaint Submitted!\nRef No: ${data.ref_number}\nAI Tags: ${data.ai_tags.join(', ')}`);
                    e.target.reset();
                    toggleComplaintTab('track');
                    document.getElementById('trackRef').value = data.ref_number;
                    trackComplaint();
                } else {
                    alert('Error: ' + data.error);
                }
            } catch (err) {
                alert('Submission failed');
            } finally {
                btn.innerText = originalText;
                btn.disabled = false;
            }
        }

        async function trackComplaint() {
            const ref = document.getElementById('trackRef').value;
            if(!ref) return;

            try {
                const res = await fetch(`${API_URL}?action=get_complaint_status&ref_number=${ref}`);
                const data = await res.json();

                if(data.ok) {
                    document.getElementById('trackError').classList.add('hidden');
                    document.getElementById('trackResult').classList.remove('hidden');
                    document.getElementById('trackStatus').innerText = data.data.status;
                    document.getElementById('trackDate').innerText = 'Submitted on: ' + data.data.created_at;
                } else {
                    document.getElementById('trackResult').classList.add('hidden');
                    document.getElementById('trackError').innerText = data.error;
                    document.getElementById('trackError').classList.remove('hidden');
                }
            } catch (err) {
                console.error(err);
            }
        }
    </script>
</body>
</html>
