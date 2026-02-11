(function () {
  function textOf(el) {
    return (el && el.textContent ? el.textContent : '').toString().toLowerCase();
  }

  function isFilterableTable(table) {
    if (!table || table.nodeType !== 1) return false;
    if (table.getAttribute('data-tmm-no-filter') === '1') return false;
    if (!table.tBodies || !table.tBodies.length) return false;
    if (!table.querySelector('thead')) return false;
    return true;
  }

  function hasNearbySearch(table) {
    var p = table && table.parentElement ? table.parentElement : null;
    for (var depth = 0; depth < 4 && p; depth++) {
      var inputs = p.querySelectorAll ? p.querySelectorAll('input') : [];
      for (var i = 0; i < inputs.length; i++) {
        var el = inputs[i];
        if (!el || el.disabled) continue;
        if (table.contains(el)) continue;
        var type = (el.getAttribute('type') || '').toLowerCase();
        if (type === 'hidden' || type === 'file' || type === 'password') continue;
        var ph = (el.getAttribute('placeholder') || '').toLowerCase();
        var id = (el.getAttribute('id') || '').toLowerCase();
        var name = (el.getAttribute('name') || '').toLowerCase();
        if (type === 'search') return true;
        if (ph.indexOf('search') !== -1) return true;
        if (id.indexOf('search') !== -1) return true;
        if (name === 'q' || name.indexOf('search') !== -1) return true;
      }
      var forms = p.querySelectorAll ? p.querySelectorAll('form') : [];
      for (var j = 0; j < forms.length; j++) {
        var f = forms[j];
        if (!f || table.contains(f)) continue;
        var method = (f.getAttribute('method') || 'GET').toUpperCase();
        if (method === 'GET') return true;
      }
      p = p.parentElement;
    }
    return false;
  }

  function ensureBar(table) {
    if (table.getAttribute('data-tmm-filterbar') === '1') return null;
    table.setAttribute('data-tmm-filterbar', '1');
    if (hasNearbySearch(table)) return null;
    var wrap = table.parentElement;
    if (!wrap) return null;
    var bar = document.createElement('div');
    bar.className = 'mb-3 flex flex-col sm:flex-row sm:items-center gap-2';
    var input = document.createElement('input');
    input.type = 'search';
    input.placeholder = 'Search in table...';
    input.className = 'w-full sm:w-72 px-4 py-2.5 rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700 text-sm font-semibold';
    bar.appendChild(input);
    wrap.insertBefore(bar, table);
    return input;
  }

  function applyFilter(table, q) {
    var query = (q || '').toString().trim().toLowerCase();
    var tbodies = table.tBodies ? Array.prototype.slice.call(table.tBodies) : [];
    tbodies.forEach(function (tbody) {
      var rows = tbody.rows ? Array.prototype.slice.call(tbody.rows) : [];
      rows.forEach(function (row) {
        if (!query) {
          row.classList.remove('hidden');
          return;
        }
        var hay = textOf(row);
        row.classList.toggle('hidden', hay.indexOf(query) === -1);
      });
    });
  }

  function initTable(table) {
    if (!isFilterableTable(table)) return;
    var input = ensureBar(table);
    if (!input) return;
    var handler = function () { applyFilter(table, input.value); };
    input.addEventListener('input', handler);

    var tbody = table.tBodies && table.tBodies[0];
    if (tbody && window.MutationObserver) {
      var obs = new MutationObserver(function () { handler(); });
      obs.observe(tbody, { childList: true, subtree: true });
    }
  }

  function init() {
    var tables = document.querySelectorAll('table');
    for (var i = 0; i < tables.length; i++) initTable(tables[i]);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
