(() => {
    function initComparisons() {
        document.querySelectorAll('[data-transformation]').forEach((section) => {
            const stage = section.querySelector('[data-compare]');
            const range = stage?.querySelector('input[type="range"]');
            if (!range || !range.disabled) return;

            const modes = section.querySelector('.tl-light-modes');
            const buttons = modes.querySelectorAll('button');
            const label = section.querySelector('[data-result-label]');
            const lit = stage.querySelector('.tl-lit');
            const unlit = stage.querySelector('.tl-unlit');

            const update = () => {
                const original = range.valueAsNumber;
                stage.style.setProperty('--split', original + '%');
                range.setAttribute('aria-valuetext', original + '% fotografie, ' + (100 - original) + '% ' + label.textContent.toLowerCase());
            };

            buttons.forEach((button) => {
                button.addEventListener('click', () => {
                    const illuminated = button.dataset.light === 'on';
                    lit.hidden = !illuminated;
                    unlit.hidden = illuminated;
                    label.textContent = illuminated ? 'Litofan iluminat' : 'Relief neiluminat';
                    buttons.forEach((item) => item.setAttribute('aria-pressed', String(item === button)));
                    if (range.valueAsNumber === Number(range.max)) range.value = '50';
                    update();
                });
            });

            modes.hidden = false;
            range.disabled = false;
            range.addEventListener('input', update);
            update();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initComparisons, { once: true });
    } else {
        initComparisons();
    }
})();
