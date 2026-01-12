<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();

$validationSearchMap = [];
$resVS = $db->query("SELECT fa.application_id, fa.franchise_ref_number, fa.operator_name, c.coop_name, r.route_code, r.description AS route_description FROM franchise_applications fa LEFT JOIN coops c ON fa.coop_id = c.id LEFT JOIN lptrp_routes r ON r.id = fa.route_ids ORDER BY fa.submitted_at DESC LIMIT 200");
if ($resVS) {
  while ($row = $resVS->fetch_assoc()) {
    $appIdRow = (int)($row['application_id'] ?? 0);
    $ref = trim((string)($row['franchise_ref_number'] ?? ''));
    $coopNameRow = trim((string)($row['coop_name'] ?? ''));
    $operatorNameRow = trim((string)($row['operator_name'] ?? ''));
    $routeCodeRow = trim((string)($row['route_code'] ?? ''));
    $routeDescRow = trim((string)($row['route_description'] ?? ''));
    $routeLabelRow = $routeCodeRow !== '' ? $routeCodeRow . ' • ' . $routeDescRow : '';
    $nameLabel = $coopNameRow !== '' ? $coopNameRow : $operatorNameRow;
    $parts = [];
    if ($nameLabel !== '') {
      $parts[] = $nameLabel;
    }
    if ($routeLabelRow !== '') {
      $parts[] = $routeLabelRow;
    }
    $suffix = '';
    if (!empty($parts)) {
      $suffix = ' — ' . implode(' — ', $parts);
    }
    if ($appIdRow > 0) {
      $val = 'APP-' . str_pad($appIdRow, 4, '0', STR_PAD_LEFT);
      if (!isset($validationSearchMap[$val])) {
        $validationSearchMap[$val] = $val . $suffix;
      }
    }
    if ($ref !== '') {
      if (!isset($validationSearchMap[$ref])) {
        $validationSearchMap[$ref] = $ref . $suffix;
      }
    }
  }
}
$validationSearchOptions = [];
foreach ($validationSearchMap as $value => $label) {
  $validationSearchOptions[] = ['value' => $value, 'label' => $label];
}

$search = trim($_GET['q'] ?? '');
$app = null;
$violations30d = 0;
$inspectionFails = 0;
$activeCases = 0;

$resV = $db->query("SELECT COUNT(*) AS c FROM compliance_cases WHERE reported_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
if ($resV) {
  $violations30d = (int)($resV->fetch_assoc()['c'] ?? 0);
}

$resC = $db->query("SELECT COUNT(*) AS c FROM compliance_cases WHERE status = 'Open'");
if ($resC) {
  $activeCases = (int)($resC->fetch_assoc()['c'] ?? 0);
}

$resI = $db->query("SELECT COUNT(*) AS c FROM inspection_results WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND overall_status = 'Failed'");
if ($resI) {
  $inspectionFails = (int)($resI->fetch_assoc()['c'] ?? 0);
}

if ($search !== '') {
  $appId = null;
  if (preg_match('/^APP-(\d+)/i', $search, $m)) {
    $appId = (int)$m[1];
  } elseif (ctype_digit($search)) {
    $appId = (int)$search;
  }
  $sql = "SELECT fa.*, c.coop_name, r.route_code, r.description AS route_description, r.max_vehicle_capacity, r.current_vehicle_count 
          FROM franchise_applications fa 
          LEFT JOIN coops c ON fa.coop_id = c.id 
          LEFT JOIN lptrp_routes r ON r.id = fa.route_ids ";
  if ($appId !== null) {
    $sql .= "WHERE fa.application_id = ? OR fa.franchise_ref_number = ?";
    $stmt = $db->prepare($sql);
    if ($stmt) {
      $refParam = $search;
      $stmt->bind_param('is', $appId, $refParam);
      $stmt->execute();
      $res = $stmt->get_result();
      if ($res) {
        $app = $res->fetch_assoc();
      }
      $stmt->close();
    }
  } else {
    $sql .= "WHERE fa.franchise_ref_number = ?";
    $stmt = $db->prepare($sql);
    if ($stmt) {
      $stmt->bind_param('s', $search);
      $stmt->execute();
      $res = $stmt->get_result();
      if ($res) {
        $app = $res->fetch_assoc();
      }
      $stmt->close();
    }
  }
}

$trackNumber = '';
$franchiseRef = '';
$coopName = '';
$routeLabel = '';
$lptrpStatus = '';
$coopStatus = '';
$validationNotes = '';
$applicationId = 0;
$routeCapacityText = '';
$routeCapacityBadgeClass = 'px-2 py-1 rounded bg-slate-100 text-slate-700';

if ($app) {
  $applicationId = (int)($app['application_id'] ?? 0);
  $franchiseRef = (string)($app['franchise_ref_number'] ?? '');
  $trackNumber = $applicationId > 0 ? 'APP-' . $applicationId : '';
  $coopName = (string)($app['coop_name'] ?? '');
  $routeCode = (string)($app['route_code'] ?? '');
  $routeDesc = (string)($app['route_description'] ?? '');
  $routeLabel = $routeCode !== '' ? $routeCode . ' • ' . $routeDesc : (string)($app['route_ids'] ?? '');
  $lptrpStatus = (string)($app['lptrp_status'] ?? '');
  $coopStatus = (string)($app['coop_status'] ?? '');
  $validationNotes = (string)($app['validation_notes'] ?? '');
  $maxCap = (int)($app['max_vehicle_capacity'] ?? 0);
  $currentCap = (int)($app['current_vehicle_count'] ?? 0);
  $vehicles = (int)($app['vehicle_count'] ?? 0);
  if ($maxCap > 0) {
    $projected = $currentCap + $vehicles;
    if ($projected <= $maxCap) {
      $routeCapacityText = 'Within LPTRP limit: ' . $projected . ' / ' . $maxCap . ' vehicles after endorsement.';
      $routeCapacityBadgeClass = 'px-2 py-1 rounded bg-emerald-100 text-emerald-700';
    } else {
      $routeCapacityText = 'Over LPTRP capacity: ' . $projected . ' / ' . $maxCap . ' vehicles after endorsement.';
      $routeCapacityBadgeClass = 'px-2 py-1 rounded bg-rose-100 text-rose-700';
    }
  }
  $coopIdCurrent = (int)($app['coop_id'] ?? 0);
  if ($coopIdCurrent > 0) {
    $liveCoopStatus = null;
    $stmtCo = $db->prepare("SELECT consolidation_status FROM coops WHERE id=?");
    if ($stmtCo) {
      $stmtCo->bind_param('i', $coopIdCurrent);
      $stmtCo->execute();
      $resCo = $stmtCo->get_result();
      if ($resCo) {
        $rowCo = $resCo->fetch_assoc();
        if ($rowCo && isset($rowCo['consolidation_status'])) {
          $liveCoopStatus = (string)$rowCo['consolidation_status'];
        }
      }
      $stmtCo->close();
    }
    if ($liveCoopStatus !== null && $applicationId > 0) {
      $lines = preg_split("/\r\n|\n|\r/", (string)$validationNotes);
      if (!is_array($lines)) {
        $lines = [];
      }
      $filtered = [];
      foreach ($lines as $ln) {
        if (trim($ln) === '') {
          continue;
        }
        if (trim($ln) === 'Coop is not consolidated.') {
          continue;
        }
        $filtered[] = $ln;
      }
      if ($liveCoopStatus === 'Consolidated') {
        $coopStatus = 'Passed';
      } else {
        $coopStatus = 'Failed';
        $hasNote = false;
        foreach ($filtered as $ln) {
          if (trim($ln) === 'Coop is not consolidated.') {
            $hasNote = true;
            break;
          }
        }
        if (!$hasNote) {
          $filtered[] = 'Coop is not consolidated.';
        }
      }
      $validationNotes = implode("\n", $filtered);
      $stmtUpd = $db->prepare("UPDATE franchise_applications SET coop_status=?, validation_notes=? WHERE application_id=?");
      if ($stmtUpd) {
        $stmtUpd->bind_param('ssi', $coopStatus, $validationNotes, $applicationId);
        $stmtUpd->execute();
        $stmtUpd->close();
      }
    }
  }
}
?>
<div class="mx-1 mt-1 p-4 md:p-6 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 rounded-lg" id="module2-sub2-root">
  <h1 class="text-2xl font-bold mb-2">Validation, Endorsement & Compliance Engine</h1>
  <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">Search franchise applications, review automated validation results, and issue endorsements.</p>

  <div id="toast-container" class="fixed bottom-4 right-4 z-[100] flex flex-col gap-2 pointer-events-none"></div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="p-4 border rounded-lg dark:border-slate-700 bg-white dark:bg-slate-900">
      <h2 class="text-lg font-semibold mb-3">Validate Application</h2>
      <form id="validationSearchForm" class="space-y-3" method="GET">
        <input type="hidden" name="page" value="module2/submodule2">
        <input id="validationSearchInput" name="q" list="validationSearchList" value="<?php echo htmlspecialchars($search); ?>" class="w-full px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Tracking # (APP-0001) or Franchise Ref">
        <datalist id="validationSearchList">
          <?php foreach ($validationSearchOptions as $opt): ?>
            <option value="<?php echo htmlspecialchars($opt['value']); ?>"><?php echo htmlspecialchars($opt['label']); ?></option>
          <?php endforeach; ?>
        </datalist>
        <div id="validationQuickPreview" class="mt-1 text-xs text-slate-500"></div>
        <button type="submit" class="inline-flex items-center justify-center px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700">Load Application</button>
      </form>
      <div class="mt-4 text-sm space-y-2">
        <?php if ($app): ?>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs sm:text-sm">
            <div>
              <div class="text-slate-500">Tracking #</div>
              <div class="font-medium"><?php echo htmlspecialchars($trackNumber !== '' ? $trackNumber : '—'); ?></div>
            </div>
            <div>
              <div class="text-slate-500">Franchise Ref</div>
              <div class="font-medium"><?php echo htmlspecialchars($franchiseRef !== '' ? $franchiseRef : '—'); ?></div>
            </div>
            <div>
              <div class="text-slate-500">Cooperative</div>
              <div class="font-medium"><?php echo htmlspecialchars($coopName !== '' ? $coopName : '—'); ?></div>
            </div>
            <div>
              <div class="text-slate-500">Route</div>
              <div class="font-medium"><?php echo htmlspecialchars($routeLabel !== '' ? $routeLabel : '—'); ?></div>
            </div>
          </div>
          <div class="mt-3 flex flex-wrap gap-2 text-[11px]">
            <?php if ($lptrpStatus !== ''): ?>
              <?php
                $lpClass = 'px-2 py-1 rounded bg-slate-100 text-slate-700';
                if (strtoupper($lptrpStatus) === 'PASSED') {
                  $lpClass = 'px-2 py-1 rounded bg-emerald-100 text-emerald-700';
                } elseif (strtoupper($lptrpStatus) === 'FAILED') {
                  $lpClass = 'px-2 py-1 rounded bg-rose-100 text-rose-700';
                }
              ?>
              <span class="<?php echo $lpClass; ?>">LPTRP: <?php echo htmlspecialchars($lptrpStatus); ?></span>
            <?php endif; ?>
            <?php if ($coopStatus !== ''): ?>
              <?php
                $cpClass = 'px-2 py-1 rounded bg-slate-100 text-slate-700';
                if (strtoupper($coopStatus) === 'PASSED') {
                  $cpClass = 'px-2 py-1 rounded bg-emerald-100 text-emerald-700';
                } elseif (strtoupper($coopStatus) === 'FAILED') {
                  $cpClass = 'px-2 py-1 rounded bg-rose-100 text-rose-700';
                }
              ?>
              <span class="<?php echo $cpClass; ?>">Coop: <?php echo htmlspecialchars($coopStatus); ?></span>
            <?php endif; ?>
            <?php if ($routeCapacityText !== ''): ?>
              <span class="<?php echo $routeCapacityBadgeClass; ?>"><?php echo htmlspecialchars($routeCapacityText); ?></span>
            <?php endif; ?>
          </div>
          <?php if ($validationNotes !== ''): ?>
            <div class="mt-3 p-3 rounded bg-slate-50 dark:bg-slate-800 text-xs leading-relaxed max-h-32 overflow-y-auto">
              <?php echo nl2br(htmlspecialchars($validationNotes)); ?>
            </div>
          <?php else: ?>
            <div class="mt-3 text-xs text-slate-500">No recorded validation notes for this application.</div>
          <?php endif; ?>
        <?php elseif ($search !== ''): ?>
          <div class="mt-2 text-xs text-rose-600">No application found for "<?php echo htmlspecialchars($search); ?>".</div>
        <?php else: ?>
          <div class="mt-2 text-xs text-slate-500">Enter a tracking number or franchise reference to begin validation.</div>
        <?php endif; ?>
      </div>
    </div>

    <div class="p-4 border rounded-lg dark:border-slate-700 bg-white dark:bg-slate-900">
      <h2 class="text-lg font-semibold mb-3">Generate Endorsement / Permit</h2>
      <?php if ($app): ?>
        <form id="endorsementForm" class="space-y-3">
          <input type="hidden" name="application_id" value="<?php echo $applicationId > 0 ? $applicationId : ''; ?>">
          <div class="text-xs text-slate-600 dark:text-slate-300">
            <div class="font-medium"><?php echo htmlspecialchars($trackNumber !== '' ? $trackNumber : $franchiseRef); ?></div>
            <div class="mt-0.5"><?php echo htmlspecialchars($coopName !== '' ? $coopName : ''); ?></div>
          </div>
          <input name="officer_name" class="w-full px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Issued by">
          <textarea name="notes" rows="3" class="w-full px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Notes or conditions (optional)"></textarea>
          <button type="button" id="endorsementSubmit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg w-full md:w-auto text-sm font-medium hover:bg-emerald-700">Generate Endorsement</button>
          <div id="endorsementStatus" class="mt-2 text-xs text-slate-500"></div>
        </form>
      <?php else: ?>
        <p class="text-sm text-slate-500 mb-3">Load an application on the left to enable endorsement.</p>
        <button type="button" class="px-4 py-2 bg-slate-200 text-slate-500 rounded-lg w-full md:w-auto text-sm cursor-not-allowed" disabled>Generate Endorsement</button>
      <?php endif; ?>
    </div>
  </div>

  <div id="compliance" class="p-4 border rounded-lg dark:border-slate-700 mt-6 bg-white dark:bg-slate-900">
    <h2 class="text-lg font-semibold mb-3">Compliance Snapshot</h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="p-3 border rounded dark:border-slate-700">
        <div class="text-sm text-slate-500">Violations (30d)</div>
        <div class="text-2xl font-bold"><?php echo $violations30d; ?></div>
      </div>
      <div class="p-3 border rounded dark:border-slate-700">
        <div class="text-sm text-slate-500">Inspection Failures (30d)</div>
        <div class="text-2xl font-bold"><?php echo $inspectionFails; ?></div>
      </div>
      <div class="p-3 border rounded dark:border-slate-700">
        <div class="text-sm text-slate-500">Active Compliance Cases</div>
        <div class="text-2xl font-bold"><?php echo $activeCases; ?></div>
      </div>
    </div>
    <div class="mt-4 flex flex-wrap gap-2">
      <a href="?page=module3/submodule2" class="px-3 py-2 border rounded text-sm text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700">
        Open Compliance Module
      </a>
    </div>
  </div>
</div>

<script>
(function(){
  var input = document.getElementById('validationSearchInput');
  var preview = document.getElementById('validationQuickPreview');
  var options = <?php echo json_encode($validationSearchOptions); ?>;
  var map = {};
  for (var i = 0; i < options.length; i++) {
    map[options[i].value] = options[i].label;
  }
  function renderPreview(v) {
    if (!preview) return;
    var key = (v || '').trim().toUpperCase();
    var label = map[key] || '';
    if (!label) {
      preview.textContent = '';
      return;
    }
    preview.textContent = label;
  }
  if (input) {
    input.addEventListener('input', function(){
      renderPreview(input.value);
    });
    renderPreview(input.value);
  }
})();
(function(){
  var btn = document.getElementById('endorsementSubmit');
  var form = document.getElementById('endorsementForm');
  var statusEl = document.getElementById('endorsementStatus');
  if (!btn || !form || !statusEl) return;
  btn.addEventListener('click', function(){
    if (btn.disabled) return;
    var appId = form.elements['application_id'] ? form.elements['application_id'].value : '';
    var officer = form.elements['officer_name'] ? form.elements['officer_name'].value.trim() : '';
    var notes = form.elements['notes'] ? form.elements['notes'].value.trim() : '';
    if (!appId) {
      statusEl.textContent = 'Load an application first.';
      statusEl.className = 'mt-2 text-xs text-red-600';
      return;
    }
    var fd = new FormData();
    fd.append('application_id', appId);
    if (officer !== '') fd.append('officer_name', officer);
    if (notes !== '') fd.append('notes', notes);
    btn.disabled = true;
    statusEl.textContent = 'Generating endorsement...';
    statusEl.className = 'mt-2 text-xs text-slate-600';
    fetch('api/module2/endorse_app.php', { method: 'POST', body: fd })
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (data && data.ok) {
          var msg = data.message || 'Endorsement issued successfully.';
          statusEl.textContent = msg;
          statusEl.className = 'mt-2 text-xs text-emerald-600';
          if (window.showToast) {
            window.showToast(msg);
          }
          setTimeout(function(){ window.location.reload(); }, 900);
        } else {
          var errMsg = data && data.error ? data.error : 'Unable to issue endorsement.';
          statusEl.textContent = errMsg;
          statusEl.className = 'mt-2 text-xs text-red-600';
          if (window.showToast) {
            window.showToast(errMsg, 'error');
          }
        }
      })
      .catch(function(err){
        var msg = 'Error: ' + err.message;
        statusEl.textContent = msg;
        statusEl.className = 'mt-2 text-xs text-red-600';
        if (window.showToast) {
          window.showToast(msg, 'error');
        }
      })
      .finally(function(){
        btn.disabled = false;
      });
  });
})();
</script>
