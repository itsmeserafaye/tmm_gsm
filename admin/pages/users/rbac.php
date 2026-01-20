<?php
require_once __DIR__ . '/../../includes/auth.php';
if (current_user_role() !== 'SuperAdmin') {
  echo '<div class="mx-auto max-w-3xl px-4 py-10">';
  echo '<div class="rounded-2xl border border-rose-200 bg-rose-50 p-6 text-rose-700">';
  echo '<div class="text-lg font-black">Access Denied</div>';
  echo '<div class="mt-1 text-sm font-bold">Only SuperAdmin can manage roles and access control.</div>';
  echo '</div>';
  echo '</div>';
  return;
}
?>

<div class="mx-auto max-w-7xl px-4 py-8 space-y-8">
  <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
    <div>
      <h1 class="text-3xl font-black text-slate-800 dark:text-white flex items-center gap-3">
        <div class="p-3 bg-indigo-500/10 rounded-2xl">
          <i data-lucide="shield-keyhole" class="w-8 h-8 text-indigo-500"></i>
        </div>
        RBAC Management
      </h1>
      <p class="mt-2 text-slate-500 dark:text-slate-400 font-medium ml-14">Role management, user access, and permission matrix in one screen.</p>
    </div>
    <div class="flex items-center gap-3">
      <button id="btnRepair" class="rounded-md bg-amber-100 hover:bg-amber-200 text-amber-700 font-bold py-2.5 px-4 transition-all flex items-center justify-center gap-2" title="Fix database duplicates">
        <i data-lucide="wrench" class="w-4 h-4"></i>
        Repair
      </button>
    </div>
  </div>

  <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
      <div class="inline-flex rounded-2xl bg-slate-100 dark:bg-slate-900/30 p-1 gap-1">
        <button id="tabBtnRoles" type="button" class="px-4 py-2 rounded-xl text-sm font-black bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm">Role Management</button>
        <button id="tabBtnUsers" type="button" class="px-4 py-2 rounded-xl text-sm font-black text-slate-600 dark:text-slate-300 hover:bg-white/60 dark:hover:bg-slate-700/60">User Management</button>
        <button id="tabBtnMatrix" type="button" class="px-4 py-2 rounded-xl text-sm font-black text-slate-600 dark:text-slate-300 hover:bg-white/60 dark:hover:bg-slate-700/60">Permission Matrix</button>
      </div>
      <div class="flex items-center gap-3">
        <div id="toast" class="hidden px-4 py-2 rounded-xl text-sm font-bold"></div>
      </div>
    </div>

    <div class="p-6">
      <div id="tabRoles" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1 space-y-3">
          <div class="flex items-center justify-between">
            <div class="text-sm font-black text-slate-700 dark:text-slate-200">Roles</div>
            <button id="btnNewRole" class="rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-black px-3 py-2">New Role</button>
          </div>
          <input id="roleSearch" type="text" placeholder="Search role..." class="w-full rounded-md border-0 bg-slate-50 dark:bg-slate-900/50 py-2.5 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-indigo-500 transition-all">
          <div id="rolesList" class="divide-y divide-slate-100 dark:divide-slate-700 rounded-lg border border-slate-200 dark:border-slate-700 overflow-hidden"></div>
        </div>

        <div class="lg:col-span-2 space-y-6">
          <div class="rounded-lg border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex items-center justify-between">
              <div>
                <div class="text-sm font-black text-slate-700 dark:text-slate-200">Role Details</div>
                <div id="roleDetailsHint" class="text-xs font-bold text-slate-400">Select a role to edit.</div>
              </div>
              <div class="flex items-center gap-2">
                <button id="btnDeleteRole" class="hidden rounded-lg bg-rose-600 hover:bg-rose-500 text-white text-xs font-black px-3 py-2">Delete</button>
                <button id="btnSaveRole" class="rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-xs font-black px-3 py-2">Save</button>
              </div>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
              <input type="hidden" id="roleId">
              <div class="space-y-2 md:col-span-2">
                <label class="block text-xs font-black text-slate-500 uppercase tracking-wider">Role Name</label>
                <input id="roleName" type="text" placeholder="e.g. Encoder" class="block w-full rounded-md border-0 bg-slate-50 dark:bg-slate-900/50 py-3 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-indigo-500 transition-all">
              </div>
              <div class="space-y-2 md:col-span-2">
                <label class="block text-xs font-black text-slate-500 uppercase tracking-wider">Description</label>
                <input id="roleDescription" type="text" placeholder="Short description..." class="block w-full rounded-md border-0 bg-slate-50 dark:bg-slate-900/50 py-3 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-indigo-500 transition-all">
              </div>
            </div>
          </div>

          <div class="rounded-lg border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
              <div>
                <div class="text-sm font-black text-slate-700 dark:text-slate-200">Assign Permissions</div>
                <div class="text-xs font-bold text-slate-400">Select permissions for the chosen role.</div>
              </div>
              <div class="flex items-center gap-2">
                <input id="permSearch" type="text" placeholder="Search permission..." class="w-full md:w-72 rounded-md border-0 bg-white dark:bg-slate-900/50 py-2.5 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-indigo-500 transition-all">
                <button id="btnSavePerms" class="rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-black px-3 py-2">Save Permissions</button>
              </div>
            </div>
            <div id="permissionsBox" class="p-6 grid grid-cols-1 md:grid-cols-2 gap-3"></div>
          </div>
        </div>
      </div>

      <div id="tabUsers" class="hidden space-y-6">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-slate-100 dark:bg-slate-900/30 rounded-xl">
              <i data-lucide="users" class="w-5 h-5 text-slate-500 dark:text-slate-300"></i>
            </div>
            <div>
              <div class="text-lg font-black text-slate-800 dark:text-white">Users</div>
              <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Assign roles and manage status</div>
            </div>
          </div>
          <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
            <input id="userQ" type="text" placeholder="Search email, name, employee no..." class="w-full sm:w-80 rounded-md border-0 bg-slate-50 dark:bg-slate-900/50 py-2.5 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-indigo-500 transition-all">
            <select id="userStatus" class="w-full sm:w-44 rounded-md border-0 bg-slate-50 dark:bg-slate-900/50 py-2.5 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-indigo-500 transition-all">
              <option value="">All Status</option>
              <option value="Active">Active</option>
              <option value="Inactive">Inactive</option>
              <option value="Locked">Locked</option>
            </select>
            <button id="btnNewUser" class="rounded-md bg-indigo-600 hover:bg-indigo-500 text-white font-bold py-2.5 px-4 transition-all flex items-center justify-center gap-2">
              <i data-lucide="user-plus" class="w-4 h-4"></i>
              Create
            </button>
          </div>
        </div>

        <div class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700">
          <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-800">
            <thead class="bg-slate-50 dark:bg-slate-900/30">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">User</th>
                <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Department</th>
                <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Roles</th>
                <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Status</th>
                <th class="px-4 py-3 text-right text-xs font-black text-slate-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody id="usersBody" class="divide-y divide-slate-100 dark:divide-slate-700"></tbody>
          </table>
        </div>
        <div id="usersMeta" class="text-xs font-bold text-slate-500"></div>
      </div>

      <div id="tabMatrix" class="hidden space-y-4">
        <div class="flex items-center justify-between">
          <div>
            <div class="text-lg font-black text-slate-800 dark:text-white">Permission Matrix</div>
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Roles vs permissions</div>
          </div>
          <button id="btnReloadMatrix" class="rounded-md bg-slate-900 hover:bg-slate-800 text-white font-bold py-2.5 px-4 transition-all flex items-center gap-2">
            <i data-lucide="refresh-cw" class="w-4 h-4"></i>
            Reload
          </button>
        </div>
        <div class="overflow-auto rounded-lg border border-slate-200 dark:border-slate-700">
          <table id="matrixTable" class="min-w-full text-sm bg-white dark:bg-slate-800"></table>
        </div>
      </div>
    </div>
  </div>
</div>

<div id="userModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4 overflow-y-auto">
  <div class="relative w-full max-w-2xl rounded-2xl bg-white dark:bg-slate-800 shadow-2xl ring-1 ring-slate-200 dark:ring-slate-700 flex flex-col max-h-[90vh]">
    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-700 px-6 py-4">
      <div>
        <h3 id="userModalTitle" class="text-lg font-black text-slate-800 dark:text-white">Create User</h3>
        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Assign role to user</p>
      </div>
      <button id="btnCloseUserModal" class="rounded-xl p-2 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-slate-600 transition-colors">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>
    <form id="formUser" class="flex-1 overflow-y-auto p-6 space-y-6">
      <input type="hidden" name="id" id="userId">
      <div id="userErr" class="hidden rounded-xl bg-rose-50 border border-rose-100 px-4 py-3 text-sm font-bold text-rose-600"></div>
      <div id="userOk" class="hidden rounded-xl bg-emerald-50 border border-emerald-100 px-4 py-3 text-sm font-bold text-emerald-600"></div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="space-y-2">
          <label class="block text-xs font-black text-slate-500 uppercase tracking-wider">First Name</label>
          <input name="first_name" required minlength="2" maxlength="80" placeholder="e.g. Juan" class="block w-full rounded-md border-0 bg-slate-50 dark:bg-slate-900/50 py-3 px-4 text-sm font-bold text-slate-900 dark:text-white placeholder:text-slate-400 placeholder:font-medium shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-indigo-500 transition-all">
        </div>
        <div class="space-y-2">
          <label class="block text-xs font-black text-slate-500 uppercase tracking-wider">Last Name</label>
          <input name="last_name" required minlength="2" maxlength="80" placeholder="e.g. Dela Cruz" class="block w-full rounded-md border-0 bg-slate-50 dark:bg-slate-900/50 py-3 px-4 text-sm font-bold text-slate-900 dark:text-white placeholder:text-slate-400 placeholder:font-medium shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-indigo-500 transition-all">
        </div>
        <div class="md:col-span-2 space-y-2">
          <label class="block text-xs font-black text-slate-500 uppercase tracking-wider">Email</label>
          <input name="email" type="email" required placeholder="name@city.gov.ph" class="block w-full rounded-md border-0 bg-slate-50 dark:bg-slate-900/50 py-3 px-4 text-sm font-bold text-slate-900 dark:text-white placeholder:text-slate-400 placeholder:font-medium shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-indigo-500 transition-all">
        </div>
        <div class="space-y-2">
          <label class="block text-xs font-black text-slate-500 uppercase tracking-wider">Employee No.</label>
          <input name="employee_no" maxlength="32" placeholder="e.g. EMP-2024-001" class="block w-full rounded-md border-0 bg-slate-50 dark:bg-slate-900/50 py-3 px-4 text-sm font-bold text-slate-900 dark:text-white placeholder:text-slate-400 placeholder:font-medium shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-indigo-500 transition-all">
        </div>
        <div class="space-y-2">
          <label class="block text-xs font-black text-slate-500 uppercase tracking-wider">Status</label>
          <select name="status" class="block w-full rounded-md border-0 bg-slate-50 dark:bg-slate-900/50 py-3 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-indigo-500 transition-all">
            <option value="Active">Active</option>
            <option value="Inactive">Inactive</option>
            <option value="Locked">Locked</option>
          </select>
        </div>
        <div class="space-y-2 md:col-span-2">
          <label class="block text-xs font-black text-slate-500 uppercase tracking-wider">Department</label>
          <input name="department" maxlength="120" placeholder="e.g. City ICT Office" class="block w-full rounded-md border-0 bg-slate-50 dark:bg-slate-900/50 py-3 px-4 text-sm font-bold text-slate-900 dark:text-white placeholder:text-slate-400 placeholder:font-medium shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-indigo-500 transition-all">
        </div>
        <div class="space-y-2 md:col-span-2">
          <label class="block text-xs font-black text-slate-500 uppercase tracking-wider">Position Title</label>
          <input name="position_title" maxlength="120" placeholder="e.g. Encoder" class="block w-full rounded-md border-0 bg-slate-50 dark:bg-slate-900/50 py-3 px-4 text-sm font-bold text-slate-900 dark:text-white placeholder:text-slate-400 placeholder:font-medium shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-indigo-500 transition-all">
        </div>
      </div>
      <div class="pt-2 border-t border-slate-100 dark:border-slate-700">
        <div class="flex items-center justify-between mb-2">
          <div class="text-xs font-black text-slate-500 uppercase tracking-wider">Roles</div>
          <div class="text-[10px] font-bold text-slate-400">Select at least one</div>
        </div>
        <div id="userRolesBox" class="grid grid-cols-1 sm:grid-cols-2 gap-2"></div>
      </div>
    </form>
    <div class="border-t border-slate-100 dark:border-slate-700 px-6 py-4 bg-slate-50/50 flex justify-end gap-3">
      <button id="btnCancelUser" type="button" class="px-5 py-2.5 rounded-xl text-sm font-bold text-slate-600 hover:bg-slate-200 transition-colors">Cancel</button>
      <button id="btnSaveUser" type="button" class="px-5 py-2.5 rounded-xl text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-500 shadow-lg shadow-indigo-600/20 transition-all flex items-center gap-2">
        <i data-lucide="save" class="w-4 h-4"></i>
        Save
      </button>
    </div>
  </div>
</div>

<script>
  const API_BASE = '<?php echo $rootUrl; ?>/admin/api/settings/';

  let roles = [];
  let permissions = [];
  let rolePermIds = [];

  function toast(msg, ok = true) {
    const el = document.getElementById('toast');
    el.textContent = msg;
    el.className = 'px-4 py-2 rounded-xl text-sm font-bold ' + (ok ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700');
    el.classList.remove('hidden');
    setTimeout(() => el.classList.add('hidden'), 2500);
  }

  function esc(s) {
    return String(s || '').replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
  }

  function byId(id) { return document.getElementById(id); }

  function setTab(tab) {
    const btnRoles = byId('tabBtnRoles');
    const btnUsers = byId('tabBtnUsers');
    const btnMatrix = byId('tabBtnMatrix');
    const tabs = [
      { key: 'roles', btn: btnRoles, el: byId('tabRoles') },
      { key: 'users', btn: btnUsers, el: byId('tabUsers') },
      { key: 'matrix', btn: btnMatrix, el: byId('tabMatrix') },
    ];
    tabs.forEach(t => {
      const active = t.key === tab;
      t.el.classList.toggle('hidden', !active);
      t.btn.classList.toggle('bg-white', active);
      t.btn.classList.toggle('dark:bg-slate-700', active);
      t.btn.classList.toggle('text-slate-900', active);
      t.btn.classList.toggle('dark:text-white', active);
      t.btn.classList.toggle('shadow-sm', active);
      if (!active) {
        t.btn.classList.add('text-slate-600', 'dark:text-slate-300');
      } else {
        t.btn.classList.remove('text-slate-600', 'dark:text-slate-300');
      }
    });
  }

  async function apiGet(path) {
    const res = await fetch(API_BASE + path, { headers: { 'Accept': 'application/json' } });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || 'Request failed');
    return data;
  }

  async function apiPost(path, payload) {
    const res = await fetch(API_BASE + path, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify(payload || {}) });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || 'Request failed');
    return data;
  }

  function renderRolesList(filter = '') {
    const list = byId('rolesList');
    const f = filter.trim().toLowerCase();
    const items = roles.filter(r => !f || String(r.name).toLowerCase().includes(f));
    list.innerHTML = items.map(r => `
      <button type="button" data-role-id="${r.id}" class="w-full text-left px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-700/40">
        <div class="text-sm font-black text-slate-800 dark:text-white">${esc(r.name)}</div>
        <div class="text-xs font-bold text-slate-400">${esc(r.description || '')}</div>
      </button>
    `).join('') || `<div class="px-4 py-6 text-sm font-bold text-slate-500">No roles found.</div>`;
  }

  function renderPermissionsBox(filter = '') {
    const box = byId('permissionsBox');
    const f = filter.trim().toLowerCase();
    const items = permissions.filter(p => !f || String(p.code).toLowerCase().includes(f) || String(p.description).toLowerCase().includes(f));
    box.innerHTML = items.map(p => `
      <label class="flex items-start gap-3 rounded-lg border border-slate-200 dark:border-slate-700 p-3 hover:bg-slate-50 dark:hover:bg-slate-700/30 cursor-pointer">
        <input type="checkbox" class="mt-1" data-perm-id="${p.id}">
        <div>
          <div class="text-sm font-black text-slate-800 dark:text-white">${esc(p.code)}</div>
          <div class="text-xs font-bold text-slate-400">${esc(p.description || '')}</div>
        </div>
      </label>
    `).join('') || `<div class="text-sm font-bold text-slate-500">No permissions found.</div>`;

    const checks = box.querySelectorAll('input[type="checkbox"][data-perm-id]');
    checks.forEach(ch => {
      const pid = parseInt(ch.getAttribute('data-perm-id') || '0', 10);
      ch.checked = rolePermIds.includes(pid);
    });
  }

  async function loadRoles() {
    const data = await apiGet('rbac_roles.php');
    roles = data.roles || [];
    renderRolesList(byId('roleSearch').value || '');
  }

  async function loadPermissions() {
    const data = await apiGet('rbac_permissions.php');
    permissions = data.permissions || [];
    renderPermissionsBox(byId('permSearch').value || '');
  }

  async function selectRole(roleId) {
    const r = roles.find(x => x.id === roleId);
    byId('roleId').value = r ? r.id : '';
    byId('roleName').value = r ? r.name : '';
    byId('roleDescription').value = r ? (r.description || '') : '';
    byId('roleDetailsHint').textContent = r ? `Editing: ${r.name}` : 'Select a role to edit.';
    byId('btnDeleteRole').classList.toggle('hidden', !r || r.name === 'SuperAdmin' || r.name === 'Commuter');

    rolePermIds = [];
    if (r) {
      const data = await apiGet(`rbac_role_permissions_get.php?role_id=${encodeURIComponent(r.id)}`);
      rolePermIds = data.permission_ids || [];
    }
    renderPermissionsBox(byId('permSearch').value || '');
  }

  async function saveRole() {
    const id = parseInt(byId('roleId').value || '0', 10);
    const name = byId('roleName').value || '';
    const description = byId('roleDescription').value || '';
    const data = await apiPost('rbac_role_save.php', { id: id || undefined, name, description });
    await loadRoles();
    await selectRole(data.role_id);
    toast('Role saved.');
  }

  async function deleteRole() {
    const id = parseInt(byId('roleId').value || '0', 10);
    if (!id) return;
    if (!confirm('Delete this role? Users with this role will lose it.')) return;
    await apiPost('rbac_role_delete.php', { id });
    byId('roleId').value = '';
    byId('roleName').value = '';
    byId('roleDescription').value = '';
    rolePermIds = [];
    await loadRoles();
    renderPermissionsBox(byId('permSearch').value || '');
    toast('Role deleted.');
  }

  async function saveRolePermissions() {
    const roleId = parseInt(byId('roleId').value || '0', 10);
    if (!roleId) throw new Error('Select a role first');
    const box = byId('permissionsBox');
    const checks = box.querySelectorAll('input[type="checkbox"][data-perm-id]');
    const ids = [];
    checks.forEach(ch => { if (ch.checked) ids.push(parseInt(ch.getAttribute('data-perm-id') || '0', 10)); });
    await apiPost('rbac_role_permissions_set.php', { role_id: roleId, permission_ids: ids });
    rolePermIds = ids;
    toast('Permissions saved.');
  }

  async function loadUsers() {
    const q = byId('userQ').value || '';
    const status = byId('userStatus').value || '';
    const params = new URLSearchParams({ q, status });
    const data = await apiGet('rbac_users.php?' + params.toString());
    const users = data.users || [];
    const body = byId('usersBody');
    if (!users.length) {
      body.innerHTML = `<tr><td colspan="5" class="px-6 py-10 text-center text-sm font-bold text-slate-500">No users found.</td></tr>`;
      byId('usersMeta').textContent = '0 results';
      return;
    }
    body.innerHTML = users.map(u => {
      const badges = (u.roles && u.roles.length) ? u.roles.map(r => `<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-black bg-indigo-50 text-indigo-700 border border-indigo-100">${esc(r.name)}</span>`).join(' ') : '<span class="text-xs font-bold text-slate-400">—</span>';
      const st = u.status === 'Active' ? 'bg-emerald-100 text-emerald-700' : (u.status === 'Locked' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700');
      return `
        <tr>
          <td class="px-4 py-3">
            <div class="font-black text-slate-900 dark:text-white">${esc(u.first_name)} ${esc(u.last_name)}</div>
            <div class="text-xs font-bold text-slate-500">${esc(u.email)}</div>
          </td>
          <td class="px-4 py-3">
            <div class="text-sm font-bold text-slate-700 dark:text-slate-200">${esc(u.department || '—')}</div>
            <div class="text-xs font-bold text-slate-400">${esc(u.position_title || '')}</div>
          </td>
          <td class="px-4 py-3"><div class="flex flex-wrap gap-1">${badges}</div></td>
          <td class="px-4 py-3"><span class="px-3 py-1 rounded-full text-xs font-black ${st}">${esc(u.status)}</span></td>
          <td class="px-4 py-3 text-right">
            <button type="button" class="text-indigo-600 hover:text-indigo-500 font-black text-sm px-3 py-1.5 rounded-lg hover:bg-indigo-50 transition-colors" data-user='${esc(JSON.stringify(u))}'>Edit</button>
          </td>
        </tr>
      `;
    }).join('');
    byId('usersMeta').textContent = `${users.length} result(s)`;
  }

  function openUserModal(user) {
    const modal = byId('userModal');
    const form = byId('formUser');
    form.reset();
    byId('userErr').classList.add('hidden');
    byId('userOk').classList.add('hidden');

    byId('userModalTitle').textContent = user ? 'Edit User' : 'Create User';
    byId('userId').value = user ? user.id : '';
    if (user) {
      form.first_name.value = user.first_name || '';
      form.last_name.value = user.last_name || '';
      form.email.value = user.email || '';
      form.employee_no.value = user.employee_no || '';
      form.department.value = user.department || '';
      form.position_title.value = user.position_title || '';
      form.status.value = user.status || 'Active';
    }

    const selected = user ? (user.roles || []).map(r => r.id) : [];
    const box = byId('userRolesBox');
    box.innerHTML = roles.filter(r => r.name !== 'Commuter').map(r => {
      const checked = selected.includes(r.id) ? 'checked' : '';
      return `
        <label class="flex items-center gap-3 rounded-lg border border-slate-200 dark:border-slate-700 p-3 hover:bg-slate-50 dark:hover:bg-slate-700/30 cursor-pointer">
          <input type="checkbox" name="roles[]" value="${r.id}" ${checked}>
          <div>
            <div class="text-sm font-black text-slate-800 dark:text-white">${esc(r.name)}</div>
            <div class="text-xs font-bold text-slate-400">${esc(r.description || '')}</div>
          </div>
        </label>
      `;
    }).join('');

    modal.classList.remove('hidden');
    modal.classList.add('flex');
    if (window.lucide) window.lucide.createIcons();
  }

  function closeUserModal() {
    const modal = byId('userModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }

  async function saveUser() {
    const form = byId('formUser');
    if (!form.checkValidity()) { form.reportValidity(); return; }

    const err = byId('userErr');
    const ok = byId('userOk');
    err.classList.add('hidden');
    ok.classList.add('hidden');

    const formData = new FormData(form);
    const payload = Object.fromEntries(formData.entries());
    const roleChecks = form.querySelectorAll('input[name="roles[]"]:checked');
    payload.roles = Array.from(roleChecks).map(cb => parseInt(cb.value, 10));
    delete payload['roles[]'];

    if (!payload.roles.length) {
      err.textContent = 'Please select at least one role.';
      err.classList.remove('hidden');
      return;
    }

    const isEdit = !!payload.id;
    const endpoint = isEdit ? 'rbac_user_update.php' : 'rbac_user_create.php';
    try {
      const data = await apiPost(endpoint, payload);
      if (!isEdit && data.temporary_password) {
        ok.textContent = 'Temporary password: ' + data.temporary_password;
        ok.classList.remove('hidden');
      } else {
        ok.textContent = 'Saved.';
        ok.classList.remove('hidden');
        setTimeout(closeUserModal, 700);
      }
      await loadUsers();
    } catch (e) {
      err.textContent = e.message;
      err.classList.remove('hidden');
    }
  }

  function renderMatrixTable(rolesList, permsList, map) {
    const table = byId('matrixTable');
    if (!rolesList.length || !permsList.length) {
      table.innerHTML = '<tr><td class="px-6 py-8 text-sm font-bold text-slate-500">No data.</td></tr>';
      return;
    }
    const head = `
      <thead class="bg-slate-50 dark:bg-slate-900/30 sticky top-0">
        <tr>
          <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider sticky left-0 bg-slate-50 dark:bg-slate-900/30">Permission</th>
          ${rolesList.map(r => `<th class="px-4 py-3 text-center text-xs font-black text-slate-500 uppercase tracking-wider">${esc(r.name)}</th>`).join('')}
        </tr>
      </thead>
    `;
    const body = `
      <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
        ${permsList.map(p => {
          return `<tr>
            <td class="px-4 py-3 sticky left-0 bg-white dark:bg-slate-800">
              <div class="text-sm font-black text-slate-800 dark:text-white">${esc(p.code)}</div>
              <div class="text-xs font-bold text-slate-400">${esc(p.description || '')}</div>
            </td>
            ${rolesList.map(r => {
              const has = map && map[r.id] && map[r.id][p.id];
              return `<td class="px-4 py-3 text-center">${has ? '<span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 font-black">✓</span>' : '<span class="text-slate-300">—</span>'}</td>`;
            }).join('')}
          </tr>`;
        }).join('')}
      </tbody>
    `;
    table.innerHTML = head + body;
  }

  async function loadMatrix() {
    const data = await apiGet('rbac_matrix.php');
    renderMatrixTable(data.roles || [], data.permissions || [], data.map || {});
  }

  async function runRepair() {
    if (!confirm('Run RBAC repair? This deduplicates roles and fixes constraints.')) return;
    await apiPost('rbac_repair.php', {});
    await loadRoles();
    await loadPermissions();
    await loadUsers();
    await loadMatrix();
    toast('Repair done.');
  }

  document.addEventListener('DOMContentLoaded', async function () {
    if (window.lucide) window.lucide.createIcons();

    byId('tabBtnRoles').addEventListener('click', () => setTab('roles'));
    byId('tabBtnUsers').addEventListener('click', async () => { setTab('users'); await loadUsers(); });
    byId('tabBtnMatrix').addEventListener('click', async () => { setTab('matrix'); await loadMatrix(); });

    byId('btnRepair').addEventListener('click', async () => { try { await runRepair(); } catch (e) { toast(e.message, false); } });
    byId('roleSearch').addEventListener('input', function () { renderRolesList(this.value || ''); });
    byId('permSearch').addEventListener('input', function () { renderPermissionsBox(this.value || ''); });

    byId('rolesList').addEventListener('click', async function (e) {
      const btn = e.target.closest('button[data-role-id]');
      if (!btn) return;
      const rid = parseInt(btn.getAttribute('data-role-id') || '0', 10);
      try { await selectRole(rid); } catch (err) { toast(err.message, false); }
    });

    byId('btnNewRole').addEventListener('click', function () {
      byId('roleId').value = '';
      byId('roleName').value = '';
      byId('roleDescription').value = '';
      rolePermIds = [];
      byId('btnDeleteRole').classList.add('hidden');
      byId('roleDetailsHint').textContent = 'Creating a new role.';
      renderPermissionsBox(byId('permSearch').value || '');
    });
    byId('btnSaveRole').addEventListener('click', async () => { try { await saveRole(); } catch (e) { toast(e.message, false); } });
    byId('btnDeleteRole').addEventListener('click', async () => { try { await deleteRole(); } catch (e) { toast(e.message, false); } });
    byId('btnSavePerms').addEventListener('click', async () => { try { await saveRolePermissions(); } catch (e) { toast(e.message, false); } });

    byId('userQ').addEventListener('input', async function () { try { await loadUsers(); } catch (e) { toast(e.message, false); } });
    byId('userStatus').addEventListener('change', async function () { try { await loadUsers(); } catch (e) { toast(e.message, false); } });
    byId('btnNewUser').addEventListener('click', function () { openUserModal(null); });

    byId('usersBody').addEventListener('click', function (e) {
      const btn = e.target.closest('button[data-user]');
      if (!btn) return;
      try {
        const u = JSON.parse(btn.getAttribute('data-user'));
        openUserModal(u);
      } catch (err) { toast('Failed to open user', false); }
    });

    byId('btnCloseUserModal').addEventListener('click', closeUserModal);
    byId('btnCancelUser').addEventListener('click', closeUserModal);
    byId('btnSaveUser').addEventListener('click', async () => { await saveUser(); });
    byId('userModal').addEventListener('click', function (e) { if (e.target === this) closeUserModal(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeUserModal(); });

    byId('btnReloadMatrix').addEventListener('click', async () => { try { await loadMatrix(); } catch (e) { toast(e.message, false); } });

    setTab('roles');
    try {
      await loadRoles();
      await loadPermissions();
      await selectRole(roles.length ? roles[0].id : 0);
    } catch (e) {
      toast(e.message, false);
    }
  });
</script>

