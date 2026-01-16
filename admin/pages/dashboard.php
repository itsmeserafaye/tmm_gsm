<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
$db = db();

$terminalsCount = 0;
$routesCount = 0;
$ticketsToday = 0;
$parkingPaymentsToday = 0;

$r1 = $db->query("SELECT COUNT(*) AS c FROM terminals");
if ($r1 && ($row = $r1->fetch_assoc())) $terminalsCount = (int)($row['c'] ?? 0);
$r2 = $db->query("SELECT COUNT(*) AS c FROM routes");
if ($r2 && ($row = $r2->fetch_assoc())) $routesCount = (int)($row['c'] ?? 0);
$r3 = $db->query("SELECT COUNT(*) AS c FROM tickets WHERE DATE(date_issued)=CURDATE()");
if ($r3 && ($row = $r3->fetch_assoc())) $ticketsToday = (int)($row['c'] ?? 0);
$r4 = $db->query("SELECT SUM(amount) AS s FROM parking_transactions WHERE DATE(created_at)=CURDATE()");
if ($r4 && ($row = $r4->fetch_assoc())) $parkingPaymentsToday = (float)($row['s'] ?? 0);

// KPI Data for System Overview
$totalVehicles = $db->query("SELECT COUNT(*) AS c FROM vehicles")->fetch_assoc()['c'] ?? 0;
$totalOperators = $db->query("SELECT COUNT(*) AS c FROM operators")->fetch_assoc()['c'] ?? 0;
$activeFranchises = $db->query("SELECT COUNT(*) AS c FROM franchise_applications WHERE status='Endorsed'")->fetch_assoc()['c'] ?? 0;
$pendingFranchises = $db->query("SELECT COUNT(*) AS c FROM franchise_applications WHERE status='Pending'")->fetch_assoc()['c'] ?? 0;
$totalViolations = $db->query("SELECT COUNT(*) AS c FROM tickets")->fetch_assoc()['c'] ?? 0;
$unpaidFines = $db->query("SELECT COUNT(*) AS c FROM tickets WHERE status IN ('Pending','Validated','Escalated')")->fetch_assoc()['c'] ?? 0;
$totalRevenue = $db->query("SELECT SUM(amount) AS s FROM parking_transactions")->fetch_assoc()['s'] ?? 0;
?>

<div class="mx-auto max-w-7xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100">
  <!-- Header Section -->
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between mb-8 border-b border-slate-200 dark:border-slate-700 pb-6">
    <div>
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">
        Transport & Mobility Intelligence
      </h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Predictive analytics for PUV demand forecasting and deployment.</p>
    </div>
  </div>

  <!-- Stats Grid -->
  <!-- Consolidated into System Overview at the bottom -->

  <!-- Context Widgets -->
  <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
    <!-- Forecast Readiness -->
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="flex items-center gap-3 mb-3">
        <div class="p-1.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
          <i data-lucide="brain-circuit" class="w-4 h-4"></i>
        </div>
        <div class="text-sm font-semibold text-slate-700 dark:text-slate-300">Forecast Readiness</div>
      </div>
      <div id="readinessValue" class="text-2xl font-bold text-slate-900 dark:text-white">—</div>
      <div id="readinessHint" class="text-xs text-slate-500 mt-1"></div>
    </div>

    <!-- Weather Now -->
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="flex items-center gap-3 mb-3">
        <div class="p-1.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
          <i data-lucide="cloud-rain" class="w-4 h-4"></i>
        </div>
        <div class="text-sm font-semibold text-slate-700 dark:text-slate-300">Weather Now</div>
      </div>
      <div id="weatherNowValue" class="text-2xl font-bold text-slate-900 dark:text-white">—</div>
      <div id="weatherNowHint" class="text-xs text-slate-500 mt-1"></div>
    </div>

    <!-- Traffic Now -->
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="flex items-center gap-3 mb-3">
        <div class="p-1.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
          <i data-lucide="car" class="w-4 h-4"></i>
        </div>
        <div class="text-sm font-semibold text-slate-700 dark:text-slate-300">Traffic Now</div>
      </div>
      <div id="trafficNowValue" class="text-2xl font-bold text-slate-900 dark:text-white">—</div>
      <div id="trafficNowHint" class="text-xs text-slate-500 mt-1"></div>
    </div>

    <!-- Upcoming Event -->
    <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="flex items-center gap-3 mb-3">
        <div class="p-1.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
          <i data-lucide="calendar-days" class="w-4 h-4"></i>
        </div>
        <div class="text-sm font-semibold text-slate-700 dark:text-slate-300">Upcoming Event</div>
      </div>
      <div id="eventsValue" class="text-2xl font-bold text-slate-900 dark:text-white">—</div>
      <div id="eventsHint" class="text-xs text-slate-500 mt-1"></div>
    </div>
  </div>

  <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
    <div class="xl:col-span-2 space-y-6">
      <!-- Main Forecast Chart -->
      <div class="p-6 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
        <div class="flex items-start justify-between gap-4 mb-6">
          <div>
            <h2 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
              <i data-lucide="trending-up" class="w-5 h-5 text-blue-600 dark:text-blue-400"></i>
              PUV Demand Forecast
            </h2>
            <div class="text-sm text-slate-500 mt-1">AI-Projected demand for the next 24 hours</div>
          </div>
          <div class="text-right px-4 py-2 rounded-md bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600">
            <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Model Accuracy</div>
            <div id="forecastAccuracy" class="text-xl font-bold text-emerald-600">—</div>
            <div id="forecastAccuracyHint" class="text-[10px] text-slate-400"></div>
          </div>
        </div>

        <div class="flex items-center justify-between mb-4">
          <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Forecast Area Type</div>
          <div class="flex bg-slate-100 dark:bg-slate-800 p-1 rounded-lg border border-slate-200 dark:border-slate-700">
            <button id="btnAreaTerminal" type="button" class="px-4 py-2 rounded-md bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm border border-slate-200 dark:border-slate-600 text-sm font-semibold transition-all">Terminals</button>
            <button id="btnAreaRoute" type="button" class="px-4 py-2 rounded-md text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 text-sm font-medium transition-all">Routes</button>
          </div>
        </div>
        
        <div class="relative h-64 w-full bg-slate-50 dark:bg-slate-900/50 rounded-md p-4 border border-slate-200 dark:border-slate-700">
          <!-- Grid lines -->
          <div class="absolute inset-0 flex flex-col justify-between p-4 pointer-events-none opacity-20">
            <div class="w-full h-px bg-slate-400 dashed"></div>
            <div class="w-full h-px bg-slate-400 dashed"></div>
            <div class="w-full h-px bg-slate-400 dashed"></div>
            <div class="w-full h-px bg-slate-400 dashed"></div>
            <div class="w-full h-px bg-slate-400 dashed"></div>
          </div>
          <div id="forecastChart" class="relative z-10 grid grid-cols-12 gap-2 items-end h-full"></div>
        </div>
        <div id="forecastChartLegend" class="mt-4 text-xs font-medium text-slate-500 flex items-center justify-center gap-6">
          <div class="flex items-center gap-2">
            <span class="w-3 h-3 rounded-sm bg-blue-600"></span> 
            <span>Normal Demand</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="w-3 h-3 rounded-sm bg-cyan-600"></span> 
            <span>Rain Impact</span>
          </div>
        </div>
      </div>

      <!-- Alerts Section -->
      <div class="p-6 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center gap-3">
            <div class="p-1.5 rounded bg-rose-50 dark:bg-rose-900/20 text-rose-600 dark:text-rose-400">
              <i data-lucide="alert-triangle" class="w-5 h-5"></i>
            </div>
            <div>
              <h3 class="text-base font-bold text-slate-900 dark:text-white">High-demand Alerts</h3>
            </div>
          </div>
          <span class="text-xs px-2 py-1 rounded-md bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 font-semibold border border-slate-200 dark:border-slate-600">Next 6 Hours</span>
        </div>
        <div id="forecastSpikes" class="space-y-3"></div>
      </div>

      <!-- Route Supply -->
      <div class="p-6 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
        <div class="flex items-center justify-between mb-6">
          <div class="flex items-center gap-3">
            <div class="p-1.5 rounded bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-400">
              <i data-lucide="bus" class="w-5 h-5"></i>
            </div>
            <div>
              <h3 class="text-base font-bold text-slate-900 dark:text-white">Route Supply Snapshot</h3>
              <div class="text-xs text-slate-500">Authorized units vs. Demand</div>
            </div>
          </div>
          <div id="routeSupplyTitle" class="text-sm font-semibold text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-700 px-3 py-1.5 rounded border border-slate-200 dark:border-slate-600"></div>
        </div>
        
        <div class="overflow-hidden rounded-md border border-slate-200 dark:border-slate-700">
          <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-700">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Route</th>
                <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Authorized Units</th>
              </tr>
            </thead>
            <tbody id="routeSupplyBody" class="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-800"></tbody>
          </table>
        </div>
        <div id="routeSupplyTotal" class="mt-3 text-right text-xs font-bold text-slate-500 uppercase"></div>
      </div>
    </div>

    <!-- Right Column: Data Inputs & AI Insights -->
    <div class="space-y-6">
      <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm sticky top-6">
        <div class="flex items-center justify-between mb-5">
          <div>
            <h2 class="text-base font-bold text-slate-900 dark:text-white">Data Inputs</h2>
            <div class="text-xs text-slate-500">Train the AI with real-time logs</div>
          </div>
        </div>
        
        <?php if (!has_permission('analytics.train')): ?>
          <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-4 text-sm text-slate-600 dark:text-slate-300">
            You can view forecasts, but you do not have access to submit real-time demand logs.
          </div>
        <?php else: ?>
        <form id="demand-log-form" class="space-y-4">
          <div class="space-y-1">
            <label class="text-xs font-semibold text-slate-500 uppercase">Area Type</label>
            <div class="relative">
              <select id="demand-area-type" name="area_type" class="w-full pl-3 pr-8 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-all outline-none">
                <option value="terminal">Terminal</option>
                <option value="route">Route</option>
              </select>
            </div>
          </div>

          <div class="space-y-1">
            <label class="text-xs font-semibold text-slate-500 uppercase">Location</label>
            <div class="relative">
              <select id="demand-area-ref" name="area_ref" class="w-full pl-3 pr-8 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-all outline-none"></select>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1">
              <label class="text-xs font-semibold text-slate-500 uppercase">Hour</label>
              <input id="demand-observed-at" name="observed_at" type="datetime-local" class="w-full px-2 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-md text-xs focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none">
            </div>
            <div class="space-y-1">
              <label class="text-xs font-semibold text-slate-500 uppercase">Count</label>
              <input id="demand-count" name="demand_count" type="number" min="0" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none" placeholder="0">
            </div>
          </div>

          <button type="submit" class="w-full py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold shadow-sm transition-all flex items-center justify-center gap-2 text-sm">
            <i data-lucide="save" class="w-4 h-4"></i>
            Save Observation
          </button>
          <div id="demand-log-result" class="text-center text-xs font-bold min-h-[1.5em]"></div>
        </form>
        <?php endif; ?>

        <div class="mt-6 pt-6 border-t border-slate-200 dark:border-slate-700">
          <div class="flex items-center gap-2 mb-4">
            <i data-lucide="lightbulb" class="w-4 h-4 text-amber-500"></i>
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">AI Insights</h3>
          </div>
          
          <div class="space-y-4">
            <div class="rounded-md border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-4">
              <div class="flex items-center gap-2 mb-2 text-slate-700 dark:text-slate-200">
                <i data-lucide="zap" class="w-4 h-4 text-amber-500"></i>
                <span class="text-xs font-bold uppercase">Hotspots (Next 6 Hours)</span>
              </div>
              <div id="aiHotspots" class="space-y-2"></div>
            </div>

            <div class="rounded-md border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-4">
              <div class="flex items-center gap-2 mb-2 text-slate-700 dark:text-slate-200">
                <i data-lucide="clipboard-list" class="w-4 h-4 text-blue-500"></i>
                <span class="text-xs font-bold uppercase">Recommended Actions</span>
              </div>
              <ul id="aiActions" class="space-y-2 text-sm text-slate-600 dark:text-slate-300"></ul>
            </div>

            <div class="rounded-md border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-4">
              <div class="flex items-center gap-2 mb-2 text-slate-700 dark:text-slate-200">
                <i data-lucide="pause-circle" class="w-4 h-4 text-emerald-500"></i>
                <span class="text-xs font-bold uppercase">Low Demand Areas</span>
              </div>
              <ul id="aiUnderutilized" class="space-y-2 text-sm text-slate-600 dark:text-slate-300"></ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- System Overview KPIs -->
  <div class="mt-8 mb-8">
    <div class="flex items-center gap-3 mb-4">
      <div class="p-1.5 rounded bg-slate-900 dark:bg-white text-white dark:text-slate-900">
        <i data-lucide="activity" class="w-5 h-5"></i>
      </div>
      <div>
        <h2 class="text-lg font-bold text-slate-800 dark:text-white">System Overview</h2>
        <div class="text-sm text-slate-500">Key Performance Indicators across all modules</div>
      </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <!-- Active Terminals -->
      <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm hover:border-blue-400 transition-colors">
        <div class="flex items-center justify-between mb-2">
          <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Active Terminals</div>
          <i data-lucide="map-pin" class="w-4 h-4 text-blue-600 dark:text-blue-400"></i>
        </div>
        <div class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo (int)$terminalsCount; ?></div>
        <div class="mt-1 text-xs text-slate-500">Registered Locations</div>
      </div>

      <!-- Routes -->
      <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm hover:border-indigo-400 transition-colors">
        <div class="flex items-center justify-between mb-2">
          <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Routes</div>
          <i data-lucide="route" class="w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
        </div>
        <div class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo (int)$routesCount; ?></div>
        <div class="mt-1 text-xs text-slate-500">Registered Routes</div>
      </div>

      <!-- Total Vehicles -->
      <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm hover:border-sky-400 transition-colors">
        <div class="flex items-center justify-between mb-2">
          <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Vehicles</div>
          <i data-lucide="bus" class="w-4 h-4 text-sky-600 dark:text-sky-400"></i>
        </div>
        <div class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo number_format($totalVehicles); ?></div>
        <div class="mt-1 text-xs text-slate-500">Registered Units</div>
      </div>

      <!-- Total Operators -->
      <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm hover:border-violet-400 transition-colors">
        <div class="flex items-center justify-between mb-2">
          <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Operators</div>
          <i data-lucide="users" class="w-4 h-4 text-violet-600 dark:text-violet-400"></i>
        </div>
        <div class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo number_format($totalOperators); ?></div>
        <div class="mt-1 text-xs text-slate-500">Active Accounts</div>
      </div>

      <!-- Active Franchises -->
      <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm hover:border-emerald-400 transition-colors">
        <div class="flex items-center justify-between mb-2">
          <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Franchises</div>
          <i data-lucide="file-check" class="w-4 h-4 text-emerald-600 dark:text-emerald-400"></i>
        </div>
        <div class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo number_format($activeFranchises); ?></div>
        <div class="mt-1 text-xs text-slate-500">Endorsed & Valid</div>
      </div>

      <!-- Pending Applications -->
      <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm hover:border-orange-400 transition-colors">
        <div class="flex items-center justify-between mb-2">
          <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Pending Apps</div>
          <i data-lucide="file-clock" class="w-4 h-4 text-orange-600 dark:text-orange-400"></i>
        </div>
        <div class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo number_format($pendingFranchises); ?></div>
        <div class="mt-1 text-xs text-slate-500">Awaiting Review</div>
      </div>

      <!-- Tickets Today -->
      <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm hover:border-amber-400 transition-colors">
        <div class="flex items-center justify-between mb-2">
          <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Tickets Today</div>
          <i data-lucide="ticket" class="w-4 h-4 text-amber-600 dark:text-amber-400"></i>
        </div>
        <div class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo (int)$ticketsToday; ?></div>
        <div class="mt-1 text-xs text-slate-500">Total Violations: <?php echo number_format($totalViolations); ?></div>
      </div>

      <!-- Unpaid Fines -->
      <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm hover:border-rose-400 transition-colors">
        <div class="flex items-center justify-between mb-2">
          <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Unpaid Fines</div>
          <i data-lucide="alert-octagon" class="w-4 h-4 text-rose-600 dark:text-rose-400"></i>
        </div>
        <div class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo number_format($unpaidFines); ?></div>
        <div class="mt-1 text-xs text-slate-500">Action Required</div>
      </div>

      <!-- Parking Payments Today -->
      <div class="p-5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm hover:border-teal-400 transition-colors">
        <div class="flex items-center justify-between mb-2">
          <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Revenue Today</div>
          <i data-lucide="wallet" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
        </div>
        <div class="text-2xl font-bold text-slate-900 dark:text-white">₱<?php echo number_format((float)$parkingPaymentsToday, 2); ?></div>
        <div class="mt-1 text-xs text-slate-500">Total: ₱<?php echo number_format((float)$totalRevenue, 0); ?></div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var btnT = document.getElementById('btnAreaTerminal');
  var btnR = document.getElementById('btnAreaRoute');
  var accuracyEl = document.getElementById('forecastAccuracy');
  var accuracyHint = document.getElementById('forecastAccuracyHint');
  var chartEl = document.getElementById('forecastChart');
  var chartLegend = document.getElementById('forecastChartLegend');
  var spikesEl = document.getElementById('forecastSpikes');
  var routeSupplyTitle = document.getElementById('routeSupplyTitle');
  var routeSupplyBody = document.getElementById('routeSupplyBody');
  var routeSupplyTotal = document.getElementById('routeSupplyTotal');
  var demandForm = document.getElementById('demand-log-form');
  var demandType = document.getElementById('demand-area-type');
  var demandRef = document.getElementById('demand-area-ref');
  var demandAt = document.getElementById('demand-observed-at');
  var demandCount = document.getElementById('demand-count');
  var demandResult = document.getElementById('demand-log-result');
  var readinessValue = document.getElementById('readinessValue');
  var readinessHint = document.getElementById('readinessHint');
  var weatherNowValue = document.getElementById('weatherNowValue');
  var weatherNowHint = document.getElementById('weatherNowHint');
  var trafficNowValue = document.getElementById('trafficNowValue');
  var trafficNowHint = document.getElementById('trafficNowHint');
  var eventsValue = document.getElementById('eventsValue');
  var eventsHint = document.getElementById('eventsHint');
  var aiHotspots = document.getElementById('aiHotspots');
  var aiActions = document.getElementById('aiActions');
  var aiUnderutilized = document.getElementById('aiUnderutilized');
  var currentType = 'terminal';
  var areas = { terminal: [], route: [] };
  var lastSpikes = [];
  var insightsByLabel = {};
  var bestTerminalLabel = '';

  function setActive(type) {
    currentType = type;
    if (btnT && btnR) {
      if (type === 'terminal') {
        btnT.className = 'px-4 py-2 rounded-md bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm border border-slate-200 dark:border-slate-600 text-sm font-semibold transition-all';
        btnR.className = 'px-4 py-2 rounded-md text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 text-sm font-medium transition-all';
      } else {
        btnR.className = 'px-4 py-2 rounded-md bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm border border-slate-200 dark:border-slate-600 text-sm font-semibold transition-all';
        btnT.className = 'px-4 py-2 rounded-md text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 text-sm font-medium transition-all';
      }
    }
    if (demandType && (type === 'terminal' || type === 'route')) {
      demandType.value = type;
      populateAreas(type);
    }
  }

  function renderBars(points) {
    if (!chartEl) return;
    chartEl.innerHTML = '';
    if (!points || !points.length) return;
    var maxV = 1;
    points.forEach(function (p) { if (p.predicted > maxV) maxV = p.predicted; });
    points.slice(0, 24).forEach(function (p) {
      var h = Math.max(4, Math.round((p.predicted / maxV) * 85));
      
      var wrapper = document.createElement('div');
      wrapper.className = 'flex flex-col justify-end h-full min-w-[32px] shrink-0 group relative';
      
      var bar = document.createElement('div');
      // Default: Blue
      var bgClass = 'bg-blue-600';
      
      var rain = (p.weather && p.weather.precip_mm) ? Number(p.weather.precip_mm) : 0;
      var prob = (p.weather && p.weather.precip_prob !== null && p.weather.precip_prob !== undefined) ? Number(p.weather.precip_prob) : null;
      var evt = (p.event && p.event.title) ? p.event.title : '';
      
      // Rain: Cyan
      if (prob !== null && prob >= 60) {
        bgClass = 'bg-cyan-600';
      }

      bar.className = 'w-full rounded-sm hover:opacity-90 transition-all cursor-help ' + bgClass;
      bar.style.height = h + '%';
      
      var parts = [p.hour_label, 'Predicted: ' + p.predicted];
      if (prob !== null) parts.push('Rain Prob: ' + prob + '%');
      if (rain) parts.push('Rain: ' + rain + 'mm');
      if (evt) parts.push('Event: ' + evt);
      
      bar.title = parts.join('\n');
      
      var label = document.createElement('div');
      label.className = 'mt-1 text-[10px] font-medium text-slate-500 text-center truncate';
      label.textContent = p.hour_label.split(' ')[0] || p.hour_label;

      wrapper.appendChild(bar);
      wrapper.appendChild(label);
      chartEl.appendChild(wrapper);
    });
  }

  function renderSpikes(spikes) {
    if (!spikesEl) return;
    spikesEl.innerHTML = '';
    if (!spikes || !spikes.length) {
      spikesEl.innerHTML = '<div class="text-sm font-medium text-slate-500 italic">No critical spikes detected in the next 6 hours.</div>';
      return;
    }
    spikes.forEach(function (s) {
      var info = insightsByLabel && s && s.area_label ? insightsByLabel[s.area_label] : null;
      var supplyLine = '';
      var badge = '<span class="text-[10px] font-bold px-2 py-1 rounded-md bg-rose-600 text-white">HIGH DEMAND</span>';
      
      if (info && info.supply_units !== null && info.supply_units !== undefined) {
        supplyLine = '<span class="text-slate-400"> • Supply: </span><span class="font-bold text-slate-700 dark:text-slate-300">' + info.supply_units + ' units</span>';
        if (info.load_status === 'potential_over_demand') {
          badge = '<span class="text-[10px] font-bold px-2 py-1 rounded-md bg-amber-600 text-white">OVERLOAD</span>';
        } else if (info.load_status === 'normal') {
          badge = '<span class="text-[10px] font-bold px-2 py-1 rounded-md bg-emerald-600 text-white">COVERED</span>';
        }
      }
      
      var item = document.createElement('div');
      item.className = 'p-3 rounded-md border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 flex items-center justify-between gap-4';
      item.innerHTML =
        '<div class="min-w-0 flex-1">' +
          '<div class="font-bold text-sm text-slate-900 dark:text-white truncate">' + s.area_label + '</div>' +
          '<div class="text-xs text-slate-500">' + s.peak_hour + ' • Predicted: <span class="text-slate-900 dark:text-slate-200 font-bold">' + s.predicted_peak + '</span>' + supplyLine + '</div>' +
        '</div>' +
        '<div class="shrink-0">' +
          badge +
        '</div>';
      spikesEl.appendChild(item);
    });
  }

  function setAccuracyUI(data) {
    if (!accuracyEl) return;
    var target = (data && data.accuracy_target) ? Number(data.accuracy_target) : 80;
    var acc = (data && data.accuracy) ? Number(data.accuracy) : 0;
    var enough = !!(data && data.accuracy_ok);
    if (!data || !data.ok) {
      accuracyEl.textContent = '—';
      if (accuracyHint) accuracyHint.textContent = '';
      return;
    }
    accuracyEl.textContent = acc.toFixed(1) + '%';
    if (enough && acc >= target) {
      accuracyEl.className = 'text-xl font-bold text-emerald-600';
      if (accuracyHint) accuracyHint.textContent = 'TARGET MET (≥' + target + '%)';
    } else {
      accuracyEl.className = 'text-xl font-bold text-amber-600';
      if (accuracyHint) accuracyHint.textContent = 'TRAINING...';
    }
  }

  function setReadinessUI(readiness) {
    if (!readinessValue || !readinessHint) return;
    if (!readiness) {
      readinessValue.textContent = '—';
      readinessHint.textContent = '';
      return;
    }
    var ok = !!readiness.ok;
    readinessValue.textContent = ok ? 'Ready' : 'Training';
    readinessValue.className = ok ? 'text-2xl font-bold text-emerald-600' : 'text-2xl font-bold text-amber-600';
    readinessHint.textContent = ok ? 'AI Model Active' : 'Gathering Data';
  }

  function setWeatherNowUI(weather) {
    if (!weatherNowValue || !weatherNowHint) return;
    if (!weather || !weather.current) {
      weatherNowValue.textContent = '—';
      weatherNowHint.textContent = '';
      return;
    }
    var t = weather.current.temperature;
    weatherNowValue.textContent = (t !== undefined ? t + '°C' : '—');
    weatherNowHint.textContent = (weather.label || 'Local Area');
  }

  function setTrafficNowUI(traffic) {
    if (!trafficNowValue || !trafficNowHint) return;
    var cong = traffic && traffic.congestion !== undefined ? traffic.congestion : null;
    var inc = traffic && traffic.incidents_count !== undefined ? traffic.incidents_count : null;
    if (cong === null || cong === undefined) {
      var provider = traffic && traffic.provider ? String(traffic.provider) : '';
      var fs = traffic && traffic.flow_status !== undefined ? traffic.flow_status : null;
      var is = traffic && traffic.incidents_status !== undefined ? traffic.incidents_status : null;
      trafficNowValue.textContent = provider ? 'No data' : '—';
      trafficNowValue.className = 'text-2xl font-bold text-slate-900 dark:text-white';
      var hint = traffic && traffic.label ? String(traffic.label) : '';
      if (provider) hint = (hint ? (hint + ' • ') : '') + 'Provider: ' + provider;
      if (fs !== null || is !== null) hint += (hint ? ' • ' : '') + 'HTTP: ' + String(fs || '—') + '/' + String(is || '—');
      trafficNowHint.textContent = hint;
      return;
    }
    var pct = Math.round(Number(cong) * 100);
    var level = 'Low';
    var color = 'text-emerald-600';
    if (pct >= 60) { level = 'Severe'; color = 'text-rose-600'; }
    else if (pct >= 40) { level = 'High'; color = 'text-amber-600'; }
    else if (pct >= 20) { level = 'Moderate'; color = 'text-orange-500'; }
    trafficNowValue.textContent = level + ' (' + pct + '%)';
    trafficNowValue.className = 'text-2xl font-bold ' + color;
    var hint = (traffic.label || 'Local Area');
    if (inc !== null && inc !== undefined) hint += ' • Incidents: ' + inc;
    trafficNowHint.textContent = hint;
  }

  function setEventsUI(events) {
    if (!eventsValue || !eventsHint) return;
    if (!events || !events.length) {
      eventsValue.textContent = 'None';
      eventsHint.textContent = 'No upcoming holidays';
      return;
    }
    eventsValue.textContent = events[0].date;
    eventsHint.textContent = events[0].title;
  }

  function renderTextList(listEl, items, emptyText) {
    if (!listEl) return;
    listEl.innerHTML = '';
    if (!items || !items.length) {
      var li = document.createElement('li');
      li.className = 'text-slate-400 italic';
      li.textContent = emptyText || 'No data.';
      listEl.appendChild(li);
      return;
    }
    items.slice(0, 6).forEach(function (t) {
      var li = document.createElement('li');
      li.className = 'flex gap-2.5 items-start';
      var dot = document.createElement('span');
      dot.className = 'mt-1.5 h-1.5 w-1.5 rounded-full bg-slate-400 shrink-0';
      var text = document.createElement('span');
      text.className = 'leading-snug';
      text.textContent = String(t);
      li.appendChild(dot);
      li.appendChild(text);
      listEl.appendChild(li);
    });
  }

  function renderHotspots(container, hotspots) {
    if (!container) return;
    container.innerHTML = '';
    if (!hotspots || !hotspots.length) {
      var empty = document.createElement('div');
      empty.className = 'text-sm text-slate-400 italic';
      empty.textContent = 'No spikes detected for the next 6 hours.';
      container.appendChild(empty);
      return;
    }

    hotspots.slice(0, 4).forEach(function (h) {
      var loc = h && h.area_label ? String(h.area_label) : '';
      var time = h && h.peak_hour ? String(h.peak_hour) : '';
      var pred = (h && h.predicted_peak !== undefined) ? Number(h.predicted_peak) : 0;
      var sev = h && h.severity ? String(h.severity) : 'medium';
      var extra = (h && h.recommended_extra_units !== null && h.recommended_extra_units !== undefined) ? Number(h.recommended_extra_units) : null;
      var drivers = (h && Array.isArray(h.drivers)) ? h.drivers.slice(0, 2).map(String) : [];
      var routePlan = (h && Array.isArray(h.route_plan)) ? h.route_plan.slice(0, 3) : [];

      var badgeClass = 'bg-slate-600';
      if (sev === 'critical') badgeClass = 'bg-rose-600';
      else if (sev === 'high') badgeClass = 'bg-amber-600';
      else if (sev === 'medium') badgeClass = 'bg-orange-500';

      var row = document.createElement('div');
      row.className = 'p-3 rounded-md border border-slate-200 dark:border-slate-700 bg-white/70 dark:bg-slate-900/20';

      var top = document.createElement('div');
      top.className = 'flex items-start justify-between gap-3';

      var left = document.createElement('div');
      left.className = 'min-w-0';

      var title = document.createElement('div');
      title.className = 'font-bold text-sm text-slate-900 dark:text-white truncate';
      title.textContent = loc;

      var meta = document.createElement('div');
      meta.className = 'mt-0.5 text-xs text-slate-500 dark:text-slate-400';
      meta.textContent = time + ' • Predicted: ' + (isFinite(pred) ? pred : 0);

      if (extra && extra > 0) {
        var extraEl = document.createElement('div');
        extraEl.className = 'mt-1 text-xs font-bold text-blue-700 dark:text-blue-400';
        extraEl.textContent = 'Suggested: +' + extra + ' units';
        left.appendChild(title);
        left.appendChild(meta);
        left.appendChild(extraEl);
      } else {
        left.appendChild(title);
        left.appendChild(meta);
      }

      var badge = document.createElement('span');
      badge.className = 'shrink-0 text-[10px] font-bold px-2 py-1 rounded-md text-white uppercase tracking-wide ' + badgeClass;
      badge.textContent = sev;

      top.appendChild(left);
      top.appendChild(badge);
      row.appendChild(top);

      if (drivers.length) {
        var drv = document.createElement('div');
        drv.className = 'mt-2 text-[11px] text-slate-500 dark:text-slate-400';
        drv.textContent = 'Drivers: ' + drivers.join(' • ');
        row.appendChild(drv);
      }

      if (routePlan.length) {
        var rpWrap = document.createElement('div');
        rpWrap.className = 'mt-2 text-[11px] text-slate-600 dark:text-slate-300';
        var title2 = document.createElement('div');
        title2.className = 'font-bold text-slate-700 dark:text-slate-200';
        title2.textContent = 'Suggested by route';
        rpWrap.appendChild(title2);
        routePlan.forEach(function (rp) {
          if (!rp) return;
          var line = document.createElement('div');
          var rn = rp.route_name ? String(rp.route_name) : (rp.route_id ? String(rp.route_id) : 'Route');
          var x = Number(rp.suggested_extra_units || 0);
          line.textContent = rn + ': +' + x + ' units';
          rpWrap.appendChild(line);
        });
        row.appendChild(rpWrap);
      }

      container.appendChild(row);
    });
  }

  function renderRouteSupply(data) {
    if (!routeSupplyBody || !routeSupplyTitle || !routeSupplyTotal) return;
    routeSupplyBody.innerHTML = '';
    if (!data || !data.ok) {
      routeSupplyTitle.textContent = 'Unknown';
      routeSupplyBody.innerHTML = '<tr><td colspan="2" class="px-6 py-4 text-sm text-slate-500 italic text-center">Select a terminal to view supply.</td></tr>';
      routeSupplyTotal.textContent = '';
      return;
    }
    routeSupplyTitle.textContent = data.terminal_name ? data.terminal_name : 'Selected Terminal';
    var routes = data.routes || [];
    if (!routes.length) {
      routeSupplyBody.innerHTML = '<tr><td colspan="2" class="px-6 py-4 text-sm text-slate-500 italic text-center">No authorized units found.</td></tr>';
      routeSupplyTotal.textContent = '';
      return;
    }
    routes.slice(0, 6).forEach(function (r) {
      var tr = document.createElement('tr');
      tr.innerHTML =
        '<td class="px-6 py-3 font-medium text-slate-700 dark:text-slate-200 border-b border-slate-50 dark:border-slate-800">' + (r.route_name || r.route_id || '') + '</td>' +
        '<td class="px-6 py-3 text-right font-bold text-slate-800 dark:text-white border-b border-slate-50 dark:border-slate-800">' + (r.units || 0) + '</td>';
      routeSupplyBody.appendChild(tr);
    });
    routeSupplyTotal.textContent = 'Total Authorized: ' + (data.total_units || 0);
  }

  function loadRouteSupply(terminalName) {
    if (currentType !== 'terminal' || !terminalName) {
      renderRouteSupply(null);
      return;
    }
    routeSupplyBody.innerHTML = '<tr><td colspan="2" class="px-6 py-8 text-center"><div class="inline-block animate-spin rounded-full h-5 w-5 border-2 border-indigo-500 border-t-transparent"></div></td></tr>';
    fetch('/tmm/admin/api/analytics/route_supply.php?terminal_name=' + encodeURIComponent(terminalName), { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (data) { renderRouteSupply(data); })
      .catch(function () { renderRouteSupply(null); });
  }

  function loadContextWidgets() {
    fetch('/tmm/admin/api/analytics/events.php?days=7', { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (data) { if (data && data.ok) setEventsUI(data.events || []); })
      .catch(function () {});

    fetch('/tmm/admin/api/analytics/demand_insights.php?area_type=' + encodeURIComponent(currentType) + '&hours=24', { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || !data.ok) return;
        setReadinessUI(data.readiness);
        renderHotspots(aiHotspots, data.hotspots || []);
        renderTextList(aiActions, data.actions || [], 'No actions suggested.');
        if (aiUnderutilized) {
          var low = Array.isArray(data.underutilized) ? data.underutilized.map(function (x) {
            if (!x) return '';
            return (x.area_label ? String(x.area_label) : 'Unknown') + ' • peak ' + Number(x.predicted_peak || 0);
          }).filter(Boolean) : [];
          renderTextList(aiUnderutilized, low, 'No low-demand areas detected.');
        }
        insightsByLabel = {};
        if (Array.isArray(data.hotspots)) {
          data.hotspots.forEach(function (h) {
            if (h && h.area_label) insightsByLabel[h.area_label] = h;
          });
        }
        if (lastSpikes && lastSpikes.length) renderSpikes(lastSpikes);
        var terminalForSupply = null;
        if (data.hotspots && data.hotspots.length && data.hotspots[0].area_label) terminalForSupply = data.hotspots[0].area_label;
        if (!terminalForSupply) terminalForSupply = bestTerminalLabel;
        loadRouteSupply(terminalForSupply);
      })
      .catch(function () {});
  }

  function populateAreas(type) {
    if (!demandRef) return;
    demandRef.innerHTML = '';
    var list = areas[type] || [];
    list.forEach(function (a) {
      var opt = document.createElement('option');
      opt.value = String(a.ref);
      opt.textContent = a.label;
      demandRef.appendChild(opt);
    });
  }

  function initDemandForm() {
    if (!demandAt) return;
    var d = new Date();
    d.setMinutes(0, 0, 0);
    var pad = function (n) { return String(n).padStart(2, '0'); };
    var val = d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':00';
    demandAt.value = val;
  }

  function load() {
    if (accuracyEl) accuracyEl.textContent = '...';
    fetch('/tmm/admin/api/analytics/demand_forecast.php?area_type=' + encodeURIComponent(currentType) + '&hours=24', { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || !data.ok) throw new Error('bad');
        setAccuracyUI(data);
        setWeatherNowUI(data.weather || null);
        setTrafficNowUI(data.traffic || null);
        var best = null;
        if (data.areas && data.areas.length) {
          data.areas.forEach(function (a) {
            if (!a || !a.forecast || !a.forecast.length) return;
            var peak = 0;
            a.forecast.slice(0, 24).forEach(function (p) { if (p.predicted > peak) peak = p.predicted; });
            if (!best || peak > best.peak) best = { area: a, peak: peak };
          });
        }
        if (best && best.area && best.area.forecast) renderBars(best.area.forecast);
        lastSpikes = data.spikes || [];
        renderSpikes(lastSpikes);
        bestTerminalLabel = (best && best.area && best.area.area_label) ? best.area.area_label : ((lastSpikes && lastSpikes.length) ? lastSpikes[0].area_label : '');
        if (data.area_lists) {
          areas = data.area_lists;
          populateAreas(demandType ? demandType.value : 'terminal');
        }
        loadContextWidgets();
        if (window.lucide) window.lucide.createIcons();
      })
      .catch(function () {
        if (accuracyEl) accuracyEl.textContent = '—';
      });
  }

  if (btnT) btnT.addEventListener('click', function () { setActive('terminal'); load(); });
  if (btnR) btnR.addEventListener('click', function () { setActive('route'); load(); });

  if (demandType) {
    demandType.addEventListener('change', function () {
      populateAreas(demandType.value);
      setActive(demandType.value);
      load();
    });
  }

  if (demandForm && demandResult) {
    demandForm.addEventListener('submit', function (e) {
      e.preventDefault();
      demandResult.textContent = 'Saving...';
      demandResult.className = 'text-xs font-bold text-slate-500';
      var fd = new FormData(demandForm);
      fetch('/tmm/admin/api/analytics/demand_observation_upsert.php', {
        method: 'POST',
        headers: { 'Accept': 'application/json' },
        body: fd
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data && data.ok) {
            demandResult.textContent = 'OBSERVATION SAVED!';
            demandResult.className = 'text-xs font-black text-emerald-600';
            load();
            setTimeout(function(){ demandResult.textContent = ''; }, 3000);
          } else {
            demandResult.textContent = (data && data.error) ? data.error : 'FAILED TO SAVE';
            demandResult.className = 'text-xs font-black text-rose-600';
          }
        })
        .catch(function () {
          demandResult.textContent = 'NETWORK ERROR';
          demandResult.className = 'text-xs font-black text-rose-600';
        });
    });
  }

  if (demandType && (demandType.value === 'terminal' || demandType.value === 'route')) {
    setActive(demandType.value);
  } else {
    setActive('terminal');
  }
  initDemandForm();
  load();
});
</script>
