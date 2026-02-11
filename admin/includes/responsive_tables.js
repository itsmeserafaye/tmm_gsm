(function () {
  function textOf(el) {
    return (el && el.textContent ? el.textContent : '').toString().trim();
  }

  function collectHeaders(table) {
    var headers = [];
    var ths = table.querySelectorAll('thead th');
    for (var i = 0; i < ths.length; i++) headers.push(textOf(ths[i]));
    return headers;
  }

  function applyLabels(table) {
    if (!table || table.nodeType !== 1) return;
    if (table.getAttribute('data-tmm-stack-ready') === '1') return;
    var headers = collectHeaders(table);
    if (!headers.length) return;
    table.classList.add('tmm-stack-table');
    var tbodies = table.tBodies ? Array.prototype.slice.call(table.tBodies) : [];
    tbodies.forEach(function (tbody) {
      var rows = tbody.rows ? Array.prototype.slice.call(tbody.rows) : [];
      rows.forEach(function (row) {
        var cells = row.children ? Array.prototype.slice.call(row.children) : [];
        var col = 0;
        cells.forEach(function (cell) {
          var tag = (cell.tagName || '').toUpperCase();
          if (tag !== 'TD' && tag !== 'TH') return;
          var label = headers[col] || '';
          if (label !== '') cell.setAttribute('data-label', label);
          col++;
        });
      });
    });
    table.setAttribute('data-tmm-stack-ready', '1');
  }

  function watchTable(table) {
    var tbody = table && table.tBodies && table.tBodies[0];
    if (!tbody || !window.MutationObserver) return;
    var obs = new MutationObserver(function () {
      table.removeAttribute('data-tmm-stack-ready');
      applyLabels(table);
    });
    obs.observe(tbody, { childList: true, subtree: true });
  }

  function isLikelyDataTable(table) {
    if (!table) return false;
    if (table.closest('[data-tmm-no-stack="1"]')) return false;
    if (table.getAttribute('data-tmm-no-stack') === '1') return false;
    if (table.querySelectorAll('thead th').length < 2) return false;
    return true;
  }

  function init() {
    var tables = document.querySelectorAll('table');
    for (var i = 0; i < tables.length; i++) {
      var t = tables[i];
      if (!isLikelyDataTable(t)) continue;
      applyLabels(t);
      watchTable(t);
    }
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();

