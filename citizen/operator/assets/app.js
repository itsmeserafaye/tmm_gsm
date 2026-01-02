(function(){
  var tabs = document.querySelectorAll('.tab-btn');
  var sections = {
    dashboard: document.getElementById('tab-dashboard'),
    applications: document.getElementById('tab-applications'),
    compliance: document.getElementById('tab-compliance'),
    notifications: document.getElementById('tab-notifications')
  };
  function setTheme(next){ document.documentElement.classList.toggle('dark', next==='dark'); document.body.classList.toggle('dark', next==='dark'); localStorage.setItem('theme', next); }
  function initTheme(){ var stored = localStorage.getItem('theme'); var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches; setTheme(stored ? stored : (prefersDark ? 'dark' : 'light')); }
  function openTab(name){
    Object.values(sections).forEach(function(el){ el.classList.add('hidden'); });
    if (sections[name]) sections[name].classList.remove('hidden');
    tabs.forEach(function(btn){
      var is = btn.getAttribute('data-tab')===name;
      btn.classList.toggle('active', is);
    });
  }
  window.openTab = openTab;
  function openSub(name){
    openAppModal(name);
  }
  window.openSub = openSub;
  function openAppModal(type){
    var id = 'modal' + type.charAt(0).toUpperCase() + type.slice(1);
    var el = document.getElementById(id);
    if (el) { el.classList.remove('hidden'); el.classList.add('flex'); }
  }
  window.openAppModal = openAppModal;
  function closeAppModal(type){
    var id = 'modal' + type.charAt(0).toUpperCase() + type.slice(1);
    var el = document.getElementById(id);
    if (el) { el.classList.add('hidden'); el.classList.remove('flex'); }
  }
  window.closeAppModal = closeAppModal;
  var themeToggle = document.getElementById('themeToggle');
  if (themeToggle) themeToggle.addEventListener('click', function(){ var isDark = document.documentElement.classList.contains('dark'); setTheme(isDark ? 'light' : 'dark'); });
  tabs.forEach(function(btn){ btn.addEventListener('click', function(){ openTab(btn.getAttribute('data-tab')); }); });
  initTheme();
  openTab('dashboard');
  function fetchJSON(url, opts){ return fetch(url, opts).then(function(r){ return r.json(); }); }
  function formDataFrom(form){ var fd = new FormData(form); return fd; }
  function renderList(el, items, empty){ if (!items || !items.length) { el.textContent = empty || 'No items'; return; } el.innerHTML = items.map(function(i){ return '<div class="p-2 rounded-lg border border-slate-200 dark:border-slate-700 mb-2">'+ i +'</div>'; }).join(''); }
  function renderStatusList(el, items, fields){ if (!items || !items.length) { el.textContent = 'No records'; return; } el.innerHTML = items.map(function(r){ return '<div class="p-2 rounded-lg border border-slate-200 dark:border-slate-700 mb-2"><div class="font-semibold">'+ (r[fields.title]||'') +'</div><div class="text-xs">'+ fields.subtitle.map(function(k){return (k+': '+(r[k]||''));}).join(' • ') +'</div></div>'; }).join(''); }
  function loadOfficers(){
    fetchJSON('api/inspection/officers.php').then(function(j){
      var sel = document.querySelector('#formInspection select[name=inspector_id]');
      sel.innerHTML = (j.items||[]).map(function(o){ return '<option value="'+o.id+'">'+o.name+' ('+o.role+')</option>'; }).join('');
    });
  }
  loadOfficers();
  var formFranchise = document.getElementById('formFranchise');
  formFranchise.addEventListener('submit', function(e){
    e.preventDefault();
    var fd = formDataFrom(formFranchise);
    fetchJSON('../../admin/api/franchise/apply.php', { method:'POST', body: fd }).then(function(j){
      var res = document.getElementById('franchiseResult');
      res.textContent = j.ok ? 'Submitted: ID '+ j.id : (j.error||'Error');
      var ref = fd.get('franchise_ref');
      var op = fd.get('operator_name');
      var url = 'api/franchise/status.php?'+ new URLSearchParams({ franchise_ref: ref||'', operator_name: op||'' }).toString();
      fetchJSON(url).then(function(s){ renderStatusList(document.getElementById('franchiseStatus'), s.items, { title: 'franchise_ref_number', subtitle: ['status','vehicle_count'] }); updateDashboardStats(); });
    });
  });
  var formInspection = document.getElementById('formInspection');
  formInspection.addEventListener('submit', function(e){
    e.preventDefault();
    var fd = formDataFrom(formInspection);
    fetchJSON('../../admin/api/module4/schedule_inspection.php', { method:'POST', body: fd }).then(function(j){
      var res = document.getElementById('inspectionResult');
      res.textContent = j.ok ? ('Scheduled: '+ j.plate_number +' '+ j.status) : (j.error||'Error');
      var plate = fd.get('plate_number');
      var url = 'api/inspection/status.php?'+ new URLSearchParams({ plate_number: plate||'' }).toString();
      fetchJSON(url).then(function(s){ renderStatusList(document.getElementById('inspectionStatus'), s.items, { title: 'plate_number', subtitle: ['status','scheduled_at','location'] }); updateDashboardStats(); });
    });
  });
  var formTerminal = document.getElementById('formTerminal');
  formTerminal.addEventListener('submit', function(e){
    e.preventDefault();
    var fd = formDataFrom(formTerminal);
    fetchJSON('../../admin/api/module5/save_terminal.php', { method:'POST', body: fd }).then(function(j){
      var res = document.getElementById('terminalResult');
      res.textContent = j.success ? ('Submitted: '+ j.app_no) : (j.message||'Error');
      var url = 'api/terminal/status.php?'+ new URLSearchParams({ applicant: fd.get('applicant')||'' }).toString();
      fetchJSON(url).then(function(s){ renderStatusList(document.getElementById('terminalStatus'), s.items, { title: 'application_no', subtitle: ['status','terminal_name'] }); updateDashboardStats(); });
    });
  });
  var formCompliance = document.getElementById('formCompliance');
  formCompliance.addEventListener('submit', function(e){
    e.preventDefault();
    var fd = formDataFrom(formCompliance);
    var url = 'api/compliance/summary.php?'+ new URLSearchParams({ operator_name: fd.get('operator_name')||'', coop_name: fd.get('coop_name')||'' }).toString();
    fetchJSON(url).then(function(j){
      var s = j.summary||{};
      var el = document.getElementById('complianceSummary');
      el.innerHTML = '<div class="grid grid-cols-2 md:grid-cols-4 gap-3">'+
        '<div class="p-3 rounded-lg bg-primary/10"><div class="text-xs">Vehicles</div><div class="text-xl font-bold">'+ (s.vehicle_count||0) +'</div></div>'+
        '<div class="p-3 rounded-lg bg-accent/10"><div class="text-xs">Violations</div><div class="text-xl font-bold">'+ (s.active_violations||0) +'</div></div>'+
        '<div class="p-3 rounded-lg bg-primary/10"><div class="text-xs">Renewals</div><div class="text-xl font-bold">'+ (s.upcoming_renewals||0) +'</div></div>'+
        '<div class="p-3 rounded-lg bg-accent/10"><div class="text-xs">Expired Insp.</div><div class="text-xl font-bold">'+ ((s.expired_inspections||[]).length) +'</div></div>'+
      '</div>';
      var vi = j.violations||[];
      if (!vi.length) document.getElementById('complianceViolations').textContent='No violations';
      else document.getElementById('complianceViolations').innerHTML = vi.map(function(x){ return '<div class="p-2 rounded-lg border border-slate-200 dark:border-slate-700 mb-2"><div class="font-semibold">'+ x.vehicle_plate +' • '+ x.violation_code +'</div><div class="text-xs">'+ x.status +' • '+ x.date_issued +'</div></div>'; }).join('');
      document.getElementById('statViolations').textContent = s.active_violations||0;
      document.getElementById('statRenewals').textContent = s.upcoming_renewals||0;
    });
  });
  var formNotif = document.getElementById('formNotif');
  formNotif.addEventListener('submit', function(e){
    e.preventDefault();
    var fd = formDataFrom(formNotif);
    var url = 'api/notifications/list.php?'+ new URLSearchParams({ operator_name: fd.get('operator_name')||'', plate_number: fd.get('plate_number')||'' }).toString();
    fetchJSON(url).then(function(j){
      var el = document.getElementById('notifList');
      if (!j.items || !j.items.length) el.textContent = 'No notifications';
      else el.innerHTML = j.items.map(function(n){
        var color = n.type==='violation' ? 'bg-accent/10' : 'bg-primary/10';
        return '<div class="p-2 rounded-lg '+color+' mb-2">'+ n.message +'</div>';
      }).join('');
    });
  });
  var uploadModal = document.getElementById('uploadModal');
  function openUpload(){ uploadModal.classList.remove('hidden'); uploadModal.classList.add('flex'); }
  function closeUpload(){ uploadModal.classList.add('hidden'); uploadModal.classList.remove('flex'); document.getElementById('formUpload').reset(); document.getElementById('uploadResult').textContent=''; document.getElementById('precheckResult').textContent=''; }
  window.openUpload = openUpload;
  window.closeUpload = closeUpload;
  function precheckOpen(){ openUpload(); }
  window.precheckOpen = precheckOpen;
  function precheckUpload(){
    var form = document.getElementById('formUpload');
    var fd = new FormData(form);
    fetchJSON('api/documents/precheck.php', { method:'POST', body: fd }).then(function(j){
      var r = j.precheck||{};
      var el = document.getElementById('precheckResult');
      el.textContent = 'Readable: '+ (r.readable?'Yes':'No') +' | Complete: '+ (r.complete?'Yes':'No') +' | Labels OK: '+ (r.labels_ok?'Yes':'No') + (r.issues && r.issues.length ? (' | Issues: '+ r.issues.join(', ')) : '');
    });
  }
  window.precheckUpload = precheckUpload;
  var formUpload = document.getElementById('formUpload');
  formUpload.addEventListener('submit', function(e){
    e.preventDefault();
    var fd = new FormData(formUpload);
    fetchJSON('../../admin/api/module4/verify_documents.php', { method:'POST', body: fd }).then(function(j){
      var el = document.getElementById('uploadResult');
      el.textContent = j.ok ? 'Uploaded: '+ (j.files||[]).join(', ') : (j.error||'Error');
    });
  });
  function updateDashboardStats(){
    fetchJSON('api/dashboard/stats.php').then(function(j){
      document.getElementById('statApplications').textContent = j.applications||0;
      document.getElementById('statViolations').textContent = j.violations||0;
      document.getElementById('statRenewals').textContent = j.renewals||0;
    });
  }
  updateDashboardStats();

  // OCR Logic for Operator Portal
  var opScanPlate = document.getElementById('opScanPlate');
  var opPlateInput = document.getElementById('opPlateInput');
  var opScanStatus = document.getElementById('opScanStatus');
  if (opScanPlate && opPlateInput) {
    opScanPlate.addEventListener('change', function(){
      if (this.files && this.files[0]) {
        var file = this.files[0];
        if (typeof Tesseract === 'undefined') { alert('OCR not loaded'); return; }
        if (opScanStatus) { opScanStatus.classList.remove('hidden'); opScanStatus.textContent = 'Scanning...'; opScanStatus.classList.remove('text-green-600', 'text-red-600'); }
        Tesseract.recognize(file, 'eng').then(function(res){
           var text = res.data.text;
           console.log(text);
           var plateRegex = /([A-Z]{3}[\s-]?[0-9]{3,4})|([0-9]{3,4}[\s-]?[A-Z]{3})/i;
           var match = text.toUpperCase().match(plateRegex);
           if (match) {
             var cleanPlate = match[0].replace(/[\s]/g, '-');
             if (!cleanPlate.includes('-')) {
                if (cleanPlate.match(/^[A-Z]{3}[0-9]{3,4}$/)) cleanPlate = cleanPlate.substring(0, 3) + '-' + cleanPlate.substring(3);
             }
             opPlateInput.value = cleanPlate;
             if (opScanStatus) { opScanStatus.textContent = 'Detected: '+ cleanPlate; opScanStatus.classList.add('text-green-600'); }
           } else {
             if (opScanStatus) { opScanStatus.textContent = 'No plate detected'; opScanStatus.classList.add('text-red-600'); }
           }
        }).catch(function(err){
           console.error(err);
           if (opScanStatus) opScanStatus.textContent = 'Error scanning';
        });
      }
    });
  }

  // Profile Logic
  var modalProfile = document.getElementById('modalProfile');
  var profileView = document.getElementById('profileView');
  var profileEdit = document.getElementById('profileEdit');
  
  function openProfile(){
    if (modalProfile) {
      modalProfile.classList.remove('hidden');
      modalProfile.classList.add('flex');
      toggleProfileEdit(false); // Always start in view mode
    }
  }
  window.openProfile = openProfile;
  
  function closeProfile(){
    if (modalProfile) {
      modalProfile.classList.add('hidden');
      modalProfile.classList.remove('flex');
    }
  }
  window.closeProfile = closeProfile;
  
  function toggleProfileEdit(editMode){
    if (profileView && profileEdit) {
      if (editMode) {
        profileView.classList.add('hidden');
        profileEdit.classList.remove('hidden');
      } else {
        profileEdit.classList.add('hidden');
        profileView.classList.remove('hidden');
      }
    }
  }
  window.toggleProfileEdit = toggleProfileEdit;
  
  var formProfile = document.getElementById('formProfile');
  if (formProfile) {
    formProfile.addEventListener('submit', function(e){
      e.preventDefault();
      // Placeholder for backend integration
      alert('Profile updated successfully (Simulation)');
      toggleProfileEdit(false);
    });
  }
  
  // Profile Image Upload
  var profileUpload = document.getElementById('profileUpload');
  var profileImagePreview = document.getElementById('profileImagePreview');
  if (profileUpload && profileImagePreview) {
    profileUpload.addEventListener('change', function(){
      if (this.files && this.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e){
          profileImagePreview.src = e.target.result;
          profileImagePreview.classList.remove('opacity-0');
          // In a real app, you would upload the file here
        };
        reader.readAsDataURL(this.files[0]);
      }
    });
  }
})(); 
