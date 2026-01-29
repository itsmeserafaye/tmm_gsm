<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('module5.assign_vehicle');

require_once __DIR__ . '/../../includes/db.php';
$db = db();

$prefillTerminalId = (int)($_GET['terminal_id'] ?? 0);

$schema = '';
$schRes = $db->query("SELECT DATABASE() AS db");
if ($schRes) {
  $schema = (string) (($schRes->fetch_assoc()['db'] ?? '') ?: '');
}
function tmm_has_col(mysqli $db, string $schema, string $table, string $col): bool
{
  if ($schema === '')
    return false;
  $stmt = $db->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1");
  if (!$stmt)
    return false;
  $stmt->bind_param('sss', $schema, $table, $col);
  $stmt->execute();
  $res = $stmt->get_result();
  $ok = (bool) ($res && $res->fetch_row());
  $stmt->close();
  return $ok;
}

function tmm_has_table(mysqli $db, string $schema, string $table): bool
{
  if ($schema === '')
    return false;
  $stmt = $db->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=? LIMIT 1");
  if (!$stmt)
    return false;
  $stmt->bind_param('ss', $schema, $table);
  $stmt->execute();
  $res = $stmt->get_result();
  $ok = (bool) ($res && $res->fetch_row());
  $stmt->close();
  return $ok;
}

$terminals = [];
$resT = $db->query("SELECT t.id, t.name, t.capacity, GROUP_CONCAT(DISTINCT r.vehicle_type) AS allowed_types
FROM terminals t
LEFT JOIN terminal_routes tr ON tr.terminal_id=t.id
LEFT JOIN routes r ON r.route_id=tr.route_id OR r.route_code=tr.route_id
WHERE t.type <> 'Parking'
GROUP BY t.id
ORDER BY t.name ASC
LIMIT 500");
if ($resT) while ($r = $resT->fetch_assoc()) $terminals[] = $r;

$vehicles = [];
$vehDocsHasVehicleId = tmm_has_col($db, $schema, 'vehicle_documents', 'vehicle_id');
$vehDocsHasPlate = tmm_has_col($db, $schema, 'vehicle_documents', 'plate_number');
$vehDocsTypeCol = tmm_has_col($db, $schema, 'vehicle_documents', 'doc_type') ? 'doc_type' : (tmm_has_col($db, $schema, 'vehicle_documents', 'document_type') ? 'document_type' : (tmm_has_col($db, $schema, 'vehicle_documents', 'type') ? 'type' : ''));
$vehDocsVerifiedCol = tmm_has_col($db, $schema, 'vehicle_documents', 'is_verified') ? 'is_verified' : (tmm_has_col($db, $schema, 'vehicle_documents', 'verified') ? 'verified' : '');

$useVehDocs = ($vehDocsTypeCol !== '' && $vehDocsVerifiedCol !== '' && ($vehDocsHasVehicleId || $vehDocsHasPlate));
$useLegacyDocs = tmm_has_col($db, $schema, 'documents', 'plate_number') && tmm_has_col($db, $schema, 'documents', 'type') && tmm_has_col($db, $schema, 'documents', 'verified');

$sqlV = "SELECT id, plate_number, operator_id, inspection_status, vehicle_type
         FROM vehicles
         WHERE COALESCE(plate_number,'') <> ''
           AND operator_id IS NOT NULL AND operator_id>0
           AND COALESCE(record_status,'') <> 'Archived'
           AND COALESCE(vehicle_type,'') <> ''";
$vehDocsCond = '';
if ($useVehDocs) {
  $idCol = $vehDocsHasVehicleId ? 'vehicle_id' : 'plate_number';
  $idExpr = $vehDocsHasVehicleId ? 'vehicles.id' : 'vehicles.plate_number';
  $vehDocsCond = "(EXISTS (SELECT 1 FROM vehicle_documents vd WHERE vd.$idCol=$idExpr AND UPPER(vd.$vehDocsTypeCol) IN ('CR','ORCR') AND COALESCE(vd.$vehDocsVerifiedCol,0)=1)
    AND EXISTS (SELECT 1 FROM vehicle_documents vd2 WHERE vd2.$idCol=$idExpr AND UPPER(vd2.$vehDocsTypeCol) IN ('OR','ORCR') AND COALESCE(vd2.$vehDocsVerifiedCol,0)=1))";
}
$legacyCond = '';
if ($useLegacyDocs) {
  $legacyCond = "(EXISTS (SELECT 1 FROM documents d WHERE d.plate_number=vehicles.plate_number AND d.type IN ('cr','orcr') AND COALESCE(d.verified,0)=1)
    AND EXISTS (SELECT 1 FROM documents d2 WHERE d2.plate_number=vehicles.plate_number AND d2.type IN ('or','orcr') AND COALESCE(d2.verified,0)=1))";
}
if ($vehDocsCond !== '' && $legacyCond !== '') {
  $sqlV .= " AND ($vehDocsCond OR $legacyCond)";
} elseif ($vehDocsCond !== '') {
  $sqlV .= " AND $vehDocsCond";
} elseif ($legacyCond !== '') {
  $sqlV .= " AND $legacyCond";
}

$sqlV .= " ORDER BY plate_number ASC LIMIT 1500";

$resV = $db->query($sqlV);
if ($resV) while ($r = $resV->fetch_assoc()) $vehicles[] = $r;

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';
?>

<div class="mx-auto max-w-4xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-6">
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between border-b border-slate-200 dark:border-slate-700 pb-6">
    <div>
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Assign Vehicle to Terminal</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">System checks: documents verified and terminal allows vehicle type.</p>
    </div>
    <div class="flex items-center gap-3">
      <a href="?page=module5/submodule1" class="inline-flex items-center justify-center gap-2 rounded-md bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 transition-colors">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>
        Terminal List
      </a>
    </div>
  </div>

  <div id="toast-container" class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 z-[100] flex flex-col gap-3 pointer-events-none"></div>

  <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-visible">
    <div class="p-6 space-y-5">
      <form id="formAssign" class="space-y-5" novalidate>
        <div>
          <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Terminal</label>
          <select name="terminal_id" required class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold">
            <option value="">Select terminal</option>
            <?php foreach ($terminals as $t): ?>
              <option value="<?php echo (int)$t['id']; ?>" data-allowed-types="<?php echo htmlspecialchars((string)($t['allowed_types'] ?? ''), ENT_QUOTES); ?>" <?php echo ($prefillTerminalId > 0 && (int)$t['id'] === $prefillTerminalId) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars((string)$t['name']); ?><?php if ((int)($t['capacity'] ?? 0) > 0) echo ' (cap ' . (int)$t['capacity'] . ')'; ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1">Vehicle</label>
          <input type="hidden" name="vehicle_id" id="vehicleId" value="">
          <div class="relative">
            <button type="button" id="vehicleDropdownBtn" class="w-full px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 text-sm font-semibold text-left flex items-center justify-between gap-3">
              <span id="vehicleDropdownLabel" class="truncate">Select vehicle</span>
              <i data-lucide="chevron-down" class="w-4 h-4 text-slate-500"></i>
            </button>
            <div id="vehicleDropdownPanel" class="hidden absolute z-50 mt-2 w-full rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-xl overflow-hidden">
              <div class="p-3 border-b border-slate-200 dark:border-slate-700">
                <div class="flex items-center gap-2">
                  <input id="vehicleDropdownSearch" class="flex-1 px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm font-semibold" placeholder="Search plate...">
                  <button type="button" id="btnVehicleSearch" class="px-4 py-2.5 rounded-md bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 dark:hover:bg-slate-600 text-white text-sm font-bold">Search</button>
                </div>
              </div>
              <div id="vehicleDropdownList" class="max-h-80 overflow-auto p-2"></div>
            </div>
          </div>
        </div>
        <div class="flex items-center justify-end gap-2 pt-2">
          <button id="btnAssign" class="px-4 py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold">Assign</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  (function(){
    const rootUrl = <?php echo json_encode($rootUrl); ?>;
    const form = document.getElementById('formAssign');
    const btn = document.getElementById('btnAssign');
    const vehicleId = document.getElementById('vehicleId');
    const vehicleDropdownBtn = document.getElementById('vehicleDropdownBtn');
    const vehicleDropdownLabel = document.getElementById('vehicleDropdownLabel');
    const vehicleDropdownPanel = document.getElementById('vehicleDropdownPanel');
    const vehicleDropdownSearch = document.getElementById('vehicleDropdownSearch');
    const btnVehicleSearch = document.getElementById('btnVehicleSearch');
    const vehicleDropdownList = document.getElementById('vehicleDropdownList');

    const allVehicles = <?php echo json_encode(array_map(function($v){
      return [
        'id'=>(int)($v['id'] ?? 0),
        'label'=>(string)($v['plate_number'] ?? ''),
        'type'=>(string)($v['vehicle_type'] ?? '')
      ];
    }, $vehicles)); ?>;

    function showToast(message, type) {
      const container = document.getElementById('toast-container');
      if (!container) return;
      const t = (type || 'success').toString();
      const color = t === 'error' ? 'bg-rose-600' : 'bg-emerald-600';
      const el = document.createElement('div');
      el.className = `pointer-events-auto px-4 py-3 rounded-xl shadow-lg text-white text-sm font-semibold ${color}`;
      el.textContent = message;
      container.appendChild(el);
      setTimeout(() => { el.classList.add('opacity-0'); el.style.transition = 'opacity 250ms'; }, 2600);
      setTimeout(() => { el.remove(); }, 3000);
    }

    const terminalSelect = document.querySelector('select[name="terminal_id"]');
    const vehicleById = {};
    allVehicles.forEach((v) => {
      const id = Number(v.id || 0);
      if (id > 0) vehicleById[id] = v;
    });

    function filterTerminalsForVehicle(vehicleType) {
      if (!terminalSelect) return;
      const vt = (vehicleType || '').toString().trim().toLowerCase();
      const opts = terminalSelect.options;
      for (let i = 0; i < opts.length; i++) {
        const opt = opts[i];
        const val = opt.value || '';
        if (val === '') {
          opt.hidden = false;
          continue;
        }
        const raw = (opt.getAttribute('data-allowed-types') || '').toString().toLowerCase();
        if (vt === '' || raw === '') {
          opt.hidden = false;
          continue;
        }
        const parts = raw.split(',').map((s) => s.trim()).filter(Boolean);
        if (!parts.length) {
          opt.hidden = false;
          continue;
        }
        let ok = false;
        for (let j = 0; j < parts.length; j++) {
          if (parts[j] === vt) { ok = true; break; }
        }
        opt.hidden = !ok;
      }
      if (terminalSelect.value && terminalSelect.selectedOptions.length && terminalSelect.selectedOptions[0].hidden) {
        terminalSelect.value = '';
      }
    }

    function openVehicleDropdown() {
      if (!vehicleDropdownPanel) return;
      vehicleDropdownPanel.classList.remove('hidden');
      if (vehicleDropdownSearch) vehicleDropdownSearch.focus();
    }

    function closeVehicleDropdown() {
      if (!vehicleDropdownPanel) return;
      vehicleDropdownPanel.classList.add('hidden');
    }

    function isVehicleDropdownOpen() {
      return vehicleDropdownPanel && !vehicleDropdownPanel.classList.contains('hidden');
    }

    function pickVehicle(id, label) {
      if (vehicleId) vehicleId.value = String(id || '');
      if (vehicleDropdownLabel) vehicleDropdownLabel.textContent = (label || 'Select vehicle').toString();
      const v = vehicleById[id] || null;
      const vt = v && v.type ? v.type : '';
      filterTerminalsForVehicle(vt);
      closeVehicleDropdown();
    }

    function renderVehicleList(items) {
      if (!vehicleDropdownList) return;
      const arr = Array.isArray(items) ? items : [];
      if (!arr.length) {
        vehicleDropdownList.innerHTML = '<div class="px-3 py-2 text-sm text-slate-500 italic">No matches.</div>';
        return;
      }
      const slice = arr.slice(0, 250);
      vehicleDropdownList.innerHTML = slice.map((v) => {
        const id = Number(v.id || 0);
        const label = (v.label || '').toString();
        const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        return `<button type="button" class="w-full text-left p-3 rounded-xl bg-white dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700 hover:border-blue-300 dark:hover:border-blue-700 hover:bg-blue-50/30 dark:hover:bg-blue-900/10 transition-all mb-2" data-veh-pick="${id}" data-veh-label="${esc(label)}">
          <div class="font-black text-slate-900 dark:text-white">${esc(label || '-')}</div>
        </button>`;
      }).join('');
      vehicleDropdownList.querySelectorAll('[data-veh-pick]').forEach((b) => {
        b.addEventListener('click', () => {
          const id = Number(b.getAttribute('data-veh-pick') || 0);
          const label = (b.getAttribute('data-veh-label') || '').toString();
          if (!id) return;
          pickVehicle(id, label);
        });
      });
      if (arr.length > 250) {
        const tail = document.createElement('div');
        tail.className = 'px-3 py-2 text-[11px] font-semibold text-slate-500';
        tail.textContent = 'Showing first 250 matches. Refine your search to narrow results.';
        vehicleDropdownList.appendChild(tail);
      }
    }

    function doVehicleSearch() {
      const q = (vehicleDropdownSearch && vehicleDropdownSearch.value) ? vehicleDropdownSearch.value.toString().trim().toLowerCase() : '';
      if (!vehicleDropdownList) return;
      if (q === '') {
        renderVehicleList(allVehicles);
        return;
      }
      renderVehicleList(allVehicles.filter((v) => ((v.label || '').toString().toLowerCase().includes(q))));
    }

    if (vehicleDropdownBtn) {
      vehicleDropdownBtn.addEventListener('click', () => {
        if (isVehicleDropdownOpen()) closeVehicleDropdown();
        else {
          openVehicleDropdown();
          doVehicleSearch();
        }
      });
    }

    if (btnVehicleSearch) btnVehicleSearch.addEventListener('click', doVehicleSearch);
    if (vehicleDropdownSearch) {
      vehicleDropdownSearch.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); doVehicleSearch(); }
      });
    }

    document.addEventListener('click', (e) => {
      if (!vehicleDropdownPanel || !vehicleDropdownBtn) return;
      const t = e.target;
      if (t && (vehicleDropdownPanel.contains(t) || vehicleDropdownBtn.contains(t))) return;
      closeVehicleDropdown();
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && isVehicleDropdownOpen()) closeVehicleDropdown();
    });

    if (form && btn) {
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!vehicleId || !vehicleId.value) { showToast('Select a vehicle.', 'error'); return; }
        if (!form.checkValidity()) { form.reportValidity(); return; }
        btn.disabled = true;
        btn.textContent = 'Assigning...';
        try {
          const res = await fetch(rootUrl + '/admin/api/module5/assign_terminal.php', { method: 'POST', body: new FormData(form) });
          const data = await res.json();
          if (!data || !data.ok) {
            const base = (data && data.error) ? String(data.error) : 'assign_failed';
            const det = (data && data.details && data.details.message) ? String(data.details.message) : '';
            throw new Error(det ? (base + ': ' + det) : base);
          }
          showToast('Vehicle assigned to terminal.');
          setTimeout(() => { window.location.href = '?page=module5/submodule1'; }, 600);
        } catch (err) {
          showToast(err.message || 'Failed', 'error');
          btn.disabled = false;
          btn.textContent = 'Assign';
        }
      });
    }
  })();
</script>
