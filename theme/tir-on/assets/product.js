(() => {
  'use strict';

  const product = document.querySelector('[data-luna-product]');
  if (!product) return;

  const get = (selector) => product.querySelector(selector);
  const thumbButtons = Array.from(product.querySelectorAll('[data-gallery-index]'));
  const mainPhoto = get('[data-main-photo]');
  const lightbox = get('[data-lightbox]');
  const largePhoto = get('[data-large-photo]');
  const images = thumbButtons.length
    ? thumbButtons.map((button) => button.querySelector('img').getAttribute('src'))
    : mainPhoto ? [mainPhoto.getAttribute('src')] : [];
  let selected = 0;

  function selectPhoto(index, focusThumb = false) {
    if (!images.length) return;
    selected = (index + images.length) % images.length;
    const alt = product.dataset.name + ', imaginea ' + (selected + 1);
    mainPhoto.src = images[selected];
    mainPhoto.alt = alt;
    if (lightbox.open) {
      largePhoto.src = images[selected];
      largePhoto.alt = alt;
      get('[data-lightbox-count]').textContent = (selected + 1) + ' / ' + images.length;
    }
    thumbButtons.forEach((button, i) => {
      button.setAttribute('aria-pressed', String(i === selected));
    });
    const activeThumb = thumbButtons[selected];
    if (focusThumb && activeThumb) activeThumb.focus();
    if (activeThumb) {
      const strip = activeThumb.parentElement;
      const delta = activeThumb.getBoundingClientRect().left - strip.getBoundingClientRect().left;
      if (delta < 0) strip.scrollLeft += delta;
      if (delta + activeThumb.clientWidth > strip.clientWidth) {
        strip.scrollLeft += delta + activeThumb.clientWidth - strip.clientWidth;
      }
    }
  }

  function moveWithKeys(event, focusThumb = false) {
    if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
    event.preventDefault();
    selectPhoto(selected + (event.key === 'ArrowRight' ? 1 : -1), focusThumb);
  }

  if (mainPhoto && lightbox) {
    const openButton = get('[data-photo-open]');
    const closeButton = get('[data-close-gallery]');
    let ignoreNextClick = false;

    thumbButtons.forEach((button, index) => {
      button.addEventListener('click', () => selectPhoto(index));
      button.addEventListener('keydown', (event) => moveWithKeys(event, true));
    });
    openButton.addEventListener('click', () => {
      if (ignoreNextClick) return;
      if (!lightbox.open) lightbox.showModal();
      selectPhoto(selected);
      closeButton.focus();
    });
    openButton.addEventListener('keydown', (event) => moveWithKeys(event));
    closeButton.addEventListener('click', () => lightbox.close());
    lightbox.addEventListener('keydown', (event) => moveWithKeys(event));
    lightbox.addEventListener('close', () => openButton.focus({ preventScroll: true }));
    lightbox.addEventListener('click', (event) => {
      if (event.target !== lightbox) return;
      const rect = lightbox.getBoundingClientRect();
      if (event.clientX < rect.left || event.clientX > rect.right ||
          event.clientY < rect.top || event.clientY > rect.bottom) lightbox.close();
    });
    product.querySelectorAll('[data-gallery-step]').forEach((button) => {
      button.addEventListener('click', () => selectPhoto(selected + Number(button.dataset.galleryStep)));
    });

    if (images.length > 1) {
      [openButton, largePhoto].forEach((surface) => {
        let start = null;
        surface.addEventListener('touchstart', (event) => {
          start = event.touches.length === 1 ? event.touches[0] : null;
          ignoreNextClick = false;
        }, { passive: true });
        surface.addEventListener('touchcancel', () => { start = null; }, { passive: true });
        surface.addEventListener('touchend', (event) => {
          if (!start || !event.changedTouches.length) return;
          const dx = event.changedTouches[0].clientX - start.clientX;
          const dy = event.changedTouches[0].clientY - start.clientY;
          start = null;
          if (Math.abs(dx) < 45 || Math.abs(dx) <= Math.abs(dy) * 1.4) return;
          selectPhoto(selected + (dx < 0 ? 1 : -1));
          ignoreNextClick = true;
          setTimeout(() => { ignoreNextClick = false; }, 400);
        }, { passive: true });
      });
    }
  }

  const fileInput = get('#lp-file');
  const fileDrop = get('[data-file-drop]');
  const photoPreview = get('[data-chosen]');
  const localImage = get('[data-local-photo]');
  const fileStatus = get('#lp-file-status');
  const addButton = get('[data-bag-add]');
  const clearButton = get('[data-clear-photo]');
  const quantity = get('#lp-quantity');
  const decrease = get('[data-step="-1"]');
  const increase = get('[data-step="1"]');
  const limitMB = Number(product.dataset.limit);
  const inStock = product.dataset.inStock !== 'false';
  let photoVersion = 0;
  let currentUrl = '';

  function resetPhoto() {
    ++photoVersion;
    if (currentUrl) URL.revokeObjectURL(currentUrl);
    currentUrl = '';
    localImage.removeAttribute('src');
    get('[data-file-name]').textContent = '';
    photoPreview.hidden = true;
    fileDrop.hidden = false;
    fileInput.value = '';
    fileInput.removeAttribute('aria-invalid');
    fileStatus.textContent = '';
    addButton.disabled = !inStock;
  }

  function identifyImage(bytes) {
    if (bytes[0] === 0xff && bytes[1] === 0xd8 && bytes[2] === 0xff) return 'image/jpeg';
    if ([137, 80, 78, 71, 13, 10, 26, 10].every((byte, i) => bytes[i] === byte)) return 'image/png';
    if (String.fromCharCode(...bytes.slice(0, 4)) === 'RIFF' &&
        String.fromCharCode(...bytes.slice(8, 12)) === 'WEBP') return 'image/webp';
    return '';
  }

  async function choosePhoto(file) {
    resetPhoto();
    if (!file) return;
    const version = photoVersion;
    let pendingUrl = '';
    addButton.disabled = true;
    fileStatus.textContent = 'Se verifică imaginea…';
    try {
      const extension = file.name.split('.').pop().toLowerCase();
      const mimeByExtension = { jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png', webp: 'image/webp' };
      const expected = mimeByExtension[extension];
      if (!expected) throw new Error('Alege o fotografie în format JPG, PNG sau WebP.');
      if (file.size > limitMB * 1024 * 1024) {
        throw new Error('Fotografia depășește limita de ' + limitMB + ' MB. Alege o imagine mai mică.');
      }
      const bytes = new Uint8Array(await file.slice(0, 12).arrayBuffer());
      if (!file.size || identifyImage(bytes) !== expected) {
        throw new Error('Conținutul imaginii nu corespunde formatului. Alege un fișier JPG, PNG sau WebP valid.');
      }
      if (version !== photoVersion) return;
      pendingUrl = URL.createObjectURL(file);
      const probe = new Image();
      probe.src = pendingUrl;
      try {
        await probe.decode();
      } catch {
        throw new Error('Imaginea nu poate fi deschisă. Verifică fișierul sau alege altă fotografie.');
      }
      if (version !== photoVersion) return;
      currentUrl = pendingUrl;
      pendingUrl = '';
      localImage.src = currentUrl;
      get('[data-file-name]').textContent = file.name;
      photoPreview.hidden = false;
      fileDrop.hidden = true;
      fileStatus.textContent = '';
      clearButton.focus({ preventScroll: true });
    } catch (error) {
      if (version !== photoVersion) return;
      fileInput.setAttribute('aria-invalid', 'true');
      fileStatus.textContent = error instanceof Error ? error.message : 'Alege altă fotografie.';
    } finally {
      if (pendingUrl) URL.revokeObjectURL(pendingUrl);
      if (version === photoVersion) addButton.disabled = !inStock;
    }
  }

  fileInput.addEventListener('change', () => choosePhoto(fileInput.files[0]));
  clearButton.addEventListener('click', () => {
    resetPhoto();
    fileInput.focus();
  });
  ['dragenter', 'dragover'].forEach((name) => fileDrop.addEventListener(name, (event) => {
    event.preventDefault();
    fileDrop.classList.add('is-dragging');
  }));
  fileDrop.addEventListener('dragleave', () => fileDrop.classList.remove('is-dragging'));
  fileDrop.addEventListener('drop', (event) => {
    event.preventDefault();
    fileDrop.classList.remove('is-dragging');
    if (event.dataTransfer.files.length > 1) {
      resetPhoto();
      fileStatus.textContent = 'Selectează o singură fotografie pentru această personalizare.';
      return;
    }
    choosePhoto(event.dataTransfer.files[0]);
  });

  function setQuantity(value) {
    const parsed = Number.parseInt(value, 10);
    const normalized = Number.isFinite(parsed) ? Math.max(1, Math.min(99, parsed)) : 1;
    quantity.value = String(normalized);
    decrease.disabled = normalized === 1;
    increase.disabled = normalized === 99;
    return normalized;
  }
  decrease.addEventListener('click', () => setQuantity(Number(quantity.value) - 1));
  increase.addEventListener('click', () => setQuantity(Number(quantity.value) + 1));
  quantity.addEventListener('change', () => setQuantity(quantity.value));
  addButton.addEventListener('click', () => {
    if (!inStock) return;
    if (!currentUrl) {
      fileInput.setAttribute('aria-invalid', 'true');
      fileStatus.textContent = 'Alege o fotografie înainte de a adăuga Luna în coș.';
      fileInput.focus();
      return;
    }
    const handled = !product.dispatchEvent(new CustomEvent('tiron:bag-add', {
      bubbles: true,
      cancelable: true,
      detail: {
        name: product.dataset.name,
        price: product.dataset.price,
        sku: product.dataset.sku,
        quantity: setQuantity(quantity.value),
        hasPhoto: true
      }
    }));
    if (!handled) fileStatus.textContent = 'Coșul de probă este indisponibil. Reîncarcă pagina și încearcă din nou.';
  });
})();
