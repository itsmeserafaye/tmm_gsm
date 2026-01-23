<?php
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['dashboard.view','module1.read','module2.read','module3.read','module4.read','module5.read']);
require_once __DIR__ . '/../../includes/db.php';
$db = db();

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';

$res = $db->query("SELECT
  COUNT(*) AS c,
  AVG((pu_1+pu_2+pu_3+pu_4)/4) AS pu_avg,
  AVG((peou_1+peou_2+peou_3+peou_4)/4) AS peou_avg
FROM tam_survey_responses");
$row = $res ? $res->fetch_assoc() : null;
$count = (int)($row['c'] ?? 0);
$puAvg = (float)($row['pu_avg'] ?? 0);
$peouAvg = (float)($row['peou_avg'] ?? 0);

$items = [];
$stmt = $db->prepare("SELECT submitted_at, respondent_type, module_used,
  pu_1, pu_2, pu_3, pu_4, peou_1, peou_2, peou_3, peou_4
  FROM tam_survey_responses
  ORDER BY submitted_at DESC
  LIMIT 50");
if ($stmt) {
  $stmt->execute();
  $r = $stmt->get_result();
  while ($x = $r->fetch_assoc()) $items[] = $x;
  $stmt->close();
}
?>

<div class="mx-auto max-w-6xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-6">
  <div class="border-b border-slate-200 dark:border-slate-700 pb-6 flex items-end justify-between gap-4">
    <div>
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">TAM Results</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Aggregated perceived usefulness and ease of use</p>
    </div>
    <a href="?page=research/tam_survey" class="px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold">Add Response</a>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Responses</div>
      <div class="mt-2 text-3xl font-black text-slate-900 dark:text-white"><?php echo number_format($count); ?></div>
    </div>
    <div class="p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">PU Average</div>
      <div class="mt-2 text-3xl font-black text-slate-900 dark:text-white"><?php echo $count ? number_format($puAvg, 2) : '—'; ?></div>
    </div>
    <div class="p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
      <div class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">PEOU Average</div>
      <div class="mt-2 text-3xl font-black text-slate-900 dark:text-white"><?php echo $count ? number_format($peouAvg, 2) : '—'; ?></div>
    </div>
  </div>

  <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
      <div class="font-black text-slate-900 dark:text-white">Latest Responses</div>
    </div>
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-sm">
        <thead class="bg-slate-50 dark:bg-slate-900/40">
          <tr class="text-left text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">
            <th class="py-3 px-4">Time</th>
            <th class="py-3 px-4">Type</th>
            <th class="py-3 px-4">Module</th>
            <th class="py-3 px-4">PU Avg</th>
            <th class="py-3 px-4">PEOU Avg</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
          <?php if (!$items): ?>
            <tr><td colspan="5" class="py-10 px-6 text-sm text-slate-500 dark:text-slate-400 italic">No responses yet.</td></tr>
          <?php endif; ?>
          <?php foreach ($items as $it): ?>
            <?php
              $pu = ((int)$it['pu_1'] + (int)$it['pu_2'] + (int)$it['pu_3'] + (int)$it['pu_4']) / 4.0;
              $peou = ((int)$it['peou_1'] + (int)$it['peou_2'] + (int)$it['peou_3'] + (int)$it['peou_4']) / 4.0;
            ?>
            <tr>
              <td class="py-3 px-4 text-slate-600 dark:text-slate-300 font-semibold"><?php echo htmlspecialchars((string)$it['submitted_at']); ?></td>
              <td class="py-3 px-4 text-slate-600 dark:text-slate-300 font-semibold"><?php echo htmlspecialchars((string)$it['respondent_type']); ?></td>
              <td class="py-3 px-4 text-slate-600 dark:text-slate-300 font-semibold"><?php echo htmlspecialchars((string)$it['module_used']); ?></td>
              <td class="py-3 px-4 font-black text-slate-900 dark:text-white"><?php echo number_format($pu, 2); ?></td>
              <td class="py-3 px-4 font-black text-slate-900 dark:text-white"><?php echo number_format($peou, 2); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

