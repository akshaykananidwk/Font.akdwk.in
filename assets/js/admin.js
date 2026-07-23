/**
 * Admin panel common JS — નાની utilities.
 */
(function () {
  'use strict';

  // બધા forms માં double-submit અટકાવો
  document.querySelectorAll('form').forEach(function (form) {
    form.addEventListener('submit', function () {
      var btn = form.querySelector('button[type="submit"]');
      if (btn && !btn.dataset.keepEnabled) {
        setTimeout(function () { btn.disabled = true; }, 10);
        setTimeout(function () { btn.disabled = false; }, 5000);
      }
    });
  });

  // Textarea auto-resize (json editors)
  document.querySelectorAll('textarea.json-editor').forEach(function (ta) {
    ta.addEventListener('keydown', function (e) {
      // Tab key → indent (focus ન ગુમાવો)
      if (e.key === 'Tab') {
        e.preventDefault();
        var start = ta.selectionStart;
        ta.value = ta.value.substring(0, start) + '  ' + ta.value.substring(ta.selectionEnd);
        ta.selectionStart = ta.selectionEnd = start + 2;
      }
    });
  });
})();
