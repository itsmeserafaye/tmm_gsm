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

<div class="mx-auto max-w-6xl px-4 py-8 space-y-8">
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

  <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <div class="px-8 py-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
      <div class="flex items-center gap-3">
        <div class="p-2 bg-slate-100 dark:bg-slate-900/30 rounded-xl">
          <i data-lucide="search" class="w-5 h-5 text-slate-500 dark:text-slate-300"></i>
        </div>
        <div>
          <h2 class="text-lg font-black text-slate-800 dark:text-white">User Directory</h2>
          <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Accounts issued by ICTO</p>
        </div>
      </div>
      <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
        <input id="q" type="text" placeholder="Search email, name, employee no..." class="w-full sm:w-80 rounded-md border-0 bg-white dark:bg-slate-900/50 py-2.5 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-blue-500 transition-all">
        <select id="statusFilter" class="w-full sm:w-44 rounded-md border-0 bg-white dark:bg-slate-900/50 py-2.5 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-blue-500 transition-all">
          <option value="">All Status</option>
          <option value="Active">Active</option>
          <option value="Inactive">Inactive</option>
          <option value="Locked">Locked</option>
        </select>
        <button id="btnRefresh" class="rounded-md bg-slate-900 hover:bg-slate-800 text-white font-bold py-2.5 px-4 transition-all flex items-center justify-center gap-2">
          <i data-lucide="refresh-cw" class="w-4 h-4"></i>
          Refresh
        </button>
      </div>
    </div>

    <div class="p-6">
      <div id="usersError" class="hidden mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-700"></div>
      <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-700">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
          <thead class="bg-slate-50 dark:bg-slate-900/30">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">User</th>
              <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Department</th>
              <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Roles</th>
              <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Status</th>
              <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Last Login</th>
              <th class="px-4 py-3 text-right text-xs font-black text-slate-500 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody id="usersBody" class="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-800"></tbody>
        </table>
      </div>
      <div id="usersMeta" class="mt-3 text-xs font-bold text-slate-500"></div>
    </div>
  </div>
</div>

<div id="modalBackdrop" class="fixed inset-0 bg-black/40 hidden items-start justify-center pt-14 px-4 z-50 overflow-y-auto">
  <div class="w-full max-w-3xl bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div class="p-2 bg-indigo-500/10 rounded-xl">
          <i data-lucide="user-plus" class="w-5 h-5 text-indigo-600"></i>
        </div>
        <div>
          <div id="modalTitle" class="text-base font-black text-slate-900 dark:text-white">Create Account</div>
          <div class="text-xs font-bold text-slate-500">HR-based access provisioning</div>
        </div>
      </div>
      <button id="btnCloseModal" class="p-2 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>
    <div class="p-6">
      <div id="modalError" class="hidden mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-700"></div>
      <div id="modalSuccess" class="hidden mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700"></div>

      <form id="userForm" class="space-y-6">
        <input type="hidden" name="id" id="userId" value="">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-xs font-black text-slate-500 uppercase tracking-wider mb-2">First Name <span class="text-rose-500">*</span></label>
            <input name="first_name" id="firstName" required placeholder="e.g. Juan" class="w-full rounded-xl border-0 bg-slate-50 dark:bg-slate-800 py-2.5 px-4 text-sm font-bold text-slate-900 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-indigo-500 placeholder:font-normal placeholder:text-slate-400">
          </div>
          <div>
            <label class="block text-xs font-black text-slate-500 uppercase tracking-wider mb-2">Middle Name</label>
            <input name="middle_name" id="middleName" placeholder="e.g. Santos" class="w-full rounded-xl border-0 bg-slate-50 dark:bg-slate-800 py-2.5 px-4 text-sm font-bold text-slate-900 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-indigo-500 placeholder:font-normal placeholder:text-slate-400">
          </div>
          <div>
            <label class="block text-xs font-black text-slate-500 uppercase tracking-wider mb-2">Last Name <span class="text-rose-500">*</span></label>
            <input name="last_name" id="lastName" required placeholder="e.g. Dela Cruz" class="w-full rounded-xl border-0 bg-slate-50 dark:bg-slate-800 py-2.5 px-4 text-sm font-bold text-slate-900 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-indigo-500 placeholder:font-normal placeholder:text-slate-400">
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="md:col-span-2">
            <label class="block text-xs font-black text-slate-500 uppercase tracking-wider mb-2">Email <span class="text-rose-500">*</span></label>
            <input name="email" id="email" type="email" required placeholder="e.g. j.delacruz@city.gov.ph" class="w-full rounded-xl border-0 bg-slate-50 dark:bg-slate-800 py-2.5 px-4 text-sm font-bold text-slate-900 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-indigo-500 placeholder:font-normal placeholder:text-slate-400">
          </div>
          <div>
            <label class="block text-xs font-black text-slate-500 uppercase tracking-wider mb-2">Password</label>
            <input name="password" id="password" type="password" placeholder="(Auto-generate)" class="w-full rounded-xl border-0 bg-slate-50 dark:bg-slate-800 py-2.5 px-4 text-sm font-bold text-slate-900 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-indigo-500 placeholder:font-normal placeholder:text-slate-400">
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-xs font-black text-slate-500 uppercase tracking-wider mb-2">Suffix</label>
            <input name="suffix" id="suffix" placeholder="e.g. Jr." class="w-full rounded-xl border-0 bg-slate-50 dark:bg-slate-800 py-2.5 px-4 text-sm font-bold text-slate-900 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-indigo-500 placeholder:font-normal placeholder:text-slate-400">
          </div>
          <div>
            <label class="block text-xs font-black text-slate-500 uppercase tracking-wider mb-2">Employee No. <span class="text-rose-500">*</span></label>
            <input name="employee_no" id="employeeNo" required placeholder="e.g. TMO-2024-001" class="w-full rounded-xl border-0 bg-slate-50 dark:bg-slate-800 py-2.5 px-4 text-sm font-bold text-slate-900 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-indigo-500 placeholder:font-normal placeholder:text-slate-400">
          </div>
          <div class="md:col-span-2">
            <label class="block text-xs font-black text-slate-500 uppercase tracking-wider mb-2">Department <span class="text-rose-500">*</span></label>
            <div class="relative">
              <select id="departmentSelect" class="w-full rounded-xl border-0 bg-slate-50 dark:bg-slate-800 py-2.5 px-4 text-sm font-bold text-slate-900 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-indigo-500">
                <option value="">Select Department...</option>
                <!-- Options populated by JS -->
                <option value="other">Other...</option>
              </select>
              <input name="department" id="department" class="hidden mt-2 w-full rounded-xl border-0 bg-slate-50 dark:bg-slate-800 py-2.5 px-4 text-sm font-bold text-slate-900 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-indigo-500 placeholder:font-normal placeholder:text-slate-400" placeholder="Type department name...">
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="md:col-span-2">
            <label class="block text-xs font-black text-slate-500 uppercase tracking-wider mb-2">Position Title <span class="text-rose-500">*</span></label>
            <div class="relative">
              <select id="positionSelect" class="w-full rounded-xl border-0 bg-slate-50 dark:bg-slate-800 py-2.5 px-4 text-sm font-bold text-slate-900 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-indigo-500">
                <option value="">Select Position...</option>
                <!-- Options populated by JS -->
                <option value="other">Other...</option>
              </select>
              <input name="position_title" id="positionTitle" class="hidden mt-2 w-full rounded-xl border-0 bg-slate-50 dark:bg-slate-800 py-2.5 px-4 text-sm font-bold text-slate-900 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-indigo-500 placeholder:font-normal placeholder:text-slate-400" placeholder="Type position title...">
            </div>
          </div>
          <div>
            <label class="block text-xs font-black text-slate-500 uppercase tracking-wider mb-2">Status</label>
            <select name="status" id="status" class="w-full rounded-xl border-0 bg-slate-50 dark:bg-slate-800 py-2.5 px-4 text-sm font-bold text-slate-900 dark:text-white ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-indigo-500">
              <option value="Active">Active</option>
              <option value="Inactive">Inactive</option>
              <option value="Locked">Locked</option>
            </select>
          </div>
        </div>

        <div>
          <label class="block text-xs font-black text-slate-500 uppercase tracking-wider mb-2">Roles</label>
          <div id="rolesGrid" class="grid grid-cols-1 sm:grid-cols-2 gap-3"></div>
        </div>

        <div class="flex items-center justify-end gap-3">
          <button type="button" id="btnResetPassword" class="hidden rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-bold py-2.5 px-4 transition-all flex items-center gap-2">
            <i data-lucide="key-round" class="w-4 h-4"></i>
            Reset Password
          </button>
          <button type="submit" id="btnSaveUser" class="rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold py-2.5 px-5 transition-all flex items-center gap-2">
            <i data-lucide="save" class="w-4 h-4"></i>
            Save
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  if (window.lucide) window.lucide.createIcons();

  function getBasePrefix() {
    var p = window.location.pathname || '';
    var idx = p.indexOf('/admin/');
    if (idx === -1) return '';
    return p.slice(0, idx);
  }
  var basePrefix = getBasePrefix();

  var usersBody = document.getElementById('usersBody');
  var usersMeta = document.getElementById('usersMeta');
  var usersError = document.getElementById('usersError');
  var qEl = document.getElementById('q');
  var statusFilter = document.getElementById('statusFilter');
  var btnRefresh = document.getElementById('btnRefresh');

  var modalBackdrop = document.getElementById('modalBackdrop');
  var btnOpenCreate = document.getElementById('btnOpenCreate');
  var btnCloseModal = document.getElementById('btnCloseModal');
  var modalTitle = document.getElementById('modalTitle');
  var modalError = document.getElementById('modalError');
  var modalSuccess = document.getElementById('modalSuccess');
  var userForm = document.getElementById('userForm');
  var userIdEl = document.getElementById('userId');
  var firstNameEl = document.getElementById('firstName');
  var middleNameEl = document.getElementById('middleName');
  var lastNameEl = document.getElementById('lastName');
  var emailEl = document.getElementById('email');
  var passwordEl = document.getElementById('password');
  var suffixEl = document.getElementById('suffix');
  var employeeNoEl = document.getElementById('employeeNo');
  var departmentEl = document.getElementById('department');
  var positionTitleEl = document.getElementById('positionTitle');
  var statusEl = document.getElementById('status');
  var rolesGrid = document.getElementById('rolesGrid');
  var btnResetPassword = document.getElementById('btnResetPassword');
  var btnSaveUser = document.getElementById('btnSaveUser');

  var roles = [];
  var lastUsers = [];

  // Data for Dropdowns
  var departments = [
    'City ICT Office',
    'Traffic Management Office',
    'City Treasurer\'s Office',
    'City Engineering Office',
    'City Mayor\'s Office',
    'Public Order & Safety',
    'Administrative Division',
    'Human Resource Management Office'
  ];

  var positions = [
    'System Administrator',
    'Department Head / OIC',
    'Administrative Officer',
    'Traffic Enforcer I',
    'Traffic Enforcer II',
    'Traffic Enforcer III',
    'Ticket Evaluator',
    'Data Encoder',
    'Records Officer',
    'Revenue Collection Clerk',
    'Parking Attendant',
    'City Administrator'
  ];

  function populateSelect(el, items) {
    var first = el.firstElementChild;
    var last = el.lastElementChild;
    el.innerHTML = '';
    el.appendChild(first);
    items.forEach(function(item) {
      var opt = document.createElement('option');
      opt.value = item;
      opt.textContent = item;
      el.appendChild(opt);
    });
    el.appendChild(last);
  }

  if (departmentSelect && positionSelect) {
    populateSelect(departmentSelect, departments);
    populateSelect(positionSelect, positions);

    departmentSelect.addEventListener('change', function() { handleSelectChange(departmentSelect, departmentEl); });
    positionSelect.addEventListener('change', function() { handleSelectChange(positionSelect, positionTitleEl); });
  }

  function handleSelectChange(selectEl, inputEl) {
    if (selectEl.value === 'other') {
      inputEl.classList.remove('hidden');
      inputEl.focus();
      inputEl.value = '';
    } else {
      inputEl.classList.add('hidden');
      inputEl.value = selectEl.value;
    }
  }

  // Auto Capslock and Format for Employee No (Format: XXX-XXXX-XXX)
  if (employeeNoEl) {
    employeeNoEl.addEventListener('input', function(e) {
      // 1. Clean input: Remove everything except letters and numbers, then uppercase
      let val = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '');

      // 2. Limit length to 10 characters (3 prefix + 4 year + 3 sequence)
      if (val.length > 10) val = val.slice(0, 10);

      // 3. Apply Formatting (XXX-XXXX-XXX)
      let formatted = '';
      if (val.length > 0) {
        formatted = val.slice(0, 3); // First 3 chars
        
        if (val.length > 3) {
          formatted += '-' + val.slice(3, 7); // Next 4 chars
        }
        
        if (val.length > 7) {
          formatted += '-' + val.slice(7, 10); // Last 3 chars
        }
      }

      e.target.value = formatted;
    });
  }

  function show(el, on) {
    if (!el) return;
    el.classList.toggle('hidden', !on);
  }
  function setText(el, text) {
    if (el) el.textContent = text;
  }
  function clearModalAlerts() {
    show(modalError, false);
    show(modalSuccess, false);
    setText(modalError, '');
    setText(modalSuccess, '');
  }
  function openModal() {
    clearModalAlerts();
    modalBackdrop.classList.remove('hidden');
    modalBackdrop.classList.add('flex');
    document.body.classList.add('overflow-hidden');
    if (window.lucide) window.lucide.createIcons();
  }
  function closeModal() {
    modalBackdrop.classList.add('hidden');
    modalBackdrop.classList.remove('flex');
    document.body.classList.remove('overflow-hidden');
  }

  function apiUrl(path) {
    return basePrefix + '/admin/api/settings/' + path;
  }

  async function fetchJson(url, opts) {
    var res = await fetch(url, opts || { headers: { 'Accept': 'application/json' } });
    var data = await res.json().catch(function () { return null; });
    if (!data || data.ok !== true) {
      var msg = (data && (data.error || data.message)) ? (data.error || data.message) : ('Request failed (' + res.status + ')');
      throw new Error(msg);
    }
    return data;
  }

  function renderRolesCheckboxes(selectedRoleIds) {
    rolesGrid.innerHTML = '';
    var selected = {};
    (selectedRoleIds || []).forEach(function (id) { selected[String(id)] = true; });
    roles.forEach(function (r) {
      var wrap = document.createElement('label');
      wrap.className = 'flex items-start gap-3 p-4 rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 cursor-pointer hover:border-indigo-400 transition-all';
      var cb = document.createElement('input');
      cb.type = 'checkbox';
      cb.value = String(r.id);
      cb.className = 'mt-1 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500';
      if (selected[String(r.id)]) cb.checked = true;
      var info = document.createElement('div');
      info.className = 'min-w-0';
      info.innerHTML = '<div class="text-sm font-black text-slate-900 dark:text-white">' + escapeHtml(r.name) + '</div>' +
        '<div class="text-xs font-bold text-slate-500">' + escapeHtml(r.description || '') + '</div>';
      wrap.appendChild(cb);
      wrap.appendChild(info);
      rolesGrid.appendChild(wrap);
    });
  }

  function escapeHtml(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
  }

  function formatUserName(u) {
    var mid = u.middle_name ? (' ' + u.middle_name) : '';
    var suf = u.suffix ? (', ' + u.suffix) : '';
    return (u.first_name || '') + mid + ' ' + (u.last_name || '') + suf;
  }

  function badge(text, cls) {
    return '<span class="inline-flex items-center px-2 py-1 rounded-lg text-[11px] font-black ' + cls + '">' + escapeHtml(text) + '</span>';
  }

  function renderUsers(users) {
    usersBody.innerHTML = '';
    if (!users || !users.length) {
      usersBody.innerHTML = '<tr><td colspan="6" class="px-6 py-10 text-center text-sm font-bold text-slate-500">No users found.</td></tr>';
      setText(usersMeta, '0 accounts');
      return;
    }
    users.forEach(function (u) {
      var rolesText = (u.roles || []).map(function (r) { return r.name; }).join(', ');
      var statusBadge = '';
      if (u.status === 'Active') statusBadge = badge('Active', 'bg-emerald-100 text-emerald-700');
      else if (u.status === 'Locked') statusBadge = badge('Locked', 'bg-rose-100 text-rose-700');
      else statusBadge = badge('Inactive', 'bg-slate-200 text-slate-700');

      var tr = document.createElement('tr');
      tr.innerHTML =
        '<td class="px-4 py-4 align-top">' +
          '<div class="font-black text-slate-900 dark:text-white">' + escapeHtml(formatUserName(u)) + '</div>' +
          '<div class="text-xs font-bold text-slate-500">' + escapeHtml(u.email) + (u.employee_no ? (' • ' + escapeHtml(u.employee_no)) : '') + '</div>' +
          '<div class="text-xs font-bold text-slate-400">' + escapeHtml(u.position_title || '') + '</div>' +
        '</td>' +
        '<td class="px-4 py-4 align-top">' +
          '<div class="text-sm font-black text-slate-800 dark:text-slate-100">' + escapeHtml(u.department || '') + '</div>' +
        '</td>' +
        '<td class="px-4 py-4 align-top">' +
          '<div class="text-sm font-black text-slate-800 dark:text-slate-100">' + escapeHtml(rolesText || '—') + '</div>' +
        '</td>' +
        '<td class="px-4 py-4 align-top">' + statusBadge + '</td>' +
        '<td class="px-4 py-4 align-top text-sm font-bold text-slate-600 dark:text-slate-300">' + escapeHtml(u.last_login_at || '—') + '</td>' +
        '<td class="px-4 py-4 align-top text-right">' +
          '<button data-action="edit" data-id="' + u.id + '" class="inline-flex items-center gap-2 px-3 py-2 rounded-md bg-slate-100 hover:bg-blue-50 text-slate-700 hover:text-blue-600 border border-slate-200 text-xs font-bold transition-all"><i data-lucide="pencil" class="w-4 h-4"></i>Edit</button>' +
        '</td>';
      usersBody.appendChild(tr);
    });
    setText(usersMeta, users.length + ' accounts');
    if (window.lucide) window.lucide.createIcons();
  }

  async function loadRoles() {
    var data = await fetchJson(apiUrl('rbac_roles.php'), { headers: { 'Accept': 'application/json' } });
    roles = data.roles || [];
  }

  async function loadUsers() {
    show(usersError, false);
    setText(usersError, '');
    var q = qEl.value.trim();
    var st = statusFilter.value;
    var url = apiUrl('rbac_users.php') + '?q=' + encodeURIComponent(q) + '&status=' + encodeURIComponent(st);
    var data = await fetchJson(url, { headers: { 'Accept': 'application/json' } });
    lastUsers = data.users || [];
    renderUsers(lastUsers);
  }

  function setFormModeCreate() {
    modalTitle.textContent = 'Create Account';
    userIdEl.value = '';
    firstNameEl.value = '';
    middleNameEl.value = '';
    lastNameEl.value = '';
    emailEl.value = '';
    passwordEl.value = '';
    passwordEl.parentElement.classList.remove('hidden'); // Show password field
    suffixEl.value = '';
    employeeNoEl.value = '';
    departmentEl.value = '';
    positionTitleEl.value = '';
    statusEl.value = 'Active';
    emailEl.disabled = false;
    firstNameEl.disabled = false;
    middleNameEl.disabled = false;
    lastNameEl.disabled = false;
    suffixEl.disabled = false;
    renderRolesCheckboxes([]);
    show(btnResetPassword, false);
  }

  function setFormModeEdit(user) {
    modalTitle.textContent = 'Edit Account';
    userIdEl.value = String(user.id);
    firstNameEl.value = user.first_name || '';
    middleNameEl.value = user.middle_name || '';
    lastNameEl.value = user.last_name || '';
    emailEl.value = user.email || '';
    suffixEl.value = user.suffix || '';
    employeeNoEl.value = user.employee_no || '';
    departmentEl.value = user.department || '';
    positionTitleEl.value = user.position_title || '';
    statusEl.value = user.status || 'Active';
    emailEl.disabled = true;
    firstNameEl.disabled = true;
    middleNameEl.disabled = true;
    lastNameEl.disabled = true;
    suffixEl.disabled = true;
    renderRolesCheckboxes((user.roles || []).map(function (r) { return r.id; }));
    show(btnResetPassword, true);
  }

  function selectedRoleIds() {
    var out = [];
    rolesGrid.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
      if (cb.checked) out.push(cb.value);
    });
    return out;
  }

  async function createUser() {
    var fd = new FormData();
    fd.append('email', emailEl.value.trim());
    fd.append('password', passwordEl.value.trim()); // Add password
    fd.append('first_name', firstNameEl.value.trim());
    fd.append('middle_name', middleNameEl.value.trim());
    fd.append('last_name', lastNameEl.value.trim());
    fd.append('suffix', suffixEl.value.trim());
    fd.append('employee_no', employeeNoEl.value.trim());
    fd.append('department', departmentEl.value.trim());
    fd.append('position_title', positionTitleEl.value.trim());
    fd.append('status', statusEl.value);
    selectedRoleIds().forEach(function (rid) { fd.append('role_ids[]', rid); });

    var data = await fetchJson(apiUrl('rbac_user_create.php'), { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } });
    show(modalSuccess, true);
    setText(modalSuccess, 'Account created. Temporary password: ' + (data.temporary_password || ''));
    await loadUsers();
  }

  async function updateUser() {
    var id = userIdEl.value;
    var fd1 = new FormData();
    fd1.append('id', id);
    fd1.append('employee_no', employeeNoEl.value.trim());
    fd1.append('department', departmentEl.value.trim());
    fd1.append('position_title', positionTitleEl.value.trim());
    fd1.append('status', statusEl.value);
    await fetchJson(apiUrl('rbac_user_update.php'), { method: 'POST', body: fd1, headers: { 'Accept': 'application/json' } });

    var fd2 = new FormData();
    fd2.append('id', id);
    selectedRoleIds().forEach(function (rid) { fd2.append('role_ids[]', rid); });
    await fetchJson(apiUrl('rbac_user_set_roles.php'), { method: 'POST', body: fd2, headers: { 'Accept': 'application/json' } });

    show(modalSuccess, true);
    setText(modalSuccess, 'Account updated.');
    await loadUsers();
  }

  async function resetPassword() {
    var id = userIdEl.value;
    var fd = new FormData();
    fd.append('id', id);
    var data = await fetchJson(apiUrl('rbac_user_reset_password.php'), { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } });
    show(modalSuccess, true);
    setText(modalSuccess, 'Temporary password: ' + (data.temporary_password || ''));
  }

  btnOpenCreate.addEventListener('click', function () {
    setFormModeCreate();
    openModal();
  });
  btnCloseModal.addEventListener('click', closeModal);
  modalBackdrop.addEventListener('click', function (e) { if (e.target === modalBackdrop) closeModal(); });

  btnRefresh.addEventListener('click', function () { loadUsers().catch(function (e) { show(usersError, true); setText(usersError, e.message || 'Failed to load users'); }); });
  qEl.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); btnRefresh.click(); } });
  statusFilter.addEventListener('change', function () { btnRefresh.click(); });

  usersBody.addEventListener('click', function (e) {
    var btn = e.target.closest('button[data-action="edit"]');
    if (!btn) return;
    var id = Number(btn.getAttribute('data-id') || 0);
    var user = (lastUsers || []).find(function (u) { return u.id === id; });
    if (!user) return;
    clearModalAlerts();
    setFormModeEdit(user);
    openModal();
  });

  btnResetPassword.addEventListener('click', function () {
    clearModalAlerts();
    resetPassword().catch(function (e) { show(modalError, true); setText(modalError, e.message || 'Failed to reset password'); });
  });

  userForm.addEventListener('submit', function (e) {
    e.preventDefault();
    clearModalAlerts();

    // Validate roles
    if (selectedRoleIds().length === 0) {
      show(modalError, true);
      setText(modalError, 'Please select at least one role for this account.');
      return;
    }

    // Validate manual inputs if "Other" is selected but empty
    if (departmentSelect && departmentSelect.value === 'other' && !departmentEl.value.trim()) {
        show(modalError, true);
        setText(modalError, 'Please specify the department.');
        return;
    }
    if (positionSelect && positionSelect.value === 'other' && !positionTitleEl.value.trim()) {
        show(modalError, true);
        setText(modalError, 'Please specify the position title.');
        return;
    }

    btnSaveUser.disabled = true;
    var orig = btnSaveUser.innerHTML;
    btnSaveUser.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Saving...';
    if (window.lucide) window.lucide.createIcons();
    var p = (userIdEl.value ? updateUser() : createUser());
    p.then(function () {
      btnSaveUser.disabled = false;
      btnSaveUser.innerHTML = orig;
      if (window.lucide) window.lucide.createIcons();
    }).catch(function (err) {
      btnSaveUser.disabled = false;
      btnSaveUser.innerHTML = orig;
      if (window.lucide) window.lucide.createIcons();
      show(modalError, true);
      setText(modalError, err.message || 'Save failed');
    });
  });

  Promise.resolve()
    .then(loadRoles)
    .then(function () { renderRolesCheckboxes([]); })
    .then(loadUsers)
    .catch(function (e) {
      show(usersError, true);
      setText(usersError, e.message || 'Failed to load data');
    });
});
</script>
