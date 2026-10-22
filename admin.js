(function () {
  try { var a = localStorage.getItem('admin_theme'); if (a) document.documentElement.setAttribute('data-at', a); } catch (e) {}
  window.atog = function () {
    var r = document.documentElement, n = r.getAttribute('data-at') === 'light' ? 'dark' : 'light';
    r.setAttribute('data-at', n);
    try { localStorage.setItem('admin_theme', n); } catch (e) {}
  };
})();
document.addEventListener('click', function (e) {
  var o = e.target.closest('[data-open]');
  if (o) { var p = document.getElementById(o.getAttribute('data-open')); if (p) { p.classList.add('show'); document.body.classList.add('lock'); } return; }
  if (e.target.closest('[data-close]') || (e.target.classList && e.target.classList.contains('panel'))) {
    document.querySelectorAll('.panel.show').forEach(function (p) { p.classList.remove('show'); });
    document.body.classList.remove('lock');
  }
});
