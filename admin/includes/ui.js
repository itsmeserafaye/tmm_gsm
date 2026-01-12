function openModal(id) {
  var el = document.getElementById(id);
  if (!el) return;
  el.classList.remove('hidden');
  if (window.lucide && window.lucide.createIcons) window.lucide.createIcons();
}
function closeModal(id) {
  var el = document.getElementById(id);
  if (!el) return;
  el.classList.add('hidden');
}
function showToast(msg, type) {
  if (type === undefined) type = 'success';
  var container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    container.className = 'fixed bottom-4 right-4 z-[100] flex flex-col gap-2 pointer-events-none';
    document.body.appendChild(container);
  }
  var toast = document.createElement('div');
  var colors = type === 'success' ? 'bg-green-500' : 'bg-red-500';
  var icon = type === 'success' ? 'check-circle' : 'alert-circle';
  toast.className = colors + ' text-white px-4 py-3 rounded-lg shadow-lg flex items-center gap-3 transform transition-all duration-300 translate-y-10 opacity-0 min-w-[300px]';
  toast.innerHTML = '<i data-lucide="' + icon + '" class="w-5 h-5"></i><span class="font-medium text-sm">' + msg + '</span>';
  container.appendChild(toast);
  if (window.lucide) window.lucide.createIcons();
  requestAnimationFrame(function(){ toast.classList.remove('translate-y-10', 'opacity-0'); });
  setTimeout(function(){ toast.classList.add('opacity-0', 'translate-x-full'); setTimeout(function(){ toast.remove(); }, 300); }, 3000);
}
function handleForm(formId, btnId, successMsg) {
  var form = document.getElementById(formId);
  var btn = document.getElementById(btnId);
  if (!form || !btn) return;
  form.addEventListener('submit', function(e){
    e.preventDefault();
    if (!form.checkValidity()) { form.reportValidity(); return; }
    var originalContent = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Processing...';
    if (window.lucide) window.lucide.createIcons();
    var formData = new FormData(form);
    fetch(form.action, { method: 'POST', body: formData })
      .then(function(res){ return res.json(); })
      .then(function(data){
        if (data.ok || data.status === 'success' || (Array.isArray(data) && data.length > 0)) {
          showToast(successMsg);
          form.reset();
          setTimeout(function(){ location.reload(); }, 1000);
        } else {
          throw new Error(data.error || 'Operation failed');
        }
      })
      .catch(function(err){
        showToast(err.message, 'error');
      })
      .finally(function(){
        btn.disabled = false;
        btn.innerHTML = originalContent;
        if (window.lucide) window.lucide.createIcons();
      });
  });
}
