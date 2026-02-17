<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');
require_login(false);
require_any_permission(['module1.read', 'module1.view', 'module1.write', 'module1.vehicles.write']);

$mode = strtolower(trim((string)($_GET['mode'] ?? '')));
$province = trim((string)($_GET['province'] ?? ''));
$city = trim((string)($_GET['city'] ?? ''));

$out = ['ok' => true, 'data' => []];

$db = db();

$fetchDistinct = function (string $sql, string $types = '', array $params = []) use ($db): array {
  $rows = [];
  if ($types !== '') {
    $stmt = $db->prepare($sql);
    if (!$stmt) return [];
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($r = $res->fetch_assoc())) {
      $v = trim((string)($r['v'] ?? ''));
      if ($v !== '') $rows[] = $v;
    }
    $stmt->close();
  } else {
    $res = $db->query($sql);
    while ($res && ($r = $res->fetch_assoc())) {
      $v = trim((string)($r['v'] ?? ''));
      if ($v !== '') $rows[] = $v;
    }
  }
  $rows = array_values(array_unique($rows));
  sort($rows, SORT_NATURAL | SORT_FLAG_CASE);
  return $rows;
};

function tmm_load_ph_geo_from_psgc(): ?array {
  static $cache = null;
  if ($cache !== null) return $cache;
  $url = 'https://raw.githubusercontent.com/flores-jacob/philippine-regions-provinces-cities-municipalities-barangays/master/philippine_provinces_cities_municipalities_and_barangays_2019v2.json';
  $ctx = stream_context_create([
    'http' => [
      'timeout' => 5,
      'header' => "User-Agent: tmm-psgc-client\r\n",
    ],
    'https' => [
      'timeout' => 5,
      'header' => "User-Agent: tmm-psgc-client\r\n",
    ],
  ]);
  $json = @file_get_contents($url, false, $ctx);
  if ($json === false || $json === '') {
    $cache = null;
    return null;
  }
  $data = json_decode($json, true);
  if (!is_array($data)) {
    $cache = null;
    return null;
  }
  $cache = $data;
  return $cache;
}

if ($mode === 'provinces') {
  $geo = tmm_load_ph_geo_from_psgc();
  if ($geo) {
    $set = [];
    foreach ($geo as $region) {
      if (!is_array($region)) continue;
      $plist = $region['province_list'] ?? [];
      if (!is_array($plist)) continue;
      foreach ($plist as $pname => $pinfo) {
        $name = trim((string)$pname);
        if ($name !== '') $set[$name] = true;
      }
    }
    $names = array_keys($set);
    natcasesort($names);
    $out['data'] = array_values($names);
  } else {
    $out['data'] = $fetchDistinct("SELECT DISTINCT TRIM(address_province) AS v FROM operators WHERE address_province IS NOT NULL AND TRIM(address_province)<>'' ORDER BY v ASC LIMIT 500");
  }
  echo json_encode($out);
  exit;
}

if ($mode === 'cities') {
  if ($province === '') { echo json_encode($out); exit; }
  $geo = tmm_load_ph_geo_from_psgc();
  if ($geo) {
    $set = [];
    $provKey = strtoupper($province);
    foreach ($geo as $region) {
      if (!is_array($region)) continue;
      $plist = $region['province_list'] ?? [];
      if (!is_array($plist)) continue;
      foreach ($plist as $pname => $pinfo) {
        if (strtoupper((string)$pname) !== $provKey) continue;
        if (!is_array($pinfo)) continue;
        $mlist = $pinfo['municipality_list'] ?? [];
        if (!is_array($mlist)) continue;
        foreach ($mlist as $mname => $minfo) {
          $name = trim((string)$mname);
          if ($name !== '') $set[$name] = true;
        }
      }
    }
    $names = array_keys($set);
    natcasesort($names);
    $out['data'] = array_values($names);
  } else {
    $out['data'] = $fetchDistinct("SELECT DISTINCT TRIM(address_city) AS v FROM operators WHERE TRIM(address_province)=? AND address_city IS NOT NULL AND TRIM(address_city)<>'' ORDER BY v ASC LIMIT 800", 's', [$province]);
  }
  echo json_encode($out);
  exit;
}

if ($mode === 'barangays') {
  if ($province === '' || $city === '') { echo json_encode($out); exit; }
  $geo = tmm_load_ph_geo_from_psgc();
  if ($geo) {
    $set = [];
    $provKey = strtoupper($province);
    $cityKey = strtoupper($city);
    foreach ($geo as $region) {
      if (!is_array($region)) continue;
      $plist = $region['province_list'] ?? [];
      if (!is_array($plist)) continue;
      foreach ($plist as $pname => $pinfo) {
        if (strtoupper((string)$pname) !== $provKey) continue;
        if (!is_array($pinfo)) continue;
        $mlist = $pinfo['municipality_list'] ?? [];
        if (!is_array($mlist)) continue;
        foreach ($mlist as $mname => $minfo) {
          if (strtoupper((string)$mname) !== $cityKey) continue;
          if (!is_array($minfo)) continue;
          $blist = $minfo['barangay_list'] ?? [];
          if (!is_array($blist)) continue;
          foreach ($blist as $bname) {
            $name = trim((string)$bname);
            if ($name !== '') $set[$name] = true;
          }
        }
      }
    }
    $names = array_keys($set);
    natcasesort($names);
    $out['data'] = array_values($names);
  } else {
    $out['data'] = $fetchDistinct("SELECT DISTINCT TRIM(address_barangay) AS v FROM operators WHERE TRIM(address_province)=? AND TRIM(address_city)=? AND address_barangay IS NOT NULL AND TRIM(address_barangay)<>'' ORDER BY v ASC LIMIT 1200", 'ss', [$province, $city]);
  }
  echo json_encode($out);
  exit;
}

if ($mode === 'postals') {
  if ($province === '' || $city === '') { echo json_encode($out); exit; }
  $out['data'] = $fetchDistinct("SELECT DISTINCT TRIM(address_postal_code) AS v FROM operators WHERE TRIM(address_province)=? AND TRIM(address_city)=? AND address_postal_code IS NOT NULL AND TRIM(address_postal_code)<>'' ORDER BY v ASC LIMIT 50", 'ss', [$province, $city]);
  echo json_encode($out);
  exit;
}

echo json_encode(['ok' => false, 'error' => 'invalid_mode']);
?>
