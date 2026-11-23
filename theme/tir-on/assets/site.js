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
      hero.querySelector('[data-caption]').textContent = nextFrame.dataset.name + (nextFrame.dataset.concept === '1' ? ' · Imagine de concept' : '');
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

  const bag = document.querySelector('#tiron-bag');
  const storageKey = 'tiron-preview-bag-v1';
  let items = [];
  try {
    const previous = JSON.parse(sessionStorage.getItem(storageKey) || '[]');
    if (Array.isArray(previous)) items = previous.filter(item => item && typeof item.name === 'string' && typeof item.price === 'string' && Number.isInteger(item.quantity) && item.quantity >= 1 && item.quantity <= 99);
  } catch { /* The bag also works in memory when storage is unavailable. */ }

  function updateCount() {
    const count = items.reduce((total, item) => total + item.quantity, 0);
    document.querySelectorAll('[data-bag-count]').forEach(node => { node.textContent = String(count); });
  }

  function saveBag() {
    try { sessionStorage.setItem(storageKey, JSON.stringify(items)); } catch { /* Session storage is optional. */ }
    updateCount();
  }

  function renderBag() {
    const content = bag.querySelector('[data-bag-content]');
    content.replaceChildren();
    if (!items.length) {
      const empty = document.createElement('p');
      empty.textContent = 'Coșul tău este gol.';
      content.append(empty);
    }
    items.forEach((item, index) => {
      const row = document.createElement('div');
      const title = document.createElement('strong');
      const line = document.createElement('span');
      const remove = document.createElement('button');
      row.className = 'bag-row';
      title.textContent = item.name;
      line.textContent = item.quantity + ' × ' + item.price;
      remove.className = 'bag-remove';
      remove.type = 'button';
      remove.textContent = 'Elimină';
      remove.setAttribute('aria-label', 'Elimină ' + item.name + ' din coș');
      remove.addEventListener('click', () => {
        items.splice(index, 1);
        saveBag();
        renderBag();
        bag.querySelector('[data-bag-close]').focus();
      });
      row.append(title, line, remove);
      content.append(row);
    });
    const link = document.createElement('a');
    link.className = 'button-dark';
    link.href = bag.dataset.lunaUrl;
    link.textContent = items.length ? 'Continuă personalizarea' : 'Descoperă Luna';
    if (document.querySelector('[data-luna-product]')) {
      link.addEventListener('click', event => {
        event.preventDefault();
        bag.close();
        document.querySelector('.lp-personalize')?.scrollIntoView({block: 'start'});
      });
    }
    content.append(link);
  }
  function openBag() {
    setMenu(false);
    renderBag();
    if (!bag.open) bag.showModal();
  }
  document.querySelectorAll('[data-bag-open]').forEach(button => button.addEventListener('click', openBag));
  bag.querySelector('[data-bag-close]').addEventListener('click', () => bag.close());
  bag.addEventListener('click', event => {
    if (event.target === bag) {
      const bounds = bag.getBoundingClientRect();
      if (event.clientX < bounds.left || event.clientX > bounds.right) bag.close();
    }
  });
  document.addEventListener('tiron:bag-add', event => {
    const {name, price, quantity, hasPhoto} = event.detail ?? {};
    if (!hasPhoto || !name || !price || !Number.isInteger(quantity) || quantity < 1 || quantity > 99) return;
    event.preventDefault();
    items.push({name, price, quantity});
    saveBag();
    openBag();
  });
  updateCount();
})();
