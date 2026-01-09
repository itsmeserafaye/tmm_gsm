<?php
require_once __DIR__ . '/../includes/db.php';
$db = db();
// Filters
$period = strtolower(trim($_GET['period'] ?? '30d'));
$status_f = trim($_GET['status'] ?? '');
$officer_id = isset($_GET['officer_id']) ? (int)($_GET['officer_id'] ?? 0) : 0;
$officers = [];
$ores = $db->query("SELECT officer_id, name, badge_no FROM officers ORDER BY name");
if ($ores) { while($row = $ores->fetch_assoc()) { $officers[] = $row; } }
// Ticket filtered counts
$conds = [];
if ($status_f !== '' && in_array($status_f, ['Pending','Validated','Settled','Escalated'])) { $conds[] = "status='".$db->real_escape_string($status_f)."'"; }
if ($officer_id > 0) { $conds[] = "officer_id=".$officer_id; }
if ($period === '7d') { $conds[] = "date_issued >= DATE_SUB(NOW(), INTERVAL 7 DAY)"; }
if ($period === '30d') { $conds[] = "date_issued >= DATE_SUB(NOW(), INTERVAL 30 DAY)"; }
if ($period === '90d') { $conds[] = "date_issued >= DATE_SUB(NOW(), INTERVAL 90 DAY)"; }
if ($period === 'year') { $conds[] = "YEAR(date_issued) = YEAR(NOW())"; }
$tickets_filtered = (int)($db->query("SELECT COUNT(*) AS c FROM tickets" . ($conds ? " WHERE " . implode(" AND ", $conds) : ""))->fetch_assoc()['c'] ?? 0);
// Activity (last 14 days)
$ticketsDaily = []; $logsDaily = [];
for ($i=13; $i>=0; $i--) { $d = date('Y-m-d', strtotime("-$i days")); $ticketsDaily[$d] = 0; $logsDaily[$d] = 0; }
$tdsql = "SELECT DATE(date_issued) AS d, COUNT(*) AS c FROM tickets WHERE date_issued >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)";
if ($status_f !== '' && in_array($status_f, ['Pending','Validated','Settled','Escalated'])) { $tdsql .= " AND status='".$db->real_escape_string($status_f)."'"; }
if ($officer_id > 0) { $tdsql .= " AND officer_id=".$officer_id; }
$tdsql .= " GROUP BY DATE(date_issued) ORDER BY d";
$tdres = $db->query($tdsql);
if ($tdres) { while($r = $tdres->fetch_assoc()) { $ticketsDaily[$r['d']] = (int)$r['c']; } }
$ldsql = "SELECT DATE(time_in) AS d, COUNT(*) AS c FROM terminal_logs WHERE time_in >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) GROUP BY DATE(time_in) ORDER BY d";
$ldres = $db->query($ldsql);
if ($ldres) { while($r = $ldres->fetch_assoc()) { $logsDaily[$r['d']] = (int)$r['c']; } }
$ticketsMax = max($ticketsDaily ?: [0]); $logsMax = max($logsDaily ?: [0]);
// Global snapshot
$vehicles_total = (int)($db->query("SELECT COUNT(*) AS c FROM vehicles")->fetch_assoc()['c'] ?? 0);
$vehicles_active = (int)($db->query("SELECT COUNT(*) AS c FROM vehicles WHERE status='Active'")->fetch_assoc()['c'] ?? 0);
$vehicles_suspended = (int)($db->query("SELECT COUNT(*) AS c FROM vehicles WHERE status='Suspended'")->fetch_assoc()['c'] ?? 0);
$compliance_suspended = (int)($db->query("SELECT COUNT(*) AS c FROM compliance_summary WHERE compliance_status='Suspended'")->fetch_assoc()['c'] ?? 0);
$tickets_pending = (int)($db->query("SELECT COUNT(*) AS c FROM tickets WHERE status='Pending'")->fetch_assoc()['c'] ?? 0);
$tickets_validated = (int)($db->query("SELECT COUNT(*) AS c FROM tickets WHERE status='Validated'")->fetch_assoc()['c'] ?? 0);
$tickets_settled = (int)($db->query("SELECT COUNT(*) AS c FROM tickets WHERE status='Settled'")->fetch_assoc()['c'] ?? 0);
$tickets_escalated = (int)($db->query("SELECT COUNT(*) AS c FROM tickets WHERE status='Escalated'")->fetch_assoc()['c'] ?? 0);
$tickets_unresolved = (int)($db->query("SELECT COUNT(*) AS c FROM tickets WHERE status IN ('Pending','Validated','Escalated')")->fetch_assoc()['c'] ?? 0);
$permits_active = (int)($db->query("SELECT COUNT(*) AS c FROM terminal_permits WHERE status='Active'")->fetch_assoc()['c'] ?? 0);
$permits_pending_payment = (int)($db->query("SELECT COUNT(*) AS c FROM terminal_permits WHERE status='Pending Payment'")->fetch_assoc()['c'] ?? 0);
$permits_revoked = (int)($db->query("SELECT COUNT(*) AS c FROM terminal_permits WHERE status='Revoked'")->fetch_assoc()['c'] ?? 0);
$permits_expired = (int)($db->query("SELECT COUNT(*) AS c FROM terminal_permits WHERE status='Expired'")->fetch_assoc()['c'] ?? 0);
$logs_today = (int)($db->query("SELECT COUNT(*) AS c FROM terminal_logs WHERE DATE(time_in)=CURDATE()")->fetch_assoc()['c'] ?? 0);
$fees_total = 0.0;
$rf = $db->query("SELECT SUM(amount) AS s FROM parking_transactions");
if ($rf && $rf->num_rows > 0) { $fees_total = (float)($rf->fetch_assoc()['s'] ?? 0.0); }
$violations_total = (int)($db->query("SELECT COUNT(*) AS c FROM parking_violations")->fetch_assoc()['c'] ?? 0);
$recent_logs = $db->query("SELECT l.vehicle_plate, COALESCE(o.full_name, v.operator_name) AS operator_name, l.time_in, l.time_out, l.activity_type, l.remarks FROM terminal_logs l LEFT JOIN operators o ON o.id=l.operator_id LEFT JOIN vehicles v ON v.plate_number=l.vehicle_plate ORDER BY l.time_in DESC LIMIT 10");
$recent_tickets = $db->query("SELECT ticket_number, vehicle_plate, status, fine_amount, date_issued FROM tickets ORDER BY date_issued DESC LIMIT 10");

// AI Insights — Upcoming Alerts (2h) from demand_forecasts
$alerts = [];
$lastForecastUpdated = null;
$af = $db->query("SELECT df.ts, df.route_id, df.terminal_id, df.forecast_trips, df.lower_ci, df.upper_ci, df.created_at, t.name AS terminal_name FROM demand_forecasts df LEFT JOIN terminals t ON t.id=df.terminal_id WHERE df.ts BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 120 MINUTE) ORDER BY df.forecast_trips DESC LIMIT 6");
if ($af) {
  while ($row = $af->fetch_assoc()) { $alerts[] = $row; }
  $lf = $db->query("SELECT MAX(created_at) AS m FROM demand_forecasts");
  if ($lf && ($lr = $lf->fetch_assoc())) { $lastForecastUpdated = $lr['m'] ?? null; }
}
// AI Status — last forecast job
$ai_job = null;
$aj = $db->query("SELECT id, status, job_type, message, started_at, finished_at FROM demand_forecast_jobs WHERE job_type='forecast' ORDER BY COALESCE(finished_at, started_at) DESC, id DESC LIMIT 1");
if ($aj) { $ai_job = $aj->fetch_assoc(); }
// Dynamic Caps summary — latest cap per route
$caps_summary = [];
$caps_last_ts = null;
$cr = $db->query("SELECT r.route_id, r.cap, r.ts, r.reason, r.confidence FROM route_cap_schedule r JOIN (SELECT route_id, MAX(ts) AS ts FROM route_cap_schedule GROUP BY route_id) m ON m.route_id=r.route_id AND m.ts=r.ts ORDER BY r.route_id");
if ($cr) {
  $stmtCnt = $db->prepare("SELECT COUNT(*) AS c FROM terminal_assignments WHERE route_id=?");
  while ($row = $cr->fetch_assoc()) {
    $rid = $row['route_id'];
    $assignedCnt = 0;
    if ($stmtCnt) { $stmtCnt->bind_param('s', $rid); $stmtCnt->execute(); $assignedCnt = (int)($stmtCnt->get_result()->fetch_assoc()['c'] ?? 0); }
    $caps_summary[] = ['route_id'=>$rid, 'cap'=>(int)($row['cap'] ?? 0), 'assigned'=>$assignedCnt, 'ts'=>$row['ts'], 'reason'=>$row['reason'] ?? '', 'confidence'=>$row['confidence']];
    $tsv = strtotime($row['ts']);
    if ($tsv !== false) { if ($caps_last_ts === null || $tsv > strtotime($caps_last_ts)) { $caps_last_ts = $row['ts']; } }
  }
}
$caps_recent = [];
$cr2 = $db->query("SELECT route_id, cap, reason, confidence, ts FROM route_cap_schedule ORDER BY ts DESC LIMIT 10");
if ($cr2) { while ($row = $cr2->fetch_assoc()) { $caps_recent[] = $row; } }
$di_term_opts = [];
$di_route_opts = [];
$dtRes = $db->query("SELECT id, name FROM terminals ORDER BY name");
if ($dtRes) { while ($r = $dtRes->fetch_assoc()) { $di_term_opts[] = $r; } }
$drRes = $db->query("SELECT route_id, route_name FROM routes ORDER BY route_id");
if ($drRes) { while ($r = $drRes->fetch_assoc()) { $di_route_opts[] = $r; } }
$summaryLines = [];
$stmtAssign = $db->prepare("SELECT COUNT(*) AS c FROM terminal_assignments WHERE route_id=?");
foreach ($caps_summary as $c) {
  $capv = (int)($c['cap'] ?? 0);
  $assignedv = (int)($c['assigned'] ?? 0);
  if ($capv > 0 && $assignedv > $capv) {
    $summaryLines[] = 'Reduce to '.$capv.' • '.$c['route_id'];
  }
}
if (!empty($alerts)) {
  $k = 0;
  foreach ($alerts as $a) {
    if ($k >= 3) break;
    $rid = $a['route_id'] ?? '';
    $tn = $a['terminal_name'] ?? '';
    $ts = $a['ts'] ?? null;
    $trips = isset($a['forecast_trips']) ? (double)$a['forecast_trips'] : 0.0;
    $assignedCnt = 0;
    if ($rid !== '' && $stmtAssign) {
      $stmtAssign->bind_param('s', $rid);
      $stmtAssign->execute();
      $assignedCnt = (int)($stmtAssign->get_result()->fetch_assoc()['c'] ?? 0);
    }
    $need = (int)ceil(max(0.0, $trips - $assignedCnt));
    $tstr = $ts ? date('H:i', strtotime($ts)) : '';
    if ($need >= 1) {
      $summaryLines[] = ['type'=>'action', 'text'=>'Add '.$need.' vehicles to '.$rid.' at '.$tn.' by '.$tstr];
    } else {
      $summaryLines[] = ['type'=>'info', 'text'=>'Expect '.(int)round($trips).' trips on '.$rid.' at '.$tn.' by '.$tstr];
    }
    $k++;
  }
}
$stmtW = $db->prepare("SELECT w.ts, w.rainfall_mm, w.weather_code, t.name AS terminal_name FROM weather_data w JOIN terminals t ON t.id=w.terminal_id WHERE w.ts BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 120 MINUTE) ORDER BY w.ts LIMIT 5");
if ($stmtW) {
  $stmtW->execute();
  $rw = $stmtW->get_result();
  while ($row = $rw->fetch_assoc()) {
    $rain = isset($row['rainfall_mm']) ? (double)$row['rainfall_mm'] : null;
    $code = $row['weather_code'] ?? '';
    $tn = $row['terminal_name'] ?? '';
    $ts = $row['ts'] ?? null;
    $tstr = $ts ? date('H:i', strtotime($ts)) : '';
    if (($rain !== null && $rain >= 5.0) || (is_string($code) && stripos($code, 'rain') !== false)) {
      $summaryLines[] = ['type'=>'weather', 'text'=>'Heavy rain expected at '.$tn.' around '.$tstr.'. Expect delays.'];
    }
  }
}
$stmtT = $db->prepare("SELECT tr.ts, tr.congestion_index, tr.route_id, t.name AS terminal_name FROM traffic_data tr JOIN terminals t ON t.id=tr.terminal_id WHERE tr.ts BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 120 MINUTE) ORDER BY tr.congestion_index DESC LIMIT 5");
if ($stmtT) {
  $stmtT->execute();
  $rt = $stmtT->get_result();
  while ($row = $rt->fetch_assoc()) {
    $ci = isset($row['congestion_index']) ? (double)$row['congestion_index'] : null;
    $rid = $row['route_id'] ?? '';
    $tn = $row['terminal_name'] ?? '';
    $ts = $row['ts'] ?? null;
    $tstr = $ts ? date('H:i', strtotime($ts)) : '';
    if ($ci !== null && $ci >= 0.7) {
      $summaryLines[] = ['type'=>'traffic', 'text'=>'Heavy traffic on '.$rid.' near '.$tn.' ('.$tstr.').'];
    }
  }
}
$stmtE = $db->prepare("SELECT e.title, e.ts_start, e.ts_end, e.expected_attendance, t.name AS terminal_name FROM event_data e JOIN terminals t ON t.id=e.terminal_id WHERE e.ts_start BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR) ORDER BY COALESCE(e.expected_attendance,0) DESC LIMIT 5");
if ($stmtE) {
  $stmtE->execute();
  $re = $stmtE->get_result();
  while ($row = $re->fetch_assoc()) {
    $title = $row['title'] ?? '';
    $tn = $row['terminal_name'] ?? '';
    $ts = $row['ts_start'] ?? null;
    $te = $row['ts_end'] ?? null;
    $t1 = $ts ? date('H:i', strtotime($ts)) : '';
    $t2 = $te ? date('H:i', strtotime($te)) : '';
    $att = isset($row['expected_attendance']) ? (int)$row['expected_attendance'] : 0;
    if ($att >= 500) {
      $summaryLines[] = ['type'=>'event', 'text'=>'Event "'.$title.'" at '.$tn.' starts '.$t1.'.'];
    }
  }
}
$simple_summary = [];
foreach ($summaryLines as $ln) {
  if (!is_array($ln)) continue;
  $txt = trim($ln['text']);
  if ($txt === '') continue;
  // Simple dedup based on text
  $found = false;
  foreach ($simple_summary as $s) { if ($s['text'] === $txt) { $found=true; break; } }
  if (!$found) {
    $simple_summary[] = $ln;
    if (count($simple_summary) >= 6) break;
  }
}
$di_terminal_id = isset($_GET['di_terminal_id']) ? (int)$_GET['di_terminal_id'] : 0;
$di_route_id = trim($_GET['di_route_id'] ?? '');
if ($di_terminal_id <= 0 && !empty($di_term_opts)) { $di_terminal_id = (int)$di_term_opts[0]['id']; }
if ($di_route_id === '' && !empty($di_route_opts)) { $di_route_id = $di_route_opts[0]['route_id']; }
$di_term_name = '';
$di_route_name = '';
foreach ($di_term_opts as $t) { if ((int)$t['id'] === (int)$di_terminal_id) { $di_term_name = $t['name']; break; } }
foreach ($di_route_opts as $r) { if ((string)$r['route_id'] === (string)$di_route_id) { $di_route_name = $r['route_name']; break; } }
$diSeries = [];
$diKeys = [];
for ($i=23; $i>=0; $i--) { $k = date('Y-m-d H:00:00', strtotime("-$i hour")); $diSeries[$k] = 0; $diKeys[] = $k; }
if ($di_terminal_id > 0 && $di_route_id !== '') {
  $stmtDI = $db->prepare("SELECT DATE_FORMAT(l.time_in, '%Y-%m-%d %H:00:00') AS ts_hour, COUNT(*) AS trips FROM terminal_logs l JOIN vehicles v ON v.plate_number = l.vehicle_plate WHERE l.activity_type='Dispatch' AND l.terminal_id=? AND v.route_id=? AND l.time_in BETWEEN DATE_SUB(NOW(), INTERVAL 24 HOUR) AND NOW() GROUP BY ts_hour ORDER BY ts_hour");
  $stmtDI->bind_param('is', $di_terminal_id, $di_route_id);
  $stmtDI->execute();
  $resDI = $stmtDI->get_result();
  while ($row = $resDI->fetch_assoc()) { $diSeries[$row['ts_hour']] = (int)$row['trips']; }
}
$diMax = 0;
foreach ($diSeries as $v) { if ($v > $diMax) { $diMax = $v; } }
$di_overlay = isset($_GET['di_overlay']) ? (int)$_GET['di_overlay'] : 0;
$fcRows = [];
$fcMax = 0;
if ($di_overlay === 1 && $di_terminal_id > 0 && $di_route_id !== '') {
  $stmtFC = $db->prepare("SELECT ts, forecast_trips FROM demand_forecasts WHERE terminal_id=? AND route_id=? AND ts BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 240 MINUTE) ORDER BY ts");
  $stmtFC->bind_param('is', $di_terminal_id, $di_route_id);
  $stmtFC->execute();
  $resFC = $stmtFC->get_result();
  while ($row = $resFC->fetch_assoc()) {
    $fcRows[] = $row;
    $v = (double)($row['forecast_trips'] ?? 0.0);
    if ($v > $fcMax) { $fcMax = $v; }
  }
}
$scaleMax = max($diMax, (int)ceil($fcMax));

// Forecasts (moved from Module 5)
$forecasts = [];
$resF = $db->query("SELECT df.ts, df.forecast_trips, df.lower_ci, df.upper_ci, df.model_version, r.route_name, t.name as terminal_name 
                    FROM demand_forecasts df 
                    JOIN routes r ON r.route_id=df.route_id 
                    JOIN terminals t ON t.id=df.terminal_id 
                    WHERE df.ts >= NOW() 
                    ORDER BY df.ts ASC LIMIT 10");
if ($resF) { while($row = $resF->fetch_assoc()) $forecasts[] = $row; }
$routes = $di_route_opts; // Alias for compatibility
$terminals = $di_term_opts; // Alias for compatibility
?>
<div class="mx-1 mt-1 p-6 dark:bg-slate-900 bg-white dark:text-slate-300 rounded-lg">
  <h1 class="text-2xl font-bold mb-4">City Transport Analytics</h1>
  <p class="text-sm text-slate-600 dark:text-slate-400 mb-6">Phase 6 Monitoring & Analytics: system-wide status, compliance, and operations snapshot.</p>
  <div class="mb-6">
    <div class="flex items-center gap-2 mb-3">
      <i data-lucide="sparkles" class="w-5 h-5 text-indigo-600 dark:text-indigo-400"></i>
      <h2 class="text-lg font-semibold text-slate-800 dark:text-slate-100">AI Insights</h2>
    </div>

  <!-- Model Performance -->
  <div class="mt-6 p-4 border rounded-lg dark:border-slate-700 bg-white dark:bg-slate-900 shadow-sm">
    <div class="flex justify-between items-center mb-3">
        <h2 class="text-lg font-semibold flex items-center gap-2 text-slate-800 dark:text-slate-100">
            <i data-lucide="bar-chart-2" class="w-5 h-5 text-indigo-500"></i>
            Model Performance (Last 7 Days)
        </h2>
        <button id="btnEvalModel" class="px-3 py-1.5 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 text-xs font-medium rounded hover:bg-indigo-100 dark:hover:bg-indigo-900/50 transition-colors flex items-center gap-1.5">
            <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
            Evaluate Now
        </button>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm text-left">
        <thead class="text-xs text-slate-500 uppercase bg-slate-50 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700">
          <tr>
            <th class="px-4 py-3">Route</th>
            <th class="px-4 py-3 text-right">Samples</th>
            <th class="px-4 py-3 text-right">MAPE</th>
            <th class="px-4 py-3 text-right">RMSE</th>
            <th class="px-4 py-3 text-center">Accuracy</th>
            <th class="px-4 py-3 text-center">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-700" id="evalResultsBody">
          <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500 italic">Click 'Evaluate Now' to compute metrics.</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <script>
  document.getElementById('btnEvalModel').addEventListener('click', function() {
    var btn = this;
    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader-2" class="w-3.5 h-3.5 animate-spin"></i> Computing...';
    if(window.lucide) window.lucide.createIcons();
    
    fetch('admin/api/analytics/evaluate_all.php?lookback_hours=168')
      .then(r => r.json())
      .then(d => {
         btn.disabled = false;
         btn.innerHTML = '<i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i> Evaluate Now';
         if(d.ok) {
             var html = '';
             if(d.results.length === 0) {
                 html = '<tr><td colspan="6" class="px-4 py-8 text-center text-slate-500 italic">No forecast data found for evaluation in the last 7 days.</td></tr>';
             } else {
                 d.results.forEach(r => {
                     var accColor = 'text-slate-600 dark:text-slate-400';
                     var status = '<span class="px-2 py-0.5 rounded text-xs bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400">Insufficient Data</span>';
                     
                     if (r.accuracy !== null) {
                         if (r.accuracy >= 80) {
                             accColor = 'text-emerald-600 dark:text-emerald-400 font-bold';
                             status = '<span class="px-2 py-0.5 rounded text-xs bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400">Excellent</span>';
                         } else if (r.accuracy >= 60) {
                             accColor = 'text-amber-600 dark:text-amber-400 font-bold';
                             status = '<span class="px-2 py-0.5 rounded text-xs bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">Needs Tuning</span>';
                         } else {
                             accColor = 'text-rose-600 dark:text-rose-400 font-bold';
                             status = '<span class="px-2 py-0.5 rounded text-xs bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-400">Poor</span>';
                         }
                     }
                     
                     html += `<tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                        <td class="px-4 py-3 font-medium text-slate-700 dark:text-slate-200">${r.route_id}</td>
                        <td class="px-4 py-3 text-right text-slate-600 dark:text-slate-400">${r.samples}</td>
                        <td class="px-4 py-3 text-right text-slate-600 dark:text-slate-400">${r.mape !== null ? r.mape + '%' : '-'}</td>
                        <td class="px-4 py-3 text-right text-slate-600 dark:text-slate-400">${r.rmse !== null ? r.rmse : '-'}</td>
                        <td class="px-4 py-3 text-center ${accColor}">${r.accuracy !== null ? r.accuracy + '%' : '-'}</td>
                        <td class="px-4 py-3 text-center">${status}</td>
                     </tr>`;
                 });
             }
             document.getElementById('evalResultsBody').innerHTML = html;
         } else {
             alert('Error: ' + (d.error || 'Unknown error'));
         }
         if(window.lucide) window.lucide.createIcons();
      })
      .catch(e => {
          btn.disabled = false;
          btn.innerHTML = '<i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i> Evaluate Now';
          alert('Network error');
          if(window.lucide) window.lucide.createIcons();
      });
  });
  </script>

<script>
  // --- AI Forecast & Caps Logic (Moved from Module 5) ---
  const runForecastBtn = document.getElementById('runForecastBtn');
  const forecastStatusEl = document.getElementById('forecastStatus');
  const forecastResultsBody = document.getElementById('forecastResultsBody');
  const computeCapsBtn = document.getElementById('computeCapsBtn');
  const computeCapsStatus = document.getElementById('computeCapsStatus');
  const computeCapsResults = document.getElementById('computeCapsResults');

  runForecastBtn?.addEventListener('click', async function() {
    const terminal_id = document.getElementById('forecastTerminal')?.value || '';
    const route_id = document.getElementById('forecastRoute')?.value || '';
    const horizon_min = document.getElementById('horizonMin')?.value || '240';
    const granularity_min = document.getElementById('granularityMin')?.value || '60';

    if (!terminal_id || !route_id) return;
    
    runForecastBtn.disabled = true;
    const prevText = runForecastBtn.innerHTML;
    runForecastBtn.innerHTML = '<i data-lucide="loader-2" class="w-3 h-3 animate-spin"></i> Running...';
    if(window.lucide) lucide.createIcons();
    forecastStatusEl.textContent = 'Forecasting...';

    try {
      const res = await fetch('/tmm/admin/api/analytics/run_forecast.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ terminal_id, route_id, horizon_min, granularity_min })
      });
      const data = await res.json();
      if (data.ok && Array.isArray(data.forecasts)) {
        forecastStatusEl.textContent = 'Done. ' + (data.inserted || 0) + ' saved.';
        renderForecastTable(data.forecasts, parseInt(granularity_min));
        setTimeout(() => { window.location.reload(); }, 2000); 
      } else {
        forecastStatusEl.textContent = 'Error: ' + (data.error || 'Unknown');
      }
    } catch (e) {
      forecastStatusEl.textContent = 'Network error';
      console.error(e);
    } finally {
      runForecastBtn.innerHTML = prevText;
      runForecastBtn.disabled = false;
      if(window.lucide) lucide.createIcons();
    }
  });

  function renderForecastTable(items, granMin) {
    if(!forecastResultsBody) return;
    let html = '';
    if(items.length === 0) {
        html = '<tr><td colspan="7" class="px-4 py-8 text-center text-slate-500 italic">No active forecasts.</td></tr>';
    } else {
        for(const f of items) {
            const ts = f.ts || '';
            const ft = parseFloat(f.forecast_trips || 0);
            const recVeh = Math.ceil(ft);
            const hw = (ft > 0) ? (60 / ft).toFixed(1) : '-';
            const lower = parseFloat(f.lower_ci || 0);
            const upper = parseFloat(f.upper_ci || 0);
            let conf = 80;
            if((upper - lower) > 0) {
                conf = (1 - ((upper - lower) / (upper + lower))) * 100;
            }
            conf = Math.min(100, Math.max(0, conf));
            
            html += `<tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
              <td class="px-4 py-3 font-medium text-slate-700 dark:text-slate-200">${new Date(ts).toLocaleString('en-US', {month:'short', day:'numeric', hour:'numeric', minute:'numeric', hour12:false})}</td>
              <td class="px-4 py-3 text-slate-600 dark:text-slate-400">Route ${f.route_id || ''}</td>
              <td class="px-4 py-3 text-right font-bold text-indigo-600 dark:text-indigo-400">${ft.toFixed(1)}</td>
              <td class="px-4 py-3 text-right"><span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400">${recVeh} PUVs</span></td>
              <td class="px-4 py-3 text-right text-slate-600 dark:text-slate-400">${hw === '-' ? '-' : hw + ' min'}</td>
              <td class="px-4 py-3 text-center"><div class="w-16 h-1.5 bg-slate-200 dark:bg-slate-700 rounded-full mx-auto overflow-hidden" title="${Math.round(conf)}%"><div class="h-full bg-indigo-500" style="width: ${conf}%"></div></div></td>
              <td class="px-4 py-3 text-xs text-slate-400">${f.model_version || ''}</td>
            </tr>`;
        }
    }
    forecastResultsBody.innerHTML = html;
  }

  computeCapsBtn?.addEventListener('click', async function() {
    const route_id = document.getElementById('capsRoute')?.value || '';
    const horizon_min = document.getElementById('capsHorizon')?.value || '240';
    const theta = document.getElementById('capsTheta')?.value || '0.7';
    const min_confidence = document.getElementById('capsConfidence')?.value || '0.6';
    const dry_run = document.getElementById('capsDryRun')?.checked ? '1' : '0';

    computeCapsBtn.disabled = true;
    computeCapsStatus.textContent = 'Computing...';
    
    try {
      const res = await fetch('/tmm/admin/api/analytics/compute_caps.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ route_id, horizon_min, theta, min_confidence, dry_run })
      });
      const data = await res.json();
      if (data.ok) {
        computeCapsStatus.textContent = 'Done. ' + (data.computed_count || 0) + ' computed.';
        renderCapsTable(data.results || []);
      } else {
        computeCapsStatus.textContent = 'Error: ' + (data.error || 'Unknown');
      }
    } catch (e) {
      computeCapsStatus.textContent = 'Network error';
    } finally {
      computeCapsBtn.disabled = false;
    }
  });

  function renderCapsTable(items) {
    if(!computeCapsResults) return;
    let html = '<table class="w-full text-sm text-left"><thead class="text-xs text-slate-500 uppercase bg-slate-100 dark:bg-slate-700 sticky top-0"><tr><th class="px-3 py-2">Route</th><th class="px-3 py-2 text-right">New Cap</th><th class="px-3 py-2 text-right">Confidence</th><th class="px-3 py-2">Reason</th></tr></thead><tbody>';
    if(items.length === 0) {
        html += '<tr><td colspan="4" class="px-3 py-2 text-center text-slate-500">No caps computed.</td></tr>';
    } else {
        for (const c of items) {
            html += `<tr class="border-b dark:border-slate-700">
              <td class="px-3 py-2 font-medium">${c.route_id}</td>
              <td class="px-3 py-2 text-right font-bold">${c.cap}</td>
              <td class="px-3 py-2 text-right">${parseFloat(c.confidence).toFixed(2)}</td>
              <td class="px-3 py-2 text-slate-600 dark:text-slate-400 text-xs">${c.reason}</td>
            </tr>`;
        }
    }
    html += '</tbody></table>';
    computeCapsResults.innerHTML = html;
  }
</script>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <!-- 1. Simple AI Summary (Command Center) -->
      <div class="md:col-span-2 p-4 rounded-xl bg-white dark:bg-slate-800 border border-indigo-100 dark:border-slate-700 shadow-sm relative overflow-hidden">
        <div class="absolute top-0 right-0 p-3 opacity-5">
          <i data-lucide="bot" class="w-24 h-24"></i>
        </div>
        <div class="flex items-center justify-between mb-3 relative z-10">
          <div class="text-sm font-medium text-slate-500 uppercase tracking-wider">Assistant</div>
          <div class="text-xs text-slate-400"><?php echo htmlspecialchars(date('H:i')); ?></div>
        </div>
        <div class="space-y-3 relative z-10">
          <?php if (!empty($simple_summary)): foreach ($simple_summary as $item): 
            $sType = $item['type'] ?? 'info';
            $sText = $item['text'] ?? '';
            $icon = 'info'; $color = 'text-blue-600 bg-blue-50 dark:bg-blue-900/30 dark:text-blue-400 border-blue-100 dark:border-blue-800';
            if ($sType === 'action') { $icon = 'arrow-right-circle'; $color = 'text-emerald-600 bg-emerald-50 dark:bg-emerald-900/30 dark:text-emerald-400 border-emerald-100 dark:border-emerald-800'; }
            if ($sType === 'weather') { $icon = 'cloud-rain'; $color = 'text-sky-600 bg-sky-50 dark:bg-sky-900/30 dark:text-sky-400 border-sky-100 dark:border-sky-800'; }
            if ($sType === 'traffic') { $icon = 'alert-triangle'; $color = 'text-amber-600 bg-amber-50 dark:bg-amber-900/30 dark:text-amber-400 border-amber-100 dark:border-amber-800'; }
            if ($sType === 'event') { $icon = 'calendar'; $color = 'text-purple-600 bg-purple-50 dark:bg-purple-900/30 dark:text-purple-400 border-purple-100 dark:border-purple-800'; }
          ?>
            <div class="flex gap-3 p-3 rounded-lg border <?php echo $color; ?>">
              <div class="mt-0.5"><i data-lucide="<?php echo $icon; ?>" class="w-4 h-4"></i></div>
              <div class="text-sm font-medium leading-tight"><?php echo htmlspecialchars($sText); ?></div>
            </div>
          <?php endforeach; else: ?>
            <div class="p-4 text-center text-slate-500 text-sm bg-slate-50 dark:bg-slate-800/50 rounded-lg">
              <i data-lucide="check-circle" class="w-5 h-5 mx-auto mb-1 opacity-50"></i>
              No immediate actions recommended.
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- 2. Technical Metrics Column -->
      <div class="space-y-4">
        <!-- Upcoming Alerts -->
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
          <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center bg-slate-50/50 dark:bg-slate-800/50">
            <div class="flex items-center gap-2">
              <div class="p-1 rounded bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">
                <i data-lucide="bar-chart-2" class="w-3.5 h-3.5"></i>
              </div>
              <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Demand Forecast (2h)</h3>
            </div>
          </div>
          <div class="p-4 space-y-3 text-sm">
            <?php if (!empty($alerts)): foreach ($alerts as $a): $t = $a['ts'] ?? ''; $trips = (float)($a['forecast_trips'] ?? 0); ?>
              <div class="flex items-center justify-between group">
                <div class="flex flex-col">
                  <span class="font-medium text-slate-700 dark:text-slate-300 group-hover:text-blue-600 transition-colors"><?php echo htmlspecialchars($a['route_id'] ?? ''); ?></span>
                  <span class="text-xs text-slate-400"><?php echo htmlspecialchars(date('H:i', strtotime($t)).' • '.($a['terminal_name'] ?? '')); ?></span>
                </div>
                <span class="font-bold text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-700 px-2 py-0.5 rounded border border-slate-200 dark:border-slate-600"><?php echo number_format($trips,1); ?></span>
              </div>
            <?php endforeach; else: ?>
              <div class="text-slate-500 text-xs italic text-center py-2">No high-demand alerts.</div>
            <?php endif; ?>
          </div>
        </div>
        
        <!-- AI Status -->
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden p-4 flex items-center justify-between">
          <div>
            <div class="text-xs text-slate-500 uppercase tracking-wide mb-1 font-semibold">System Status</div>
            <div class="flex items-center gap-2">
              <span class="relative flex h-2.5 w-2.5">
                <?php if(($ai_job && ($ai_job['status']??'')==='succeeded')): ?>
                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                  <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                <?php else: ?>
                  <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-500"></span>
                <?php endif; ?>
              </span>
              <span class="font-semibold text-sm text-slate-700 dark:text-slate-200"><?php echo htmlspecialchars($ai_job['status'] ?? 'Unknown'); ?></span>
            </div>
          </div>
          <div class="text-right">
             <div class="text-xs text-slate-400 mb-0.5">Last Run</div>
             <div class="text-xs font-mono text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-700 px-1.5 py-0.5 rounded"><?php echo htmlspecialchars($ai_job ? date('H:i', strtotime($ai_job['finished_at'] ?? $ai_job['started_at'])) : '--:--'); ?></div>
          </div>
        </div>
      </div>

      <!-- 3. Caps & Controls Column -->
      <div class="space-y-4">
        <!-- Capped Routes -->
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
           <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center bg-slate-50/50 dark:bg-slate-800/50">
            <div class="flex items-center gap-2">
              <div class="p-1 rounded bg-rose-100 text-rose-600 dark:bg-rose-900/30 dark:text-rose-400">
                <i data-lucide="shield-alert" class="w-3.5 h-3.5"></i>
              </div>
              <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Active Caps</h3>
            </div>
            <?php
              $capped = 0; $over = 0;
              foreach ($caps_summary as $c) { if (($c['cap'] ?? 0) > 0) { $capped++; if ($c['assigned'] >= $c['cap']) $over++; } }
              $topCaps = array_slice(array_filter($caps_summary, function($c){ return ($c['cap'] ?? 0) > 0; }), 0, 3);
            ?>
            <div class="flex gap-2 text-xs">
              <span class="px-1.5 py-0.5 bg-slate-200 dark:bg-slate-700 rounded text-slate-600 dark:text-slate-300 font-medium"><?php echo $capped; ?> Active</span>
              <?php if ($over>0): ?><span class="px-1.5 py-0.5 bg-rose-100 dark:bg-rose-900/30 text-rose-600 dark:text-rose-400 rounded font-bold border border-rose-200 dark:border-rose-800"><?php echo $over; ?> Over</span><?php endif; ?>
            </div>
          </div>
          <div class="p-4 space-y-3 text-sm">
            <?php if (!empty($topCaps)): foreach ($topCaps as $c): $isRecent = false; $tsv = strtotime($c['ts']); if ($tsv !== false) { $isRecent = (time() - $tsv) <= 3600; } 
                $pct = $c['cap']>0 ? min(100, round(($c['assigned']/$c['cap'])*100)) : 0;
            ?>
              <div>
                <div class="flex justify-between items-center mb-1">
                  <span class="font-medium text-slate-700 dark:text-slate-300"><?php echo htmlspecialchars($c['route_id']); ?></span>
                  <span class="text-xs <?php echo $c['assigned'] >= $c['cap'] ? 'text-rose-500 font-bold' : 'text-slate-500'; ?>">
                    <?php echo $c['assigned'].'/'.$c['cap']; ?>
                  </span>
                </div>
                <div class="w-full bg-slate-100 dark:bg-slate-700 rounded-full h-1.5 overflow-hidden">
                  <div class="bg-rose-500 h-1.5 rounded-full transition-all duration-500" style="width: <?php echo $pct; ?>%"></div>
                </div>
              </div>
            <?php endforeach; else: ?>
              <div class="text-slate-500 text-xs italic text-center py-2">No active caps.</div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Dispatch Chart Mini -->
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden p-4">
          <div class="flex items-center justify-between mb-3">
             <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Dispatch Trend (24h)</div>
             <i data-lucide="activity" class="w-3.5 h-3.5 text-emerald-500"></i>
          </div>
           <div class="flex items-end gap-1 h-12 justify-between">
            <?php foreach ($diKeys as $k): $c = (int)$diSeries[$k]; $h = $scaleMax>0 ? max(4, (int)round(($c/$scaleMax)*48)) : 4; ?>
              <div class="w-full bg-emerald-500/80 hover:bg-emerald-600 transition-colors rounded-t-sm" style="height: <?php echo $h; ?>px;" title="<?php echo date('H:i', strtotime($k)).': '.$c; ?>"></div>
            <?php endforeach; ?>
           </div>
        </div>
      </div>
  </div>

  <!-- Forecast Panel (AI Tools) -->
  <div class="mt-6 p-4 border rounded-lg dark:border-slate-700 bg-white dark:bg-slate-900 shadow-sm">
    <h2 class="text-lg font-semibold mb-3 flex items-center gap-2 text-slate-800 dark:text-slate-100">
      <i data-lucide="zap" class="w-5 h-5 text-indigo-500"></i>
      Demand Forecasts & Recommendations
    </h2>
    <div class="grid grid-cols-1 md:grid-cols-5 gap-3 mb-4">
      <div>
        <label class="block text-xs font-medium text-slate-500 mb-1">Terminal</label>
        <select id="forecastTerminal" class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700">
          <?php foreach($terminals as $t): ?>
            <option value="<?php echo (int)$t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="md:col-span-2">
        <label class="block text-xs font-medium text-slate-500 mb-1">Route</label>
        <select id="forecastRoute" class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700">
          <?php foreach($routes as $r): ?>
            <option value="<?php echo htmlspecialchars($r['route_id']); ?>"><?php echo htmlspecialchars(($r['route_id'] ?? '').' • '.($r['route_name'] ?? '')); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-500 mb-1">Granularity (min)</label>
        <select id="granularityMin" class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700">
          <option value="60">60</option>
          <option value="30">30</option>
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-500 mb-1">Horizon (min)</label>
        <input id="horizonMin" type="number" value="240" min="30" step="30" class="w-full px-3 py-2 border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700">
      </div>
    </div>
    <div class="flex items-center gap-2 mb-3">
      <button id="runForecastBtn" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded shadow-sm transition-all flex items-center gap-2">
        <i data-lucide="play" class="w-3 h-3"></i> Run Forecast AI
      </button>
      <div id="forecastStatus" class="text-sm text-slate-600 dark:text-slate-400"></div>
    </div>
    
    <div class="overflow-x-auto">
      <table class="w-full text-sm text-left border-collapse">
        <thead class="text-xs text-slate-500 uppercase bg-slate-50 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700">
          <tr>
            <th class="px-4 py-3">Time Slot</th>
            <th class="px-4 py-3">Route</th>
            <th class="px-4 py-3 text-right">Forecast Trips</th>
            <th class="px-4 py-3 text-right">Rec. Vehicles</th>
            <th class="px-4 py-3 text-right">Headway</th>
            <th class="px-4 py-3 text-center">Confidence</th>
            <th class="px-4 py-3">Model</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-700" id="forecastResultsBody">
          <?php if (empty($forecasts)): ?>
            <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500 italic">No active forecasts. Click 'Run Forecast AI' to generate.</td></tr>
          <?php else: foreach($forecasts as $f): 
             $ft = (float)$f['forecast_trips'];
             $recVeh = ceil($ft); 
             $hw = ($ft > 0) ? round(60 / $ft, 1) : '-';
             $conf = ($f['upper_ci'] - $f['lower_ci']) > 0 ? (1 - (($f['upper_ci'] - $f['lower_ci']) / ($f['upper_ci'] + $f['lower_ci']))) * 100 : 80; 
             $conf = min(100, max(0, $conf));
          ?>
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
              <td class="px-4 py-3 font-medium text-slate-700 dark:text-slate-200">
                <?php echo date('M d, H:i', strtotime($f['ts'])); ?>
              </td>
              <td class="px-4 py-3 text-slate-600 dark:text-slate-400">
                <?php echo htmlspecialchars($f['route_name']); ?>
              </td>
              <td class="px-4 py-3 text-right font-bold text-indigo-600 dark:text-indigo-400">
                <?php echo number_format($ft, 1); ?>
              </td>
              <td class="px-4 py-3 text-right">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400">
                  <?php echo $recVeh; ?> PUVs
                </span>
              </td>
              <td class="px-4 py-3 text-right text-slate-600 dark:text-slate-400">
                <?php echo $hw === '-' ? '-' : $hw . ' min'; ?>
              </td>
              <td class="px-4 py-3 text-center">
                 <div class="w-16 h-1.5 bg-slate-200 dark:bg-slate-700 rounded-full mx-auto overflow-hidden" title="<?php echo round($conf); ?>%">
                   <div class="h-full bg-indigo-500" style="width: <?php echo $conf; ?>%"></div>
                 </div>
              </td>
              <td class="px-4 py-3 text-xs text-slate-400">
                <?php echo htmlspecialchars($f['model_version']); ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Current Dynamic Caps Status -->
  <div class="mt-6 p-4 border rounded-lg dark:border-slate-700 bg-white dark:bg-slate-900 shadow-sm">
    <div class="flex justify-between items-center mb-3">
        <h2 class="text-lg font-semibold flex items-center gap-2 text-slate-800 dark:text-slate-100">
            <i data-lucide="activity" class="w-5 h-5 text-emerald-500"></i>
            Current Dynamic Caps Status
        </h2>
        <div class="text-xs text-slate-500">Last updated <?php echo $caps_last_ts ? htmlspecialchars($caps_last_ts) : '—'; ?></div>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm text-left">
        <thead class="text-xs text-slate-500 uppercase bg-slate-50 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700">
          <tr>
            <th class="px-4 py-3">Route</th>
            <th class="px-4 py-3 text-right">Cap</th>
            <th class="px-4 py-3 text-right">Assigned</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3">Updated</th>
            <th class="px-4 py-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
          <?php if (!empty($caps_summary)): foreach($caps_summary as $c): $ok = $c['cap'] === 0 ? true : ($c['assigned'] < $c['cap']); $isRecent = false; $tsv = strtotime($c['ts']); if ($tsv !== false) { $isRecent = (time() - $tsv) <= 3600; } ?>
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
              <td class="px-4 py-3 font-medium text-slate-700 dark:text-slate-200"><?php echo htmlspecialchars($c['route_id']); ?></td>
              <td class="px-4 py-3 text-right font-bold text-slate-700 dark:text-slate-300"><?php echo (int)$c['cap']; ?></td>
              <td class="px-4 py-3 text-right text-slate-600 dark:text-slate-400"><?php echo (int)$c['assigned']; ?></td>
              <td class="px-4 py-3">
                  <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?php echo $ok ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-400'; ?>">
                    <?php echo $ok ? 'Within cap' : 'Over cap'; ?>
                  </span>
                  <?php if ($isRecent): ?> <span class="ml-2 px-2 py-0.5 text-xs bg-amber-100 text-amber-700 rounded-full">New</span><?php endif; ?>
              </td>
              <td class="px-4 py-3 text-xs text-slate-500"><?php echo htmlspecialchars($c['ts']); ?></td>
              <td class="px-4 py-3 text-right">
                  <button onclick="openOverrideModal('<?php echo htmlspecialchars($c['route_id']); ?>', <?php echo (int)$c['cap']; ?>)" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 text-xs font-medium">
                      Override
                  </button>
              </td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500 italic">No caps configured.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Manual Override Modal -->
  <div id="overrideModal" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-sm flex items-center justify-center">
      <div class="bg-white dark:bg-slate-800 rounded-lg shadow-xl w-full max-w-md mx-4 overflow-hidden border border-slate-200 dark:border-slate-700">
          <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center bg-slate-50/50 dark:bg-slate-800/50">
              <h3 class="font-semibold text-slate-800 dark:text-slate-100">Manual Cap Override</h3>
              <button onclick="closeOverrideModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
                  <i data-lucide="x" class="w-5 h-5"></i>
              </button>
          </div>
          <div class="p-6">
              <div class="mb-4">
                  <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Route</label>
                  <input type="text" id="ovRouteId" readonly class="w-full px-3 py-2 border rounded bg-slate-100 text-slate-500 dark:bg-slate-900 dark:border-slate-700 cursor-not-allowed">
              </div>
              <div class="mb-4">
                  <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">New Cap</label>
                  <input type="number" id="ovCap" min="-1" class="w-full px-3 py-2 border rounded bg-white dark:bg-slate-900 dark:border-slate-700 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                  <p class="text-xs text-slate-500 mt-1">Set to -1 for uncapped.</p>
              </div>
              <div class="mb-4">
                  <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Reason</label>
                  <input type="text" id="ovReason" placeholder="e.g. Special Event, Traffic Incident" class="w-full px-3 py-2 border rounded bg-white dark:bg-slate-900 dark:border-slate-700 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
              </div>
              <div id="ovStatus" class="mb-4 text-sm hidden"></div>
              <div class="flex justify-end gap-3">
                  <button onclick="closeOverrideModal()" class="px-4 py-2 text-slate-600 hover:bg-slate-100 rounded dark:text-slate-400 dark:hover:bg-slate-700 transition-colors">Cancel</button>
                  <button onclick="submitOverride()" id="btnSaveOverride" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded shadow-sm transition-colors flex items-center gap-2">
                      <i data-lucide="save" class="w-4 h-4"></i> Save Override
                  </button>
              </div>
          </div>
      </div>
  </div>

  <script>
    function openOverrideModal(routeId, currentCap) {
        document.getElementById('ovRouteId').value = routeId;
        document.getElementById('ovCap').value = currentCap;
        document.getElementById('ovReason').value = '';
        document.getElementById('ovStatus').classList.add('hidden');
        document.getElementById('overrideModal').classList.remove('hidden');
    }
    
    function closeOverrideModal() {
        document.getElementById('overrideModal').classList.add('hidden');
    }
    
    function submitOverride() {
        const routeId = document.getElementById('ovRouteId').value;
        const cap = document.getElementById('ovCap').value;
        const reason = document.getElementById('ovReason').value;
        const btn = document.getElementById('btnSaveOverride');
        const statusEl = document.getElementById('ovStatus');
        
        if (!routeId || cap === '') return;
        
        btn.disabled = true;
        btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Saving...';
        
        fetch('admin/api/analytics/override_cap.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ route_id: routeId, cap: cap, reason: reason })
        })
        .then(r => r.json())
        .then(d => {
            if (d.ok) {
                statusEl.innerHTML = '<span class="text-emerald-600">Override saved successfully. Reloading...</span>';
                statusEl.classList.remove('hidden');
                setTimeout(() => { window.location.reload(); }, 1000);
            } else {
                statusEl.innerHTML = '<span class="text-rose-600">Error: ' + (d.error || 'Unknown') + '</span>';
                statusEl.classList.remove('hidden');
                btn.disabled = false;
                btn.innerHTML = '<i data-lucide="save" class="w-4 h-4"></i> Save Override';
            }
        })
        .catch(e => {
            statusEl.innerHTML = '<span class="text-rose-600">Network error</span>';
            statusEl.classList.remove('hidden');
            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="save" class="w-4 h-4"></i> Save Override';
        });
    }
  </script>





</div>
  <!-- Recent Cap Changes (Modernized) -->
  <div class="mb-6 bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center bg-slate-50/50 dark:bg-slate-800/50">
      <div class="flex items-center gap-2">
        <div class="p-1.5 rounded-lg bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400">
          <i data-lucide="bar-chart-3" class="w-4 h-4"></i>
        </div>
        <h2 class="text-base font-semibold text-slate-800 dark:text-slate-100">Recent Cap Changes</h2>
      </div>
      <button class="text-slate-400 hover:text-emerald-600 transition-colors">
        <i data-lucide="refresh-cw" class="w-4 h-4"></i>
      </button>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm text-left">
        <thead class="text-xs text-slate-500 uppercase bg-slate-50/80 dark:bg-slate-800/80 border-b border-slate-100 dark:border-slate-700">
          <tr>
            <th class="px-6 py-3 font-medium">Route</th>
            <th class="px-6 py-3 font-medium text-right">Cap</th>
            <th class="px-6 py-3 font-medium text-right">Confidence</th>
            <th class="px-6 py-3 font-medium">Reason</th>
            <th class="px-6 py-3 font-medium text-right">Updated</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
          <?php if (!empty($caps_recent)): foreach ($caps_recent as $a): $rs = $a['reason'] ?? ''; $rs = mb_strlen($rs) > 80 ? mb_substr($rs, 0, 80).'…' : $rs; ?>
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors group">
              <td class="px-6 py-3 font-medium text-slate-700 dark:text-slate-200">
                <div class="flex items-center gap-2">
                  <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                  <?php echo htmlspecialchars($a['route_id']); ?>
                </div>
              </td>
              <td class="px-6 py-3 text-right">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300">
                  <?php echo (int)($a['cap'] ?? 0); ?>
                </span>
              </td>
              <td class="px-6 py-3 text-right">
                <div class="flex items-center justify-end gap-2">
                  <div class="w-16 h-1.5 bg-slate-100 rounded-full overflow-hidden dark:bg-slate-700">
                    <div class="h-full bg-emerald-500 rounded-full" style="width: <?php echo min(100, max(0, ((float)($a['confidence']??0))*100)); ?>%"></div>
                  </div>
                  <span class="text-xs text-slate-500"><?php echo isset($a['confidence']) ? number_format((float)$a['confidence'], 2) : '—'; ?></span>
                </div>
              </td>
              <td class="px-6 py-3 text-slate-600 dark:text-slate-400 max-w-xs truncate" title="<?php echo htmlspecialchars($a['reason']??''); ?>">
                <?php echo htmlspecialchars($rs); ?>
              </td>
              <td class="px-6 py-3 text-right text-slate-500 text-xs">
                <?php echo htmlspecialchars($a['ts'] ?? ''); ?>
              </td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="5" class="px-6 py-8 text-center text-slate-500 italic">No recent cap changes found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Compute Caps Form (Modernized) -->
  <div class="mb-6 bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center bg-slate-50/50 dark:bg-slate-800/50">
      <div class="flex items-center gap-2">
        <div class="p-1.5 rounded-lg bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400">
          <i data-lucide="calculator" class="w-4 h-4"></i>
        </div>
        <h2 class="text-base font-semibold text-slate-800 dark:text-slate-100">Compute Caps</h2>
      </div>
    </div>
    
    <div class="p-6">
      <form id="dashComputeCapsForm" class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
        <div class="md:col-span-3">
          <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Horizon (min)</label>
          <div class="relative">
            <i data-lucide="clock" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
            <input id="dashCapsHorizon" type="number" value="120" min="60" step="60" class="w-full pl-9 pr-3 py-2 text-sm border-slate-200 rounded-lg focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all dark:bg-slate-900 dark:border-slate-700">
          </div>
        </div>
        <div class="md:col-span-2">
          <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Theta (θ)</label>
          <div class="relative">
            <i data-lucide="percent" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
            <input id="dashCapsTheta" type="number" value="0.7" min="0.1" max="0.9" step="0.05" class="w-full pl-9 pr-3 py-2 text-sm border-slate-200 rounded-lg focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all dark:bg-slate-900 dark:border-slate-700">
          </div>
        </div>
        <div class="md:col-span-2">
          <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Min Conf</label>
          <div class="relative">
            <i data-lucide="check-circle-2" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
            <input id="dashCapsConf" type="number" value="0.6" min="0" max="1" step="0.05" class="w-full pl-9 pr-3 py-2 text-sm border-slate-200 rounded-lg focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all dark:bg-slate-900 dark:border-slate-700">
          </div>
        </div>
        <div class="md:col-span-2 pb-2">
          <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300 cursor-pointer select-none">
            <input id="dashCapsDryRun" type="checkbox" class="rounded border-slate-300 text-amber-600 focus:ring-amber-500" checked>
            <span>Preview Mode</span>
          </label>
        </div>
        <div class="md:col-span-3">
          <button id="dashComputeCapsBtn" type="button" class="w-full px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-sm font-medium rounded-lg shadow-sm hover:shadow-md transition-all flex items-center justify-center gap-2">
            <i data-lucide="play" class="w-4 h-4"></i>
            Run Computation
          </button>
        </div>
      </form>
      
      <div id="dashComputeCapsStatus" class="mt-4 text-sm text-slate-600 dark:text-slate-400 min-h-[20px]"></div>
      <div id="dashComputeCapsResults" class="mt-2 bg-slate-50 dark:bg-slate-900/50 rounded-lg border border-slate-200 dark:border-slate-700 overflow-hidden hidden empty:hidden"></div>
    </div>
  </div>
  <script>
    (function(){
      // Re-initialize icons for dynamic content
      if(window.lucide) window.lucide.createIcons();
      
      var btn = document.getElementById('dashComputeCapsBtn');
      if (!btn) return;
      btn.addEventListener('click', function(){
        var h = document.getElementById('dashCapsHorizon').value;
        var t = document.getElementById('dashCapsTheta').value;
        var c = document.getElementById('dashCapsConf').value;
        var d = document.getElementById('dashCapsDryRun').checked ? 'true' : 'false';
        var statusEl = document.getElementById('dashComputeCapsStatus');
        var resEl = document.getElementById('dashComputeCapsResults');
        
        // UI Loading State
        var originalBtnContent = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Processing...';
        if(window.lucide) window.lucide.createIcons();
        
        statusEl.innerHTML = '<span class="flex items-center gap-2 text-amber-600"><i data-lucide="loader" class="w-4 h-4 animate-spin"></i> Running cap computation...</span>';
        if(window.lucide) window.lucide.createIcons();

        fetch('/tmm/admin/api/analytics/compute_caps.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: 'horizon_min='+encodeURIComponent(h)+'&theta='+encodeURIComponent(t)+'&min_confidence='+encodeURIComponent(c)+'&dry_run='+encodeURIComponent(d)+'&role=Admin'
        }).then(function(r){ return r.json(); }).then(function(j){
          btn.disabled = false;
          btn.innerHTML = originalBtnContent;
          if(window.lucide) window.lucide.createIcons();
          
          if (j && j.ok) {
            statusEl.innerHTML = '<span class="flex items-center gap-2 text-emerald-600"><i data-lucide="check-circle" class="w-4 h-4"></i> ' + (j.dry_run ? 'Preview Complete' : 'Changes Applied') + ' • ' + (j.inserted||0) + ' records processed</span>';
            
            var rows = j.updated || [];
            resEl.classList.remove('hidden');
            
            var html = '<table class="w-full text-sm text-left"><thead class="text-xs text-slate-500 uppercase bg-slate-100 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700"><tr><th class="px-4 py-2">Route</th><th class="px-4 py-2 text-right">Cap</th><th class="px-4 py-2 text-right">Confidence</th><th class="px-4 py-2">Reason</th></tr></thead><tbody class="divide-y divide-slate-100 dark:divide-slate-700">';
            
            if (rows.length === 0) {
                html += '<tr><td colspan="4" class="px-4 py-4 text-center text-slate-500 italic">No updates required based on current parameters.</td></tr>';
            } else {
                for (var i=0; i<rows.length; i++) {
                  var r = rows[i];
                  var rs = r.reason||'';
                  if (rs.length>80) rs = rs.substring(0,80)+'…';
                  html += '<tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors"><td class="px-4 py-2 font-medium">'+(r.route_id||'')+'</td><td class="px-4 py-2 text-right font-mono text-slate-700 dark:text-slate-300">'+(r.cap||0)+'</td><td class="px-4 py-2 text-right text-slate-600">'+(r.confidence!==undefined ? Number(r.confidence).toFixed(2) : '—')+'</td><td class="px-4 py-2 text-slate-500 text-xs">'+rs+'</td></tr>';
                }
            }
            html += '</tbody></table>';
            resEl.innerHTML = html;
          } else {
             statusEl.innerHTML = '<span class="flex items-center gap-2 text-rose-600"><i data-lucide="alert-circle" class="w-4 h-4"></i> Error: ' + (j.error || 'Unknown error') + '</span>';
          }
          if(window.lucide) window.lucide.createIcons();
        }).catch(function(e){
            btn.disabled = false;
            btn.innerHTML = originalBtnContent;
            if(window.lucide) window.lucide.createIcons();
            statusEl.innerHTML = '<span class="flex items-center gap-2 text-rose-600"><i data-lucide="alert-circle" class="w-4 h-4"></i> Network/Server Error</span>';
            console.error(e);
        });
      });
    })();
            }
            if (rows.length===0) html += '<tr><td colspan="4" class="px-3 py-2 text-center text-slate-500">No routes in window.</td></tr>';
            html += '</tbody></table>';
            resEl.innerHTML = html;
          } else {
            statusEl.textContent = 'Failed';
            resEl.textContent = '';
          }
        }).catch(function(){ statusEl.textContent = 'Error'; resEl.textContent = ''; });
      });
    })();
    if (window.lucide) window.lucide.createIcons();
  </script>
  <!-- Global Filters (Modernized) -->
  <div class="mb-6 bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm p-4">
    <form class="flex flex-col md:flex-row gap-4 items-end" method="GET">
      <input type="hidden" name="page" value="dashboard">
      <div class="flex-1 grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Period</label>
          <div class="relative">
            <i data-lucide="calendar" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
            <select name="period" class="w-full pl-9 pr-3 py-2 text-sm border-slate-200 rounded-lg focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all appearance-none bg-white dark:bg-slate-900 dark:border-slate-700">
              <option value="7d" <?php echo $period==='7d'?'selected':''; ?>>Last 7 days</option>
              <option value="30d" <?php echo $period==='30d'?'selected':''; ?>>Last 30 days</option>
              <option value="90d" <?php echo $period==='90d'?'selected':''; ?>>Last 90 days</option>
              <option value="year" <?php echo $period==='year'?'selected':''; ?>>Year to date</option>
              <option value="all" <?php echo $period==='all'?'selected':''; ?>>All time</option>
            </select>
          </div>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Ticket Status</label>
          <div class="relative">
            <i data-lucide="tag" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
            <select name="status" class="w-full pl-9 pr-3 py-2 text-sm border-slate-200 rounded-lg focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all appearance-none bg-white dark:bg-slate-900 dark:border-slate-700">
              <option value="" <?php echo $status_f===''?'selected':''; ?>>Any Status</option>
              <option value="Pending" <?php echo $status_f==='Pending'?'selected':''; ?>>Pending</option>
              <option value="Validated" <?php echo $status_f==='Validated'?'selected':''; ?>>Validated</option>
              <option value="Settled" <?php echo $status_f==='Settled'?'selected':''; ?>>Settled</option>
              <option value="Escalated" <?php echo $status_f==='Escalated'?'selected':''; ?>>Escalated</option>
            </select>
          </div>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Officer</label>
          <div class="relative">
            <i data-lucide="user" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
            <select name="officer_id" class="w-full pl-9 pr-3 py-2 text-sm border-slate-200 rounded-lg focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all appearance-none bg-white dark:bg-slate-900 dark:border-slate-700">
              <option value="0" <?php echo $officer_id===0?'selected':''; ?>>All Officers</option>
              <?php foreach ($officers as $o): ?>
                <option value="<?php echo (int)$o['officer_id']; ?>" <?php echo $officer_id===(int)$o['officer_id']?'selected':''; ?>><?php echo htmlspecialchars($o['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
      
      <div class="flex gap-2 shrink-0">
        <button class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg shadow-sm hover:shadow-md transition-all flex items-center gap-2">
          <i data-lucide="filter" class="w-4 h-4"></i>
          Apply
        </button>
        <?php $ep = 'period='.$period.'&status='.$status_f.'&officer_id='.$officer_id; ?>
        <div class="flex rounded-lg shadow-sm">
            <a href="/tmm/admin/api/tickets/export_csv.php?<?php echo $ep; ?>" class="px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 text-sm font-medium rounded-l-lg transition-colors flex items-center gap-1.5" title="Export CSV">
                <i data-lucide="file-spreadsheet" class="w-4 h-4 text-emerald-600"></i>
                <span class="hidden sm:inline">CSV</span>
            </a>
            <a href="/tmm/admin/api/tickets/export_pdf.php?<?php echo $ep; ?>" target="_blank" class="px-3 py-2 bg-white dark:bg-slate-800 border-y border-r border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 text-sm font-medium rounded-r-lg transition-colors flex items-center gap-1.5" title="Export PDF">
                <i data-lucide="file-text" class="w-4 h-4 text-rose-600"></i>
                <span class="hidden sm:inline">PDF</span>
            </a>
        </div>
      </div>
    </form>
    <div class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-700 flex justify-between items-center">
        <div class="text-xs text-slate-500">
            Showing data for <span class="font-semibold text-slate-700 dark:text-slate-300"><?php echo $period === 'all' ? 'All time' : 'Last ' . str_replace(['7d','30d','90d','year'], ['7 days','30 days','90 days','Year'], $period); ?></span>
        </div>
        <div class="text-xs text-slate-500">
            Filtered count: <span class="font-semibold text-indigo-600 dark:text-indigo-400"><?php echo number_format($tickets_filtered); ?></span> tickets
        </div>
    </div>
  </div>
  <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <!-- Vehicles -->
    <div class="p-5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm hover:shadow-md transition-shadow relative overflow-hidden group">
      <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
        <i data-lucide="car" class="w-16 h-16 text-blue-600 dark:text-blue-400"></i>
      </div>
      <div class="flex items-center gap-2 mb-4 relative z-10">
        <div class="p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg text-blue-600 dark:text-blue-400">
          <i data-lucide="car" class="w-5 h-5"></i>
        </div>
        <h3 class="font-semibold text-slate-700 dark:text-slate-200">Vehicles</h3>
      </div>
      <div class="grid grid-cols-3 gap-2 text-center relative z-10">
        <div class="p-2 rounded bg-slate-50 dark:bg-slate-700/50">
          <div class="text-xl font-bold text-slate-800 dark:text-slate-100"><?php echo $vehicles_total; ?></div>
          <div class="text-[10px] uppercase font-medium text-slate-500">Total</div>
        </div>
        <div class="p-2 rounded bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-100 dark:border-emerald-800/30">
          <div class="text-xl font-bold text-emerald-600 dark:text-emerald-400"><?php echo $vehicles_active; ?></div>
          <div class="text-[10px] uppercase font-medium text-emerald-600/80 dark:text-emerald-400/80">Active</div>
        </div>
        <div class="p-2 rounded bg-red-50 dark:bg-red-900/20 border border-red-100 dark:border-red-800/30">
          <div class="text-xl font-bold text-red-600 dark:text-red-400"><?php echo $vehicles_suspended; ?></div>
          <div class="text-[10px] uppercase font-medium text-red-600/80 dark:text-red-400/80">Susp.</div>
        </div>
      </div>
    </div>

    <!-- Tickets -->
    <div class="p-5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm hover:shadow-md transition-shadow relative overflow-hidden group">
      <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
        <i data-lucide="ticket" class="w-16 h-16 text-amber-600 dark:text-amber-400"></i>
      </div>
      <div class="flex items-center gap-2 mb-4 relative z-10">
        <div class="p-2 bg-amber-100 dark:bg-amber-900/30 rounded-lg text-amber-600 dark:text-amber-400">
          <i data-lucide="ticket" class="w-5 h-5"></i>
        </div>
        <h3 class="font-semibold text-slate-700 dark:text-slate-200">Tickets</h3>
      </div>
      <div class="grid grid-cols-2 gap-2 text-center relative z-10 mb-2">
        <div class="p-2 rounded bg-slate-50 dark:bg-slate-700/50">
          <div class="text-lg font-bold text-slate-700 dark:text-slate-200"><?php echo $tickets_pending; ?></div>
          <div class="text-[10px] uppercase font-medium text-slate-500">Pending</div>
        </div>
        <div class="p-2 rounded bg-slate-50 dark:bg-slate-700/50">
          <div class="text-lg font-bold text-slate-700 dark:text-slate-200"><?php echo $tickets_settled; ?></div>
          <div class="text-[10px] uppercase font-medium text-slate-500">Settled</div>
        </div>
      </div>
      <div class="flex justify-between items-center text-xs px-2 relative z-10">
        <span class="text-slate-500">Unresolved</span>
        <span class="font-bold text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/30 px-2 py-0.5 rounded-full"><?php echo $tickets_unresolved; ?></span>
      </div>
    </div>

    <!-- Permits -->
    <div class="p-5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm hover:shadow-md transition-shadow relative overflow-hidden group">
      <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
        <i data-lucide="file-check" class="w-16 h-16 text-violet-600 dark:text-violet-400"></i>
      </div>
      <div class="flex items-center gap-2 mb-4 relative z-10">
        <div class="p-2 bg-violet-100 dark:bg-violet-900/30 rounded-lg text-violet-600 dark:text-violet-400">
          <i data-lucide="file-check" class="w-5 h-5"></i>
        </div>
        <h3 class="font-semibold text-slate-700 dark:text-slate-200">Permits</h3>
      </div>
      <div class="grid grid-cols-2 gap-2 text-center relative z-10">
        <div class="p-2 rounded bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-100 dark:border-emerald-800/30">
          <div class="text-lg font-bold text-emerald-600 dark:text-emerald-400"><?php echo $permits_active; ?></div>
          <div class="text-[10px] uppercase font-medium text-emerald-600/80 dark:text-emerald-400/80">Active</div>
        </div>
        <div class="p-2 rounded bg-amber-50 dark:bg-amber-900/20 border border-amber-100 dark:border-amber-800/30">
          <div class="text-lg font-bold text-amber-600 dark:text-amber-400"><?php echo $permits_pending_payment; ?></div>
          <div class="text-[10px] uppercase font-medium text-amber-600/80 dark:text-amber-400/80">Pending</div>
        </div>
      </div>
      <div class="mt-2 text-center text-xs text-slate-400 relative z-10">
        Logs Today: <span class="font-medium text-slate-600 dark:text-slate-300"><?php echo $logs_today; ?></span>
      </div>
    </div>

    <!-- Compliance -->
    <div class="p-5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm hover:shadow-md transition-shadow relative overflow-hidden group">
      <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
        <i data-lucide="alert-triangle" class="w-16 h-16 text-rose-600 dark:text-rose-400"></i>
      </div>
      <div class="flex items-center gap-2 mb-4 relative z-10">
        <div class="p-2 bg-rose-100 dark:bg-rose-900/30 rounded-lg text-rose-600 dark:text-rose-400">
          <i data-lucide="alert-triangle" class="w-5 h-5"></i>
        </div>
        <h3 class="font-semibold text-slate-700 dark:text-slate-200">Compliance</h3>
      </div>
      <div class="text-center relative z-10 mb-2">
        <div class="text-2xl font-bold text-slate-800 dark:text-slate-100">₱<?php echo number_format($fees_total, 0); ?></div>
        <div class="text-xs text-slate-500 uppercase">Total Fees Collected</div>
      </div>
      <div class="grid grid-cols-2 gap-2 text-center relative z-10">
        <div class="p-1.5 rounded bg-slate-50 dark:bg-slate-700/50">
          <div class="font-bold text-slate-700 dark:text-slate-200"><?php echo $violations_total; ?></div>
          <div class="text-[10px] text-slate-500">Incidents</div>
        </div>
        <div class="p-1.5 rounded bg-rose-50 dark:bg-rose-900/20 border border-rose-100 dark:border-rose-800/30">
          <div class="font-bold text-rose-600 dark:text-rose-400"><?php echo $compliance_suspended; ?></div>
          <div class="text-[10px] text-rose-600/80 dark:text-rose-400/80">Suspended</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Activity Charts -->
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <div class="p-6 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="flex items-center gap-2 mb-4">
        <i data-lucide="bar-chart" class="w-5 h-5 text-blue-500"></i>
        <div>
          <h3 class="font-semibold text-slate-800 dark:text-slate-100">Ticket Activity</h3>
          <div class="text-xs text-slate-500">Last 14 Days • <?php echo $status_f ?: 'All Status'; ?></div>
        </div>
      </div>
      <div class="flex items-end gap-1.5 h-32 pt-4">
        <?php foreach ($ticketsDaily as $d => $c): $h = $ticketsMax>0 ? max(4, (int)round(($c/$ticketsMax)*100)) : 4; ?>
          <div class="relative flex-1 group">
            <div class="w-full bg-blue-500/80 hover:bg-blue-600 transition-colors rounded-t-md" style="height: <?php echo $h; ?>px;"></div>
            <div class="absolute -top-8 left-1/2 -translate-x-1/2 bg-slate-800 text-white text-[10px] px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-10">
              <?php echo date('M d', strtotime($d)); ?>: <?php echo $c; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    
    <div class="p-6 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="flex items-center gap-2 mb-4">
        <i data-lucide="activity" class="w-5 h-5 text-emerald-500"></i>
        <div>
          <h3 class="font-semibold text-slate-800 dark:text-slate-100">Terminal Activity</h3>
          <div class="text-xs text-slate-500">Daily Logs • Last 14 Days</div>
        </div>
      </div>
      <div class="flex items-end gap-1.5 h-32 pt-4">
        <?php foreach ($logsDaily as $d => $c): $h = $logsMax>0 ? max(4, (int)round(($c/$logsMax)*100)) : 4; ?>
          <div class="relative flex-1 group">
            <div class="w-full bg-emerald-500/80 hover:bg-emerald-600 transition-colors rounded-t-md" style="height: <?php echo $h; ?>px;"></div>
            <div class="absolute -top-8 left-1/2 -translate-x-1/2 bg-slate-800 text-white text-[10px] px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-10">
              <?php echo date('M d', strtotime($d)); ?>: <?php echo $c; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Recent Data Tables -->
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
      <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center bg-slate-50/50 dark:bg-slate-800/50">
        <div class="flex items-center gap-2">
          <div class="p-1.5 rounded-lg bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">
            <i data-lucide="clipboard-list" class="w-4 h-4"></i>
          </div>
          <h3 class="font-semibold text-slate-800 dark:text-slate-100">Recent Logs</h3>
        </div>
        <a href="?page=module5/submodule1" class="text-xs font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400">View All &rarr;</a>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
          <thead class="text-xs text-slate-500 uppercase bg-slate-50/80 dark:bg-slate-800/80 border-b border-slate-100 dark:border-slate-700">
            <tr>
              <th class="px-6 py-3 font-medium">Plate</th>
              <th class="px-6 py-3 font-medium">Type</th>
              <th class="px-6 py-3 font-medium text-right">Time</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
            <?php if ($recent_logs && $recent_logs->num_rows > 0): while($r = $recent_logs->fetch_assoc()): ?>
              <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                <td class="px-6 py-3 font-medium">
                  <div class="text-slate-700 dark:text-slate-200"><?php echo htmlspecialchars($r['vehicle_plate']); ?></div>
                  <div class="text-xs text-slate-400 font-normal"><?php echo htmlspecialchars($r['operator_name'] ?? 'Unknown'); ?></div>
                </td>
                <td class="px-6 py-3">
                  <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300">
                    <?php echo htmlspecialchars($r['activity_type'] ?? '-'); ?>
                  </span>
                </td>
                <td class="px-6 py-3 text-right text-slate-500 text-xs">
                  <?php echo date('H:i', strtotime($r['time_in'])); ?>
                </td>
              </tr>
            <?php endwhile; else: ?>
              <tr><td colspan="3" class="px-6 py-8 text-center text-slate-500 italic">No logs recorded today.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
      <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center bg-slate-50/50 dark:bg-slate-800/50">
        <div class="flex items-center gap-2">
          <div class="p-1.5 rounded-lg bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400">
            <i data-lucide="tag" class="w-4 h-4"></i>
          </div>
          <h3 class="font-semibold text-slate-800 dark:text-slate-100">Latest Tickets</h3>
        </div>
        <a href="?page=module3/submodule3" class="text-xs font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400">View All &rarr;</a>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
          <thead class="text-xs text-slate-500 uppercase bg-slate-50/80 dark:bg-slate-800/80 border-b border-slate-100 dark:border-slate-700">
            <tr>
              <th class="px-6 py-3 font-medium">Ticket</th>
              <th class="px-6 py-3 font-medium">Status</th>
              <th class="px-6 py-3 font-medium text-right">Fine</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
            <?php if ($recent_tickets && $recent_tickets->num_rows > 0): while($t = $recent_tickets->fetch_assoc()): 
              $st = $t['status'];
              $stColor = 'bg-slate-100 text-slate-800';
              if ($st==='Pending') $stColor = 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400';
              if ($st==='Validated') $stColor = 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400';
              if ($st==='Settled') $stColor = 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400';
              if ($st==='Escalated') $stColor = 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
            ?>
              <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                <td class="px-6 py-3 font-medium">
                  <div class="text-slate-700 dark:text-slate-200"><?php echo htmlspecialchars($t['ticket_number']); ?></div>
                  <div class="text-xs text-slate-400 font-normal"><?php echo htmlspecialchars($t['vehicle_plate']); ?></div>
                </td>
                <td class="px-6 py-3">
                  <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?php echo $stColor; ?>">
                    <?php echo htmlspecialchars($st); ?>
                  </span>
                </td>
                <td class="px-6 py-3 text-right font-medium text-slate-700 dark:text-slate-300">
                  ₱<?php echo number_format((float)$t['fine_amount'], 0); ?>
                </td>
              </tr>
            <?php endwhile; else: ?>
              <tr><td colspan="3" class="px-6 py-8 text-center text-slate-500 italic">No recent tickets.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Quick Links -->
  <div class="mb-6">
    <h2 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-3">Quick Access</h2>
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-3">
      <?php
        $links = [
          ['PUV Registry', 'module1/submodule1', 'database'],
          ['Franchise', 'module2/submodule1', 'file-check-2'],
          ['Inspections', 'module4/submodule1', 'clipboard-check'],
          ['Terminal Ops', 'module5/submodule1', 'parking-circle'],
          ['Analytics', 'module5/submodule3', 'bar-chart-2'],
          ['Ticketing', 'module3/submodule1', 'ticket'],
          ['Reports', 'module3/submodule3', 'file-text'],
          ['Operators', 'module1/submodule2', 'users']
        ];
        foreach ($links as $l):
      ?>
      <a href="?page=<?php echo $l[1]; ?>" class="flex flex-col items-center justify-center p-3 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl hover:border-indigo-500 hover:shadow-md transition-all group">
        <i data-lucide="<?php echo $l[2]; ?>" class="w-5 h-5 mb-2 text-slate-400 group-hover:text-indigo-500 transition-colors"></i>
        <span class="text-xs font-medium text-slate-600 dark:text-slate-300 group-hover:text-slate-900 dark:group-hover:text-white"><?php echo $l[0]; ?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
