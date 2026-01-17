<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/external_data.php';
$db = db();
header('Content-Type: application/json');

$areaType = trim((string)($_GET['area_type'] ?? 'terminal'));
$areaType = $areaType === 'parking_area' ? 'route' : $areaType;
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

$supplyByTerminalName = [];
if ($areaType === 'terminal') {
  $res = $db->query("SELECT terminal_name, COUNT(*) AS c FROM terminal_assignments WHERE status IS NULL OR status='Authorized' GROUP BY terminal_name");
  while ($res && ($r = $res->fetch_assoc())) {
    $supplyByTerminalName[(string)$r['terminal_name']] = (int)($r['c'] ?? 0);
  }
}

function tmm_congestion_class(float $currentSpeed, float $freeFlowSpeed): string {
  if ($freeFlowSpeed <= 0.0 || $currentSpeed <= 0.0) return 'unknown';
  $ratio = $currentSpeed / $freeFlowSpeed;
  if ($ratio >= 0.85) return 'free';
  if ($ratio >= 0.65) return 'moderate';
  if ($ratio >= 0.45) return 'heavy';
  return 'severe';
}

function tmm_tomtom_flow_summary(?array $flow): ?array {
  if (!is_array($flow)) return null;
  $fsd = $flow['flowSegmentData'] ?? null;
  if (!is_array($fsd)) return null;
  $cur = isset($fsd['currentSpeed']) ? (float)$fsd['currentSpeed'] : null;
  $free = isset($fsd['freeFlowSpeed']) ? (float)$fsd['freeFlowSpeed'] : null;
  if (!is_float($cur) || !is_float($free)) return null;
  $conf = isset($fsd['confidence']) ? (float)$fsd['confidence'] : null;
  $class = tmm_congestion_class($cur, $free);
  $congestionPct = ($free > 0.0) ? round(max(0.0, min(100.0, (1.0 - ($cur / $free)) * 100.0)), 1) : null;
  return [
    'current_speed_kph' => round($cur, 1),
    'free_flow_speed_kph' => round($free, 1),
    'congestion' => $class,
    'congestion_pct' => $congestionPct,
    'confidence' => is_float($conf) ? round($conf, 2) : null,
  ];
}

function tmm_tomtom_incidents_summary(?array $incidents): ?array {
  if (!is_array($incidents)) return null;
  $list = $incidents['incidents'] ?? null;
  if (!is_array($list)) return ['count' => 0, 'samples' => []];
  $samples = [];
  foreach ($list as $it) {
    if (!is_array($it)) continue;
    $p = $it['properties'] ?? null;
    if (!is_array($p)) continue;
    $desc = '';
    $events = $p['events'] ?? null;
    if (is_array($events) && !empty($events) && is_array($events[0] ?? null)) {
      $desc = (string)($events[0]['description'] ?? '');
    }
    if ($desc === '') $desc = (string)($p['from'] ?? '');
    if ($desc === '') $desc = (string)($p['to'] ?? '');
    if ($desc === '') $desc = (string)($p['iconCategory'] ?? '');
    $desc = trim($desc);
    if ($desc !== '') $samples[] = $desc;
    if (count($samples) >= 3) break;
  }
  return ['count' => count($list), 'samples' => $samples];
}

function tmm_load_terminal_location(mysqli $db, string $terminalId): ?array {
  if ($terminalId === '') return null;
  $stmt = $db->prepare("SELECT name, city, address FROM terminals WHERE id=? LIMIT 1");
  if (!$stmt) return null;
  $stmt->bind_param('s', $terminalId);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!is_array($row)) return null;
  return [
    'name' => (string)($row['name'] ?? ''),
    'city' => (string)($row['city'] ?? ''),
    'address' => (string)($row['address'] ?? ''),
  ];
}

function tmm_load_route_endpoints(mysqli $db, string $routeId): ?array {
  if ($routeId === '') return null;
  $stmt = $db->prepare("SELECT route_name, origin, destination FROM routes WHERE route_id=? LIMIT 1");
  if (!$stmt) return null;
  $stmt->bind_param('s', $routeId);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!is_array($row)) return null;
  return [
    'route_name' => (string)($row['route_name'] ?? ''),
    'origin' => (string)($row['origin'] ?? ''),
    'destination' => (string)($row['destination'] ?? ''),
  ];
}

// Generate Alerts Analysis
$alerts = [];
foreach ($spikes as $s) {
  if (!is_array($s)) continue;
  $ref = (string)($s['area_ref'] ?? '');
  $label = (string)($s['area_label'] ?? '');
  $pred = (int)($s['predicted_peak'] ?? 0);
  $baseline = (float)($s['baseline'] ?? 0);
  $peakHour = (string)($s['peak_hour'] ?? '');
  $weather = $s['weather'] ?? [];

  $supply = null;
  if ($areaType === 'terminal' && $label !== '') {
    $supply = $supplyByTerminalName[$label] ?? null;
  }

  $severity = 'medium';
  if ($baseline > 0 && $pred >= (int)ceil($baseline * 1.6)) $severity = 'high';
  if ($baseline > 0 && $pred >= (int)ceil($baseline * 2.0)) $severity = 'critical';

  $baselinePerUnit = null;
  $predPerUnit = null;
  $loadStatus = 'unknown';
  if (is_int($supply) && $supply > 0) {
    $baselinePerUnit = round($baseline / $supply, 3);
    $predPerUnit = round($pred / $supply, 3);
    if ($baselinePerUnit <= 0) {
      $loadStatus = $predPerUnit > 0 ? 'potential_over_demand' : 'normal';
    } else {
      $loadStatus = ($predPerUnit >= ($baselinePerUnit * 1.3)) ? 'potential_over_demand' : 'normal';
    }
  }

  $constraint = 'Recommendations keep operators within their assigned routes.';
  $traffic = null;
  $trafficStatus = 'unavailable';
  if (tmm_tomtom_api_key($db) !== '') {
    if ($areaType === 'terminal') {
      $tloc = tmm_load_terminal_location($db, $ref);
      if (is_array($tloc)) {
        $q = trim(($tloc['address'] ?? '') . ' ' . ($tloc['city'] ?? '') . ' Philippines');
        $geo = tmm_tomtom_geocode($db, $q);
        if (is_array($geo)) {
          $flow = tmm_tomtom_traffic_flow($db, (float)$geo['lat'], (float)$geo['lon']);
          $inc = tmm_tomtom_traffic_incidents($db, (float)$geo['lat'], (float)$geo['lon'], 2.5);
          $traffic = [
            'point' => ['lat' => (float)$geo['lat'], 'lon' => (float)$geo['lon'], 'label' => (string)($tloc['name'] ?? $label)],
            'flow' => tmm_tomtom_flow_summary($flow),
            'incidents' => tmm_tomtom_incidents_summary($inc),
          ];
          $trafficStatus = 'ok';
        }
      }
    } else {
      $route = tmm_load_route_endpoints($db, $ref);
      if (is_array($route)) {
        $oQ = trim(((string)($route['origin'] ?? '')) . ' Philippines');
        $dQ = trim(((string)($route['destination'] ?? '')) . ' Philippines');
        $oGeo = $oQ !== '' ? tmm_tomtom_geocode($db, $oQ) : null;
        $dGeo = $dQ !== '' ? tmm_tomtom_geocode($db, $dQ) : null;
        $traffic = [
          'origin' => null,
          'destination' => null,
        ];
        $okAny = false;
        if (is_array($oGeo)) {
          $oFlow = tmm_tomtom_traffic_flow($db, (float)$oGeo['lat'], (float)$oGeo['lon']);
          $oInc = tmm_tomtom_traffic_incidents($db, (float)$oGeo['lat'], (float)$oGeo['lon'], 2.5);
          $traffic['origin'] = [
            'point' => ['lat' => (float)$oGeo['lat'], 'lon' => (float)$oGeo['lon'], 'label' => (string)($route['origin'] ?? 'Origin')],
            'flow' => tmm_tomtom_flow_summary($oFlow),
            'incidents' => tmm_tomtom_incidents_summary($oInc),
          ];
          $okAny = true;
        }
        if (is_array($dGeo)) {
          $dFlow = tmm_tomtom_traffic_flow($db, (float)$dGeo['lat'], (float)$dGeo['lon']);
          $dInc = tmm_tomtom_traffic_incidents($db, (float)$dGeo['lat'], (float)$dGeo['lon'], 2.5);
          $traffic['destination'] = [
            'point' => ['lat' => (float)$dGeo['lat'], 'lon' => (float)$dGeo['lon'], 'label' => (string)($route['destination'] ?? 'Destination')],
            'flow' => tmm_tomtom_flow_summary($dFlow),
            'incidents' => tmm_tomtom_incidents_summary($dInc),
          ];
          $okAny = true;
        }
        if ($okAny) $trafficStatus = 'ok';
      }
    }
  }
  $alerts[] = [
    'area_ref' => $ref,
    'area_label' => $label,
    'peak_hour' => $peakHour,
    'predicted_peak' => $pred,
    'baseline' => $baseline,
    'severity' => $severity,
    'supply_units' => $supply,
    'baseline_per_unit' => $baselinePerUnit,
    'predicted_per_unit' => $predPerUnit,
    'load_status' => $loadStatus,
    'constraint' => $constraint,
    'weather' => $weather,
    'traffic_status' => $trafficStatus,
    'traffic' => $traffic
  ];
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
      $msg = "CRITICAL: **{$loc}** forecast to exceed baseline demand by >100% at {$time}. Immediate dispatch of route-compliant reserve units required.";
      if ($rain) $msg .= " (High Rain Probability: Expect slower turnaround times).";
      $insights[] = $msg;
    } elseif ($sev === 'high') {
      $insights[] = "High Demand at **{$loc}** ({$time}). Shorten dispatch headways by 5-10 minutes to prevent queuing.";
    } else {
      if ($areaType === 'terminal') {
        $insights[] = "Moderate surge expected at **{$loc}** around {$time}. Adjust bay staffing and loading flow.";
      } else {
        $insights[] = "Moderate surge expected on **{$loc}** around {$time}. Tighten dispatch cadence within the route.";
      }
    }

    // Load-based recommendations
    if ($alert['load_status'] === 'potential_over_demand' && $alert['supply_units']) {
      $insights[] = "Supply Gap: **{$loc}** has only {$alert['supply_units']} authorized units. Activate route-compliant reserve units and request additional units for the same route if demand persists.";
    }

    $t = $alert['traffic'] ?? null;
    if (($alert['traffic_status'] ?? '') === 'ok' && is_array($t)) {
      $parts = [];
      $points = [];
      if ($areaType === 'terminal') {
        $points[] = $t;
      } else {
        if (is_array($t['origin'] ?? null)) $points[] = $t['origin'];
        if (is_array($t['destination'] ?? null)) $points[] = $t['destination'];
      }
      foreach ($points as $pt) {
        $flow = $pt['flow'] ?? null;
        $inc = $pt['incidents'] ?? null;
        $label = (string)(($pt['point']['label'] ?? '') ?: 'Traffic Point');
        if (is_array($flow) && in_array((string)($flow['congestion'] ?? ''), ['heavy', 'severe'], true)) {
          $pct = $flow['congestion_pct'];
          $parts[] = "{$label}: {$flow['congestion']} congestion" . (is_numeric($pct) ? " (~{$pct}% slower vs free-flow)" : "");
        }
        if (is_array($inc) && ((int)($inc['count'] ?? 0) > 0)) {
          $c = (int)$inc['count'];
          $samp = $inc['samples'] ?? [];
          $tail = (is_array($samp) && !empty($samp)) ? (": " . implode('; ', array_slice($samp, 0, 2))) : '';
          $parts[] = "{$label}: {$c} incident(s){$tail}";
        }
      }
      if (!empty($parts)) {
        $insights[] = "Traffic Impact: " . implode(' | ', $parts) . ".";
      }
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
    $insights[] = "Optimization: Reduce dispatch frequency on these routes/terminals and prioritize service on the same assigned routes where demand is higher.";
    $insights[] = "Maintenance Opportunity: Schedule inspections/repairs for units assigned to {$list}.";
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
  'alerts' => $alerts,
  'playbook' => [
    'over_demand' => generate_over_demand_insights($alerts, $areaType),
    'under_demand' => generate_under_demand_insights($forecast, $alerts, $areaType),
  ],
]);
