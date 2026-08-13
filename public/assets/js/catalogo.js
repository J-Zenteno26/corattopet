'use strict';

const cartModal = document.querySelector('#catalog-cart-modal');

if (cartModal instanceof HTMLDialogElement) {
    cartModal.showModal();

    document.querySelectorAll('[data-cart-modal-close]').forEach((button) => {
        button.addEventListener('click', () => cartModal.close());
    });

    cartModal.addEventListener('click', (event) => {
        if (event.target === cartModal) {
            cartModal.close();
        }
    });
}

document.querySelectorAll('.product-purchase-form').forEach((form) => {
    const input = form.querySelector('input[name="cantidad"]');
    const decrease = form.querySelector('[data-purchase-quantity="decrease"]');
    const increase = form.querySelector('[data-purchase-quantity="increase"]');

    if (!(input instanceof HTMLInputElement)) {
        return;
    }

    const clampQuantity = (value) => {
        const minimum = Number.parseInt(input.min || '1', 10);
        const maximum = Number.parseInt(input.max || '99', 10);
        return Math.min(maximum, Math.max(minimum, value));
    };

    decrease?.addEventListener('click', () => {
        const current = Number.parseInt(input.value || '1', 10);
        input.value = String(clampQuantity(current - 1));
    });

    increase?.addEventListener('click', () => {
        const current = Number.parseInt(input.value || '1', 10);
        input.value = String(clampQuantity(current + 1));
    });

    input.addEventListener('change', () => {
        const current = Number.parseInt(input.value || '1', 10);
        input.value = String(clampQuantity(current));
    });
});
