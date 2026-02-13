(function () {
  function debounce(fn, wait) {
    var t = 0;
    return function () {
      var ctx = this;
      var args = arguments;
      if (t) clearTimeout(t);
      t = setTimeout(function () { fn.apply(ctx, args); }, wait);
    };
  }

  function isGetForm(form) {
    var method = (form.getAttribute('method') || 'GET').toUpperCase();
    return method === 'GET';
  }

  function hasPageParam(form) {
    return !!form.querySelector('input[name="page"]');
  }

  function isSafe(form) {
    if (!form || form.nodeType !== 1) return false;
    if (form.getAttribute('data-tmm-no-auto-filter') === '1') return false;
    if (!isGetForm(form)) return false;
    if (!hasPageParam(form)) return false;
    if (form.querySelector('input[type="file"]')) return false;
    return true;
  }

  function hideSubmitControls(form) {
    var btns = form.querySelectorAll('button[type="submit"], input[type="submit"]');
    for (var i = 0; i < btns.length; i++) {
      var b = btns[i];
      if (b.getAttribute('data-tmm-keep-submit') === '1') continue;
      b.classList.add('hidden');
      b.setAttribute('aria-hidden', 'true');
      b.tabIndex = -1;
    }

    var maybe = form.querySelectorAll('button:not([data-tmm-keep-submit="1"])');
    for (var j = 0; j < maybe.length; j++) {
      var x = maybe[j];
      if (!x) continue;
      var label = (x.textContent || '').toString().replace(/\s+/g, ' ').trim().toLowerCase();
      if (label === 'apply' || label === 'search' || label === 'filter' || label === 'apply filters' || label === 'search now' || label === 'filter now') {
        x.classList.add('hidden');
        x.setAttribute('aria-hidden', 'true');
        x.tabIndex = -1;
      }
    }
  }

  function trySubmit(form) {
    try {
      try { sessionStorage.setItem('tmm_scroll_y', String(window.scrollY || 0)); } catch (e0) { }
      if (typeof form.requestSubmit === 'function') {
        form.requestSubmit();
      } else {
        form.submit();
      }
    } catch (e) {
      try { form.submit(); } catch (e2) { }
    }
  }

  function attach(form) {
    if (form.getAttribute('data-tmm-auto-filter-ready') === '1') return;
    form.setAttribute('data-tmm-auto-filter-ready', '1');
    hideSubmitControls(form);

    var debounced = debounce(function () { trySubmit(form); }, 350);
    var controls = form.querySelectorAll('input, select, textarea');
    for (var i = 0; i < controls.length; i++) {
      var el = controls[i];
      if (!el || el.disabled) continue;
      var name = (el.getAttribute('name') || '').toString();
      if (name === '' || name === 'page') continue;
      if ((el.getAttribute('type') || '').toLowerCase() === 'hidden') continue;
      if (el.getAttribute('data-tmm-no-auto-filter') === '1') continue;

      var tag = (el.tagName || '').toUpperCase();
      var type = (el.getAttribute('type') || '').toLowerCase();
      if (tag === 'SELECT' || type === 'checkbox' || type === 'radio' || type === 'date' || type === 'datetime-local') {
        el.addEventListener('change', function () { trySubmit(form); });
      } else {
        el.addEventListener('input', debounced);
        el.addEventListener('keydown', function (ev) {
          if (!ev) return;
          if (ev.key === 'Enter') {
            ev.preventDefault();
            trySubmit(form);
          }
        });
      }
    }
  }

  function init() {
    try {
      var y = sessionStorage.getItem('tmm_scroll_y');
      if (y !== null && y !== '') {
        sessionStorage.removeItem('tmm_scroll_y');
        var ny = Number(y);
        if (!isNaN(ny) && ny > 0) {
          requestAnimationFrame(function () { window.scrollTo(0, ny); });
        }
      }
    } catch (e) { }
    var forms = document.querySelectorAll('form');
    for (var i = 0; i < forms.length; i++) {
      var f = forms[i];
      if (!isSafe(f)) continue;
      attach(f);
    }
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
