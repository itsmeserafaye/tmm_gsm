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

  function ensureBar(table) {
    if (table.getAttribute('data-tmm-filterbar') === '1') return null;
    table.setAttribute('data-tmm-filterbar', '1');
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

