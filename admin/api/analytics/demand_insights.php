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

function tmm_playbook_over_demand(): array {
  return [
    'Activate reserve units within the same route/TODA (standby list).',
    'Stagger dispatch and shorten headways for the affected terminal only.',
    'Implement queue marshaling: prioritize seniors/PWD, enforce loading order, prevent “cutting”.',
    'Extend operating hours for the same route (early dispatch / late dispatch).',
    'Coordinate with enforcers for traffic flow and loading bay clearing.',
    'If still overloaded, escalate to city transport office for temporary special dispatch authority.'
  ];
}

function tmm_playbook_under_demand(): array {
  return [
    'Increase headways (less frequent dispatch) while keeping minimum service.',
    'Rotate dispatch fairly: implement “skip turn” rotation to reduce empty trips.',
    'Schedule maintenance/inspection during low-demand windows.',
    'Use holding areas to avoid road congestion and reduce fuel waste.',
    'Coordinate with terminal management for real-time passenger information (next departure).',
    'Adjust shift schedules for the same route (avoid over-supplying off-peak).'
  ];
}

$alerts = [];
foreach ($spikes as $s) {
  if (!is_array($s)) continue;
  $label = (string)($s['area_label'] ?? '');
  $pred = (int)($s['predicted_peak'] ?? 0);
  $baseline = (float)($s['baseline'] ?? 0);
  $peakHour = (string)($s['peak_hour'] ?? '');

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
  ];
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
    'over_demand' => tmm_playbook_over_demand(),
    'under_demand' => tmm_playbook_under_demand(),
  ],
]);

