/* Tema calido: menú móvil, cabecera al hacer scroll, aparición suave de bloques y copiar código. */
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

  var header = document.querySelector('.site-header');
  if (header) {
    var onScroll = function () { header.classList.toggle('scrolled', window.scrollY > 8); };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  var items = document.querySelectorAll('.reveal');
  if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    document.documentElement.classList.add('reveal-on');
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); }
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
    items.forEach(function (el, i) { el.style.transitionDelay = (i % 4) * 70 + 'ms'; io.observe(el); });
  } else {
    items.forEach(function (el) { el.classList.add('in'); });
  }

  document.addEventListener('click', function (ev) {
    var b = ev.target.closest && ev.target.closest('[data-copy]');
    if (!b) return;
    var code = b.closest('.codeblock').querySelector('code').textContent;
    if (navigator.clipboard) navigator.clipboard.writeText(code).then(function () {
      b.textContent = '¡Copiado!'; setTimeout(function () { b.textContent = 'Copiar'; }, 1500);
    });
  });
})();
