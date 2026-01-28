<?php
// Force no cache
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

require_once __DIR__ . '/../includes/db.php';
$db = db();

$terminalsCount = 0;
$parkingAreasCount = 0;
$ticketsToday = 0;
$parkingPaymentsToday = 0;

$r1 = $db->query("SELECT COUNT(*) AS c FROM terminals");
if ($r1 && ($row = $r1->fetch_assoc()))
  $terminalsCount = (int) ($row['c'] ?? 0);
$r2 = $db->query("SELECT COUNT(*) AS c FROM parking_areas");
if ($r2 && ($row = $r2->fetch_assoc()))
  $parkingAreasCount = (int) ($row['c'] ?? 0);
$r3 = $db->query("SELECT COUNT(*) AS c FROM tickets WHERE DATE(date_issued)=CURDATE()");
if ($r3 && ($row = $r3->fetch_assoc()))
  $ticketsToday = (int) ($row['c'] ?? 0);
$r4 = $db->query("SELECT SUM(amount) AS s FROM parking_transactions WHERE DATE(created_at)=CURDATE()");
if ($r4 && ($row = $r4->fetch_assoc()))
  $parkingPaymentsToday = (float) ($row['s'] ?? 0);

// KPI Data for System Overview
$totalVehicles = $db->query("SELECT COUNT(*) AS c FROM vehicles")->fetch_assoc()['c'] ?? 0;
$totalOperators = $db->query("SELECT COUNT(*) AS c FROM operators")->fetch_assoc()['c'] ?? 0;
$activeFranchises = $db->query("SELECT COUNT(*) AS c FROM franchise_applications WHERE status='Endorsed'")->fetch_assoc()['c'] ?? 0;
$pendingFranchises = $db->query("SELECT COUNT(*) AS c FROM franchise_applications WHERE status='Pending'")->fetch_assoc()['c'] ?? 0;
$totalViolations = $db->query("SELECT COUNT(*) AS c FROM tickets")->fetch_assoc()['c'] ?? 0;
$unpaidFines = $db->query("SELECT COUNT(*) AS c FROM tickets WHERE status IN ('Pending','Validated','Escalated')")->fetch_assoc()['c'] ?? 0;
$totalRevenue = $db->query("SELECT SUM(amount) AS s FROM parking_transactions")->fetch_assoc()['s'] ?? 0;

$revenueLast7DaysRow = $db->query("SELECT SUM(amount) AS s FROM parking_transactions WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetch_assoc();
$revenueLast7Days = $revenueLast7DaysRow['s'] ?? 0;
$revenue7DayAvg = $revenueLast7Days ? ($revenueLast7Days / 7) : 0;
$revenueTodayVsAvg = $revenue7DayAvg ? (($parkingPaymentsToday - $revenue7DayAvg) / $revenue7DayAvg) * 100 : 0;

$unpaidRate = $totalViolations ? ($unpaidFines / $totalViolations * 100) : 0;
$vehiclesPerTerminal = $terminalsCount ? ($totalVehicles / $terminalsCount) : 0;

function tmm_table_exists(mysqli $db, string $name): bool {
  $nameEsc = $db->real_escape_string($name);
  $res = $db->query("SHOW TABLES LIKE '$nameEsc'");
  return (bool)($res && $res->num_rows > 0);
}

$terminalOccupancyPct = null;
$slotTotals = ['total' => 0, 'occupied' => 0];
if (tmm_table_exists($db, 'parking_slots')) {
  $r = $db->query("SELECT COUNT(*) AS total, SUM(CASE WHEN status='Occupied' THEN 1 ELSE 0 END) AS occupied FROM parking_slots");
  if ($r && ($row = $r->fetch_assoc())) {
    $slotTotals['total'] = (int)($row['total'] ?? 0);
    $slotTotals['occupied'] = (int)($row['occupied'] ?? 0);
    $terminalOccupancyPct = $slotTotals['total'] > 0 ? ($slotTotals['occupied'] / $slotTotals['total'] * 100.0) : null;
  }
}

$violations7d = (int)($db->query("SELECT COUNT(*) AS c FROM tickets WHERE date_issued >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_assoc()['c'] ?? 0);
$violations7dAvg = $violations7d / 7.0;
$hotspots = [];
if ($db->query("SHOW COLUMNS FROM tickets LIKE 'location'") && ($db->query("SHOW COLUMNS FROM tickets LIKE 'location'")->num_rows ?? 0) > 0) {
  $resH = $db->query("SELECT location, COUNT(*) AS c FROM tickets WHERE date_issued >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND location IS NOT NULL AND location<>'' GROUP BY location ORDER BY c DESC LIMIT 3");
  if ($resH) while ($r = $resH->fetch_assoc()) $hotspots[] = ['location' => (string)$r['location'], 'count' => (int)$r['c']];
}
?>

<!-- DASHBOARD v3.0 - RESTRUCTURED -->
<div class="mx-auto max-w-[1920px] px-4 sm:px-6 lg:px-8 py-6 font-sans text-slate-900 dark:text-slate-100">

  
  <!-- Header Section -->
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between mb-8 pb-6 border-b-2 border-gradient-to-r from-blue-200 via-green-200 to-blue-200 dark:from-blue-900/30 dark:via-green-900/30 dark:to-blue-900/30 animate-slide-up">
    <div>
      <h1 class="text-4xl font-black tracking-tight" style="background: linear-gradient(135deg, #4a90e2, #66bb6a); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
        Transport & Mobility Intelligence
      </h1>
      <p class="text-sm text-slate-600 dark:text-slate-400 mt-2 font-medium">Operational system + analytics-ready platform (trend-based forecasting)</p>
    </div>
    <div class="flex gap-2 p-1.5 rounded-xl border-2 shadow-sm" style="background: linear-gradient(135deg, rgba(91, 163, 245, 0.1), rgba(102, 187, 106, 0.1)); border-color: rgba(91, 163, 245, 0.3);">
      <button id="btnAreaTerminal"
        class="px-5 py-2.5 rounded-lg font-bold text-sm transition-all duration-200 transform hover:scale-105" style="background: linear-gradient(135deg, #5ba3f5, #4a90e2); color: white; box-shadow: 0 4px 12px rgba(74, 144, 226, 0.3);">Terminals</button>
      <button id="btnAreaParking"
        class="px-5 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 text-sm font-semibold transition-all duration-200 hover:bg-white/50 dark:hover:bg-slate-700/50">Routes</button>
    </div>
  </div>

  <!-- System Status Section -->
  <div class="mb-10">
    <div class="flex items-center gap-3 mb-6">
      <div class="p-2 rounded-lg bg-blue-600 shadow-lg shadow-blue-500/20">
        <i data-lucide="activity" class="w-5 h-5 text-white"></i>
      </div>
      <div>
        <h2 class="text-xl font-bold text-slate-900 dark:text-white">System Status</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400">Real-time operational metrics and performance indicators</p>
      </div>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 animate-fade-in">
      <!-- Revenue -->
      <div class="group bg-white dark:bg-slate-800 rounded-2xl p-6 border border-slate-200 dark:border-slate-700 shadow-sm hover:shadow-xl transition-all duration-300 hover:-translate-y-1 relative overflow-hidden">
        <div class="absolute top-0 right-0 p-3 opacity-10 group-hover:opacity-20 transition-opacity">
          <i data-lucide="wallet" class="w-24 h-24 text-emerald-500 transform translate-x-4 -translate-y-4"></i>
        </div>
        <div class="flex items-center justify-between mb-4 relative z-10">
          <div class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400">
            <i data-lucide="wallet" class="w-6 h-6"></i>
          </div>
          <span class="flex items-center text-xs font-bold text-emerald-700 dark:text-emerald-400 bg-emerald-100 dark:bg-emerald-900/30 px-2.5 py-1 rounded-full">
            <i data-lucide="trending-up" class="w-3 h-3 mr-1"></i> +8.4%
          </span>
        </div>
        <h3 class="text-slate-500 dark:text-slate-400 text-sm font-semibold uppercase tracking-wider relative z-10">Total Revenue</h3>
        <div class="text-3xl font-black text-slate-900 dark:text-white mt-2 relative z-10">
          ₱<?php echo number_format($totalRevenue, 2); ?>
        </div>
      </div>

      <!-- Active Units -->
      <div class="group bg-white dark:bg-slate-800 rounded-2xl p-6 border border-slate-200 dark:border-slate-700 shadow-sm hover:shadow-xl transition-all duration-300 hover:-translate-y-1 relative overflow-hidden">
        <div class="absolute top-0 right-0 p-3 opacity-10 group-hover:opacity-20 transition-opacity">
          <i data-lucide="bus" class="w-24 h-24 text-blue-500 transform translate-x-4 -translate-y-4"></i>
        </div>
        <div class="flex items-center justify-between mb-4 relative z-10">
          <div class="p-3 rounded-xl bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400">
            <i data-lucide="bus" class="w-6 h-6"></i>
          </div>
          <span class="flex items-center text-xs font-bold text-blue-700 dark:text-blue-400 bg-blue-100 dark:bg-blue-900/30 px-2.5 py-1 rounded-full">
            <i data-lucide="trending-up" class="w-3 h-3 mr-1"></i> +2.1%
          </span>
        </div>
        <h3 class="text-slate-500 dark:text-slate-400 text-sm font-semibold uppercase tracking-wider relative z-10">Active Units</h3>
        <div class="text-3xl font-black text-slate-900 dark:text-white mt-2 relative z-10">
          <?php echo number_format($totalVehicles); ?>
        </div>
      </div>

      <!-- Total Violations -->
      <div class="group bg-white dark:bg-slate-800 rounded-2xl p-6 border border-slate-200 dark:border-slate-700 shadow-sm hover:shadow-xl transition-all duration-300 hover:-translate-y-1 relative overflow-hidden">
        <div class="absolute top-0 right-0 p-3 opacity-10 group-hover:opacity-20 transition-opacity">
          <i data-lucide="alert-circle" class="w-24 h-24 text-rose-500 transform translate-x-4 -translate-y-4"></i>
        </div>
        <div class="flex items-center justify-between mb-4 relative z-10">
          <div class="p-3 rounded-xl bg-rose-50 dark:bg-rose-900/20 text-rose-600 dark:text-rose-400">
            <i data-lucide="alert-circle" class="w-6 h-6"></i>
          </div>
          <span class="flex items-center text-xs font-bold text-rose-700 dark:text-rose-400 bg-rose-100 dark:bg-rose-900/30 px-2.5 py-1 rounded-full">
            <i data-lucide="arrow-up" class="w-3 h-3 mr-1"></i> +5.2%
          </span>
        </div>
        <h3 class="text-slate-500 dark:text-slate-400 text-sm font-semibold uppercase tracking-wider relative z-10">Violations</h3>
        <div class="text-3xl font-black text-slate-900 dark:text-white mt-2 relative z-10">
          <?php echo number_format($totalViolations); ?>
        </div>
      </div>

      <!-- System Health (Operators) -->
      <div class="group bg-white dark:bg-slate-800 rounded-2xl p-6 border border-slate-200 dark:border-slate-700 shadow-sm hover:shadow-xl transition-all duration-300 hover:-translate-y-1 relative overflow-hidden">
        <div class="absolute top-0 right-0 p-3 opacity-10 group-hover:opacity-20 transition-opacity">
          <i data-lucide="users" class="w-24 h-24 text-violet-500 transform translate-x-4 -translate-y-4"></i>
        </div>
        <div class="flex items-center justify-between mb-4 relative z-10">
          <div class="p-3 rounded-xl bg-violet-50 dark:bg-violet-900/20 text-violet-600 dark:text-violet-400">
            <i data-lucide="users" class="w-6 h-6"></i>
          </div>
          <span class="flex items-center text-xs font-bold text-emerald-700 dark:text-emerald-400 bg-emerald-100 dark:bg-emerald-900/30 px-2.5 py-1 rounded-full">
            <i data-lucide="activity" class="w-3 h-3 mr-1"></i> 98.5%
          </span>
        </div>
        <h3 class="text-slate-500 dark:text-slate-400 text-sm font-semibold uppercase tracking-wider relative z-10">Active Operators</h3>
        <div class="text-3xl font-black text-slate-900 dark:text-white mt-2 relative z-10">
          <?php echo number_format($totalOperators); ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Live Monitoring Section -->
  <div class="mb-10">
    <div class="flex items-center gap-3 mb-6">
      <div class="p-2 rounded-lg bg-orange-500 shadow-lg shadow-orange-500/20">
        <i data-lucide="eye" class="w-5 h-5 text-white"></i>
      </div>
      <div>
        <h2 class="text-xl font-bold text-slate-900 dark:text-white">Live Monitoring</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400">Current alerts, occupancy, and hotspot tracking</p>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 animate-slide-up" style="animation-delay: 0.1s;">
      <!-- Violation Frequency -->
      <div class="bg-white dark:bg-slate-800 rounded-2xl p-6 border border-slate-200 dark:border-slate-700 shadow-sm hover:shadow-xl transition-all duration-300 hover:-translate-y-1 relative overflow-hidden group">
        <div class="absolute right-0 top-0 h-full w-1 bg-amber-500/50"></div>
        <div class="flex items-center gap-3 mb-4">
          <div class="p-2 rounded-lg bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400">
            <i data-lucide="alert-octagon" class="w-5 h-5"></i>
          </div>
          <div class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest">Violation Frequency</div>
        </div>
        <div class="text-3xl font-black text-slate-900 dark:text-white"><?php echo number_format($violations7dAvg, 1); ?><span class="text-lg text-slate-400 font-medium ml-1">/day</span></div>
        <div class="mt-2 text-xs text-slate-500 dark:text-slate-400 font-medium">7-day average • Based on recorded tickets</div>
      </div>

      <!-- Terminal Occupancy -->
      <div class="bg-white dark:bg-slate-800 rounded-2xl p-6 border border-slate-200 dark:border-slate-700 shadow-sm hover:shadow-xl transition-all duration-300 hover:-translate-y-1 relative overflow-hidden group">
        <div class="absolute right-0 top-0 h-full w-1 bg-cyan-500/50"></div>
        <div class="flex items-center gap-3 mb-4">
          <div class="p-2 rounded-lg bg-cyan-100 dark:bg-cyan-900/30 text-cyan-600 dark:text-cyan-400">
            <i data-lucide="gauge" class="w-5 h-5"></i>
          </div>
          <div class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest">Terminal Occupancy</div>
        </div>
        <div class="text-3xl font-black text-slate-900 dark:text-white">
          <?php echo $terminalOccupancyPct === null ? '—' : number_format($terminalOccupancyPct, 1) . '%'; ?>
        </div>
        <div class="mt-2 text-xs text-slate-500 dark:text-slate-400 font-medium"><?php echo $slotTotals['total'] > 0 ? (number_format($slotTotals['occupied']) . ' / ' . number_format($slotTotals['total']) . ' slots occupied') : 'No slot data available'; ?></div>
      </div>

      <!-- Enforcement Hotspots -->
      <div class="bg-white dark:bg-slate-800 rounded-2xl p-6 border border-slate-200 dark:border-slate-700 shadow-sm hover:shadow-xl transition-all duration-300 hover:-translate-y-1 relative overflow-hidden group">
        <div class="absolute right-0 top-0 h-full w-1 bg-rose-500/50"></div>
        <div class="flex items-center gap-3 mb-4">
          <div class="p-2 rounded-lg bg-rose-100 dark:bg-rose-900/30 text-rose-600 dark:text-rose-400">
            <i data-lucide="map-pin" class="w-5 h-5"></i>
          </div>
          <div class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest">Enforcement Hotspots</div>
        </div>
        <div class="mt-2 space-y-3">
          <?php if (!$hotspots) { ?>
            <div class="text-xs text-slate-500 italic">No hotspot data yet</div>
          <?php } else { foreach ($hotspots as $h) { ?>
            <div class="flex items-center justify-between gap-3">
              <div class="flex items-center gap-2 min-w-0">
                <div class="w-1.5 h-1.5 rounded-full bg-rose-500"></div>
                <div class="truncate text-sm font-semibold text-slate-700 dark:text-slate-300"><?php echo htmlspecialchars($h['location']); ?></div>
              </div>
              <div class="shrink-0 text-xs font-bold text-white bg-rose-500 px-2 py-0.5 rounded-full shadow-sm"><?php echo (int)$h['count']; ?></div>
            </div>
          <?php } } ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Analytics Context Section -->
  <div class="mb-10">
    <div class="flex items-center gap-3 mb-6">
      <div class="p-2 rounded-lg bg-indigo-600 shadow-lg shadow-indigo-500/20">
        <i data-lucide="bar-chart-2" class="w-5 h-5 text-white"></i>
      </div>
      <div>
        <h2 class="text-xl font-bold text-slate-900 dark:text-white">Analytics & Context</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400">Environmental factors and model readiness</p>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
    <!-- Forecast Readiness -->
    <div class="p-5 rounded-xl bg-gradient-to-br from-white to-indigo-50/30 dark:from-slate-800 dark:to-indigo-900/10 border-2 border-indigo-100 dark:border-indigo-900/30 shadow-md hover:shadow-lg transition-all duration-300">
      <div class="flex items-center gap-3 mb-3">
        <div class="p-2 rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 shadow-md">
          <i data-lucide="line-chart" class="w-4 h-4 text-white"></i>
        </div>
        <div class="text-xs font-black text-indigo-700 dark:text-indigo-400 uppercase tracking-wide">Analytics</div>
      </div>
      <div id="readinessValue" class="text-2xl font-black text-slate-900 dark:text-white">—</div>
      <div id="readinessHint" class="text-xs text-slate-600 dark:text-slate-400 mt-1 font-semibold"></div>
    </div>

    <!-- Weather Now -->
    <div class="p-5 rounded-xl bg-gradient-to-br from-white to-sky-50/30 dark:from-slate-800 dark:to-sky-900/10 border-2 border-sky-100 dark:border-sky-900/30 shadow-md hover:shadow-lg transition-all duration-300">
      <div class="flex items-center gap-3 mb-3">
        <div class="p-2 rounded-lg bg-gradient-to-br from-sky-500 to-blue-600 shadow-md">
          <i data-lucide="cloud-rain" class="w-4 h-4 text-white"></i>
        </div>
        <div class="text-xs font-black text-sky-700 dark:text-sky-400 uppercase tracking-wide">Weather</div>
      </div>
      <div id="weatherNowValue" class="text-2xl font-black text-slate-900 dark:text-white">—</div>
      <div id="weatherNowHint" class="text-xs text-slate-600 dark:text-slate-400 mt-1 font-semibold"></div>
    </div>

    <!-- Upcoming Event -->
    <div class="p-5 rounded-xl bg-gradient-to-br from-white to-violet-50/30 dark:from-slate-800 dark:to-violet-900/10 border-2 border-violet-100 dark:border-violet-900/30 shadow-md hover:shadow-lg transition-all duration-300">
      <div class="flex items-center gap-3 mb-3">
        <div class="p-2 rounded-lg bg-gradient-to-br from-violet-500 to-purple-600 shadow-md">
          <i data-lucide="calendar-days" class="w-4 h-4 text-white"></i>
        </div>
        <div class="text-xs font-black text-violet-700 dark:text-violet-400 uppercase tracking-wide">Events</div>
      </div>
      <div id="eventsValue" class="text-2xl font-black text-slate-900 dark:text-white">—</div>
      <div id="eventsHint" class="text-xs text-slate-600 dark:text-slate-400 mt-1 font-semibold"></div>
    </div>

    <!-- Traffic Now -->
    <div class="p-5 rounded-xl bg-gradient-to-br from-white to-orange-50/30 dark:from-slate-800 dark:to-orange-900/10 border-2 border-orange-100 dark:border-orange-900/30 shadow-md hover:shadow-lg transition-all duration-300">
      <div class="flex items-center gap-3 mb-3">
        <div class="p-2 rounded-lg bg-gradient-to-br from-orange-500 to-red-600 shadow-md">
          <i data-lucide="traffic-cone" class="w-4 h-4 text-white"></i>
        </div>
        <div class="text-xs font-black text-orange-700 dark:text-orange-400 uppercase tracking-wide">Traffic</div>
      </div>
      <div id="trafficNowValue" class="text-2xl font-black text-slate-900 dark:text-white">—</div>
      <div id="trafficNowHint" class="text-xs text-slate-600 dark:text-slate-400 mt-1 font-semibold"></div>
    </div>
  </div>

  <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
    <div class="xl:col-span-2 space-y-12">
      
      <div class="space-y-6">
        <div class="flex items-center gap-3">
          <div class="p-2 rounded-lg bg-blue-600 shadow-lg shadow-blue-500/20">
            <i data-lucide="trending-up" class="w-5 h-5 text-white"></i>
          </div>
          <div>
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">Demand Forecasting</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">AI-driven demand prediction and analysis</p>
          </div>
        </div>
        <!-- Main Forecast Chart -->
      <div class="p-6 rounded-xl bg-gradient-to-br from-white to-slate-50/50 dark:from-slate-800 dark:to-slate-900/50 border-2 border-slate-200 dark:border-slate-700 shadow-lg">
        <div class="flex items-start justify-between gap-4 mb-2">
          <div>
            <h2 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
              <i data-lucide="trending-up" class="w-5 h-5 text-blue-600 dark:text-blue-400"></i>
              Demand Forecast
            </h2>
            <div class="text-sm text-slate-500 mt-1">Trend-based projection for the next 24 hours</div>
          </div>
          <div
            class="text-right px-4 py-2 rounded-md bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600">
            <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Model Accuracy</div>
            <div id="forecastAccuracy" class="text-xl font-bold text-emerald-600">—</div>
            <div id="forecastAccuracyHint" class="text-[10px] text-slate-400"></div>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="p-5 rounded-xl bg-gradient-to-br from-blue-50 to-white dark:from-slate-800 dark:to-slate-800 border-2 border-blue-100 dark:border-blue-900/30 shadow-md">
            <div class="text-xs font-black text-blue-600 dark:text-blue-400 uppercase tracking-wider">Peak Operating Hour</div>
            <div id="peakHourValue" class="mt-2 text-2xl font-black text-slate-900 dark:text-white">—</div>
            <div id="peakHourHint" class="mt-1 text-xs text-slate-600 dark:text-slate-400 font-semibold"></div>
          </div>
          <div class="p-5 rounded-xl bg-gradient-to-br from-emerald-50 to-white dark:from-slate-800 dark:to-slate-800 border-2 border-emerald-100 dark:border-emerald-900/30 shadow-md">
            <div class="text-xs font-black text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">Peak Demand (24h)</div>
            <div id="peakDemandValue" class="mt-2 text-2xl font-black text-slate-900 dark:text-white">—</div>
            <div id="peakDemandHint" class="mt-1 text-xs text-slate-600 dark:text-slate-400 font-semibold"></div>
          </div>
          <div class="p-5 rounded-xl bg-gradient-to-br from-violet-50 to-white dark:from-slate-800 dark:to-slate-800 border-2 border-violet-100 dark:border-violet-900/30 shadow-md">
            <div class="text-xs font-black text-violet-600 dark:text-violet-400 uppercase tracking-wider">Suggested Units</div>
            <div id="requiredUnitsValue" class="mt-2 text-2xl font-black text-slate-900 dark:text-white">—</div>
            <div id="requiredUnitsHint" class="mt-1 text-xs text-slate-600 dark:text-slate-400 font-semibold"></div>
          </div>
        </div>

        <div class="relative w-full h-[350px] bg-transparent">
          <div id="forecastChart" class="w-full h-full"></div>
        </div>
        <div id="forecastChartLegend"
          class="mt-4 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 border-t border-slate-100 dark:border-slate-700 pt-4">
        </div>
        <div id="chartAnalysis"
          class="mt-4 p-4 bg-slate-50 dark:bg-slate-700/50 rounded-lg text-sm text-slate-600 dark:text-slate-300 border border-slate-100 dark:border-slate-700 leading-relaxed">
          Select data to view analysis.
        </div>
      </div>

      </div>

      <div class="space-y-6">
        <div class="flex items-center gap-3">
          <div class="p-2 rounded-lg bg-violet-600 shadow-lg shadow-violet-500/20">
            <i data-lucide="zap" class="w-5 h-5 text-white"></i>
          </div>
          <div>
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">Operational Intelligence</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">Actionable insights and supply optimization</p>
          </div>
        </div>
        <!-- Analytics Insights Section -->
      <div class="p-6 rounded-xl bg-gradient-to-br from-white to-blue-50/30 dark:from-slate-800 dark:to-slate-900/50 border-2 border-blue-100 dark:border-blue-900/30 shadow-lg">
        <div class="flex items-center justify-between mb-6">
          <div class="flex items-center gap-4">
            <div class="p-3 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 text-white shadow-lg shadow-blue-500/20">
              <i data-lucide="sparkles" class="w-6 h-6"></i>
            </div>
            <div>
              <h2 class="text-xl font-bold text-slate-900 dark:text-white tracking-tight">Predictive Analytics Insights</h2>
              <div class="flex items-center gap-2 mt-1">
                <span class="flex w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="text-sm text-slate-500 font-medium">Live Operational Signals</span>
                <span id="insightsModePill" class="ml-2 text-[11px] font-bold px-2.5 py-1 rounded-full bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600">TERMINALS</span>
              </div>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Peak Demand Insight -->
          <div class="p-5 rounded-2xl border border-blue-100 dark:border-blue-900/30 bg-gradient-to-br from-blue-50 to-white dark:from-slate-800 dark:to-slate-800/50 shadow-sm hover:shadow-md transition-shadow">
            <div class="flex items-start justify-between mb-4">
              <div class="flex items-center gap-2">
                <i data-lucide="trending-up" class="w-5 h-5 text-blue-600 dark:text-blue-400"></i>
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-100"><span id="insightsOverScope">Terminal</span> High-demand Signals</h3>
              </div>
              <span class="text-[11px] font-bold px-2.5 py-1 rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300 border border-blue-200 dark:border-blue-800">UPCOMING</span>
            </div>
            <ul id="insightsOver" class="text-sm font-medium text-slate-600 dark:text-slate-300 space-y-3 leading-relaxed"></ul>
          </div>

          <!-- Optimization Insight -->
          <div class="p-5 rounded-2xl border border-emerald-100 dark:border-emerald-900/30 bg-gradient-to-br from-emerald-50 to-white dark:from-slate-800 dark:to-slate-800/50 shadow-sm hover:shadow-md transition-shadow">
            <div class="flex items-start justify-between mb-4">
              <div class="flex items-center gap-2">
                <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600 dark:text-emerald-400"></i>
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-100"><span id="insightsUnderScope">Terminal</span> Operational Recommendations</h3>
              </div>
              <span class="text-[11px] font-bold px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">ACTIONABLE</span>
            </div>
            <ul id="insightsUnder" class="text-sm font-medium text-slate-600 dark:text-slate-300 space-y-3 leading-relaxed"></ul>
          </div>
        </div>

        <div class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div class="p-5 rounded-2xl border border-rose-100 dark:border-rose-900/30 bg-white/70 dark:bg-slate-800/40 shadow-sm">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <i data-lucide="arrow-up-right" class="w-5 h-5 text-rose-600 dark:text-rose-400"></i>
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">Top Shortage</h3>
              </div>
              <span class="text-[11px] font-bold px-2.5 py-1 rounded-full bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300 border border-rose-200 dark:border-rose-800">ADD UNITS</span>
            </div>
            <div class="mt-4 overflow-x-auto">
              <table class="min-w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-700 border-b border-slate-200 dark:border-slate-700">
                  <tr class="text-left text-slate-500 dark:text-slate-400">
                    <th class="py-3 px-3 font-black uppercase tracking-widest text-xs">Area</th>
                    <th class="py-3 px-3 font-black uppercase tracking-widest text-xs text-right">Peak</th>
                    <th class="py-3 px-3 font-black uppercase tracking-widest text-xs text-right">Supply</th>
                    <th class="py-3 px-3 font-black uppercase tracking-widest text-xs text-right">Suggested</th>
                  </tr>
                </thead>
                <tbody id="miniShortageBody" class="divide-y divide-slate-200 dark:divide-slate-700">
                  <tr><td colspan="4" class="py-8 text-center text-slate-500 font-medium italic">Loading...</td></tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="p-5 rounded-2xl border border-emerald-100 dark:border-emerald-900/30 bg-white/70 dark:bg-slate-800/40 shadow-sm">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <i data-lucide="arrow-down-right" class="w-5 h-5 text-emerald-600 dark:text-emerald-400"></i>
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">Top Oversupply</h3>
              </div>
              <span class="text-[11px] font-bold px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">REDUCE</span>
            </div>
            <div class="mt-4 overflow-x-auto">
              <table class="min-w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-700 border-b border-slate-200 dark:border-slate-700">
                  <tr class="text-left text-slate-500 dark:text-slate-400">
                    <th class="py-3 px-3 font-black uppercase tracking-widest text-xs">Area</th>
                    <th class="py-3 px-3 font-black uppercase tracking-widest text-xs text-right">Peak</th>
                    <th class="py-3 px-3 font-black uppercase tracking-widest text-xs text-right">Supply</th>
                    <th class="py-3 px-3 font-black uppercase tracking-widest text-xs text-right">Suggested</th>
                  </tr>
                </thead>
                <tbody id="miniOversupplyBody" class="divide-y divide-slate-200 dark:divide-slate-700">
                  <tr><td colspan="4" class="py-8 text-center text-slate-500 font-medium italic">Loading...</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- Alerts Section -->
      <div class="p-6 rounded-xl bg-gradient-to-br from-white to-rose-50/30 dark:from-slate-800 dark:to-rose-900/10 border-2 border-rose-100 dark:border-rose-900/30 shadow-lg">
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center gap-3">
            <div class="p-1.5 rounded bg-rose-50 dark:bg-rose-900/20 text-rose-600 dark:text-rose-400">
              <i data-lucide="alert-triangle" class="w-5 h-5"></i>
            </div>
            <div>
              <h3 class="text-base font-bold text-slate-900 dark:text-white">High-demand Alerts</h3>
            </div>
          </div>
          <span
            class="text-xs px-2 py-1 rounded-md bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 font-semibold border border-slate-200 dark:border-slate-600">Next
            6 Hours</span>
        </div>
        <div id="forecastSpikes" class="space-y-3"></div>
      </div>

      <!-- Route Supply -->
      <div class="p-6 rounded-xl bg-gradient-to-br from-white to-indigo-50/30 dark:from-slate-800 dark:to-indigo-900/10 border-2 border-indigo-100 dark:border-indigo-900/30 shadow-lg">
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
          <div class="flex items-center gap-2">
            <div id="routeSupplyTitle"
              class="text-sm font-semibold text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-700 px-3 py-1.5 rounded border border-slate-200 dark:border-slate-600">
            </div>
            <?php if (has_permission('reports.export')): ?>
              <a id="routeSupplyExportCsv" href="#"
                class="px-3 py-2 rounded-md bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition-colors text-xs font-bold flex items-center gap-2">
                <i data-lucide="download" class="w-4 h-4"></i> CSV
              </a>
              <a id="routeSupplyExportExcel" href="#"
                class="px-3 py-2 rounded-md bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition-colors text-xs font-bold flex items-center gap-2">
                <i data-lucide="file-spreadsheet" class="w-4 h-4"></i> Excel
              </a>
            <?php endif; ?>
          </div>
        </div>

        <div class="overflow-hidden rounded-md border border-slate-200 dark:border-slate-700">
          <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-700">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Route</th>
                <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Authorized
                  Units</th>
              </tr>
            </thead>
            <tbody id="routeSupplyBody"
              class="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-800"></tbody>
          </table>
        </div>
        <div id="routeSupplyTotal" class="mt-3 text-right text-xs font-bold text-slate-500 uppercase"></div>
      </div>
    </div>
    </div>

      <!-- Right Column: Data Inputs -->
    <div class="space-y-6">
      
      <div class="flex items-center gap-3 mb-2">
        <div class="p-2 rounded-lg bg-emerald-600 shadow-lg shadow-emerald-500/20">
          <i data-lucide="database" class="w-5 h-5 text-white"></i>
        </div>
        <div>
          <h2 class="text-xl font-bold text-slate-900 dark:text-white">Data Management</h2>
          <p class="text-sm text-slate-500 dark:text-slate-400">Input demand data and adjust weights</p>
        </div>
      </div>

      <div class="p-6 rounded-xl bg-gradient-to-br from-white to-emerald-50/30 dark:from-slate-800 dark:to-emerald-900/10 border-2 border-emerald-100 dark:border-emerald-900/30 shadow-lg">
        <div class="flex items-center justify-between mb-5">
          <div>
            <h2 class="text-base font-bold text-slate-900 dark:text-white">Data Inputs</h2>
            <div class="text-xs text-slate-500">Record demand observations (trend-based forecasting)</div>
          </div>
          <a href="?page=module5/submodule3"
            class="p-2 rounded-md bg-slate-50 dark:bg-slate-700 text-slate-400 hover:text-blue-600 hover:bg-blue-50 transition-all"
            title="Go to Parking Data">
            <i data-lucide="database" class="w-4 h-4"></i>
          </a>
        </div>

        <form id="demand-log-form" class="space-y-4">
          <div class="space-y-1">
            <label class="text-xs font-semibold text-slate-500 uppercase">Area Type</label>
            <div class="relative">
              <select id="demand-area-type" name="area_type"
                class="w-full pl-3 pr-8 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-all outline-none">
                <option value="terminal">Terminal</option>
                <option value="route">Route</option>
              </select>
            </div>
          </div>

          <div class="space-y-1">
            <label class="text-xs font-semibold text-slate-500 uppercase">Location</label>
            <div class="relative">
              <select id="demand-area-ref" name="area_ref"
                class="w-full pl-3 pr-8 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-all outline-none"></select>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1">
              <label class="text-xs font-semibold text-slate-500 uppercase">Hour</label>
              <input id="demand-observed-at" name="observed_at" type="datetime-local"
                class="w-full px-2 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-md text-xs focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none">
            </div>
            <div class="space-y-1">
              <label class="text-xs font-semibold text-slate-500 uppercase">Count</label>
              <input id="demand-count" name="demand_count" type="number" min="0"
                class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none"
                placeholder="0">
            </div>
          </div>

          <button type="submit"
            class="w-full py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold shadow-sm transition-all flex items-center justify-center gap-2 text-sm">
            <i data-lucide="save" class="w-4 h-4"></i>
            Save Observation
          </button>
          <div id="demand-log-result" class="text-center text-xs font-bold min-h-[1.5em]"></div>
        </form>

        <div class="mt-6 pt-6 border-t border-slate-200 dark:border-slate-700">
          <div class="flex items-center gap-2 mb-4">
            <i data-lucide="sliders" class="w-4 h-4 text-violet-500"></i>
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">Adjustment Factors</h3>
          </div>
          <form id="forecast-weights-form" class="space-y-3">
            <div class="grid grid-cols-3 gap-2 items-center">
              <label class="text-[11px] font-bold text-slate-500 uppercase">Weather</label>
              <input id="wWeather" name="ai_weather_weight" type="number" step="0.01" min="-0.50" max="0.50"
                class="col-span-2 w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-md text-sm font-semibold focus:ring-1 focus:ring-violet-500 focus:border-violet-500 outline-none">
            </div>
            <div class="grid grid-cols-3 gap-2 items-center">
              <label class="text-[11px] font-bold text-slate-500 uppercase">Events</label>
              <input id="wEvent" name="ai_event_weight" type="number" step="0.01" min="-0.50" max="0.50"
                class="col-span-2 w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-md text-sm font-semibold focus:ring-1 focus:ring-violet-500 focus:border-violet-500 outline-none">
            </div>
            <div class="grid grid-cols-3 gap-2 items-center">
              <label class="text-[11px] font-bold text-slate-500 uppercase">Traffic</label>
              <input id="wTraffic" name="ai_traffic_weight" type="number" step="0.01" min="0.00" max="2.00"
                class="col-span-2 w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-md text-sm font-semibold focus:ring-1 focus:ring-violet-500 focus:border-violet-500 outline-none">
            </div>
            <button type="submit"
              class="w-full py-2.5 rounded-md bg-violet-700 hover:bg-violet-800 text-white font-semibold shadow-sm transition-all flex items-center justify-center gap-2 text-sm">
              <i data-lucide="save" class="w-4 h-4"></i>
              Save Weights
            </button>
            <div id="forecast-weights-result" class="text-center text-xs font-bold min-h-[1.5em]"></div>
          </form>
        </div>
      </div>


    </div>

    <!-- End main content wrapper -->
  </div>

  <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

  <script>
  document.addEventListener('DOMContentLoaded', function () {
      var btnT = document.getElementById('btnAreaTerminal');
      var btnP = document.getElementById('btnAreaParking');
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
      var eventsValue = document.getElementById('eventsValue');
      var eventsHint = document.getElementById('eventsHint');
      var trafficNowValue = document.getElementById('trafficNowValue');
      var trafficNowHint = document.getElementById('trafficNowHint');
      var insightsOver = document.getElementById('insightsOver');
      var insightsUnder = document.getElementById('insightsUnder');
      var miniShortageBody = document.getElementById('miniShortageBody');
      var miniOversupplyBody = document.getElementById('miniOversupplyBody');
      var peakHourValue = document.getElementById('peakHourValue');
      var peakHourHint = document.getElementById('peakHourHint');
      var peakDemandValue = document.getElementById('peakDemandValue');
      var peakDemandHint = document.getElementById('peakDemandHint');
      var requiredUnitsValue = document.getElementById('requiredUnitsValue');
      var requiredUnitsHint = document.getElementById('requiredUnitsHint');
      var weightsForm = document.getElementById('forecast-weights-form');
      var wWeather = document.getElementById('wWeather');
      var wEvent = document.getElementById('wEvent');
      var wTraffic = document.getElementById('wTraffic');
      var weightsResult = document.getElementById('forecast-weights-result');
      var currentType = 'terminal';
      var areas = { terminal: [], route: [] };
      var lastSpikes = [];
      var insightsByLabel = {};
      var bestTerminalLabel = '';
      var bestAreaRef = '';
      var lastModel = null;
      var forecastChartInstance = null;

      function setActive(type) {
        currentType = type;
        var isTerminal = type === 'terminal';
        var scopeLabel = isTerminal ? 'Terminal' : 'Route';
        var scopePill = isTerminal ? 'TERMINALS' : 'ROUTES';
        var pill = document.getElementById('insightsModePill');
        var overScope = document.getElementById('insightsOverScope');
        var underScope = document.getElementById('insightsUnderScope');
        if (pill) pill.textContent = scopePill;
        if (overScope) overScope.textContent = scopeLabel;
        if (underScope) underScope.textContent = scopeLabel;
        if (btnT && btnP) {
          if (type === 'terminal') {
            btnT.className = 'px-4 py-2 rounded-md bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm border border-slate-200 dark:border-slate-600 text-sm font-semibold transition-all';
            btnP.className = 'px-4 py-2 rounded-md text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 text-sm font-medium transition-all';
          } else {
            btnP.className = 'px-4 py-2 rounded-md bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm border border-slate-200 dark:border-slate-600 text-sm font-semibold transition-all';
            btnT.className = 'px-4 py-2 rounded-md text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 text-sm font-medium transition-all';
          }
        }
      }

      function renderBars(points) {
        if (!chartEl) return;

        if (!points || !points.length) {
          if (forecastChartInstance) forecastChartInstance.updateSeries([]);
          return;
        }

        var maxV = 1;
        var nowMs = Date.now();
        var currentHourLabel = '';

        var chartData = points.slice(0, 24).map(function (p) {
          var predicted = (p && p.predicted_adjusted != null) ? p.predicted_adjusted : (p ? p.predicted : 0);
          var baseline = (p && p.baseline != null) ? p.baseline : predicted;

          if (predicted > maxV) maxV = predicted;
          if (baseline > maxV) maxV = baseline;

          var rain = (p && p.weather && p.weather.precip_mm) ? Number(p.weather.precip_mm) : 0;
          var prob = (p && p.weather && p.weather.precip_prob !== null && p.weather.precip_prob !== undefined) ? Number(p.weather.precip_prob) : 0;
          var evt = (p && p.event && p.event.title) ? p.event.title : '';
          var tf = (p && p.traffic_factor != null) ? Number(p.traffic_factor) : 1.0;
          var wf = (p && p.weather_factor != null) ? Number(p.weather_factor) : 1.0;
          var ef = (p && p.event_factor != null) ? Number(p.event_factor) : 1.0;

          var isTraffic = Math.abs(tf - 1.0) > 0.02;
          var isWeather = Math.abs(wf - 1.0) > 0.02 || prob >= 60;
          var isEvent = Math.abs(ef - 1.0) > 0.02;

          var activeImpacts = 0;
          if (isTraffic) activeImpacts++;
          if (isWeather) activeImpacts++;
          if (isEvent) activeImpacts++;

          var color = '#3b82f6'; // blue-500
          if (activeImpacts >= 2) color = '#f43f5e'; // rose-500
          else if (isEvent) color = '#8b5cf6'; // violet-500
          else if (isTraffic) color = '#f59e0b'; // amber-500
          else if (isWeather) color = '#06b6d4'; // cyan-500

          // Format time label
          var label = '';
          if (p && p.hour_label) {
            label = p.hour_label.split(' ')[1] || p.hour_label;
          }

          // Identify current hour
          if (p && p.ts) {
            var tsMs = Number(p.ts) * 1000;
            if (!isNaN(tsMs) && Math.abs(tsMs - nowMs) <= 30 * 60 * 1000) {
              currentHourLabel = label;
            }
          }

          return {
            x: label,
            y: Math.max(0, predicted),
            baseline: Math.max(0, baseline),
            fillColor: color,
            details: {
              traffic: tf,
              weather: wf,
              event: ef,
              rain: rain,
              prob: prob,
              evt: evt,
              combined: p.combined_factor
            }
          };
        });

        var isDark = document.documentElement.classList.contains('dark');
        var textColor = isDark ? '#94a3b8' : '#64748b';
        var gridColor = isDark ? '#334155' : '#e2e8f0';

        var annotations = {};
        if (currentHourLabel) {
          annotations = {
            xaxis: [{
              x: currentHourLabel,
              borderColor: '#22c55e',
              label: {
                borderColor: '#22c55e',
                style: {
                  color: '#fff',
                  background: '#22c55e',
                  fontSize: '10px',
                  fontWeight: 'bold',
                },
                text: 'NOW',
                orientation: 'horizontal',
                offsetY: -20
              }
            }]
          };
        }

        var options = {
          series: [
            {
              name: 'Predicted',
              type: 'bar',
              data: chartData
            },
            {
              name: 'Baseline',
              type: 'area',
              data: chartData.map(d => ({ x: d.x, y: d.baseline }))
            }
          ],
          chart: {
            type: 'line',
            height: 350,
            toolbar: { show: false },
            fontFamily: 'inherit',
            animations: {
              enabled: true,
              easing: 'easeinout',
              speed: 800
            },
            background: 'transparent'
          },
          plotOptions: {
            bar: {
              columnWidth: '50%',
              borderRadius: 6,
              borderRadiusApplication: 'end',
              distributed: true,
              dataLabels: { position: 'top' }
            }
          },
          colors: chartData.map(d => d.fillColor),
          fill: {
            type: ['gradient', 'gradient'],
            gradient: {
              shade: 'light',
              type: "vertical",
              shadeIntensity: 0.25,
              opacityFrom: [1, 0.4], // 1 for bar, 0.4 for area
              opacityTo: [0.8, 0.1], // 0.8 for bar, 0.1 for area
              stops: [0, 100]
            }
          },
          stroke: {
            width: [0, 3],
            curve: 'smooth',
            colors: ['transparent', '#94a3b8']
          },
          xaxis: {
            categories: chartData.map(d => d.x),
            labels: { style: { colors: textColor, fontSize: '11px', fontWeight: 500 } },
            axisBorder: { show: false },
            axisTicks: { show: false },
            tooltip: { enabled: false }
          },
          yaxis: {
            labels: {
              style: { colors: textColor, fontSize: '11px' },
              formatter: function (val) { return Math.round(val) }
            },
            forceNiceScale: true
          },
          grid: {
            borderColor: gridColor,
            strokeDashArray: 4,
            xaxis: { lines: { show: false } },
            yaxis: { lines: { show: true } },
            padding: { top: 0, right: 0, bottom: 0, left: 10 }
          },
          annotations: annotations,
          legend: {
            show: false // Custom legend is better
          },
          dataLabels: {
            enabled: false
          },
          tooltip: {
            theme: isDark ? 'dark' : 'light',
            shared: true,
            intersect: false,
            y: {
              formatter: function (y) {
                if (typeof y !== "undefined") return y.toFixed(0);
                return y;
              }
            },
            custom: function ({ series, seriesIndex, dataPointIndex, w }) {
              var data = w.globals.initialSeries[0].data[dataPointIndex];
              if (!data) return '';

              var details = data.details;
              var predicted = series[0][dataPointIndex];
              var baseline = series[1][dataPointIndex];
              var diff = predicted - baseline;
              var diffSign = diff >= 0 ? '+' : '';
              var diffClass = diff >= 0 ? 'text-emerald-600' : 'text-rose-600';

              var factors = [];
              if (Math.abs(details.traffic - 1.0) > 0.02) factors.push(`<div class="flex items-center justify-between text-[10px] gap-2"><span class="font-medium text-amber-600">Traffic</span><span class="font-bold text-slate-700 dark:text-slate-300">x${details.traffic.toFixed(2)}</span></div>`);
              if (Math.abs(details.weather - 1.0) > 0.02 || details.prob >= 60) factors.push(`<div class="flex items-center justify-between text-[10px] gap-2"><span class="font-medium text-cyan-600">Weather</span><span class="font-bold text-slate-700 dark:text-slate-300">x${details.weather.toFixed(2)}</span></div>`);
              if (Math.abs(details.event - 1.0) > 0.02) factors.push(`<div class="flex items-center justify-between text-[10px] gap-2"><span class="font-medium text-violet-600">Event</span><span class="font-bold text-slate-700 dark:text-slate-300">x${details.event.toFixed(2)}</span></div>`);
              if (details.evt) factors.push(`<div class="mt-1 pt-1 border-t border-slate-100 dark:border-slate-700 text-[10px] text-slate-500 italic truncate max-w-[150px]">${details.evt}</div>`);

              return `
            <div class="p-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-xl min-w-[160px]">
              <div class="flex items-center justify-between mb-2 pb-2 border-b border-slate-100 dark:border-slate-700">
                <span class="text-xs font-bold text-slate-500 uppercase">${data.x}</span>
                <span class="text-xs font-bold ${diffClass}">${diffSign}${Math.round(diff)}</span>
              </div>
              <div class="space-y-1">
                <div class="flex justify-between items-center">
                    <span class="text-xs text-slate-500">Predicted</span>
                    <span class="text-sm font-bold text-blue-600">${Math.round(predicted)}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-xs text-slate-500">Baseline</span>
                    <span class="text-xs font-semibold text-slate-400">${Math.round(baseline)}</span>
                </div>
              </div>
              ${factors.length ? `<div class="mt-2 pt-2 border-t border-slate-100 dark:border-slate-700 space-y-1 bg-slate-50 dark:bg-slate-900/50 -mx-2.5 -mb-2.5 p-2.5 rounded-b-lg">${factors.join('')}</div>` : ''}
            </div>
          `;
            }
          }
        };

        // Populate Custom Legend
        if (chartLegend) {
          chartLegend.innerHTML = `
          <div class="flex items-center gap-2">
            <span class="w-3 h-3 rounded-sm bg-blue-500 shadow-sm"></span>
            <span class="text-xs font-medium text-slate-600 dark:text-slate-400">Normal</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="w-3 h-3 rounded-sm bg-amber-500 shadow-sm"></span>
            <span class="text-xs font-medium text-slate-600 dark:text-slate-400">Traffic</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="w-3 h-3 rounded-sm bg-cyan-500 shadow-sm"></span>
            <span class="text-xs font-medium text-slate-600 dark:text-slate-400">Weather</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="w-3 h-3 rounded-sm bg-violet-500 shadow-sm"></span>
            <span class="text-xs font-medium text-slate-600 dark:text-slate-400">Event</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="w-3 h-3 rounded-sm bg-rose-500 shadow-sm"></span>
            <span class="text-xs font-medium text-slate-600 dark:text-slate-400">High Impact</span>
          </div>
          <div class="flex items-center gap-2">
            <div class="w-3 h-3 rounded-sm bg-slate-300 dark:bg-slate-600 opacity-50 relative overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-t from-slate-400/20 to-transparent"></div>
            </div>
            <span class="text-xs font-medium text-slate-600 dark:text-slate-400">Baseline</span>
          </div>
        `;
        }

        if (forecastChartInstance) {
          forecastChartInstance.destroy();
        }

        forecastChartInstance = new ApexCharts(document.querySelector("#forecastChart"), options);
      forecastChartInstance.render();

      // Update Chart Summary
      var analysisEl = document.getElementById('chartAnalysis');
      if (analysisEl && points && points.length > 0) {
        var peakVal = 0;
        var peakTime = '';
        var totalPred = 0;
        var totalBase = 0;
        
        points.slice(0, 24).forEach(function(p) {
            var val = (p.predicted_adjusted != null) ? p.predicted_adjusted : p.predicted;
            if (val > peakVal) {
                peakVal = val;
                peakTime = p.hour_label;
            }
            totalPred += val;
            totalBase += (p.baseline != null) ? p.baseline : val;
        });

        var diffPercent = totalBase > 0 ? ((totalPred - totalBase) / totalBase) * 100 : 0;
        var trendStr = diffPercent > 2 ? 'higher than average' : (diffPercent < -2 ? 'lower than average' : 'consistent with baseline');
        var trendColor = diffPercent > 2 ? 'text-rose-600' : (diffPercent < -2 ? 'text-emerald-600' : 'text-blue-600');

        analysisEl.innerHTML = `
            Forecast indicates a peak demand of <span class="font-bold text-slate-900 dark:text-white">${Math.round(peakVal)} passengers</span> 
            at <span class="font-bold text-slate-900 dark:text-white">${peakTime}</span>. 
            Overall demand is trending <span class="font-bold ${trendColor}">${trendStr}</span> (${diffPercent > 0 ? '+' : ''}${diffPercent.toFixed(1)}%).
        `;
      } else if (analysisEl) {
        analysisEl.innerHTML = 'Insufficient data for analysis.';
      }

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
          if (accuracyHint) accuracyHint.textContent = 'CALIBRATING...';
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
        readinessValue.textContent = ok ? 'Ready' : 'Collecting';
        readinessValue.className = ok ? 'text-2xl font-bold text-emerald-600' : 'text-2xl font-bold text-amber-600 animate-pulse';
        readinessHint.textContent = ok ? 'Analytics layer active' : 'Awaiting more logs...';
      }

      function updateForecastHighlights(area, points) {
        if (!peakHourValue || !peakDemandValue || !requiredUnitsValue) return;
        if (!points || !points.length) {
          peakHourValue.textContent = '—';
          peakDemandValue.textContent = '—';
          requiredUnitsValue.textContent = '—';
          if (peakHourHint) peakHourHint.textContent = '';
          if (peakDemandHint) peakDemandHint.textContent = '';
          if (requiredUnitsHint) requiredUnitsHint.textContent = '';
          return;
        }
        var best = null;
        points.slice(0, 24).forEach(function (p) {
          var v = (p && p.predicted_adjusted != null) ? Number(p.predicted_adjusted) : Number((p && p.predicted) ? p.predicted : 0);
          if (!best || v > best.v) best = { p: p, v: v };
        });
        if (!best) return;
        var when = best.p && best.p.hour_label ? String(best.p.hour_label) : '';
        var label = when || '—';
        peakHourValue.textContent = label;
        if (peakHourHint) peakHourHint.textContent = area && area.area_label ? String(area.area_label) : '';
        peakDemandValue.textContent = String(Math.round(best.v));
        if (peakDemandHint) peakDemandHint.textContent = currentType === 'route' ? 'High-demand route window' : 'High-demand terminal window';
        var suggested = Math.max(1, Math.ceil(best.v));
        requiredUnitsValue.textContent = String(suggested);
        if (requiredUnitsHint) requiredUnitsHint.textContent = 'Rule-based: ceil(peak demand)';
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

      function setTrafficNowUI(resp) {
        if (!trafficNowValue || !trafficNowHint) return;
        if (!resp || !resp.ok || !resp.data) {
          trafficNowValue.textContent = '—';
          trafficNowHint.textContent = '';
          return;
        }
        var d = resp.data;
        if (!d.configured) {
          trafficNowValue.textContent = '—';
          trafficNowHint.textContent = 'Not configured';
          return;
        }
        var cong = d.congestion ? String(d.congestion) : 'unknown';
        var pct = (d.congestion_pct !== null && d.congestion_pct !== undefined) ? Number(d.congestion_pct) : null;
        var inc = (d.incidents_count !== null && d.incidents_count !== undefined) ? Number(d.incidents_count) : 0;
        var factor = (d.traffic_factor !== null && d.traffic_factor !== undefined) ? Number(d.traffic_factor) : 1;
        var label = cong.toUpperCase();
        if (pct !== null && !Number.isNaN(pct)) label += ' ' + pct.toFixed(1) + '%';
        trafficNowValue.textContent = label;
        var hintParts = [];
        hintParts.push('Incidents: ' + (inc || 0));
        if (factor && factor !== 1 && factor !== 1.0) hintParts.push('Factor: x' + factor.toFixed(2));
        if (d.traffic_source === 'city_fallback') hintParts.push('City fallback');
        if (d.traffic_status && d.traffic_status !== 'ok') {
          if (d.traffic_status === 'missing_location') hintParts.push('Missing location data');
          else if (d.traffic_status === 'geocode_failed') hintParts.push('Geocode failed');
        }
        trafficNowHint.textContent = hintParts.join(' • ');
      }

      function loadTrafficNow(areaType, areaRef) {
        if (!trafficNowValue || !trafficNowHint) return;
        trafficNowValue.textContent = '...';
        trafficNowHint.textContent = '';
        var url = (window.TMM_ROOT_URL || '') + '/admin/api/analytics/traffic.php';
        if (areaType && areaType !== 'city') {
          url += '?area_type=' + encodeURIComponent(areaType) + '&area_ref=' + encodeURIComponent(areaRef || '');
        } else {
          url += '?area_type=city';
        }
        fetch(url, { headers: { 'Accept': 'application/json' } })
          .then(function (r) { return r.json(); })
          .then(function (data) { setTrafficNowUI(data); })
          .catch(function () {
            trafficNowValue.textContent = '—';
            trafficNowHint.textContent = '';
          });
      }

      var tmmMoreModal = null;
      var tmmMoreModalTitle = null;
      var tmmMoreModalList = null;

      function ensureMoreModal() {
        if (tmmMoreModal) return;
        tmmMoreModal = document.createElement('div');
        tmmMoreModal.className = 'fixed inset-0 z-[80] hidden items-center justify-center bg-slate-900/60 p-4';
        tmmMoreModal.innerHTML = ''
          + '<div class="w-full max-w-2xl rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-xl overflow-hidden">'
          +   '<div class="flex items-center justify-between px-5 py-4 border-b border-slate-200 dark:border-slate-700">'
          +     '<div class="text-sm font-black text-slate-900 dark:text-white" data-tmm-more-title></div>'
          +     '<button type="button" class="text-xs font-black text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white px-2 py-1 rounded-lg" data-tmm-more-close>Close</button>'
          +   '</div>'
          +   '<div class="p-5">'
          +     '<ul class="max-h-[60vh] overflow-auto space-y-2 text-sm text-slate-700 dark:text-slate-200" data-tmm-more-list></ul>'
          +   '</div>'
          + '</div>';
        document.body.appendChild(tmmMoreModal);
        tmmMoreModalTitle = tmmMoreModal.querySelector('[data-tmm-more-title]');
        tmmMoreModalList = tmmMoreModal.querySelector('[data-tmm-more-list]');
        var closeBtn = tmmMoreModal.querySelector('[data-tmm-more-close]');
        if (closeBtn) closeBtn.addEventListener('click', function () { closeMoreModal(); });
        tmmMoreModal.addEventListener('click', function (e) {
          if (e && e.target === tmmMoreModal) closeMoreModal();
        });
        document.addEventListener('keydown', function (e) {
          if (e && e.key === 'Escape') closeMoreModal();
        });
      }

      function openMoreModal(title, items) {
        ensureMoreModal();
        if (tmmMoreModalTitle) tmmMoreModalTitle.textContent = String(title || 'More');
        if (tmmMoreModalList) {
          tmmMoreModalList.innerHTML = '';
          (items || []).forEach(function (it) {
            var li = document.createElement('li');
            li.className = 'flex gap-2 items-start';
            var dot = document.createElement('span');
            dot.className = 'mt-1.5 h-1.5 w-1.5 rounded-full bg-slate-400 shrink-0';
            var txt = document.createElement('span');
            txt.className = 'leading-snug';
            txt.textContent = String(it || '');
            li.appendChild(dot);
            li.appendChild(txt);
            tmmMoreModalList.appendChild(li);
          });
        }
        tmmMoreModal.classList.remove('hidden');
        tmmMoreModal.classList.add('flex');
      }

      function closeMoreModal() {
        if (!tmmMoreModal) return;
        tmmMoreModal.classList.add('hidden');
        tmmMoreModal.classList.remove('flex');
      }

      function renderPlaybook(listEl, items, type) {
        if (!listEl) return;
        listEl.innerHTML = '';
        if (!items || !items.length) {
          listEl.innerHTML = '<li class="text-xs text-slate-400 italic pl-1">No specific actions needed.</li>';
          return;
        }

        var escapeHtml = function (s) {
          if (s === null || s === undefined) return '';
          return String(s).replace(/[&<>"']/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]; });
        };
        
        var formatInsight = function (t) {
          var s = escapeHtml(t || '');
          // Highlight key terms
          s = s.replace(/^(Maintenance Opportunity|Low Activity|Optimization|High Demand):/i, '<span class="font-bold text-slate-800 dark:text-white">$1:</span>');
          s = s.replace(/\*\*(.+?)\*\*/g, '<strong class="font-bold text-slate-900 dark:text-white bg-slate-100 dark:bg-slate-800 px-1 py-0.5 rounded mx-0.5">$1</strong>');
          s = s.replace(/\n/g, '<br>');
          return s;
        };

        var extractLongAreaList = function (raw) {
          var text = String(raw || '');
          var m = text.match(/\*\*([^*]+)\*\*/);
          if (!m) return null;
          var inner = String(m[1] || '');
          if (inner.indexOf(',') === -1) return null;
          var parts = inner.split(',').map(function (x) { return String(x || '').trim(); }).filter(function (x) { return x !== ''; });
          
          // Truncate if > 6 items OR (> 2 items and total length > 100 chars for long route names)
          var shouldTruncate = parts.length > 6 || (parts.length > 2 && inner.length > 100);
          
          if (!shouldTruncate) return null;
          
          var prefix = '';
          var idx = text.indexOf(':');
          if (idx > 0) prefix = text.slice(0, idx).trim();
          return { bold: m[0], items: parts, prefix: prefix };
        };

        var iconColor = 'text-slate-400';
        var iconName = 'circle-dot';
        var containerClass = 'border-slate-100 dark:border-slate-700 bg-white/70 dark:bg-slate-900/30';
        
        if (type === 'over') {
          iconColor = 'text-rose-500';
          iconName = 'alert-circle';
          containerClass = 'border-rose-100 dark:border-rose-900/30 bg-rose-50/50 dark:bg-rose-900/10';
        } else if (type === 'under') {
          iconColor = 'text-emerald-500';
          iconName = 'check-circle-2';
          containerClass = 'border-emerald-100 dark:border-emerald-900/30 bg-emerald-50/50 dark:bg-emerald-900/10';
        }

        items.forEach(function (t, i) {
          var li = document.createElement('li');
          li.className = 'flex gap-3 items-start text-sm text-slate-600 dark:text-slate-300 p-4 rounded-xl border transition-all hover:shadow-sm ' + containerClass;
          
          if (i >= 7) {
            li.style.display = 'none';
            li.dataset.hidden = 'true';
          }
          
          // Custom icon based on content
          var currentIconName = iconName;
          var currentIconColor = iconColor;
          
          if (t.includes('Maintenance Opportunity')) {
            currentIconName = 'wrench';
            currentIconColor = 'text-amber-500';
            li.className = li.className.replace(containerClass, 'border-amber-100 dark:border-amber-900/30 bg-amber-50/50 dark:bg-amber-900/10');
          } else if (t.includes('Optimization')) {
             currentIconName = 'zap';
             currentIconColor = 'text-violet-500';
             li.className = li.className.replace(containerClass, 'border-violet-100 dark:border-violet-900/30 bg-violet-50/50 dark:bg-violet-900/10');
          }

          var iconHtml = '<div class="mt-0.5 shrink-0"><i data-lucide="' + currentIconName + '" class="w-5 h-5 ' + currentIconColor + '"></i></div>';
          
          var info = extractLongAreaList(t);
          var displayText = t;
          var moreBtn = '';
          
          if (info) {
            // Show fewer items for long lists to keep UI clean
            var showCount = 3;
            if (info.items.length <= 4) showCount = info.items.length; // Show all if small enough
            
            var shown = info.items.slice(0, showCount).join(', ');
            var remaining = info.items.length - showCount;
            
            if (remaining > 0) {
                 displayText = String(t || '').replace(info.bold, '**' + shown + '**');
                 moreBtn = '<button type="button" class="ml-2 inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 shadow-sm text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 hover:bg-slate-50 transition-colors whitespace-nowrap" data-more-action>' + 
                           '<span>+' + remaining + ' more</span>' +
                           '</button>';
            }
          }
          
          li.innerHTML = iconHtml;
          
          var contentDiv = document.createElement('div');
          contentDiv.className = 'flex-1 leading-relaxed';
          contentDiv.innerHTML = formatInsight(displayText);
          
          if (moreBtn) {
             // Append button to the bolded part or at the end? 
             // The formatInsight wraps bold parts. We can append the button after the text.
             var wrapper = document.createElement('div');
             wrapper.className = 'inline';
             wrapper.innerHTML = moreBtn;
             contentDiv.appendChild(wrapper);
             
             // Add event listener to the button we just added
             setTimeout(function() {
                 var btn = li.querySelector('[data-more-action]');
                 if (btn) {
                     btn.addEventListener('click', function (e) {
                         e.stopPropagation();
                         var kind = currentType === 'terminal' ? 'Terminals' : 'Routes';
                         var title = (info.prefix ? (info.prefix) : 'Locations') + ' <span class="text-slate-400 font-normal">(' + info.items.length + ')</span>';
                         openMoreModal(title, info.items);
                     });
                 }
             }, 0);
          }
          
          li.appendChild(contentDiv);
          listEl.appendChild(li);
        });

        if (items.length > 7) {
          var btnContainer = document.createElement('li');
          btnContainer.className = 'list-none pt-2';
          var btn = document.createElement('button');
          btn.className = 'w-full text-center py-2.5 text-xs text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 font-medium hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition-colors cursor-pointer border border-transparent hover:border-slate-200 dark:hover:border-slate-700 dashed';
          btn.textContent = 'Show ' + (items.length - 7) + ' more insights';
          btn.onclick = function() {
            btnContainer.remove();
            Array.from(listEl.children).forEach(function(child) {
              if (child.dataset.hidden === 'true') {
                child.style.display = 'flex';
              }
            });
          };
          btnContainer.appendChild(btn);
          listEl.appendChild(btnContainer);
        }
        if (window.lucide) window.lucide.createIcons();
      }

      function renderMiniTable(bodyEl, rows, mode) {
        if (!bodyEl) return;
        bodyEl.innerHTML = '';
        if (!rows || !rows.length) {
          bodyEl.innerHTML = '<tr><td colspan="4" class="py-8 text-center text-slate-500 font-medium italic">No items.</td></tr>';
          return;
        }
        var esc = function (s) {
          if (s === null || s === undefined) return '';
          return String(s).replace(/[&<>"']/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]; });
        };
        rows.slice(0, 5).forEach(function (r) {
          var tr = document.createElement('tr');
          var area = esc((r && r.area_label) ? r.area_label : '-');
          var peak = (r && r.peak_hour) ? String(r.peak_hour) : '';
          var peakVal = (r && r.peak_predicted !== undefined) ? String(r.peak_predicted) : '0';
          var supply = (r && r.supply_units !== null && r.supply_units !== undefined) ? String(r.supply_units) : '—';
          var delta = (r && r.suggested_delta !== undefined && r.suggested_delta !== null) ? Number(r.suggested_delta) : 0;
          var sign = delta > 0 ? '+' : (delta < 0 ? '−' : '');
          var abs = Math.abs(delta);
          var deltaTxt = (sign ? (sign + abs) : '—');
          var deltaCls = delta > 0 ? 'text-rose-700 dark:text-rose-400' : (delta < 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-slate-500');
          var peakTxt = peak ? (peak + ' • ' + peakVal) : peakVal;
          tr.innerHTML =
            '<td class="py-3 px-3 font-black text-slate-900 dark:text-white">' + area + '</td>' +
            '<td class="py-3 px-3 text-right font-semibold text-slate-600 dark:text-slate-300">' + esc(peakTxt) + '</td>' +
            '<td class="py-3 px-3 text-right font-black text-slate-900 dark:text-white">' + esc(supply) + '</td>' +
            '<td class="py-3 px-3 text-right font-black ' + deltaCls + '">' + esc(deltaTxt) + '</td>';
          bodyEl.appendChild(tr);
        });
      }

      function renderRouteSupply(data) {
        if (!routeSupplyBody || !routeSupplyTitle || !routeSupplyTotal) return;
        var exportCsv = document.getElementById('routeSupplyExportCsv');
        var exportExcel = document.getElementById('routeSupplyExportExcel');
        var setExportLinks = function (terminalName) {
          if (!exportCsv && !exportExcel) return;
          var base = (window.TMM_ROOT_URL || '') + '/admin/api/analytics/export_route_supply.php?terminal_name=' + encodeURIComponent(terminalName || '');
          if (exportCsv) exportCsv.href = base + '&format=csv';
          if (exportExcel) exportExcel.href = base + '&format=excel';
        };
        routeSupplyBody.innerHTML = '';
        if (!data || !data.ok) {
          routeSupplyTitle.textContent = 'Unknown';
          routeSupplyBody.innerHTML = '<tr><td colspan="2" class="px-6 py-4 text-sm text-slate-500 italic text-center">Select a terminal to view supply.</td></tr>';
          routeSupplyTotal.textContent = '';
          if (exportCsv) exportCsv.href = '#';
          if (exportExcel) exportExcel.href = '#';
          return;
        }
        routeSupplyTitle.textContent = data.terminal_name ? data.terminal_name : 'Selected Terminal';
        setExportLinks(data.terminal_name || '');
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
        fetch((window.TMM_ROOT_URL || '') + '/admin/api/analytics/route_supply.php?terminal_name=' + encodeURIComponent(terminalName), { headers: { 'Accept': 'application/json' } })
          .then(function (r) { return r.json(); })
          .then(function (data) { renderRouteSupply(data); })
          .catch(function () { renderRouteSupply(null); });
      }

      function loadContextWidgets() {
        fetch((window.TMM_ROOT_URL || '') + '/admin/api/analytics/events.php?days=7', { headers: { 'Accept': 'application/json' } })
          .then(function (r) { return r.json(); })
          .then(function (data) { if (data && data.ok) setEventsUI(data.events || []); })
          .catch(function () { });

        fetch((window.TMM_ROOT_URL || '') + '/admin/api/analytics/demand_insights.php?area_type=' + encodeURIComponent(currentType) + '&hours=24', { headers: { 'Accept': 'application/json' } })
          .then(function (r) { return r.json(); })
          .then(function (data) {
            if (!data || !data.ok) return;
            setReadinessUI(data.readiness);
            renderPlaybook(insightsOver, (data.playbook && data.playbook.over_demand) ? data.playbook.over_demand : [], 'over');
            renderPlaybook(insightsUnder, (data.playbook && data.playbook.under_demand) ? data.playbook.under_demand : [], 'under');
            if (data.mini_tables) {
              renderMiniTable(miniShortageBody, data.mini_tables.shortage || [], 'shortage');
              renderMiniTable(miniOversupplyBody, data.mini_tables.oversupply || [], 'oversupply');
            } else {
              renderMiniTable(miniShortageBody, [], 'shortage');
              renderMiniTable(miniOversupplyBody, [], 'oversupply');
            }
            insightsByLabel = {};
            if (Array.isArray(data.alerts)) {
              data.alerts.forEach(function (a) {
                if (a && a.area_label) insightsByLabel[a.area_label] = a;
              });
            }
            if (lastSpikes && lastSpikes.length) renderSpikes(lastSpikes);
            var terminalForSupply = null;
            if (data.alerts && data.alerts.length && data.alerts[0].area_label) terminalForSupply = data.alerts[0].area_label;
            if (!terminalForSupply) terminalForSupply = bestTerminalLabel;
            loadRouteSupply(terminalForSupply);
          })
          .catch(function () { });
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
        fetch((window.TMM_ROOT_URL || '') + '/admin/api/analytics/demand_forecast.php?area_type=' + encodeURIComponent(currentType) + '&hours=24', { headers: { 'Accept': 'application/json' } })
          .then(function (r) { return r.json(); })
          .then(function (data) {
            if (!data || !data.ok) throw new Error('bad');
            setAccuracyUI(data);
            setWeatherNowUI(data.weather || null);
            lastModel = data.model || null;
            if (wWeather && lastModel && lastModel.ai_weather_weight !== undefined) wWeather.value = lastModel.ai_weather_weight;
            if (wEvent && lastModel && lastModel.ai_event_weight !== undefined) wEvent.value = lastModel.ai_event_weight;
            if (wTraffic && lastModel && lastModel.ai_traffic_weight !== undefined) wTraffic.value = lastModel.ai_traffic_weight;
            var best = null;
            if (data.areas && data.areas.length) {
              data.areas.forEach(function (a) {
                if (!a || !a.forecast || !a.forecast.length) return;
                var peak = 0;
                a.forecast.slice(0, 24).forEach(function (p) {
                  var v = (p && p.predicted_adjusted != null) ? p.predicted_adjusted : (p ? p.predicted : 0);
                  if (v > peak) peak = v;
                });
                if (!best || peak > best.peak) best = { area: a, peak: peak };
              });
            }
            if (best && best.area && best.area.forecast) {
              renderBars(best.area.forecast);
              updateForecastHighlights(best.area, best.area.forecast);
            } else {
              updateForecastHighlights(null, null);
            }
            lastSpikes = data.spikes || [];
            renderSpikes(lastSpikes);
            bestTerminalLabel = (best && best.area && best.area.area_label) ? best.area.area_label : ((lastSpikes && lastSpikes.length) ? lastSpikes[0].area_label : '');
            bestAreaRef = (best && best.area && best.area.area_ref) ? String(best.area.area_ref) : '';
            if (data.area_lists) {
              areas = data.area_lists;
              populateAreas(demandType ? demandType.value : 'terminal');
            }
            if (bestAreaRef) loadTrafficNow(currentType, bestAreaRef);
            else loadTrafficNow('city', '');
            loadContextWidgets();
            if (window.lucide) window.lucide.createIcons();
          })
          .catch(function () {
            if (accuracyEl) accuracyEl.textContent = '—';
          });
      }

      if (weightsForm) {
        weightsForm.addEventListener('submit', function (e) {
          e.preventDefault();
          if (!wWeather || !wEvent || !wTraffic) return;
          if (weightsResult) {
            weightsResult.className = 'text-center text-xs font-bold min-h-[1.5em] text-slate-500';
            weightsResult.textContent = 'Saving...';
          }
          var fd = new FormData();
          fd.append('ai_weather_weight', String(wWeather.value || '0'));
          fd.append('ai_event_weight', String(wEvent.value || '0'));
          fd.append('ai_traffic_weight', String(wTraffic.value || '1'));
          fetch((window.TMM_ROOT_URL || '') + '/admin/api/settings/update.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
              if (data && data.ok) {
                if (weightsResult) {
                  weightsResult.className = 'text-center text-xs font-bold min-h-[1.5em] text-emerald-600';
                  weightsResult.textContent = 'Saved';
                }
                load();
              } else {
                if (weightsResult) {
                  weightsResult.className = 'text-center text-xs font-bold min-h-[1.5em] text-rose-600';
                  weightsResult.textContent = 'Failed to save';
                }
              }
            })
            .catch(function () {
              if (weightsResult) {
                weightsResult.className = 'text-center text-xs font-bold min-h-[1.5em] text-rose-600';
                weightsResult.textContent = 'Failed to save';
              }
            });
        });
      }

      if (btnT) btnT.addEventListener('click', function () { setActive('terminal'); load(); });
      if (btnP) btnP.addEventListener('click', function () { setActive('route'); load(); });

      if (demandType) {
        demandType.addEventListener('change', function () {
          populateAreas(demandType.value);
          if (demandRef && demandRef.value) loadTrafficNow(demandType.value, demandRef.value);
        });
      }
      if (demandRef) {
        demandRef.addEventListener('change', function () {
          if (demandType && demandType.value) loadTrafficNow(demandType.value, demandRef.value);
        });
      }

      if (demandForm && demandResult) {
        demandForm.addEventListener('submit', function (e) {
          e.preventDefault();
          demandResult.textContent = 'Saving...';
          demandResult.className = 'text-xs font-bold text-slate-500';
          var fd = new FormData(demandForm);
          fetch((window.TMM_ROOT_URL || '') + '/admin/api/analytics/demand_observation_upsert.php', {
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
                setTimeout(function () { demandResult.textContent = ''; }, 3000);
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

      setActive('terminal');
      initDemandForm();
      load();
    });
  </script>
