<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/external_data.php';
$db = db();
header('Content-Type: application/json');

$areaType = trim((string)($_GET['area_type'] ?? 'terminal'));
if (!in_array($areaType, ['terminal', 'route'], true)) $areaType = 'terminal';

$hours = (int)($_GET['hours'] ?? 24);
if ($hours < 6) $hours = 6;
if ($hours > 72) $hours = 72;

$demandUrl = __DIR__ . '/demand_forecast.php';

ob_start();
$_GET['area_type'] = $areaType;
$_GET['hours'] = (string)$hours;
include $demandUrl;
$raw = ob_get_clean();
$forecast = json_decode((string)$raw, true);

if (!is_array($forecast) || !($forecast['ok'] ?? false)) {
  echo json_encode(['ok' => false, 'error' => 'forecast_unavailable']);
  exit;
}

$spikes = $forecast['spikes'] ?? [];
if (!is_array($spikes)) $spikes = [];

$traffic = is_array($forecast['traffic'] ?? null) ? $forecast['traffic'] : [];
$trafficCong = isset($traffic['congestion']) ? $traffic['congestion'] : null;
$trafficIncidents = isset($traffic['incidents_count']) ? $traffic['incidents_count'] : null;
$trafficMaxDelay = isset($traffic['max_delay']) ? $traffic['max_delay'] : null;

$supplyByTerminalName = [];
if ($areaType === 'terminal') {
  $res = $db->query("SELECT terminal_name, COUNT(*) AS c FROM terminal_assignments WHERE status IS NULL OR status='Authorized' GROUP BY terminal_name");
  while ($res && ($r = $res->fetch_assoc())) {
    $supplyByTerminalName[(string)$r['terminal_name']] = (int)($r['c'] ?? 0);
  }
}

$supplyByRoute = [];
if ($areaType === 'route') {
  $res = $db->query("SELECT route_id, COUNT(*) AS c FROM terminal_assignments WHERE status IS NULL OR status='Authorized' GROUP BY route_id");
  while ($res && ($r = $res->fetch_assoc())) {
    $supplyByRoute[(string)$r['route_id']] = (int)($r['c'] ?? 0);
  }
}

function clamp_f(float $v, float $min, float $max): float {
  if ($v < $min) return $min;
  if ($v > $max) return $max;
  return $v;
}

$rainProbThreshold = (float)tmm_setting($db, 'ai_rain_prob_threshold', '60');
$rainProbThreshold = clamp_f($rainProbThreshold, 0, 100);
$targetLoadPerUnit = (float)tmm_setting($db, 'ai_target_load_per_unit', '1.0');
$targetLoadPerUnit = clamp_f($targetLoadPerUnit, 0.1, 100000);
$trafficThreshold = (float)tmm_setting($db, 'ai_traffic_congestion_threshold', '0.25');
$trafficThreshold = clamp_f($trafficThreshold, 0.0, 1.0);

$areasByRef = [];
if (isset($forecast['areas']) && is_array($forecast['areas'])) {
  foreach ($forecast['areas'] as $a) {
    if (!is_array($a)) continue;
    $ref = (string)($a['area_ref'] ?? '');
    if ($ref === '') continue;
    $areasByRef[$ref] = $a;
  }
}

$hotspots = [];
$alerts = [];
foreach ($spikes as $s) {
  if (!is_array($s)) continue;
  $label = (string)($s['area_label'] ?? '');
  $ref = (string)($s['area_ref'] ?? '');
  $pred = (int)($s['predicted_peak'] ?? 0);
  $baseline = (float)($s['baseline'] ?? 0);
  $peakHour = (string)($s['peak_hour'] ?? '');
  $weather = is_array($s['weather'] ?? null) ? $s['weather'] : [];
  $event = is_array($s['event'] ?? null) ? $s['event'] : null;

  $areaMeta = $areasByRef[$ref] ?? null;
  $trendFactor = is_array($areaMeta) ? (float)($areaMeta['trend_factor'] ?? 1.0) : 1.0;
  $capacity = is_array($areaMeta) ? (int)($areaMeta['capacity'] ?? 0) : 0;
  $multAtPeak = 1.0;
  if (is_array($areaMeta) && isset($areaMeta['forecast']) && is_array($areaMeta['forecast'])) {
    foreach ($areaMeta['forecast'] as $p) {
      if (!is_array($p)) continue;
      if ((string)($p['hour_label'] ?? '') === $peakHour) {
        $multAtPeak = (float)($p['multiplier'] ?? 1.0);
        break;
      }
    }
  }

  $supply = null;
  if ($areaType === 'terminal' && $label !== '') {
    $supply = $supplyByTerminalName[$label] ?? null;
  }
  if ($areaType === 'route' && $ref !== '') {
    $supply = $supplyByRoute[$ref] ?? null;
  }

  $routesAtTerminal = [];
  if ($areaType === 'terminal' && $label !== '') {
    $stmtRoutes = $db->prepare("SELECT ta.route_id, COALESCE(r.route_name, ta.route_id) AS route_name, COUNT(*) AS units
      FROM terminal_assignments ta
      LEFT JOIN routes r ON r.route_id = ta.route_id
      WHERE ta.terminal_name = ? AND (ta.status IS NULL OR ta.status = 'Authorized')
      GROUP BY ta.route_id, route_name
      ORDER BY units DESC, route_name ASC");
    if ($stmtRoutes) {
      $stmtRoutes->bind_param('s', $label);
      $stmtRoutes->execute();
      $resRoutes = $stmtRoutes->get_result();
      while ($resRoutes && ($rr = $resRoutes->fetch_assoc())) {
        $routesAtTerminal[] = [
          'route_id' => (string)($rr['route_id'] ?? ''),
          'route_name' => (string)($rr['route_name'] ?? ''),
          'units' => (int)($rr['units'] ?? 0),
        ];
      }
      $stmtRoutes->close();
    }
  }

  $severity = 'medium';
  if ($baseline > 0 && $pred >= (int)ceil($baseline * 1.6)) $severity = 'high';
  if ($baseline > 0 && $pred >= (int)ceil($baseline * 2.0)) $severity = 'critical';

  $baselinePerUnit = null;
  $predPerUnit = null;
  $loadStatus = 'unknown';
  $recommendedExtraUnits = null;
  $recommendedTotalUnits = null;
  $routePlan = [];

  if (is_int($supply) && $supply > 0) {
    $baselinePerUnit = round($baseline / $supply, 3);
    $predPerUnit = round($pred / $supply, 3);
    if (($baselinePerUnit ?? 0) > 0) {
      $loadStatus = ($predPerUnit >= ($baselinePerUnit * 1.3)) ? 'potential_over_demand' : 'normal';
    } else {
      $loadStatus = $predPerUnit > 0 ? 'potential_over_demand' : 'normal';
    }

    $target = $baselinePerUnit && $baselinePerUnit > 0 ? max(0.25, (float)$baselinePerUnit) : $targetLoadPerUnit;
    $requiredUnits = (int)ceil($pred / max(0.01, $target));
    $recommendedTotalUnits = max($supply, $requiredUnits);
    $recommendedExtraUnits = max(0, $recommendedTotalUnits - $supply);
  }

  if ($areaType === 'terminal' && is_int($recommendedExtraUnits) && $recommendedExtraUnits > 0 && $routesAtTerminal) {
    $totalRouteUnits = 0;
    foreach ($routesAtTerminal as $rt) $totalRouteUnits += (int)($rt['units'] ?? 0);
    if ($totalRouteUnits <= 0) $totalRouteUnits = 1;

    $remaining = $recommendedExtraUnits;
    $alloc = [];
    foreach ($routesAtTerminal as $rt) {
      $rid = (string)($rt['route_id'] ?? '');
      $u = (int)($rt['units'] ?? 0);
      $n = (int)floor(($recommendedExtraUnits * $u) / $totalRouteUnits);
      if ($n < 0) $n = 0;
      $alloc[$rid] = $n;
      $remaining -= $n;
    }
    if ($remaining > 0) {
      usort($routesAtTerminal, function ($a, $b) {
        return ((int)($b['units'] ?? 0)) <=> ((int)($a['units'] ?? 0));
      });
      $i = 0;
      $len = count($routesAtTerminal);
      while ($remaining > 0 && $len > 0) {
        $rid = (string)($routesAtTerminal[$i % $len]['route_id'] ?? '');
        $alloc[$rid] = ($alloc[$rid] ?? 0) + 1;
        $remaining--;
        $i++;
      }
    }

    foreach ($routesAtTerminal as $rt) {
      $rid = (string)($rt['route_id'] ?? '');
      $u = (int)($rt['units'] ?? 0);
      $x = (int)($alloc[$rid] ?? 0);
      if ($x <= 0) continue;
      $routePlan[] = [
        'route_id' => $rid,
        'route_name' => (string)($rt['route_name'] ?? $rid),
        'current_units' => $u,
        'suggested_extra_units' => $x,
        'suggested_total_units' => $u + $x,
      ];
    }
  }

  $drivers = [];
  if (is_numeric($weather['precip_prob'] ?? null)) {
    $p = (float)$weather['precip_prob'];
    if ($p >= $rainProbThreshold) $drivers[] = 'Rain probability ' . round($p) . '%';
  }
  if (is_array($event) && !empty($event['title'])) {
    $drivers[] = (string)$event['title'];
  }
  if (abs($trendFactor - 1.0) >= 0.05) {
    $drivers[] = 'Recent trend ' . (round(($trendFactor - 1.0) * 100)) . '%';
  }
  if (abs($multAtPeak - 1.0) >= 0.01) {
    $drivers[] = 'Model multiplier x' . number_format($multAtPeak, 2);
  }

  $hotspots[] = [
    'area_ref' => $ref,
    'area_label' => $label,
    'peak_hour' => $peakHour,
    'predicted_peak' => $pred,
    'baseline' => $baseline,
    'severity' => $severity,
    'capacity' => $capacity,
    'supply_units' => $supply,
    'load_status' => $loadStatus,
    'recommended_extra_units' => $recommendedExtraUnits,
    'recommended_total_units' => $recommendedTotalUnits,
    'route_plan' => $routePlan,
    'drivers' => $drivers,
    'weather' => $weather,
    'event' => $event,
  ];

  $alerts[] = [
    'area_label' => $label,
    'peak_hour' => $peakHour,
    'predicted_peak' => $pred,
    'baseline' => $baseline,
    'severity' => $severity,
    'supply_units' => $supply,
    'baseline_per_unit' => $baselinePerUnit,
    'predicted_per_unit' => $predPerUnit,
    'load_status' => $loadStatus,
    'constraint' => 'Recommendations keep operators within their assigned routes.',
    'weather' => $weather
  ];
}

usort($hotspots, function ($a, $b) {
  $ap = (int)($a['predicted_peak'] ?? 0);
  $bp = (int)($b['predicted_peak'] ?? 0);
  if ($ap === $bp) return 0;
  return ($ap > $bp) ? -1 : 1;
});

$actions = [];
foreach ($hotspots as $h) {
  if (!is_array($h)) continue;
  $loc = (string)($h['area_label'] ?? '');
  $time = (string)($h['peak_hour'] ?? '');
  $sev = (string)($h['severity'] ?? 'medium');
  $extra = $h['recommended_extra_units'];
  if (is_int($extra) && $extra > 0) {
    $actions[] = 'Deploy +' . $extra . ' units to ' . $loc . ' before ' . $time . '.';
  } else {
    if ($sev === 'critical') $actions[] = 'Activate reserve dispatch plan for ' . $loc . ' before ' . $time . '.';
    elseif ($sev === 'high') $actions[] = 'Shorten headways at ' . $loc . ' before ' . $time . '.';
    else $actions[] = 'Pre-position staff and manage bay loading at ' . $loc . ' around ' . $time . '.';
  }
  $drivers = $h['drivers'] ?? [];
  if (is_array($drivers) && $drivers) {
    $actions[] = 'Drivers: ' . implode(' • ', array_slice($drivers, 0, 3)) . '.';
  }
}

if (is_numeric($trafficCong) && (float)$trafficCong >= $trafficThreshold) {
  $pct = (int)round(((float)$trafficCong) * 100);
  $actions[] = 'Traffic congestion is elevated (' . $pct . '%). Expect slower turnaround and queue buildup.';
}
if (is_numeric($trafficIncidents) && (int)$trafficIncidents > 0) {
  $actions[] = 'Road incidents detected (' . (int)$trafficIncidents . '). Monitor diversions and adjust dispatch timing.';
}
$actions = array_values(array_unique($actions));

$underutilized = [];
if (isset($forecast['areas']) && is_array($forecast['areas'])) {
  $spikeNames = [];
  foreach ($hotspots as $h) {
    if (is_array($h) && !empty($h['area_label'])) $spikeNames[(string)$h['area_label']] = true;
  }
  foreach ($forecast['areas'] as $a) {
    if (!is_array($a)) continue;
    $name = (string)($a['area_label'] ?? '');
    if ($name === '' || isset($spikeNames[$name])) continue;
    $peak = 0;
    if (isset($a['forecast']) && is_array($a['forecast'])) {
      foreach ($a['forecast'] as $p) {
        if (!is_array($p)) continue;
        $peak = max($peak, (int)($p['predicted'] ?? 0));
      }
    }
    if ($peak <= 1) {
      $underutilized[] = ['area_label' => $name, 'predicted_peak' => $peak];
    }
  }
  usort($underutilized, function ($a, $b) {
    return ((int)($a['predicted_peak'] ?? 0)) <=> ((int)($b['predicted_peak'] ?? 0));
  });
  $underutilized = array_slice($underutilized, 0, 5);
}

// --- Dynamic AI Insights Generation ---

function generate_over_demand_insights(array $alerts, string $areaType): array {
  $insights = [];
  
  if (empty($alerts)) {
    return [
      'Demand levels are within normal operating limits.',
      'Maintain standard dispatch intervals.',
      'Monitor real-time arrivals for unexpected surges.'
    ];
  }

  foreach ($alerts as $alert) {
    $loc = $alert['area_label'];
    $time = explode(' ', $alert['peak_hour'])[1] ?? $alert['peak_hour']; // Extract hour
    $sev = $alert['severity'];
    $rain = ($alert['weather']['precip_prob'] ?? 0) > 50;

    // Severity-based recommendations
    if ($sev === 'critical') {
      $msg = "CRITICAL: **{$loc}** forecast to exceed capacity by >100% at {$time}. Immediate dispatch of reserve units required.";
      if ($rain) $msg .= " (High Rain Probability: Expect slower turnaround times).";
      $insights[] = $msg;
    } elseif ($sev === 'high') {
      $insights[] = "High Demand at **{$loc}** ({$time}). Shorten dispatch headways by 5-10 minutes to prevent queuing.";
    } else {
      $insights[] = "Moderate surge expected at **{$loc}** around {$time}. Alert terminal staff to manage loading bays.";
    }

    // Load-based recommendations
    if ($alert['load_status'] === 'potential_over_demand' && $alert['supply_units']) {
      $insights[] = "Supply Gap: **{$loc}** has only {$alert['supply_units']} authorized units. Consider temporary cross-route authority if demand persists.";
    }
  }

  // General rain advice if any alert has rain
  foreach ($alerts as $a) {
    if (($a['weather']['precip_prob'] ?? 0) > 60) {
      $insights[] = "Rain Alert: Wet conditions detected. Implement 'Wet Weather Dispatch' protocol (slower speeds = need more units).";
      break; 
    }
  }

  return array_unique($insights);
}

function generate_under_demand_insights(array $forecastData, array $alerts, string $areaType): array {
  $insights = [];
  $overloadedAreas = array_column($alerts, 'area_label');
  
  // Find areas with low predicted peaks compared to baseline or capacity
  $lowDemandAreas = [];
  
  if (isset($forecastData['areas']) && is_array($forecastData['areas'])) {
    foreach ($forecastData['areas'] as $area) {
      $name = $area['area_label'];
      if (in_array($name, $overloadedAreas)) continue; // Skip overloaded areas

      // Calculate peak for this area
      $peak = 0;
      if (isset($area['forecast']) && is_array($area['forecast'])) {
        foreach ($area['forecast'] as $p) {
          $peak = max($peak, $p['predicted']);
        }
      }

      // Logic: If peak is very low (< 5 or < 20% of capacity if known)
      // For now, simple threshold
      if ($peak < 5) {
        $lowDemandAreas[] = $name;
      }
    }
  }

  if (!empty($lowDemandAreas)) {
    $list = implode(', ', array_slice($lowDemandAreas, 0, 3));
    if (count($lowDemandAreas) > 3) $list .= " and " . (count($lowDemandAreas)-3) . " others";
    
    $insights[] = "Low Activity: **{$list}** showing minimal demand. Extend headways to conserve fuel/energy.";
    $insights[] = "Optimization: Reassign 1-2 units from low-demand zones to high-demand areas (if route regulations allow).";
    $insights[] = "Maintenance Opportunity: Schedule vehicle inspections/repairs for units assigned to {$list}.";
  } else {
    $insights[] = "No significant under-utilization detected across the network.";
    $insights[] = "Standard rotation applies for all routes.";
  }

  return $insights;
}

$readiness = [
  'accuracy' => (float)($forecast['accuracy'] ?? 0),
  'target' => (float)($forecast['accuracy_target'] ?? 80),
  'ok' => (bool)($forecast['accuracy_ok'] ?? false),
  'data_points' => (int)($forecast['data_points'] ?? 0),
  'data_source' => (string)($forecast['data_source'] ?? 'unknown'),
];
$need = max(0, 40 - (int)$readiness['data_points']);
$readiness['needed_points'] = $need;

echo json_encode([
  'ok' => true,
  'area_type' => $areaType,
  'hours' => $hours,
  'readiness' => $readiness,
  'traffic' => [
    'congestion' => $trafficCong,
    'incidents_count' => $trafficIncidents,
    'max_delay' => $trafficMaxDelay,
  ],
  'hotspots' => $hotspots,
  'actions' => array_slice($actions, 0, 8),
  'underutilized' => $underutilized,
  'alerts' => $alerts,
  'playbook' => [
    'over_demand' => generate_over_demand_insights($alerts, $areaType),
    'under_demand' => generate_under_demand_insights($forecast, $alerts, $areaType),
  ],
]);
