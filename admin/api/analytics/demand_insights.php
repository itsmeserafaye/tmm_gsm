<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
header('Content-Type: application/json');

$areaType = trim((string)($_GET['area_type'] ?? 'terminal'));
if (!in_array($areaType, ['terminal', 'parking_area'], true)) $areaType = 'terminal';

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

// Generate Alerts Analysis
$alerts = [];
foreach ($spikes as $s) {
  if (!is_array($s)) continue;
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
    'constraint' => $constraint,
    'weather' => $weather
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
  'alerts' => $alerts,
  'playbook' => [
    'over_demand' => generate_over_demand_insights($alerts, $areaType),
    'under_demand' => generate_under_demand_insights($forecast, $alerts, $areaType),
  ],
]);
