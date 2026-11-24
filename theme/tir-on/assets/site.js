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

  const hero = document.querySelector('[data-hero]');
  if (hero) {
    const frames = [...hero.querySelectorAll('[data-frame]')];
    const markers = [...hero.querySelectorAll('[data-slide]')];
    let index = 0;
    function selectSlide(nextIndex) {
      index = (nextIndex + frames.length) % frames.length;
      const nextFrame = frames[index];
      nextFrame.querySelectorAll('[data-srcset]').forEach(source => {
        source.srcset = source.dataset.srcset;
        delete source.dataset.srcset;
      });
      const img = nextFrame.querySelector('[data-src]');
      if (img) {
        img.src = img.dataset.src;
        delete img.dataset.src;
      }
      frames.forEach((frame, i) => {
        const active = i === index;
        frame.classList.toggle('is-active', active);
        frame.setAttribute('aria-hidden', String(!active));
        markers[i].setAttribute('aria-pressed', String(active));
      });
      hero.querySelector('[data-position]').textContent = String(index + 1).padStart(2, '0');
      hero.querySelector('[data-caption]').textContent = nextFrame.dataset.name + (nextFrame.dataset.concept === '1' ? ' · Vizualizare de concept' : '');
    }
    markers.forEach((marker, i) => marker.addEventListener('click', () => selectSlide(i)));
    hero.querySelector('[data-prev]').addEventListener('click', () => selectSlide(index - 1));
    hero.querySelector('[data-next]').addEventListener('click', () => selectSlide(index + 1));
    hero.addEventListener('keydown', event => {
      if (event.target.tagName !== 'BUTTON') return;
      if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
        event.preventDefault();
        selectSlide(index + (event.key === 'ArrowLeft' ? -1 : 1));
      }
    });
    let touchStart;
    hero.addEventListener('touchstart', event => {
      touchStart = [event.touches[0].clientX, event.touches[0].clientY];
    }, {passive: true});
    hero.addEventListener('touchend', event => {
      if (!touchStart) return;
      const delta = event.changedTouches[0].clientX - touchStart[0];
      const vertical = Math.abs(event.changedTouches[0].clientY - touchStart[1]);
      if (Math.abs(delta) > 65 && vertical < Math.abs(delta) / 2) selectSlide(index + (delta < 0 ? 1 : -1));
      touchStart = null;
    }, {passive: true});
  }

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
