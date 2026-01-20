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
        <button id="btnRepair" class="rounded-md bg-amber-100 hover:bg-amber-200 text-amber-700 font-bold py-2.5 px-4 transition-all flex items-center justify-center gap-2" title="Fix database duplicates">
          <i data-lucide="wrench" class="w-4 h-4"></i>
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
        <input id="commuterQ" type="text" placeholder="Search name, email, mobile..." class="w-full sm:w-80 rounded-md border-0 bg-white dark:bg-slate-900/50 py-2.5 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-emerald-500 transition-all">
        <select id="commuterStatusFilter" class="w-full sm:w-44 rounded-md border-0 bg-white dark:bg-slate-900/50 py-2.5 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-emerald-500 transition-all">
          <option value="">All Status</option>
          <option value="Active">Active</option>
          <option value="Inactive">Inactive</option>
          <option value="Locked">Locked</option>
        </select>
        <button id="commuterRefresh" class="rounded-md bg-emerald-600 hover:bg-emerald-500 text-white font-bold py-2.5 px-4 transition-all flex items-center justify-center gap-2">
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
        <div class="p-2 bg-amber-500/10 rounded-xl">
          <i data-lucide="briefcase" class="w-5 h-5 text-amber-600"></i>
        </div>
        <div>
          <h2 class="text-lg font-black text-slate-800 dark:text-white">Operator Accounts</h2>
          <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Transport Operators</p>
        </div>
      </div>
      <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
        <input id="operatorQ" type="text" placeholder="Search name, email, plates..." class="w-full sm:w-80 rounded-md border-0 bg-white dark:bg-slate-900/50 py-2.5 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-amber-500 transition-all">
        <select id="operatorStatusFilter" class="w-full sm:w-44 rounded-md border-0 bg-white dark:bg-slate-900/50 py-2.5 px-4 text-sm font-bold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-amber-500 transition-all">
          <option value="">All Status</option>
          <option value="Active">Active</option>
          <option value="Inactive">Inactive</option>
          <option value="Locked">Locked</option>
        </select>
        <button id="operatorRefresh" class="rounded-md bg-amber-600 hover:bg-amber-500 text-white font-bold py-2.5 px-4 transition-all flex items-center justify-center gap-2">
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
              <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Vehicles</th>
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

<!-- Activity Modal -->
<div id="activityBackdrop" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
  <div class="relative w-full max-w-lg rounded-2xl bg-white dark:bg-slate-800 shadow-2xl ring-1 ring-slate-200 dark:ring-slate-700 flex flex-col max-h-[80vh]">
    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-700 px-6 py-4">
      <div>
        <h3 id="activityTitle" class="text-lg font-black text-slate-800 dark:text-white">Login Activity</h3>
        <p id="activitySubtitle" class="text-xs font-bold text-slate-400 uppercase tracking-wider">Recent sign-in attempts</p>
      </div>
      <button id="btnCloseActivity" class="rounded-xl p-2 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-slate-600 transition-colors">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>
    <div class="flex-1 overflow-y-auto p-0">
      <div id="activityError" class="hidden m-4 rounded-xl bg-rose-50 border border-rose-100 px-4 py-3 text-sm font-bold text-rose-600"></div>
      <table class="min-w-full divide-y divide-slate-100 dark:divide-slate-700">
        <thead class="bg-slate-50 dark:bg-slate-900/30 sticky top-0">
          <tr>
            <th class="px-4 py-2 text-left text-xs font-black text-slate-500 uppercase">Result</th>
            <th class="px-4 py-2 text-left text-xs font-black text-slate-500 uppercase">IP Address</th>
            <th class="px-4 py-2 text-left text-xs font-black text-slate-500 uppercase">Time</th>
          </tr>
        </thead>
        <tbody id="activityBody" class="divide-y divide-slate-100 dark:divide-slate-700"></tbody>
      </table>
    </div>
    <div id="activityMeta" class="px-6 py-3 bg-slate-50 dark:bg-slate-800 border-t border-slate-100 dark:border-slate-700 text-xs font-bold text-slate-500 text-right"></div>
  </div>
</div>

<!-- Create/Edit User Modal (Unified) -->
<div id="userModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4 overflow-y-auto">
  <div class="relative w-full max-w-2xl rounded-2xl bg-white dark:bg-slate-800 shadow-2xl ring-1 ring-slate-200 dark:ring-slate-700 flex flex-col max-h-[90vh]">
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
    <form id="formUser" class="flex-1 overflow-y-auto p-6 space-y-6">
      <input type="hidden" name="id" id="userId">
      <div id="modalError" class="hidden rounded-xl bg-rose-50 border border-rose-100 px-4 py-3 text-sm font-bold text-rose-600 flex items-start gap-2">
        <i data-lucide="alert-circle" class="w-5 h-5 shrink-0"></i>
        <span id="modalErrorText"></span>
      </div>
      <div id="modalSuccess" class="hidden rounded-xl bg-emerald-50 border border-emerald-100 px-4 py-3 text-sm font-bold text-emerald-600 flex items-start gap-2">
        <i data-lucide="check-circle" class="w-5 h-5 shrink-0"></i>
        <span id="modalSuccessText"></span>
      </div>
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
  var basePrefix = '<?php echo $rootUrl; ?>';
  const API_BASE = '<?php echo $rootUrl; ?>/admin/api/settings/';

  // Tab Logic
  const tabBtnStaff = document.getElementById('tabBtnStaff');
  const tabBtnCommuters = document.getElementById('tabBtnCommuters');
  const tabBtnOperators = document.getElementById('tabBtnOperators');
  const tabStaff = document.getElementById('tabStaff');
  const tabCommuters = document.getElementById('tabCommuters');
  const tabOperators = document.getElementById('tabOperators');

  function switchTab(t) {
    // Reset buttons
    [tabBtnStaff, tabBtnCommuters, tabBtnOperators].forEach(b => {
      b.classList.remove('bg-white', 'text-slate-900', 'shadow-sm');
      b.classList.add('text-slate-600', 'hover:bg-white/60');
    });
    // Reset views
    [tabStaff, tabCommuters, tabOperators].forEach(v => v.classList.add('hidden'));

    if (t === 'staff') {
      tabBtnStaff.classList.add('bg-white', 'text-slate-900', 'shadow-sm');
      tabBtnStaff.classList.remove('text-slate-600', 'hover:bg-white/60');
      tabStaff.classList.remove('hidden');
    }
    if (t === 'commuters') {
      tabBtnCommuters.classList.add('bg-white', 'text-slate-900', 'shadow-sm');
      tabBtnCommuters.classList.remove('text-slate-600', 'hover:bg-white/60');
      tabCommuters.classList.remove('hidden');
      loadCommuters();
    }
    if (t === 'operators') {
      tabBtnOperators.classList.add('bg-white', 'text-slate-900', 'shadow-sm');
      tabBtnOperators.classList.remove('text-slate-600', 'hover:bg-white/60');
      tabOperators.classList.remove('hidden');
      loadOperators();
    }
  }

  tabBtnStaff.addEventListener('click', () => switchTab('staff'));
  tabBtnCommuters.addEventListener('click', () => switchTab('commuters'));
  tabBtnOperators.addEventListener('click', () => switchTab('operators'));

  // Helpers
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
  function statusBadgeHtml(status) {
    var s = String(status || '');
    if (s === 'Active') return '<span class="px-3 py-1 rounded-full text-xs font-black bg-emerald-100 text-emerald-700">Active</span>';
    if (s === 'Locked') return '<span class="px-3 py-1 rounded-full text-xs font-black bg-rose-100 text-rose-700">Locked</span>';
    return '<span class="px-3 py-1 rounded-full text-xs font-black bg-amber-100 text-amber-700">Inactive</span>';
  }
  function show(el, v) { if(el) { if(v) el.classList.remove('hidden'); else el.classList.add('hidden'); } }
  function setText(el, t) { if(el) el.textContent = t; }

  function apiUsersUrl(path) {
    return basePrefix + '/admin/api/users/' + path;
  }

  // --- STAFF ---
  let rolesCache = [];
  async function loadRoles() {
    try {
      const res = await fetch(`${API_BASE}rbac_roles.php`);
      const data = await res.json();
      if (data.ok) {
        rolesCache = data.roles || [];
        renderRoleCheckboxes();
      }
    } catch (e) { console.error(e); }
  }
  function renderRoleCheckboxes(selectedIds = []) {
    const container = document.getElementById('rolesContainer');
    if (!rolesCache.length) {
      container.innerHTML = '<div class="text-sm text-slate-400">No roles defined.</div>';
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

  async function loadUsers() {
    const tbody = document.getElementById('usersBody');
    const meta = document.getElementById('usersMeta');
    const q = document.getElementById('q').value;
    const status = document.getElementById('statusFilter').value;
    
    tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-10 text-center text-sm font-bold text-slate-500">Loading...</td></tr>';
    
    try {
      const params = new URLSearchParams({ q, status });
      const res = await fetch(`${API_BASE}rbac_users.php?${params}`);
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Failed');
      
      const users = data.users || [];
      if(!users.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-10 text-center text-sm font-bold text-slate-500">No staff accounts found.</td></tr>';
        meta.textContent = '0 results';
        return;
      }
      
      tbody.innerHTML = users.map(u => {
        const roleBadges = (u.roles && u.roles.length) 
          ? u.roles.map(r => `<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-black bg-indigo-50 text-indigo-700 border border-indigo-100">${escapeHtml(r.name)}</span>`).join(' ')
          : '<span class="text-xs text-slate-400 italic">No roles</span>';
        
        return `
          <tr class="hover:bg-slate-50/50 transition-colors group">
            <td class="px-4 py-3">
              <div class="font-black text-slate-900 dark:text-white">${escapeHtml(u.first_name)} ${escapeHtml(u.last_name)}</div>
              <div class="text-xs font-bold text-slate-500">${escapeHtml(u.email)}</div>
            </td>
            <td class="px-4 py-3">
              <div class="text-sm font-bold text-slate-700">${escapeHtml(u.department || '—')}</div>
              <div class="text-xs text-slate-500">${escapeHtml(u.position_title || '')}</div>
            </td>
            <td class="px-4 py-3">
              <div class="flex flex-wrap gap-1 max-w-xs">${roleBadges}</div>
            </td>
            <td class="px-4 py-3">${statusBadgeHtml(u.status)}</td>
            <td class="px-4 py-3 text-xs font-bold text-slate-500">${escapeHtml(u.last_login_at || '—')}</td>
            <td class="px-4 py-3 text-right">
              <button onclick='openUserModal(${JSON.stringify(u)})' class="text-indigo-600 hover:text-indigo-500 font-bold text-sm px-3 py-1.5 rounded-lg hover:bg-indigo-50 transition-colors">Edit</button>
            </td>
          </tr>
        `;
      }).join('');
      meta.textContent = `${users.length} staff member(s)`;
      if(window.lucide) lucide.createIcons();
    } catch (e) {
      tbody.innerHTML = `<tr><td colspan="6" class="px-6 py-10 text-center text-sm font-bold text-rose-500">Error: ${e.message}</td></tr>`;
    }
  }

  async function runRepair() {
    if (!confirm('Run database diagnostics and repair? This will deduplicate roles and fix constraints.')) return;
    const btn = document.getElementById('btnRepair');
    btn.classList.add('opacity-50', 'pointer-events-none');
    try {
      const res = await fetch(`${API_BASE}rbac_repair.php`, { method: 'POST' });
      const data = await res.json();
      if (data.ok) { alert('Success: ' + data.message); loadUsers(); loadRoles(); } 
      else { alert('Repair Failed: ' + (data.error || 'Unknown error')); }
    } catch (e) { alert('Repair Error: ' + e.message); } 
    finally { btn.classList.remove('opacity-50', 'pointer-events-none'); }
  }

  // --- COMMUTERS ---
  var commuterQ = document.getElementById('commuterQ');
  var commuterStatusFilter = document.getElementById('commuterStatusFilter');
  var commuterRefresh = document.getElementById('commuterRefresh');
  var commuterBody = document.getElementById('commuterBody');
  var commuterMeta = document.getElementById('commuterMeta');
  var commuterError = document.getElementById('commuterError');
  var commuterSuccess = document.getElementById('commuterSuccess');

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
    show(commuterError, false); setText(commuterError, '');
    show(commuterSuccess, false); setText(commuterSuccess, '');
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

  if (commuterRefresh) commuterRefresh.addEventListener('click', function () { loadCommuters().catch(function () {}); });
  if (commuterQ) commuterQ.addEventListener('input', function () { clearTimeout(window.__cq); window.__cq = setTimeout(function () { loadCommuters().catch(function () {}); }, 250); });
  if (commuterStatusFilter) commuterStatusFilter.addEventListener('change', function () { loadCommuters().catch(function () {}); });
  
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

  // --- OPERATORS ---
  var operatorQ = document.getElementById('operatorQ');
  var operatorStatusFilter = document.getElementById('operatorStatusFilter');
  var operatorRefresh = document.getElementById('operatorRefresh');
  var operatorBody = document.getElementById('operatorBody');
  var operatorMeta = document.getElementById('operatorMeta');
  var operatorError = document.getElementById('operatorError');
  var operatorSuccess = document.getElementById('operatorSuccess');

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
    show(operatorError, false); setText(operatorError, '');
    show(operatorSuccess, false); setText(operatorSuccess, '');
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

  if (operatorRefresh) operatorRefresh.addEventListener('click', function () { loadOperators().catch(function () {}); });
  if (operatorQ) operatorQ.addEventListener('input', function () { clearTimeout(window.__oq); window.__oq = setTimeout(function () { loadOperators().catch(function () {}); }, 250); });
  if (operatorStatusFilter) operatorStatusFilter.addEventListener('change', function () { loadOperators().catch(function () {}); });
  
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

  // --- ACTIVITY MODAL ---
  var activityBackdrop = document.getElementById('activityBackdrop');
  var btnCloseActivity = document.getElementById('btnCloseActivity');
  var activityTitle = document.getElementById('activityTitle');
  var activitySubtitle = document.getElementById('activitySubtitle');
  var activityError = document.getElementById('activityError');
  var activityBody = document.getElementById('activityBody');
  var activityMeta = document.getElementById('activityMeta');

  function openActivityModal(title, subtitle) {
    if (!activityBackdrop) return;
    show(activityError, false); setText(activityError, '');
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

  // --- USER MODAL (STAFF) ---
  function openUserModal(user = null) {
    const modal = document.getElementById('userModal');
    const form = document.getElementById('formUser');
    const title = document.getElementById('modalTitle');
    const errorDiv = document.getElementById('modalError');
    const successDiv = document.getElementById('modalSuccess');
    
    form.reset();
    errorDiv.classList.add('hidden');
    successDiv.classList.add('hidden');
    
    if (user) {
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
      title.textContent = 'Create Account';
      document.getElementById('userId').value = '';
      renderRoleCheckboxes([]);
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
    
    if (!form.checkValidity()) { form.reportValidity(); return; }
    
    const formData = new FormData(form);
    const payload = Object.fromEntries(formData.entries());
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
      
      if (isEdit) {
        successText.textContent = 'Account updated successfully.';
        successDiv.classList.remove('hidden');
        setTimeout(() => { closeModal(); loadUsers(); }, 1000);
      } else {
        const msg = `Account created! Temp Password: ${data.temporary_password}`;
        successText.textContent = msg;
        successDiv.classList.remove('hidden');
        loadUsers();
      }
    } catch (err) {
      errorText.textContent = err.message;
      errorDiv.classList.remove('hidden');
    }
  }

  // Load staff initially
  loadRoles();
  loadUsers();

</script>
