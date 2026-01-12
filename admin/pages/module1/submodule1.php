<div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8 space-y-8">
  <!-- Header -->
  <div class="sm:flex sm:items-center sm:justify-between">
    <div>
      <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">Vehicle & Ownership Registry</h1>
      <p class="mt-2 text-sm text-slate-500 dark:text-slate-400 max-w-2xl">Manage PUV master records, OR/CR document storage, ownership details, transfers, and status tracking.</p>
    </div>
  </div>

  <?php
    require_once __DIR__ . '/../../includes/db.php';
    $db = db();
    $prefillFrRef = trim($_GET['fr_ref'] ?? '');
    $prefillOperator = trim($_GET['op'] ?? '');
  ?>

  <!-- Stats Overview -->
  <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
    <?php
      $total = $db->query("SELECT COUNT(*) AS c FROM vehicles")->fetch_assoc()['c'] ?? 0;
      $act = $db->query("SELECT COUNT(*) AS c FROM vehicles WHERE status='Active'")->fetch_assoc()['c'] ?? 0;
      $sus = $db->query("SELECT COUNT(*) AS c FROM vehicles WHERE status='Suspended'")->fetch_assoc()['c'] ?? 0;
    ?>
    <!-- Stat Card 1 -->
    <div class="overflow-hidden rounded-xl bg-white dark:bg-slate-900 shadow ring-1 ring-slate-900/5 dark:ring-white/10">
      <div class="p-6">
        <div class="flex items-center">
          <div class="flex-shrink-0 p-3 rounded-lg bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400">
             <i data-lucide="bus" class="h-6 w-6"></i>
          </div>
          <div class="ml-5 w-0 flex-1">
            <dl>
              <dt class="truncate text-sm font-medium text-slate-500 dark:text-slate-400">Total Vehicles</dt>
              <dd class="mt-1 text-3xl font-semibold tracking-tight text-slate-900 dark:text-slate-100"><?php echo (int)$total; ?></dd>
            </dl>
          </div>
        </div>
      </div>
    </div>
    <!-- Stat Card 2 -->
    <div class="overflow-hidden rounded-xl bg-white dark:bg-slate-900 shadow ring-1 ring-slate-900/5 dark:ring-white/10">
      <div class="p-6">
        <div class="flex items-center">
          <div class="flex-shrink-0 p-3 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400">
             <i data-lucide="check-circle-2" class="h-6 w-6"></i>
          </div>
          <div class="ml-5 w-0 flex-1">
            <dl>
              <dt class="truncate text-sm font-medium text-slate-500 dark:text-slate-400">Active Units</dt>
              <dd class="mt-1 text-3xl font-semibold tracking-tight text-emerald-600 dark:text-emerald-400"><?php echo (int)$act; ?></dd>
            </dl>
          </div>
        </div>
      </div>
    </div>
    <!-- Stat Card 3 -->
    <div class="overflow-hidden rounded-xl bg-white dark:bg-slate-900 shadow ring-1 ring-slate-900/5 dark:ring-white/10">
      <div class="p-6">
        <div class="flex items-center">
          <div class="flex-shrink-0 p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400">
             <i data-lucide="alert-triangle" class="h-6 w-6"></i>
          </div>
          <div class="ml-5 w-0 flex-1">
            <dl>
              <dt class="truncate text-sm font-medium text-slate-500 dark:text-slate-400">Suspended</dt>
              <dd class="mt-1 text-3xl font-semibold tracking-tight text-amber-600 dark:text-amber-400"><?php echo (int)$sus; ?></dd>
            </dl>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Filters & Actions Toolbar -->
  <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between bg-white dark:bg-slate-900 p-4 rounded-xl shadow ring-1 ring-slate-900/5 dark:ring-white/10">
    <form id="filterForm" class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center" method="GET">
       <!-- Search Input -->
       <div class="relative max-w-xs w-full">
         <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
           <i data-lucide="search" class="h-4 w-4 text-slate-400"></i>
         </div>
         <input name="q" id="searchInput" class="block w-full rounded-lg border-0 bg-slate-50 dark:bg-slate-800 py-2 pl-10 pr-3 text-slate-900 dark:text-slate-200 ring-1 ring-inset ring-slate-200 dark:ring-slate-700 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6" placeholder="Search plate or operator..." value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">
       </div>

       <?php require_once __DIR__ . '/../../includes/vehicle_types.php'; $types = vehicle_types(); ?>
       <!-- Type Select -->
       <div class="w-full sm:w-48">
         <select name="vehicle_type" id="typeSelect" class="block w-full rounded-lg border-0 bg-slate-50 dark:bg-slate-800 py-2 pl-3 pr-10 text-slate-900 dark:text-slate-200 ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6">
            <option value="">All Types</option>
            <?php foreach ($types as $t): ?>
              <option value="<?php echo htmlspecialchars($t); ?>" <?php echo (($_GET['vehicle_type'] ?? '')===$t)?'selected':''; ?>><?php echo htmlspecialchars($t); ?></option>
            <?php endforeach; ?>
         </select>
       </div>

       <!-- Status Select -->
       <div class="w-full sm:w-40">
          <select name="status" id="statusSelect" class="block w-full rounded-lg border-0 bg-slate-50 dark:bg-slate-800 py-2 pl-3 pr-10 text-slate-900 dark:text-slate-200 ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6">
            <option value="">All Status</option>
            <option value="Active" <?php echo (($_GET['status'] ?? '')==='Active')?'selected':''; ?>>Active</option>
            <option value="Suspended" <?php echo (($_GET['status'] ?? '')==='Suspended')?'selected':''; ?>>Suspended</option>
            <option value="Deactivated" <?php echo (($_GET['status'] ?? '')==='Deactivated')?'selected':''; ?>>Deactivated</option>
          </select>
       </div>
       <div class="w-full sm:w-64">
         <label class="flex items-center gap-2 text-xs font-medium text-slate-600 dark:text-slate-300">
           <input
             type="checkbox"
             name="awaiting_endorsement"
             value="1"
             <?php echo (($_GET['awaiting_endorsement'] ?? '')==='1')?'checked':''; ?>
             class="h-4 w-4 rounded border-slate-300 text-amber-600 focus:ring-amber-500"
           >
           <span>Show only suspended vehicles awaiting franchise endorsement</span>
         </label>
       </div>
    </form>

    <div id="tableActionsToolbar" class="flex items-center gap-3 border-t lg:border-t-0 border-slate-200 dark:border-slate-700 pt-3 lg:pt-0">
       <button id="openUploadDocsModalBtn" type="button" class="inline-flex items-center gap-x-2 rounded-lg bg-white dark:bg-slate-800 px-3.5 py-2.5 text-sm font-semibold text-slate-900 dark:text-slate-200 shadow-sm ring-1 ring-inset ring-slate-300 dark:ring-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
         <i data-lucide="upload-cloud" class="-ml-0.5 h-4 w-4 text-slate-400"></i>
         Upload Docs
       </button>
       <button id="openCreateVehicleModalBtn" type="button" class="inline-flex items-center gap-x-2 rounded-lg bg-blue-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 transition-colors">
         <i data-lucide="plus" class="-ml-0.5 h-4 w-4"></i>
         New Vehicle
       </button>
    </div>
  </div>

  <!-- Data Table -->
  <div id="vehicleTableContainer" class="overflow-hidden rounded-xl bg-white dark:bg-slate-900 shadow ring-1 ring-slate-900/5 dark:ring-white/10">
     <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
           <thead class="bg-slate-50 dark:bg-slate-800">
              <tr>
                 <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 sm:pl-6">Plate</th>
                 <th scope="col" class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Type</th>
                 <th scope="col" class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Operator / COOP</th>
                 <th scope="col" class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Franchise / Route</th>
                 <th scope="col" class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Status</th>
                 <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                    <span class="sr-only">Actions</span>
                 </th>
              </tr>
           </thead>
           <tbody class="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-900">
             <?php
                $q = trim($_GET['q'] ?? '');
                $status = trim($_GET['status'] ?? '');
                $awaiting = trim($_GET['awaiting_endorsement'] ?? '');
                $sql = "SELECT v.plate_number, v.vehicle_type, v.operator_name, v.coop_name, v.franchise_id, v.route_id, v.status, fa.status AS franchise_status FROM vehicles v LEFT JOIN franchise_applications fa ON v.franchise_id = fa.franchise_ref_number";
                $conds = ["v.plate_number NOT LIKE 'TEST-%'"];
                $params = [];
                $typesStr = '';
                if ($q !== '') { $conds[] = "(v.plate_number LIKE ? OR v.operator_name LIKE ?)"; $params[] = "%$q%"; $params[] = "%$q%"; $typesStr .= 'ss'; }
                $vehicleType = trim($_GET['vehicle_type'] ?? '');
                if ($status !== '' && $status !== 'Status') { $conds[] = "v.status=?"; $params[] = $status; $typesStr .= 's'; }
                if ($vehicleType !== '' && $vehicleType !== 'Vehicle type') { $conds[] = "v.vehicle_type=?"; $params[] = $vehicleType; $typesStr .= 's'; }
                if ($awaiting === '1') {
                  $conds[] = "(v.status='Suspended' AND ((v.franchise_id <> '' AND fa.status IS NULL) OR fa.status IN ('Pending','Rejected','Cancelled') OR v.franchise_id IS NULL OR v.franchise_id=''))";
                }
                if ($conds) { $sql .= " WHERE " . implode(" AND ", $conds); }
                $sql .= " ORDER BY v.created_at DESC";
                if ($params) { $stmt = $db->prepare($sql); $stmt->bind_param($typesStr, ...$params); $stmt->execute(); $res = $stmt->get_result(); } else { $res = $db->query($sql); }
                while ($row = $res->fetch_assoc()):
             ?>
              <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                 <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-slate-900 dark:text-white sm:pl-6">
                    <?php echo htmlspecialchars($row['plate_number']); ?>
                 </td>
                 <td class="whitespace-nowrap px-3 py-4 text-sm text-slate-500 dark:text-slate-400">
                    <span class="inline-flex items-center rounded-md bg-slate-50 dark:bg-slate-800 px-2 py-1 text-xs font-medium text-slate-700 dark:text-slate-300 ring-1 ring-inset ring-slate-600/20">
                      <?php echo htmlspecialchars($row['vehicle_type']); ?>
                    </span>
                 </td>
                 <td class="px-3 py-4 text-sm text-slate-500 dark:text-slate-400">
                    <div class="font-medium text-slate-900 dark:text-slate-200"><?php echo htmlspecialchars($row['operator_name']); ?></div>
                    <?php if($row['coop_name']): ?><div class="text-xs text-slate-500 mt-0.5"><?php echo htmlspecialchars($row['coop_name']); ?></div><?php endif; ?>
                 </td>
                <td class="px-3 py-4 text-sm text-slate-500 dark:text-slate-400">
                   <div class="flex flex-col gap-0.5">
                       <div class="flex items-center gap-1.5"><span class="text-[10px] uppercase text-slate-400 font-semibold tracking-wider min-w-[30px]">ID</span> <span class="text-slate-700 dark:text-slate-300"><?php echo htmlspecialchars($row['franchise_id'] ?? '-'); ?></span></div>
                       <div class="flex items-center gap-1.5"><span class="text-[10px] uppercase text-slate-400 font-semibold tracking-wider min-w-[30px]">RT</span> <span class="text-slate-700 dark:text-slate-300"><?php echo htmlspecialchars($row['route_id'] ?? '-'); ?></span></div>
                       <?php
                          $fs = $row['franchise_status'] ?? null;
                          if (!empty($row['franchise_id'])) {
                             $fsLabel = $fs ?: 'No record';
                             $fsClass = match($fs) {
                                'Endorsed' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-900/20 dark:text-emerald-400 dark:ring-emerald-500/20',
                                'Pending' => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-900/20 dark:text-amber-400 dark:ring-amber-500/20',
                                'Rejected', 'Cancelled' => 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-900/20 dark:text-red-400 dark:ring-red-500/20',
                                default => 'bg-slate-50 text-slate-700 ring-slate-600/20 dark:bg-slate-800 dark:text-slate-400'
                             };
                             echo '<div class="flex items-center gap-1.5"><span class="text-[10px] uppercase text-slate-400 font-semibold tracking-wider min-w-[30px]">FR</span><span class="inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset '.$fsClass.'">'.htmlspecialchars($fsLabel).'</span></div>';
                          }
                       ?>
                   </div>
                </td>
                 <td class="whitespace-nowrap px-3 py-4 text-sm">
                    <?php
                       $st = (string)($row['status'] ?? '');
                       $fs = (string)($row['franchise_status'] ?? '');
                       $statusLabel = $st !== '' ? $st : 'Unknown';
                       $statusHint = '';
                       if ($st === 'Suspended' && $fs !== 'Endorsed') {
                         if ($fs === '' && $row['franchise_id'] !== '') {
                           $statusHint = 'No matching endorsed franchise record found.';
                         } elseif ($fs === 'Pending') {
                           $statusHint = 'Franchise application is still pending endorsement.';
                         } elseif (in_array($fs, ['Rejected', 'Cancelled'], true)) {
                           $statusHint = 'Franchise application is not endorsed ('.$fs.').';
                         } elseif ($row['franchise_id'] === '' || $row['franchise_id'] === null) {
                           $statusHint = 'No franchise is linked to this vehicle.';
                         } else {
                           $statusHint = 'Vehicle is suspended until franchise is endorsed.';
                         }
                       } elseif ($st === 'Active' && $fs === 'Endorsed') {
                         $statusHint = 'Vehicle is active under an endorsed franchise.';
                       }
                       $badgeClass = match($st) {
                          'Active' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-900/20 dark:text-emerald-400 dark:ring-emerald-500/20',
                          'Suspended' => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-900/20 dark:text-amber-400 dark:ring-amber-500/20',
                          'Deactivated' => 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-900/20 dark:text-red-400 dark:ring-red-500/20',
                          default => 'bg-slate-50 text-slate-700 ring-slate-600/20 dark:bg-slate-800 dark:text-slate-400'
                       };
                    ?>
                    <div class="flex items-center gap-2">
                      <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset <?php echo $badgeClass; ?>">
                        <?php echo htmlspecialchars($statusLabel); ?>
                      </span>
                      <?php if ($statusHint !== ''): ?>
                        <button
                          type="button"
                          class="inline-flex items-center justify-center rounded-full border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 h-6 w-6"
                          title="<?php echo htmlspecialchars($statusHint); ?>"
                        >
                          <span class="text-[10px] font-semibold">i</span>
                        </button>
                      <?php endif; ?>
                    </div>
                 </td>
                 <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                    <div class="flex items-center justify-end gap-2">
                       <button title="View Details" data-plate="<?php echo htmlspecialchars($row['plate_number']); ?>" class="p-1.5 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-all">
                          <i data-lucide="eye" class="h-4 w-4"></i>
                       </button>
                       <a title="Assign Route" href="?page=module1/submodule3&plate=<?php echo urlencode($row['plate_number']); ?>&route_id=<?php echo urlencode($row['route_id'] ?? ''); ?>" class="p-1.5 rounded-lg text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-all">
                          <i data-lucide="map-pin" class="h-4 w-4"></i>
                       </a>
                       <button title="Transfer Ownership" data-transfer-plate="<?php echo htmlspecialchars($row['plate_number']); ?>" class="p-1.5 rounded-lg text-slate-400 hover:text-orange-600 hover:bg-orange-50 dark:hover:bg-orange-900/20 transition-all">
                          <i data-lucide="refresh-cw" class="h-4 w-4"></i>
                       </button>
                    </div>
                 </td>
              </tr>
              <?php endwhile; ?>
           </tbody>
        </table>
     </div>
  </div>

  <!-- Toast Container -->
  <div id="toast-container" class="fixed bottom-6 right-6 z-[100] flex flex-col gap-3 pointer-events-none"></div>

  <!-- Loading Indicator -->
  <div id="loading-indicator" class="fixed top-6 right-6 z-50 hidden transition-all duration-300">
    <div class="bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 px-4 py-3 rounded-xl shadow-lg border border-slate-200 dark:border-slate-700 flex items-center gap-3">
      <i data-lucide="loader-2" class="w-5 h-5 animate-spin text-blue-600"></i>
      <span class="font-medium text-sm">Processing...</span>
    </div>
  </div>

  <!-- Create Vehicle Modal -->
  <div id="createVehicleModal" class="fixed inset-0 z-50 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-slate-900/75 backdrop-blur-sm transition-opacity"></div>
    <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
      <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
        <div class="relative transform overflow-hidden rounded-xl bg-white dark:bg-slate-900 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-slate-200 dark:border-slate-700">
           <!-- Header -->
           <div class="bg-slate-50 dark:bg-slate-800/50 px-4 py-3 sm:px-6 flex items-center justify-between border-b border-slate-200 dark:border-slate-700">
             <h3 class="text-base font-semibold leading-6 text-slate-900 dark:text-white" id="modal-title">New Vehicle Record</h3>
             <button id="createVehicleModalClose" class="text-slate-400 hover:text-slate-500 transition-colors">
               <i data-lucide="x" class="h-5 w-5"></i>
             </button>
           </div>
           
           <form id="createVehicleForm" class="p-6 space-y-5" novalidate method="POST" action="/tmm/admin/api/module1/create_vehicle.php">
              <div>
                <label class="block text-sm font-medium leading-6 text-slate-900 dark:text-slate-200">Plate Number</label>
                <div class="mt-2 relative">
                  <input name="plate_number" id="cv_plate" class="block w-full rounded-lg border-0 bg-slate-50 dark:bg-slate-800 py-2.5 text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-300 dark:ring-slate-700 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6 uppercase placeholder:normal-case transition-shadow" placeholder="ABC-1234" pattern="^[A-Z0-9-]{6,10}$" required>
                  <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 hidden" id="cv_plate_error_icon">
                     <i data-lucide="alert-circle" class="h-5 w-5 text-red-500"></i>
                  </div>
                </div>
                <p class="mt-2 text-xs text-red-600 hidden" id="cv_plate_error">Invalid plate format (e.g., ABC-1234)</p>
              </div>

              <div class="grid grid-cols-2 gap-5">
                 <div>
                   <label class="block text-sm font-medium leading-6 text-slate-900 dark:text-slate-200">Type</label>
                   <div class="mt-2">
                     <?php $types = vehicle_types(); ?>
                     <select name="vehicle_type" class="block w-full rounded-lg border-0 bg-slate-50 dark:bg-slate-800 py-2.5 text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-300 dark:ring-slate-700 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6" required>
                        <option value="">Select...</option>
                        <?php foreach ($types as $t): ?>
                          <option value="<?php echo htmlspecialchars($t); ?>"><?php echo htmlspecialchars($t); ?></option>
                        <?php endforeach; ?>
                     </select>
                   </div>
                 </div>
                 <div>
                   <label class="block text-sm font-medium leading-6 text-slate-900 dark:text-slate-200">Status</label>
                   <div class="mt-2">
                     <div class="block w-full rounded-lg border border-dashed border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 py-2.5 px-3 text-xs text-slate-600 dark:text-slate-300">
                       Status is automatically managed. New records start as Suspended until endorsed.
                     </div>
                   </div>
                 </div>
              </div>

              <div>
                 <label class="block text-sm font-medium leading-6 text-slate-900 dark:text-slate-200">Operator Name</label>
                 <div class="mt-2">
                  <input name="operator_name" value="<?php echo htmlspecialchars($prefillOperator); ?>" class="block w-full rounded-lg border-0 bg-slate-50 dark:bg-slate-800 py-2.5 text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-300 dark:ring-slate-700 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6" placeholder="Full Name" required>
                 </div>
              </div>

              <div class="grid grid-cols-2 gap-5">
                 <div>
                 <label class="block text-sm font-medium leading-6 text-slate-900 dark:text-slate-200">Franchise No.</label>
                  <div class="mt-2">
                     <input name="franchise_id" value="<?php echo htmlspecialchars($prefillFrRef); ?>" class="block w-full rounded-lg border-0 bg-slate-50 dark:bg-slate-800 py-2.5 text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-300 dark:ring-slate-700 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6 uppercase" placeholder="2024-00123">
                     <p id="cv_franchise_status" class="mt-1 text-xs text-slate-500 dark:text-slate-400"></p>
                  </div>
                 </div>
                 <div>
                    <label class="block text-sm font-medium leading-6 text-slate-900 dark:text-slate-200">Route ID</label>
                    <div class="mt-2">
                      <?php
                        if (!isset($db)) {
                          require_once __DIR__ . '/../../includes/db.php';
                          $db = db();
                        }
                        $routesForVehicleForm = $db->query("SELECT route_id, route_name FROM routes ORDER BY route_id");
                      ?>
                      <input name="route_id" id="cv_route" list="vehicle-route-list" class="block w-full rounded-lg border-0 bg-slate-50 dark:bg-slate-800 py-2.5 text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-300 dark:ring-slate-700 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6 uppercase" placeholder="R_001">
                      <datalist id="vehicle-route-list">
                        <?php if ($routesForVehicleForm): ?>
                          <?php while ($r = $routesForVehicleForm->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($r['route_id']); ?>"><?php echo htmlspecialchars($r['route_id'] . ' — ' . $r['route_name']); ?></option>
                          <?php endwhile; ?>
                        <?php endif; ?>
                      </datalist>
                      <p id="cv_route_status" class="mt-1 text-xs text-slate-500 dark:text-slate-400">Pick a valid route ID from the list.</p>
                    </div>
                 </div>
              </div>

              <div class="mt-6 flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                 <button type="button" id="createCancelBtn" class="px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">Cancel</button>
                 <button type="submit" id="btnSaveVehicle" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-500 shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 transition-all">
                    <span>Save Record</span>
                    <i data-lucide="save" class="h-4 w-4"></i>
                 </button>
              </div>
           </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Upload Docs Modal -->
  <div id="uploadDocsModal" class="fixed inset-0 z-50 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-slate-900/75 backdrop-blur-sm transition-opacity"></div>
    <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
      <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
        <div class="relative transform overflow-hidden rounded-xl bg-white dark:bg-slate-900 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-slate-200 dark:border-slate-700">
           <div class="bg-slate-50 dark:bg-slate-800/50 px-4 py-3 sm:px-6 flex items-center justify-between border-b border-slate-200 dark:border-slate-700">
             <h3 class="text-base font-semibold leading-6 text-slate-900 dark:text-white" id="modal-title">Upload Documents</h3>
             <button id="uploadDocsModalClose" class="text-slate-400 hover:text-slate-500 transition-colors">
               <i data-lucide="x" class="h-5 w-5"></i>
             </button>
           </div>
           
           <form id="uploadDocsForm" class="p-6 space-y-5" method="POST" action="/tmm/admin/api/module1/upload_docs.php" enctype="multipart/form-data">
              <div>
                <label class="block text-sm font-medium leading-6 text-slate-900 dark:text-slate-200">Target Vehicle</label>
                <div class="mt-2">
                   <input name="plate_number" id="plateSearchInput" list="platesList" class="block w-full rounded-lg border-0 bg-slate-50 dark:bg-slate-800 py-2.5 text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-300 dark:ring-slate-700 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6 uppercase" placeholder="Search Plate..." autocomplete="off" required>
                   <datalist id="platesList"></datalist>
                </div>
              </div>

              <div class="space-y-4">
                 <div class="group">
                    <label class="block text-sm font-medium leading-6 text-slate-700 dark:text-slate-300 mb-2">OR Document</label>
                    <input name="or" type="file" accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-blue-900/20 dark:file:text-blue-400 transition-all cursor-pointer">
                 </div>
                 <div class="group">
                    <label class="block text-sm font-medium leading-6 text-slate-700 dark:text-slate-300 mb-2">CR Document</label>
                    <input name="cr" type="file" accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-blue-900/20 dark:file:text-blue-400 transition-all cursor-pointer">
                 </div>
                 <div class="group">
                    <label class="block text-sm font-medium leading-6 text-slate-700 dark:text-slate-300 mb-2">Deed of Sale</label>
                    <input name="deed" type="file" accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-blue-900/20 dark:file:text-blue-400 transition-all cursor-pointer">
                 </div>
              </div>

              <?php if (getenv('TMM_AV_SCANNER')): ?>
              <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Files are scanned for viruses when uploaded.</p>
              <?php endif; ?>

              <div class="mt-6 flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                 <button type="button" id="uploadCancelBtn" class="px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">Cancel</button>
                 <button type="submit" id="btnUploadDocs" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-500 shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 transition-all">
                    <span>Upload Files</span>
                    <i data-lucide="upload" class="h-4 w-4"></i>
                 </button>
              </div>
           </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Transfer Ownership Modal -->
  <div id="transferModal" class="fixed inset-0 z-50 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-slate-900/75 backdrop-blur-sm transition-opacity"></div>
    <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
      <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
        <div class="relative transform overflow-hidden rounded-xl bg-white dark:bg-slate-900 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-slate-200 dark:border-slate-700">
           <div class="bg-slate-50 dark:bg-slate-800/50 px-4 py-3 sm:px-6 flex items-center justify-between border-b border-slate-200 dark:border-slate-700">
             <h3 class="text-base font-semibold leading-6 text-slate-900 dark:text-white" id="modal-title">Transfer Ownership</h3>
             <button id="transferModalClose" class="text-slate-400 hover:text-slate-500 transition-colors">
               <i data-lucide="x" class="h-5 w-5"></i>
             </button>
           </div>

           <form id="transferModalForm" class="p-6 space-y-5" method="POST" action="/tmm/admin/api/module1/transfer_ownership.php">
              <input type="hidden" name="plate_number" id="transferPlateInput">
              
              <div class="bg-orange-50 dark:bg-orange-900/10 p-4 rounded-lg border border-orange-100 dark:border-orange-900/20">
                 <div class="text-xs font-medium text-orange-600 dark:text-orange-400 uppercase tracking-wide mb-1">Vehicle Plate</div>
                 <div id="transferModalPlateLabel" class="text-2xl font-bold text-orange-700 dark:text-orange-300 uppercase tracking-tight"></div>
              </div>

              <div>
                <label class="block text-sm font-medium leading-6 text-slate-900 dark:text-slate-200">New Operator Name</label>
                <div class="mt-2">
                   <input name="new_operator_name" class="block w-full rounded-lg border-0 bg-slate-50 dark:bg-slate-800 py-2.5 text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-300 dark:ring-slate-700 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-orange-600 sm:text-sm sm:leading-6" placeholder="Full Name" required>
                </div>
              </div>

              <div>
                <label class="block text-sm font-medium leading-6 text-slate-900 dark:text-slate-200">Deed of Sale Reference</label>
                <div class="mt-2">
                   <input name="deed_ref" id="deedRefInput" list="deedRefsList" class="block w-full rounded-lg border-0 bg-slate-50 dark:bg-slate-800 py-2.5 text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-300 dark:ring-slate-700 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-orange-600 sm:text-sm sm:leading-6" placeholder="Doc Ref #" autocomplete="off" required>
                   <datalist id="deedRefsList"></datalist>
                </div>
              </div>

              <div class="mt-6 flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                 <button type="button" id="transferCancelBtn" class="px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">Cancel</button>
                 <button type="submit" id="btnTransferModal" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-orange-600 rounded-lg hover:bg-orange-500 shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orange-600 transition-all">
                    <span>Transfer</span>
                    <i data-lucide="arrow-right" class="h-4 w-4"></i>
                 </button>
              </div>
           </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Vehicle Details Modal (Large) -->
  <div id="vehicleModal" class="fixed inset-0 z-50 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-slate-900/75 backdrop-blur-sm transition-opacity"></div>
    <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
      <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative transform overflow-hidden rounded-xl bg-white dark:bg-slate-900 text-left shadow-xl transition-all sm:w-full sm:max-w-5xl border border-slate-200 dark:border-slate-700 flex flex-col max-h-[90vh]">
           <div class="bg-slate-50 dark:bg-slate-800/50 px-4 py-3 sm:px-6 flex items-center justify-between border-b border-slate-200 dark:border-slate-700 flex-shrink-0">
             <h3 class="text-base font-semibold leading-6 text-slate-900 dark:text-white">Vehicle Details</h3>
             <button id="vehicleModalClose" class="text-slate-400 hover:text-slate-500 transition-colors">
               <i data-lucide="x" class="h-5 w-5"></i>
             </button>
           </div>
           <div id="vehicleModalBody" class="p-6 overflow-y-auto"></div>
        </div>
      </div>
    </div>
  </div>

  <script>
    (function(){
      // Toast System
      function showToast(msg, type = 'success') {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        const colors = type === 'success' ? 'bg-emerald-600 text-white' : 'bg-red-600 text-white';
        const icon = type === 'success' ? 'check-circle' : 'alert-circle';
        
        toast.className = `${colors} px-4 py-3 rounded-xl shadow-lg flex items-center gap-3 transform transition-all duration-300 translate-y-10 opacity-0 min-w-[320px]`;
        toast.innerHTML = `
          <i data-lucide="${icon}" class="w-5 h-5"></i>
          <span class="font-medium text-sm">${msg}</span>
        `;
        
        container.appendChild(toast);
        if (window.lucide) window.lucide.createIcons();

        // Animate in
        requestAnimationFrame(() => {
          toast.classList.remove('translate-y-10', 'opacity-0');
        });

        // Remove after 3s
        setTimeout(() => {
          toast.classList.add('opacity-0', 'translate-x-full');
          setTimeout(() => toast.remove(), 300);
        }, 3000);
      }

      // Input Masking & Validation
      const plateInput = document.getElementById('cv_plate');
      const plateError = document.getElementById('cv_plate_error');
      
      if(plateInput) {
        plateInput.addEventListener('input', function(e) {
          let raw = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
          if (raw.length >= 4 && raw.indexOf('-') === -1) {
            raw = raw.slice(0, 3) + '-' + raw.slice(3);
          }
          this.value = raw;
          const regex = /^[A-Z0-9-]{6,10}$/;
          if(this.value.length > 0 && !regex.test(this.value)) {
            plateError.classList.remove('hidden');
            this.classList.add('ring-red-500', 'focus:ring-red-500');
            this.classList.remove('focus:ring-blue-600');
          } else {
            plateError.classList.add('hidden');
            this.classList.remove('ring-red-500', 'focus:ring-red-500');
            this.classList.add('focus:ring-blue-600');
          }
        });
      }

      function mapCreateVehicleError(code) {
        var c = (code || '').toString();
        switch (c) {
          case 'invalid_plate':
            return 'Invalid plate format. Example: ABC-1234.';
          case 'missing_fields':
            return 'Vehicle type and operator name are required.';
          case 'route_not_found':
            return 'Route ID not found in Routes registry.';
          case 'route_inactive':
            return 'Route exists but is not Active. Please pick an Active route.';
          case 'route_validation_failed':
            return 'Could not validate route. Please try again.';
          default:
            return 'Error: ' + c;
        }
      }

      // Generic Form Handler
      function handleForm(formId, btnId, successMsg) {
        const form = document.getElementById(formId);
        const btn = document.getElementById(btnId);
        if(!form || !btn) return;

        form.addEventListener('submit', async function(e) {
          e.preventDefault();
          
          // HTML5 Validation
          if (!form.checkValidity()) {
            form.reportValidity();
            return;
          }

          // Custom Validation (e.g. Plate)
          if(formId === 'createVehicleForm') {
            const plate = document.getElementById('cv_plate').value;
            if(!/^[A-Z0-9-]{6,10}$/.test(plate)) {
              showToast('Invalid Plate Number Format', 'error');
              return;
            }
          }

          // UI Loading State
          const originalContent = btn.innerHTML;
          btn.disabled = true;
          btn.innerHTML = `<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Processing...`;
          if (window.lucide) window.lucide.createIcons();

          try {
            const formData = new FormData(form);
            const res = await fetch(form.action, {
              method: 'POST',
              body: formData
            });
            
            const data = await res.json();
            
            const ok = data && (data.ok || data.status === 'success' || (Array.isArray(data) && data.length > 0));
            if (ok) {
              showToast(successMsg);
              form.reset();
              // Optional: Reload table content dynamically here
              setTimeout(() => location.reload(), 1000); 
            } else {
              let errMsg = (data && data.error) ? data.error : 'Operation failed';
              if (formId === 'createVehicleForm') {
                errMsg = mapCreateVehicleError(errMsg);
              }
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

      // Init Handlers
      handleForm('createVehicleForm', 'btnSaveVehicle', 'Vehicle saved successfully!');
      handleForm('uploadDocsForm', 'btnUploadDocs', 'Documents uploaded successfully!');
      handleForm('transferModalForm', 'btnTransferModal', 'Ownership transferred successfully!');

      // Searchable plate selector for Upload Documents (datalist + live search)
      function setupPlateAutocomplete() {
        const inputs = Array.from(document.querySelectorAll('input[list="platesList"]'));
        const datalist = document.getElementById('platesList');
        if (!inputs.length || !datalist) return;

        let controller = null;
        let debounceTimer = null;

        function fetchPlates(query) {
          if (controller) controller.abort();
          controller = new AbortController();
          const url = '/tmm/admin/api/module1/list_vehicles.php?q=' + encodeURIComponent(query || '');
          fetch(url, { signal: controller.signal })
            .then(r => r.json())
            .then(data => {
              datalist.innerHTML = '';
              if (data && data.ok && Array.isArray(data.data)) {
                data.data.slice(0, 50).forEach(row => {
                  const opt = document.createElement('option');
                  opt.value = row.plate_number;
                  if (row.operator_name) opt.label = row.plate_number + ' • ' + row.operator_name;
                  datalist.appendChild(opt);
                });
              }
            })
            .catch(() => { /* ignore aborted/failed */ });
        }

        inputs.forEach(input => {
          input.addEventListener('input', function(e) {
            const q = e.target.value.trim();
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => fetchPlates(q), 300);
          });
          input.addEventListener('focus', function() {
            const q = input.value.trim();
            if (q.length > 0) fetchPlates(q);
          });
        });
      }

      // Initialize plate autocomplete for all matching inputs
      setupPlateAutocomplete();

      // Autofill deed reference based on selected plate's uploaded deed documents
      function setupTransferDeedAutofill() {
        const plateInput = document.getElementById('transferPlateInput');
        const deedInput = document.getElementById('deedRefInput');
        const deedList = document.getElementById('deedRefsList');
        if (!plateInput || !deedInput || !deedList) return;

        let controller = null;

        function loadDeeds(plate) {
          const p = (plate || '').trim();
          if (p === '') { deedInput.value=''; deedList.innerHTML=''; return; }
          if (controller) controller.abort();
          controller = new AbortController();
          const url = '/tmm/admin/api/module1/list_documents.php?plate=' + encodeURIComponent(p) + '&type=deed';
          fetch(url, { signal: controller.signal })
            .then(r => r.json())
            .then(data => {
              deedList.innerHTML = '';
              if (data && data.ok && Array.isArray(data.data) && data.data.length) {
                // Sort newest first by uploaded_at and populate suggestions
                const docs = data.data.slice().sort(function(a, b){ return (a.uploaded_at < b.uploaded_at ? 1 : -1); });
                docs.forEach(function(doc){
                  const opt = document.createElement('option');
                  opt.value = doc.file_path;
                  opt.label = doc.file_path;
                  deedList.appendChild(opt);
                });
                // Auto-fill the reference with the latest deed document
                deedInput.value = docs[0].file_path;
              } else {
                deedInput.value = '';
              }
            })
            .catch(function(){ /* ignore aborted/failed */ });
        }

        plateInput.addEventListener('change', function(){ loadDeeds(plateInput.value); });
        plateInput.addEventListener('blur', function(){ loadDeeds(plateInput.value); });

        // Expose a helper to trigger deed loading when modal is opened
        window.__loadDeedsForTransfer = loadDeeds;
      }

      setupTransferDeedAutofill();

      // Modal Logic (Existing)
      var modal = document.getElementById('vehicleModal');
      var body = document.getElementById('vehicleModalBody');
      var closeBtn = document.getElementById('vehicleModalClose');
      function openModal(html){ body.innerHTML = html; modal.classList.remove('hidden'); if (window.lucide && window.lucide.createIcons) window.lucide.createIcons(); }
      function closeModal(){ modal.classList.add('hidden'); body.innerHTML=''; }
      if(closeBtn) closeBtn.addEventListener('click', closeModal);
      if(modal) modal.addEventListener('click', function(e){ if (e.target === modal || e.target.classList.contains('backdrop-blur-sm')) closeModal(); });
      document.querySelectorAll('button[data-plate]').forEach(function(btn){
        btn.addEventListener('click', function(){
          var plate = this.getAttribute('data-plate');
          fetch('api/module1/view_html.php?plate='+encodeURIComponent(plate)).then(function(r){ return r.text(); }).then(function(html){ openModal(html); });
        });
      });

      // Create Vehicle Modal logic
      var createVehicleModal = document.getElementById('createVehicleModal');
      var createCloseBtn = document.getElementById('createVehicleModalClose');
      var createCancelBtn = document.getElementById('createCancelBtn');
      var openCreateBtn = document.getElementById('openCreateVehicleModalBtn');
      function openCreateModal(){ createVehicleModal.classList.remove('hidden'); if (window.lucide && window.lucide.createIcons) window.lucide.createIcons(); }
      function closeCreateModal(){ createVehicleModal.classList.add('hidden'); }
      if (openCreateBtn) openCreateBtn.addEventListener('click', openCreateModal);
      if (createCloseBtn) createCloseBtn.addEventListener('click', closeCreateModal);
      if (createCancelBtn) createCancelBtn.addEventListener('click', closeCreateModal);
      if (createVehicleModal) createVehicleModal.addEventListener('click', function(e){ if (e.target === createVehicleModal || e.target.classList.contains('backdrop-blur-sm')) closeCreateModal(); });
      var prefillFrRef = <?php echo json_encode($prefillFrRef); ?>;
      if (prefillFrRef && typeof openCreateModal === 'function') {
        openCreateModal();
      }
      var createVehicleForm = document.getElementById('createVehicleForm');
      if (createVehicleForm) {
        var franchiseInput = createVehicleForm.elements['franchise_id'];
        var franchiseStatusEl = document.getElementById('cv_franchise_status');
        var franchiseTimer = null;
        function setFranchiseStatus(cls, text) {
          if (!franchiseStatusEl) return;
          franchiseStatusEl.className = 'mt-1 text-xs ' + cls;
          franchiseStatusEl.textContent = text;
        }
        function clearFranchiseStatus() {
          if (!franchiseStatusEl) return;
          franchiseStatusEl.className = 'mt-1 text-xs text-slate-500 dark:text-slate-400';
          franchiseStatusEl.textContent = '';
        }
        function validateFranchiseRef(v) {
          var ref = (v || '').trim().toUpperCase();
          if (!ref) {
            clearFranchiseStatus();
            return;
          }
          var refPattern = /^[0-9]{4}-[0-9]{3,5}$/;
          if (!refPattern.test(ref)) {
            setFranchiseStatus('text-red-600 dark:text-red-400', 'Franchise reference should look like 2024-00123.');
            return;
          }
          setFranchiseStatus('text-xs text-slate-500 dark:text-slate-400', 'Checking franchise reference...');
          fetch('api/franchise/validate.php?franchise_id=' + encodeURIComponent(ref))
            .then(function(r){ return r.json(); })
            .then(function(data){
              if (!data || data.ok === false) {
                setFranchiseStatus('text-red-600 dark:text-red-400', (data && data.error) ? data.error : 'Unable to validate franchise.');
                return;
              }
              if (data.valid) {
                setFranchiseStatus('text-emerald-600 dark:text-emerald-400', 'Franchise is endorsed for ' + (data.operator || 'operator') + '.');
              } else {
                setFranchiseStatus('text-amber-600 dark:text-amber-400', 'Franchise is not endorsed or not found (status: ' + (data.status || 'Unknown') + ').');
              }
            })
            .catch(function(err){
              setFranchiseStatus('text-red-600 dark:text-red-400', 'Error: ' + err.message);
            });
        }
        if (franchiseInput) {
          franchiseInput.addEventListener('input', function(){
            if (franchiseTimer) clearTimeout(franchiseTimer);
            franchiseTimer = setTimeout(function(){ validateFranchiseRef(franchiseInput.value); }, 500);
          });
          franchiseInput.addEventListener('blur', function(){
            validateFranchiseRef(franchiseInput.value);
          });
        }
        var routeInput = createVehicleForm.elements['route_id'];
        var routeStatusEl = document.getElementById('cv_route_status');
        var routeTimer = null;
        function setRouteStatus(cls, text) {
          if (!routeStatusEl) return;
          routeStatusEl.className = 'mt-1 text-xs ' + cls;
          routeStatusEl.textContent = text;
        }
        function clearRouteStatus() {
          if (!routeStatusEl) return;
          routeStatusEl.className = 'mt-1 text-xs text-slate-500 dark:text-slate-400';
          routeStatusEl.textContent = 'Pick a valid route ID from the list.';
        }
        function validateRouteId(v) {
          var rid = (v || '').trim().toUpperCase();
          if (routeInput) routeInput.value = rid;
          if (!rid) {
            clearRouteStatus();
            return;
          }
          setRouteStatus('text-xs text-slate-500 dark:text-slate-400', 'Checking route...');
          fetch('api/module4/route_validate.php?route_id=' + encodeURIComponent(rid))
            .then(function(r){ return r.json(); })
            .then(function(data){
              if (!data || data.ok === false) {
                var err = data && data.error ? data.error : 'Unable to validate route.';
                if (err === 'route_not_found') {
                  setRouteStatus('text-red-600 dark:text-red-400', 'Route not found in registry.');
                } else if (err === 'route_required') {
                  setRouteStatus('text-red-600 dark:text-red-400', 'Route ID is required.');
                } else {
                  setRouteStatus('text-red-600 dark:text-red-400', 'Route invalid (' + err + ').');
                }
                return;
              }
              var id = data.route_id || rid;
              var name = data.route_name || 'Unnamed';
              var assigned = typeof data.assigned === 'number' ? data.assigned : '?';
              var maxLimit = typeof data.max_limit === 'number' ? data.max_limit : '?';
              var base = 'Route ' + id + ' • ' + name + ' — ' + assigned + '/' + maxLimit + ' vehicles.';
              if (data.within_limit === false) {
                setRouteStatus('text-amber-600 dark:text-amber-400', base + ' At or over capacity.');
              } else {
                setRouteStatus('text-emerald-600 dark:text-emerald-400', base);
              }
            })
            .catch(function(err){
              setRouteStatus('text-red-600 dark:text-red-400', 'Error: ' + err.message);
            });
        }
        if (routeInput) {
          routeInput.addEventListener('input', function(){
            this.value = this.value.toUpperCase();
            if (routeTimer) clearTimeout(routeTimer);
            routeTimer = setTimeout(function(){ validateRouteId(routeInput.value); }, 500);
          });
          routeInput.addEventListener('blur', function(){
            validateRouteId(routeInput.value);
          });
        }
      }

      // Upload Documents Modal logic
      var uploadDocsModal = document.getElementById('uploadDocsModal');
      var uploadCloseBtn = document.getElementById('uploadDocsModalClose');
      var uploadCancelBtn = document.getElementById('uploadCancelBtn');
      var openUploadBtn = document.getElementById('openUploadDocsModalBtn');
      function openUploadModal(){ uploadDocsModal.classList.remove('hidden'); if (window.lucide && window.lucide.createIcons) window.lucide.createIcons(); }
      function closeUploadModal(){ uploadDocsModal.classList.add('hidden'); }
      if (openUploadBtn) openUploadBtn.addEventListener('click', openUploadModal);
      if (uploadCloseBtn) uploadCloseBtn.addEventListener('click', closeUploadModal);
      if (uploadCancelBtn) uploadCancelBtn.addEventListener('click', closeUploadModal);
      if (uploadDocsModal) uploadDocsModal.addEventListener('click', function(e){ if (e.target === uploadDocsModal || e.target.classList.contains('backdrop-blur-sm')) closeUploadModal(); });

      // Transfer Ownership Modal logic
      var transferModal = document.getElementById('transferModal');
      var transferCloseBtn = document.getElementById('transferModalClose');
      var transferCancelBtn = document.getElementById('transferCancelBtn');
      function openTransferModal(){ transferModal.classList.remove('hidden'); if (window.lucide && window.lucide.createIcons) window.lucide.createIcons(); }
      function closeTransferModal(){ transferModal.classList.add('hidden'); }
      if (transferCloseBtn) transferCloseBtn.addEventListener('click', closeTransferModal);
      if (transferCancelBtn) transferCancelBtn.addEventListener('click', closeTransferModal);
      if (transferModal) transferModal.addEventListener('click', function(e){ if (e.target === transferModal || e.target.classList.contains('backdrop-blur-sm')) closeTransferModal(); });

      function openTransferForPlate(plate){
        var hiddenPlate = document.getElementById('transferPlateInput');
        var plateLabel = document.getElementById('transferModalPlateLabel');
        if (hiddenPlate) hiddenPlate.value = plate;
        if (plateLabel) plateLabel.textContent = plate;
        openTransferModal();
        if (window.__loadDeedsForTransfer) window.__loadDeedsForTransfer(plate);
      }

      // Bind transfer buttons
      document.querySelectorAll('button[data-transfer-plate]').forEach(function(btn){
        btn.addEventListener('click', function(){
          var plate = this.getAttribute('data-transfer-plate');
          openTransferForPlate(plate);
        });
      });

      function setupAutoFiltering() {
        const searchInput = document.getElementById('searchInput');
        const typeSelect = document.getElementById('typeSelect');
        const statusSelect = document.getElementById('statusSelect');
        const filterForm = document.getElementById('filterForm');
        const awaitingCheckbox = filterForm ? filterForm.querySelector('input[name="awaiting_endorsement"]') : null;
        
        if (!filterForm) return;

        // Debounce function to prevent too many requests
        function debounce(func, wait) {
          let timeout;
          return function executedFunction(...args) {
            const later = () => {
              clearTimeout(timeout);
              func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
          };
        }

        // Function to submit form with AJAX and update results
        function submitFilterForm() {
          const formData = new FormData(filterForm);
          const params = new URLSearchParams(formData);
          
          // Build the correct URL for this specific page
          // Since this is loaded through index.php?page=module1/submodule1
          const currentUrl = new URL(window.location.href);
          
          // Preserve the page parameter and add our filter parameters
          const finalParams = new URLSearchParams();
          finalParams.set('page', 'module1/submodule1'); // Ensure we stay on this page
          
          // Add our filter parameters
          if (params.get('q')) finalParams.set('q', params.get('q'));
          if (params.get('vehicle_type')) finalParams.set('vehicle_type', params.get('vehicle_type'));
          if (params.get('status')) finalParams.set('status', params.get('status'));
          if (params.get('awaiting_endorsement')) finalParams.set('awaiting_endorsement', params.get('awaiting_endorsement'));
          
          const url = new URL(currentUrl.pathname, window.location.origin);
          url.search = finalParams.toString();
          
          console.log('Fetching URL:', url.toString()); // Debug log
          console.log('Current page URL:', window.location.href); // Debug log
          
          // Show loading indicator
          const loadingIndicator = document.getElementById('loading-indicator');
          if (loadingIndicator) {
            loadingIndicator.classList.remove('hidden');
          }
          
          // Use fetch to get new results
          fetch(url.toString(), {
            method: 'GET',
            headers: {
              'X-Requested-With': 'XMLHttpRequest'
            }
          })
          .then(response => {
            console.log('Response status:', response.status); // Debug log
            if (!response.ok) {
              throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text();
          })
          .then(html => {
            console.log('Received HTML length:', html.length); // Debug log
            
            // Hide loading indicator
            if (loadingIndicator) {
              loadingIndicator.classList.add('hidden');
            }
            
            // Parse the HTML to extract just the table
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newTable = doc.querySelector('#vehicleTableContainer');
            const currentTable = document.querySelector('#vehicleTableContainer');
            
            console.log('New table found:', !!newTable); // Debug log
            console.log('Current table found:', !!currentTable); // Debug log
            
            if (newTable && currentTable) {
              // Replace the table content
              currentTable.innerHTML = newTable.innerHTML;
              
              // Re-initialize any event listeners for the new table
              initializeTableEventListeners();
              
              // Show a subtle indication that filtering occurred
              // showToast('Results updated', 'success'); // Optional, maybe too noisy
            } else {
              console.error('Table containers not found');
              showToast('Error: Could not update table', 'error');
            }
          })
          .catch(error => {
            console.error('Error filtering results:', error);
            // Hide loading indicator on error
            if (loadingIndicator) {
              loadingIndicator.classList.add('hidden');
            }
            showToast('Error updating results: ' + error.message, 'error');
          });
        }

        // Function to initialize event listeners for table buttons
        function initializeTableEventListeners() {
          // Re-attach modal listeners for view details buttons
          document.querySelectorAll('button[data-plate]').forEach(function(btn){
            btn.addEventListener('click', function(){
              var plate = this.getAttribute('data-plate');
              fetch('api/module1/view_html.php?plate='+encodeURIComponent(plate))
                .then(function(r){ return r.text(); })
                .then(function(html){ openModal(html); });
            });
          });

          // Toolbar buttons may be inside replaced container in future; re-bind cautiously
          var openCreateBtn2 = document.getElementById('openCreateVehicleModalBtn');
          var openUploadBtn2 = document.getElementById('openUploadDocsModalBtn');
          if (openCreateBtn2) openCreateBtn2.addEventListener('click', openCreateModal);
          if (openUploadBtn2) openUploadBtn2.addEventListener('click', openUploadModal);

          // Re-attach listeners for transfer buttons
          document.querySelectorAll('button[data-transfer-plate]').forEach(function(btn){
            btn.addEventListener('click', function(){
              var plate = this.getAttribute('data-transfer-plate');
              openTransferForPlate(plate);
            });
          });
          
          // Re-initialize icons
          if (window.lucide && window.lucide.createIcons) window.lucide.createIcons();
        }

        // Create debounced version of submit function
        const debouncedSubmit = debounce(submitFilterForm, 500);

        // Add event listeners for auto-filtering
        if (searchInput) {
          searchInput.addEventListener('input', debouncedSubmit);
        }
        
        if (typeSelect) {
          typeSelect.addEventListener('change', submitFilterForm);
        }
        
        if (statusSelect) {
          statusSelect.addEventListener('change', submitFilterForm);
        }

        if (awaitingCheckbox) {
          awaitingCheckbox.addEventListener('change', submitFilterForm);
        }

        // Prevent form submission on Enter key in search input
        if (searchInput) {
          searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
              e.preventDefault();
              submitFilterForm();
            }
          });
        }
      }

      // Initialize auto-filtering when DOM is ready
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupAutoFiltering);
      } else {
        setupAutoFiltering();
      }
    })();
  </script>
</div>
