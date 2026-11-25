(() => {
  'use strict';

  function refreshCartControls() {
    const form = document.querySelector('.tiron-cart-layout .woocommerce-cart-form');
    if (!form) return;

    const coupon = form.querySelector('.actions > .coupon');
    if (coupon) {
      const details = document.createElement('details');
      const summary = document.createElement('summary');
      details.className = 'tiron-cart-coupon';
      summary.textContent = 'Ai un cupon?';
      coupon.before(details);
      details.append(summary, coupon);
      // WooCommerce restores validation messages after replacing the cart with AJAX.
      if (coupon.querySelector('.coupon-error-notice, [aria-invalid="true"]')) details.open = true;
    }

    form.querySelectorAll('.product-quantity .quantity').forEach((group) => {
      const input = group.querySelector('input.qty[type="number"]:not([readonly]):not([disabled])');
      if (!input || group.querySelector('[data-cart-step]') || input.step === 'any') return;
      const name = group.closest('.cart_item').querySelector('.product-name a')?.textContent.trim();
      const buttons = [-1, 1].map((step) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.dataset.cartStep = String(step);
        button.setAttribute('aria-label', (step < 0 ? 'Scade cantitatea' : 'Crește cantitatea') + (name ? ' pentru ' + name : ''));
        button.setAttribute('aria-controls', input.id);
        button.textContent = step < 0 ? '−' : '+';
        if (step < 0) input.before(button);
        else input.after(button);
        return button;
      });
      const atMinimum = () => input.valueAsNumber <= Math.max(1, Number(input.min));
      const sync = () => {
        // Keep removal deliberate. The native remove link offers WooCommerce undo.
        buttons[0].disabled = atMinimum();
        buttons[1].disabled = input.max !== '' && input.valueAsNumber >= Number(input.max);
      };
      buttons.forEach((button, index) => button.addEventListener('click', () => {
        if (index) input.stepUp();
        else input.stepDown();
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
      }));
      input.addEventListener('input', sync);
      sync();
    });
  }

  // A shopper can change a quantity and head straight for checkout.
  // Keep Woo's update and validation, then let them review the recalculated total.
  document.addEventListener('click', event => {
    const checkout = event.target.closest('.tiron-cart-layout .checkout-button');
    if (!checkout) return;
    const form = document.querySelector('.tiron-cart-layout .woocommerce-cart-form');
    if (!form || !Array.from(form.querySelectorAll('input.qty')).some(input => input.value !== input.defaultValue)) return;
    event.preventDefault();
    if (form.reportValidity()) {
      const update = form.querySelector('[name="update_cart"]');
      if (update && !update.disabled) update.click();
    }
  });

  refreshCartControls();
  if (window.jQuery) window.jQuery(document.body).on('updated_wc_div', refreshCartControls);
})();
