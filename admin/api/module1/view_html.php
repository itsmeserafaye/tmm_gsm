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
if (!$v) { echo '<div class="text-sm text-slate-500 dark:text-slate-400">Vehicle not found.</div>'; exit; }

$statClass = 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-400/10 dark:text-emerald-400 dark:ring-emerald-400/30';
if ($v['status'] === 'Suspended') $statClass = 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-400/10 dark:text-amber-400 dark:ring-amber-400/30';
if ($v['status'] === 'Deactivated') $statClass = 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-400/10 dark:text-red-400 dark:ring-red-400/30';

// Styles
$cardClass = "overflow-hidden rounded-xl bg-white dark:bg-slate-900 shadow-sm ring-1 ring-slate-900/5 dark:ring-white/10 p-6";
$inputClass = "block w-full rounded-lg border-0 bg-slate-50 dark:bg-slate-800 py-1.5 text-slate-900 dark:text-slate-200 ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6";
$btnClass = "rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed";

echo '<div class="space-y-6">';

// Header
echo '<div class="flex items-center justify-between">';
echo '  <div class="flex items-center gap-3">';
echo '    <span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10 dark:bg-blue-400/10 dark:text-blue-400 dark:ring-blue-400/30">PUV</span>';
echo '    <h2 class="text-xl font-bold text-slate-900 dark:text-white">'.htmlspecialchars($v['plate_number']).'</h2>';
echo '  </div>';
echo '  <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset '.$statClass.'">'.htmlspecialchars($v['status']).'</span>';
echo '</div>';

echo '<div class="grid grid-cols-1 gap-6">';

// Details Card
echo '<div class="'.$cardClass.'">';
echo '  <h3 class="text-base font-semibold leading-7 text-slate-900 dark:text-white mb-4">Vehicle Information</h3>';
echo '  <dl class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2">';
echo '    <div class="sm:col-span-1"><dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Type</dt><dd class="mt-1 text-sm text-slate-900 dark:text-white">'.htmlspecialchars($v['vehicle_type']).'</dd></div>';
echo '    <div class="sm:col-span-1"><dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Operator</dt><dd class="mt-1 text-sm text-slate-900 dark:text-white">'.htmlspecialchars($v['operator_name']).'</dd></div>';
echo '    <div class="sm:col-span-1"><dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Cooperative</dt><dd class="mt-1 text-sm text-slate-900 dark:text-white">'.htmlspecialchars($v['coop_name'] ?? '-').'</dd></div>';
echo '    <div class="sm:col-span-1"><dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Franchise ID</dt><dd class="mt-1 text-sm text-slate-900 dark:text-white">'.htmlspecialchars($v['franchise_id'] ?? '-').'</dd></div>';
echo '    <div class="sm:col-span-1"><dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Franchise Status</dt><dd class="mt-1 text-sm text-slate-900 dark:text-white">'.htmlspecialchars($v['franchise_status'] ?? 'No record').'</dd></div>';
echo '    <div class="sm:col-span-1"><dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Route ID</dt><dd class="mt-1 text-sm text-slate-900 dark:text-white">'.htmlspecialchars($v['route_id'] ?? '-').'</dd></div>';
echo '    <div class="sm:col-span-1"><dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Registered</dt><dd class="mt-1 text-sm text-slate-900 dark:text-white">'.htmlspecialchars($v['created_at']).'</dd></div>';
echo '  </dl>';
echo '</div>';

// Management Card
echo '<div class="'.$cardClass.'">';
echo '  <h3 class="text-base font-semibold leading-7 text-slate-900 dark:text-white mb-4">Management</h3>';
echo '  <div class="space-y-4">';
// Status Form
echo '    <form id="formStatus" class="flex items-center gap-3" method="POST" action="api/module1/update_vehicle.php">';
echo '      <input type="hidden" name="plate_number" value="'.htmlspecialchars($v['plate_number']).'">';
echo '      <div class="flex-grow"><select name="status" class="'.$inputClass.'"><option>Status</option><option '.($v['status']==='Active'?'selected':'').'>Active</option><option '.($v['status']==='Suspended'?'selected':'').'>Suspended</option><option '.($v['status']==='Deactivated'?'selected':'').'>Deactivated</option></select></div>';
echo '      <button class="'.$btnClass.'">Update Status</button>';
echo '    </form>';
// Type Form
echo '    <form id="formType" class="flex items-center gap-3" method="POST" action="api/module1/update_vehicle.php">';
echo '      <input type="hidden" name="plate_number" value="'.htmlspecialchars($v['plate_number']).'">';
echo '      <div class="flex-grow"><select name="vehicle_type" class="'.$inputClass.'"><option>Select Type</option>';
foreach ($types as $t) { echo '<option '.($v['vehicle_type']===$t?'selected':'').'>'.htmlspecialchars($t).'</option>'; }
echo '      </select></div>';
echo '      <button class="'.$btnClass.'">Update Type</button>';
echo '    </form>';
echo '  </div>';
echo '</div>';

// Documents Card
echo '<div class="'.$cardClass.'">';
echo '  <h3 class="text-base font-semibold leading-7 text-slate-900 dark:text-white mb-4">Documents</h3>';
echo '  <div class="space-y-3 mb-6">';
$stmtD = $db->prepare("SELECT type, file_path, uploaded_at FROM documents WHERE plate_number=? ORDER BY uploaded_at DESC");
$stmtD->bind_param('s', $plate);
$stmtD->execute();
$resD = $stmtD->get_result();
if ($resD->num_rows === 0) { echo '<div class="text-sm text-slate-500 italic">No documents uploaded yet.</div>'; }
while ($d = $resD->fetch_assoc()) {
  echo '<div class="flex items-center justify-between text-sm p-2 rounded-lg bg-slate-50 dark:bg-slate-800">';
  echo '  <span class="font-medium text-slate-900 dark:text-slate-200">'.htmlspecialchars($d['type']).'</span>';
  echo '  <div class="flex items-center gap-3">';
  echo '    <span class="text-xs text-slate-500">'.htmlspecialchars($d['uploaded_at']).'</span>';
  echo '    <a class="text-blue-600 hover:text-blue-500 font-medium" href="/tmm/admin/'.htmlspecialchars($d['file_path']).'" target="_blank">View</a>';
  echo '  </div>';
  echo '</div>';
}
echo '  </div>';
// Upload Form
echo '  <div class="border-t border-slate-200 dark:border-slate-700 pt-4">';
echo '    <h4 class="text-sm font-medium text-slate-900 dark:text-slate-200 mb-3">Upload New Documents</h4>';
echo '    <form id="formUpload" class="grid grid-cols-1 md:grid-cols-4 gap-4" method="POST" action="/tmm/admin/api/module1/upload_docs.php">';
echo '      <input type="hidden" name="plate_number" value="'.htmlspecialchars($v['plate_number']).'">';
echo '      <div><label class="block text-xs font-medium text-slate-500 mb-1">OR</label><input name="or" type="file" class="block w-full text-xs text-slate-500 file:mr-2 file:py-1 file:px-2 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"></div>';
echo '      <div><label class="block text-xs font-medium text-slate-500 mb-1">CR</label><input name="cr" type="file" class="block w-full text-xs text-slate-500 file:mr-2 file:py-1 file:px-2 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"></div>';
echo '      <div><label class="block text-xs font-medium text-slate-500 mb-1">Deed of Sale</label><input name="deed" type="file" class="block w-full text-xs text-slate-500 file:mr-2 file:py-1 file:px-2 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"></div>';
echo '      <div class="flex items-end"><button class="'.$btnClass.' w-full">Upload</button></div>';
echo '    </form>';
if (getenv('TMM_AV_SCANNER')) { echo '<p class="mt-2 text-[11px] text-slate-500 dark:text-slate-400">Files are scanned for viruses when uploaded.</p>'; }
echo '    <div id="uploadProgress" class="w-full bg-slate-100 dark:bg-slate-700 h-1.5 rounded-full mt-3 overflow-hidden hidden"><div id="uploadBar" class="h-full bg-blue-600 w-0 transition-all duration-300"></div></div>';
echo '    <div id="uploadMsg" class="text-xs mt-2 font-medium"></div>';
echo '  </div>';
echo '</div>';

echo '</div>'; // End Grid

// Terminal Assignment & Link Operator Grid
echo '<div class="grid grid-cols-1 gap-6 mt-6">';

$stmtA = $db->prepare("SELECT route_id, terminal_name, status, assigned_at FROM terminal_assignments WHERE plate_number=?");
$stmtA->bind_param('s', $plate);
$stmtA->execute();
$a = $stmtA->get_result()->fetch_assoc();

// Terminal Assignment Card
echo '<div class="'.$cardClass.'">';
echo '  <h3 class="text-base font-semibold leading-7 text-slate-900 dark:text-white mb-4">Terminal Assignment</h3>';
echo '  <div class="bg-slate-50 dark:bg-slate-800 rounded-lg p-3 mb-4 text-sm">';
if (!$a) { echo '<span class="text-slate-500 italic">No active assignment.</span>'; } 
else { 
  echo '<div class="flex flex-wrap gap-x-4 gap-y-2">';
  echo '<div><span class="text-slate-500">Route:</span> <span class="font-medium text-slate-900 dark:text-white">'.htmlspecialchars($a['route_id']).'</span></div>';
  echo '<div><span class="text-slate-500">Terminal:</span> <span class="font-medium text-slate-900 dark:text-white">'.htmlspecialchars($a['terminal_name']).'</span></div>';
  echo '<div><span class="text-slate-500">Status:</span> <span class="font-medium text-slate-900 dark:text-white">'.htmlspecialchars($a['status']).'</span></div>';
  echo '</div>';
}
echo '  </div>';
echo '  <form id="formAssign" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3" method="POST" action="/tmm/admin/api/module1/assign_route.php">';
echo '    <input type="hidden" name="plate_number" value="'.htmlspecialchars($v['plate_number']).'">';
echo '    <div class="lg:col-span-1"><input name="route_id" class="'.$inputClass.'" placeholder="Route ID" value="'.htmlspecialchars($a['route_id'] ?? '').'"></div>';
echo '    <div class="lg:col-span-1"><input name="terminal_name" class="'.$inputClass.'" placeholder="Terminal Name" value="'.htmlspecialchars($a['terminal_name'] ?? '').'"></div>';
echo '    <div class="lg:col-span-1"><select name="status" class="'.$inputClass.'"><option '.(($a['status'] ?? '')==='Authorized'?'selected':'').'>Authorized</option><option '.(($a['status'] ?? '')!=='Authorized'?'selected':'').'>Pending</option></select></div>';
echo '    <div class="sm:col-span-2 lg:col-span-2"><button class="'.$btnClass.' w-full">Update Assignment</button></div>';
echo '  </form>';
echo '</div>';

// Link Operator Card
echo '<div class="'.$cardClass.'">';
echo '  <h3 class="text-base font-semibold leading-7 text-slate-900 dark:text-white mb-4">Link Operator / Cooperative</h3>';
echo '  <form id="formLink" class="grid grid-cols-1 md:grid-cols-3 gap-3" method="POST" action="/tmm/admin/api/module1/link_vehicle_operator.php">';
echo '    <input type="hidden" name="plate_number" value="'.htmlspecialchars($v['plate_number']).'">';
echo '    <input name="operator_name" class="'.$inputClass.'" placeholder="Operator Name" value="'.htmlspecialchars($v['operator_name'] ?? '').'">';
echo '    <input name="coop_name" class="'.$inputClass.'" placeholder="Cooperative Name" value="'.htmlspecialchars($v['coop_name'] ?? '').'">';
echo '    <button class="'.$btnClass.'">Link Entity</button>';
echo '  </form>';
echo '</div>';

echo '</div>';

// JavaScript (Minified/Inline)
echo '<script>
(function(){
  function refresh(){
    fetch("api/module1/view_html.php?plate='.htmlspecialchars($v['plate_number']).'")
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
      var originalText = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = "Saving...";
      fetch(f.action, {method:"POST", body:fd})
        .then(()=>{ refresh(); })
        .catch(()=>{ btn.disabled=false; btn.innerHTML=originalText; });
    });
  }
  bind("formStatus");
  bind("formType");
  bind("formAssign");
  bind("formLink");
  
  var fu=document.getElementById("formUpload");
  if(fu){
    fu.addEventListener("submit", function(e){
      e.preventDefault();
      var fd=new FormData(fu);
      var xhr=new XMLHttpRequest();
      var bar=document.getElementById("uploadBar");
      var wrap=document.getElementById("uploadProgress");
      var msg=document.getElementById("uploadMsg");
      
      wrap.classList.remove("hidden");
      bar.style.width="0%";
      msg.textContent="";
      msg.className = "text-xs mt-2 font-medium text-slate-500";
      
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
            msg.className = "text-xs mt-2 font-medium text-emerald-600";
            refresh();
          } else {
            msg.textContent="Upload failed. Please try again.";
            msg.className = "text-xs mt-2 font-medium text-red-600";
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
</script>';
?>
