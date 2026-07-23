/**
 * ગુજરાતી ફોન્ટ કન્વર્ટર — frontend logic (Vanilla JS, no dependencies).
 * AJAX conversion, font search dropdown, copy/clear/download, swap,
 * dark mode, demo counter, keyboard shortcuts, localStorage history.
 */
(function () {
  'use strict';

  var $ = function (id) { return document.getElementById(id); };
  var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  var baseUrl = ((document.querySelector('meta[name="base-url"]') || {}).content || '/').replace(/\/$/, '');

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
    hamburger.addEventListener('click', function () {
      $('mainNav').classList.toggle('open');
    });
  }

  // Converter પેજ નથી? — બાકીનું skip
  var legacyBox = $('legacyText');
  var unicodeBox = $('unicodeText');
  if (!legacyBox || !unicodeBox) return;

  var fontSearch = $('fontSearch');
  var fontSlugInput = $('fontSlug');
  var dropdown = $('fontDropdown');
  var statusEl = $('convStatus');
  var demoBar = $('demoBar');
  var demoBarText = $('demoBarText');
  var demoState = { unlimited: false, char_limit: 200, attempts_left: null };

  // ---------- Font dropdown (searchable) ----------
  function selectFont(slug, name) {
    fontSlugInput.value = slug;
    fontSearch.value = name;
    dropdown.classList.remove('open');
    try { localStorage.setItem('gfc_font', JSON.stringify({ slug: slug, name: name })); } catch (e) {}
  }

  // પહેલાની પસંદગી પાછી લાવો; નહીં તો page ના default slug નું નામ બતાવો
  (function initFont() {
    var initial = fontSlugInput.value;
    var stored = null;
    try { stored = JSON.parse(localStorage.getItem('gfc_font') || 'null'); } catch (e) {}
    var items = dropdown.querySelectorAll('.fd-item');
    // Font-page પર server-set slug પ્રથમ priority
    for (var i = 0; i < items.length; i++) {
      if (items[i].dataset.slug === initial) {
        fontSearch.value = items[i].dataset.name;
        return;
      }
    }
    if (stored && stored.slug) {
      for (var j = 0; j < items.length; j++) {
        if (items[j].dataset.slug === stored.slug) {
          selectFont(stored.slug, stored.name);
          return;
        }
      }
    }
  })();

  fontSearch.addEventListener('focus', function () { dropdown.classList.add('open'); this.select(); });
  fontSearch.addEventListener('input', function () {
    var q = this.value.toLowerCase();
    dropdown.classList.add('open');
    dropdown.querySelectorAll('.fd-item').forEach(function (item) {
      item.classList.toggle('hidden', q !== '' && item.dataset.name.toLowerCase().indexOf(q) === -1);
    });
  });
  document.addEventListener('click', function (e) {
    if (!e.target.closest('.font-select-wrap')) dropdown.classList.remove('open');
    var item = e.target.closest('.fd-item');
    if (item) selectFont(item.dataset.slug, item.dataset.name);
  });

  // ---------- Character counters ----------
  function updateCounter(box, counterId) {
    var el = $(counterId);
    if (!el) return;
    var len = Array.from(box.value).length; // multibyte-safe
    el.textContent = len + ' અક્ષર' + (
      !demoState.unlimited && box === legacyBox && demoState.char_limit
        ? ' / ' + demoState.char_limit : ''
    );
    el.classList.toggle('limit-near',
      !demoState.unlimited && demoState.char_limit && len >= demoState.char_limit * 0.9);
  }
  legacyBox.addEventListener('input', function () { updateCounter(legacyBox, 'counterLegacy'); });
  unicodeBox.addEventListener('input', function () { updateCounter(unicodeBox, 'counterUnicode'); });

  // ---------- Demo status ----------
  function refreshDemoBar() {
    if (demoState.unlimited) { demoBar.hidden = true; return; }
    if (demoState.attempts_left === null) return;
    demoBar.hidden = false;
    demoBarText.textContent = '⚠ DEMO: ' + demoState.char_limit + ' અક્ષરની મર્યાદા. '
      + demoState.attempts_left + ' પ્રયાસ બાકી.';
  }
  fetch(baseUrl + '/demo-status', { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (json) {
      if (!json.success) return;
      demoState.unlimited = !!json.unlimited;
      if (!json.unlimited) {
        demoState.char_limit = json.char_limit;
        demoState.attempts_left = json.attempts_left;
      }
      refreshDemoBar();
      updateCounter(legacyBox, 'counterLegacy');
    })
    .catch(function () {});

  // ---------- Status message ----------
  function setStatus(msg, type) {
    statusEl.textContent = msg || '';
    statusEl.className = 'conv-status' + (type ? ' ' + type : '');
  }

  // ---------- History (localStorage, છેલ્લા 5 — ટેક્સ્ટ સર્વર પર જતો નથી) ----------
  function saveHistory(input, output, direction) {
    try {
      var hist = JSON.parse(localStorage.getItem('gfc_history') || '[]');
      hist.unshift({
        i: input.slice(0, 500), o: output.slice(0, 500),
        d: direction, f: fontSlugInput.value, t: Date.now()
      });
      localStorage.setItem('gfc_history', JSON.stringify(hist.slice(0, 5)));
    } catch (e) {}
  }

  // ---------- Conversion (AJAX) ----------
  function convert(direction) {
    var srcBox = direction === 'legacy_to_unicode' ? legacyBox : unicodeBox;
    var dstBox = direction === 'legacy_to_unicode' ? unicodeBox : legacyBox;
    var btn = direction === 'legacy_to_unicode' ? $('btnToUnicode') : $('btnToLegacy');
    var text = srcBox.value;

    if (!text.trim()) { setStatus('પહેલા ટેક્સ્ટ લખો કે paste કરો.', 'err'); return; }
    if (!fontSlugInput.value) { setStatus('ફોન્ટ પસંદ કરો.', 'err'); return; }

    var originalLabel = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> કન્વર્ટ થાય છે...';
    setStatus('');

    var body = new URLSearchParams();
    body.append('csrf_token', csrfToken);
    body.append('text', text);
    body.append('font', fontSlugInput.value);
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
          updateCounter(dstBox, dstBox === unicodeBox ? 'counterUnicode' : 'counterLegacy');
          setStatus('✓ ' + json.data.char_count + ' અક્ષર કન્વર્ટ થયા ('
            + json.data.processing_time_ms + 'ms) — ' + json.data.font, 'ok');
          saveHistory(text, json.data.converted_text, direction);
          if (json.demo) {
            demoState.attempts_left = json.demo.attempts_left;
            demoState.char_limit = json.demo.char_limit;
            refreshDemoBar();
          }
        } else {
          setStatus('✗ ' + (json.error || 'કન્વર્ઝન ફેલ થયું'), 'err');
        }
      })
      .catch(function () { setStatus('✗ Network ભૂલ — ફરી પ્રયત્ન કરો.', 'err'); })
      .finally(function () {
        btn.disabled = false;
        btn.innerHTML = originalLabel;
      });
  }

  $('btnToUnicode').addEventListener('click', function () { convert('legacy_to_unicode'); });
  $('btnToLegacy').addEventListener('click', function () { convert('unicode_to_legacy'); });

  // ---------- Swap ----------
  $('btnSwap').addEventListener('click', function () {
    var tmp = legacyBox.value;
    legacyBox.value = unicodeBox.value;
    unicodeBox.value = tmp;
    updateCounter(legacyBox, 'counterLegacy');
    updateCounter(unicodeBox, 'counterUnicode');
  });

  // ---------- Copy / Clear ----------
  document.addEventListener('click', function (e) {
    var copyBtn = e.target.closest('[data-copy]');
    if (copyBtn) {
      var box = $(copyBtn.dataset.copy);
      copyToClipboard(box.value, copyBtn);
    }
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
      btn.textContent = '✓ કૉપી થયું';
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

  // ---------- Download .txt ----------
  $('btnDownload').addEventListener('click', function () {
    var text = unicodeBox.value;
    if (!text) { setStatus('ડાઉનલોડ કરવા માટે પહેલા કન્વર્ટ કરો.', 'err'); return; }
    var blob = new Blob(['﻿' + text], { type: 'text/plain;charset=utf-8' });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'converted-unicode-' + Date.now() + '.txt';
    document.body.appendChild(a);
    a.click();
    setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 500);
  });

  // ---------- Keyboard shortcuts ----------
  document.addEventListener('keydown', function (e) {
    if (e.ctrlKey && e.key === 'Enter') {
      e.preventDefault();
      convert('legacy_to_unicode');
    }
    if (e.ctrlKey && e.shiftKey && (e.key === 'C' || e.key === 'c')) {
      e.preventDefault();
      copyToClipboard(unicodeBox.value, document.querySelector('[data-copy="unicodeText"]'));
    }
  });
})();
