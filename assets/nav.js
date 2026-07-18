// Menu déroulant "Do It Yourself" du menu principal
(function () {
  var dd = document.querySelector('.nav-dropdown');
  if (!dd) return;
  var toggle = dd.querySelector('.nav-dropdown-toggle');

  function close() {
    dd.classList.remove('open');
    toggle.setAttribute('aria-expanded', 'false');
  }

  toggle.addEventListener('click', function (e) {
    e.stopPropagation();
    var open = dd.classList.toggle('open');
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  });

  document.addEventListener('click', function (e) {
    if (!dd.contains(e.target)) close();
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') close();
  });
})();
