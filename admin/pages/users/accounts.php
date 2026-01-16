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

  <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
    <div class="inline-flex rounded-2xl bg-slate-100 dark:bg-slate-900/30 p-1 gap-1">
      <button id="tabBtnStaff" type="button" class="px-4 py-2 rounded-xl text-sm font-black bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-sm">Staff Accounts</button>
      <button id="tabBtnCommuters" type="button" class="px-4 py-2 rounded-xl text-sm font-black text-slate-600 dark:text-slate-300 hover:bg-white/60 dark:hover:bg-slate-800/60">Citizen Accounts</button>
      <button id="tabBtnOperators" type="button" class="px-4 py-2 rounded-xl text-sm font-black text-slate-600 dark:text-slate-300 hover:bg-white/60 dark:hover:bg-slate-800/60">Operator Accounts</button>
    </div>
  </div>

  <div id="tabStaff">
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

  <div id="tabCommuters" class="hidden bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <div class="px-8 py-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
      <div class="flex items-center gap-3">
        <div class="p-2 bg-emerald-500/10 rounded-xl">
          <i data-lucide="users" class="w-5 h-5 text-emerald-600"></i>
        </div>
        <div>
          <h2 class="text-lg font-black text-slate-800 dark:text-white">Citizen Accounts</h2>
          <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Commuters</p>
        </div>
      </div>
      <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
        <input id="commuterQ" type="text" placeholder="Search name, email, mobile, barangay..." class="w-full sm:w-96 rounded-md border-0 bg-white dark:bg-slate-900/50 py-2.5 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-emerald-500 transition-all">
        <select id="commuterStatusFilter" class="w-full sm:w-44 rounded-md border-0 bg-white dark:bg-slate-900/50 py-2.5 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-emerald-500 transition-all">
          <option value="">All Status</option>
          <option value="Active">Active</option>
          <option value="Inactive">Inactive</option>
          <option value="Locked">Locked</option>
        </select>
        <button id="commuterRefresh" class="rounded-md bg-slate-900 hover:bg-slate-800 text-white font-bold py-2.5 px-4 transition-all flex items-center justify-center gap-2">
          <i data-lucide="refresh-cw" class="w-4 h-4"></i>
          Refresh
        </button>
      </div>
    </div>
    <div class="p-6">
      <div id="commuterError" class="hidden mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-700"></div>
      <div id="commuterSuccess" class="hidden mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700"></div>
      <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-700">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
          <thead class="bg-slate-50 dark:bg-slate-900/30">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">User</th>
              <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Mobile</th>
              <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Barangay</th>
              <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Status</th>
              <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Last Login</th>
              <th class="px-4 py-3 text-right text-xs font-black text-slate-500 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody id="commuterBody" class="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-800"></tbody>
        </table>
      </div>
      <div id="commuterMeta" class="mt-3 text-xs font-bold text-slate-500"></div>
    </div>
  </div>

  <div id="tabOperators" class="hidden bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <div class="px-8 py-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
      <div class="flex items-center gap-3">
        <div class="p-2 bg-orange-500/10 rounded-xl">
          <i data-lucide="id-card" class="w-5 h-5 text-orange-600"></i>
        </div>
        <div>
          <h2 class="text-lg font-black text-slate-800 dark:text-white">Operator Accounts</h2>
          <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Operators</p>
        </div>
      </div>
      <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
        <input id="operatorQ" type="text" placeholder="Search email, name, plate..." class="w-full sm:w-96 rounded-md border-0 bg-white dark:bg-slate-900/50 py-2.5 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-orange-500 transition-all">
        <select id="operatorStatusFilter" class="w-full sm:w-44 rounded-md border-0 bg-white dark:bg-slate-900/50 py-2.5 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-orange-500 transition-all">
          <option value="">All Status</option>
          <option value="Active">Active</option>
          <option value="Inactive">Inactive</option>
          <option value="Locked">Locked</option>
        </select>
        <button id="operatorRefresh" class="rounded-md bg-slate-900 hover:bg-slate-800 text-white font-bold py-2.5 px-4 transition-all flex items-center justify-center gap-2">
          <i data-lucide="refresh-cw" class="w-4 h-4"></i>
          Refresh
        </button>
      </div>
    </div>
    <div class="p-6">
      <div id="operatorError" class="hidden mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-700"></div>
      <div id="operatorSuccess" class="hidden mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700"></div>
      <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-700">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
          <thead class="bg-slate-50 dark:bg-slate-900/30">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Operator</th>
              <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Association</th>
              <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Plates</th>
              <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Status</th>
              <th class="px-4 py-3 text-right text-xs font-black text-slate-500 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody id="operatorBody" class="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-800"></tbody>
        </table>
      </div>
      <div id="operatorMeta" class="mt-3 text-xs font-bold text-slate-500"></div>
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

<div id="activityBackdrop" class="fixed inset-0 bg-black/40 hidden items-start justify-center pt-14 px-4 z-50 overflow-y-auto">
  <div class="w-full max-w-3xl bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div class="p-2 bg-emerald-500/10 rounded-xl">
          <i data-lucide="activity" class="w-5 h-5 text-emerald-600"></i>
        </div>
        <div>
          <div id="activityTitle" class="text-base font-black text-slate-900 dark:text-white">Login Activity</div>
          <div id="activitySubtitle" class="text-xs font-bold text-slate-500">Recent sign-in attempts</div>
        </div>
      </div>
      <button id="btnCloseActivity" class="p-2 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>
    <div class="p-6">
      <div id="activityError" class="hidden mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-700"></div>
      <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-700">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
          <thead class="bg-slate-50 dark:bg-slate-900/30">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Result</th>
              <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">IP</th>
              <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">When</th>
            </tr>
          </thead>
          <tbody id="activityBody" class="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-800"></tbody>
        </table>
      </div>
      <div id="activityMeta" class="mt-3 text-xs font-bold text-slate-500"></div>
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

  var tabBtnStaff = document.getElementById('tabBtnStaff');
  var tabBtnCommuters = document.getElementById('tabBtnCommuters');
  var tabBtnOperators = document.getElementById('tabBtnOperators');
  var tabStaff = document.getElementById('tabStaff');
  var tabCommuters = document.getElementById('tabCommuters');
  var tabOperators = document.getElementById('tabOperators');

  function setTab(name) {
    var isStaff = name === 'staff';
    var isCommuters = name === 'commuters';
    var isOperators = name === 'operators';

    show(tabStaff, isStaff);
    show(tabCommuters, isCommuters);
    show(tabOperators, isOperators);

    if (btnOpenCreate) show(btnOpenCreate, isStaff);

    if (tabBtnStaff) tabBtnStaff.className = 'px-4 py-2 rounded-xl text-sm font-black ' + (isStaff ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:bg-white/60 dark:hover:bg-slate-800/60');
    if (tabBtnCommuters) tabBtnCommuters.className = 'px-4 py-2 rounded-xl text-sm font-black ' + (isCommuters ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:bg-white/60 dark:hover:bg-slate-800/60');
    if (tabBtnOperators) tabBtnOperators.className = 'px-4 py-2 rounded-xl text-sm font-black ' + (isOperators ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:bg-white/60 dark:hover:bg-slate-800/60');

    if (window.lucide) window.lucide.createIcons();
    if (isCommuters) loadCommuters().catch(function () {});
    if (isOperators) loadOperators().catch(function () {});
  }

  if (tabBtnStaff) tabBtnStaff.addEventListener('click', function () { setTab('staff'); });
  if (tabBtnCommuters) tabBtnCommuters.addEventListener('click', function () { setTab('commuters'); });
  if (tabBtnOperators) tabBtnOperators.addEventListener('click', function () { setTab('operators'); });

  function apiUsersUrl(path) {
    return basePrefix + '/admin/api/users/' + path;
  }

  var commuterQ = document.getElementById('commuterQ');
  var commuterStatusFilter = document.getElementById('commuterStatusFilter');
  var commuterRefresh = document.getElementById('commuterRefresh');
  var commuterBody = document.getElementById('commuterBody');
  var commuterMeta = document.getElementById('commuterMeta');
  var commuterError = document.getElementById('commuterError');
  var commuterSuccess = document.getElementById('commuterSuccess');

  var operatorQ = document.getElementById('operatorQ');
  var operatorStatusFilter = document.getElementById('operatorStatusFilter');
  var operatorRefresh = document.getElementById('operatorRefresh');
  var operatorBody = document.getElementById('operatorBody');
  var operatorMeta = document.getElementById('operatorMeta');
  var operatorError = document.getElementById('operatorError');
  var operatorSuccess = document.getElementById('operatorSuccess');

  var activityBackdrop = document.getElementById('activityBackdrop');
  var btnCloseActivity = document.getElementById('btnCloseActivity');
  var activityTitle = document.getElementById('activityTitle');
  var activitySubtitle = document.getElementById('activitySubtitle');
  var activityError = document.getElementById('activityError');
  var activityBody = document.getElementById('activityBody');
  var activityMeta = document.getElementById('activityMeta');

  function openActivityModal(title, subtitle) {
    if (!activityBackdrop) return;
    show(activityError, false);
    setText(activityError, '');
    setText(activityTitle, title || 'Login Activity');
    setText(activitySubtitle, subtitle || 'Recent sign-in attempts');
    activityBackdrop.classList.remove('hidden');
    activityBackdrop.classList.add('flex');
    document.body.classList.add('overflow-hidden');
    if (window.lucide) window.lucide.createIcons();
  }
  function closeActivityModal() {
    if (!activityBackdrop) return;
    activityBackdrop.classList.add('hidden');
    activityBackdrop.classList.remove('flex');
    document.body.classList.remove('overflow-hidden');
  }
  if (btnCloseActivity) btnCloseActivity.addEventListener('click', closeActivityModal);
  if (activityBackdrop) activityBackdrop.addEventListener('click', function (e) { if (e.target === activityBackdrop) closeActivityModal(); });

  function statusBadgeHtml(status) {
    var s = String(status || '');
    if (s === 'Active') return '<span class="px-3 py-1 rounded-full text-xs font-black bg-emerald-100 text-emerald-700">Active</span>';
    if (s === 'Locked') return '<span class="px-3 py-1 rounded-full text-xs font-black bg-rose-100 text-rose-700">Locked</span>';
    return '<span class="px-3 py-1 rounded-full text-xs font-black bg-amber-100 text-amber-700">Inactive</span>';
  }

  async function commutersGet() {
    var params = new URLSearchParams();
    if (commuterQ && commuterQ.value.trim()) params.set('q', commuterQ.value.trim());
    if (commuterStatusFilter && commuterStatusFilter.value) params.set('status', commuterStatusFilter.value);
    var res = await fetch(apiUsersUrl('commuters.php') + '?' + params.toString(), { headers: { 'Accept': 'application/json' } });
    return await res.json().catch(function () { return null; });
  }
  async function commutersPost(payload) {
    var res = await fetch(apiUsersUrl('commuters.php'), { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify(payload || {}) });
    return await res.json().catch(function () { return null; });
  }
  async function loadCommuters() {
    show(commuterError, false);
    setText(commuterError, '');
    show(commuterSuccess, false);
    setText(commuterSuccess, '');
    if (commuterBody) commuterBody.innerHTML = '<tr><td colspan="6" class="px-6 py-10 text-center text-sm font-bold text-slate-500">Loading...</td></tr>';
    var data = await commutersGet();
    if (!data || data.ok !== true) {
      show(commuterError, true);
      setText(commuterError, (data && (data.error || data.message)) ? (data.error || data.message) : 'Failed to load commuters.');
      if (commuterBody) commuterBody.innerHTML = '<tr><td colspan="6" class="px-6 py-10 text-center text-sm font-bold text-slate-500">Error.</td></tr>';
      return;
    }
    var users = data.users || [];
    if (!users.length) {
      if (commuterBody) commuterBody.innerHTML = '<tr><td colspan="6" class="px-6 py-10 text-center text-sm font-bold text-slate-500">No commuters found.</td></tr>';
      setText(commuterMeta, '0 results');
      return;
    }
    commuterBody.innerHTML = users.map(function (u) {
      var actions = '' +
        '<div class="flex items-center justify-end gap-2">' +
          '<select data-action="status" data-id="' + u.id + '" class="rounded-lg bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 px-2 py-1 text-xs font-black">' +
            '<option value="Active"' + (u.status === 'Active' ? ' selected' : '') + '>Active</option>' +
            '<option value="Inactive"' + (u.status === 'Inactive' ? ' selected' : '') + '>Inactive</option>' +
            '<option value="Locked"' + (u.status === 'Locked' ? ' selected' : '') + '>Locked</option>' +
          '</select>' +
          '<button data-action="activity" data-id="' + u.id + '" data-email="' + escapeHtml(u.email) + '" class="rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-1 text-xs font-black">Activity</button>' +
          '<button data-action="reset" data-id="' + u.id + '" class="rounded-lg bg-amber-600 hover:bg-amber-500 text-white px-3 py-1 text-xs font-black">Reset</button>' +
          '<button data-action="delete" data-id="' + u.id + '" class="rounded-lg bg-rose-600 hover:bg-rose-500 text-white px-3 py-1 text-xs font-black">Delete</button>' +
        '</div>';
      return '' +
        '<tr>' +
          '<td class="px-4 py-3">' +
            '<div class="font-black text-slate-900 dark:text-white">' + escapeHtml(u.name) + '</div>' +
            '<div class="text-xs font-bold text-slate-500">' + escapeHtml(u.email) + '</div>' +
          '</td>' +
          '<td class="px-4 py-3 text-sm font-bold text-slate-700 dark:text-slate-200">' + escapeHtml(u.mobile || '—') + '</td>' +
          '<td class="px-4 py-3 text-sm font-bold text-slate-700 dark:text-slate-200">' + escapeHtml(u.barangay || '—') + '</td>' +
          '<td class="px-4 py-3">' + statusBadgeHtml(u.status) + '</td>' +
          '<td class="px-4 py-3 text-xs font-bold text-slate-500">' + escapeHtml(u.last_login_at || '—') + '</td>' +
          '<td class="px-4 py-3">' + actions + '</td>' +
        '</tr>';
    }).join('');
    setText(commuterMeta, users.length + ' result(s)');
    if (window.lucide) window.lucide.createIcons();
  }

  async function operatorsGet() {
    var params = new URLSearchParams();
    if (operatorQ && operatorQ.value.trim()) params.set('q', operatorQ.value.trim());
    if (operatorStatusFilter && operatorStatusFilter.value) params.set('status', operatorStatusFilter.value);
    var res = await fetch(apiUsersUrl('operators.php') + '?' + params.toString(), { headers: { 'Accept': 'application/json' } });
    return await res.json().catch(function () { return null; });
  }
  async function operatorsPost(payload) {
    var res = await fetch(apiUsersUrl('operators.php'), { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify(payload || {}) });
    return await res.json().catch(function () { return null; });
  }
  async function loadOperators() {
    show(operatorError, false);
    setText(operatorError, '');
    show(operatorSuccess, false);
    setText(operatorSuccess, '');
    if (operatorBody) operatorBody.innerHTML = '<tr><td colspan="5" class="px-6 py-10 text-center text-sm font-bold text-slate-500">Loading...</td></tr>';
    var data = await operatorsGet();
    if (!data || data.ok !== true) {
      show(operatorError, true);
      setText(operatorError, (data && (data.error || data.message)) ? (data.error || data.message) : 'Failed to load operators.');
      if (operatorBody) operatorBody.innerHTML = '<tr><td colspan="5" class="px-6 py-10 text-center text-sm font-bold text-slate-500">Error.</td></tr>';
      return;
    }
    var users = data.users || [];
    if (!users.length) {
      if (operatorBody) operatorBody.innerHTML = '<tr><td colspan="5" class="px-6 py-10 text-center text-sm font-bold text-slate-500">No operators found.</td></tr>';
      setText(operatorMeta, '0 results');
      return;
    }
    operatorBody.innerHTML = users.map(function (u) {
      var actions = '' +
        '<div class="flex items-center justify-end gap-2">' +
          '<select data-action="status" data-id="' + u.id + '" class="rounded-lg bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 px-2 py-1 text-xs font-black">' +
            '<option value="Active"' + (u.status === 'Active' ? ' selected' : '') + '>Active</option>' +
            '<option value="Inactive"' + (u.status === 'Inactive' ? ' selected' : '') + '>Inactive</option>' +
            '<option value="Locked"' + (u.status === 'Locked' ? ' selected' : '') + '>Locked</option>' +
          '</select>' +
          '<button data-action="reset" data-id="' + u.id + '" class="rounded-lg bg-amber-600 hover:bg-amber-500 text-white px-3 py-1 text-xs font-black">Reset</button>' +
          '<button data-action="delete" data-id="' + u.id + '" class="rounded-lg bg-rose-600 hover:bg-rose-500 text-white px-3 py-1 text-xs font-black">Delete</button>' +
        '</div>';
      return '' +
        '<tr>' +
          '<td class="px-4 py-3">' +
            '<div class="font-black text-slate-900 dark:text-white">' + escapeHtml(u.full_name || '—') + '</div>' +
            '<div class="text-xs font-bold text-slate-500">' + escapeHtml(u.email) + '</div>' +
            '<div class="text-xs font-bold text-slate-500">' + escapeHtml(u.contact_info || '') + '</div>' +
          '</td>' +
          '<td class="px-4 py-3 text-sm font-bold text-slate-700 dark:text-slate-200">' + escapeHtml(u.association_name || '—') + '</td>' +
          '<td class="px-4 py-3 text-sm font-bold text-slate-700 dark:text-slate-200">' + escapeHtml(u.plates || '—') + '</td>' +
          '<td class="px-4 py-3">' + statusBadgeHtml(u.status) + '</td>' +
          '<td class="px-4 py-3">' + actions + '</td>' +
        '</tr>';
    }).join('');
    setText(operatorMeta, users.length + ' result(s)');
    if (window.lucide) window.lucide.createIcons();
  }

  if (commuterRefresh) commuterRefresh.addEventListener('click', function () { loadCommuters().catch(function () {}); });
  if (commuterQ) commuterQ.addEventListener('input', function () { clearTimeout(window.__cq); window.__cq = setTimeout(function () { loadCommuters().catch(function () {}); }, 250); });
  if (commuterStatusFilter) commuterStatusFilter.addEventListener('change', function () { loadCommuters().catch(function () {}); });

  if (operatorRefresh) operatorRefresh.addEventListener('click', function () { loadOperators().catch(function () {}); });
  if (operatorQ) operatorQ.addEventListener('input', function () { clearTimeout(window.__oq); window.__oq = setTimeout(function () { loadOperators().catch(function () {}); }, 250); });
  if (operatorStatusFilter) operatorStatusFilter.addEventListener('change', function () { loadOperators().catch(function () {}); });

  if (commuterBody) {
    commuterBody.addEventListener('change', function (e) {
      var sel = e.target;
      if (!sel || !sel.dataset || sel.dataset.action !== 'status') return;
      var userId = parseInt(sel.dataset.id || '0', 10);
      var st = sel.value;
      commutersPost({ action: 'set_status', user_id: userId, status: st }).then(function (res) {
        if (!res || res.ok !== true) { show(commuterError, true); setText(commuterError, 'Failed to update status.'); }
        loadCommuters().catch(function () {});
      });
    });
    commuterBody.addEventListener('click', function (e) {
      var btn = e.target.closest('button[data-action]');
      if (!btn) return;
      var act = btn.dataset.action;
      var userId = parseInt(btn.dataset.id || '0', 10);
      if (act === 'delete') {
        if (!confirm('Delete this commuter account?')) return;
        commutersPost({ action: 'delete', user_id: userId }).then(function (res) {
          if (!res || res.ok !== true) { show(commuterError, true); setText(commuterError, 'Failed to delete account.'); }
          loadCommuters().catch(function () {});
        });
        return;
      }
      if (act === 'reset') {
        if (!confirm('Reset password and unlock this commuter account?')) return;
        commutersPost({ action: 'reset_password', user_id: userId }).then(function (res) {
          if (!res || res.ok !== true) { show(commuterError, true); setText(commuterError, 'Failed to reset password.'); return; }
          show(commuterSuccess, true);
          setText(commuterSuccess, 'Temporary password: ' + (res.temporary_password || ''));
          loadCommuters().catch(function () {});
        });
        return;
      }
      if (act === 'activity') {
        var email = btn.dataset.email || '';
        commutersPost({ action: 'activity', user_id: userId }).then(function (res) {
          if (!res || res.ok !== true) { show(commuterError, true); setText(commuterError, 'Failed to load activity.'); return; }
          var items = res.items || [];
          openActivityModal('Login Activity', email ? ('Commuter: ' + email) : 'Recent sign-in attempts');
          if (!items.length) {
            if (activityBody) activityBody.innerHTML = '<tr><td colspan="3" class="px-6 py-10 text-center text-sm font-bold text-slate-500">No activity found.</td></tr>';
            setText(activityMeta, '0 events');
            return;
          }
          activityBody.innerHTML = items.map(function (it) {
            var ok = it.ok ? '<span class="px-2 py-1 rounded-lg text-[11px] font-black bg-emerald-100 text-emerald-700">OK</span>' : '<span class="px-2 py-1 rounded-lg text-[11px] font-black bg-rose-100 text-rose-700">FAIL</span>';
            return '<tr>' +
              '<td class="px-4 py-3">' + ok + '</td>' +
              '<td class="px-4 py-3 text-xs font-bold text-slate-600 dark:text-slate-300">' + escapeHtml(it.ip || '—') + '</td>' +
              '<td class="px-4 py-3 text-xs font-bold text-slate-600 dark:text-slate-300">' + escapeHtml(it.created_at || '—') + '</td>' +
            '</tr>';
          }).join('');
          setText(activityMeta, items.length + ' events');
        });
      }
    });
  }

  if (operatorBody) {
    operatorBody.addEventListener('change', function (e) {
      var sel = e.target;
      if (!sel || !sel.dataset || sel.dataset.action !== 'status') return;
      var userId = parseInt(sel.dataset.id || '0', 10);
      var st = sel.value;
      operatorsPost({ action: 'set_status', user_id: userId, status: st }).then(function (res) {
        if (!res || res.ok !== true) { show(operatorError, true); setText(operatorError, 'Failed to update status.'); }
        loadOperators().catch(function () {});
      });
    });
    operatorBody.addEventListener('click', function (e) {
      var btn = e.target.closest('button[data-action]');
      if (!btn) return;
      var act = btn.dataset.action;
      var userId = parseInt(btn.dataset.id || '0', 10);
      if (act === 'delete') {
        if (!confirm('Delete this operator account?')) return;
        operatorsPost({ action: 'delete', user_id: userId }).then(function (res) {
          if (!res || res.ok !== true) { show(operatorError, true); setText(operatorError, 'Failed to delete account.'); }
          loadOperators().catch(function () {});
        });
        return;
      }
      if (act === 'reset') {
        if (!confirm('Reset password and unlock this operator account?')) return;
        operatorsPost({ action: 'reset_password', user_id: userId }).then(function (res) {
          if (!res || res.ok !== true) { show(operatorError, true); setText(operatorError, 'Failed to reset password.'); return; }
          show(operatorSuccess, true);
          setText(operatorSuccess, 'Temporary password: ' + (res.temporary_password || ''));
          loadOperators().catch(function () {});
        });
      }
    });
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

  setTab('staff');

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
