document.querySelectorAll('[data-autosubmit]').forEach(function (el) {
  el.addEventListener('change', function () { el.form.submit(); });
});
document.querySelectorAll('form[data-confirm]').forEach(function (f) {
  f.addEventListener('submit', function (ev) {
    if (!window.confirm(f.getAttribute('data-confirm'))) ev.preventDefault();
  });
});
