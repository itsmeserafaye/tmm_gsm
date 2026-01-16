<?php
require_once __DIR__ . '/../../includes/db.php';
$types = [];
if (is_file(__DIR__ . '/../../includes/vehicle_types.php')) { require_once __DIR__ . '/../../includes/vehicle_types.php'; $types = vehicle_types(); }
$db = db();
$plate = trim($_GET['plate'] ?? '');
$stmt = $db->prepare("SELECT v.plate_number, v.vehicle_type, v.operator_name, v.coop_name, v.franchise_id, v.route_id, v.status, v.created_at, fa.status AS franchise_status FROM vehicles v LEFT JOIN franchise_applications fa ON v.franchise_id = fa.franchise_ref_number WHERE v.plate_number=?");
$stmt->bind_param('s', $plate);
$stmt->execute();
$v = $stmt->get_result()->fetch_assoc();
header('Content-Type: text/html; charset=utf-8');

if (!$v) {
    echo '<div class="flex flex-col items-center justify-center p-12 text-center rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-dashed border-slate-300 dark:border-slate-700">';
    echo '  <div class="p-4 rounded-full bg-slate-100 dark:bg-slate-800 mb-4"><i data-lucide="search-x" class="w-8 h-8 text-slate-400"></i></div>';
    echo '  <h3 class="text-lg font-bold text-slate-900 dark:text-white">Vehicle Not Found</h3>';
    echo '  <p class="text-slate-500 dark:text-slate-400 max-w-xs mt-2">The requested vehicle record could not be found.</p>';
    echo '</div>';
    exit;
}

$statusClass = match($v['status']) {
    'Active' => 'bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20',
    'Suspended' => 'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/20',
    'Deactivated' => 'bg-rose-100 text-rose-700 border-rose-200 dark:bg-rose-500/10 dark:text-rose-400 dark:border-rose-500/20',
    default => 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-500/10 dark:text-slate-400 dark:border-slate-500/20'
};

// Common Styles
$cardClass = "overflow-hidden rounded-2xl bg-white dark:bg-slate-900 shadow-sm border border-slate-200 dark:border-slate-700";
$cardHeaderClass = "px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-800/50";
$cardBodyClass = "p-6";
$inputClass = "block w-full rounded-lg bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 py-2 px-3 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 sm:text-sm transition-all";
$btnClass = "rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 transition-all active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed";
$labelClass = "block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1 uppercase tracking-wide";

?>

<div class="space-y-6 animate-in fade-in zoom-in-95 duration-300">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 bg-white dark:bg-slate-900 p-6 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700">
        <div class="flex items-start gap-5">
            <div class="p-4 rounded-xl bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 ring-1 ring-blue-100 dark:ring-blue-800/30">
                <i data-lucide="bus" class="w-8 h-8"></i>
            </div>
            <div>
                <div class="flex items-center gap-3">
                    <h2 class="text-3xl font-black text-slate-900 dark:text-white tracking-tight"><?php echo htmlspecialchars($v['plate_number']); ?></h2>
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold border <?php echo $statusClass; ?>">
                        <?php echo htmlspecialchars($v['status']); ?>
                    </span>
                </div>
                <div class="text-base font-medium text-slate-500 dark:text-slate-400 mt-1 flex items-center gap-2">
                    <i data-lucide="user" class="w-4 h-4"></i>
                    <?php echo htmlspecialchars($v['operator_name']); ?>
                </div>
                <div class="text-xs text-slate-400 mt-2 flex items-center gap-2">
                    <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
                    Registered on <?php echo date('F d, Y', strtotime($v['created_at'])); ?>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Left Column -->
        <div class="lg:col-span-2 space-y-6">
            
            <!-- Details Card -->
            <div class="<?php echo $cardClass; ?>">
                <div class="<?php echo $cardHeaderClass; ?>">
                    <h3 class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <i data-lucide="info" class="w-4 h-4 text-blue-500"></i> Vehicle Information
                    </h3>
                </div>
                <div class="<?php echo $cardBodyClass; ?>">
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-6">
                        <div>
                            <dt class="<?php echo $labelClass; ?>">Vehicle Type</dt>
                            <dd class="text-lg font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($v['vehicle_type']); ?></dd>
                        </div>
                        <div>
                            <dt class="<?php echo $labelClass; ?>">Franchise ID</dt>
                            <dd class="text-lg font-bold text-indigo-600 dark:text-indigo-400 flex items-center gap-2">
                                <?php echo htmlspecialchars($v['franchise_id'] ?? '-'); ?>
                                <?php if (!empty($v['franchise_id'])): ?>
                                    <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-500"></i>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="<?php echo $labelClass; ?>">Cooperative</dt>
                            <dd class="font-medium text-slate-900 dark:text-white flex items-center gap-2">
                                <div class="p-1.5 rounded-md bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400">
                                    <i data-lucide="building-2" class="w-4 h-4"></i>
                                </div>
                                <?php echo htmlspecialchars($v['coop_name'] ?? 'No Cooperative Assigned'); ?>
                            </dd>
                        </div>
                        <div>
                            <dt class="<?php echo $labelClass; ?>">Route ID</dt>
                            <dd class="font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($v['route_id'] ?? '-'); ?></dd>
                        </div>
                        <div>
                            <dt class="<?php echo $labelClass; ?>">Franchise Status</dt>
                            <dd class="font-bold text-slate-700 dark:text-slate-300"><?php echo htmlspecialchars($v['franchise_status'] ?? 'No record'); ?></dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Terminal Assignment -->
            <?php
            $stmtA = $db->prepare("SELECT route_id, terminal_name, status, assigned_at FROM terminal_assignments WHERE plate_number=?");
            $stmtA->bind_param('s', $plate);
            $stmtA->execute();
            $a = $stmtA->get_result()->fetch_assoc();
            ?>
            <div class="<?php echo $cardClass; ?>">
                <div class="<?php echo $cardHeaderClass; ?>">
                    <h3 class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <i data-lucide="map-pin" class="w-4 h-4 text-emerald-500"></i> Terminal Assignment
                    </h3>
                </div>
                <div class="<?php echo $cardBodyClass; ?>">
                    <?php if (!$a): ?>
                        <div class="mb-6 p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-dashed border-slate-200 dark:border-slate-700 flex items-center gap-3 text-slate-500">
                            <i data-lucide="alert-circle" class="w-5 h-5"></i>
                            <span class="text-sm">No active terminal assignment.</span>
                        </div>
                    <?php else: ?>
                        <div class="mb-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div class="p-3 rounded-lg bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700">
                                <div class="text-xs text-slate-400 uppercase tracking-wider mb-1">Route</div>
                                <div class="font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($a['route_id']); ?></div>
                            </div>
                            <div class="p-3 rounded-lg bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700">
                                <div class="text-xs text-slate-400 uppercase tracking-wider mb-1">Terminal</div>
                                <div class="font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($a['terminal_name']); ?></div>
                            </div>
                            <div class="p-3 rounded-lg bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700">
                                <div class="text-xs text-slate-400 uppercase tracking-wider mb-1">Status</div>
                                <div class="font-bold <?php echo $a['status']==='Authorized'?'text-emerald-600':'text-amber-600'; ?>">
                                    <?php echo htmlspecialchars($a['status']); ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form id="formAssign" class="grid grid-cols-1 sm:grid-cols-12 gap-4 items-end" method="POST" action="/tmm/admin/api/module1/assign_route.php">
                        <input type="hidden" name="plate_number" value="<?php echo htmlspecialchars($v['plate_number']); ?>">
                        <div class="sm:col-span-3">
                            <label class="<?php echo $labelClass; ?>">Route ID</label>
                            <input name="route_id" class="<?php echo $inputClass; ?>" placeholder="e.g. R-01" value="<?php echo htmlspecialchars($a['route_id'] ?? ''); ?>">
                        </div>
                        <div class="sm:col-span-4">
                            <label class="<?php echo $labelClass; ?>">Terminal Name</label>
                            <input name="terminal_name" class="<?php echo $inputClass; ?>" placeholder="e.g. Central Terminal" value="<?php echo htmlspecialchars($a['terminal_name'] ?? ''); ?>">
                        </div>
                        <div class="sm:col-span-3">
                            <label class="<?php echo $labelClass; ?>">Status</label>
                            <select name="status" class="<?php echo $inputClass; ?>">
                                <option <?php echo (($a['status'] ?? '')==='Authorized'?'selected':''); ?>>Authorized</option>
                                <option <?php echo (($a['status'] ?? '')!=='Authorized'?'selected':''); ?>>Pending</option>
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <button class="<?php echo $btnClass; ?> w-full">Update</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Management Card -->
            <div class="<?php echo $cardClass; ?>">
                <div class="<?php echo $cardHeaderClass; ?>">
                    <h3 class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <i data-lucide="settings-2" class="w-4 h-4 text-slate-500"></i> Management
                    </h3>
                </div>
                <div class="<?php echo $cardBodyClass; ?> space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Status Form -->
                        <form id="formStatus" method="POST" action="api/module1/update_vehicle.php">
                            <input type="hidden" name="plate_number" value="<?php echo htmlspecialchars($v['plate_number']); ?>">
                            <label class="<?php echo $labelClass; ?>">Update Status</label>
                            <div class="flex gap-2">
                                <select name="status" class="<?php echo $inputClass; ?>">
                                    <option disabled>Select Status</option>
                                    <option <?php echo ($v['status']==='Active'?'selected':''); ?>>Active</option>
                                    <option <?php echo ($v['status']==='Suspended'?'selected':''); ?>>Suspended</option>
                                    <option <?php echo ($v['status']==='Deactivated'?'selected':''); ?>>Deactivated</option>
                                </select>
                                <button class="<?php echo $btnClass; ?>">Save</button>
                            </div>
                        </form>

                        <!-- Type Form -->
                        <form id="formType" method="POST" action="api/module1/update_vehicle.php">
                            <input type="hidden" name="plate_number" value="<?php echo htmlspecialchars($v['plate_number']); ?>">
                            <label class="<?php echo $labelClass; ?>">Update Type</label>
                            <div class="flex gap-2">
                                <select name="vehicle_type" class="<?php echo $inputClass; ?>">
                                    <option disabled>Select Type</option>
                                    <?php foreach ($types as $t): ?>
                                        <option <?php echo ($v['vehicle_type']===$t?'selected':''); ?>><?php echo htmlspecialchars($t); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="<?php echo $btnClass; ?>">Save</button>
                            </div>
                        </form>
                    </div>

                    <div class="border-t border-slate-100 dark:border-slate-800 pt-6">
                        <label class="<?php echo $labelClass; ?> mb-3">Link Operator / Cooperative</label>
                        <form id="formLink" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end" method="POST" action="/tmm/admin/api/module1/link_vehicle_operator.php">
                            <input type="hidden" name="plate_number" value="<?php echo htmlspecialchars($v['plate_number']); ?>">
                            <div>
                                <input name="operator_name" class="<?php echo $inputClass; ?>" placeholder="Operator Name" value="<?php echo htmlspecialchars($v['operator_name'] ?? ''); ?>">
                            </div>
                            <div>
                                <input name="coop_name" class="<?php echo $inputClass; ?>" placeholder="Cooperative Name" value="<?php echo htmlspecialchars($v['coop_name'] ?? ''); ?>">
                            </div>
                            <div>
                                <button class="<?php echo $btnClass; ?> w-full">Link Entity</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>

        <!-- Right Column -->
        <div class="space-y-6">
            
            <!-- Documents Card -->
            <div class="<?php echo $cardClass; ?> h-full flex flex-col">
                <div class="<?php echo $cardHeaderClass; ?>">
                    <h3 class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <i data-lucide="file-text" class="w-4 h-4 text-amber-500"></i> Documents
                    </h3>
                    <span class="text-xs font-medium px-2 py-1 rounded-md bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                        <?php
                        $stmtCount = $db->prepare("SELECT COUNT(*) as c FROM documents WHERE plate_number=?");
                        $stmtCount->bind_param('s', $plate);
                        $stmtCount->execute();
                        echo $stmtCount->get_result()->fetch_assoc()['c'];
                        ?>
                    </span>
                </div>
                
                <div class="flex-grow p-4 space-y-3 overflow-y-auto max-h-[500px]">
                    <?php
                    $stmtD = $db->prepare("SELECT type, file_path, uploaded_at FROM documents WHERE plate_number=? ORDER BY uploaded_at DESC");
                    $stmtD->bind_param('s', $plate);
                    $stmtD->execute();
                    $resD = $stmtD->get_result();
                    if ($resD->num_rows === 0):
                    ?>
                        <div class="flex flex-col items-center justify-center py-10 text-center">
                            <div class="p-3 rounded-full bg-slate-100 dark:bg-slate-800 mb-3">
                                <i data-lucide="file-x" class="w-6 h-6 text-slate-400"></i>
                            </div>
                            <p class="text-sm text-slate-500 dark:text-slate-400">No documents uploaded.</p>
                        </div>
                    <?php else: while ($d = $resD->fetch_assoc()): ?>
                        <div class="group flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 hover:border-blue-200 dark:hover:border-blue-800 hover:bg-blue-50/50 dark:hover:bg-blue-900/10 transition-all">
                            <div class="flex items-center gap-3">
                                <div class="p-2 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-slate-500 dark:text-slate-400 group-hover:text-blue-500 group-hover:border-blue-200 dark:group-hover:border-blue-800 transition-colors">
                                    <i data-lucide="file" class="w-4 h-4"></i>
                                </div>
                                <div>
                                    <div class="text-sm font-bold text-slate-700 dark:text-slate-200 group-hover:text-blue-700 dark:group-hover:text-blue-300"><?php echo htmlspecialchars($d['type']); ?></div>
                                    <div class="text-[10px] text-slate-400"><?php echo date('M d, Y', strtotime($d['uploaded_at'])); ?></div>
                                </div>
                            </div>
                            <a href="/tmm/admin/<?php echo htmlspecialchars($d['file_path']); ?>" target="_blank" class="p-2 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-white dark:hover:bg-slate-800 hover:shadow-sm transition-all" title="View Document">
                                <i data-lucide="external-link" class="w-4 h-4"></i>
                            </a>
                        </div>
                    <?php endwhile; endif; ?>
                </div>

                <div class="p-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30">
                    <h4 class="text-xs font-bold text-slate-900 dark:text-white mb-3 uppercase tracking-wide">Upload New Documents</h4>
                    <form id="formUpload" class="space-y-3" method="POST" action="/tmm/admin/api/module1/upload_docs.php">
                        <input type="hidden" name="plate_number" value="<?php echo htmlspecialchars($v['plate_number']); ?>">
                        
                        <div class="grid grid-cols-3 gap-2">
                            <div class="relative group">
                                <label class="flex flex-col items-center justify-center p-3 rounded-lg border border-dashed border-slate-300 dark:border-slate-600 hover:border-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/10 cursor-pointer transition-all h-20">
                                    <i data-lucide="file-plus" class="w-5 h-5 text-slate-400 mb-1"></i>
                                    <span class="text-[10px] font-medium text-slate-500">OR</span>
                                    <input name="or" type="file" class="hidden">
                                </label>
                            </div>
                            <div class="relative group">
                                <label class="flex flex-col items-center justify-center p-3 rounded-lg border border-dashed border-slate-300 dark:border-slate-600 hover:border-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/10 cursor-pointer transition-all h-20">
                                    <i data-lucide="file-plus" class="w-5 h-5 text-slate-400 mb-1"></i>
                                    <span class="text-[10px] font-medium text-slate-500">CR</span>
                                    <input name="cr" type="file" class="hidden">
                                </label>
                            </div>
                            <div class="relative group">
                                <label class="flex flex-col items-center justify-center p-3 rounded-lg border border-dashed border-slate-300 dark:border-slate-600 hover:border-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/10 cursor-pointer transition-all h-20">
                                    <i data-lucide="file-plus" class="w-5 h-5 text-slate-400 mb-1"></i>
                                    <span class="text-[10px] font-medium text-slate-500">Deed</span>
                                    <input name="deed" type="file" class="hidden">
                                </label>
                            </div>
                        </div>

                        <button class="<?php echo $btnClass; ?> w-full flex items-center justify-center gap-2">
                            <i data-lucide="upload-cloud" class="w-4 h-4"></i> Upload Selected Files
                        </button>
                    </form>
                    
                    <?php if (getenv('TMM_AV_SCANNER')): ?>
                        <p class="mt-2 text-[10px] text-slate-400 text-center flex items-center justify-center gap-1">
                            <i data-lucide="shield-check" class="w-3 h-3"></i> Files are scanned for viruses.
                        </p>
                    <?php endif; ?>
                    
                    <div id="uploadProgress" class="w-full bg-slate-200 dark:bg-slate-700 h-1.5 rounded-full mt-3 overflow-hidden hidden">
                        <div id="uploadBar" class="h-full bg-blue-600 w-0 transition-all duration-300"></div>
                    </div>
                    <div id="uploadMsg" class="text-xs mt-2 font-medium text-center min-h-[1.25rem]"></div>
                </div>
            </div>
        
        </div>
    </div>
</div>

<script>
(function(){
  function refresh(){
    fetch("api/module1/view_html.php?plate=<?php echo htmlspecialchars($v['plate_number']); ?>")
      .then(r=>r.text())
      .then(html=>{
        var c=document.getElementById("vehicleModalBody");
        if(c){c.innerHTML=html; if(window.lucide&&window.lucide.createIcons) window.lucide.createIcons();}
      });
  }
  function bind(id){
    var f=document.getElementById(id);
    if(!f) return;
    f.addEventListener("submit", function(e){
      e.preventDefault();
      var fd=new FormData(f);
      var btn = f.querySelector("button");
      var originalContent = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<span class="animate-spin inline-block w-4 h-4 border-2 border-current border-t-transparent rounded-full"></span>';
      fetch(f.action, {method:"POST", body:fd})
        .then(()=>{ refresh(); })
        .catch(()=>{ btn.disabled=false; btn.innerHTML=originalContent; });
    });
  }
  
  // File input feedback
  document.querySelectorAll('input[type="file"]').forEach(input => {
    input.addEventListener('change', function() {
        var label = this.parentElement;
        if(this.files && this.files.length > 0) {
            label.classList.add('border-blue-500', 'bg-blue-50', 'dark:bg-blue-900/20');
            label.classList.remove('border-slate-300', 'dark:border-slate-600');
            var icon = label.querySelector('i');
            if(icon) {
                icon.setAttribute('data-lucide', 'check');
                icon.classList.remove('text-slate-400');
                icon.classList.add('text-blue-500');
                if(window.lucide&&window.lucide.createIcons) window.lucide.createIcons();
            }
        }
    });
  });

  bind("formStatus");
  bind("formType");
  bind("formAssign");
  bind("formLink");
  
  var fu=document.getElementById("formUpload");
  if(fu){
    fu.addEventListener("submit", function(e){
      e.preventDefault();
      var fd=new FormData(fu);
      
      // Check if files selected
      var hasFiles = false;
      for (var p of fd.entries()) { if(p[1] instanceof File && p[1].size > 0) hasFiles = true; }
      
      if(!hasFiles) {
          var msg = document.getElementById("uploadMsg");
          msg.textContent = "Please select at least one file.";
          msg.className = "text-xs mt-2 font-medium text-center text-amber-600";
          return;
      }

      var xhr=new XMLHttpRequest();
      var bar=document.getElementById("uploadBar");
      var wrap=document.getElementById("uploadProgress");
      var msg=document.getElementById("uploadMsg");
      
      wrap.classList.remove("hidden");
      bar.style.width="0%";
      msg.textContent="Uploading...";
      msg.className = "text-xs mt-2 font-medium text-center text-slate-500";
      
      xhr.upload.addEventListener("progress", function(ev){
        if(ev.lengthComputable){
          var p=Math.round((ev.loaded/ev.total)*100);
          bar.style.width=p+"%";
        }
      });
      
      xhr.onreadystatechange=function(){
        if(xhr.readyState===4){
          if(xhr.status>=200 && xhr.status<300){
            msg.textContent="Documents uploaded successfully";
            msg.className = "text-xs mt-2 font-medium text-center text-emerald-600";
            setTimeout(refresh, 500);
          } else {
            msg.textContent="Upload failed. Please try again.";
            msg.className = "text-xs mt-2 font-medium text-center text-red-600";
          }
          setTimeout(function(){
            wrap.classList.add("hidden");
            bar.style.width="0%";
          }, 2000);
        }
      };
      xhr.open("POST", fu.action);
      xhr.send(fd);
    });
  }
})();
</script>