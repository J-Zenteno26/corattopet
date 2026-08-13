'use strict';

document.querySelectorAll('[data-newsletter-form]').forEach((form) => {
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const feedback = form.parentElement?.querySelector(
        '[data-newsletter-feedback]'
    );

    const submitButton = form.querySelector('button[type="submit"]');

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!form.reportValidity()) {
            return;
        }

        if (submitButton instanceof HTMLButtonElement) {
            submitButton.disabled = true;
            submitButton.setAttribute('aria-busy', 'true');
        }

        if (feedback instanceof HTMLElement) {
            feedback.hidden = true;
            feedback.textContent = '';
        }

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await response.json();

            if (!response.ok || data.ok !== true) {
                throw new Error(
                    typeof data.mensaje === 'string'
                        ? data.mensaje
                        : 'No pudimos registrar tu suscripción.'
                );
            }

            form.reset();

            if (feedback instanceof HTMLElement) {
                feedback.textContent = data.mensaje;
                feedback.hidden = false;
            }
        } catch (error) {
            if (feedback instanceof HTMLElement) {
                feedback.textContent = error instanceof Error
                    ? error.message
                    : 'No pudimos registrar tu suscripción.';

                feedback.hidden = false;
            }
        } finally {
            if (submitButton instanceof HTMLButtonElement) {
                submitButton.disabled = false;
                submitButton.removeAttribute('aria-busy');
            }
        }
    });
});