<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['SuperAdmin']);
?>

<div class="mx-auto max-w-7xl px-4 py-8 space-y-8">
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <h1 class="text-3xl font-black text-slate-800 dark:text-white flex items-center gap-3">
        <div class="p-3 bg-indigo-500/10 rounded-2xl">
          <i data-lucide="badge-check" class="w-8 h-8 text-indigo-500"></i>
        </div>
        Operator Verification
      </h1>
      <p class="mt-2 text-slate-500 dark:text-slate-400 font-medium ml-14">Review uploaded documents and approve operator accounts.</p>
    </div>
  </div>

  <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 p-4 flex flex-col sm:flex-row gap-4">
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
      <option value="Active" selected>Active</option>
      <option value="Inactive">Inactive</option>
      <option value="Locked">Locked</option>
    </select>
  </div>

  <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
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
  tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-12 text-center text-slate-400 font-medium"><i data-lucide="loader-2" class="w-8 h-8 animate-spin mx-auto mb-2"></i>Loading operators...</td></tr>`;
  if (window.lucide) window.lucide.createIcons();

  const q = String(document.getElementById('ov-search').value || '').trim();
  const approval = String(document.getElementById('ov-approval-filter').value || '').trim();
  const status = String(document.getElementById('ov-status-filter').value || '').trim();

  const url = new URL((window.TMM_ROOT_URL || '') + '/admin/api/users/operator_verification.php', window.location.origin);
  if (q) url.searchParams.set('q', q);
  if (approval) url.searchParams.set('approval_status', approval);
  if (status) url.searchParams.set('status', status);
  url.searchParams.set('t', Date.now());

  const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
  const data = await res.json();
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
  docs.innerHTML = 'Loading...';
  sub.textContent = 'Loading...';
  if (remarks) remarks.value = '';
  modal.classList.remove('hidden');
  if (window.lucide) window.lucide.createIcons();

  const url = new URL((window.TMM_ROOT_URL || '') + '/admin/api/users/operator_verification.php', window.location.origin);
  url.searchParams.set('user_id', String(userId));
  url.searchParams.set('t', Date.now());
  const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
  const data = await res.json();
  if (!data || !data.ok) {
    docs.innerHTML = '<div class="text-rose-600 font-bold">Failed to load.</div>';
    return;
  }
  ovActive = data;
  const u = data.user || {};
  document.getElementById('ov-modal-title').textContent = (u.full_name || 'Operator') + ' • Verification';
  sub.textContent = u.email || '';
  document.getElementById('ov-approval').textContent = u.approval_status || 'Pending';
  document.getElementById('ov-type').textContent = u.operator_type || 'Individual';
  document.getElementById('ov-submitted').textContent = u.verification_submitted_at ? String(u.verification_submitted_at).slice(0, 16) : '-';
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
  return await res.json();
}

async function ovSetDoc(docKey, status) {
  if (!ovActiveUserId) return;
  const remarks = String(document.getElementById('ov-remarks').value || '').trim();
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
  const remarks = String(document.getElementById('ov-remarks').value || '').trim();
  const data = await ovApiPost({ action: 'set_approval', user_id: ovActiveUserId, approval_status: 'Approved', remarks });
  if (!data || !data.ok) {
    alert((data && data.error) ? data.error : 'Failed to approve');
    return;
  }
  alert('Approved.');
  await ovOpenModal(ovActiveUserId);
  ovFetchList();
}

async function ovReject() {
  if (!ovActiveUserId) return;
  const remarks = String(document.getElementById('ov-remarks').value || '').trim();
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
  ovFetchList();

  let t = null;
  document.getElementById('ov-search').addEventListener('input', () => {
    clearTimeout(t);
    t = setTimeout(() => ovFetchList(), 250);
  });
  document.getElementById('ov-approval-filter').addEventListener('change', () => ovFetchList());
  document.getElementById('ov-status-filter').addEventListener('change', () => ovFetchList());
});
</script>

