<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['SuperAdmin']);
?>

<div class="mx-auto max-w-7xl px-4 py-8 space-y-8">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-black text-slate-800 dark:text-white flex items-center gap-3">
                <div class="p-3 bg-indigo-500/10 rounded-2xl">
                    <i data-lucide="users" class="w-8 h-8 text-indigo-500"></i>
                </div>
                Accounts & Roles
            </h1>
            <p class="mt-2 text-slate-500 dark:text-slate-400 font-medium ml-14">Manage system users and their role assignments.</p>
        </div>
        <button onclick="openUserModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-6 rounded-xl shadow-sm transition-all flex items-center gap-2">
            <i data-lucide="plus" class="w-5 h-5"></i>
            New Account
        </button>
    </div>

    <!-- Filters & Search -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 p-4 flex flex-col sm:flex-row gap-4">
        <div class="relative flex-1">
            <i data-lucide="search" class="absolute left-3 top-3 w-5 h-5 text-slate-400"></i>
            <input type="text" id="user-search" placeholder="Search by name, email, or employee ID..." 
                class="block w-full pl-10 rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 py-2.5 text-sm font-medium focus:ring-2 focus:ring-indigo-500">
        </div>
        <select id="user-status-filter" class="rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 py-2.5 pl-4 pr-10 text-sm font-medium focus:ring-2 focus:ring-indigo-500">
            <option value="">All Status</option>
            <option value="Active">Active</option>
            <option value="Inactive">Inactive</option>
            <option value="Locked">Locked</option>
        </select>
    </div>

    <!-- Users Table -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700 text-xs uppercase tracking-wider text-slate-500 font-bold">
                        <th class="px-6 py-4">User</th>
                        <th class="px-6 py-4">Role(s)</th>
                        <th class="px-6 py-4">Department / Position</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="users-table-body" class="divide-y divide-slate-100 dark:divide-slate-700">
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-slate-400 font-medium">
                            <i data-lucide="loader-2" class="w-8 h-8 animate-spin mx-auto mb-2"></i>
                            Loading accounts...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create/Edit User Modal -->
<div id="user-modal" class="fixed inset-0 z-[100] hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="closeUserModal()"></div>
    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-slate-800 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-2xl border border-slate-200 dark:border-slate-700">
                
                <!-- Modal Header -->
                <div class="bg-slate-50/50 dark:bg-slate-800/50 px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                    <h3 class="text-lg font-black text-slate-800 dark:text-white" id="modal-title">New Account</h3>
                    <button type="button" onclick="closeUserModal()" class="text-slate-400 hover:text-slate-500 transition-colors">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <!-- Modal Body -->
                <form id="user-form" class="px-6 py-6 space-y-6">
                    <input type="hidden" name="id" id="user-id">
                    
                    <!-- Basic Info -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div class="col-span-1 sm:col-span-2">
                            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 border-b border-slate-100 dark:border-slate-700 pb-2">Personal Information</h4>
                        </div>
                        
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">First Name <span class="text-rose-500">*</span></label>
                            <input type="text" name="first_name" id="user-first" required
                                class="block w-full rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 py-2.5 px-4 text-sm font-bold focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Last Name <span class="text-rose-500">*</span></label>
                            <input type="text" name="last_name" id="user-last" required
                                class="block w-full rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 py-2.5 px-4 text-sm font-bold focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Middle Name</label>
                            <input type="text" name="middle_name" id="user-middle"
                                class="block w-full rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 py-2.5 px-4 text-sm font-bold focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Suffix</label>
                            <input type="text" name="suffix" id="user-suffix"
                                class="block w-full rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 py-2.5 px-4 text-sm font-bold focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>

                    <!-- Work Info -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div class="col-span-1 sm:col-span-2">
                            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 border-b border-slate-100 dark:border-slate-700 pb-2">Employment Details</h4>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Email Address <span class="text-rose-500">*</span></label>
                            <input type="email" name="email" id="user-email" required
                                class="block w-full rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 py-2.5 px-4 text-sm font-bold focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Employee No.</label>
                            <input type="text" name="employee_no" id="user-emp-no"
                                class="block w-full rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 py-2.5 px-4 text-sm font-bold focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Department</label>
                            <input type="text" name="department" id="user-dept"
                                class="block w-full rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 py-2.5 px-4 text-sm font-bold focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Position Title</label>
                            <input type="text" name="position_title" id="user-pos"
                                class="block w-full rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 py-2.5 px-4 text-sm font-bold focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>

                    <!-- Role Assignment -->
                    <div>
                        <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 border-b border-slate-100 dark:border-slate-700 pb-2">Role Assignment</h4>
                        <div class="space-y-3" id="roles-container">
                            <p class="text-sm text-slate-400 italic">Loading roles...</p>
                        </div>
                        <p class="mt-3 text-xs text-slate-400">
                            <i data-lucide="info" class="w-3 h-3 inline mr-1"></i>
                            Permissions are automatically assigned based on the selected role(s).
                        </p>
                    </div>

                    <!-- Status -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Account Status</label>
                            <select name="status" id="user-status" class="block w-full rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 py-2.5 px-4 text-sm font-bold focus:ring-2 focus:ring-indigo-500">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                                <option value="Locked">Locked</option>
                            </select>
                        </div>
                         <div id="password-container">
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Password</label>
                             <input type="password" name="password" id="user-password" placeholder="Leave blank to auto-generate"
                                class="block w-full rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 py-2.5 px-4 text-sm font-bold focus:ring-2 focus:ring-indigo-500">
                             <p class="mt-1 text-xs text-slate-400">Only for new accounts.</p>
                        </div>
                    </div>
                </form>

                <!-- Modal Footer -->
                <div class="bg-slate-50/50 dark:bg-slate-800/50 px-6 py-4 border-t border-slate-200 dark:border-slate-700 flex justify-end gap-3">
                    <button type="button" onclick="closeUserModal()" class="px-5 py-2.5 text-sm font-bold text-slate-600 hover:text-slate-800 hover:bg-slate-100 rounded-xl transition-all">Cancel</button>
                    <button type="button" onclick="saveUser()" id="btn-save-user" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-6 rounded-xl shadow-sm transition-all flex items-center gap-2">
                        <i data-lucide="save" class="w-4 h-4"></i>
                        Save Account
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let allRoles = [];
let allUsers = [];

document.addEventListener('DOMContentLoaded', () => {
    if(window.lucide) window.lucide.createIcons();
    fetchRoles();
    fetchUsers();

    // Debounced search
    let searchTimeout;
    document.getElementById('user-search').addEventListener('input', (e) => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => fetchUsers(e.target.value, document.getElementById('user-status-filter').value), 300);
    });

    document.getElementById('user-status-filter').addEventListener('change', (e) => {
        fetchUsers(document.getElementById('user-search').value, e.target.value);
    });
});

async function fetchRoles() {
    try {
        const res = await fetch((window.TMM_ROOT_URL || '') + '/admin/api/settings/rbac_roles.php?t=' + Date.now());
        const data = await res.json();
        if (data.ok) {
            allRoles = data.roles;
            renderRolesInput();
        }
    } catch (e) {
        console.error('Failed to fetch roles', e);
    }
}

function renderRolesInput() {
    const container = document.getElementById('roles-container');
    container.innerHTML = '';
    
    allRoles.forEach(role => {
        // Use a wrapper label to ensure the whole area is clickable and toggles the checkbox
        const label = document.createElement('label');
        label.className = 'relative flex items-start cursor-pointer group p-2 hover:bg-slate-50 dark:hover:bg-slate-700/50 rounded-lg transition-colors';
        label.innerHTML = `
            <div class="flex h-6 items-center">
                <input id="role-${role.id}" name="role_ids[]" value="${role.id}" type="checkbox" 
                    class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-600 bg-slate-100 cursor-pointer">
            </div>
            <div class="ml-3 text-sm leading-6 select-none">
                <span class="font-bold text-slate-900 dark:text-white block">${role.name}</span>
                <span class="text-xs text-slate-500 block">${role.description || ''}</span>
            </div>
        `;
        container.appendChild(label);
    });
}

async function fetchUsers(q = '', status = '') {
    const tbody = document.getElementById('users-table-body');
    tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-12 text-center text-slate-400 font-medium"><i data-lucide="loader-2" class="w-8 h-8 animate-spin mx-auto mb-2"></i>Loading accounts...</td></tr>`;
    if(window.lucide) window.lucide.createIcons();

    try {
        const url = new URL((window.TMM_ROOT_URL || '') + '/admin/api/settings/rbac_users.php', window.location.origin);
        if (q) url.searchParams.append('q', q);
        if (status) url.searchParams.append('status', status);
        url.searchParams.append('t', Date.now()); // Prevent caching

        const res = await fetch(url);
        const data = await res.json();

        if (data.ok) {
            allUsers = data.users;
            renderUsers(data.users);
        } else {
            tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-8 text-center text-rose-500 font-bold">Error loading users</td></tr>`;
        }
    } catch (e) {
        console.error(e);
        tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-8 text-center text-rose-500 font-bold">Network error</td></tr>`;
    }
}

function renderUsers(users) {
    const tbody = document.getElementById('users-table-body');
    if (users.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-12 text-center text-slate-400 font-medium">No accounts found.</td></tr>`;
        return;
    }

    tbody.innerHTML = users.map(user => {
        const fullName = `${user.first_name} ${user.last_name}`;
        const rolesHtml = user.roles.map(r => `
            <span class="inline-flex items-center rounded-md bg-indigo-50 dark:bg-indigo-900/30 px-2 py-1 text-xs font-medium text-indigo-700 dark:text-indigo-300 ring-1 ring-inset ring-indigo-700/10">
                ${r.name}
            </span>
        `).join(' ');

        let statusColor = 'bg-slate-100 text-slate-600';
        if(user.status === 'Active') statusColor = 'bg-emerald-100 text-emerald-700';
        if(user.status === 'Locked') statusColor = 'bg-rose-100 text-rose-700';

        return `
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                <td class="px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 flex-shrink-0 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-slate-500 font-bold">
                            ${user.first_name.charAt(0)}${user.last_name.charAt(0)}
                        </div>
                        <div>
                            <div class="font-bold text-slate-900 dark:text-white">${fullName}</div>
                            <div class="text-xs text-slate-500">${user.email}</div>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4">
                    <div class="flex flex-wrap gap-1">${rolesHtml || '<span class="text-slate-400 text-xs italic">No roles</span>'}</div>
                </td>
                <td class="px-6 py-4">
                    <div class="text-sm font-medium text-slate-900 dark:text-white">${user.position_title || '-'}</div>
                    <div class="text-xs text-slate-500">${user.department || '-'}</div>
                </td>
                <td class="px-6 py-4">
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${statusColor}">
                        ${user.status}
                    </span>
                </td>
                <td class="px-6 py-4 text-right">
                    <button onclick="editUser(${user.id})" class="text-indigo-600 hover:text-indigo-900 font-medium text-sm">Edit</button>
                </td>
            </tr>
        `;
    }).join('');
    if(window.lucide) window.lucide.createIcons();
}

function openUserModal(isEdit = false) {
    const modal = document.getElementById('user-modal');
    modal.classList.remove('hidden');
    document.getElementById('modal-title').textContent = isEdit ? 'Edit Account' : 'New Account';
    
    const pwdContainer = document.getElementById('password-container');
    if (isEdit) {
        pwdContainer.style.display = 'none';
    } else {
        pwdContainer.style.display = 'block';
    }
}

function closeUserModal() {
    document.getElementById('user-modal').classList.add('hidden');
    document.getElementById('user-form').reset();
    document.getElementById('user-id').value = '';
    // Uncheck all roles
    document.querySelectorAll('input[name="role_ids[]"]').forEach(cb => cb.checked = false);
}

function editUser(id) {
    // Force refresh of roles to ensure we have latest IDs
    fetchRoles().then(() => {
        const user = allUsers.find(u => u.id === id);
        if (!user) return;

        document.getElementById('user-id').value = user.id;
        document.getElementById('user-first').value = user.first_name;
        document.getElementById('user-last').value = user.last_name;
        document.getElementById('user-middle').value = user.middle_name;
        document.getElementById('user-suffix').value = user.suffix;
        document.getElementById('user-email').value = user.email;
        document.getElementById('user-emp-no').value = user.employee_no;
        document.getElementById('user-dept').value = user.department;
        document.getElementById('user-pos').value = user.position_title;
        document.getElementById('user-status').value = user.status;

        // Check roles
        // We must match against the numeric ID
        const userRoleIds = user.roles.map(r => parseInt(r.id));
        document.querySelectorAll('input[name="role_ids[]"]').forEach(cb => {
            cb.checked = userRoleIds.includes(parseInt(cb.value));
        });

        openUserModal(true);
    });
}

async function saveUser() {
    const form = document.getElementById('user-form');
    const btn = document.getElementById('btn-save-user');
    const id = document.getElementById('user-id').value;
    const isEdit = !!id;

    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const originalBtnContent = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Saving...';
    if(window.lucide) window.lucide.createIcons();

    const formData = new FormData(form);
    
    // Explicitly handle roles to be safe
    const checkedRoles = Array.from(document.querySelectorAll('input[name="role_ids[]"]:checked')).map(cb => cb.value);
    
    // Debug
    // console.log('Saving roles:', checkedRoles);

    try {
        let url = (window.TMM_ROOT_URL || '') + '/admin/api/settings/rbac_user_create.php';
        if (isEdit) {
            url = (window.TMM_ROOT_URL || '') + '/admin/api/settings/rbac_user_update.php';
        }

        const res = await fetch(url, {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.ok) {
            if (isEdit) {
                const roleFormData = new FormData();
                roleFormData.append('id', id);
                checkedRoles.forEach(rid => roleFormData.append('role_ids[]', rid));
                
                // If no roles checked, we should probably send something or the API handles it (errors out)
                if (checkedRoles.length === 0) {
                     // The API expects at least one role usually, or it throws 'no_roles'
                     // Let's allow it to error if that's the logic, or handle it gracefully
                }

                const roleRes = await fetch((window.TMM_ROOT_URL || '') + '/admin/api/settings/rbac_user_set_roles.php', {
                    method: 'POST',
                    body: roleFormData
                });
                const roleData = await roleRes.json();
                if (!roleData.ok) throw new Error('User updated but failed to update roles: ' + (roleData.error || 'Unknown error'));
            }

            closeUserModal();
            fetchUsers();
        } else {
            alert('Error: ' + (data.error || 'Failed to save'));
        }
    } catch (e) {
        console.error(e);
        alert('An error occurred while saving: ' + e.message);
    } finally {
        btn.innerHTML = originalBtnContent;
        btn.disabled = false;
        if(window.lucide) window.lucide.createIcons();
    }
}
</script>
