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

  const form = product.querySelector('[data-personalization]');
  if (!form) return;
  const quantity = get('#lp-quantity');
  const decrease = get('[data-step="-1"]');
  const increase = get('[data-step="1"]');
  const minimum = Number(quantity.min);
  const maximum = Number(quantity.max);

  function updateSteps() {
    quantity.setCustomValidity('');
    decrease.disabled = !(quantity.valueAsNumber > minimum);
    increase.disabled = quantity.valueAsNumber >= maximum;
  }
  function stepQuantity(delta) {
    const current = quantity.valueAsNumber;
    quantity.value = String(Number.isFinite(current)
      ? Math.max(minimum, Math.min(maximum, Math.trunc(current) + delta))
      : minimum);
    updateSteps();
  }
  decrease.addEventListener('click', () => stepQuantity(-1));
  increase.addEventListener('click', () => stepQuantity(1));
  quantity.addEventListener('input', updateSteps);
  quantity.addEventListener('invalid', () => {
    const message = minimum === maximum
      ? 'Poți comanda ' + minimum + ' buc. pentru această personalizare.'
      : 'Alege o cantitate între ' + minimum + ' și ' + maximum + ' bucăți.';
    quantity.setCustomValidity(message);
  });
  updateSteps();
  form.classList.add('lp-quantity-ready');

  // Browsers without the image APIs retain the native file input and submit.
  if (typeof Blob.prototype.arrayBuffer !== 'function' ||
      typeof HTMLImageElement.prototype.decode !== 'function' ||
      typeof URL.createObjectURL !== 'function') return;
  const fileInput = get('#lp-file');
  const fileDrop = get('[data-file-drop]');
  const photoPreview = get('[data-chosen]');
  const localImage = get('[data-local-photo]');
  const fileName = get('[data-file-name]');
  const fileDetails = get('[data-file-details]');
  const filePrompt = get('[data-file-prompt]');
  const guideSwitch = get('[data-guide-switch]');
  const guideOutline = get('[data-guide-outline]');
  const fileStatus = get('#lp-file-status');
  const submitStatus = get('.lp-submit-status');
  const addButton = get('[data-add-product]');
  const clearButton = get('[data-clear-photo]');
  const canSubmit = addButton.type === 'submit';
  const limitMB = Number(product.dataset.limit);
  const maximumSide = Number(product.dataset.maxSide);
  const maximumPixels = Number(product.dataset.maxPixels);
  const recommendedSide = 1200;
  const numberFormat = new Intl.NumberFormat('ro-RO', { maximumFractionDigits: 1 });
  const formats = { jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png', webp: 'image/webp' };
  const formatNames = { 'image/jpeg': 'JPG', 'image/png': 'PNG', 'image/webp': 'WebP' };
  let photoVersion = 0;
  let currentUrl = '';
  let acceptedFile = null;
  let submitting = false;
  form.classList.add('lp-photo-ready');

  function setStatus(message, tone = '') {
    fileStatus.textContent = message;
    fileStatus.dataset.tone = tone;
  }

  function resetPreview() {
    ++photoVersion;
    localImage.removeAttribute('src');
    if (currentUrl) URL.revokeObjectURL(currentUrl);
    currentUrl = '';
    acceptedFile = null;
    fileName.textContent = '';
    fileDetails.textContent = '';
    filePrompt.textContent = 'Alege o fotografie';
    photoPreview.hidden = true;
    guideOutline.hidden = true;
    fileDrop.classList.remove('has-photo');
    fileInput.setCustomValidity('');
    fileInput.removeAttribute('aria-invalid');
    setStatus('');
    addButton.disabled = !canSubmit || submitting;
  }

  function fileError(message) {
    fileInput.setCustomValidity(message);
    fileInput.setAttribute('aria-invalid', 'true');
    setStatus(message, 'error');
  }

  function identifyImage(bytes) {
    if (bytes[0] === 0xff && bytes[1] === 0xd8 && bytes[2] === 0xff) return 'image/jpeg';
    if ([137, 80, 78, 71, 13, 10, 26, 10].every((byte, i) => bytes[i] === byte)) return 'image/png';
    if (String.fromCharCode(...bytes.slice(0, 4)) === 'RIFF' &&
        String.fromCharCode(...bytes.slice(8, 12)) === 'WEBP') return 'image/webp';
    return '';
  }

  async function choosePhoto() {
    resetPreview();
    const file = fileInput.files[0];
    if (!file) return;
    const version = photoVersion;
    addButton.disabled = true;
    setStatus('Se verifică fotografia…', 'checking');
    try {
      const extension = file.name.split('.').pop().toLowerCase();
      const expected = formats[extension];
      if (!expected) throw new Error('Alege o fotografie în format JPG, PNG sau WebP.');
      if (file.size > limitMB * 1024 * 1024) {
        throw new Error('Fotografia depășește limita de ' + limitMB + ' MB. Alege o imagine mai mică.');
      }
      const bytes = new Uint8Array(await file.slice(0, 12).arrayBuffer());
      if (!file.size || identifyImage(bytes) !== expected) {
        throw new Error('Conținutul imaginii nu corespunde formatului. Alege un fișier JPG, PNG sau WebP valid.');
      }
      if (version !== photoVersion) return;
      currentUrl = URL.createObjectURL(file);
      localImage.src = currentUrl;
      try {
        await localImage.decode();
      } catch {
        throw new Error('Imaginea nu poate fi deschisă. Verifică fișierul sau alege altă fotografie.');
      }
      if (version !== photoVersion) return;
      const width = localImage.naturalWidth;
      const height = localImage.naturalHeight;
      const shorterSide = Math.min(width, height);
      if (width > maximumSide || height > maximumSide || width * height > maximumPixels) {
        throw new Error('Alege o copie de cel mult ' + numberFormat.format(maximumPixels / 1000000) +
          ' megapixeli și ' + numberFormat.format(maximumSide) + ' px pe latură.');
      }

      const size = file.size >= 1024 * 1024
        ? numberFormat.format(file.size / 1024 / 1024) + ' MB'
        : Math.max(1, Math.round(file.size / 1024)) + ' KB';
      acceptedFile = file;
      fileName.textContent = file.name;
      fileDetails.textContent = formatNames[expected] + ' · ' + width + ' × ' + height + ' px · ' + size;
      guideOutline.style.setProperty('--lp-guide-width', (90 * shorterSide / width) + '%');
      guideOutline.hidden = !guideSwitch.checked;
      photoPreview.hidden = false;
      filePrompt.textContent = 'Schimbă fotografia';
      fileDrop.classList.add('has-photo');
      const lowResolution = shorterSide < recommendedSide;
      const resolutionTip = lowResolution
        ? 'Unele detalii se pot pierde. Dacă ai originalul, alege o fotografie cu cel puțin ' +
          numberFormat.format(recommendedSide) + ' px pe latura scurtă.'
        : 'Rezoluția atinge pragul recomandat. Verifică dacă subiectul este clar.';
      const framingTip = Math.max(width, height) / shorterSide >= 1.5
        ? ' Formatul este alungit. Verifică poziția subiectului în ghidul circular.'
        : '';
      setStatus(resolutionTip + framingTip, lowResolution ? 'guidance' : 'ready');
    } catch (error) {
      if (version === photoVersion) {
        resetPreview();
        fileError(error instanceof Error ? error.message : 'Alege altă fotografie.');
      }
    } finally {
      if (version === photoVersion) addButton.disabled = !canSubmit || submitting;
    }
  }

  fileInput.addEventListener('change', choosePhoto);
  fileInput.addEventListener('invalid', () => {
    if (!fileInput.files.length) fileError('Alege o fotografie pentru această personalizare.');
  });
  guideSwitch.addEventListener('change', () => {
    guideOutline.hidden = !guideSwitch.checked;
  });
  clearButton.addEventListener('click', () => {
    fileInput.value = '';
    resetPreview();
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
    const files = event.dataTransfer.files;
    if (files.length !== 1) {
      fileInput.value = '';
      resetPreview();
      fileError('Selectează o singură fotografie pentru această personalizare.');
      return;
    }
    try {
      fileInput.files = files;
    } catch {
      fileInput.value = '';
      resetPreview();
      fileError('Apasă pe „Alege o fotografie” pentru a selecta fișierul.');
      return;
    }
    choosePhoto();
  });

  form.addEventListener('submit', (event) => {
    if (!canSubmit) {
      event.preventDefault();
      return;
    }
    if (submitting || !acceptedFile || fileInput.files[0] !== acceptedFile) {
      event.preventDefault();
      if (!submitting && !addButton.disabled) {
        fileError('Selectează o fotografie validă înainte de a adăuga Luna în coș.');
        fileInput.focus();
      }
      return;
    }
    submitting = true;
    form.setAttribute('aria-busy', 'true');
    addButton.disabled = true;
    submitStatus.textContent = 'Se încarcă fotografia și se adaugă produsul în coș…';
  });
  window.addEventListener('pageshow', (event) => {
    if (!event.persisted) return;
    submitting = false;
    updateSteps();
    form.removeAttribute('aria-busy');
    submitStatus.textContent = '';
    choosePhoto();
  });
  if (fileInput.files.length) choosePhoto();
})();
