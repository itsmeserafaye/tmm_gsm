<?php
require_once __DIR__ . '/../../../includes/cors.php';
tmm_apply_dev_cors();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/external_data.php';
$db = db();
header('Content-Type: application/json');

$country = strtoupper(trim(tmm_setting($db, 'events_country', 'PH')));
if ($country === '') $country = 'PH';
$city = trim(tmm_setting($db, 'events_city', 'Manila'));
$rssUrl = trim(tmm_setting($db, 'events_rss_url', ''));

$days = (int)($_GET['days'] ?? 7);
if ($days < 1) $days = 1;
if ($days > 30) $days = 30;

$year = (int)date('Y');
$cacheKey = 'events:nager:' . $country . ':' . $year;
$holidays = tmm_cache_get($db, $cacheKey);
if (!$holidays) {
  $url = "https://date.nager.at/api/v3/PublicHolidays/" . rawurlencode((string)$year) . "/" . rawurlencode($country);
  $res = tmm_http_get_json($url, 12);
  if (($res['ok'] ?? false) && is_array($res['data'])) {
    $holidays = $res['data'];
    tmm_cache_set($db, $cacheKey, $holidays, 24 * 3600);
  } else {
    $holidays = [];
  }
}

$today = date('Y-m-d');
$end = date('Y-m-d', strtotime('+' . $days . ' days'));
$events = [];
foreach ($holidays as $h) {
  if (!is_array($h)) continue;
  $d = (string)($h['date'] ?? '');
  if ($d === '' || $d < $today || $d > $end) continue;
  $events[] = [
    'date' => $d,
    'title' => (string)($h['localName'] ?? ($h['name'] ?? 'Holiday')),
    'type' => 'holiday',
    'source' => 'nager',
  ];
}

if ($rssUrl !== '') {
  $rssKey = 'events:rss:' . sha1($rssUrl);
  $rssCached = tmm_cache_get($db, $rssKey);
  $rssItems = null;
  if (is_array($rssCached)) {
    $rssItems = $rssCached;
  } else {
    $raw = @file_get_contents($rssUrl);
    if (is_string($raw) && $raw !== '') {
      $xml = @simplexml_load_string($raw);
      if ($xml) {
        $items = [];
        if (isset($xml->channel->item)) {
          foreach ($xml->channel->item as $it) {
            $title = trim((string)$it->title);
            $pub = trim((string)$it->pubDate);
            $link = trim((string)$it->link);
            $dt = $pub ? date('Y-m-d', strtotime($pub)) : '';
            if ($dt !== '') {
              $items[] = ['date' => $dt, 'title' => $title, 'link' => $link];
            }
          }
        } elseif (isset($xml->entry)) {
          foreach ($xml->entry as $it) {
            $title = trim((string)$it->title);
            $updated = trim((string)$it->updated);
            $dt = $updated ? date('Y-m-d', strtotime($updated)) : '';
            $link = '';
            if (isset($it->link)) {
              foreach ($it->link as $lnk) {
                $href = (string)$lnk['href'];
                if ($href) { $link = $href; break; }
              }
            }
            if ($dt !== '') {
              $items[] = ['date' => $dt, 'title' => $title, 'link' => $link];
            }
          }
        }
        $rssItems = $items;
        tmm_cache_set($db, $rssKey, $rssItems, 30 * 60);
      }
    }
  }

  if (is_array($rssItems)) {
    foreach ($rssItems as $it) {
      $d = (string)($it['date'] ?? '');
      if ($d === '' || $d < $today || $d > $end) continue;
      $events[] = [
        'date' => $d,
        'title' => (string)($it['title'] ?? 'Event'),
        'type' => 'event',
        'source' => 'rss',
        'link' => (string)($it['link'] ?? ''),
      ];
    }
  }
}

usort($events, function ($a, $b) { return strcmp($a['date'], $b['date']); });

echo json_encode([
  'ok' => true,
  'country' => $country,
  'city' => $city,
  'days' => $days,
  'events' => $events,
]);

