<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['SuperAdmin']);

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$rootUrl = '';
$pos = strpos($scriptName, '/admin/');
if ($pos !== false) $rootUrl = substr($scriptName, 0, $pos);
if ($rootUrl === '/') $rootUrl = '';
?>

<div class="mx-auto max-w-7xl px-4 py-8 space-y-8">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-black text-slate-800 dark:text-white flex flex-wrap items-center gap-3 leading-tight">
                <div class="p-3 bg-violet-500/10 rounded-2xl">
                    <i data-lucide="id-card" class="w-8 h-8 text-violet-500"></i>
                </div>
                Operator Portal Accounts
            </h1>
            <p class="mt-2 text-slate-500 dark:text-slate-400 font-medium ml-0 sm:ml-14">Manage operator portal logins (status, reset password, delete).</p>
        </div>
        <div class="flex flex-col sm:flex-row sm:items-center gap-2 w-full sm:w-auto">
            <button type="button" onclick="syncPortalOperators()" class="w-full sm:w-auto bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2.5 px-6 rounded-xl shadow-sm transition-all flex items-center justify-center gap-2">
                <i data-lucide="user-check" class="w-5 h-5"></i>
                Sync Operators
            </button>
            <button type="button" onclick="syncOperatorPlates()" class="w-full sm:w-auto bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-6 rounded-xl shadow-sm transition-all flex items-center justify-center gap-2">
                <i data-lucide="refresh-ccw" class="w-5 h-5"></i>
                Sync Plates
            </button>
            <button type="button" onclick="loadOperatorAccounts(true)" class="w-full sm:w-auto bg-violet-600 hover:bg-violet-700 text-white font-bold py-2.5 px-6 rounded-xl shadow-sm transition-all flex items-center justify-center gap-2">
                <i data-lucide="refresh-cw" class="w-5 h-5"></i>
                Refresh
            </button>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 p-4 flex flex-col sm:flex-row gap-4">
        <div class="relative flex-1">
            <i data-lucide="search" class="absolute left-3 top-3 w-5 h-5 text-slate-400"></i>
            <input type="text" id="op-search" placeholder="Search email, name, plate..." class="block w-full pl-10 rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 py-2.5 text-sm font-medium focus:ring-2 focus:ring-violet-500">
        </div>
        <select id="op-status-filter" class="rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 py-2.5 pl-4 pr-10 text-sm font-medium focus:ring-2 focus:ring-violet-500">
            <option value="">All Status</option>
            <option value="Active">Active</option>
            <option value="Inactive">Inactive</option>
            <option value="Locked">Locked</option>
        </select>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700 text-xs uppercase tracking-wider text-slate-500 font-bold">
                        <th class="px-6 py-4">Account</th>
                        <th class="px-6 py-4">Plates</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4">Created</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="op-table-body" class="divide-y divide-slate-100 dark:divide-slate-700">
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-slate-400 font-medium">
                            <i data-lucide="loader-2" class="w-8 h-8 animate-spin mx-auto mb-2"></i>
                            Loading operator accounts...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between gap-4">
            <div>
                <div class="text-lg font-black text-slate-800 dark:text-white">Operator Verification</div>
                <div class="text-sm text-slate-500 dark:text-slate-400 font-medium">Review uploaded documents and approve operator accounts.</div>
            </div>
        </div>
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row gap-4">
            <div class="relative flex-1">
                <i data-lucide="search" class="absolute left-3 top-3 w-5 h-5 text-slate-400"></i>
                <input type="text" id="ov-search" placeholder="Search by name, email, association..."
                    class="block w-full pl-10 rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 py-2.5 text-sm font-medium focus:ring-2 focus:ring-indigo-500">
            </div>
            <select id="ov-approval-filter" class="rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 py-2.5 pl-4 pr-10 text-sm font-medium focus:ring-2 focus:ring-indigo-500">
                <option value="">All Approval</option>
                <option value="Pending" selected>Pending</option>
                <option value="Approved">Approved</option>
                <option value="Rejected">Rejected</option>
            </select>
            <select id="ov-status-filter" class="rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 py-2.5 pl-4 pr-10 text-sm font-medium focus:ring-2 focus:ring-indigo-500">
                <option value="">All Status</option>
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
                <option value="Locked">Locked</option>
            </select>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700 text-xs uppercase tracking-wider text-slate-500 font-bold">
                        <th class="px-6 py-4">Operator</th>
                        <th class="px-6 py-4">Type</th>
                        <th class="px-6 py-4">Approval</th>
                        <th class="px-6 py-4">Docs</th>
                        <th class="px-6 py-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody id="ov-table-body" class="divide-y divide-slate-100 dark:divide-slate-700">
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-slate-400 font-medium">
                            <i data-lucide="loader-2" class="w-8 h-8 animate-spin mx-auto mb-2"></i>
                            Loading operators...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="ov-modal" class="fixed inset-0 z-[100] hidden" aria-labelledby="ov-modal-title" role="dialog" aria-modal="true">
  <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="ovCloseModal()"></div>
  <div class="fixed inset-0 z-10 overflow-y-auto">
    <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
      <div class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-slate-800 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-3xl border border-slate-200 dark:border-slate-700">
        <div class="bg-slate-50/50 dark:bg-slate-800/50 px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
          <div>
            <h3 class="text-lg font-black text-slate-800 dark:text-white" id="ov-modal-title">Review Operator</h3>
            <div class="text-xs text-slate-500 mt-1" id="ov-modal-sub">Loading...</div>
          </div>
          <button type="button" onclick="ovCloseModal()" class="text-slate-400 hover:text-slate-500 transition-colors">
            <i data-lucide="x" class="w-5 h-5"></i>
          </button>
        </div>

        <div class="px-6 py-6 space-y-6">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/30">
              <div class="text-[10px] font-black uppercase tracking-wider text-slate-400">Approval</div>
              <div class="mt-1 text-sm font-bold text-slate-900 dark:text-white" id="ov-approval">--</div>
            </div>
            <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/30">
              <div class="text-[10px] font-black uppercase tracking-wider text-slate-400">Operator Type</div>
              <div class="mt-1 text-sm font-bold text-slate-900 dark:text-white" id="ov-type">--</div>
            </div>
            <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/30">
              <div class="text-[10px] font-black uppercase tracking-wider text-slate-400">Submitted</div>
              <div class="mt-1 text-sm font-bold text-slate-900 dark:text-white" id="ov-submitted">--</div>
            </div>
          </div>

          <div>
            <div class="text-xs font-black uppercase tracking-wider text-slate-400 mb-3">Documents</div>
            <div class="space-y-3" id="ov-docs">Loading...</div>
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Remarks (optional)</label>
            <textarea id="ov-remarks" rows="3" class="block w-full rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 py-2.5 px-4 text-sm font-semibold focus:ring-2 focus:ring-indigo-500" placeholder="Remarks shown to the operator..."></textarea>
          </div>
        </div>

        <div class="bg-slate-50/50 dark:bg-slate-800/50 px-6 py-4 border-t border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row justify-between gap-3">
          <button type="button" onclick="ovReject()" class="px-5 py-2.5 text-sm font-black text-white rounded-xl bg-rose-600 hover:bg-rose-700 transition-all">Reject</button>
          <div class="flex items-center justify-end gap-2">
            <button type="button" onclick="ovCloseModal()" class="px-5 py-2.5 text-sm font-bold text-slate-600 hover:text-slate-800 hover:bg-slate-100 rounded-xl transition-all">Close</button>
            <button type="button" onclick="ovApprove()" class="bg-emerald-600 hover:bg-emerald-700 text-white font-black py-2.5 px-6 rounded-xl shadow-sm transition-all">Approve</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div id="op-pwd-modal" class="fixed inset-0 z-[110] hidden" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeOpPwdModal()"></div>
    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-slate-800 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-slate-200 dark:border-slate-700">
                <div class="bg-slate-50/50 dark:bg-slate-800/50 px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                    <h3 class="text-lg font-black text-slate-800 dark:text-white">Temporary Password</h3>
                    <button type="button" onclick="closeOpPwdModal()" class="text-slate-400 hover:text-slate-500 transition-colors">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
                <div class="px-6 py-6 space-y-4">
                    <div class="text-sm font-semibold text-slate-600 dark:text-slate-300">Copy and send this to the operator. It will be shown only once.</div>
                    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40 p-4">
                        <div class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Password</div>
                        <div class="mt-2 flex items-center gap-2">
                            <input id="op-temp-password" readonly class="flex-1 rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900/60 py-2.5 px-4 text-sm font-black tracking-wider">
                            <button type="button" id="op-copy-btn" onclick="copyOpTempPassword()" class="bg-violet-600 hover:bg-violet-700 text-white font-bold py-2.5 px-4 rounded-xl shadow-sm transition-all flex items-center gap-2">
                                <i data-lucide="copy" class="w-4 h-4"></i>
                                Copy
                            </button>
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button type="button" onclick="closeOpPwdModal()" class="bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 dark:hover:bg-slate-600 text-white font-bold py-2.5 px-6 rounded-xl shadow-sm transition-all">
                            Done
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        const rootUrl = <?php echo json_encode($rootUrl, JSON_UNESCAPED_SLASHES); ?>;
        const apiUrl = rootUrl + '/admin/api/users/operators.php';
        const tableBody = document.getElementById('op-table-body');
        const searchEl = document.getElementById('op-search');
        const statusEl = document.getElementById('op-status-filter');
        const appTypeEl = document.getElementById('op-app-type');
        const appTableBody = document.getElementById('op-app-table-body');
        const pwdModal = document.getElementById('op-pwd-modal');
        const pwdInput = document.getElementById('op-temp-password');

        window.syncPortalOperators = async function() {
            if (!confirm('This will backfill operator records for approved Active portal accounts that are not yet linked. Proceed?')) return;

            const btn = document.querySelector('button[onclick="syncPortalOperators()"]');
            if (!btn) return;
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = `<i data-lucide="loader-2" class="w-5 h-5 animate-spin"></i> Syncing...`;
            if (window.lucide) window.lucide.createIcons();

            try {
                const res = await fetch(rootUrl + '/admin/api/users/sync_portal_operators.php', {
                    headers: { 'Accept': 'application/json' }
                });
                const text = await res.text();
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('JSON Parse Error:', text);
                    throw new Error('Invalid server response: ' + text.substring(0, 120));
                }
                if (data.ok) {
                    const s = data.stats || {};
                    alert(
                        'Operator sync complete.' +
                        '\n\nPortal users processed: ' + String(s.processed ?? 0) +
                        '\nOperator records created: ' + String(s.created ?? 0) +
                        '\nOperator records updated: ' + String(s.updated ?? 0) +
                        '\nAccounts linked: ' + String(s.linked ?? 0) +
                        '\nSkipped: ' + String(s.skipped ?? 0) +
                        '\nFailed: ' + String(s.failed ?? 0)
                    );
                    loadOperatorAccounts(true);
                } else {
                    alert('Sync Failed: ' + (data.error || 'Unknown error'));
                }
            } catch (e) {
                console.error(e);
                alert('Sync Error: ' + e.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
                if (window.lucide) window.lucide.createIcons();
            }
        };

        window.syncOperatorPlates = async function() {
            if (!confirm('This will sync all admin-linked vehicles to operator portal users based on matching names. Proceed?')) return;
            
            const btn = document.querySelector('button[onclick="syncOperatorPlates()"]');
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = `<i data-lucide="loader-2" class="w-5 h-5 animate-spin"></i> Syncing...`;
            if (window.lucide) window.lucide.createIcons();

            try {
                const res = await fetch(rootUrl + '/admin/api/module1/sync_plates.php', {
                    headers: { 'Accept': 'application/json' }
                });
                const text = await res.text();
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('JSON Parse Error:', text);
                    throw new Error('Invalid server response: ' + text.substring(0, 100));
                }
                
                if (data.ok) {
                    const stats = data.stats;
                    alert(`Sync Complete!\n\nProcessed: ${stats.processed}\nSynced: ${stats.synced}\nSkipped: ${stats.skipped}\nNot Found: ${stats.not_found}\nFailed: ${stats.failed}`);
                    loadOperatorAccounts(true);
                } else {
                    alert('Sync Failed: ' + (data.error || 'Unknown error'));
                }
            } catch (e) {
                console.error(e);
                alert('Sync Error: ' + e.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
                if (window.lucide) window.lucide.createIcons();
            }
        };

        let debounceId = null;

        function esc(s) {
            const div = document.createElement('div');
            div.textContent = String(s || '');
            return div.innerHTML;
        }

        function badge(status) {
            const st = String(status || '');
            if (st === 'Active') return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300';
            if (st === 'Locked') return 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300';
            return 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300';
        }

        function renderLoading() {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="5" class="px-6 py-12 text-center text-slate-400 font-medium">
                        <i data-lucide="loader-2" class="w-8 h-8 animate-spin mx-auto mb-2"></i>
                        Loading operator accounts...
                    </td>
                </tr>
            `;
            try { window.lucide && window.lucide.createIcons && window.lucide.createIcons(); } catch (e) { }
        }

        function renderEmpty() {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="5" class="px-6 py-12 text-center text-slate-400 font-medium">
                        No operator accounts found.
                    </td>
                </tr>
            `;
        }

        function renderRows(users) {
            if (!users || !users.length) {
                renderEmpty();
                return;
            }
            tableBody.innerHTML = users.map(u => {
                const name = u.full_name ? u.full_name : '(no name)';
                const created = u.created_at ? u.created_at : '';
                const plates = u.plates ? u.plates : '';
                const st = u.status ? u.status : '';
                return `
                    <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-700/20 transition-colors">
                        <td class="px-6 py-4">
                            <div class="font-black text-slate-800 dark:text-white">${esc(name)}</div>
                            <div class="text-sm text-slate-500 dark:text-slate-400 font-semibold mt-1">${esc(u.email)}</div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm font-bold text-slate-700 dark:text-slate-200">${esc(plates)}</div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-3 py-1.5 rounded-xl text-xs font-black ${badge(st)}">${esc(st)}</span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm font-semibold text-slate-600 dark:text-slate-300">${esc(created)}</div>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <select data-action="status" data-id="${u.id}" class="rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 py-2 px-3 text-xs font-bold focus:ring-2 focus:ring-violet-500">
                                    <option value="Active" ${st === 'Active' ? 'selected' : ''}>Active</option>
                                    <option value="Inactive" ${st === 'Inactive' ? 'selected' : ''}>Inactive</option>
                                    <option value="Locked" ${st === 'Locked' ? 'selected' : ''}>Locked</option>
                                </select>
                                <button type="button" data-action="reset" data-id="${u.id}" class="bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40 text-slate-700 dark:text-slate-200 font-bold py-2 px-3 rounded-xl shadow-sm transition-all flex items-center gap-2 border border-slate-200 dark:border-slate-700">
                                    <i data-lucide="key" class="w-4 h-4"></i>
                                    Reset
                                </button>
                                <button type="button" data-action="delete" data-id="${u.id}" class="bg-rose-600 hover:bg-rose-700 text-white font-bold py-2 px-3 rounded-xl shadow-sm transition-all flex items-center gap-2">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
            try { window.lucide && window.lucide.createIcons && window.lucide.createIcons(); } catch (e) { }
        }

        function renderAppLoading() {
            if (!appTableBody) return;
            appTableBody.innerHTML = `
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-slate-400 font-medium">
                        <i data-lucide="loader-2" class="w-8 h-8 animate-spin mx-auto mb-2"></i>
                        Loading applications...
                    </td>
                </tr>
            `;
            try { window.lucide && window.lucide.createIcons && window.lucide.createIcons(); } catch (e) { }
        }

        function renderAppRows(rows) {
            if (!appTableBody) return;
            const items = Array.isArray(rows) ? rows : [];
            if (!items.length) {
                appTableBody.innerHTML = `<tr><td colspan="6" class="px-6 py-12 text-center text-slate-400 font-medium">No applications found.</td></tr>`;
                return;
            }
            appTableBody.innerHTML = items.map(a => {
                const dt = (a.created_at || '').toString();
                const operator = (a.full_name || a.association_name || '').toString();
                const email = (a.email || '').toString();
                const plate = (a.plate_number || '').toString();
                const type = (a.type || '').toString();
                const status = (a.status || '').toString();
                const canInspect = type === 'Vehicle Inspection';
                return `
                    <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-700/20 transition-colors">
                        <td class="px-6 py-4 text-sm font-semibold text-slate-600 dark:text-slate-300">${esc(dt)}</td>
                        <td class="px-6 py-4">
                            <div class="font-black text-slate-800 dark:text-white">${esc(operator || '(no name)')}</div>
                            <div class="text-sm text-slate-500 dark:text-slate-400 font-semibold mt-1">${esc(email)}</div>
                        </td>
                        <td class="px-6 py-4 font-mono font-bold text-slate-700 dark:text-slate-200">${esc(plate)}</td>
                        <td class="px-6 py-4 text-sm font-bold text-slate-700 dark:text-slate-200">${esc(type)}</td>
                        <td class="px-6 py-4">
                            <select data-action="app-status" data-app-id="${a.id}" class="rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 py-2 px-3 text-xs font-bold focus:ring-2 focus:ring-violet-500">
                                ${['Pending','Submitted','Under Review','Endorsed','Approved','Denied','Rejected'].map(s => `<option value="${esc(s)}" ${s===status?'selected':''}>${esc(s)}</option>`).join('')}
                            </select>
                        </td>
                        <td class="px-6 py-4 text-right">
                            ${canInspect ? `
                                <div class="flex items-center justify-end gap-2">
                                    <button type="button" data-action="inspect-pass" data-app-id="${a.id}" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2 px-3 rounded-xl shadow-sm transition-all text-xs">Mark Passed</button>
                                    <button type="button" data-action="inspect-fail" data-app-id="${a.id}" class="bg-rose-600 hover:bg-rose-700 text-white font-bold py-2 px-3 rounded-xl shadow-sm transition-all text-xs">Mark Failed</button>
                                </div>
                            ` : `<span class="text-xs text-slate-400 font-semibold">—</span>`}
                        </td>
                    </tr>
                `;
            }).join('');
            try { window.lucide && window.lucide.createIcons && window.lucide.createIcons(); } catch (e) { }
        }

        async function apiPost(payload) {
            const res = await fetch(apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(payload || {})
            });
            const data = await res.json().catch(() => null);
            if (!data || !data.ok) {
                const msg = (data && (data.message || data.error)) ? String(data.message || data.error) : 'Request failed';
                throw new Error(msg);
            }
            return data;
        }

        async function loadOperatorAccounts(force) {
            if (force) renderLoading();
            const q = String(searchEl.value || '').trim();
            const st = String(statusEl.value || '').trim();
            const params = new URLSearchParams();
            if (q) params.set('q', q);
            if (st) params.set('status', st);
            const res = await fetch(apiUrl + (params.toString() ? ('?' + params.toString()) : ''), { headers: { 'Accept': 'application/json' } });
            const data = await res.json().catch(() => null);
            if (!data || !data.ok) {
                tableBody.innerHTML = `
                    <tr><td colspan="5" class="px-6 py-12 text-center text-rose-500 font-semibold">Failed to load.</td></tr>
                `;
                return;
            }
            renderRows(data.users || []);
        }

        async function loadOperatorApplications(force) {
            if (!appTableBody) return;
            if (force) renderAppLoading();
            const q = String(searchEl.value || '').trim();
            const type = appTypeEl ? String(appTypeEl.value || '').trim() : '';
            const params = new URLSearchParams();
            params.set('mode', 'applications');
            if (q) params.set('q', q);
            if (type) params.set('type', type);
            const res = await fetch(apiUrl + '?' + params.toString(), { headers: { 'Accept': 'application/json' } });
            const data = await res.json().catch(() => null);
            if (!data || !data.ok) {
                appTableBody.innerHTML = `<tr><td colspan="6" class="px-6 py-12 text-center text-rose-500 font-semibold">Failed to load applications.</td></tr>`;
                return;
            }
            renderAppRows(data.applications || []);
        }

        function scheduleReload() {
            if (debounceId) clearTimeout(debounceId);
            debounceId = setTimeout(() => loadOperatorAccounts(false), 250);
        }

        function openOpPwdModal(pwd) {
            if (!pwdModal || !pwdInput) return;
            pwdInput.value = String(pwd || '');
            pwdModal.classList.remove('hidden');
            try { document.body.classList.add('overflow-hidden'); } catch (e) { }
            try { window.lucide && window.lucide.createIcons && window.lucide.createIcons(); } catch (e) { }
        }

        function closeOpPwdModal() {
            if (!pwdModal) return;
            pwdModal.classList.add('hidden');
            try { document.body.classList.remove('overflow-hidden'); } catch (e) { }
        }

        async function copyOpTempPassword() {
            const v = pwdInput ? String(pwdInput.value || '') : '';
            if (!v) return;
            try {
                await navigator.clipboard.writeText(v);
                const btn = document.getElementById('op-copy-btn');
                if (btn) {
                    btn.disabled = true;
                    btn.textContent = 'Copied';
                    setTimeout(() => { btn.disabled = false; btn.innerHTML = '<i data-lucide="copy" class="w-4 h-4"></i> Copy'; try { window.lucide && window.lucide.createIcons && window.lucide.createIcons(); } catch (e) { } }, 900);
                }
            } catch (e) { }
        }

        tableBody.addEventListener('change', async (e) => {
            const el = e.target;
            if (!el || el.getAttribute('data-action') !== 'status') return;
            const id = parseInt(el.getAttribute('data-id') || '0', 10);
            const status = String(el.value || '');
            if (!id || !status) return;
            el.disabled = true;
            try {
                await apiPost({ action: 'set_status', user_id: id, status });
            } catch (err) {
                alert(err.message || 'Failed');
            } finally {
                el.disabled = false;
                await loadOperatorAccounts(false);
            }
        });

        tableBody.addEventListener('click', async (e) => {
            const btn = e.target && e.target.closest ? e.target.closest('button[data-action]') : null;
            if (!btn) return;
            const action = btn.getAttribute('data-action');
            const id = parseInt(btn.getAttribute('data-id') || '0', 10);
            if (!action || !id) return;
            btn.disabled = true;
            try {
                if (action === 'reset') {
                    const data = await apiPost({ action: 'reset_password', user_id: id });
                    openOpPwdModal(data.temporary_password || '');
                } else if (action === 'delete') {
                    const ok = confirm('Delete this operator portal account? This cannot be undone.');
                    if (!ok) return;
                    await apiPost({ action: 'delete', user_id: id });
                }
            } catch (err) {
                alert(err.message || 'Failed');
            } finally {
                btn.disabled = false;
                await loadOperatorAccounts(false);
            }
        });

        if (appTableBody) {
            appTableBody.addEventListener('change', async (e) => {
                const el = e.target;
                if (!el || el.getAttribute('data-action') !== 'app-status') return;
                const appId = parseInt(el.getAttribute('data-app-id') || '0', 10);
                const status = String(el.value || '');
                if (!appId || !status) return;
                el.disabled = true;
                try {
                    await apiPost({ mode: 'applications', action: 'app_set_status', app_id: appId, status });
                } catch (err) {
                    alert(err.message || 'Failed');
                } finally {
                    el.disabled = false;
                    await loadOperatorApplications(false);
                }
            });

            appTableBody.addEventListener('click', async (e) => {
                const btn = e.target && e.target.closest ? e.target.closest('button[data-action]') : null;
                if (!btn) return;
                const action = String(btn.getAttribute('data-action') || '');
                const appId = parseInt(btn.getAttribute('data-app-id') || '0', 10);
                if (!action || !appId) return;
                btn.disabled = true;
                try {
                    if (action === 'inspect-pass') {
                        await apiPost({ mode: 'applications', action: 'app_set_inspection', app_id: appId, inspection_status: 'Passed' });
                        await apiPost({ mode: 'applications', action: 'app_set_status', app_id: appId, status: 'Approved' });
                    } else if (action === 'inspect-fail') {
                        await apiPost({ mode: 'applications', action: 'app_set_inspection', app_id: appId, inspection_status: 'Failed' });
                        await apiPost({ mode: 'applications', action: 'app_set_status', app_id: appId, status: 'Denied' });
                    }
                } catch (err) {
                    alert(err.message || 'Failed');
                } finally {
                    btn.disabled = false;
                    await loadOperatorApplications(false);
                }
            });
        }

        searchEl.addEventListener('input', scheduleReload);
        statusEl.addEventListener('change', () => loadOperatorAccounts(true));
        if (appTypeEl) appTypeEl.addEventListener('change', () => loadOperatorApplications(true));

        window.loadOperatorAccounts = loadOperatorAccounts;
        window.loadOperatorApplications = loadOperatorApplications;
        window.closeOpPwdModal = closeOpPwdModal;
        window.copyOpTempPassword = copyOpTempPassword;

        renderLoading();
        loadOperatorAccounts(true);
        loadOperatorApplications(true);
    })();
</script>

<script>
let ovUsers = [];
let ovActiveUserId = null;
let ovActive = null;

function ovEscape(s) {
  return String(s || '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function ovBadge(status) {
  const s = String(status || '');
  if (s === 'Approved') return 'bg-emerald-100 text-emerald-700';
  if (s === 'Rejected') return 'bg-rose-100 text-rose-700';
  return 'bg-amber-100 text-amber-700';
}

async function ovFetchList() {
  const tbody = document.getElementById('ov-table-body');
  if (!tbody) return;
  tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-12 text-center text-slate-400 font-medium"><i data-lucide="loader-2" class="w-8 h-8 animate-spin mx-auto mb-2"></i>Loading operators...</td></tr>`;
  if (window.lucide) window.lucide.createIcons();

  const qEl = document.getElementById('ov-search');
  const approvalEl = document.getElementById('ov-approval-filter');
  const statusEl = document.getElementById('ov-status-filter');
  const q = qEl ? String(qEl.value || '').trim() : '';
  const approval = approvalEl ? String(approvalEl.value || '').trim() : '';
  const status = statusEl ? String(statusEl.value || '').trim() : '';

  const url = new URL((window.TMM_ROOT_URL || '') + '/admin/api/users/operator_verification.php', window.location.origin);
  if (q) url.searchParams.set('q', q);
  if (approval) url.searchParams.set('approval_status', approval);
  if (status) url.searchParams.set('status', status);
  url.searchParams.set('t', Date.now());

  const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
  const data = await res.json().catch(() => null);
  if (!data || !data.ok) {
    tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-10 text-center text-rose-600 font-bold">Failed to load</td></tr>`;
    return;
  }
  ovUsers = Array.isArray(data.users) ? data.users : [];
  if (!ovUsers.length) {
    tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-12 text-center text-slate-400 font-medium">No operators found.</td></tr>`;
    return;
  }
  tbody.innerHTML = ovUsers.map(u => {
    const docs = `${u.docs_valid || 0} valid • ${u.docs_pending || 0} pending • ${u.docs_invalid || 0} invalid`;
    return `
      <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
        <td class="px-6 py-4">
          <div class="font-black text-slate-900 dark:text-white">${ovEscape(u.full_name || '')}</div>
          <div class="text-xs text-slate-500">${ovEscape(u.email || '')}</div>
        </td>
        <td class="px-6 py-4">
          <div class="text-sm font-bold text-slate-800 dark:text-slate-200">${ovEscape(u.operator_type || 'Individual')}</div>
        </td>
        <td class="px-6 py-4">
          <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-black ${ovBadge(u.approval_status)}">${ovEscape(u.approval_status || 'Pending')}</span>
        </td>
        <td class="px-6 py-4">
          <div class="text-xs font-semibold text-slate-600 dark:text-slate-300">${ovEscape(docs)}</div>
        </td>
        <td class="px-6 py-4 text-right">
          <button onclick="ovOpenModal(${u.id})" class="text-indigo-600 hover:text-indigo-900 font-black text-sm">Review</button>
        </td>
      </tr>
    `;
  }).join('');
  if (window.lucide) window.lucide.createIcons();
}

async function ovOpenModal(userId) {
  ovActiveUserId = userId;
  const modal = document.getElementById('ov-modal');
  const docs = document.getElementById('ov-docs');
  const sub = document.getElementById('ov-modal-sub');
  const remarks = document.getElementById('ov-remarks');
  if (!modal || !docs || !sub) return;
  docs.innerHTML = 'Loading...';
  sub.textContent = 'Loading...';
  if (remarks) remarks.value = '';
  modal.classList.remove('hidden');
  if (window.lucide) window.lucide.createIcons();

  const url = new URL((window.TMM_ROOT_URL || '') + '/admin/api/users/operator_verification.php', window.location.origin);
  url.searchParams.set('user_id', String(userId));
  url.searchParams.set('t', Date.now());
  const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
  const data = await res.json().catch(() => null);
  if (!data || !data.ok) {
    docs.innerHTML = '<div class="text-rose-600 font-bold">Failed to load.</div>';
    return;
  }
  ovActive = data;
  const u = data.user || {};
  const titleEl = document.getElementById('ov-modal-title');
  if (titleEl) titleEl.textContent = (u.full_name || 'Operator') + ' • Verification';
  sub.textContent = u.email || '';
  const approvalEl = document.getElementById('ov-approval');
  const typeEl = document.getElementById('ov-type');
  const subEl = document.getElementById('ov-submitted');
  if (approvalEl) approvalEl.textContent = u.approval_status || 'Pending';
  if (typeEl) typeEl.textContent = u.operator_type || 'Individual';
  if (subEl) subEl.textContent = u.verification_submitted_at ? String(u.verification_submitted_at).slice(0, 16) : '-';
  if (remarks) remarks.value = u.approval_remarks || '';

  const required = Array.isArray(data.required_doc_keys) ? data.required_doc_keys : [];
  const list = Array.isArray(data.documents) ? data.documents : [];
  const root = (window.TMM_ROOT_URL || '');
  const map = {};
  list.forEach(d => { map[String(d.doc_key || '')] = d; });
  docs.innerHTML = required.map(k => {
    const d = map[k] || null;
    const st = d ? (d.status || 'Pending') : 'Missing';
    const badge = st === 'Valid' ? 'bg-emerald-100 text-emerald-700' : (st === 'Invalid' ? 'bg-rose-100 text-rose-700' : (st === 'Pending' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600'));
    const href = d && d.file_path ? (root + '/citizen/operator/' + String(d.file_path).replace(/^\/+/, '')) : '';
    const remarksLine = (d && d.remarks) ? ('<div class="mt-1 text-xs text-rose-700 font-semibold">' + ovEscape(d.remarks) + '</div>') : '';
    return `
      <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/30">
        <div class="flex items-start justify-between gap-3">
          <div>
            <div class="text-sm font-black text-slate-900 dark:text-white">${ovEscape(k)}</div>
            ${href ? `<a class="text-xs font-bold text-indigo-600 hover:underline" href="${ovEscape(href)}" target="_blank" rel="noopener">Open file</a>` : `<div class="text-xs text-slate-400 font-semibold">No upload</div>`}
            ${remarksLine}
          </div>
          <span class="px-2 py-1 rounded-full text-[10px] font-black ${badge}">${ovEscape(st)}</span>
        </div>
        <div class="mt-3 flex flex-col sm:flex-row gap-2">
          <button type="button" class="px-3 py-2 rounded-xl bg-white border border-slate-200 text-slate-700 text-xs font-black hover:bg-slate-100" onclick="ovSetDoc('${ovEscape(k)}','Valid')">Mark Valid</button>
          <button type="button" class="px-3 py-2 rounded-xl bg-white border border-slate-200 text-slate-700 text-xs font-black hover:bg-slate-100" onclick="ovSetDoc('${ovEscape(k)}','Invalid')">Mark Invalid</button>
          <button type="button" class="px-3 py-2 rounded-xl bg-white border border-slate-200 text-slate-700 text-xs font-black hover:bg-slate-100" onclick="ovSetDoc('${ovEscape(k)}','Pending')">Reset Pending</button>
        </div>
      </div>
    `;
  }).join('');
  if (window.lucide) window.lucide.createIcons();
}

function ovCloseModal() {
  const modal = document.getElementById('ov-modal');
  if (!modal) return;
  modal.classList.add('hidden');
  ovActiveUserId = null;
  ovActive = null;
}

async function ovApiPost(payload) {
  const res = await fetch((window.TMM_ROOT_URL || '') + '/admin/api/users/operator_verification.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    body: JSON.stringify(payload)
  });
  return await res.json().catch(() => null);
}

async function ovSetDoc(docKey, status) {
  if (!ovActiveUserId) return;
  const remarksEl = document.getElementById('ov-remarks');
  const remarks = remarksEl ? String(remarksEl.value || '').trim() : '';
  const data = await ovApiPost({ action: 'review_document', user_id: ovActiveUserId, doc_key: docKey, status, remarks });
  if (!data || !data.ok) {
    alert((data && data.error) ? data.error : 'Failed');
    return;
  }
  await ovOpenModal(ovActiveUserId);
  ovFetchList();
}

async function ovApprove() {
  if (!ovActiveUserId) return;
  const remarksEl = document.getElementById('ov-remarks');
  const remarks = remarksEl ? String(remarksEl.value || '').trim() : '';
  const data = await ovApiPost({ action: 'set_approval', user_id: ovActiveUserId, approval_status: 'Approved', remarks });
  if (!data || !data.ok) {
    alert((data && data.error) ? data.error : 'Failed to approve');
    return;
  }
  const opId = data.operator_id ? parseInt(String(data.operator_id), 10) : 0;
  if (opId > 0) {
    window.location.href = '?page=puv-database/operator-encoding&highlight_operator_id=' + encodeURIComponent(opId);
    return;
  }
  alert('Approved.');
  await ovOpenModal(ovActiveUserId);
  ovFetchList();
}

async function ovReject() {
  if (!ovActiveUserId) return;
  const remarksEl = document.getElementById('ov-remarks');
  const remarks = remarksEl ? String(remarksEl.value || '').trim() : '';
  const data = await ovApiPost({ action: 'set_approval', user_id: ovActiveUserId, approval_status: 'Rejected', remarks });
  if (!data || !data.ok) {
    alert((data && data.error) ? data.error : 'Failed to reject');
    return;
  }
  alert('Rejected.');
  await ovOpenModal(ovActiveUserId);
  ovFetchList();
}

document.addEventListener('DOMContentLoaded', () => {
  if (window.lucide) window.lucide.createIcons();
  ovFetchList().catch(() => {});

  const searchEl = document.getElementById('ov-search');
  const approvalEl = document.getElementById('ov-approval-filter');
  const statusEl = document.getElementById('ov-status-filter');

  let t = null;
  if (searchEl) {
    searchEl.addEventListener('input', () => {
      clearTimeout(t);
      t = setTimeout(() => ovFetchList(), 250);
    });
  }
  if (approvalEl) approvalEl.addEventListener('change', () => ovFetchList());
  if (statusEl) statusEl.addEventListener('change', () => ovFetchList());
});
</script>
