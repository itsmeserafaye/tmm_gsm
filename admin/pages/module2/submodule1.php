<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();

$coopsRes = $db->query("SELECT id, coop_name, consolidation_status FROM coops ORDER BY coop_name");
$routesRes = $db->query("SELECT id, route_code, description, max_vehicle_capacity, current_vehicle_count FROM lptrp_routes ORDER BY route_code");

$coops = [];
if ($coopsRes) {
  while ($row = $coopsRes->fetch_assoc()) {
    $coops[] = $row;
  }
}

$routes = [];
if ($routesRes) {
  while ($row = $routesRes->fetch_assoc()) {
    $routes[] = $row;
  }
}

$q = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$sql = "SELECT fa.*, o.full_name AS operator, c.coop_name, r.route_code, r.description AS route_description 
        FROM franchise_applications fa 
        LEFT JOIN operators o ON fa.operator_id = o.id 
        LEFT JOIN coops c ON fa.coop_id = c.id 
        LEFT JOIN lptrp_routes r ON r.id = fa.route_ids";

$conds = [];
$params = [];
$types = '';

if ($q !== '') {
  $conds[] = "(fa.franchise_ref_number LIKE ? OR o.full_name LIKE ? OR c.coop_name LIKE ?)";
  $like = "%$q%";
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
  $types .= 'sss';
}

if ($statusFilter !== '' && $statusFilter !== 'All') {
  $conds[] = "fa.status = ?";
  $params[] = $statusFilter;
  $types .= 's';
}

if ($conds) {
  $sql .= " WHERE " . implode(" AND ", $conds);
}

$sql .= " ORDER BY fa.submitted_at DESC LIMIT 50";

if ($params) {
  $stmt = $db->prepare($sql);
  if ($stmt && $types !== '') {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $appsRes = $stmt->get_result();
  } else {
    $appsRes = false;
  }
} else {
  $appsRes = $db->query($sql);
}
?>
<div class="mx-1 mt-1 p-4 md:p-6 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 rounded-lg">
  <h1 class="text-2xl font-bold mb-2">Franchise Application & Cooperative Management</h1>
  <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">Intake and tracking of franchise endorsement applications, cooperative profiles, consolidation status, and documentation.</p>

  <div class="p-4 border rounded-lg dark:border-slate-700 mb-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
      <h2 class="text-lg font-semibold">Submit Application</h2>
      <p class="text-xs text-slate-500 dark:text-slate-400 md:text-right">Runs automated checks on cooperative consolidation and LPTRP route capacity before queuing for validation.</p>
    </div>
    <form id="module2AppForm" class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm mb-1">Cooperative</label>
        <select name="coop_id" class="w-full px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700">
          <option value="">Select cooperative</option>
          <?php foreach ($coops as $c): ?>
            <?php
              $label = $c['coop_name'] ?? 'Coop';
              $status = $c['consolidation_status'] ?? '';
              if ($status !== '') {
                $label .= " ({$status})";
              }
            ?>
            <option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($label); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm mb-1">Representative name</label>
        <input name="rep_name" class="w-full px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Coop representative">
      </div>
      <div>
        <label class="block text-sm mb-1">LTFRB franchise reference</label>
        <input name="franchise_ref" class="w-full px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="2024-00123">
      </div>
      <div>
        <label class="block text-sm mb-1">Vehicle count requested</label>
        <input name="vehicle_count" type="number" min="1" class="w-full px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="e.g. 10">
      </div>
      <div>
        <label class="block text-sm mb-1">Proposed LPTRP route</label>
        <select name="route_id" class="w-full px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700">
          <option value="">Select route</option>
          <?php foreach ($routes as $r): ?>
            <?php
              $cap = (int)($r['max_vehicle_capacity'] ?? 0);
              $curr = (int)($r['current_vehicle_count'] ?? 0);
              $label = ($r['route_code'] ?? 'Route') . ' • ' . ($r['description'] ?? '') . " ({$curr}/{$cap})";
            ?>
            <option value="<?php echo (int)$r['id']; ?>"><?php echo htmlspecialchars($label); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm mb-1">Fee receipt ID (optional)</label>
        <input name="fee_receipt" class="w-full px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="OR / receipt reference">
      </div>
      <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label class="block text-sm mb-1">LTFRB document</label>
          <input name="doc_ltfrb" type="file" accept=".pdf,.jpg,.jpeg,.png" class="w-full px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 text-sm file:px-3 file:py-1.5 file:mr-3 file:rounded-md file:border-0 file:bg-emerald-500 file:text-white file:text-xs file:font-medium hover:file:bg-emerald-600 file:cursor-pointer">
          <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Upload the LTFRB franchise decision or order as a clear PDF or image.</p>
        </div>
        <div>
          <label class="block text-sm mb-1">Coop registration</label>
          <input name="doc_coop" type="file" accept=".pdf,.jpg,.jpeg,.png" class="w-full px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 text-sm file:px-3 file:py-1.5 file:mr-3 file:rounded-md file:border-0 file:bg-emerald-500 file:text-white file:text-xs file:font-medium hover:file:bg-emerald-600 file:cursor-pointer">
          <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Upload the cooperative’s registration or accreditation document.</p>
        </div>
        <div>
          <label class="block text-sm mb-1">Member vehicles list</label>
          <input name="doc_members" type="file" accept=".pdf,.jpg,.jpeg,.png" class="w-full px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 text-sm file:px-3 file:py-1.5 file:mr-3 file:rounded-md file:border-0 file:bg-emerald-500 file:text-white file:text-xs file:font-medium hover:file:bg-emerald-600 file:cursor-pointer">
          <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Upload a PDF, spreadsheet, or document listing the member vehicles.</p>
        </div>
      </div>
      <?php if (getenv('TMM_AV_SCANNER')): ?>
        <p class="md:col-span-2 text-[11px] text-slate-500 dark:text-slate-400">Files are scanned for viruses when uploaded.</p>
      <?php endif; ?>
      <div class="md:col-span-2 flex flex-col md:flex-row md:items-center md:justify-between gap-2">
        <button type="button" id="module2SubmitBtn" class="px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white rounded-lg w-full md:w-auto">Create Application</button>
        <div id="module2AppStatus" class="mt-1 text-xs text-slate-500"></div>
      </div>
    </form>
  </div>

  <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="p-4 border rounded-lg dark:border-slate-700">
      <h2 class="text-base font-semibold mb-2">Cooperative Directory</h2>
      <p class="text-xs text-slate-500 dark:text-slate-400 mb-3">Review cooperatives and update their consolidation status after legal consolidation is complete.</p>
      <div class="overflow-x-auto max-h-72">
        <table class="min-w-full text-xs">
          <thead class="bg-slate-50 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700">
            <tr class="text-left text-slate-600 dark:text-slate-300">
              <th class="py-2 px-3">Cooperative</th>
              <th class="py-2 px-3">Status</th>
              <th class="py-2 px-3 text-right">Update</th>
            </tr>
          </thead>
          <tbody class="divide-y dark:divide-slate-700">
            <?php if (!empty($coops)): ?>
              <?php foreach ($coops as $c): ?>
                <?php $currentStatus = $c['consolidation_status'] ?? 'Not Consolidated'; ?>
                <tr>
                  <td class="py-2 px-3 align-middle">
                    <div class="text-sm font-medium text-slate-800 dark:text-slate-100"><?php echo htmlspecialchars($c['coop_name'] ?? ''); ?></div>
                  </td>
                  <td class="py-2 px-3 align-middle">
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium
                      <?php
                        if ($currentStatus === 'Consolidated') {
                          echo 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300';
                        } elseif ($currentStatus === 'In Progress') {
                          echo 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300';
                        } else {
                          echo 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300';
                        }
                      ?>
                    ">
                      <?php echo htmlspecialchars($currentStatus); ?>
                    </span>
                  </td>
                  <td class="py-2 px-3 text-right align-middle">
                    <form class="inline-flex items-center gap-1 text-xs" method="POST" action="/tmm/admin/api/module1/save_coop.php">
                      <input type="hidden" name="coop_name" value="<?php echo htmlspecialchars($c['coop_name'] ?? '', ENT_QUOTES); ?>">
                      <input type="hidden" name="address" value="KEEP_EXISTING">
                      <input type="hidden" name="chairperson_name" value="KEEP_EXISTING">
                      <input type="hidden" name="lgu_approval_number" value="KEEP_EXISTING">
                      <select name="consolidation_status" class="px-2 py-1 border rounded bg-white dark:bg-slate-900 dark:border-slate-700" onchange="window.updateCoopStatus(this);">
                        <option value="Not Consolidated" <?php echo $currentStatus === 'Not Consolidated' ? 'selected' : ''; ?>>Not Consolidated</option>
                        <option value="In Progress" <?php echo $currentStatus === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="Consolidated" <?php echo $currentStatus === 'Consolidated' ? 'selected' : ''; ?>>Consolidated</option>
                      </select>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="3" class="py-4 px-3 text-center text-xs text-slate-500">No cooperatives found. Register a cooperative in Module 1.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-3">
    <h2 class="text-lg font-semibold">Recent Applications</h2>
    <form id="module2FilterForm" method="GET" class="flex flex-col md:flex-row gap-2 md:items-center">
      <input type="hidden" name="page" value="module2/submodule1">
      <div class="relative">
        <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
        <input name="q" list="module2SearchList" value="<?php echo htmlspecialchars($q); ?>" class="pl-9 pr-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 text-sm focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 outline-none transition-all" placeholder="Search Ref or Operator...">
        <?php
          $module2SearchOptions = [];
          $module2ResSearch = $db->query("SELECT DISTINCT franchise_ref_number, operator_name FROM franchise_applications ORDER BY submitted_at DESC LIMIT 100");
          if ($module2ResSearch) {
            while ($r = $module2ResSearch->fetch_assoc()) {
              $ref = trim((string)($r['franchise_ref_number'] ?? ''));
              $opn = trim((string)($r['operator_name'] ?? ''));
              if ($ref !== '') $module2SearchOptions[$ref] = true;
              if ($opn !== '') $module2SearchOptions[$opn] = true;
            }
          }
        ?>
        <datalist id="module2SearchList">
          <?php foreach (array_keys($module2SearchOptions) as $opt): ?>
            <option value="<?php echo htmlspecialchars($opt); ?>"></option>
          <?php endforeach; ?>
        </datalist>
      </div>
      <select name="status" class="px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 text-sm focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 outline-none transition-all">
        <option value="">Status</option>
        <?php
          $statuses = ['Pending','Under Review','Endorsed','Rejected'];
          foreach ($statuses as $st):
        ?>
          <option value="<?php echo $st; ?>" <?php echo $statusFilter === $st ? 'selected' : ''; ?>><?php echo $st; ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>

  <div class="overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="hidden md:table-header-group">
        <tr class="text-left text-slate-600 dark:text-slate-300">
          <th class="py-2 px-3">Tracking #</th>
          <th class="py-2 px-3">Cooperative</th>
          <th class="py-2 px-3">Franchise Ref</th>
          <th class="py-2 px-3">Route</th>
          <th class="py-2 px-3">Vehicle Count</th>
          <th class="py-2 px-3">Status</th>
          <th class="py-2 px-3">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y dark:divide-slate-700">
        <?php if ($appsRes && $appsRes->num_rows > 0): ?>
          <?php while ($row = $appsRes->fetch_assoc()): ?>
            <?php
              $ref = $row['franchise_ref_number'] ?? '';
              $track = 'APP-' . (int)($row['application_id'] ?? 0);
              $status = $row['status'] ?? 'Pending';
              $badgeClass = 'px-2 py-1 rounded bg-slate-100 text-slate-700';
              if ($status === 'Endorsed') {
                $badgeClass = 'px-2 py-1 rounded bg-emerald-100 text-emerald-700';
              } elseif ($status === 'Pending') {
                $badgeClass = 'px-2 py-1 rounded bg-amber-100 text-amber-700';
              } elseif ($status === 'Under Review') {
                $badgeClass = 'px-2 py-1 rounded bg-blue-100 text-blue-700';
              } elseif ($status === 'Rejected') {
                $badgeClass = 'px-2 py-1 rounded bg-rose-100 text-rose-700';
              }
              $lpStatus = $row['lptrp_status'] ?? '';
              $coopStatus = $row['coop_status'] ?? '';
              $routeLabel = ($row['route_code'] ?? '') !== '' ? ($row['route_code'] . ' • ' . ($row['route_description'] ?? '')) : ($row['route_ids'] ?? '');
              $openHref = $ref !== '' ? '?page=module1/submodule2&q=' . urlencode($ref) : '';
            ?>
            <tr class="grid grid-cols-1 md:table-row gap-2 md:gap-0 p-2 md:p-0 hover:bg-slate-50 dark:hover:bg-slate-800/60 transition-colors">
              <td class="py-2 px-3">
                <span class="md:hidden font-semibold">Tracking #: </span><?php echo htmlspecialchars($track); ?>
              </td>
              <td class="py-2 px-3">
                <span class="md:hidden font-semibold">Cooperative: </span><?php echo htmlspecialchars($row['coop_name'] ?? '—'); ?>
              </td>
              <td class="py-2 px-3">
                <span class="md:hidden font-semibold">Franchise Ref: </span><?php echo htmlspecialchars($ref !== '' ? $ref : '—'); ?>
              </td>
              <td class="py-2 px-3">
                <span class="md:hidden font-semibold">Route: </span><?php echo htmlspecialchars($routeLabel !== '' ? $routeLabel : '—'); ?>
              </td>
              <td class="py-2 px-3">
                <span class="md:hidden font-semibold">Vehicle Count: </span><?php echo (int)($row['vehicle_count'] ?? 0); ?>
              </td>
              <td class="py-2 px-3">
                <span class="md:hidden font-semibold">Status: </span>
                <span class="<?php echo $badgeClass; ?>"><?php echo htmlspecialchars($status); ?></span>
                <div class="mt-1 text-[11px] text-slate-500 space-x-2">
                  <?php if ($lpStatus !== ''): ?>
                    <span>LPTRP: <?php echo htmlspecialchars($lpStatus); ?></span>
                  <?php endif; ?>
                  <?php if ($coopStatus !== ''): ?>
                    <span>Coop: <?php echo htmlspecialchars($coopStatus); ?></span>
                  <?php endif; ?>
                </div>
              </td>
              <td class="py-2 px-3 space-y-2 md:space-x-2 md:space-y-0 flex flex-col md:flex-row">
                <?php if ($openHref !== ''): ?>
                  <a href="<?php echo htmlspecialchars($openHref); ?>" class="px-2 py-1 border rounded w-full md:w-auto text-center text-xs">Open in Validation</a>
                <?php else: ?>
                  <span class="px-2 py-1 border rounded w-full md:w-auto text-center text-xs text-slate-400">No reference</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr>
            <td colspan="7" class="py-6 px-3 text-center text-sm text-slate-500">No franchise applications found for the current filters.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
        </div>
      </div>
    </div>
  </div>
<script>
(function() {
  var form = document.getElementById('module2AppForm');
  var btn = document.getElementById('module2SubmitBtn');
  var statusEl = document.getElementById('module2AppStatus');
  if (!form || !btn || !statusEl) return;

  btn.addEventListener('click', function() {
    if (btn.disabled) return;
    var coopId = form.elements['coop_id'] ? form.elements['coop_id'].value : '';
    var repName = form.elements['rep_name'] ? form.elements['rep_name'].value : '';
    var franchiseRef = form.elements['franchise_ref'] ? form.elements['franchise_ref'].value : '';
    var vehicleCount = form.elements['vehicle_count'] ? form.elements['vehicle_count'].value : '';
    var routeId = form.elements['route_id'] ? form.elements['route_id'].value : '';
    var feeReceipt = form.elements['fee_receipt'] ? form.elements['fee_receipt'].value : '';
    var docLtfrbInput = form.querySelector('input[name="doc_ltfrb"]');
    var docCoopInput = form.querySelector('input[name="doc_coop"]');
    var docMembersInput = form.querySelector('input[name="doc_members"]');

    var fd = new FormData();
    fd.append('coop_id', coopId);
    fd.append('rep_name', repName);
    fd.append('franchise_ref', franchiseRef);
    fd.append('vehicle_count', vehicleCount);
    fd.append('route_id', routeId);
    fd.append('fee_receipt', feeReceipt);

    btn.disabled = true;
    statusEl.textContent = 'Submitting application...';
    statusEl.className = 'mt-1 text-xs text-slate-600';

    fetch('api/module2/save_application.php', {
      method: 'POST',
      body: fd
    })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data && data.ok) {
          var appId = data.application_id || 0;
          var hasLtfrb = docLtfrbInput && docLtfrbInput.files && docLtfrbInput.files.length > 0;
          var hasCoop = docCoopInput && docCoopInput.files && docCoopInput.files.length > 0;
          var hasMembers = docMembersInput && docMembersInput.files && docMembersInput.files.length > 0;
          var hasAnyDocs = hasLtfrb || hasCoop || hasMembers;

          if (appId && hasAnyDocs) {
            statusEl.textContent = 'Application saved. Uploading documents...';
            statusEl.className = 'mt-1 text-xs text-slate-600';

            var docsFd = new FormData();
            docsFd.append('application_id', appId);
            if (hasLtfrb) {
              docsFd.append('doc_ltfrb', docLtfrbInput.files[0]);
            }
            if (hasCoop) {
              docsFd.append('doc_coop', docCoopInput.files[0]);
            }
            if (hasMembers) {
              docsFd.append('doc_members', docMembersInput.files[0]);
            }

            return fetch('api/module2/upload_app_docs.php', {
              method: 'POST',
              body: docsFd
            })
              .then(function(r) { return r.json(); })
              .then(function(docRes) {
                var hasErrors = docRes && Array.isArray(docRes.errors) && docRes.errors.length > 0;
                if (hasErrors) {
                  statusEl.textContent = 'Application submitted. Some documents had issues: ' + docRes.errors.join('; ');
                  statusEl.className = 'mt-1 text-xs text-amber-600';
                } else {
                  statusEl.textContent = 'Application and documents submitted successfully.';
                  statusEl.className = 'mt-1 text-xs text-emerald-600';
                }
                form.reset();
                setTimeout(function() {
                  window.location.reload();
                }, 900);
              })
              .catch(function(err) {
                statusEl.textContent = 'Application saved, but document upload failed: ' + err.message;
                statusEl.className = 'mt-1 text-xs text-amber-600';
                setTimeout(function() {
                  window.location.reload();
                }, 1200);
              })
              .finally(function() {
                btn.disabled = false;
              });
          } else {
            statusEl.textContent = data.message || 'Application submitted.';
            statusEl.className = 'mt-1 text-xs text-emerald-600';
            form.reset();
            setTimeout(function() {
              window.location.reload();
            }, 800);
            btn.disabled = false;
          }
        } else {
          statusEl.textContent = (data && data.error) ? data.error : 'Unable to submit application.';
          statusEl.className = 'mt-1 text-xs text-red-600';
          btn.disabled = false;
        }
      })
      .catch(function(err) {
        statusEl.textContent = 'Error: ' + err.message;
        statusEl.className = 'mt-1 text-xs text-red-600';
        btn.disabled = false;
      });
  });
})();
(function(){
  var form = document.getElementById('module2FilterForm');
  if (!form) return;
  var qInput = form.querySelector('input[name="q"]');
  var statusSelect = form.querySelector('select[name="status"]');
  var debounceTimer = null;
  function scheduleSubmit() {
    if (!form) return;
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(function(){ form.submit(); }, 400);
  }
  if (qInput) {
    qInput.addEventListener('input', scheduleSubmit);
  }
  if (statusSelect) {
    statusSelect.addEventListener('change', function(){
      form.submit();
    });
  }
})();

window.updateCoopStatus = function(el) {
  var form = el && el.closest ? el.closest('form') : null;
  if (!form) return false;
  var fd = new FormData(form);
  var name = fd.get('coop_name') || '';
  var status = fd.get('consolidation_status') || '';
  if (name === '' || status === '') return false;
  if (el && el.disabled) return false;
  if (el) el.disabled = true;
  fetch(form.action, { method: 'POST', body: fd })
    .then(function(r){ return r.json(); })
    .then(function(data){
      if (!data || data.ok === false) {
        var err = data && data.error ? data.error : 'Failed to update cooperative';
        if (window.showToast) window.showToast(err, 'error');
      } else {
        if (window.showToast) window.showToast('Cooperative updated', 'success');
        window.location.reload();
      }
    })
    .catch(function(err){
      if (window.showToast) window.showToast('Error: ' + err.message, 'error');
    })
    .finally(function(){
      if (el) el.disabled = false;
    });
  return false;
};
</script>
