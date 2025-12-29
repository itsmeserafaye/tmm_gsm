<div class="mx-1 mt-1 p-4 md:p-6 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 rounded-lg">
  <h1 class="text-2xl font-bold mb-2">Route & Terminal Assignment Management</h1>
  <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">Assign vehicles to LPTRP-approved routes and authorized terminals, enforcing capacity and eligibility.</p>

  <!-- Toast Notification Container -->
  <div id="toast-container" class="fixed bottom-4 right-4 z-50 flex flex-col gap-2 pointer-events-none"></div>

  <div class="p-6 border rounded-lg ring-1 ring-slate-200 dark:ring-slate-700 bg-white dark:bg-slate-900 border-t-4 border-t-green-500 shadow-sm mb-6">
    <h2 class="text-lg font-semibold mb-4 text-slate-800 dark:text-slate-100 flex items-center gap-2"><i data-lucide="map-pin" class="w-5 h-5 text-green-500"></i> Assign Route</h2>
    <form id="assignRouteForm" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-4" method="POST" action="/tmm/admin/api/module1/assign_route.php">
      <input name="plate_number" class="px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-green-500/20 focus:border-green-500 outline-none transition-all w-full uppercase" placeholder="Plate number" required>
      <input name="route_id" class="px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-green-500/20 focus:border-green-500 outline-none transition-all w-full uppercase" placeholder="Route ID" required>
      <select name="terminal_name" class="px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-green-500/20 focus:border-green-500 outline-none transition-all w-full">
        <option value="">Select Terminal</option>
        <option>Central Terminal</option>
        <option>East Hub</option>
        <option>North Bay</option>
        <option>West Yard</option>
        <option>South Station</option>
      </select>
      <select name="status" class="px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-green-500/20 focus:border-green-500 outline-none transition-all w-full">
        <option>Authorized</option>
        <option>Pending</option>
      </select>
      <div class="sm:col-span-2 md:col-span-1">
        <button type="submit" id="btnAssignRoute" class="flex items-center justify-center gap-2 px-6 py-2 rounded-lg bg-green-500 text-white w-full hover:bg-green-600 font-medium shadow-sm shadow-green-500/30 transition-colors">
          <span>Assign</span>
          <i data-lucide="check" class="w-4 h-4"></i>
        </button>
      </div>
    </form>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="p-6 border rounded-lg ring-1 ring-slate-200 dark:ring-slate-700 bg-white dark:bg-slate-900 border-t-4 border-t-blue-500 shadow-sm">
      <h3 class="text-md font-semibold mb-3 flex items-center gap-2"><i data-lucide="bar-chart-2" class="w-5 h-5 text-blue-500"></i> Route Capacity</h3>
      <?php
        require_once __DIR__ . '/../../includes/db.php';
        $db = db();
        $routeId = htmlspecialchars($_GET['route_id'] ?? 'R-12');
        $stmtR = $db->prepare("SELECT route_name, max_vehicle_limit FROM routes WHERE route_id=?");
        $stmtR->bind_param('s', $routeId);
        $stmtR->execute();
        $routeRow = $stmtR->get_result()->fetch_assoc();
        $cap = (int)($routeRow['max_vehicle_limit'] ?? 50);
        $routeName = $routeRow ? $routeRow['route_name'] : $routeId;
        $stmt = $db->prepare("SELECT COUNT(*) AS c FROM terminal_assignments WHERE route_id=?");
        $stmt->bind_param('s', $routeId);
        $stmt->execute();
        $cnt = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
        $pct = $cap > 0 ? min(100, round(($cnt / $cap) * 100)) : 0;
      ?>
      <div class="text-sm font-medium text-slate-700 dark:text-slate-300">Route <?php echo htmlspecialchars($routeId); ?> (<?php echo htmlspecialchars($routeName); ?>) • Max Vehicles: <?php echo $cap; ?></div>
      <div class="w-full rounded-full h-3 mt-3 overflow-hidden bg-slate-100 dark:bg-slate-800 ring-1 ring-slate-200 dark:ring-slate-700">
        <div class="h-full rounded-full bg-gradient-to-r from-blue-400 to-green-500 transition-all duration-500" style="width: <?php echo $pct; ?>%"></div>
      </div>
      <div class="text-xs mt-2 font-medium text-slate-500"><?php echo $cnt; ?>/<?php echo $cap; ?> assigned</div>
    </div>

    <div class="p-6 border rounded-lg ring-1 ring-slate-200 dark:ring-slate-700 bg-white dark:bg-slate-900 border-t-4 border-t-purple-500 shadow-sm">
      <h3 class="text-md font-semibold mb-3 flex items-center gap-2"><i data-lucide="home" class="w-5 h-5 text-purple-500"></i> Terminals for Route</h3>
      <ul class="text-sm space-y-2">
        <?php
          $stmt2 = $db->prepare("SELECT terminal_name, COUNT(*) AS c FROM terminal_assignments WHERE route_id=? GROUP BY terminal_name");
          $stmt2->bind_param('s', $routeId);
          $stmt2->execute();
          $res2 = $stmt2->get_result();
          while ($r = $res2->fetch_assoc()):
            $badge = 'bg-green-100 text-green-700 ring-1 ring-green-600/20';
            $label = 'Allowed';
        ?>
        <li class="flex items-center justify-between p-2 rounded bg-slate-50 dark:bg-slate-800/50">
          <span class="font-medium"><?php echo htmlspecialchars($r['terminal_name']); ?></span>
          <span class="px-2 py-0.5 rounded-full text-xs font-medium <?php echo $badge; ?>"><?php echo $label; ?></span>
        </li>
        <?php endwhile; ?>
      </ul>
    </div>
  </div>

  <div class="p-6 border rounded-lg ring-1 ring-slate-200 dark:ring-slate-700 bg-white dark:bg-slate-900 border-t-4 border-t-indigo-500 mt-6 shadow-sm">
    <h3 class="text-md font-semibold mb-4 flex items-center gap-2"><i data-lucide="settings" class="w-5 h-5 text-indigo-500"></i> Manage Routes</h3>
    <form id="saveRouteForm" class="grid grid-cols-1 md:grid-cols-5 gap-4" method="POST" action="/tmm/admin/api/routes/save.php">
      <input name="route_id" class="px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all uppercase" placeholder="Route ID" required>
      <input name="route_name" class="px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all" placeholder="Route Name" required>
      <input name="max_vehicle_limit" type="number" min="1" class="px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all" placeholder="Capacity" required>
      <select name="status" class="px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all">
        <option>Active</option>
        <option>Inactive</option>
      </select>
      <button type="submit" id="btnSaveRoute" class="flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-indigo-500 text-white hover:bg-indigo-600 font-medium shadow-sm shadow-indigo-500/30 transition-colors">
        <span>Save Route</span>
        <i data-lucide="save" class="w-4 h-4"></i>
      </button>
    </form>
    <div class="overflow-x-auto mt-6 rounded-lg ring-1 ring-slate-200 dark:ring-slate-700">
      <table class="min-w-full text-sm">
        <thead class="hidden md:table-header-group bg-slate-100 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700">
          <tr class="text-left text-slate-700 dark:text-slate-200">
            <th class="py-3 px-4 font-semibold text-xs uppercase tracking-wider">Route</th><th class="py-3 px-4 font-semibold text-xs uppercase tracking-wider">Name</th><th class="py-3 px-4 font-semibold text-xs uppercase tracking-wider">Capacity</th><th class="py-3 px-4 font-semibold text-xs uppercase tracking-wider">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
          <?php
            $routes = $db->query("SELECT route_id, route_name, max_vehicle_limit, status FROM routes ORDER BY route_id");
            while ($rt = $routes->fetch_assoc()):
          ?>
          <tr class="grid grid-cols-1 md:table-row gap-2 md:gap-0 p-2 md:p-0 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
            <td class="py-3 px-4"><span class="md:hidden font-semibold">Route: </span><span class="font-medium"><?php echo htmlspecialchars($rt['route_id']); ?></span></td>
            <td class="py-3 px-4"><span class="md:hidden font-semibold">Name: </span><?php echo htmlspecialchars($rt['route_name']); ?></td>
            <td class="py-3 px-4"><span class="md:hidden font-semibold">Capacity: </span><?php echo htmlspecialchars($rt['max_vehicle_limit']); ?></td>
            <td class="py-3 px-4"><span class="md:hidden font-semibold">Status: </span><?php echo htmlspecialchars($rt['status']); ?></td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="overflow-x-auto mt-8 rounded-xl ring-1 ring-slate-200 dark:ring-slate-700 bg-white dark:bg-slate-900 shadow-sm">
    <table class="min-w-full text-sm">
      <thead class="hidden md:table-header-group bg-slate-100 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700">
        <tr class="text-left text-slate-700 dark:text-slate-200">
          <th class="py-3 px-4 font-semibold text-xs uppercase tracking-wider">Plate</th>
          <th class="py-3 px-4 font-semibold text-xs uppercase tracking-wider">Route</th>
          <th class="py-3 px-4 font-semibold text-xs uppercase tracking-wider">Terminal</th>
          <th class="py-3 px-4 font-semibold text-xs uppercase tracking-wider">Status</th>
          <th class="py-3 px-4 font-semibold text-xs uppercase tracking-wider">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
        <?php
          $stmt3 = $db->prepare("SELECT ta.plate_number, ta.route_id, ta.terminal_name, ta.status FROM terminal_assignments ta WHERE ta.route_id=? ORDER BY ta.assigned_at DESC");
          $stmt3->bind_param('s', $routeId);
          $stmt3->execute();
          $res3 = $stmt3->get_result();
          while ($a = $res3->fetch_assoc()):
            $badge = $a['status'] === 'Authorized' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700';
        ?>
        <tr class="grid grid-cols-1 md:table-row gap-2 md:gap-0 p-2 md:p-0 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
          <td class="py-3 px-4"><span class="md:hidden font-semibold">Plate: </span><span class="font-medium text-slate-900 dark:text-slate-100"><?php echo htmlspecialchars($a['plate_number']); ?></span></td>
          <td class="py-3 px-4"><span class="md:hidden font-semibold">Route: </span><?php echo htmlspecialchars($a['route_id']); ?></td>
          <td class="py-3 px-4"><span class="md:hidden font-semibold">Terminal: </span><?php echo htmlspecialchars($a['terminal_name']); ?></td>
          <td class="py-3 px-4"><span class="md:hidden font-semibold">Status: </span><span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $badge; ?>"><?php echo htmlspecialchars($a['status']); ?></span></td>
          <td class="py-3 px-4">
            <div class="flex items-center gap-2">
              <button title="View Details" data-plate="<?php echo htmlspecialchars($a['plate_number']); ?>" class="p-2 rounded-full text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-colors"><i data-lucide="eye" class="w-5 h-5"></i><span class="sr-only">View</span></button>
              <a title="Transfer Ownership" href="?page=module1/submodule1#transfer-section" class="p-2 rounded-full text-orange-600 hover:bg-orange-50 dark:hover:bg-orange-900/30 transition-colors"><i data-lucide="repeat" class="w-5 h-5"></i><span class="sr-only">Transfer</span></a>
            </div>
            <form method="POST" action="/tmm/admin/api/module1/assign_route.php" class="flex flex-wrap items-center gap-2 mt-2 pt-2 border-t border-slate-100 dark:border-slate-800 md:border-0 md:pt-0 md:mt-0">
              <input type="hidden" name="plate_number" value="<?php echo htmlspecialchars($a['plate_number']); ?>">
              <input name="route_id" class="w-20 px-2 py-1 text-xs border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-1 focus:ring-green-500" placeholder="Route" value="<?php echo htmlspecialchars($a['route_id']); ?>">
              <input name="terminal_name" class="w-24 px-2 py-1 text-xs border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-1 focus:ring-green-500" placeholder="Terminal" value="<?php echo htmlspecialchars($a['terminal_name']); ?>">
              <select name="status" class="w-24 px-2 py-1 text-xs border rounded bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-1 focus:ring-green-500"><option <?php echo $a['status']==='Authorized'?'selected':''; ?>>Authorized</option><option <?php echo $a['status']!=='Authorized'?'selected':''; ?>>Pending</option></select>
              <button title="Update Assignment" class="p-1.5 rounded-full text-green-600 hover:bg-green-50 dark:hover:bg-green-900/30 transition-colors"><i data-lucide="check" class="w-4 h-4"></i><span class="sr-only">Update</span></button>
            </form>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
<div id="vehicleModalS3" class="fixed inset-0 z-50 hidden">
  <div class="absolute inset-0 bg-black/50"></div>
  <div class="absolute inset-0 flex items-center justify-center p-4">
    <div class="w-[95vw] max-w-5xl bg-white dark:bg-slate-900 rounded-xl shadow-lg border border-slate-200 dark:border-slate-700">
      <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700">
        <div class="text-lg font-semibold">Vehicle Details</div>
        <button id="vehicleModalS3Close" class="p-2 rounded hover:bg-slate-100 dark:hover:bg-slate-800"><i data-lucide="x" class="w-5 h-5"></i></button>
      </div>
      <div id="vehicleModalS3Body" class="p-6 max-h-[75vh] overflow-y-auto"></div>
    </div>
  </div>
</div>
<script>
    (function(){
      // Toast System
      function showToast(msg, type = 'success') {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        const colors = type === 'success' ? 'bg-green-500' : 'bg-red-500';
        const icon = type === 'success' ? 'check-circle' : 'alert-circle';
        
        toast.className = `${colors} text-white px-4 py-3 rounded-lg shadow-lg flex items-center gap-3 transform transition-all duration-300 translate-y-10 opacity-0 min-w-[300px]`;
        toast.innerHTML = `
          <i data-lucide="${icon}" class="w-5 h-5"></i>
          <span class="font-medium text-sm">${msg}</span>
        `;
        
        container.appendChild(toast);
        if (window.lucide) window.lucide.createIcons();
        requestAnimationFrame(() => toast.classList.remove('translate-y-10', 'opacity-0'));
        setTimeout(() => { toast.classList.add('opacity-0', 'translate-x-full'); setTimeout(() => toast.remove(), 300); }, 3000);
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
          btn.innerHTML = `<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Processing...`;
          if (window.lucide) window.lucide.createIcons();

          try {
            const formData = new FormData(form);
            const res = await fetch(form.action, { method: 'POST', body: formData });
            const data = await res.json();
            
            if (data.ok || data.status === 'success' || (Array.isArray(data) && data.length > 0)) {
              showToast(successMsg);
              form.reset();
              setTimeout(() => location.reload(), 1000);
            } else {
              throw new Error(data.error || 'Operation failed');
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
      handleForm('saveRouteForm', 'btnSaveRoute', 'Route saved successfully!');

      // Modal (Existing)
      var modal = document.getElementById('vehicleModal');
      var body = document.getElementById('vehicleModalBody');
      var closeBtn = document.getElementById('vehicleModalClose');
      function openModal(html){ body.innerHTML = html; modal.classList.remove('hidden'); if (window.lucide && window.lucide.createIcons) window.lucide.createIcons(); }
      function closeModal(){ modal.classList.add('hidden'); body.innerHTML=''; }
      if(closeBtn) closeBtn.addEventListener('click', closeModal);
      if(modal) modal.addEventListener('click', function(e){ if (e.target === modal || e.target.classList.contains('bg-black/50')) closeModal(); });
      document.querySelectorAll('button[data-plate]').forEach(function(btn){
        btn.addEventListener('click', function(){
          var plate = this.getAttribute('data-plate');
          fetch('api/module1/view_html.php?plate='+encodeURIComponent(plate)).then(function(r){ return r.text(); }).then(function(html){ openModal(html); });
        });
      });
    })();
  </script>
