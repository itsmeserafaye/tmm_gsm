<?php
/**
 * Analytics Helper Functions
 * Shared logic for Forecasting and Dynamic Caps
 */

function run_forecast_job($db, $terminalId, $routeId, $horizonMin = 240, $granMin = 60) {
    // Log Job Start
    $params = json_encode([
        'terminal_id' => $terminalId,
        'route_id' => $routeId,
        'horizon_min' => $horizonMin,
        'granularity_min' => $granMin
    ], JSON_UNESCAPED_SLASHES);
    
    $stmtJob = $db->prepare("INSERT INTO demand_forecast_jobs(job_type,status,params_json,started_at) VALUES ('forecast','running',?,NOW())");
    $stmtJob->bind_param('s', $params);
    $stmtJob->execute();
    $jobId = (int)$db->insert_id;

    $forecasts = null;
    $modelVersion = 'baseline_v1';
    $serviceOk = false;
    $steps = max(1, (int)floor($horizonMin / $granMin));

    // 1. Try Python Service
    try {
        $payload = json_encode([
            'terminal_id' => $terminalId,
            'route_id' => $routeId,
            'horizon_min' => $horizonMin,
            'granularity_min' => $granMin
        ]);
        // Update port to 8000 (Python Forecast Service)
        $ch = curl_init('http://127.0.0.1:8000/forecast');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // Increase timeout for production stability
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($res !== false && $httpCode >= 200 && $httpCode < 300) {
            $data = json_decode($res, true);
            if (is_array($data) && ($data['ok'] ?? false) && isset($data['forecasts']) && is_array($data['forecasts'])) {
                $forecasts = [];
                foreach ($data['forecasts'] as $f) {
                    $forecasts[] = [
                        'ts' => $f['ts'] ?? null,
                        'forecast_trips' => isset($f['forecast_trips']) ? (double)$f['forecast_trips'] : 0.0,
                        'lower_ci' => isset($f['lower_ci']) ? (double)$f['lower_ci'] : null,
                        'upper_ci' => isset($f['upper_ci']) ? (double)$f['upper_ci'] : null
                    ];
                }
                $modelVersion = 'AI Model (v1)';
                $serviceOk = true;
            }
        }
    } catch (\Throwable $e) {}

    // 2. Fallback to PHP Logic if Service Failed
    if ($forecasts === null) {
        $now = new DateTime('now');
        $base = clone $now;
        $base->setTime((int)$base->format('H'), (int)floor(((int)$base->format('i')) / $granMin) * $granMin, 0);
        $end = new DateTime('now');
        $start = (clone $end)->modify('-30 days');
        
        $stmtS = $db->prepare("
            SELECT DATE_FORMAT(l.time_in, '%Y-%m-%d %H:00:00') AS ts_hour, COUNT(*) AS trips
            FROM terminal_logs l
            JOIN vehicles v ON v.plate_number = l.vehicle_plate
            WHERE l.activity_type='Dispatch'
              AND v.route_id=?
              AND l.time_in BETWEEN ? AND ?
            GROUP BY ts_hour
            ORDER BY ts_hour
        ");
        $sStart = $start->format('Y-m-d H:i:s');
        $sEnd = $end->format('Y-m-d H:i:s');
        $stmtS->bind_param('sss', $routeId, $sStart, $sEnd);
        $stmtS->execute();
        $resS = $stmtS->get_result();
        $series = [];
        while ($r = $resS->fetch_assoc()) {
            $series[$r['ts_hour']] = (double)($r['trips'] ?? 0);
        }

        // Calculate 24h Moving Average (Trend)
        $vals = array_values($series);
        $ma = 0.0;
        if (!empty($vals)) {
            $tail = array_slice($vals, max(0, count($vals) - 24));
            $ma = array_sum($tail) / max(1, count($tail));
        }

        // Pre-fetch exogenous data
        $hStart = clone $base;
        $hEnd = (clone $base)->modify('+' . $horizonMin . ' minutes');
        $wxRows = [];
        $tfRows = [];
        $evRows = [];

        $stmtW = $db->prepare("SELECT ts, temp_c, humidity, rainfall_mm, wind_kph FROM weather_data WHERE terminal_id=? AND ts BETWEEN ? AND ?");
        if ($stmtW) {
            $wStart = $hStart->format('Y-m-d H:i:s');
            $wEnd = $hEnd->format('Y-m-d H:i:s');
            $stmtW->bind_param('iss', $terminalId, $wStart, $wEnd);
            $stmtW->execute();
            $resW = $stmtW->get_result();
            while ($row = $resW->fetch_assoc()) { $wxRows[$row['ts']] = $row; }
        }

        $stmtT = $db->prepare("SELECT ts, avg_speed_kph, congestion_index, travel_time_min FROM traffic_data WHERE terminal_id=? AND (route_id IS NULL OR route_id=?) AND ts BETWEEN ? AND ?");
        if ($stmtT) {
            $tStart = $hStart->format('Y-m-d H:i:s');
            $tEnd = $hEnd->format('Y-m-d H:i:s');
            $stmtT->bind_param('isss', $terminalId, $routeId, $tStart, $tEnd);
            $stmtT->execute();
            $resT = $stmtT->get_result();
            while ($row = $resT->fetch_assoc()) { $tfRows[$row['ts']] = $row; }
        }

        $stmtE = $db->prepare("SELECT ts_start, ts_end, expected_attendance, priority FROM event_data WHERE terminal_id=? AND ts_start <= ? AND (ts_end IS NULL OR ts_end >= ?)");
        if ($stmtE) {
            $eStart = $hStart->format('Y-m-d H:i:s');
            $eEnd = $hEnd->format('Y-m-d H:i:s');
            $stmtE->bind_param('iss', $terminalId, $eEnd, $eStart);
            $stmtE->execute();
            $resE = $stmtE->get_result();
            while ($row = $resE->fetch_assoc()) { $evRows[] = $row; }
        }

        $forecasts = [];
        $modelVersion = 'Baseline (V2 - Tuned)';

        for ($i = 0; $i < $steps; $i++) {
            $tsObj = (clone $base)->modify('+' . ($granMin * $i) . ' minutes');
            $tsKey = $tsObj->format('Y-m-d H:00:00');
            
            // Feature 1: Last Week Same Hour (Seasonality)
            $lwObj = (clone $tsObj)->modify('-7 days');
            $lwKey = $lwObj->format('Y-m-d H:00:00');
            $lwVal = array_key_exists($lwKey, $series) ? (double)$series[$lwKey] : null;
            
            // Feature 2: Yesterday Same Hour (Short-term Pattern)
            $yestObj = (clone $tsObj)->modify('-1 day');
            $yestKey = $yestObj->format('Y-m-d H:00:00');
            $yestVal = array_key_exists($yestKey, $series) ? (double)$series[$yestKey] : null;

            // Ensemble Logic
            $y = $ma; // Default to trend
            if ($lwVal !== null && $yestVal !== null) {
                $y = 0.5 * $lwVal + 0.3 * $yestVal + 0.2 * $ma;
            } elseif ($lwVal !== null) {
                $y = 0.7 * $lwVal + 0.3 * $ma;
            } elseif ($yestVal !== null) {
                $y = 0.6 * $yestVal + 0.4 * $ma;
            }
            
            $y = max(0.0, (double)$y);
            
            // Apply Exogenous Factors
            $wx = isset($wxRows[$tsKey]) ? $wxRows[$tsKey] : null;
            $tf = isset($tfRows[$tsKey]) ? $tfRows[$tsKey] : null;
            
            $rain = $wx && isset($wx['rainfall_mm']) ? (double)$wx['rainfall_mm'] : 0.0;
            $cong = $tf && isset($tf['congestion_index']) ? (double)$tf['congestion_index'] : 0.0;
            
            $rainFactor = 1.0 - min(0.4, max(0.0, $rain) / 50.0);
            $congFactor = 1.0 + min(0.3, max(0.0, $cong));
            
            $eventFactor = 1.0;
            $evActive = false;
            if (!empty($evRows)) {
                $tsStr = $tsObj->format('Y-m-d H:i:s');
                foreach ($evRows as $ev) {
                    $s = isset($ev['ts_start']) ? strtotime($ev['ts_start']) : null;
                    $e = isset($ev['ts_end']) ? strtotime($ev['ts_end']) : null;
                    $t = strtotime($tsStr);
                    if ($s !== null && $t !== false && $t >= $s && ($e === null || $t <= $e)) {
                        $evActive = true;
                        $att = isset($ev['expected_attendance']) ? (double)$ev['expected_attendance'] : 0.0;
                        $prio = isset($ev['priority']) ? (double)$ev['priority'] : 0.0;
                        $strength = min(0.5, ($att / 5000.0) + ($prio / 20.0));
                        $eventFactor = max($eventFactor, 1.0 + $strength);
                    }
                }
            }
            
            $y = $y * $rainFactor * $congFactor * $eventFactor;
            
            $unc = 0.15;
            if ($rain >= 5.0) $unc += 0.1;
            if ($evActive) $unc += 0.1;
            if ($cong >= 0.7) $unc += 0.1;
            if ($lwVal === null) $unc += 0.2;
            
            $lower = max(0.0, $y * (1.0 - $unc));
            $upper = $y * (1.0 + $unc);
            
            $forecasts[] = [
                'ts' => $tsObj->format('c'),
                'forecast_trips' => $y,
                'lower_ci' => $lower,
                'upper_ci' => $upper
            ];
        }
    }

    // Insert Forecasts
    $inserted = 0;
    $stmtI = $db->prepare("INSERT INTO demand_forecasts(terminal_id, route_id, ts, horizon_min, forecast_trips, lower_ci, upper_ci, model_version) VALUES (?,?,?,?,?,?,?,?)");
    if ($forecasts) {
        foreach ($forecasts as $f) {
            $ts = isset($f['ts']) ? date('Y-m-d H:i:s', strtotime($f['ts'])) : date('Y-m-d H:i:s');
            $y = isset($f['forecast_trips']) ? (double)$f['forecast_trips'] : 0.0;
            $l = isset($f['lower_ci']) ? (double)$f['lower_ci'] : null;
            $u = isset($f['upper_ci']) ? (double)$f['upper_ci'] : null;
            $stmtI->bind_param('issiddds', $terminalId, $routeId, $ts, $granMin, $y, $l, $u, $modelVersion);
            if ($stmtI->execute()) { $inserted++; }
        }
    }

    // Build Alerts
    $alerts = [];
    $nowTs = time();
    if ($forecasts) {
        foreach ($forecasts as $f) {
            $ts = isset($f['ts']) ? strtotime($f['ts']) : null;
            if ($ts !== null && $ts >= $nowTs && $ts <= ($nowTs + 120 * 60)) {
                $alerts[] = ['ts' => date('Y-m-d H:i:s', $ts), 'pred_trips' => (double)($f['forecast_trips'] ?? 0)];
            }
        }
    }

    // Update Job
    $msg = 'inserted ' . $inserted . ' • model=' . $modelVersion . ($serviceOk ? ' • service' : ' • fallback');
    $stmtUpd = $db->prepare("UPDATE demand_forecast_jobs SET status='succeeded', finished_at=NOW(), message=? WHERE id=?");
    $stmtUpd->bind_param('si', $msg, $jobId);
    $stmtUpd->execute();

    return [
        'ok' => true,
        'inserted' => $inserted,
        'model_version' => $modelVersion,
        'granularity_min' => $granMin,
        'forecasts' => $forecasts,
        'alerts' => $alerts
    ];
}

function run_compute_caps_job($db, $routeFilter = '', $horizonMin = 240, $theta = 0.7, $minConfidence = 0.6, $dryRun = false) {
    if ($horizonMin <= 0) $horizonMin = 240;
    if ($theta <= 0 || $theta >= 1) $theta = 0.7;
    if ($minConfidence < 0 || $minConfidence > 1) $minConfidence = 0.6;

    $startTs = date('Y-m-d H:i:s');
    $endTs = date('Y-m-d H:i:s', time() + ($horizonMin * 60));
    
    // Fetch Forecasts
    $sqlF = "SELECT route_id, AVG(forecast_trips) AS f_mean, AVG(lower_ci) AS l_mean, AVG(upper_ci) AS u_mean, COUNT(*) AS n
             FROM demand_forecasts
             WHERE ts BETWEEN ? AND ?";
    if ($routeFilter !== '') { $sqlF .= " AND route_id=?"; }
    $sqlF .= " GROUP BY route_id";
    $stmtF = $db->prepare($sqlF);
    if ($routeFilter !== '') { $stmtF->bind_param('sss', $startTs, $endTs, $routeFilter); } else { $stmtF->bind_param('ss', $startTs, $endTs); }
    $stmtF->execute();
    $resF = $stmtF->get_result();
    $forecastByRoute = [];
    while ($r = $resF->fetch_assoc()) {
        $forecastByRoute[$r['route_id']] = [
            'f_mean' => (double)($r['f_mean'] ?? 0),
            'l_mean' => $r['l_mean'] !== null ? (double)$r['l_mean'] : null,
            'u_mean' => $r['u_mean'] !== null ? (double)$r['u_mean'] : null,
            'n' => (int)($r['n'] ?? 0)
        ];
    }

    if (empty($forecastByRoute)) {
        return ['ok'=>false,'error'=>'no_forecasts_in_horizon'];
    }

    // Fetch Assignments
    $sqlA = "SELECT route_id, COUNT(*) AS assigned FROM terminal_assignments";
    if ($routeFilter !== '') { $sqlA .= " WHERE route_id=?"; }
    $sqlA .= " GROUP BY route_id";
    $stmtA = $routeFilter !== '' ? $db->prepare($sqlA) : $db->prepare($sqlA);
    if ($routeFilter !== '') { $stmtA->bind_param('s', $routeFilter); }
    $stmtA->execute();
    $resA = $stmtA->get_result();
    $assignedByRoute = [];
    while ($a = $resA->fetch_assoc()) {
        $assignedByRoute[$a['route_id']] = (int)($a['assigned'] ?? 0);
    }

    $insertCount = 0;
    $updates = [];
    
    foreach ($forecastByRoute as $rid => $finfo) {
        $assigned = $assignedByRoute[$rid] ?? 0;
        $fMean = max(0.0, (double)$finfo['f_mean']);
        $lMean = $finfo['l_mean'];
        $uMean = $finfo['u_mean'];
        $nWin = (int)$finfo['n'];

        $conf = 0.6;
        if ($lMean !== null && $uMean !== null) {
            $width = max(0.0, (double)$uMean - (double)$lMean);
            $scale = max(1.0, $fMean * 2.0);
            $conf = max(0.0, min(1.0, 1.0 - ($width / $scale)));
        }

        $servedCapacity = max(1.0, (double)$assigned);
        $dcr = $fMean / $servedCapacity;

        $capNew = 0;
        $reason = '';
        if ($dcr < $theta && $conf >= $minConfidence) {
            $capNew = (int)ceil($fMean / $theta);
            $reason = "oversupply DCR=" . round($dcr, 2) . " • mean=" . round($fMean, 2) . " • assigned=" . $assigned . " • θ=" . $theta;
        } else {
            $capNew = -1; // lift cap (uncapped)
            $reason = "normal supply DCR=" . round($dcr, 2) . " • mean=" . round($fMean, 2) . " • assigned=" . $assigned;
        }

        $updates[] = [
            'route_id' => $rid,
            'assigned' => $assigned,
            'forecast_mean' => round($fMean, 2),
            'dcr' => round($dcr, 2),
            'cap' => $capNew,
            'confidence' => round($conf, 2),
            'windows' => $nWin,
            'reason' => $reason
        ];

        if (!$dryRun) {
            $stmtI = $db->prepare("INSERT INTO route_cap_schedule(route_id, ts, cap, reason, confidence) VALUES (?, NOW(), ?, ?, ?)");
            $stmtI->bind_param('sisd', $rid, $capNew, $reason, $conf);
            if ($stmtI->execute()) { $insertCount++; }
        }
    }

    return [
        'ok' => true,
        'horizon_min' => $horizonMin,
        'theta' => $theta,
        'min_confidence' => $minConfidence,
        'dry_run' => $dryRun,
        'updated' => $updates,
        'inserted' => $insertCount
    ];
}
?>