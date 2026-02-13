(function () {
  if (window.TMMFormEnhancements) return;

  var STYLE_ID = 'tmm-form-enhancements-style';

  function ensureStyle() {
    if (document.getElementById(STYLE_ID)) return;
    var style = document.createElement('style');
    style.id = STYLE_ID;
    style.textContent = [
      '.tmm-inline-error{margin-top:4px;font-size:12px;line-height:1.25;color:#e11d48;font-weight:600}',
      '.tmm-inline-error[hidden]{display:none}',
      '.tmm-invalid{outline:2px solid rgba(225,29,72,.45);outline-offset:2px;border-color:#fb7185 !important}',
      '.tmm-file-clear{margin-left:8px;display:none;align-items:center;justify-content:center;width:34px;height:34px;border-radius:10px;border:1px solid rgba(148,163,184,.7);background:#fff;color:#64748b;font-weight:900;line-height:1;cursor:pointer;user-select:none}',
      '.tmm-file-clear:hover{background:#f1f5f9;color:#0f172a}',
      '.tmm-file-clear:focus{outline:2px solid rgba(59,130,246,.45);outline-offset:2px}',
      '.dark .tmm-file-clear{background:rgba(15,23,42,.55);border-color:rgba(100,116,139,.8);color:rgba(203,213,225,1)}',
      '.dark .tmm-file-clear:hover{background:rgba(30,41,59,.8);color:#fff}'
    ].join('');
    document.head.appendChild(style);
  }

  function escapeAttrValue(value) {
    if (window.CSS && typeof window.CSS.escape === 'function') return window.CSS.escape(value);
    return String(value).replace(/"/g, '\\"');
  }

  function fieldKey(el) {
    return el.getAttribute('name') || el.id || '';
  }

  function getOrCreateErrorEl(el) {
    ensureStyle();
    var key = fieldKey(el);
    if (!key) return null;
    var parent = el.parentElement || el;
    var selector = '.tmm-inline-error[data-for="' + escapeAttrValue(key) + '"]';
    var err = parent.querySelector(selector);
    if (!err) {
      err = document.createElement('div');
      err.className = 'tmm-inline-error';
      err.setAttribute('data-for', key);
      err.hidden = true;
      el.insertAdjacentElement('afterend', err);
    }
    return err;
  }

  function normalizePlate(value) {
    var v = (value || '').toString().toUpperCase().replace(/\s+/g, '');
    v = v.replace(/[^A-Z0-9-]/g, '');
    v = v.replace(/-+/g, '-');
    if (v.indexOf('-') !== -1) return v;
    var mStd = v.match(/^([A-Z]{1,3})(\d{3,4})$/);
    if (mStd) return mStd[1] + '-' + mStd[2];
    var mAny = v.match(/^([A-Z0-9]+)(\d{3,4})$/);
    if (mAny) return mAny[1] + '-' + mAny[2];
    return v;
  }

  function normalizePlateAny(value) {
    var v = (value || '').toString().toUpperCase().replace(/\s+/g, '');
    v = v.replace(/[^A-Z0-9-]/g, '');
    v = v.replace(/-+/g, '-');
    if (v.indexOf('-') !== -1) return v;
    var m4 = v.match(/^([A-Z0-9]+)(\d{4})$/);
    if (m4) return m4[1] + '-' + m4[2];
    var m3 = v.match(/^([A-Z0-9]+)(\d{3})$/);
    if (m3) return m3[1] + '-' + m3[2];
    return v;
  }

  function inferUppercase(el) {
    if (el.dataset.tmmUppercase === '1') return true;
    if (el.getAttribute('autocapitalize') === 'characters') return true;
    if (el.classList && el.classList.contains('uppercase')) return true;
    return false;
  }

  function inferMask(el) {
    if (el.dataset.tmmMask) return el.dataset.tmmMask;
    var name = (el.getAttribute('name') || '').toLowerCase();
    var id = (el.id || '').toLowerCase();
    var placeholder = el.getAttribute('placeholder') || '';
    var pattern = el.getAttribute('pattern') || '';
    var looksLikePlate = (name.indexOf('plate') !== -1) || (id.indexOf('plate') !== -1);
    if (looksLikePlate && (placeholder.indexOf('-') !== -1 || pattern.indexOf('\\-') !== -1)) return 'plate';
    return '';
  }

  function inferFilter(el) {
    if (el.dataset.tmmFilter) {
      var forced = String(el.dataset.tmmFilter || '').toLowerCase();
      if (forced === 'phone' || forced === 'tel' || forced === 'phoneish') return 'phoneish';
      if (forced === 'engine' || forced === 'alnumdash') return 'alnumdash';
      if (forced === 'vin' || forced === 'chassis') return 'vin';
      if (forced === 'digits') return 'digits';
      if (forced === 'digits-dash') return 'digits-dash';
    }

    var type = (el.getAttribute('type') || '').toLowerCase();
    var name = (el.getAttribute('name') || '').toLowerCase();
    if (type === 'tel' || name.indexOf('contact') !== -1 || name.indexOf('phone') !== -1 || name.indexOf('mobile') !== -1) {
      return 'phoneish';
    }

    var pattern = el.getAttribute('pattern') || '';
    if (!pattern) return '';
    var hasLetters = /[a-z]/i.test(pattern);
    if (hasLetters) return '';
    if (pattern.indexOf('0-9') !== -1 && (pattern.indexOf('\\s') !== -1 || pattern.indexOf('+') !== -1 || pattern.indexOf('(') !== -1 || pattern.indexOf('\\-') !== -1)) {
      return 'phoneish';
    }
    if (pattern.indexOf('0-9') !== -1 && pattern.indexOf('\\-') !== -1 && pattern.indexOf('\\s') === -1) return 'digits-dash';
    if (pattern.indexOf('0-9') !== -1 && pattern.indexOf('\\s') === -1 && pattern.indexOf('\\-') === -1 && pattern.indexOf('+') === -1) return 'digits';
    return '';
  }

  function capToMaxLength(el, insertedText) {
    var max = Number(el.getAttribute('maxlength') || 0);
    if (!(max > 0)) return false;

    var start = typeof el.selectionStart === 'number' ? el.selectionStart : null;
    var end = typeof el.selectionEnd === 'number' ? el.selectionEnd : null;
    if (start === null || end === null) return false;

    var existingLen = (el.value || '').length;
    var selectedLen = Math.max(0, end - start);
    var nextLenBase = existingLen - selectedLen;
    var remaining = max - nextLenBase;

    if (remaining <= 0) return true;
    if (typeof insertedText !== 'string') return false;
    if (insertedText.length <= remaining) return false;

    var truncated = insertedText.slice(0, remaining);
    try {
      el.setRangeText(truncated, start, end, 'end');
      applyInputTransform(el);
      setFieldValidityUI(el, false);
      return true;
    } catch (_) {
      return false;
    }
  }

  function inferNumericOnly(el) {
    if (el.dataset.tmmNumericOnly === '1') return true;
    var type = (el.getAttribute('type') || '').toLowerCase();
    var inputmode = (el.getAttribute('inputmode') || '').toLowerCase();
    if (inputmode === 'numeric') return true;
    return false;
  }

  function applyInputTransform(el) {
    if (!el || typeof el.value !== 'string') return;

    var raw = el.value;
    var v = raw;

    var mask = inferMask(el);
    if (mask === 'plate') {
      v = normalizePlate(v);
    } else if (mask === 'plate_any') {
      v = normalizePlateAny(v);
    }

    var filter = inferFilter(el);
    if (filter === 'phoneish') {
      v = v.replace(/[^0-9+()\-\s]/g, '');
    } else if (filter === 'alnumdash') {
      v = v.toUpperCase().replace(/[^A-Z0-9-]/g, '');
      v = v.replace(/-+/g, '-');
    } else if (filter === 'vin') {
      v = v.toUpperCase().replace(/[^A-Z0-9]/g, '');
      v = v.replace(/[IOQ]/g, '');
    } else if (filter === 'digits-dash') {
      v = v.replace(/[^0-9-]/g, '');
      v = v.replace(/-+/g, '-');
    } else if (filter === 'digits') {
      v = v.replace(/[^0-9]/g, '');
    }

    if (inferNumericOnly(el) && mask !== 'plate') {
      v = v.replace(/[^0-9]/g, '');
    }

    if (inferUppercase(el)) {
      v = v.toUpperCase();
    }

    var max = Number(el.getAttribute('maxlength') || 0);
    if (max > 0 && v.length > max) v = v.slice(0, max);

    if (v !== raw) el.value = v;
  }

  function setFieldValidityUI(el, forceShow) {
    if (!el || !el.willValidate) return;
    var show = forceShow || el.dataset.tmmTouched === '1';
    var err = getOrCreateErrorEl(el);
    if (!err) return;

    function normalizePattern(p) {
      if (!p) return '';
      var s = String(p);
      if (s.charAt(0) === '^') s = s.slice(1);
      if (s.charAt(s.length - 1) === '$') s = s.slice(0, -1);
      return s;
    }

    function safeCheckValidity(field) {
      try {
        return field.checkValidity();
      } catch (e) {
        if (field.disabled) return true;
        var tag = (field.tagName || '').toLowerCase();
        var type = (field.getAttribute('type') || '').toLowerCase();
        var value = (field.value || '').toString();

        if (field.hasAttribute('required')) {
          if (type === 'file') {
            if (!field.files || field.files.length === 0) return false;
          } else if (tag === 'select') {
            if (value.trim() === '') return false;
          } else {
            if (value.trim() === '') return false;
          }
        }

        var minLen = Number(field.getAttribute('minlength') || 0);
        if (minLen > 0 && value.length > 0 && value.length < minLen) return false;

        var maxLen = Number(field.getAttribute('maxlength') || 0);
        if (maxLen > 0 && value.length > maxLen) return false;

        var pattern = field.getAttribute('pattern');
        if (pattern && value !== '') {
          try {
            var re = new RegExp('^(?:' + normalizePattern(pattern) + ')$');
            if (!re.test(value)) return false;
          } catch (_) {
            return false;
          }
        }

        return true;
      }
    }

    function safeValidationMessage(field) {
      try {
        return field.validationMessage || 'Invalid value';
      } catch (e) {
        if (field.hasAttribute('required')) return 'This field is required.';
        return 'Invalid input format';
      }
    }

    var isValid = false;
    try {
      isValid = safeCheckValidity(el);
    } catch (e) {
      if (!show) {
        err.hidden = true;
        err.textContent = '';
        el.classList.remove('tmm-invalid');
        el.removeAttribute('aria-invalid');
        return;
      }
      err.textContent = 'Invalid input format';
      err.hidden = false;
      el.classList.add('tmm-invalid');
      el.setAttribute('aria-invalid', 'true');
      return;
    }

    if (isValid) {
      err.hidden = true;
      err.textContent = '';
      el.classList.remove('tmm-invalid');
      el.removeAttribute('aria-invalid');
      return;
    }

    if (!show) {
      err.hidden = true;
      err.textContent = '';
      el.classList.remove('tmm-invalid');
      el.removeAttribute('aria-invalid');
      return;
    }

    err.textContent = safeValidationMessage(el);
    err.hidden = false;
    el.classList.add('tmm-invalid');
    el.setAttribute('aria-invalid', 'true');
  }

  function enhanceField(el) {
    if (!el || el.dataset.tmmEnhanced === '1') return;
    el.dataset.tmmEnhanced = '1';

    if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
      el.addEventListener('beforeinput', function (e) {
        if (!e || e.defaultPrevented) return;
        var t = String(e.inputType || '');
        if (t.indexOf('insert') !== 0) return;
        var inserted = typeof e.data === 'string' ? e.data : null;
        if (capToMaxLength(el, inserted)) e.preventDefault();
      });
      el.addEventListener('paste', function (e) {
        if (!e || e.defaultPrevented) return;
        var clip = e.clipboardData;
        if (!clip || typeof clip.getData !== 'function') return;
        var text = clip.getData('text');
        if (typeof text !== 'string' || !text) return;
        if (capToMaxLength(el, text)) e.preventDefault();
      });
      el.addEventListener('input', function () {
        el.dataset.tmmTouched = '1';
        applyInputTransform(el);
        setFieldValidityUI(el, false);
      });
      el.addEventListener('blur', function () {
        el.dataset.tmmTouched = '1';
        applyInputTransform(el);
        setFieldValidityUI(el, true);
      });
      el.addEventListener('change', function () {
        el.dataset.tmmTouched = '1';
        applyInputTransform(el);
        setFieldValidityUI(el, true);
      });
      applyInputTransform(el);
      setFieldValidityUI(el, false);
      return;
    }

    if (el.tagName === 'SELECT') {
      el.addEventListener('change', function () {
        el.dataset.tmmTouched = '1';
        setFieldValidityUI(el, true);
      });
      setFieldValidityUI(el, false);
    }
  }

  function enhanceForm(form) {
    if (!form || form.dataset.tmmFormEnhanced === '1') return;
    form.dataset.tmmFormEnhanced = '1';

    var fields = Array.prototype.slice.call(form.querySelectorAll('input,select,textarea'));
    fields.forEach(enhanceField);

    form.addEventListener('submit', function (e) {
      var firstInvalid = null;
      fields.forEach(function (f) {
        f.dataset.tmmTouched = '1';
        applyInputTransform(f);
        var ok = true;
        if (f.willValidate) {
          try { ok = f.checkValidity(); } catch (_) { ok = false; }
        }
        if (!firstInvalid && f.willValidate && !ok) firstInvalid = f;
        setFieldValidityUI(f, true);
      });
      if (firstInvalid) {
        e.preventDefault();
        e.stopImmediatePropagation();
        try { firstInvalid.focus({ preventScroll: true }); } catch (_) { try { firstInvalid.focus(); } catch (_) { } }
        try { firstInvalid.scrollIntoView({ block: 'center', behavior: 'smooth' }); } catch (_) { }
      }
    }, true);
  }

  function enhanceFileInput(input) {
    if (!input || input.dataset.tmmFileClearEnhanced === '1') return;
    var type = (input.getAttribute('type') || '').toLowerCase();
    if (type !== 'file') return;
    input.dataset.tmmFileClearEnhanced = '1';

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'tmm-file-clear';
    btn.setAttribute('aria-label', 'Clear selected file');
    btn.textContent = '×';

    input.insertAdjacentElement('afterend', btn);

    function hasSelection() {
      try {
        if (input.disabled) return false;
        if (input.files && input.files.length > 0) return true;
        return (input.value || '').toString().trim() !== '';
      } catch (_) {
        return false;
      }
    }

    function sync() {
      btn.style.display = hasSelection() ? 'inline-flex' : 'none';
    }

    btn.addEventListener('click', function () {
      try { input.value = ''; } catch (_) { }
      sync();
      try { input.dispatchEvent(new Event('change', { bubbles: true })); } catch (_) { }
      try { input.dispatchEvent(new Event('input', { bubbles: true })); } catch (_) { }
      setFieldValidityUI(input, false);
    });

    input.addEventListener('change', sync);
    sync();
  }

  function init(root) {
    ensureStyle();
    var node = root || document;
    if (node.tagName === 'FORM') {
      enhanceForm(node);
      Array.prototype.slice.call(node.querySelectorAll ? node.querySelectorAll('input[type="file"]') : []).forEach(enhanceFileInput);
      return;
    }
    Array.prototype.slice.call(node.querySelectorAll ? node.querySelectorAll('form') : []).forEach(enhanceForm);
    Array.prototype.slice.call(node.querySelectorAll ? node.querySelectorAll('input,select,textarea') : []).forEach(enhanceField);
    Array.prototype.slice.call(node.querySelectorAll ? node.querySelectorAll('input[type="file"]') : []).forEach(enhanceFileInput);
  }

  function startObserver() {
    if (!document.body || typeof MutationObserver === 'undefined') return;
    var obs = new MutationObserver(function (mutations) {
      mutations.forEach(function (m) {
        Array.prototype.slice.call(m.addedNodes || []).forEach(function (n) {
          if (!n || n.nodeType !== 1) return;
          init(n);
        });
      });
    });
    obs.observe(document.body, { childList: true, subtree: true });
  }

  function validateForm(form) {
    if (!form) return false;
    init(form);
    var fields = Array.prototype.slice.call(form.querySelectorAll('input,select,textarea'));
    var firstInvalid = null;
    fields.forEach(function (f) {
      f.dataset.tmmTouched = '1';
      applyInputTransform(f);
      var ok = true;
      if (f.willValidate) {
        try { ok = f.checkValidity(); } catch (_) { ok = false; }
      }
      setFieldValidityUI(f, true);
      if (!firstInvalid && f.willValidate && !ok) firstInvalid = f;
    });
    if (firstInvalid) {
      try { firstInvalid.focus({ preventScroll: true }); } catch (_) { try { firstInvalid.focus(); } catch (_) { } }
      try { firstInvalid.scrollIntoView({ block: 'center', behavior: 'smooth' }); } catch (_) { }
      return false;
    }
    return true;
  }

  window.TMMFormEnhancements = { init: init, validateForm: validateForm };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      init(document);
      startObserver();
    });
  } else {
    init(document);
    startObserver();
  }
})();
