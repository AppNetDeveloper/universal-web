(function () {
  var toggle = document.querySelector('.nav-toggle'), nav = document.getElementById('site-nav');
  if (toggle && nav) {
    var setOpen = function (open, back) {
      nav.classList.toggle('open', open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (!open && back) toggle.focus();
    };
    var isOpen = function () { return nav.classList.contains('open'); };
    toggle.addEventListener('click', function () { setOpen(!isOpen()); });
    // Escape cierra y devuelve el foco al botón; un enlace del menú, un clic fuera o sacar el foco del menú también lo cierran.
    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && isOpen()) setOpen(false, true);
    });
    nav.addEventListener('click', function (ev) { if (ev.target.closest && ev.target.closest('a')) setOpen(false); });
    document.addEventListener('click', function (ev) {
      if (isOpen() && !nav.contains(ev.target) && !toggle.contains(ev.target)) setOpen(false);
    });
    nav.addEventListener('focusout', function (ev) {
      if (isOpen() && ev.relatedTarget && !nav.contains(ev.relatedTarget) && ev.relatedTarget !== toggle) setOpen(false);
    });
    window.addEventListener('resize', function () { if (isOpen() && !toggle.offsetParent) setOpen(false); });
  }

  document.addEventListener('click', function (ev) {
    var b = ev.target.closest && ev.target.closest('[data-copy]');
    if (!b) return;
    var code = b.closest('.codeblock').querySelector('code').textContent;
    var done = function () { b.textContent = '¡Copiado!'; setTimeout(function () { b.textContent = 'Copiar'; }, 1500); };
    if (navigator.clipboard) navigator.clipboard.writeText(code).then(done);
  });
})();
