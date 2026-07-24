/**
 * Gujarati Font Converter — frontend logic (Vanilla JS, no dependencies).
 * Dual format pickers (each box selects "Unicode" or a legacy font), AJAX conversion,
 * auto direction detection, copy/clear, dark mode, demo counters, keyboard shortcuts.
 */
(function () {
  'use strict';

  var $ = function (id) { return document.getElementById(id); };
  var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  var baseUrl = ((document.querySelector('meta[name="base-url"]') || {}).content || '/').replace(/\/$/, '');
  var UNICODE = 'unicode';

  // ---------- Dark mode ----------
  var themeToggle = $('themeToggle');
  if (themeToggle) {
    themeToggle.addEventListener('click', function () {
      var root = document.documentElement;
      var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', next);
      try { localStorage.setItem('gfc_theme', next); } catch (e) {}
    });
  }

  // ---------- Mobile nav ----------
  var hamburger = $('hamburger');
  if (hamburger) {
    hamburger.addEventListener('click', function () { $('mainNav').classList.toggle('open'); });
  }

  // Not the converter page? — skip the rest
  var boxTop = $('boxTop');
  var boxBottom = $('boxBottom');
  if (!boxTop || !boxBottom) return;

  var statusEl = $('convStatus');
  var demoBar = $('demoBar');
  var demoBarText = $('demoBarText');
  var demoState = { unlimited: false, char_limit: 200, attempts_left: null };

  // ---------- Format pickers (each box: searchable dropdown incl. "Unicode") ----------
  // Config for each of the two pickers.
  var pickers = [
    { search: 'searchTop', value: 'fmtTop', dropdown: 'dropdownTop', box: boxTop, counter: 'counterTop' },
    { search: 'searchBottom', value: 'fmtBottom', dropdown: 'dropdownBottom', box: boxBottom, counter: 'counterBottom' }
  ];

  function nameForSlug(dropdownId, slug) {
    var items = $(dropdownId).querySelectorAll('.fd-item');
    for (var i = 0; i < items.length; i++) {
      if (items[i].dataset.slug === slug) return items[i].dataset.name;
    }
    return '';
  }

  pickers.forEach(function (p) {
    var searchEl = $(p.search);
    var valueEl = $(p.value);
    var dropdownEl = $(p.dropdown);

    // Initialise the visible text from the hidden value
    searchEl.value = nameForSlug(p.dropdown, valueEl.value) || 'Unicode (Shruti)';

    searchEl.addEventListener('focus', function () { dropdownEl.classList.add('open'); this.select(); });
    searchEl.addEventListener('input', function () {
      var q = this.value.toLowerCase();
      dropdownEl.classList.add('open');
      dropdownEl.querySelectorAll('.fd-item').forEach(function (item) {
        item.classList.toggle('hidden', q !== '' && item.dataset.name.toLowerCase().indexOf(q) === -1);
      });
    });
    dropdownEl.addEventListener('click', function (e) {
      var item = e.target.closest('.fd-item');
      if (!item) return;
      valueEl.value = item.dataset.slug;
      searchEl.value = item.dataset.name;
      dropdownEl.classList.remove('open');
      updateCounter(p.box, p.counter);
      try { localStorage.setItem('gfc_' + p.value, item.dataset.slug); } catch (e) {}
    });
  });

  // Close any open dropdown when clicking outside
  document.addEventListener('click', function (e) {
    if (!e.target.closest('.fmt-picker')) {
      document.querySelectorAll('.font-dropdown.open').forEach(function (d) { d.classList.remove('open'); });
    }
  });

  // ---------- Character counters ----------
  function updateCounter(box, counterId) {
    var el = $(counterId);
    if (!el) return;
    var len = Array.from(box.value).length; // multibyte-safe
    el.textContent = len + ' characters' + (!demoState.unlimited && demoState.char_limit ? ' / ' + demoState.char_limit : '');
    el.classList.toggle('limit-near', !demoState.unlimited && demoState.char_limit && len >= demoState.char_limit * 0.9);
  }
  boxTop.addEventListener('input', function () { updateCounter(boxTop, 'counterTop'); });
  boxBottom.addEventListener('input', function () { updateCounter(boxBottom, 'counterBottom'); });

  // ---------- Demo status ----------
  function refreshDemoBar() {
    if (demoState.unlimited) { demoBar.hidden = true; return; }
    if (demoState.attempts_left === null) return;
    demoBar.hidden = false;
    demoBarText.textContent = '⚠ DEMO: ' + demoState.char_limit + ' character limit. ' + demoState.attempts_left + ' attempt(s) left.';
  }
  fetch(baseUrl + '/demo-status', { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (json) {
      if (!json.success) return;
      demoState.unlimited = !!json.unlimited;
      if (!json.unlimited) { demoState.char_limit = json.char_limit; demoState.attempts_left = json.attempts_left; }
      refreshDemoBar();
      updateCounter(boxTop, 'counterTop');
      updateCounter(boxBottom, 'counterBottom');
    })
    .catch(function () {});

  // ---------- Status message ----------
  function setStatus(msg, type) {
    statusEl.textContent = msg || '';
    statusEl.className = 'conv-status' + (type ? ' ' + type : '');
  }

  // ---------- History (localStorage, last 5 — text never leaves the browser) ----------
  function saveHistory(input, output, font, direction) {
    try {
      var hist = JSON.parse(localStorage.getItem('gfc_history') || '[]');
      hist.unshift({ i: input.slice(0, 500), o: output.slice(0, 500), d: direction, f: font, t: Date.now() });
      localStorage.setItem('gfc_history', JSON.stringify(hist.slice(0, 5)));
    } catch (e) {}
  }

  // ---------- Conversion (AJAX) ----------
  // fromSide: 'top' or 'bottom'. The other side is the destination.
  function convert(fromSide) {
    var srcBox = fromSide === 'top' ? boxTop : boxBottom;
    var dstBox = fromSide === 'top' ? boxBottom : boxTop;
    var srcFmt = (fromSide === 'top' ? $('fmtTop') : $('fmtBottom')).value;
    var dstFmt = (fromSide === 'top' ? $('fmtBottom') : $('fmtTop')).value;
    var btn = fromSide === 'top' ? $('btnDown') : $('btnUp');
    var text = srcBox.value;

    if (!text.trim()) { setStatus('Please type or paste text first.', 'err'); return; }

    // Decide the legacy font + direction. Exactly one side must be Unicode.
    var font, direction;
    if (srcFmt === UNICODE && dstFmt !== UNICODE) {
      font = dstFmt; direction = 'unicode_to_legacy';
    } else if (srcFmt !== UNICODE && dstFmt === UNICODE) {
      font = srcFmt; direction = 'legacy_to_unicode';
    } else if (srcFmt === UNICODE && dstFmt === UNICODE) {
      setStatus('Both boxes are set to Unicode — set one side to a legacy font.', 'err'); return;
    } else {
      setStatus('Direct font-to-font conversion is not supported. Set one side to Unicode.', 'err'); return;
    }

    var originalLabel = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Converting...';
    setStatus('');

    var body = new URLSearchParams();
    body.append('csrf_token', csrfToken);
    body.append('text', text);
    body.append('font', font);
    body.append('direction', direction);

    fetch(baseUrl + '/convert', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
      body: body.toString()
    })
      .then(function (r) { return r.json(); })
      .then(function (json) {
        if (json.success) {
          dstBox.value = json.data.converted_text;
          updateCounter(dstBox, dstBox === boxTop ? 'counterTop' : 'counterBottom');
          setStatus('✓ Converted ' + json.data.char_count + ' characters (' + json.data.processing_time_ms + 'ms) — ' + json.data.font, 'ok');
          saveHistory(text, json.data.converted_text, font, direction);
          if (json.demo) { demoState.attempts_left = json.demo.attempts_left; demoState.char_limit = json.demo.char_limit; refreshDemoBar(); }
        } else {
          setStatus('✗ ' + (json.error || 'Conversion failed'), 'err');
        }
      })
      .catch(function () { setStatus('✗ Network error — please try again.', 'err'); })
      .finally(function () { btn.disabled = false; btn.innerHTML = originalLabel; });
  }

  $('btnDown').addEventListener('click', function () { convert('top'); });
  $('btnUp').addEventListener('click', function () { convert('bottom'); });

  // ---------- Copy / Clear ----------
  document.addEventListener('click', function (e) {
    var copyBtn = e.target.closest('[data-copy]');
    if (copyBtn) { copyToClipboard($(copyBtn.dataset.copy).value, copyBtn); }
    var clearBtn = e.target.closest('[data-clear]');
    if (clearBtn) {
      var cbox = $(clearBtn.dataset.clear);
      cbox.value = '';
      cbox.dispatchEvent(new Event('input'));
      cbox.focus();
    }
  });

  function copyToClipboard(text, btn) {
    if (!text) return;
    var done = function () {
      var old = btn.textContent;
      btn.textContent = '✓ Copied';
      setTimeout(function () { btn.textContent = old; }, 1600);
    };
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(done).catch(function () { fallbackCopy(text, done); });
    } else {
      fallbackCopy(text, done);
    }
  }
  function fallbackCopy(text, done) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.cssText = 'position:fixed;left:-9999px';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); done(); } catch (e) {}
    document.body.removeChild(ta);
  }

  // ---------- Keyboard shortcuts ----------
  document.addEventListener('keydown', function (e) {
    if (e.ctrlKey && e.key === 'Enter') { e.preventDefault(); convert('top'); }
    if (e.ctrlKey && e.shiftKey && (e.key === 'C' || e.key === 'c')) {
      e.preventDefault();
      copyToClipboard(boxBottom.value, document.querySelector('[data-copy="boxBottom"]'));
    }
  });
})();
