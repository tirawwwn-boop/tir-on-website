(() => {
  'use strict';

  const header = document.querySelector('.site-head');
  const menuButton = header.querySelector('.menu-trigger');
  function setMenu(open) {
    header.classList.toggle('menu-open', open);
    menuButton.setAttribute('aria-expanded', String(open));
    menuButton.setAttribute('aria-label', open ? 'Închide meniul' : 'Deschide meniul');
  }
  menuButton.addEventListener('click', () => setMenu(!header.classList.contains('menu-open')));
  header.querySelector('nav').addEventListener('click', () => setMenu(false));
  document.addEventListener('click', event => {
    if (!header.contains(event.target)) setMenu(false);
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && header.classList.contains('menu-open')) {
      setMenu(false);
      menuButton.focus();
    }
  });

  if ('IntersectionObserver' in window && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
    const observer = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.remove('awaiting-view');
          observer.unobserve(entry.target);
        }
      });
    }, {threshold: 0.08});
    document.querySelectorAll('.feature-copy, .tl-transform-copy, .tl-steps, .tl-final-inner').forEach(section => {
      if (section.getBoundingClientRect().top > window.innerHeight) {
        section.classList.add('awaiting-view');
        observer.observe(section);
      }
    });
  }
})();
