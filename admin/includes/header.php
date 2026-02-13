<?php
$crumbText = implode(' > ', $breadcrumb ?? ['Dashboard']);
?>
<div class="bg-white/80 backdrop-blur-xl border-b border-slate-200 px-6 py-4 dark:bg-slate-800 dark:border-slate-700/50">
  <div class="flex items-center justify-between">
    <div class="flex items-center space-x-4">
      <button class="p-2.5 rounded-xl text-slate-600 hover:bg-gradient-to-br hover:from-blue-50 hover:to-blue-100 dark:text-slate-300 dark:hover:from-slate-700 dark:hover:to-slate-600 transition-all duration-200 hover:scale-105" onclick="toggleSidebar()">
        <i data-lucide="menu" class="w-6 h-6"></i>
      </button>
      <div>
        <div class="hidden md:flex items-center space-x-1">
          <h1 class="text-md font-bold bg-gradient-to-r from-blue-600 to-green-600 bg-clip-text text-transparent" style="background: linear-gradient(to right, #4a90e2, #66bb6a); background-clip: text; -webkit-background-clip: text; -webkit-text-fill-color: transparent;">TRANSPORT & MOBILITY MANAGEMENT</h1>
        </div>
        <div>
          <span class="text-xs text-slate-500 dark:text-slate-400 font-bold"><?php echo htmlspecialchars($crumbText); ?></span>
        </div>
      </div>
    </div>
    <div class="flex items-center space-x-1">
      <div class="relative">
        <button id="adminNotifBtn" class="relative rounded-xl p-2.5 text-slate-600 dark:text-slate-400 dark:hover:text-slate-100 hover:bg-gradient-to-br hover:from-blue-50 hover:to-blue-100 dark:hover:from-slate-700 dark:hover:to-slate-600 transition-all duration-200 hover:scale-105">
          <i data-lucide="bell" class="w-6 h-6"></i>
          <span id="adminNotifBadge" class="hidden absolute top-1 right-1 min-w-4 h-4 px-1 text-white text-xs bg-gradient-to-br from-red-500 to-red-600 rounded-full flex items-center justify-center shadow-lg"></span>
        </button>
        <div id="adminNotifPanel" class="hidden absolute right-0 mt-2 w-80 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-xl overflow-hidden z-[300]">
          <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-700">
            <div class="text-sm font-black text-slate-900 dark:text-white">Notifications</div>
            <div id="adminNotifSub" class="mt-0.5 text-xs font-semibold text-slate-500 dark:text-slate-400">Loading…</div>
          </div>
          <div id="adminNotifList" class="p-2"></div>
        </div>
      </div>
      <button class="rounded-xl p-2.5 text-slate-600 hover:bg-gradient-to-br hover:from-amber-50 hover:to-amber-100 dark:text-yellow-400 dark:hover:from-slate-700 dark:hover:to-slate-600 transition-all duration-200 hover:scale-105" onclick="toggleTheme()" aria-label="Toggle dark mode">
        <span id="themeState" class="sr-only">light</span>
        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 hidden dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor"><circle cx="12" cy="12" r="5" stroke="currentColor" stroke-width="2" fill="none"/><path stroke="currentColor" stroke-width="2" d="M12 1v2m0 18v2m11-11h-2M3 12H1m16.95 7.07l-1.41-1.41M6.46 6.46L5.05 5.05m13.9 0l-1.41 1.41M6.46 17.54l-1.41 1.41"/></svg>
        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke="currentColor" stroke-width="2" d="M21 12.79A9 9 0 1111.21 3a7 7 0 109.79 9.79z"/></svg>
      </button>
    </div>
  </div>
</div>

<script>
  (function () {
    const btn = document.getElementById('adminNotifBtn');
    const panel = document.getElementById('adminNotifPanel');
    const badge = document.getElementById('adminNotifBadge');
    const sub = document.getElementById('adminNotifSub');
    const list = document.getElementById('adminNotifList');
    if (!btn || !panel || !badge || !sub || !list) return;

    const baseUrl = (() => {
      try {
        const p = String(window.location.pathname || '');
        const idx = p.indexOf('/admin/');
        if (idx >= 0) return p.slice(0, idx);
      } catch (e) {}
      return '';
    })();

    let loaded = false;
    let open = false;

    function setOpen(v) {
      open = !!v;
      if (open) panel.classList.remove('hidden');
      else panel.classList.add('hidden');
    }

    async function loadSummary() {
      try {
        const res = await fetch(baseUrl + '/admin/api/notifications_summary.php', { credentials: 'same-origin' });
        const data = await res.json().catch(() => null);
        if (!data || !data.ok) throw new Error('load_failed');
        const total = Number(data.total || 0) || 0;
        if (total > 0) {
          badge.textContent = String(total);
          badge.classList.remove('hidden');
        } else {
          badge.textContent = '';
          badge.classList.add('hidden');
        }
        const items = Array.isArray(data.items) ? data.items : [];
        sub.textContent = total > 0 ? (String(total) + ' pending item(s)') : 'No new alerts';
        const iconByKey = {
          scheduled_inspections: 'calendar',
          overdue_inspections: 'alert-triangle',
          expired_docs: 'file-warning',
        };
        const rows = items.map((it) => {
          const key = String(it.key || '');
          const label = String(it.label || '');
          const href = String(it.href || '#');
          const count = Number(it.count || 0) || 0;
          const icon = iconByKey[key] || 'bell';
          const badgeHtml = count > 0
            ? `<span class="ml-auto inline-flex items-center justify-center min-w-6 h-6 px-2 rounded-full bg-slate-900 text-white text-xs font-black dark:bg-slate-700">${count}</span>`
            : '';
          return `
            <a href="${href}" class="flex items-center gap-3 rounded-xl px-3 py-2 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
              <span class="w-9 h-9 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-center">
                <i data-lucide="${icon}" class="w-4 h-4 text-slate-500 dark:text-slate-300"></i>
              </span>
              <div class="min-w-0">
                <div class="text-sm font-black text-slate-900 dark:text-white truncate">${label}</div>
              </div>
              ${badgeHtml}
            </a>
          `;
        }).join('');
        list.innerHTML = rows || `<div class="px-3 py-4 text-sm font-semibold text-slate-500 dark:text-slate-400 italic">No notifications.</div>`;
        if (window.lucide) window.lucide.createIcons();
        loaded = true;
      } catch (e) {
        sub.textContent = 'Failed to load';
        list.innerHTML = `<div class="px-3 py-4 text-sm font-semibold text-rose-600 dark:text-rose-400">Failed to load notifications.</div>`;
      }
    }

    btn.addEventListener('click', async (e) => {
      e.preventDefault();
      e.stopPropagation();
      setOpen(!open);
      if (!loaded) await loadSummary();
    });

    document.addEventListener('click', (e) => {
      if (!open) return;
      const t = e && e.target ? e.target : null;
      if (!t) return;
      if (t === panel || panel.contains(t) || t === btn || btn.contains(t)) return;
      setOpen(false);
    });

    document.addEventListener('keydown', (e) => {
      if (!open) return;
      if (e && e.key === 'Escape') setOpen(false);
    });

    loadSummary();
  })();
</script>
