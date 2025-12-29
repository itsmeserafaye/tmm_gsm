<div class="mx-1 mt-1 p-4 md:p-6 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 rounded-lg">
  <h1 class="text-2xl font-bold mb-2">Vehicle & Ownership Registry</h1>
  <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">Manages PUV master records, OR/CR document storage, ownership details, transfers, and status tracking.</p>
  <?php require_once __DIR__ . '/../../includes/db.php'; $db = db(); ?>

  <form class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6" method="GET">
    <input name="q" class="col-span-1 px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700" placeholder="Plate/operator search" value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">
    <?php require_once __DIR__ . '/../../includes/vehicle_types.php'; $types = vehicle_types(); ?>
    <select name="vehicle_type" class="col-span-1 px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700">
      <option>Vehicle type</option>
      <?php foreach ($types as $t): ?>
        <option <?php echo (($_GET['vehicle_type'] ?? '')===$t)?'selected':''; ?>><?php echo htmlspecialchars($t); ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status" class="col-span-1 px-3 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700">
      <option>Status</option>
      <option>Active</option>
      <option>Suspended</option>
      <option>Deactivated</option>
    </select>
    <button class="px-4 py-2 bg-[#4CAF50] text-white rounded-lg w-full md:w-auto">Search</button>
  </form>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <?php
      $total = $db->query("SELECT COUNT(*) AS c FROM vehicles")->fetch_assoc()['c'] ?? 0;
      $act = $db->query("SELECT COUNT(*) AS c FROM vehicles WHERE status='Active'")->fetch_assoc()['c'] ?? 0;
      $sus = $db->query("SELECT COUNT(*) AS c FROM vehicles WHERE status='Suspended'")->fetch_assoc()['c'] ?? 0;
    ?>
    <div class="p-4 rounded-lg shadow-sm bg-gradient-to-br from-[#4CAF50]/10 to-[#4A90E2]/10 border border-[#4CAF50]/20"><div class="text-xs text-slate-600 dark:text-slate-300">Total Vehicles</div><div class="text-2xl font-bold"><?php echo (int)$total; ?></div></div>
    <div class="p-4 rounded-lg shadow-sm bg-gradient-to-br from-[#4CAF50]/10 to-[#4A90E2]/10 border border-[#4CAF50]/20"><div class="text-xs text-slate-600 dark:text-slate-300">Active</div><div class="text-2xl font-bold text-[#4CAF50]"><?php echo (int)$act; ?></div></div>
    <div class="p-4 rounded-lg shadow-sm bg-gradient-to-br from-[#FDA811]/10 to-[#4A90E2]/10 border border-[#FDA811]/20"><div class="text-xs text-slate-600 dark:text-slate-300">Suspended</div><div class="text-2xl font-bold text-[#FDA811]"><?php echo (int)$sus; ?></div></div>
  </div>
  <div class="overflow-x-auto rounded-xl ring-1 ring-slate-200 dark:ring-slate-700 bg-white dark:bg-slate-900 shadow-sm">
    <table class="min-w-full text-sm">
      <thead class="hidden md:table-header-group bg-slate-100 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700">
        <tr class="text-left text-slate-700 dark:text-slate-200">
          <th class="py-3 px-4 font-semibold text-xs uppercase tracking-wider">Plate</th>
          <th class="py-3 px-4 font-semibold text-xs uppercase tracking-wider">Type</th>
          <th class="py-3 px-4 font-semibold text-xs uppercase tracking-wider">Operator</th>
          <th class="py-3 px-4 font-semibold text-xs uppercase tracking-wider">COOP</th>
          <th class="py-3 px-4 font-semibold text-xs uppercase tracking-wider">Franchise ID</th>
          <th class="py-3 px-4 font-semibold text-xs uppercase tracking-wider">Route ID</th>
          <th class="py-3 px-4 font-semibold text-xs uppercase tracking-wider">Status</th>
          <th class="py-3 px-4 font-semibold text-xs uppercase tracking-wider text-center">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
        <?php
          $q = trim($_GET['q'] ?? '');
          $status = trim($_GET['status'] ?? '');
          $sql = "SELECT plate_number, vehicle_type, operator_name, coop_name, franchise_id, route_id, status FROM vehicles";
          $conds = [];
          $params = [];
          $types = '';
          if ($q !== '') { $conds[] = "(plate_number LIKE ? OR operator_name LIKE ?)"; $params[] = "%$q%"; $params[] = "%$q%"; $types .= 'ss'; }
          $vehicleType = trim($_GET['vehicle_type'] ?? '');
          if ($status !== '' && $status !== 'Status') { $conds[] = "status=?"; $params[] = $status; $types .= 's'; }
          if ($vehicleType !== '' && $vehicleType !== 'Vehicle type') { $conds[] = "vehicle_type=?"; $params[] = $vehicleType; $types .= 's'; }
          if ($conds) { $sql .= " WHERE " . implode(" AND ", $conds); }
          $sql .= " ORDER BY created_at DESC";
          if ($params) { $stmt = $db->prepare($sql); $stmt->bind_param($types, ...$params); $stmt->execute(); $res = $stmt->get_result(); } else { $res = $db->query($sql); }
          while ($row = $res->fetch_assoc()):
        ?>
        <tr class="grid grid-cols-1 md:table-row gap-2 md:gap-0 p-2 md:p-0 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors duration-150">
          <td class="py-3 px-4"><span class="md:hidden font-semibold">Plate: </span><span class="font-medium text-slate-900 dark:text-slate-100"><?php echo htmlspecialchars($row['plate_number']); ?></span></td>
          <td class="py-3 px-4"><span class="md:hidden font-semibold">Type: </span><?php $tc='bg-secondary/10 text-secondary'; if($row['vehicle_type']==='Jeepney'||$row['vehicle_type']==='Modern Jeepney') $tc='bg-blue-100 text-blue-700'; if($row['vehicle_type']==='UV Express'||$row['vehicle_type']==='Shuttle Van') $tc='bg-indigo-100 text-indigo-700'; if($row['vehicle_type']==='E-trike'||$row['vehicle_type']==='Tricycle'||$row['vehicle_type']==='Motorized Pedicab') $tc='bg-teal-100 text-teal-700'; if($row['vehicle_type']==='City Bus'||$row['vehicle_type']==='Mini-bus') $tc='bg-purple-100 text-purple-700'; if($row['vehicle_type']==='Taxi') $tc='bg-pink-100 text-pink-700'; ?><span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $tc; ?>"><?php echo htmlspecialchars($row['vehicle_type']); ?></span></td>
          <td class="py-3 px-4 text-slate-600 dark:text-slate-400"><span class="md:hidden font-semibold">Operator: </span><?php echo htmlspecialchars($row['operator_name']); ?></td>
          <td class="py-3 px-4 text-slate-600 dark:text-slate-400"><span class="md:hidden font-semibold">COOP: </span><?php echo htmlspecialchars($row['coop_name'] ?? ''); ?></td>
          <td class="py-3 px-4 text-slate-600 dark:text-slate-400"><span class="md:hidden font-semibold">Franchise ID: </span><?php echo htmlspecialchars($row['franchise_id'] ?? ''); ?></td>
          <td class="py-3 px-4 text-slate-600 dark:text-slate-400"><span class="md:hidden font-semibold">Route ID: </span><?php echo htmlspecialchars($row['route_id'] ?? ''); ?></td>
          <td class="py-3 px-4"><span class="md:hidden font-semibold">Status: </span><?php $sc='bg-green-100 text-green-700'; if($row['status']==='Suspended') $sc='bg-yellow-100 text-yellow-700'; if($row['status']==='Deactivated') $sc='bg-red-100 text-red-700'; ?><span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $sc; ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
          <td class="py-3 px-4 text-center">
            <div class="flex items-center justify-center space-x-2">
              <button title="View Details" data-plate="<?php echo htmlspecialchars($row['plate_number']); ?>" class="p-2 rounded-full text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-colors"><i data-lucide="eye" class="w-5 h-5"></i><span class="sr-only">View</span></button>
              <a title="Assign Route" class="p-2 rounded-full text-green-600 hover:bg-green-50 dark:hover:bg-green-900/30 transition-colors" href="?page=module1/submodule3&route_id=<?php echo urlencode($row['route_id'] ?? ''); ?>"><i data-lucide="map-pin" class="w-5 h-5"></i><span class="sr-only">Assign</span></a>
              <a title="Transfer Ownership" class="p-2 rounded-full text-orange-600 hover:bg-orange-50 dark:hover:bg-orange-900/30 transition-colors" href="#transfer-section"><i data-lucide="repeat" class="w-5 h-5"></i><span class="sr-only">Transfer</span></a>
            </div>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>

  <!-- Toast Notification Container -->
  <div id="toast-container" class="fixed bottom-4 right-4 z-50 flex flex-col gap-2 pointer-events-none"></div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-8">
    <div class="p-6 border rounded-lg dark:border-slate-700 bg-white dark:bg-slate-900 border-t-4 border-t-green-500 shadow-sm">
      <h2 class="text-lg font-semibold mb-4 text-slate-800 dark:text-slate-100 flex items-center gap-2"><i data-lucide="plus-circle" class="w-5 h-5 text-green-500"></i> Create Vehicle Record</h2>
      <form id="createVehicleForm" class="space-y-4" novalidate>
        <div>
          <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1 uppercase tracking-wide">Plate Number</label>
          <input name="plate_number" id="cv_plate" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-green-500/20 focus:border-green-500 outline-none transition-all uppercase placeholder:normal-case" placeholder="ABC-1234" pattern="^[A-Z0-9-]{6,10}$" required>
          <p class="text-xs text-red-500 mt-1 hidden" id="cv_plate_error">Invalid plate format (e.g., ABC-1234)</p>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1 uppercase tracking-wide">Type</label>
            <?php $types = vehicle_types(); ?>
            <select name="vehicle_type" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-green-500/20 focus:border-green-500 outline-none transition-all" required>
              <option value="">Select Type</option>
              <?php foreach ($types as $t): ?>
                <option value="<?php echo htmlspecialchars($t); ?>"><?php echo htmlspecialchars($t); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1 uppercase tracking-wide">Status</label>
            <select name="status" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-green-500/20 focus:border-green-500 outline-none transition-all">
              <option value="Active">Active</option>
              <option value="Suspended">Suspended</option>
              <option value="Deactivated">Deactivated</option>
            </select>
          </div>
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1 uppercase tracking-wide">Operator Name</label>
          <input name="operator_name" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-green-500/20 focus:border-green-500 outline-none transition-all" placeholder="Full Name" required>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1 uppercase tracking-wide">Franchise No.</label>
            <input name="franchise_id" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-green-500/20 focus:border-green-500 outline-none transition-all uppercase" placeholder="FR-0000">
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1 uppercase tracking-wide">Route ID</label>
            <input name="route_id" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-green-500/20 focus:border-green-500 outline-none transition-all uppercase" placeholder="R-00">
          </div>
        </div>

        <button type="submit" id="btnSaveVehicle" class="flex items-center justify-center gap-2 px-6 py-2.5 bg-green-500 hover:bg-green-600 text-white font-medium rounded-lg w-full transition-colors shadow-sm shadow-green-500/30">
          <span>Save Record</span>
          <i data-lucide="save" class="w-4 h-4"></i>
        </button>
      </form>
    </div>

    <div class="p-6 border rounded-lg dark:border-slate-700 bg-white dark:bg-slate-900 border-t-4 border-t-blue-500 shadow-sm">
      <h2 class="text-lg font-semibold mb-4 text-slate-800 dark:text-slate-100 flex items-center gap-2"><i data-lucide="upload-cloud" class="w-5 h-5 text-blue-500"></i> Upload Documents</h2>
      <form id="uploadDocsForm" class="space-y-4" enctype="multipart/form-data">
        <div>
          <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1 uppercase tracking-wide">Target Vehicle (Plate)</label>
          <input name="plate_number" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all uppercase" placeholder="Search Plate..." required>
        </div>
        
        <div class="space-y-3">
          <div class="relative group">
            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">OR Document (PDF/IMG)</label>
            <input name="or" type="file" accept=".pdf,.jpg,.jpeg,.png" class="w-full text-sm file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition-colors border rounded-lg cursor-pointer">
          </div>
          <div class="relative group">
            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">CR Document (PDF/IMG)</label>
            <input name="cr" type="file" accept=".pdf,.jpg,.jpeg,.png" class="w-full text-sm file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition-colors border rounded-lg cursor-pointer">
          </div>
          <div class="relative group">
            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Deed of Sale (PDF/IMG)</label>
            <input name="deed" type="file" accept=".pdf,.jpg,.jpeg,.png" class="w-full text-sm file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition-colors border rounded-lg cursor-pointer">
          </div>
        </div>

        <button type="submit" id="btnUploadDocs" class="flex items-center justify-center gap-2 px-6 py-2.5 bg-blue-500 hover:bg-blue-600 text-white font-medium rounded-lg w-full transition-colors shadow-sm shadow-blue-500/30">
          <span>Upload Files</span>
          <i data-lucide="upload" class="w-4 h-4"></i>
        </button>
      </form>
    </div>
  </div>

  <div id="transfer-section" class="p-6 border rounded-lg dark:border-slate-700 bg-white dark:bg-slate-900 border-t-4 border-t-orange-500 mt-8 shadow-sm">
    <h2 class="text-lg font-semibold mb-4 text-slate-800 dark:text-slate-100 flex items-center gap-2"><i data-lucide="refresh-cw" class="w-5 h-5 text-orange-500"></i> Ownership Transfer</h2>
    <form id="transferForm" class="grid grid-cols-1 md:grid-cols-2 gap-6" method="POST" action="/tmm/admin/api/module1/transfer_ownership.php">
      <div>
        <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1 uppercase tracking-wide">Vehicle Plate</label>
        <input name="plate_number" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-orange-500/20 focus:border-orange-500 outline-none transition-all uppercase" placeholder="ABC-1234" required>
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1 uppercase tracking-wide">New Operator</label>
        <input name="new_operator_name" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-orange-500/20 focus:border-orange-500 outline-none transition-all" placeholder="New Operator Name" required>
      </div>
      <div class="md:col-span-2">
        <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1 uppercase tracking-wide">Deed of Sale Reference</label>
        <input name="deed_ref" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-orange-500/20 focus:border-orange-500 outline-none transition-all" placeholder="Doc Ref #">
      </div>
      <div class="md:col-span-2 md:w-auto">
        <button type="submit" id="btnTransfer" class="flex items-center justify-center gap-2 px-6 py-2.5 bg-orange-500 hover:bg-orange-600 text-white font-medium rounded-lg w-full md:w-auto transition-colors shadow-sm shadow-orange-500/30">
          <span>Transfer Ownership</span>
          <i data-lucide="arrow-right" class="w-4 h-4"></i>
        </button>
      </div>
    </form>
  </div>
  <div id="vehicleModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/50"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
      <div class="w-[95vw] max-w-5xl bg-white dark:bg-slate-900 rounded-xl shadow-lg border border-slate-200 dark:border-slate-700">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700">
          <div class="text-lg font-semibold">Vehicle Details</div>
          <button id="vehicleModalClose" class="p-2 rounded hover:bg-slate-100 dark:hover:bg-slate-800"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>
        <div id="vehicleModalBody" class="p-6 max-h-[75vh] overflow-y-auto"></div>
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
          this.value = this.value.toUpperCase();
          const regex = /^[A-Z0-9-]{6,10}$/;
          if(this.value.length > 0 && !regex.test(this.value)) {
            plateError.classList.remove('hidden');
            this.classList.add('border-red-500', 'focus:border-red-500', 'focus:ring-red-500/20');
            this.classList.remove('border-slate-200', 'focus:border-green-500', 'focus:ring-green-500/20');
          } else {
            plateError.classList.add('hidden');
            this.classList.remove('border-red-500', 'focus:border-red-500', 'focus:ring-red-500/20');
          }
        });
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
            
            if (data.ok || data.status === 'success' || (Array.isArray(data) && data.length > 0)) {
              showToast(successMsg);
              form.reset();
              // Optional: Reload table content dynamically here
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

      // Init Handlers
      handleForm('createVehicleForm', 'btnSaveVehicle', 'Vehicle saved successfully!');
      handleForm('uploadDocsForm', 'btnUploadDocs', 'Documents uploaded successfully!');
      handleForm('transferForm', 'btnTransfer', 'Ownership transferred successfully!');

      // Modal Logic (Existing)
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
</div>
