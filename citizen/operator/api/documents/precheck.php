<?php
require_once __DIR__ . '/../common.php';
$plate = strtoupper(trim($_POST['plate_number'] ?? ''));
$files = ['or' => null, 'cr' => null];
$result = ['readable' => true, 'complete' => false, 'labels_ok' => true, 'issues' => []];
foreach (['or','cr'] as $k) {
  if (!isset($_FILES[$k]) || $_FILES[$k]['error'] === UPLOAD_ERR_NO_FILE) continue;
  if ($_FILES[$k]['error'] !== UPLOAD_ERR_OK) { $result['issues'][] = $k . '_upload_error'; continue; }
  $ext = strtolower(pathinfo($_FILES[$k]['name'], PATHINFO_EXTENSION));
  if (!in_array($ext, ['jpg','jpeg','png','pdf'])) { $result['issues'][] = $k . '_invalid_type'; $result['labels_ok'] = false; }
  $size = (int)($_FILES[$k]['size'] ?? 0);
  if ($size < 10240) { $result['issues'][] = $k . '_too_small'; $result['readable'] = false; }
  $name = $_FILES[$k]['name'] ?? '';
  if ($k === 'or' && stripos($name, 'or') === false) { $result['labels_ok'] = false; }
  if ($k === 'cr' && stripos($name, 'cr') === false) { $result['labels_ok'] = false; }
  $files[$k] = true;
}
if ($files['or'] && $files['cr']) { $result['complete'] = true; }
json_ok(['precheck' => $result, 'plate_number' => $plate]);
