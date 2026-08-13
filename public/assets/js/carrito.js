'use strict';

document.documentElement.classList.add('cart-auto-update-ready');

const quantityForms = document.querySelectorAll('.cart-quantity-form');

quantityForms.forEach((form) => {
    const input = form.querySelector('.cart-quantity-input');
    const decreaseButton = form.querySelector(
        '[data-quantity-action="decrease"]'
    );
    const increaseButton = form.querySelector(
        '[data-quantity-action="increase"]'
    );

    if (!(input instanceof HTMLInputElement)) {
        return;
    }

    let updateTimer = null;
    let lastSubmittedValue = input.value;

    const getMinimum = () => {
        return Number.parseInt(input.min || '1', 10);
    };

    const getMaximum = () => {
        return Number.parseInt(input.max || '99', 10);
    };

    const updateButtons = () => {
        const quantity = Number.parseInt(input.value, 10);
        const minimum = getMinimum();
        const maximum = getMaximum();

        if (decreaseButton instanceof HTMLButtonElement) {
            decreaseButton.disabled = quantity <= minimum;
        }

        if (increaseButton instanceof HTMLButtonElement) {
            increaseButton.disabled = quantity >= maximum;
        }
    };

    const submitQuantity = () => {
        const quantity = Number.parseInt(input.value, 10);
        const minimum = getMinimum();
        const maximum = getMaximum();

        if (
            !Number.isInteger(quantity)
            || quantity < minimum
            || quantity > maximum
            || input.value === lastSubmittedValue
        ) {
            updateButtons();
            return;
        }

        lastSubmittedValue = input.value;
        input.setAttribute('aria-busy', 'true');

        form.requestSubmit();
    };

    const scheduleUpdate = () => {
        if (updateTimer !== null) {
            window.clearTimeout(updateTimer);
        }

        updateTimer = window.setTimeout(submitQuantity, 450);
    };

    const changeQuantity = (difference) => {
        const currentQuantity = Number.parseInt(input.value, 10);
        const minimum = getMinimum();
        const maximum = getMaximum();

        const nextQuantity = Math.min(
            maximum,
            Math.max(minimum, currentQuantity + difference)
        );

        if (nextQuantity === currentQuantity) {
            return;
        }

        input.value = String(nextQuantity);
        updateButtons();
        submitQuantity();
    };

    decreaseButton?.addEventListener('click', () => {
        changeQuantity(-1);
    });

    increaseButton?.addEventListener('click', () => {
        changeQuantity(1);
    });

    input.addEventListener('input', () => {
        updateButtons();
        scheduleUpdate();
    });

    input.addEventListener('change', () => {
        if (updateTimer !== null) {
            window.clearTimeout(updateTimer);
        }

        submitQuantity();
    });

    updateButtons();
});