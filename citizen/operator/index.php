<?php
$baseDir = __DIR__;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Operator Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/jpeg" href="../../admin/includes/logo.jpg">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    // New Theme: Corporate Green & Orange
    tailwind.config = { 
      darkMode: 'class', 
      theme: { 
        extend: { 
          colors: { 
            primary: '#064e3b', // Emerald 900 (Deep Green)
            secondary: '#334155', // Slate 700
            accent: '#f97316', // Orange 500
            bg: '#f1f5f9', // Slate 100
            surface: '#ffffff'
          } 
        } 
      } 
    }
  </script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <!-- Tesseract.js for OCR -->
  <script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
  <style>
    /* Custom Scrollbar */
    ::-webkit-scrollbar { width: 8px; }
    ::-webkit-scrollbar-track { background: #f1f5f9; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    
    .glass-header {
      background: rgba(6, 78, 59, 0.98); /* Emerald 900 */
      backdrop-filter: blur(10px);
    }
    
    .nav-link {
      position: relative;
      transition: all 0.3s ease;
    }
    
    .nav-link.active {
      color: #f97316; /* Orange 500 */
      font-weight: 600;
    }
    
    .nav-link.active::after {
      content: '';
      position: absolute;
      bottom: -1rem; /* Adjust based on padding */
      left: 0;
      width: 100%;
      height: 3px;
      background-color: #f97316; /* Orange 500 */
      border-radius: 3px 3px 0 0;
    }

    .card-hover {
      transition: transform 0.2s, box-shadow 0.2s;
    }
    .card-hover:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }
  </style>
</head>
<body class="min-h-screen bg-bg text-slate-800 font-sans">
  <div class="flex flex-col min-h-screen">
    
    <!-- Top Header (Dark Navy) -->
    <header class="glass-header text-white shadow-md sticky top-0 z-50">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
          
          <!-- Logo & Title -->
          <div class="flex items-center gap-4">
            <img src="../../admin/includes/logo.jpg" class="w-10 h-10 rounded-lg border-2 border-slate-600" alt="Logo">
            <div>
              <h1 class="text-lg font-bold tracking-wide">OPERATOR PORTAL</h1>
              <p class="text-[10px] text-slate-400 uppercase tracking-wider">Transport Management Module</p>
            </div>
          </div>

          <!-- Navigation (Top Bar) -->
          <nav class="hidden md:flex items-center gap-8">
            <button data-tab="dashboard" class="tab-btn nav-link active text-sm uppercase tracking-wide hover:text-white/80 py-5">Dashboard</button>
            <button data-tab="applications" class="tab-btn nav-link text-sm uppercase tracking-wide hover:text-white/80 py-5">Applications</button>
            <button data-tab="compliance" class="tab-btn nav-link text-sm uppercase tracking-wide hover:text-white/80 py-5">Compliance</button>
            <button data-tab="notifications" class="tab-btn nav-link text-sm uppercase tracking-wide hover:text-white/80 py-5">Notifications</button>
          </nav>

          <!-- User/Theme Toggle -->
          <button onclick="openProfile()" class="flex items-center gap-4 hover:bg-white/10 p-2 rounded-lg transition-colors focus:outline-none">
            <div class="text-right hidden sm:block">
              <div class="text-xs text-slate-400">Welcome,</div>
              <div class="text-sm font-semibold">Operator</div>
            </div>
            <div class="w-8 h-8 rounded-full bg-accent flex items-center justify-center text-white font-bold border-2 border-transparent hover:border-white transition-all">
              OP
            </div>
          </button>
        </div>
      </div>
      
      <!-- Mobile Nav (Visible only on small screens) -->
      <div class="md:hidden border-t border-slate-800 bg-primary px-4 py-2 flex justify-between overflow-x-auto">
        <button data-tab="dashboard" class="tab-btn text-xs text-slate-300 py-2 px-3 hover:text-white">Dashboard</button>
        <button data-tab="applications" class="tab-btn text-xs text-slate-300 py-2 px-3 hover:text-white">Applications</button>
        <button data-tab="compliance" class="tab-btn text-xs text-slate-300 py-2 px-3 hover:text-white">Compliance</button>
        <button data-tab="notifications" class="tab-btn text-xs text-slate-300 py-2 px-3 hover:text-white">Notifs</button>
      </div>
    </header>

    <main class="flex-1 max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8">
      
      <!-- DASHBOARD TAB -->
      <section id="tab-dashboard" class="tab-section animate-fade-in">
        <div class="mb-8">
          <h2 class="text-2xl font-bold text-primary">Overview</h2>
          <p class="text-slate-500">Welcome back. Here is your fleet status.</p>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
          <div class="bg-white rounded-xl p-6 shadow-sm border-t-4 border-accent card-hover">
            <div class="flex items-center justify-between mb-4">
              <div class="p-3 bg-blue-50 rounded-lg">
                <i data-lucide="file-text" class="w-6 h-6 text-blue-600"></i>
              </div>
              <span class="text-xs font-bold text-blue-600 bg-blue-50 px-2 py-1 rounded-full">Total</span>
            </div>
            <div class="text-slate-500 text-sm font-medium">Applications Submitted</div>
            <div id="statApplications" class="text-3xl font-bold text-primary mt-1">0</div>
          </div>

          <div class="bg-white rounded-xl p-6 shadow-sm border-t-4 border-red-500 card-hover">
             <div class="flex items-center justify-between mb-4">
              <div class="p-3 bg-red-50 rounded-lg">
                <i data-lucide="alert-circle" class="w-6 h-6 text-red-600"></i>
              </div>
              <span class="text-xs font-bold text-red-600 bg-red-50 px-2 py-1 rounded-full">Action Needed</span>
            </div>
            <div class="text-slate-500 text-sm font-medium">Active Violations</div>
            <div id="statViolations" class="text-3xl font-bold text-primary mt-1">0</div>
          </div>

          <div class="bg-white rounded-xl p-6 shadow-sm border-t-4 border-emerald-500 card-hover">
             <div class="flex items-center justify-between mb-4">
              <div class="p-3 bg-emerald-50 rounded-lg">
                <i data-lucide="calendar" class="w-6 h-6 text-emerald-600"></i>
              </div>
              <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-2 py-1 rounded-full">Upcoming</span>
            </div>
            <div class="text-slate-500 text-sm font-medium">Renewals (30 Days)</div>
            <div id="statRenewals" class="text-3xl font-bold text-primary mt-1">0</div>
          </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
          <div class="bg-slate-50 px-6 py-4 border-b border-slate-200 flex justify-between items-center">
            <h3 class="font-bold text-primary">Quick Actions</h3>
            <i data-lucide="zap" class="w-4 h-4 text-accent"></i>
          </div>
          <div class="p-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <button class="flex flex-col items-center justify-center p-6 border-2 border-dashed border-slate-200 rounded-xl hover:border-accent hover:bg-orange-50 transition-colors group" onclick="openTab('applications'); openSub('franchise')">
              <div class="w-12 h-12 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                <i data-lucide="stamp" class="w-6 h-6"></i>
              </div>
              <span class="font-semibold text-primary">Franchise Endorsement</span>
              <span class="text-xs text-slate-500 mt-1">Apply for new franchise</span>
            </button>

            <button class="flex flex-col items-center justify-center p-6 border-2 border-dashed border-slate-200 rounded-xl hover:border-accent hover:bg-orange-50 transition-colors group" onclick="openTab('applications'); openSub('inspection')">
              <div class="w-12 h-12 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                <i data-lucide="clipboard-check" class="w-6 h-6"></i>
              </div>
              <span class="font-semibold text-primary">Vehicle Inspection</span>
              <span class="text-xs text-slate-500 mt-1">Schedule appointment</span>
            </button>

            <button class="flex flex-col items-center justify-center p-6 border-2 border-dashed border-slate-200 rounded-xl hover:border-accent hover:bg-orange-50 transition-colors group" onclick="openTab('applications'); openSub('terminal')">
              <div class="w-12 h-12 rounded-full bg-purple-100 text-purple-600 flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                <i data-lucide="map-pin" class="w-6 h-6"></i>
              </div>
              <span class="font-semibold text-primary">Terminal Enrollment</span>
              <span class="text-xs text-slate-500 mt-1">Register facility</span>
            </button>

            <button class="flex flex-col items-center justify-center p-6 border-2 border-dashed border-slate-200 rounded-xl hover:border-accent hover:bg-orange-50 transition-colors group" onclick="openTab('compliance')">
              <div class="w-12 h-12 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                <i data-lucide="activity" class="w-6 h-6"></i>
              </div>
              <span class="font-semibold text-primary">Monitor Compliance</span>
              <span class="text-xs text-slate-500 mt-1">Check fleet status</span>
            </button>
          </div>
        </div>
      </section>

      <!-- APPLICATIONS TAB (CARD DECK) -->
      <section id="tab-applications" class="tab-section hidden animate-fade-in">
        <div class="mb-8">
          <h2 class="text-2xl font-bold text-primary">Applications</h2>
          <p class="text-slate-500">Select an application type to proceed.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          
          <!-- Franchise Card -->
          <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-8 flex flex-col items-center text-center card-hover cursor-pointer group" onclick="openAppModal('franchise')">
            <div class="w-20 h-20 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
              <i data-lucide="stamp" class="w-10 h-10"></i>
            </div>
            <h3 class="text-xl font-bold text-primary mb-2">Franchise Endorsement</h3>
            <p class="text-slate-500 text-sm mb-6">Apply for a new franchise endorsement or renew existing ones.</p>
            <button class="mt-auto px-6 py-2 rounded-full bg-blue-50 text-blue-600 font-semibold text-sm group-hover:bg-blue-600 group-hover:text-white transition-colors">
              Apply Now
            </button>
          </div>

          <!-- Inspection Card -->
          <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-8 flex flex-col items-center text-center card-hover cursor-pointer group" onclick="openAppModal('inspection')">
             <div class="w-20 h-20 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
              <i data-lucide="clipboard-check" class="w-10 h-10"></i>
            </div>
            <h3 class="text-xl font-bold text-primary mb-2">Vehicle Inspection</h3>
            <p class="text-slate-500 text-sm mb-6">Schedule your vehicle for inspection at our centers.</p>
            <button class="mt-auto px-6 py-2 rounded-full bg-emerald-50 text-emerald-600 font-semibold text-sm group-hover:bg-emerald-600 group-hover:text-white transition-colors">
              Schedule
            </button>
          </div>

          <!-- Terminal Card -->
          <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-8 flex flex-col items-center text-center card-hover cursor-pointer group" onclick="openAppModal('terminal')">
             <div class="w-20 h-20 rounded-full bg-purple-50 text-purple-600 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
              <i data-lucide="map-pin" class="w-10 h-10"></i>
            </div>
            <h3 class="text-xl font-bold text-primary mb-2">Terminal Enrollment</h3>
            <p class="text-slate-500 text-sm mb-6">Register a new terminal, parking, or loading bay.</p>
            <button class="mt-auto px-6 py-2 rounded-full bg-purple-50 text-purple-600 font-semibold text-sm group-hover:bg-purple-600 group-hover:text-white transition-colors">
              Register
            </button>
          </div>

        </div>
      </section>

      <!-- COMPLIANCE TAB -->
      <section id="tab-compliance" class="tab-section hidden animate-fade-in">
        <div class="mb-8">
           <h2 class="text-2xl font-bold text-primary">Fleet Compliance</h2>
           <p class="text-slate-500">Monitor violations and ensure your fleet remains compliant.</p>
        </div>
        
        <div class="bg-white rounded-xl shadow-lg border border-slate-200 overflow-hidden">
          <div class="p-6 border-b border-slate-200 bg-slate-50">
            <form id="formCompliance" class="flex flex-col md:flex-row gap-4 items-end">
              <div class="flex-1 w-full space-y-1">
                 <label class="text-xs font-bold text-slate-500 uppercase">Operator Name</label>
                 <input name="operator_name" class="w-full px-4 py-2 rounded-lg border border-slate-300 bg-white" placeholder="Search...">
              </div>
              <div class="flex-1 w-full space-y-1">
                 <label class="text-xs font-bold text-slate-500 uppercase">Cooperative</label>
                 <input name="coop_name" class="w-full px-4 py-2 rounded-lg border border-slate-300 bg-white" placeholder="Search...">
              </div>
              <button class="px-6 py-2 rounded-lg bg-primary text-white hover:bg-slate-800 shadow-md transition-all">
                <i data-lucide="search" class="w-4 h-4 inline mr-2"></i> View Report
              </button>
            </form>
          </div>
          
          <div class="p-8">
             <div id="complianceSummary" class="mb-8">
               <!-- Placeholder State -->
               <div class="text-center py-12 text-slate-400">
                 <i data-lucide="bar-chart-2" class="w-12 h-12 mx-auto mb-4 opacity-50"></i>
                 <p>Enter operator details to view compliance summary.</p>
               </div>
             </div>

             <div class="border-t border-slate-200 pt-8">
                <h3 class="font-bold text-primary mb-4 flex items-center gap-2">
                  <i data-lucide="alert-triangle" class="w-5 h-5 text-red-500"></i>
                  Recent Violations
                </h3>
                <div id="complianceViolations" class="bg-slate-50 rounded-lg p-4 min-h-[100px]">
                   <p class="text-slate-400 text-sm italic">No records to display.</p>
                </div>
             </div>
          </div>
        </div>
      </section>

      <!-- NOTIFICATIONS TAB -->
      <section id="tab-notifications" class="tab-section hidden animate-fade-in">
         <div class="mb-8">
           <h2 class="text-2xl font-bold text-primary">Notifications</h2>
           <p class="text-slate-500">Stay updated with alerts and reminders.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
          <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
              <h3 class="font-bold text-primary mb-4">Filter Alerts</h3>
              <form id="formNotif" class="space-y-4">
                <div class="space-y-1">
                   <label class="text-xs font-bold text-slate-500 uppercase">Operator Name</label>
                   <input name="operator_name" class="w-full px-4 py-2 rounded-lg border border-slate-300 bg-slate-50" placeholder="Required">
                </div>
                <div class="space-y-1">
                   <label class="text-xs font-bold text-slate-500 uppercase">Plate Number</label>
                   <input name="plate_number" class="w-full px-4 py-2 rounded-lg border border-slate-300 bg-slate-50" placeholder="Optional">
                </div>
                <button class="w-full px-4 py-2 rounded-lg bg-secondary text-white hover:bg-slate-600 shadow-md transition-all">
                  Load Notifications
                </button>
              </form>
            </div>
          </div>

          <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 min-h-[400px]">
               <div id="notifList" class="space-y-3">
                  <!-- Empty State -->
                  <div class="text-center py-12 text-slate-400">
                    <i data-lucide="bell-off" class="w-12 h-12 mx-auto mb-4 opacity-50"></i>
                    <p>No notifications loaded.</p>
                  </div>
               </div>
            </div>
          </div>
        </div>
      </section>

    </main>

    <!-- Upload Modal -->
    <div id="uploadModal" class="fixed inset-0 hidden items-center justify-center z-[60] backdrop-blur-sm">
      <div class="absolute inset-0 bg-slate-900/60" onclick="closeUpload()"></div>
      <div class="relative w-full max-w-md mx-auto p-6 rounded-2xl bg-white shadow-2xl border border-slate-200 transform transition-all scale-100">
        <div class="flex items-center justify-between mb-6">
          <div class="font-bold text-xl text-primary">Upload Documents</div>
          <button class="p-2 rounded-full hover:bg-slate-100 transition-colors" onclick="closeUpload()">
            <i data-lucide="x" class="w-5 h-5 text-slate-500"></i>
          </button>
        </div>
        <form id="formUpload" class="space-y-4">
          <div class="space-y-1">
             <label class="text-xs font-bold text-slate-500 uppercase">Plate Number</label>
             <input name="plate_number" class="w-full px-4 py-2 rounded-lg border border-slate-300 bg-slate-50" placeholder="ABC-1234">
          </div>
          
          <div class="p-4 border-2 border-dashed border-slate-200 rounded-xl bg-slate-50">
             <label class="block text-sm font-medium text-slate-700 mb-2">OR (Official Receipt)</label>
             <input name="or" type="file" accept=".jpg,.jpeg,.png,.pdf" class="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
          </div>

          <div class="p-4 border-2 border-dashed border-slate-200 rounded-xl bg-slate-50">
             <label class="block text-sm font-medium text-slate-700 mb-2">CR (Certificate of Registration)</label>
             <input name="cr" type="file" accept=".jpg,.jpeg,.png,.pdf" class="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
          </div>

          <div class="flex gap-3 pt-2">
             <button type="button" class="flex-1 px-4 py-2 rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 font-medium text-sm transition-colors border border-emerald-100" onclick="precheckUpload()">
               <i data-lucide="scan" class="w-4 h-4 inline mr-1"></i> AI Pre-Check
             </button>
             <button type="submit" class="flex-1 px-4 py-2 rounded-lg bg-primary text-white hover:bg-slate-800 font-medium text-sm shadow-lg transition-all">
               Upload Now
             </button>
          </div>
          
          <div id="precheckResult" class="text-xs text-center min-h-[20px]"></div>
        </form>
        <div id="uploadResult" class="mt-4 p-3 bg-slate-50 rounded-lg text-sm text-center border border-slate-100"></div>
      </div>
    </div>

    <!-- Franchise Modal -->
    <div id="modalFranchise" class="fixed inset-0 hidden items-center justify-center z-50 backdrop-blur-sm">
      <div class="absolute inset-0 bg-slate-900/60" onclick="closeAppModal('franchise')"></div>
      <div class="relative w-full max-w-2xl mx-auto p-0 rounded-2xl bg-white shadow-2xl border border-slate-200 transform transition-all scale-100 max-h-[90vh] overflow-y-auto">
        <!-- Header -->
        <div class="sticky top-0 bg-white z-10 px-6 py-4 border-b border-slate-100 flex items-center justify-between">
           <div class="flex items-center gap-3">
              <div class="p-2 bg-blue-50 rounded-lg text-blue-600">
                <i data-lucide="stamp" class="w-6 h-6"></i>
              </div>
              <div>
                <h3 class="font-bold text-lg text-primary">Franchise Endorsement</h3>
                <p class="text-xs text-slate-500">New Application</p>
              </div>
           </div>
           <button class="p-2 rounded-full hover:bg-slate-100 transition-colors" onclick="closeAppModal('franchise')">
             <i data-lucide="x" class="w-5 h-5 text-slate-500"></i>
           </button>
        </div>
        
        <!-- Body -->
        <div class="p-6">
          <form id="formFranchise" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="space-y-1">
                <label class="text-xs font-bold text-slate-500 uppercase">Reference No.</label>
                <input name="franchise_ref" class="w-full px-4 py-2 rounded-lg border border-slate-300 focus:border-accent focus:ring-1 focus:ring-accent outline-none transition-colors bg-slate-50" placeholder="e.g. FR-2024-001">
              </div>
              <div class="space-y-1">
                <label class="text-xs font-bold text-slate-500 uppercase">Operator Name</label>
                <input name="operator_name" class="w-full px-4 py-2 rounded-lg border border-slate-300 focus:border-accent focus:ring-1 focus:ring-accent outline-none transition-colors bg-slate-50" placeholder="Full Name">
              </div>
            </div>
            
            <div class="space-y-1">
              <label class="text-xs font-bold text-slate-500 uppercase">Cooperative (Optional)</label>
              <input name="coop_name" class="w-full px-4 py-2 rounded-lg border border-slate-300 focus:border-accent focus:ring-1 focus:ring-accent outline-none transition-colors bg-slate-50" placeholder="Cooperative Name">
            </div>

            <div class="grid grid-cols-3 gap-4">
              <div class="col-span-1 space-y-1">
                <label class="text-xs font-bold text-slate-500 uppercase">Units</label>
                <input name="vehicle_count" type="number" min="1" class="w-full px-4 py-2 rounded-lg border border-slate-300 focus:border-accent focus:ring-1 focus:ring-accent outline-none transition-colors bg-slate-50" placeholder="1">
              </div>
              <div class="col-span-2 space-y-1">
                 <label class="text-xs font-bold text-slate-500 uppercase">Plate Number</label>
                 <div class="flex gap-2">
                    <input name="plate_number" id="opPlateInput" class="w-full px-4 py-2 rounded-lg border border-slate-300 focus:border-accent focus:ring-1 focus:ring-accent outline-none transition-colors bg-slate-50" placeholder="ABC-1234">
                    <input type="file" id="opScanPlate" accept="image/*" class="hidden">
                    <button type="button" class="px-3 py-2 rounded-lg bg-orange-100 text-orange-700 hover:bg-orange-200 transition-colors" onclick="document.getElementById('opScanPlate').click()" title="Scan Plate">
                      <i data-lucide="camera" class="w-5 h-5"></i>
                    </button>
                 </div>
                 <div id="opScanStatus" class="text-xs text-slate-500 hidden mt-1">Scanning...</div>
              </div>
            </div>

            <div class="flex gap-3 pt-2">
               <button type="button" class="flex-1 px-4 py-2 rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 font-medium text-sm transition-colors border border-slate-200" onclick="openUpload('franchise')">
                <i data-lucide="upload" class="w-4 h-4 inline mr-1"></i> Upload OR/CR
               </button>
               <button type="submit" class="flex-1 px-4 py-2 rounded-lg bg-primary text-white hover:bg-slate-800 font-medium text-sm shadow-lg shadow-blue-900/20 transition-all transform active:scale-95">
                Submit Application
               </button>
            </div>
          </form>
          <div id="franchiseResult" class="mt-4 p-3 bg-slate-50 rounded-lg text-sm border border-slate-100 hidden"></div>
          
          <div class="mt-6 border-t border-slate-100 pt-4">
            <h4 class="text-xs font-bold text-slate-400 uppercase mb-2">Recent Status</h4>
            <div id="franchiseStatus" class="space-y-2">
               <!-- Populated by JS -->
               <div class="text-sm text-slate-400 italic">No recent applications</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Inspection Modal -->
    <div id="modalInspection" class="fixed inset-0 hidden items-center justify-center z-50 backdrop-blur-sm">
      <div class="absolute inset-0 bg-slate-900/60" onclick="closeAppModal('inspection')"></div>
      <div class="relative w-full max-w-lg mx-auto p-0 rounded-2xl bg-white shadow-2xl border border-slate-200 transform transition-all scale-100">
        <!-- Header -->
        <div class="sticky top-0 bg-white z-10 px-6 py-4 border-b border-slate-100 flex items-center justify-between">
           <div class="flex items-center gap-3">
              <div class="p-2 bg-emerald-50 rounded-lg text-emerald-600">
                <i data-lucide="clipboard-check" class="w-6 h-6"></i>
              </div>
              <div>
                <h3 class="font-bold text-lg text-primary">Vehicle Inspection</h3>
                <p class="text-xs text-slate-500">Schedule Appointment</p>
              </div>
           </div>
           <button class="p-2 rounded-full hover:bg-slate-100 transition-colors" onclick="closeAppModal('inspection')">
             <i data-lucide="x" class="w-5 h-5 text-slate-500"></i>
           </button>
        </div>
        
        <!-- Body -->
        <div class="p-6">
          <form id="formInspection" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div class="space-y-1">
                <label class="text-xs font-bold text-slate-500 uppercase">Plate No.</label>
                <input name="plate_number" class="w-full px-4 py-2 rounded-lg border border-slate-300 focus:border-accent outline-none bg-slate-50" placeholder="ABC-1234">
              </div>
              <div class="space-y-1">
                <label class="text-xs font-bold text-slate-500 uppercase">Date</label>
                <input name="scheduled_at" type="datetime-local" class="w-full px-4 py-2 rounded-lg border border-slate-300 focus:border-accent outline-none bg-slate-50">
              </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
               <div class="space-y-1">
                  <label class="text-xs font-bold text-slate-500 uppercase">Location</label>
                  <input name="location" class="w-full px-4 py-2 rounded-lg border border-slate-300 focus:border-accent outline-none bg-slate-50" placeholder="Center 1">
               </div>
               <div class="space-y-1">
                  <label class="text-xs font-bold text-slate-500 uppercase">Inspector</label>
                  <select name="inspector_id" class="w-full px-4 py-2 rounded-lg border border-slate-300 focus:border-accent outline-none bg-slate-50"></select>
               </div>
            </div>

            <div class="flex gap-3 pt-2">
              <button type="button" class="px-4 py-2 rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 font-medium text-sm transition-colors border border-slate-200" onclick="precheckOpen()">
                <i data-lucide="bot" class="w-4 h-4 inline mr-1"></i> AI Check
              </button>
              <button type="submit" class="flex-1 px-4 py-2 rounded-lg bg-secondary text-white hover:bg-slate-600 font-medium text-sm shadow-md transition-all">
                Schedule Now
              </button>
            </div>
          </form>
          <div id="inspectionResult" class="mt-2 text-sm text-center"></div>
          <div id="inspectionStatus" class="mt-4 pt-4 border-t border-slate-100 text-sm"></div>
        </div>
      </div>
    </div>

    <!-- Terminal Modal -->
    <div id="modalTerminal" class="fixed inset-0 hidden items-center justify-center z-50 backdrop-blur-sm">
      <div class="absolute inset-0 bg-slate-900/60" onclick="closeAppModal('terminal')"></div>
      <div class="relative w-full max-w-lg mx-auto p-0 rounded-2xl bg-white shadow-2xl border border-slate-200 transform transition-all scale-100">
        <!-- Header -->
        <div class="sticky top-0 bg-white z-10 px-6 py-4 border-b border-slate-100 flex items-center justify-between">
           <div class="flex items-center gap-3">
              <div class="p-2 bg-purple-50 rounded-lg text-purple-600">
                <i data-lucide="map-pin" class="w-6 h-6"></i>
              </div>
              <div>
                <h3 class="font-bold text-lg text-primary">Terminal Enrollment</h3>
                <p class="text-xs text-slate-500">Register Facility</p>
              </div>
           </div>
           <button class="p-2 rounded-full hover:bg-slate-100 transition-colors" onclick="closeAppModal('terminal')">
             <i data-lucide="x" class="w-5 h-5 text-slate-500"></i>
           </button>
        </div>
        
        <!-- Body -->
        <div class="p-6">
          <form id="formTerminal" class="space-y-4">
            <div class="space-y-1">
              <label class="text-xs font-bold text-slate-500 uppercase">Terminal Name</label>
              <input name="name" class="w-full px-4 py-2 rounded-lg border border-slate-300 focus:border-accent outline-none bg-slate-50" placeholder="e.g. Central Terminal">
            </div>
            
            <div class="grid grid-cols-2 gap-4">
               <div class="space-y-1">
                  <label class="text-xs font-bold text-slate-500 uppercase">Type</label>
                  <select name="type" class="w-full px-4 py-2 rounded-lg border border-slate-300 focus:border-accent outline-none bg-slate-50">
                    <option value="Terminal">Terminal</option>
                    <option value="Parking">Parking</option>
                    <option value="LoadingBay">Loading Bay</option>
                  </select>
               </div>
               <div class="space-y-1">
                  <label class="text-xs font-bold text-slate-500 uppercase">Capacity</label>
                  <input name="capacity" type="number" min="0" class="w-full px-4 py-2 rounded-lg border border-slate-300 focus:border-accent outline-none bg-slate-50" placeholder="50">
               </div>
            </div>

             <div class="space-y-1">
              <label class="text-xs font-bold text-slate-500 uppercase">Applicant</label>
              <input name="applicant" class="w-full px-4 py-2 rounded-lg border border-slate-300 focus:border-accent outline-none bg-slate-50" placeholder="Name">
            </div>

            <button type="submit" class="w-full px-4 py-2 rounded-lg bg-accent text-white hover:bg-amber-700 font-medium text-sm shadow-md transition-all">
              Submit Enrollment
            </button>
          </form>
          <div id="terminalResult" class="mt-2 text-sm text-center"></div>
          <div id="terminalStatus" class="mt-4 pt-4 border-t border-slate-100 text-sm"></div>
        </div>
      </div>
    </div>

    <!-- Profile Modal -->
    <div id="modalProfile" class="fixed inset-0 hidden items-center justify-center z-[70] backdrop-blur-sm">
      <div class="absolute inset-0 bg-slate-900/60" onclick="closeProfile()"></div>
      <div class="relative w-full max-w-md mx-auto p-0 rounded-2xl bg-white shadow-2xl border border-slate-200 transform transition-all scale-100 overflow-hidden">
        
        <!-- Profile View -->
         <div id="profileView" class="p-6 text-center">
            <button class="absolute top-4 right-4 p-2 rounded-full hover:bg-slate-100 transition-colors" onclick="closeProfile()">
              <i data-lucide="x" class="w-5 h-5 text-slate-500"></i>
            </button>
            
            <div class="relative w-24 h-24 mx-auto mb-4 group">
              <div class="w-24 h-24 rounded-full bg-slate-100 border-4 border-white shadow-lg overflow-hidden relative">
                <div class="absolute inset-0 flex items-center justify-center bg-slate-200 text-slate-400 font-bold text-3xl">OP</div>
                <img id="profileImagePreview" src="https://ui-avatars.com/api/?name=Juan+Dela+Cruz&background=0D8ABC&color=fff&size=128" alt="Profile" class="absolute inset-0 w-full h-full object-cover opacity-100 transition-opacity duration-300">
              </div>
              
              <!-- Pencil Icon / Upload Trigger -->
              <button onclick="document.getElementById('profileUpload').click()" class="absolute bottom-0 right-0 p-2 bg-white rounded-full shadow-md border border-slate-200 hover:bg-slate-50 text-slate-600 transition-all transform hover:scale-110 z-10">
                <i data-lucide="pencil" class="w-4 h-4"></i>
              </button>
              <input type="file" id="profileUpload" accept="image/*" class="hidden">
            </div>
            
            <h3 class="text-xl font-bold text-primary">Operator Account</h3>
            <p class="text-slate-500 text-sm mb-6">Transport Operator</p>
            
            <div class="space-y-3 text-left mb-8">
              <div class="p-3 bg-slate-50 rounded-lg flex items-center gap-3">
                <i data-lucide="user" class="w-5 h-5 text-slate-400"></i>
                <div>
                  <div class="text-xs text-slate-400 uppercase">Full Name</div>
                  <div class="font-medium text-slate-700">Juan Dela Cruz</div>
                </div>
              </div>
              <div class="p-3 bg-slate-50 rounded-lg flex items-center gap-3">
                <i data-lucide="mail" class="w-5 h-5 text-slate-400"></i>
                <div>
                  <div class="text-xs text-slate-400 uppercase">Email Address</div>
                  <div class="font-medium text-slate-700">operator@example.com</div>
                </div>
              </div>
            </div>
            
            <button onclick="toggleProfileEdit(true)" class="w-full py-3 rounded-lg bg-primary text-white font-bold hover:bg-emerald-800 transition-colors shadow-lg">
              Edit Credentials
            </button>
         </div>

        <!-- Profile Edit -->
        <div id="profileEdit" class="hidden p-6">
           <div class="flex items-center justify-between mb-6">
             <h3 class="font-bold text-lg text-primary">Edit Profile</h3>
             <button class="p-2 rounded-full hover:bg-slate-100 transition-colors" onclick="toggleProfileEdit(false)">
               <i data-lucide="arrow-left" class="w-5 h-5 text-slate-500"></i>
             </button>
           </div>
           
           <form id="formProfile" class="space-y-4">
             <div class="space-y-1">
               <label class="text-xs font-bold text-slate-500 uppercase">Full Name</label>
               <input name="name" value="Juan Dela Cruz" class="w-full px-4 py-2 rounded-lg border border-slate-300 focus:border-accent outline-none bg-slate-50">
             </div>
             <div class="space-y-1">
               <label class="text-xs font-bold text-slate-500 uppercase">Email</label>
               <input name="email" type="email" value="operator@example.com" class="w-full px-4 py-2 rounded-lg border border-slate-300 focus:border-accent outline-none bg-slate-50">
             </div>
             <div class="space-y-1">
               <label class="text-xs font-bold text-slate-500 uppercase">New Password</label>
               <input name="password" type="password" placeholder="Leave blank to keep current" class="w-full px-4 py-2 rounded-lg border border-slate-300 focus:border-accent outline-none bg-slate-50">
             </div>
             <div class="space-y-1">
               <label class="text-xs font-bold text-slate-500 uppercase">Retype Password</label>
               <input name="confirm_password" type="password" placeholder="Confirm new password" class="w-full px-4 py-2 rounded-lg border border-slate-300 focus:border-accent outline-none bg-slate-50">
             </div>
             
             <button type="button" class="w-full py-2 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 font-medium transition-colors flex items-center justify-center gap-2 border border-blue-200 mt-2" onclick="alert('OTP Sent! (This feature will be available soon)')">
               <i data-lucide="smartphone" class="w-4 h-4"></i> Send OTP Verification
             </button>
             
             <div class="pt-4 flex gap-3">
               <button type="button" onclick="toggleProfileEdit(false)" class="flex-1 py-2 rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 font-medium">Cancel</button>
               <button type="submit" class="flex-1 py-2 rounded-lg bg-amber-600 text-white hover:bg-amber-700 font-medium shadow-md">Save Changes</button>
             </div>
           </form>
        </div>
        
      </div>
    </div>
      <div class="max-w-6xl mx-auto px-4">
        <p>&copy; <?php echo date('Y'); ?> Transport Management Module. All rights reserved.</p>
        <p class="mt-2">Operator Portal v2.0</p>
      </div>
    </footer>
  </div>

  <script src="assets/app.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      if (window.lucide && window.lucide.createIcons) window.lucide.createIcons();
    });
  </script>
</body>
</html>
