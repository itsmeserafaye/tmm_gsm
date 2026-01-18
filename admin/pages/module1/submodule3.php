<?php
require_once __DIR__ . '/../../includes/auth.php';
require_any_permission(['module1.view','module1.vehicles.write','module1.routes.write','module1.coops.write']);
?>
<div class="mx-auto max-w-7xl px-4 sm:px-6 md:px-8 mt-6 font-sans text-slate-900 dark:text-slate-100 space-y-8">
  <!-- Header -->
  <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
    <div>
      <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Route & Terminal Management</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
        Assign vehicles to LPTRP-approved routes and manage terminal capacities.
      </p>
    </div>
  </div>

  <!-- Toast Container -->
  <div id="toast-container" class="fixed bottom-4 right-4 z-[100] flex flex-col gap-2 pointer-events-none"></div>

  <?php
    require_once __DIR__ . '/../../includes/db.php';
    $db = db();
    if (defined('TMM_TEST') && has_permission('module1.routes.write')) {
      $db->query("UPDATE terminal_assignments SET route_id=NULL WHERE route_id IN ('R_999','R-999')");
      $db->query("UPDATE vehicles SET route_id=NULL WHERE route_id IN ('R_999','R-999')");
      $db->query("DELETE FROM routes WHERE route_id IN ('R_999','R-999')");
    }
    $hasLptrpRes = $db->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='lptrp_routes' LIMIT 1");
    $hasLptrp = (bool)($hasLptrpRes && $hasLptrpRes->fetch_row());
    if ($hasLptrp) {
      $routesForSuggestions = $db->query("SELECT r.route_id, r.route_name FROM routes r JOIN lptrp_routes lr ON lr.route_code=r.route_id ORDER BY r.route_id");
    } else {
      $routesForSuggestions = $db->query("SELECT route_id, route_name FROM routes ORDER BY route_id");
    }
    $terminalsForSelect = [];
    $resTerminals = $db->query("SELECT name FROM terminals WHERE type <> 'Parking' ORDER BY name");
    if ($resTerminals) {
      while ($t = $resTerminals->fetch_assoc()) $terminalsForSelect[] = (string)($t['name'] ?? '');
    }

    $platePrefill = trim((string)($_GET['plate'] ?? ''));
    $frRefPrefill = trim((string)($_GET['fr_ref'] ?? ''));
    $routePrefill = trim((string)($_GET['route_id'] ?? ''));
    $currentVehicle = null;
    $currentAssignment = null;

    if ($platePrefill === '' && $frRefPrefill !== '') {
      $stmtP = $db->prepare("SELECT plate_number FROM vehicles WHERE franchise_id=? AND plate_number <> '' ORDER BY plate_number ASC LIMIT 1");
      if ($stmtP) {
        $stmtP->bind_param('s', $frRefPrefill);
        $stmtP->execute();
        $rowP = $stmtP->get_result()->fetch_assoc();
        $stmtP->close();
        if ($rowP && isset($rowP['plate_number'])) $platePrefill = (string)$rowP['plate_number'];
      }
    }

    $recentEndorsedPlates = [];
    $orderCol = 'application_id';
    $chkSubmitted = $db->query("SHOW COLUMNS FROM franchise_applications LIKE 'submitted_at'");
    if ($chkSubmitted && $chkSubmitted->num_rows > 0) $orderCol = 'submitted_at';
    else {
      $chkCreated = $db->query("SHOW COLUMNS FROM franchise_applications LIKE 'created_at'");
      if ($chkCreated && $chkCreated->num_rows > 0) $orderCol = 'created_at';
    }
    $resPlates = $db->query("SELECT DISTINCT v.plate_number
                             FROM franchise_applications fa
                             JOIN vehicles v ON v.franchise_id = fa.franchise_ref_number
                             WHERE fa.status='Endorsed' AND v.plate_number <> ''
                             ORDER BY fa.$orderCol DESC, v.plate_number ASC
                             LIMIT 40");
    if ($resPlates) {
      while ($r = $resPlates->fetch_assoc()) {
        $p = strtoupper(trim((string)($r['plate_number'] ?? '')));
        if ($p !== '') $recentEndorsedPlates[] = $p;
      }
    }
    
    if ($platePrefill !== '') {
      $stmtV = $db->prepare("SELECT plate_number, route_id, vehicle_type, operator_name, status, inspection_status, franchise_id FROM vehicles WHERE plate_number=? LIMIT 1");
      $stmtV->bind_param('s', $platePrefill);
      $stmtV->execute();
      $currentVehicle = $stmtV->get_result()->fetch_assoc() ?: null;
      $stmtV->close();

      $stmtA = $db->prepare("SELECT terminal_name, route_id, status, assigned_at FROM terminal_assignments WHERE plate_number=? LIMIT 1");
      $stmtA->bind_param('s', $platePrefill);
      $stmtA->execute();
      $currentAssignment = $stmtA->get_result()->fetch_assoc() ?: null;
      $stmtA->close();

      if ($routePrefill === '' && $currentVehicle && !empty($currentVehicle['route_id'])) {
        $routePrefill = (string)$currentVehicle['route_id'];
      }
    }
  ?>

  <!-- Top Section: Assignment Form -->
  <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <div class="p-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30">
      <div class="flex items-center gap-3">
        <div class="p-1.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
          <i data-lucide="map-pin" class="w-5 h-5"></i>
        </div>
        <h2 class="text-lg font-bold text-slate-900 dark:text-white">Assign Vehicle to Route</h2>
      </div>
    </div>
    
    <div class="p-6">
      <?php if ($currentVehicle): ?>
        <div class="mb-6 p-4 rounded-xl bg-slate-50 border border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-bold text-sm">
              <?php echo substr($currentVehicle['plate_number'], 0, 1); ?>
            </div>
            <div>
              <div class="text-sm text-slate-500">Selected Vehicle</div>
              <div class="font-bold text-slate-800"><?php echo htmlspecialchars((string)$currentVehicle['plate_number'], ENT_QUOTES); ?></div>
            </div>
          </div>
          
          <div class="flex items-center gap-4 text-sm">
            <div>
              <span class="text-slate-500">Current Route:</span>
              <span class="font-semibold text-slate-700 ml-1"><?php echo htmlspecialchars((string)($currentVehicle['route_id'] ?? '-'), ENT_QUOTES); ?></span>
            </div>
            <?php if ($currentAssignment && !empty($currentAssignment['terminal_name'])): ?>
            <div>
              <span class="text-slate-500">Terminal:</span>
              <span class="font-semibold text-slate-700 ml-1"><?php echo htmlspecialchars((string)$currentAssignment['terminal_name'], ENT_QUOTES); ?></span>
            </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <form id="assignRouteForm" class="grid grid-cols-1 md:grid-cols-12 gap-4" method="POST" action="<?php echo htmlspecialchars($rootUrl ?? '', ENT_QUOTES); ?>/admin/api/module1/assign_route.php">
        <div class="md:col-span-3">
          <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Plate Number</label>
          <input name="plate_number" list="recent-endorsed-plates" value="<?php echo htmlspecialchars($platePrefill, ENT_QUOTES); ?>" 
                 class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all uppercase font-semibold text-sm text-slate-900 dark:text-white" 
                 placeholder="<?php echo htmlspecialchars($platePrefill !== '' ? 'ABC-1234' : (!empty($recentEndorsedPlates) ? ('Try: ' . $recentEndorsedPlates[0]) : 'ABC-1234'), ENT_QUOTES); ?>" required>
        </div>
        
        <div class="md:col-span-3">
          <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Route ID</label>
          <input name="route_id" list="route-list" value="<?php echo htmlspecialchars($routePrefill, ENT_QUOTES); ?>" 
                 class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all uppercase font-semibold text-sm text-slate-900 dark:text-white" 
                 placeholder="Search Route..." required>
        </div>
        
        <div class="md:col-span-3">
          <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Terminal</label>
          <select name="terminal_name" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all appearance-none text-sm font-semibold text-slate-900 dark:text-white">
            <option value="">Select Terminal</option>
            <?php if ($terminalsForSelect): ?>
              <?php foreach ($terminalsForSelect as $tn): ?>
                <option <?php echo ($currentAssignment && (string)($currentAssignment['terminal_name'] ?? '') === $tn) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($tn, ENT_QUOTES); ?>
                </option>
              <?php endforeach; ?>
            <?php else: ?>
              <option>Central Terminal</option>
              <option>East Hub</option>
              <option>North Bay</option>
            <?php endif; ?>
          </select>
        </div>
        
        <div class="md:col-span-2">
          <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Status</label>
          <select name="status" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all appearance-none text-sm font-semibold text-slate-900 dark:text-white">
            <option>Authorized</option>
            <option>Pending</option>
          </select>
        </div>
        
        <div class="md:col-span-1 flex items-end">
          <button type="submit" id="btnAssignRoute" class="w-full py-2.5 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold shadow-sm active:scale-[0.98] transition-all flex items-center justify-center">
            <i data-lucide="check" class="w-5 h-5"></i>
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Analytics Grid -->
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Route Capacity Card -->
    <div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden flex flex-col">
      <div class="p-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="p-1.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
            <i data-lucide="bar-chart-2" class="w-5 h-5"></i>
          </div>
          <h3 class="text-base font-bold text-slate-900 dark:text-white">Route Capacity Overview</h3>
        </div>
        
        <?php
          $routeIdRaw = trim((string)($_GET['route_id'] ?? ''));
          if ($routeIdRaw === '') {
            $resFirstRoute = $db->query("SELECT route_id FROM routes ORDER BY CASE WHEN route_id REGEXP '^R_[0-9]+$' THEN CAST(SUBSTRING(route_id,3) AS UNSIGNED) ELSE 99999999 END, route_id LIMIT 1");
            if ($resFirstRoute && ($rowFirstRoute = $resFirstRoute->fetch_assoc())) {
              $routeIdRaw = (string)($rowFirstRoute['route_id'] ?? '');
            } else {
              $routeIdRaw = '';
            }
          }
          $routeIdEsc = htmlspecialchars($routeIdRaw, ENT_QUOTES);

          $routesForOverview = $db->query("SELECT r.route_id, r.route_name
                                          FROM routes r
                                          WHERE r.route_id REGEXP '^R_[0-9]+$'
                                          ORDER BY CAST(SUBSTRING(r.route_id,3) AS UNSIGNED), r.route_id");
        ?>
        <div class="flex items-center gap-2">
          <span class="text-xs font-semibold text-slate-500 dark:text-slate-300">Route</span>
          <select id="routeOverviewSelect" class="rounded-md border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/50 px-3 py-2 text-sm font-semibold text-slate-900 dark:text-white focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none">
            <?php if ($routesForOverview): ?>
              <?php while ($ro = $routesForOverview->fetch_assoc()): ?>
                <?php $roId = (string)($ro['route_id'] ?? ''); ?>
                <option value="<?php echo htmlspecialchars($roId, ENT_QUOTES); ?>" <?php echo $roId === $routeIdRaw ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($roId . ' — ' . (string)($ro['route_name'] ?? ''), ENT_QUOTES); ?>
                </option>
              <?php endwhile; ?>
            <?php endif; ?>
          </select>
        </div>
      </div>
      
      <div class="p-6 flex-1">
        <?php
          $stmtR = $db->prepare("SELECT route_name, max_vehicle_limit FROM routes WHERE route_id=?");
          $stmtR->bind_param('s', $routeIdRaw);
          $stmtR->execute();
          $routeRow = $stmtR->get_result()->fetch_assoc();
          $cap = (int)($routeRow['max_vehicle_limit'] ?? 50);
          $routeName = $routeRow ? $routeRow['route_name'] : $routeIdRaw;
          
          $stmt = $db->prepare("SELECT COUNT(*) AS c FROM terminal_assignments WHERE route_id=? AND status IN ('Authorized','Pending')");
          $stmt->bind_param('s', $routeIdRaw);
          $stmt->execute();
          $cnt = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
          $pct = $cap > 0 ? min(100, round(($cnt / $cap) * 100)) : 0;
          
          // Determine color based on capacity
          $barColor = 'bg-blue-600';
          if ($pct > 80) $barColor = 'bg-rose-600';
          else if ($pct > 50) $barColor = 'bg-amber-600';
        ?>
        
        <div class="flex items-center justify-between mb-2">
          <div>
            <span class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo $cnt; ?></span>
            <span class="text-slate-400 mx-1">/</span>
            <span class="text-slate-500 dark:text-slate-300 font-medium"><?php echo $cap; ?> Vehicles</span>
          </div>
          <div class="px-3 py-1 rounded-full bg-slate-100 dark:bg-slate-700 text-xs font-semibold text-slate-600 dark:text-slate-200">
            <?php echo $pct; ?>% Utilization
          </div>
        </div>
        
        <div class="relative w-full h-4 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
          <div class="absolute top-0 left-0 h-full rounded-full <?php echo $barColor; ?> transition-all duration-700 ease-out" style="width: <?php echo $pct; ?>%"></div>
        </div>
        
        <div class="mt-4 p-4 bg-slate-50 dark:bg-slate-900/30 rounded-md border border-slate-200 dark:border-slate-700">
          <h4 class="text-sm font-semibold text-slate-700 dark:text-slate-200 mb-1"><?php echo $routeIdEsc; ?> — <?php echo htmlspecialchars($routeName, ENT_QUOTES); ?></h4>
          <p class="text-xs text-slate-500 dark:text-slate-300">
            This route is currently operating at <span class="font-semibold text-slate-700 dark:text-slate-200"><?php echo $pct; ?>%</span> capacity. 
            <?php if($pct > 90): ?>
              <span class="text-red-500 font-medium">Warning: Near Maximum Capacity.</span>
            <?php else: ?>
              Capacity levels are within normal range.
            <?php endif; ?>
          </p>
        </div>
      </div>
    </div>
    
    <!-- Allowed Terminals Card -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden flex flex-col">
      <div class="p-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30 flex items-center gap-3">
        <div class="p-1.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
          <i data-lucide="home" class="w-5 h-5"></i>
        </div>
        <h3 class="text-base font-bold text-slate-900 dark:text-white">Active Terminals</h3>
      </div>
      
      <div class="p-4 flex-1 overflow-y-auto max-h-[300px]">
        <ul class="space-y-2">
          <?php
            $stmt2 = $db->prepare("SELECT terminal_name, COUNT(*) AS c FROM terminal_assignments WHERE route_id=? AND status IN ('Authorized','Pending') GROUP BY terminal_name");
            $stmt2->bind_param('s', $routeIdRaw);
            $stmt2->execute();
            $res2 = $stmt2->get_result();
            if ($res2->num_rows > 0):
              while ($r = $res2->fetch_assoc()):
          ?>
            <li class="flex items-center justify-between p-3 rounded-md bg-slate-50 dark:bg-slate-900/30 border border-slate-200 dark:border-slate-700 transition-colors">
              <span class="font-medium text-slate-700 dark:text-slate-200 text-sm"><?php echo htmlspecialchars($r['terminal_name']); ?></span>
              <span class="px-2 py-1 rounded-md bg-white dark:bg-slate-800 text-xs font-bold text-slate-600 dark:text-slate-200 shadow-sm border border-slate-200 dark:border-slate-700">
                <?php echo $r['c']; ?> units
              </span>
            </li>
          <?php 
              endwhile;
            else:
          ?>
            <div class="text-center py-8 text-slate-400 text-sm">
              No terminals currently active for this route.
            </div>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </div>

  <!-- Manage Routes Section -->
  <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <div class="p-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30 flex items-center gap-3">
      <div class="p-1.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
        <i data-lucide="settings" class="w-5 h-5"></i>
      </div>
      <h3 class="text-base font-bold text-slate-900 dark:text-white">LPTRP Route Masterlist</h3>
    </div>
    
    <div class="p-6">
      <div class="mb-6 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/30 p-4 flex flex-col gap-2">
        <div class="text-sm font-bold text-slate-900 dark:text-white">Routes are LPTRP-controlled</div>
        <div class="text-xs text-slate-600 dark:text-slate-300">
          Add/approve routes in Module 2 (LPTRP masterlist). This page only syncs and uses LPTRP-approved routes for assignment and capacity tracking.
        </div>
        <div class="flex flex-col sm:flex-row gap-2 pt-1">
          <a href="?page=module2/submodule1" class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-md bg-emerald-700 hover:bg-emerald-800 text-white font-semibold shadow-sm transition-all text-sm">
            <i data-lucide="arrow-right" class="w-4 h-4"></i>
            Open Module 2 LPTRP
          </a>
          <form id="importLptrpForm" class="flex items-center gap-2" method="POST" action="<?php echo htmlspecialchars($rootUrl ?? '', ENT_QUOTES); ?>/admin/api/routes/import_lptrp.php" enctype="multipart/form-data">
            <input type="file" name="file" accept=".csv" class="block w-full text-xs text-slate-600 file:mr-3 file:rounded-md file:border file:border-slate-200 file:bg-white file:px-3 file:py-2 file:text-xs file:font-semibold file:text-slate-700 hover:file:bg-slate-50">
            <button type="submit" id="btnImportLptrp" class="px-4 py-2 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-semibold shadow-sm transition-all text-sm whitespace-nowrap">Import CSV</button>
          </form>
        </div>
      </div>

      <!-- Routes Table -->
      <div class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700">
        <div class="overflow-x-auto">
        <table class="min-w-full text-sm text-left">
          <thead class="bg-slate-50 dark:bg-slate-700 text-slate-500 dark:text-slate-200 font-medium border-b border-slate-200 dark:border-slate-700">
            <tr>
              <th class="py-3 px-4">Route ID</th>
              <th class="py-3 px-4">Route Name</th>
              <th class="py-3 px-4">Capacity</th>
              <th class="py-3 px-4">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
            <?php
              if ($hasLptrp) {
                $routes = $db->query("SELECT r.route_id, r.route_name, r.max_vehicle_limit, r.status FROM routes r JOIN lptrp_routes lr ON lr.route_code=r.route_id ORDER BY r.route_id");
              } else {
                $routes = $db->query("SELECT route_id, route_name, max_vehicle_limit, status FROM routes ORDER BY route_id");
              }
              while ($r = $routes->fetch_assoc()):
            ?>
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors group">
              <td class="py-3 px-4 font-semibold text-slate-900 dark:text-white"><?php echo htmlspecialchars($r['route_id']); ?></td>
              <td class="py-3 px-4 text-slate-600 dark:text-slate-300 font-medium"><?php echo htmlspecialchars($r['route_name']); ?></td>
              <td class="py-3 px-4">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-200">
                  <?php echo htmlspecialchars($r['max_vehicle_limit']); ?> max
                </span>
              </td>
              <td class="py-3 px-4">
                <?php if($r['status'] === 'Active'): ?>
                  <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-100">
                    <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Active
                  </span>
                <?php else: ?>
                  <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-200 border border-slate-200 dark:border-slate-600">
                    <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> Inactive
                  </span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Assignments Table Section -->
  <?php
    $assignRouteIdRaw = trim((string)($_GET['assign_route_id'] ?? 'all'));
    if ($assignRouteIdRaw === '') $assignRouteIdRaw = 'all';
    $assignRouteIdEsc = htmlspecialchars($assignRouteIdRaw, ENT_QUOTES);
    if ($hasLptrp) {
      $routesForAssignments = $db->query("SELECT r.route_id, r.route_name FROM routes r JOIN lptrp_routes lr ON lr.route_code=r.route_id ORDER BY r.route_id");
    } else {
      $routesForAssignments = $db->query("SELECT route_id, route_name FROM routes ORDER BY route_id");
    }
  ?>
  <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <div class="p-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30 flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div class="flex items-center gap-3">
        <div class="p-1.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
          <i data-lucide="list" class="w-5 h-5"></i>
        </div>
        <h3 class="text-base font-bold text-slate-900 dark:text-white">
          Current Assignments<?php echo $assignRouteIdRaw !== 'all' ? (' for ' . $assignRouteIdEsc) : ''; ?>
        </h3>
      </div>
      <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full md:w-auto">
        <div class="flex items-center gap-2">
          <span class="text-xs font-semibold text-slate-500 dark:text-slate-300 whitespace-nowrap">Route</span>
          <select id="assignmentsRouteSelect" class="w-full sm:w-auto rounded-md border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/50 px-3 py-2 text-sm font-semibold text-slate-900 dark:text-white focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none">
            <option value="all" <?php echo $assignRouteIdRaw === 'all' ? 'selected' : ''; ?>>All Routes</option>
            <?php if ($routesForAssignments): ?>
              <?php while ($ar = $routesForAssignments->fetch_assoc()): ?>
                <?php $arId = (string)($ar['route_id'] ?? ''); ?>
                <option value="<?php echo htmlspecialchars($arId, ENT_QUOTES); ?>" <?php echo $arId === $assignRouteIdRaw ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($arId . ' — ' . (string)($ar['route_name'] ?? ''), ENT_QUOTES); ?>
                </option>
              <?php endwhile; ?>
            <?php endif; ?>
          </select>
        </div>
        <div class="relative w-full sm:w-auto">
          <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
          <input id="assignmentsFilterInput" type="text" placeholder="Filter vehicles..." class="w-full sm:w-64 pl-9 pr-4 py-2 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-md text-sm font-semibold text-slate-900 dark:text-white focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none">
        </div>
      </div>
    </div>
    
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm text-left">
        <thead class="bg-slate-50 dark:bg-slate-700 text-slate-500 dark:text-slate-200 font-medium border-b border-slate-200 dark:border-slate-700">
          <tr>
            <th class="py-3 px-6">Vehicle</th>
            <?php if ($assignRouteIdRaw === 'all'): ?>
              <th class="py-3 px-4">Route</th>
            <?php endif; ?>
            <th class="py-3 px-4">Terminal</th>
            <th class="py-3 px-4">Status</th>
            <th class="py-3 px-4">Last Update</th>
            <th class="py-3 px-4 text-right">Quick Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
          <?php
            if ($assignRouteIdRaw === 'all') {
              $stmt3 = $db->prepare("SELECT ta.plate_number, ta.route_id, r.route_name, ta.terminal_name, ta.status, ta.assigned_at FROM terminal_assignments ta LEFT JOIN routes r ON ta.route_id = r.route_id ORDER BY ta.assigned_at DESC");
            } else {
              $stmt3 = $db->prepare("SELECT ta.plate_number, ta.route_id, r.route_name, ta.terminal_name, ta.status, ta.assigned_at FROM terminal_assignments ta LEFT JOIN routes r ON ta.route_id = r.route_id WHERE ta.route_id=? ORDER BY ta.assigned_at DESC");
              $stmt3->bind_param('s', $assignRouteIdRaw);
            }
            $stmt3->execute();
            $res3 = $stmt3->get_result();
            if ($res3->num_rows > 0):
              while ($a = $res3->fetch_assoc()):
          ?>
          <tr data-assignment-row="1" class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors group">
            <td class="py-3 px-6">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-md bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-slate-600 dark:text-slate-200 font-bold text-xs">
                  <?php echo substr($a['plate_number'], 0, 1); ?>
                </div>
                <span class="font-semibold text-slate-900 dark:text-white"><?php echo htmlspecialchars($a['plate_number']); ?></span>
              </div>
            </td>
            <?php if ($assignRouteIdRaw === 'all'): ?>
              <td class="py-3 px-4 text-slate-600 dark:text-slate-300">
                <div class="font-semibold text-slate-900 dark:text-white"><?php echo htmlspecialchars((string)($a['route_id'] ?? '')); ?></div>
                <?php if (!empty($a['route_name'])): ?><div class="text-xs text-slate-400"><?php echo htmlspecialchars((string)$a['route_name']); ?></div><?php endif; ?>
              </td>
            <?php endif; ?>
            <td class="py-3 px-4 text-slate-600 dark:text-slate-300 font-medium"><?php echo htmlspecialchars($a['terminal_name']); ?></td>
            <td class="py-3 px-4">
              <?php if($a['status'] === 'Authorized'): ?>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-100">
                  <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Authorized
                </span>
              <?php else: ?>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-100">
                  <span class="w-1.5 h-1.5 rounded-full bg-yellow-500"></span> Pending
                </span>
              <?php endif; ?>
            </td>
            <td class="py-3 px-4 text-slate-500 dark:text-slate-300 text-xs font-medium">
              <?php echo date('M d, H:i', strtotime($a['assigned_at'])); ?>
            </td>
            <td class="py-3 px-4 text-right">
              <div class="flex items-center justify-end gap-2 opacity-60 group-hover:opacity-100 transition-opacity">
                <button title="View Details" data-plate="<?php echo htmlspecialchars($a['plate_number']); ?>" class="p-2 rounded-lg text-blue-600 hover:bg-blue-50 transition-colors">
                  <i data-lucide="eye" class="w-4 h-4"></i>
                </button>
                <form method="POST" action="<?php echo htmlspecialchars($rootUrl ?? '', ENT_QUOTES); ?>/admin/api/module1/assign_route.php" class="inline-flex" data-toggle-assignment="1">
                   <input type="hidden" name="plate_number" value="<?php echo htmlspecialchars($a['plate_number']); ?>">
                   <input type="hidden" name="route_id" value="<?php echo htmlspecialchars($a['route_id']); ?>">
                   <input type="hidden" name="terminal_name" value="<?php echo htmlspecialchars($a['terminal_name']); ?>">
                   <!-- Toggle status logic -->
                   <input type="hidden" name="status" value="<?php echo $a['status'] === 'Authorized' ? 'Pending' : 'Authorized'; ?>">
                   <button title="Toggle Status" class="p-2 rounded-lg <?php echo $a['status'] === 'Authorized' ? 'text-orange-600 hover:bg-orange-50' : 'text-green-600 hover:bg-green-50'; ?> transition-colors">
                     <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                   </button>
                </form>
              </div>
            </td>
          </tr>
          <?php 
              endwhile;
            else:
          ?>
            <tr>
              <td colspan="<?php echo $assignRouteIdRaw === 'all' ? '6' : '5'; ?>" class="py-8 text-center text-slate-400">
                <div class="flex flex-col items-center gap-2">
                  <i data-lucide="inbox" class="w-8 h-8 stroke-1"></i>
                  <span><?php echo $assignRouteIdRaw === 'all' ? 'No vehicles assigned yet.' : 'No vehicles assigned to this route yet.'; ?></span>
                </div>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Datalists -->
<datalist id="route-list">
  <?php if ($routesForSuggestions): ?>
    <?php $routesForSuggestions->data_seek(0); while ($r = $routesForSuggestions->fetch_assoc()): ?>
      <option value="<?php echo htmlspecialchars($r['route_id']); ?>"><?php echo htmlspecialchars($r['route_id'] . ' — ' . $r['route_name']); ?></option>
    <?php endwhile; ?>
  <?php endif; ?>
</datalist>
<datalist id="recent-endorsed-plates">
  <?php foreach ($recentEndorsedPlates as $p): ?>
    <option value="<?php echo htmlspecialchars($p, ENT_QUOTES); ?>"></option>
  <?php endforeach; ?>
</datalist>

<!-- Vehicle Detail Modal -->
<div id="vehicleModalS3" class="fixed inset-0 z-[60] hidden">
  <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity opacity-0" id="vehicleModalS3Backdrop"></div>
  <div class="absolute inset-0 flex items-center justify-center p-4">
    <div class="w-full max-w-5xl bg-white rounded-2xl shadow-2xl transform scale-95 opacity-0 transition-all duration-300 flex flex-col max-h-[90vh]" id="vehicleModalS3Content">
      <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
        <h3 class="text-lg font-bold text-slate-800">Vehicle Details</h3>
        <button id="vehicleModalS3Close" class="p-2 rounded-xl hover:bg-slate-100 text-slate-500 transition-colors">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>
      <div id="vehicleModalS3Body" class="p-6 overflow-y-auto">
        <!-- Content injected via JS -->
      </div>
    </div>
  </div>
</div>

<script>
    (function(){
      // Initialize Lucide
      if (window.lucide) window.lucide.createIcons();

      // Toast System
      function showToast(msg, type = 'success') {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        const colors = type === 'success' ? 'bg-emerald-500' : 'bg-red-500';
        const icon = type === 'success' ? 'check-circle' : 'alert-circle';
        
        toast.className = `${colors} text-white px-4 py-3 rounded-xl shadow-lg shadow-black/5 flex items-center gap-3 transform transition-all duration-300 translate-y-10 opacity-0 min-w-[300px] backdrop-blur-md`;
        toast.innerHTML = `
          <i data-lucide="${icon}" class="w-5 h-5"></i>
          <span class="font-medium text-sm">${msg}</span>
        `;
        
        container.appendChild(toast);
        if (window.lucide) window.lucide.createIcons();
        requestAnimationFrame(() => toast.classList.remove('translate-y-10', 'opacity-0'));
        setTimeout(() => { toast.classList.add('opacity-0', 'translate-x-full'); setTimeout(() => toast.remove(), 300); }, 3000);
      }

      function showToastAction(msg, actionLabel, actionHref) {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        toast.className = `bg-amber-500 text-white px-4 py-3 rounded-xl shadow-lg shadow-black/5 flex items-start gap-3 transform transition-all duration-300 translate-y-10 opacity-0 min-w-[320px] backdrop-blur-md pointer-events-auto`;
        toast.innerHTML = `
          <i data-lucide="alert-triangle" class="w-5 h-5 mt-0.5 shrink-0"></i>
          <div class="flex-1">
            <div class="font-medium text-sm leading-snug">${msg}</div>
            <div class="pt-2">
              <a href="${actionHref}" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-white/15 hover:bg-white/20 transition-colors text-xs font-semibold">
                <i data-lucide="calendar" class="w-4 h-4"></i>
                <span>${actionLabel}</span>
              </a>
            </div>
          </div>
          <button type="button" class="p-1.5 rounded-lg hover:bg-white/15 transition-colors" data-toast-close="1" aria-label="Close">
            <i data-lucide="x" class="w-4 h-4"></i>
          </button>
        `;
        container.appendChild(toast);
        if (window.lucide) window.lucide.createIcons();
        requestAnimationFrame(() => toast.classList.remove('translate-y-10', 'opacity-0'));
        const closeBtn = toast.querySelector('button[data-toast-close="1"]');
        if (closeBtn) closeBtn.addEventListener('click', () => toast.remove());
        setTimeout(() => { toast.classList.add('opacity-0', 'translate-x-full'); setTimeout(() => toast.remove(), 300); }, 8000);
      }

      function mapAssignRouteError(code) {
        var c = (code || '').toString();
        switch (c) {
          case 'missing_fields': return 'Plate, route, and terminal are all required.';
          case 'vehicle_not_found': return 'Vehicle not found in registry.';
          case 'inspection_not_passed': return 'Inspection status must be PASSED before assigning a route.';
          case 'franchise_invalid': return 'Franchise is not endorsed. Route assignment is blocked.';
          case 'route_not_found': return 'Route ID not found in Routes registry.';
          case 'route_over_capacity': return 'Route is already at capacity. Choose another route or review LPTRP.';
          default: return 'Error: ' + c;
        }
      }

      function mapImportLptrpError(code) {
        var c = (code || '').toString();
        switch (c) {
          case 'missing_csv': return 'Please upload a CSV file.';
          case 'empty_csv': return 'CSV file looks empty.';
          default: return 'Error: ' + c;
        }
      }

      // Generic Form Handler
      function handleForm(formId, btnId, successMsg) {
        const form = document.getElementById(formId);
        const btn = document.getElementById(btnId);
        if(!form || !btn) return;

        form.addEventListener('submit', async function(e) {
          e.preventDefault();
          if (!form.checkValidity()) { form.reportValidity(); return; }

          const originalContent = btn.innerHTML;
          btn.disabled = true;
          btn.innerHTML = `<i data-lucide="loader-2" class="w-5 h-5 animate-spin"></i>`;
          if (window.lucide) window.lucide.createIcons();

          try {
            const formData = new FormData(form);
            const keepPlate = (formData.get('plate_number') || '').toString();
            const keepRoute = (formData.get('route_id') || '').toString();
            
            const res = await fetch(form.action, { method: 'POST', body: formData });
            const data = await res.json();
            
            const ok = data && (data.ok || data.status === 'success' || (Array.isArray(data) && data.length > 0));
            if (ok) {
              showToast(successMsg);
              if (formId === 'assignRouteForm') {
                form.reset();
                const params = new URLSearchParams();
                params.set('page', 'module1/submodule3');
                if (keepRoute) params.set('route_id', keepRoute);
                if (keepPlate) params.set('plate', keepPlate);
                setTimeout(() => { window.location.href = '?' + params.toString(); }, 600);
              } else if (formId === 'importLptrpForm') {
                setTimeout(() => location.reload(), 800);
              } else {
                form.reset();
                setTimeout(() => location.reload(), 1000);
              }
            } else {
              const errCode = (data && data.error) ? data.error : 'operation_failed';
              if (formId === 'assignRouteForm' && errCode === 'inspection_not_passed') {
                const plate = (formData.get('plate_number') || '').toString().trim();
                const params = new URLSearchParams();
                params.set('page', 'module4/submodule1');
                if (plate) params.set('plate', plate);
                params.set('prefill_type', 'Reinspection');
                showToastAction('This vehicle has not passed inspection yet. Schedule an inspection to continue.', 'Schedule Inspection', '?' + params.toString());
                return;
              }
              let errMsg = errCode;
              if (formId === 'assignRouteForm') errMsg = mapAssignRouteError(errMsg);
              if (formId === 'importLptrpForm') errMsg = mapImportLptrpError(errMsg);
              throw new Error(errMsg);
            }
          } catch (err) {
            showToast(err.message, 'error');
          } finally {
            btn.disabled = false;
            btn.innerHTML = originalContent;
            if (window.lucide) window.lucide.createIcons();
          }
        });
      }

      handleForm('assignRouteForm', 'btnAssignRoute', 'Route assigned successfully!');
      handleForm('importLptrpForm', 'btnImportLptrp', 'LPTRP routes imported successfully!');

      const routeOverviewSelect = document.getElementById('routeOverviewSelect');
      if (routeOverviewSelect) {
        routeOverviewSelect.addEventListener('change', function () {
          const params = new URLSearchParams(window.location.search || '');
          params.set('page', 'module1/submodule3');
          params.set('route_id', routeOverviewSelect.value || '');
          window.location.search = params.toString();
        });
      }

      const assignmentsRouteSelect = document.getElementById('assignmentsRouteSelect');
      if (assignmentsRouteSelect) {
        assignmentsRouteSelect.addEventListener('change', function () {
          const params = new URLSearchParams(window.location.search || '');
          params.set('page', 'module1/submodule3');
          const v = (assignmentsRouteSelect.value || '').toString();
          if (!v || v === 'all') params.delete('assign_route_id');
          else params.set('assign_route_id', v);
          window.location.search = params.toString();
        });
      }

      const assignmentsFilterInput = document.getElementById('assignmentsFilterInput');
      if (assignmentsFilterInput) {
        assignmentsFilterInput.addEventListener('input', function () {
          const q = (assignmentsFilterInput.value || '').toString().toLowerCase();
          document.querySelectorAll('tbody tr[data-assignment-row="1"]').forEach(function (tr) {
            const txt = (tr.textContent || '').toLowerCase();
            tr.style.display = txt.includes(q) ? '' : 'none';
          });
        });
      }

      // Modal System
      const modal = document.getElementById('vehicleModalS3');
      const backdrop = document.getElementById('vehicleModalS3Backdrop');
      const content = document.getElementById('vehicleModalS3Content');
      const body = document.getElementById('vehicleModalS3Body');
      const closeBtn = document.getElementById('vehicleModalS3Close');

      function openModal(html){ 
        body.innerHTML = html; 
        modal.classList.remove('hidden'); 
        requestAnimationFrame(() => {
            backdrop.classList.remove('opacity-0');
            content.classList.remove('scale-95', 'opacity-0');
        });
        if (window.lucide) window.lucide.createIcons(); 
      }

      function closeModal(){ 
        backdrop.classList.add('opacity-0');
        content.classList.add('scale-95', 'opacity-0');
        setTimeout(() => {
            modal.classList.add('hidden'); 
            body.innerHTML='';
        }, 300);
      }

      if(closeBtn) closeBtn.addEventListener('click', closeModal);
      if(backdrop) backdrop.addEventListener('click', closeModal);

      document.querySelectorAll('button[data-plate]').forEach(function(btn){
        btn.addEventListener('click', function(){
          var plate = this.getAttribute('data-plate');
          // Add loading state
          const originalIcon = this.innerHTML;
          this.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i>';
          if(window.lucide) window.lucide.createIcons();
          
          fetch('api/module1/view_html.php?plate='+encodeURIComponent(plate))
            .then(r => r.text())
            .then(html => {
                openModal(html);
                this.innerHTML = originalIcon;
                if(window.lucide) window.lucide.createIcons();
            })
            .catch(() => {
                showToast('Failed to load vehicle details', 'error');
                this.innerHTML = originalIcon;
                if(window.lucide) window.lucide.createIcons();
            });
        });
      });

      document.querySelectorAll('form[data-toggle-assignment="1"]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
          e.preventDefault();
          var fd = new FormData(form);
          var btn = form.querySelector('button');
          var original = btn ? btn.innerHTML : '';
          if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i>';
            if (window.lucide) window.lucide.createIcons();
          }
          fetch(form.action, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(function (data) {
              if (data && data.ok) {
                showToast('Assignment status updated.', 'success');
                setTimeout(function(){ window.location.reload(); }, 600);
              } else {
                var msg = (data && data.error) ? String(data.error) : 'Unable to update assignment.';
                if (msg === 'inspection_not_passed') msg = 'Cannot assign/toggle: inspection not passed.';
                if (msg === 'franchise_invalid') msg = 'Cannot assign/toggle: franchise is not endorsed.';
                showToast(msg, 'error');
              }
            })
            .catch(function () {
              showToast('Network error while updating assignment.', 'error');
            })
            .finally(function () {
              if (btn) {
                btn.disabled = false;
                btn.innerHTML = original;
                if (window.lucide) window.lucide.createIcons();
              }
            });
        });
      });
    })();
</script>
