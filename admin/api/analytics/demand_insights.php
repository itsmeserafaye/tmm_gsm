<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/external_data.php';
$db = db();
// Only set header if not being included by another API endpoint
if (php_sapi_name() !== 'cli' && !ob_get_level()) {
    header('Content-Type: application/json');
}

$areaType = trim((string)($_GET['area_type'] ?? 'terminal'));
$areaType = $areaType === 'parking_area' ? 'route' : $areaType;
if (!in_array($areaType, ['terminal', 'route'], true)) $areaType = 'terminal';

$hours = (int)($_GET['hours'] ?? 24);
if ($hours < 6) $hours = 6;
if ($hours > 72) $hours = 72;

$includeTraffic = ((int)($_GET['include_traffic'] ?? 0)) === 1;

$demandUrl = __DIR__ . '/demand_forecast.php';

ob_start();
$_GET['area_type'] = $areaType;
$_GET['hours'] = (string)$hours;
$_GET['include_traffic'] = $includeTraffic ? '1' : '0';
include $demandUrl;
$raw = ob_get_clean();
$forecast = json_decode((string)$raw, true);

if (!is_array($forecast) || !($forecast['ok'] ?? false)) {
  echo json_encode(['ok' => false, 'error' => 'forecast_unavailable']);
  if (!ob_get_level() && php_sapi_name() !== 'cli') {
    exit;
  }
}

$spikes = $forecast['spikes'] ?? [];
if (!is_array($spikes)) $spikes = [];

$areasForecast = $forecast['areas'] ?? [];
if (!is_array($areasForecast)) $areasForecast = [];
$areaByRef = [];
foreach ($areasForecast as $a) {
  if (!is_array($a)) continue;
  $ref = (string)($a['area_ref'] ?? '');
  if ($ref !== '') $areaByRef[$ref] = $a;
}

$supplyByTerminalName = [];
if ($areaType === 'terminal') {
  $res = $db->query("SELECT terminal_name, COUNT(*) AS c FROM terminal_assignments WHERE status IS NULL OR status='Authorized' GROUP BY terminal_name");
  while ($res && ($r = $res->fetch_assoc())) {
    $supplyByTerminalName[(string)$r['terminal_name']] = (int)($r['c'] ?? 0);
  }
}
$supplyByRouteId = [];
if ($areaType === 'route') {
  $res = $db->query("SELECT route_id, COUNT(*) AS c FROM terminal_assignments WHERE status IS NULL OR status='Authorized' GROUP BY route_id");
  while ($res && ($r = $res->fetch_assoc())) {
    $supplyByRouteId[(string)$r['route_id']] = (int)($r['c'] ?? 0);
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
  $areaItem = ($ref !== '' && isset($areaByRef[$ref]) && is_array($areaByRef[$ref])) ? $areaByRef[$ref] : null;
  $peakPoint = null;
  if (is_array($areaItem) && is_array($areaItem['forecast'] ?? null)) {
    foreach ($areaItem['forecast'] as $p) {
      if (!is_array($p)) continue;
      if ($peakHour !== '' && (string)($p['hour_label'] ?? '') === $peakHour) { $peakPoint = $p; break; }
    }
    if (!$peakPoint) {
      $best = null;
      foreach (array_slice($areaItem['forecast'], 0, 6) as $p) {
        if (!is_array($p)) continue;
        $pv = (int)($p['predicted_adjusted'] ?? $p['predicted'] ?? 0);
        if ($best === null || $pv > (int)($best['pv'] ?? 0)) $best = ['pv' => $pv, 'p' => $p];
      }
      if (is_array($best) && is_array($best['p'] ?? null)) $peakPoint = $best['p'];
    }
  }
  $weather = is_array($peakPoint) ? ($peakPoint['weather'] ?? []) : [];
  $event = is_array($peakPoint) ? ($peakPoint['event'] ?? null) : null;
  $combinedFactor = is_array($peakPoint) ? (float)($peakPoint['combined_factor'] ?? 1.0) : 1.0;
  $trafficFactor = is_array($peakPoint) ? (float)($peakPoint['traffic_factor'] ?? 1.0) : 1.0;
  $weatherFactor = is_array($peakPoint) ? (float)($peakPoint['weather_factor'] ?? 1.0) : 1.0;
  $eventFactor = is_array($peakPoint) ? (float)($peakPoint['event_factor'] ?? 1.0) : 1.0;

  $supply = null;
  if ($areaType === 'terminal' && $label !== '') {
    $supply = $supplyByTerminalName[$label] ?? null;
  }
  if ($areaType === 'route' && $ref !== '') {
    $supply = $supplyByRouteId[$ref] ?? null;
  }

  $severity = 'medium';
  if ($baseline > 0 && $pred >= (int)ceil($baseline * 1.6)) $severity = 'high';
  if ($baseline > 0 && $pred >= (int)ceil($baseline * 2.0)) $severity = 'critical';

  $baselinePerUnit = null;
  $predPerUnit = null;
  $loadStatus = 'unknown';
  $requiredUnits = null;
  $additionalUnits = null;
  if (is_int($supply) && $supply > 0) {
    $baselinePerUnit = round($baseline / $supply, 3);
    $predPerUnit = round($pred / $supply, 3);
    if ($baselinePerUnit <= 0) {
      $loadStatus = $predPerUnit > 0 ? 'potential_over_demand' : 'normal';
    } else {
      $loadStatus = ($predPerUnit >= ($baselinePerUnit * 1.3)) ? 'potential_over_demand' : 'normal';
    }
    $targetLoad = max(1.0, (float)$baselinePerUnit);
    $requiredUnits = (int)ceil($pred / $targetLoad);
    $additionalUnits = max(0, $requiredUnits - $supply);
  }

  $constraint = 'Recommendations keep operators within their assigned routes.';
  $traffic = is_array($areaItem) ? ($areaItem['traffic'] ?? null) : null;
  $trafficStatus = is_array($areaItem) ? (string)($areaItem['traffic_status'] ?? 'unavailable') : 'unavailable';
  if (tmm_tomtom_api_key($db) !== '') {
    if ($trafficStatus === 'ok' && is_array($traffic)) {
    } else
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
    'required_units' => $requiredUnits,
    'additional_units' => $additionalUnits,
    'baseline_per_unit' => $baselinePerUnit,
    'predicted_per_unit' => $predPerUnit,
    'load_status' => $loadStatus,
    'constraint' => $constraint,
    'weather' => $weather,
    'event' => $event,
    'factors' => [
      'combined' => round($combinedFactor, 3),
      'traffic' => round($trafficFactor, 3),
      'weather' => round($weatherFactor, 3),
      'event' => round($eventFactor, 3),
    ],
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
    $loc = (string)($alert['area_label'] ?? 'Unknown');
    $time = explode(' ', (string)($alert['peak_hour'] ?? ''))[1] ?? (string)($alert['peak_hour'] ?? '');
    $sev = (string)($alert['severity'] ?? 'medium');
    $pred = (int)($alert['predicted_peak'] ?? 0);
    $base = (float)($alert['baseline'] ?? 0);
    $deltaPct = ($base > 0) ? round((($pred - $base) / $base) * 100.0, 1) : null;
    $supply = $alert['supply_units'] ?? null;
    $req = $alert['required_units'] ?? null;
    $add = $alert['additional_units'] ?? null;
    $rainProb = is_array($alert['weather'] ?? null) ? (float)($alert['weather']['precip_prob'] ?? 0) : 0.0;
    $rainMm = is_array($alert['weather'] ?? null) ? (float)($alert['weather']['precip_mm'] ?? 0) : 0.0;
    $rain = $rainProb >= 60;
    $evt = is_array($alert['event'] ?? null) ? $alert['event'] : null;
    $evtTitle = is_array($evt) ? trim((string)($evt['title'] ?? '')) : '';
    $f = is_array($alert['factors'] ?? null) ? $alert['factors'] : [];
    $tf = isset($f['traffic']) ? (float)$f['traffic'] : 1.0;
    $wf = isset($f['weather']) ? (float)$f['weather'] : 1.0;
    $ef = isset($f['event']) ? (float)$f['event'] : 1.0;
    $drivers = [];
    if (abs($tf - 1.0) > 0.02) $drivers[] = 'Traffic×' . number_format($tf, 2);
    if (abs($wf - 1.0) > 0.02) $drivers[] = 'Weather×' . number_format($wf, 2);
    if (abs($ef - 1.0) > 0.02) $drivers[] = 'Event×' . number_format($ef, 2);
    $driversTxt = $drivers ? (' Drivers: ' . implode(' • ', $drivers) . '.') : '';
    $supplyTxt = (is_int($supply) && $supply > 0) ? (' Units: ' . $supply) : ' Units: —';
    if (is_int($req)) $supplyTxt .= ' • Needed: ' . $req . (is_int($add) && $add > 0 ? (' (+' . $add . ')') : '');
    $wxTxt = $rain ? (" Weather: rain prob {$rainProb}%" . ($rainMm > 0 ? (" • ~{$rainMm}mm") : "") . ".") : '';
    $evtTxt = $evtTitle !== '' ? (" Event: {$evtTitle}.") : '';

    // Severity-based recommendations
    if ($sev === 'critical') {
      $insights[] = "CRITICAL: **{$loc}** peak at {$time}. Predicted {$pred} vs baseline {$base}" . ($deltaPct !== null ? " ({$deltaPct}%)" : "") . ".{$supplyTxt}.{$driversTxt}{$evtTxt}{$wxTxt}";
      $insights[] = "Action: shorten headways by 10–15 minutes, stage route-compliant reserves, and increase bay/queue marshals to prevent spillover.";
    } elseif ($sev === 'high') {
      $insights[] = "High demand: **{$loc}** around {$time}. Predicted {$pred} vs baseline {$base}" . ($deltaPct !== null ? " ({$deltaPct}%)" : "") . ".{$supplyTxt}.{$driversTxt}{$evtTxt}{$wxTxt}";
      $insights[] = "Action: shorten headways by 5–10 minutes and pre-position standby units within the same route assignment.";
    } else {
      if ($areaType === 'terminal') {
        $insights[] = "Moderate surge: **{$loc}** around {$time}. Predicted {$pred} vs baseline {$base}" . ($deltaPct !== null ? " ({$deltaPct}%)" : "") . ".{$supplyTxt}.{$driversTxt}";
        $insights[] = "Action: adjust bay staffing and loading flow; monitor queue length every 15 minutes.";
      } else {
        $insights[] = "Moderate surge: **{$loc}** around {$time}. Predicted {$pred} vs baseline {$base}" . ($deltaPct !== null ? " ({$deltaPct}%)" : "") . ".{$supplyTxt}.{$driversTxt}";
        $insights[] = "Action: tighten dispatch cadence within the route and monitor boarding hotspots.";
      }
    }

    // Load-based recommendations
    if ($alert['load_status'] === 'potential_over_demand' && $alert['supply_units']) {
      if (is_int($add) && $add > 0) {
        $insights[] = "Supply gap: **{$loc}** likely needs ~{$add} additional unit(s) at {$time} to match baseline loading conditions.";
      } else {
        $insights[] = "Supply gap risk: **{$loc}** may overload with current authorized units; monitor and escalate if queues build up.";
      }
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
      $insights[] = "Rain Alert: wet conditions likely. Expect slower turnaround times and higher dwell time—keep standby units ready and adjust dispatch intervals proactively.";
      break; 
    }
  }

  return array_unique($insights);
}

function generate_under_demand_insights(array $forecastData, array $alerts, string $areaType, array $supplyByTerminalName, array $supplyByRouteId): array {
  $insights = [];
  $overloadedAreas = array_column($alerts, 'area_label');
  
  // Find areas with low predicted peaks compared to baseline or capacity
  $lowDemandAreas = [];
  $oversupply = [];
  
  if (isset($forecastData['areas']) && is_array($forecastData['areas'])) {
    foreach ($forecastData['areas'] as $area) {
      $name = (string)($area['area_label'] ?? '');
      $ref = (string)($area['area_ref'] ?? '');
      if (in_array($name, $overloadedAreas)) continue; // Skip overloaded areas

      // Calculate peak for this area
      $peak = 0;
      if (isset($area['forecast']) && is_array($area['forecast'])) {
        foreach ($area['forecast'] as $p) {
          $pv = isset($p['predicted_adjusted']) ? (int)$p['predicted_adjusted'] : (int)($p['predicted'] ?? 0);
          $peak = max($peak, $pv);
        }
      }

      $supply = null;
      if ($areaType === 'terminal' && $name !== '') $supply = $supplyByTerminalName[$name] ?? null;
      if ($areaType === 'route' && $ref !== '') $supply = $supplyByRouteId[$ref] ?? null;

      if ($peak < 5) {
        $lowDemandAreas[] = ['name' => $name, 'peak' => $peak, 'supply' => $supply];
      }
      if (is_int($supply) && $supply > 0) {
        $util = $peak / $supply;
        if ($supply >= 5 && $util < 0.30) {
          $oversupply[] = ['name' => $name, 'peak' => $peak, 'supply' => $supply, 'util' => $util];
        }
      }
    }
  }

  if (!empty($oversupply)) {
    usort($oversupply, function($a, $b){ return ($b['supply'] ?? 0) <=> ($a['supply'] ?? 0); });
    foreach ($oversupply as $o) {
      $nm = (string)($o['name'] ?? 'Unknown');
      $su = (int)($o['supply'] ?? 0);
      $pk = (int)($o['peak'] ?? 0);
      $insights[] = "Oversupply: **{$nm}** has {$su} PUVs but forecast peak demand is {$pk}. Hold/rotate units, reduce loading bays, and avoid queue congestion.";
    }
  }

  if (!empty($lowDemandAreas)) {
    usort($lowDemandAreas, function($a, $b){ return ($a['peak'] ?? 0) <=> ($b['peak'] ?? 0); });
    $names = array_map(function($x){ return (string)($x['name'] ?? ''); }, $lowDemandAreas);
    $list = implode(', ', array_filter($names));
    $scopeWord = $areaType === 'terminal' ? 'terminals' : 'routes';
    $insights[] = "Low Activity: **{$list}** showing minimal demand. Extend headways and reduce staging to keep operations smooth.";
    $insights[] = "Optimization: prioritize dispatch within the same assigned routes where demand is higher; decongest low-activity {$scopeWord}.";
    $insights[] = "Maintenance Opportunity: schedule inspections/repairs during low-activity windows at {$list}.";
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

$miniShortage = [];
foreach ($alerts as $a) {
  if (!is_array($a)) continue;
  $add = $a['additional_units'] ?? null;
  if (!is_int($add) || $add <= 0) continue;
  $miniShortage[] = [
    'area_ref' => (string)($a['area_ref'] ?? ''),
    'area_label' => (string)($a['area_label'] ?? ''),
    'peak_hour' => (string)($a['peak_hour'] ?? ''),
    'peak_predicted' => (int)($a['predicted_peak'] ?? 0),
    'supply_units' => is_int($a['supply_units'] ?? null) ? (int)$a['supply_units'] : null,
    'recommended_units' => is_int($a['required_units'] ?? null) ? (int)$a['required_units'] : null,
    'suggested_delta' => $add,
  ];
}
usort($miniShortage, function($x, $y){
  $dx = (int)($x['suggested_delta'] ?? 0);
  $dy = (int)($y['suggested_delta'] ?? 0);
  if ($dx === $dy) return ((int)($y['peak_predicted'] ?? 0)) <=> ((int)($x['peak_predicted'] ?? 0));
  return $dy <=> $dx;
});
$miniShortage = array_slice($miniShortage, 0, 5);

$miniOversupply = [];
$overloadedLabels = array_column($alerts, 'area_label');
if (isset($forecast['areas']) && is_array($forecast['areas'])) {
  foreach ($forecast['areas'] as $area) {
    if (!is_array($area)) continue;
    $label = (string)($area['area_label'] ?? '');
    if ($label !== '' && in_array($label, $overloadedLabels, true)) continue;
    $ref = (string)($area['area_ref'] ?? '');
    $supply = null;
    if ($areaType === 'terminal' && $label !== '') $supply = $supplyByTerminalName[$label] ?? null;
    if ($areaType === 'route' && $ref !== '') $supply = $supplyByRouteId[$ref] ?? null;
    if (!is_int($supply) || $supply <= 0) continue;

    $peakPred = 0;
    $peakHour = '';
    $baselineAtPeak = 0.0;
    if (isset($area['forecast']) && is_array($area['forecast'])) {
      foreach ($area['forecast'] as $p) {
        if (!is_array($p)) continue;
        $pv = isset($p['predicted_adjusted']) ? (int)$p['predicted_adjusted'] : (int)($p['predicted'] ?? 0);
        if ($pv > $peakPred) {
          $peakPred = $pv;
          $peakHour = (string)($p['hour_label'] ?? '');
          $baselineAtPeak = (float)($p['baseline'] ?? 0.0);
        }
      }
    }

    $baselinePerUnit = $supply > 0 ? ($baselineAtPeak / $supply) : 0.0;
    $targetLoad = max(1.0, (float)$baselinePerUnit);
    $recommended = (int)max(0, ceil($peakPred / $targetLoad));
    $reduce = max(0, $supply - $recommended);
    if ($reduce < 3) continue;

    $miniOversupply[] = [
      'area_ref' => $ref,
      'area_label' => $label,
      'peak_hour' => $peakHour,
      'peak_predicted' => $peakPred,
      'supply_units' => $supply,
      'recommended_units' => $recommended,
      'suggested_delta' => -$reduce,
    ];
  }
}
usort($miniOversupply, function($x, $y){
  $dx = abs((int)($x['suggested_delta'] ?? 0));
  $dy = abs((int)($y['suggested_delta'] ?? 0));
  if ($dx === $dy) return ((int)($y['supply_units'] ?? 0)) <=> ((int)($x['supply_units'] ?? 0));
  return $dy <=> $dx;
});
$miniOversupply = array_slice($miniOversupply, 0, 5);

echo json_encode([
  'ok' => true,
  'area_type' => $areaType,
  'hours' => $hours,
  'readiness' => $readiness,
  'context' => [
    'data_source' => (string)($forecast['data_source'] ?? 'unknown'),
    'model' => $forecast['model'] ?? null,
    'weather' => $forecast['weather'] ?? null,
    'events' => $forecast['events'] ?? null,
  ],
  'area_lists' => $forecast['area_lists'] ?? null,
  'alerts' => $alerts,
  'mini_tables' => [
    'shortage' => $miniShortage,
    'oversupply' => $miniOversupply,
  ],
  'playbook' => [
    'over_demand' => generate_over_demand_insights($alerts, $areaType),
    'under_demand' => generate_under_demand_insights($forecast, $alerts, $areaType, $supplyByTerminalName, $supplyByRouteId),
  ],
]);
