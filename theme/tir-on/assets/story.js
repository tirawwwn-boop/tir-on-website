(() => {
  const section = document.querySelector('[data-relief]');
  if (!section) return;

  const controls = section.querySelector('.relief-controls');
  const buttons = Array.from(controls.querySelectorAll('[data-relief-view]'));
  const picture = section.querySelector('[data-relief-image]');
  const caption = section.querySelector('[data-relief-caption]');

  for (const button of buttons) {
    button.addEventListener('click', () => {
      const detail = button.dataset.reliefView === 'detail';
      picture.classList.toggle('relief-zoomed', detail);
      caption.textContent = detail ? 'Textura litofanului · detaliu mărit' : 'Luna stinsă';
      for (const candidate of buttons) {
        candidate.setAttribute('aria-pressed', String(candidate === button));
      }
    });
  }

  controls.hidden = false;
})();
