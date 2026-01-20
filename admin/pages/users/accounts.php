<?php
require_once __DIR__ . '/../../includes/auth.php';
if (current_user_role() !== 'SuperAdmin') {
  echo '<div class="mx-auto max-w-3xl px-4 py-10">';
  echo '<div class="rounded-2xl border border-rose-200 bg-rose-50 p-6 text-rose-700">';
  echo '<div class="text-lg font-black">Access Denied</div>';
  echo '<div class="mt-1 text-sm font-bold">Only City ICT Office administrators can manage accounts.</div>';
  echo '</div>';
  echo '</div>';
  return;
}
?>

<div class="mx-auto max-w-7xl px-4 py-8 space-y-8">
  <!-- Header -->
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
    <div>
      <h1 class="text-3xl font-black text-slate-800 dark:text-white flex items-center gap-3">
        <div class="p-3 bg-indigo-500/10 rounded-2xl">
          <i data-lucide="users-cog" class="w-8 h-8 text-indigo-500"></i>
        </div>
        Accounts & Roles
      </h1>
      <p class="mt-2 text-slate-500 dark:text-slate-400 font-medium ml-14">City Government RBAC management for system access.</p>
    </div>
    <div class="flex items-center gap-3">
      <button id="btnOpenCreate" class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold py-2.5 px-5 rounded-xl shadow-lg shadow-indigo-600/25 transition-all flex items-center gap-2">
        <i data-lucide="user-plus" class="w-4 h-4"></i>
        Create Account
      </button>
    </div>
  </div>

  <!-- Tabs -->
  <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
    <div class="inline-flex rounded-2xl bg-slate-100 dark:bg-slate-900/30 p-1 gap-1">
      <button id="tabBtnStaff" type="button" class="px-4 py-2 rounded-xl text-sm font-black bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-sm transition-all">Staff Accounts</button>
      <button id="tabBtnCommuters" type="button" class="px-4 py-2 rounded-xl text-sm font-black text-slate-600 dark:text-slate-300 hover:bg-white/60 dark:hover:bg-slate-800/60 transition-all">Citizen Accounts</button>
      <button id="tabBtnOperators" type="button" class="px-4 py-2 rounded-xl text-sm font-black text-slate-600 dark:text-slate-300 hover:bg-white/60 dark:hover:bg-slate-800/60 transition-all">Operator Accounts</button>
    </div>
  </div>

  <!-- Staff Tab -->
  <div id="tabStaff" class="space-y-6">
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
      <!-- Toolbar -->
      <div class="px-6 py-5 border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="flex items-center gap-3">
          <div class="p-2 bg-slate-100 dark:bg-slate-900/30 rounded-xl">
            <i data-lucide="search" class="w-5 h-5 text-slate-500 dark:text-slate-300"></i>
          </div>
          <div>
            <h2 class="text-lg font-black text-slate-800 dark:text-white">Staff Directory</h2>
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Authorized Personnel</p>
          </div>
        </div>
        <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
          <input id="q" type="text" placeholder="Search name, email, employee no..." class="w-full sm:w-72 rounded-xl border-0 bg-white dark:bg-slate-900/50 py-2.5 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-indigo-500 transition-all">
          <select id="statusFilter" class="w-full sm:w-40 rounded-xl border-0 bg-white dark:bg-slate-900/50 py-2.5 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-indigo-500 transition-all">
            <option value="">All Status</option>
            <option value="Active">Active</option>
            <option value="Inactive">Inactive</option>
            <option value="Locked">Locked</option>
          </select>
          <button id="btnRefresh" class="rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold py-2.5 px-4 transition-all flex items-center justify-center gap-2">
            <i data-lucide="refresh-cw" class="w-4 h-4"></i>
            Refresh
          </button>
          <button id="btnRepair" class="rounded-xl bg-amber-100 hover:bg-amber-200 text-amber-700 font-bold py-2.5 px-4 transition-all flex items-center justify-center gap-2" title="Fix database duplicates">
            <i data-lucide="wrench" class="w-4 h-4"></i>
          </button>
        </div>
      </div>

      <!-- Table -->
      <div class="relative">
        <div id="usersLoader" class="hidden absolute inset-0 bg-white/80 dark:bg-slate-800/80 z-10 flex items-center justify-center backdrop-blur-sm">
          <div class="flex flex-col items-center gap-3">
            <div class="w-8 h-8 border-4 border-indigo-500/30 border-t-indigo-600 rounded-full animate-spin"></div>
            <div class="text-xs font-black text-indigo-600 uppercase tracking-wider">Loading...</div>
          </div>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead class="bg-slate-50 dark:bg-slate-900/30 border-b border-slate-200 dark:border-slate-700">
              <tr>
                <th class="px-6 py-4 text-xs font-black text-slate-500 uppercase tracking-wider">User Profile</th>
                <th class="px-6 py-4 text-xs font-black text-slate-500 uppercase tracking-wider">Department / Position</th>
                <th class="px-6 py-4 text-xs font-black text-slate-500 uppercase tracking-wider">Assigned Roles</th>
                <th class="px-6 py-4 text-xs font-black text-slate-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-4 text-right text-xs font-black text-slate-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody id="usersBody" class="divide-y divide-slate-100 dark:divide-slate-700/50"></tbody>
          </table>
        </div>
      </div>
      
      <!-- Footer -->
      <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex justify-between items-center">
        <div id="usersMeta" class="text-xs font-bold text-slate-500"></div>
      </div>
    </div>
  </div>

  <!-- Commuters Tab (Placeholder for structure, content loaded dynamically) -->
  <div id="tabCommuters" class="hidden space-y-6">
    <!-- Similar structure as Staff but handled by existing logic adapted -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 p-8 text-center">
      <h3 class="text-lg font-bold text-slate-800">Citizen Accounts</h3>
      <p class="text-slate-500 mb-4">Manage commuter/citizen accounts via the separate module logic.</p>
      <!-- We will inject the existing commuter table here via JS if needed, or keep it simple -->
      <div id="commuterWrapper"></div> 
    </div>
  </div>

  <!-- Operators Tab -->
  <div id="tabOperators" class="hidden space-y-6">
    <div id="operatorWrapper"></div>
  </div>
</div>

<!-- Create/Edit Modal -->
<div id="userModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4 overflow-y-auto">
  <div class="relative w-full max-w-2xl rounded-2xl bg-white dark:bg-slate-800 shadow-2xl ring-1 ring-slate-200 dark:ring-slate-700 flex flex-col max-h-[90vh]">
    <!-- Modal Header -->
    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-700 px-6 py-4">
      <div class="flex items-center gap-3">
        <div class="p-2 bg-indigo-50 rounded-xl">
          <i data-lucide="user-cog" class="w-5 h-5 text-indigo-600"></i>
        </div>
        <div>
          <h3 id="modalTitle" class="text-lg font-black text-slate-800 dark:text-white">Create Account</h3>
          <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">HR-based access provisioning</p>
        </div>
      </div>
      <button onclick="closeModal()" class="rounded-xl p-2 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-slate-600 transition-colors">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>

    <!-- Modal Body -->
    <form id="formUser" class="flex-1 overflow-y-auto p-6 space-y-6">
      <input type="hidden" name="id" id="userId">
      
      <!-- Alerts -->
      <div id="modalError" class="hidden rounded-xl bg-rose-50 border border-rose-100 px-4 py-3 text-sm font-bold text-rose-600 flex items-start gap-2">
        <i data-lucide="alert-circle" class="w-5 h-5 shrink-0"></i>
        <span id="modalErrorText"></span>
      </div>
      <div id="modalSuccess" class="hidden rounded-xl bg-emerald-50 border border-emerald-100 px-4 py-3 text-sm font-bold text-emerald-600 flex items-start gap-2">
        <i data-lucide="check-circle" class="w-5 h-5 shrink-0"></i>
        <span id="modalSuccessText"></span>
      </div>

      <!-- Personal Info -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="space-y-1">
          <label class="text-xs font-black text-slate-500 uppercase tracking-wider">First Name <span class="text-rose-500">*</span></label>
          <input type="text" name="first_name" required class="w-full rounded-xl border-slate-200 bg-slate-50/50 text-sm font-bold focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div class="space-y-1">
          <label class="text-xs font-black text-slate-500 uppercase tracking-wider">Last Name <span class="text-rose-500">*</span></label>
          <input type="text" name="last_name" required class="w-full rounded-xl border-slate-200 bg-slate-50/50 text-sm font-bold focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div class="md:col-span-2 space-y-1">
          <label class="text-xs font-black text-slate-500 uppercase tracking-wider">Email Address <span class="text-rose-500">*</span></label>
          <input type="email" name="email" required class="w-full rounded-xl border-slate-200 bg-slate-50/50 text-sm font-bold focus:border-indigo-500 focus:ring-indigo-500">
        </div>
      </div>

      <!-- Employment Info -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2 border-t border-slate-100">
        <div class="space-y-1">
          <label class="text-xs font-black text-slate-500 uppercase tracking-wider">Employee No.</label>
          <input type="text" name="employee_no" class="w-full rounded-xl border-slate-200 bg-slate-50/50 text-sm font-bold focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div class="space-y-1">
          <label class="text-xs font-black text-slate-500 uppercase tracking-wider">Department</label>
          <select name="department" class="w-full rounded-xl border-slate-200 bg-slate-50/50 text-sm font-bold focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">Select Department</option>
            <option value="City ICT Office">City ICT Office</option>
            <option value="Traffic Management Office">Traffic Management Office</option>
            <option value="Administration">Administration</option>
            <option value="Treasury">Treasury</option>
            <option value="Engineering">Engineering</option>
          </select>
        </div>
        <div class="space-y-1">
          <label class="text-xs font-black text-slate-500 uppercase tracking-wider">Position Title</label>
          <input type="text" name="position_title" class="w-full rounded-xl border-slate-200 bg-slate-50/50 text-sm font-bold focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div class="space-y-1">
          <label class="text-xs font-black text-slate-500 uppercase tracking-wider">Status</label>
          <select name="status" class="w-full rounded-xl border-slate-200 bg-slate-50/50 text-sm font-bold focus:border-indigo-500 focus:ring-indigo-500">
            <option value="Active">Active</option>
            <option value="Inactive">Inactive</option>
            <option value="Locked">Locked</option>
          </select>
        </div>
      </div>

      <!-- Roles -->
      <div class="space-y-3 pt-2 border-t border-slate-100">
        <div class="flex items-center justify-between">
          <label class="text-xs font-black text-slate-500 uppercase tracking-wider">Assigned Roles</label>
          <span class="text-[10px] font-bold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">Select at least one</span>
        </div>
        <div id="rolesContainer" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div class="text-sm text-slate-500 italic">Loading roles...</div>
        </div>
      </div>
    </form>

    <!-- Modal Footer -->
    <div class="border-t border-slate-100 dark:border-slate-700 px-6 py-4 bg-slate-50/50 flex justify-end gap-3">
      <button onclick="closeModal()" type="button" class="px-5 py-2.5 rounded-xl text-sm font-bold text-slate-600 hover:bg-slate-200 transition-colors">Cancel</button>
      <button onclick="saveUser()" type="button" id="btnSaveUser" class="px-5 py-2.5 rounded-xl text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-500 shadow-lg shadow-indigo-600/20 transition-all flex items-center gap-2">
        <i data-lucide="save" class="w-4 h-4"></i>
        <span>Save Account</span>
      </button>
    </div>
  </div>
</div>

<script>
// --- Configuration ---
const API_BASE = '<?php echo $rootUrl; ?>/admin/api/settings/';

// --- State ---
let rolesCache = [];

// --- Init ---
document.addEventListener('DOMContentLoaded', () => {
  if(window.lucide) lucide.createIcons();
  loadRoles(); // Pre-fetch roles
  loadUsers(); // Load staff list
  
  // Event Listeners
  document.getElementById('btnRefresh').addEventListener('click', loadUsers);
  document.getElementById('btnRepair').addEventListener('click', runRepair);
  document.getElementById('q').addEventListener('input', debounce(loadUsers, 300));
  document.getElementById('statusFilter').addEventListener('change', loadUsers);
  document.getElementById('btnOpenCreate').addEventListener('click', () => openUserModal());

  // Tabs
  document.getElementById('tabBtnStaff').addEventListener('click', () => switchTab('staff'));
  document.getElementById('tabBtnCommuters').addEventListener('click', () => switchTab('commuters'));
  document.getElementById('tabBtnOperators').addEventListener('click', () => switchTab('operators'));
});

// --- Tabs Logic ---
function switchTab(tab) {
  // Update buttons
  const btns = {
    staff: document.getElementById('tabBtnStaff'),
    commuters: document.getElementById('tabBtnCommuters'),
    operators: document.getElementById('tabBtnOperators')
  };
  
  Object.values(btns).forEach(b => {
    b.classList.remove('bg-white', 'text-slate-900', 'shadow-sm');
    b.classList.add('text-slate-600', 'hover:bg-white/60');
  });
  
  btns[tab].classList.add('bg-white', 'text-slate-900', 'shadow-sm');
  btns[tab].classList.remove('text-slate-600', 'hover:bg-white/60');

  // Update Content
  document.getElementById('tabStaff').classList.add('hidden');
  document.getElementById('tabCommuters').classList.add('hidden');
  document.getElementById('tabOperators').classList.add('hidden');
  
  if (tab === 'staff') document.getElementById('tabStaff').classList.remove('hidden');
  if (tab === 'commuters') {
    document.getElementById('tabCommuters').classList.remove('hidden');
    // Lazy load commuters if not already loaded (omitted for brevity, assume existing logic matches)
  }
  if (tab === 'operators') {
    document.getElementById('tabOperators').classList.remove('hidden');
  }
}

// --- User Management (Staff) ---

async function runRepair() {
  if (!confirm('Run database diagnostics and repair? This will deduplicate roles and fix constraints.')) return;
  
  const btn = document.getElementById('btnRepair');
  const icon = btn.querySelector('i');
  btn.classList.add('opacity-50', 'pointer-events-none');
  
  try {
    const res = await fetch(`${API_BASE}rbac_repair.php`, { method: 'POST' });
    const data = await res.json();
    if (data.ok) {
      alert('Success: ' + data.message);
      loadUsers();
      loadRoles();
    } else {
      alert('Repair Failed: ' + (data.error || 'Unknown error'));
    }
  } catch (e) {
    alert('Repair Error: ' + e.message);
  } finally {
    btn.classList.remove('opacity-50', 'pointer-events-none');
  }
}

async function loadUsers() {
  const loader = document.getElementById('usersLoader');
  const tbody = document.getElementById('usersBody');
  const meta = document.getElementById('usersMeta');
  const q = document.getElementById('q').value;
  const status = document.getElementById('statusFilter').value;

  loader.classList.remove('hidden');
  
  try {
    const params = new URLSearchParams({ q, status });
    const res = await fetch(`${API_BASE}rbac_users.php?${params}`);
    const data = await res.json();

    if (!data.ok) throw new Error(data.error || 'Failed to load users');

    const users = data.users || [];
    renderUsers(users);
    meta.textContent = `${users.length} staff member${users.length !== 1 ? 's' : ''} found`;

  } catch (err) {
    console.error(err);
    tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-8 text-center text-sm font-bold text-rose-500">Error: ${err.message}</td></tr>`;
  } finally {
    loader.classList.add('hidden');
  }
}

function renderUsers(users) {
  const tbody = document.getElementById('usersBody');
  if (users.length === 0) {
    tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-12 text-center text-slate-500 font-bold">No staff accounts found matching your criteria.</td></tr>`;
    return;
  }

  tbody.innerHTML = users.map(u => {
    // Role Badges
    const roleBadges = (u.roles && u.roles.length) 
      ? u.roles.map(r => `<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-black bg-indigo-50 text-indigo-700 border border-indigo-100">${escapeHtml(r.name)}</span>`).join(' ')
      : '<span class="text-xs text-slate-400 italic">No roles</span>';

    // Status Badge
    let statusClass = 'bg-slate-100 text-slate-600';
    if (u.status === 'Active') statusClass = 'bg-emerald-100 text-emerald-700';
    if (u.status === 'Locked') statusClass = 'bg-rose-100 text-rose-700';
    if (u.status === 'Inactive') statusClass = 'bg-amber-100 text-amber-700';

    return `
      <tr class="hover:bg-slate-50/50 transition-colors group">
        <td class="px-6 py-4">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center font-black text-sm">
              ${getInitials(u.first_name, u.last_name)}
            </div>
            <div>
              <div class="font-bold text-slate-900 text-sm">${escapeHtml(u.first_name)} ${escapeHtml(u.last_name)}</div>
              <div class="text-xs text-slate-500">${escapeHtml(u.email)}</div>
            </div>
          </div>
        </td>
        <td class="px-6 py-4">
          <div class="text-sm font-bold text-slate-700">${escapeHtml(u.department || '—')}</div>
          <div class="text-xs text-slate-500">${escapeHtml(u.position_title || '')}</div>
        </td>
        <td class="px-6 py-4">
          <div class="flex flex-wrap gap-1 max-w-xs">
            ${roleBadges}
          </div>
        </td>
        <td class="px-6 py-4">
          <span class="px-2.5 py-1 rounded-full text-xs font-black ${statusClass}">
            ${escapeHtml(u.status)}
          </span>
        </td>
        <td class="px-6 py-4 text-right">
          <button onclick='openUserModal(${JSON.stringify(u)})' class="text-indigo-600 hover:text-indigo-500 font-bold text-sm px-3 py-1.5 rounded-lg hover:bg-indigo-50 transition-colors">
            Edit
          </button>
        </td>
      </tr>
    `;
  }).join('');
  
  if(window.lucide) lucide.createIcons();
}

// --- Roles & Modal ---

async function loadRoles() {
  try {
    const res = await fetch(`${API_BASE}rbac_roles.php`);
    const data = await res.json();
    if (data.ok) {
      rolesCache = data.roles || [];
      renderRoleCheckboxes();
    }
  } catch (e) {
    console.error('Failed to load roles', e);
  }
}

function renderRoleCheckboxes(selectedIds = []) {
  const container = document.getElementById('rolesContainer');
  if (!rolesCache.length) {
    container.innerHTML = '<div class="text-sm text-slate-400">No roles defined in system.</div>';
    return;
  }
  
  container.innerHTML = rolesCache.map(r => {
    const isChecked = selectedIds.includes(r.id) ? 'checked' : '';
    return `
      <label class="relative flex items-start p-3 rounded-xl border border-slate-200 cursor-pointer hover:bg-slate-50 transition-colors has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50/30">
        <div class="flex items-center h-5">
          <input type="checkbox" name="roles[]" value="${r.id}" ${isChecked} class="w-4 h-4 text-indigo-600 border-slate-300 rounded focus:ring-indigo-500">
        </div>
        <div class="ml-3 text-sm">
          <span class="font-bold text-slate-900 block">${escapeHtml(r.name)}</span>
          <span class="text-xs text-slate-500 block leading-tight mt-0.5">${escapeHtml(r.description || '')}</span>
        </div>
      </label>
    `;
  }).join('');
}

function openUserModal(user = null) {
  const modal = document.getElementById('userModal');
  const form = document.getElementById('formUser');
  const title = document.getElementById('modalTitle');
  const errorDiv = document.getElementById('modalError');
  const successDiv = document.getElementById('modalSuccess');
  
  // Reset Form
  form.reset();
  errorDiv.classList.add('hidden');
  successDiv.classList.add('hidden');
  
  if (user) {
    // Edit Mode
    title.textContent = 'Edit Account';
    document.getElementById('userId').value = user.id;
    form.first_name.value = user.first_name || '';
    form.last_name.value = user.last_name || '';
    form.email.value = user.email || '';
    form.employee_no.value = user.employee_no || '';
    form.department.value = user.department || '';
    form.position_title.value = user.position_title || '';
    form.status.value = user.status || 'Active';
    
    const roleIds = (user.roles || []).map(r => r.id);
    renderRoleCheckboxes(roleIds);
  } else {
    // Create Mode
    title.textContent = 'Create Account';
    document.getElementById('userId').value = '';
    renderRoleCheckboxes([]); // Clear checks
  }
  
  modal.classList.remove('hidden');
  modal.classList.add('flex');
}

function closeModal() {
  const modal = document.getElementById('userModal');
  modal.classList.add('hidden');
  modal.classList.remove('flex');
}

async function saveUser() {
  const form = document.getElementById('formUser');
  const userId = document.getElementById('userId').value;
  const errorDiv = document.getElementById('modalError');
  const errorText = document.getElementById('modalErrorText');
  const successDiv = document.getElementById('modalSuccess');
  const successText = document.getElementById('modalSuccessText');
  
  // Basic Validation
  if (!form.checkValidity()) {
    form.reportValidity();
    return;
  }
  
  // Gather Data
  const formData = new FormData(form);
  const payload = Object.fromEntries(formData.entries());
  
  // Handle roles array manually (FormData entries() handles checkboxes poorly for JSON)
  const roleCheckboxes = form.querySelectorAll('input[name="roles[]"]:checked');
  payload.roles = Array.from(roleCheckboxes).map(cb => parseInt(cb.value));
  
  const isEdit = !!userId;
  const endpoint = isEdit ? 'rbac_user_update.php' : 'rbac_user_create.php';
  
  errorDiv.classList.add('hidden');
  successDiv.classList.add('hidden');
  
  try {
    const res = await fetch(`${API_BASE}${endpoint}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    
    if (!data.ok) throw new Error(data.error || 'Operation failed');
    
    // Success
    if (isEdit) {
      successText.textContent = 'Account updated successfully.';
      successDiv.classList.remove('hidden');
      setTimeout(() => { closeModal(); loadUsers(); }, 1000);
    } else {
      // Show temp password if created
      const msg = `Account created! Temp Password: ${data.temporary_password}`;
      successText.textContent = msg;
      successDiv.classList.remove('hidden');
      loadUsers(); // Refresh list immediately behind modal
      // Don't close immediately so they can copy password
    }
    
  } catch (err) {
    errorText.textContent = err.message;
    errorDiv.classList.remove('hidden');
  }
}

// --- Utils ---
function escapeHtml(text) {
  if (!text) return '';
  return String(text)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

function getInitials(f, l) {
  return ((f?.[0] || '') + (l?.[0] || '')).toUpperCase();
}

function debounce(func, wait) {
  let timeout;
  return function(...args) {
    clearTimeout(timeout);
    timeout = setTimeout(() => func.apply(this, args), wait);
  };
}
</script>
