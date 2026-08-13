'use strict';

const checkoutForm = document.querySelector('#checkout-form');
const regionSelect = document.querySelector('#id_region');
const communeSelect = document.querySelector('#id_comuna');
const feedback = document.querySelector('#checkout-feedback');
const shippingCost = document.querySelector('#checkout-shipping-cost');
const deliveryCostLabel = document.querySelector('#checkout-delivery-cost-label');
const deliveryNotice = document.querySelector('#checkout-delivery-notice');
const shippingSection = document.querySelector('[data-delivery-section="despacho"]');
const pickupNote = document.querySelector('[data-pickup-note]');
const checkoutWeight = document.querySelector('[data-checkout-weight]');
const calculateButton = document.querySelector('[data-calculate-shipping]');
const total = document.querySelector('#checkout-total');
const payButton = document.querySelector('#checkout-pay-button');
const deliveryInputs = Array.from(
    document.querySelectorAll('input[name="metodo_entrega"]')
);

let shippingCalculated = false;

const checkoutValid = checkoutForm instanceof HTMLFormElement
    && checkoutForm.dataset.checkoutValid === '1';

function subtotalAmount() {
    if (!(checkoutForm instanceof HTMLFormElement)) {
        return 0;
    }

    return Number(checkoutForm.dataset.subtotal || 0);
}

function minimumForMethod(method) {
    if (!(checkoutForm instanceof HTMLFormElement)) {
        return 0;
    }

    if (method === 'retiro_en_tienda') {
        return Number(checkoutForm.dataset.minimumPickup || 0);
    }

    if (method === 'despacho') {
        return Number(checkoutForm.dataset.minimumShipping || 0);
    }

    return 0;
}

function formatMoney(amount) {
    return new Intl.NumberFormat('es-CL', {
        style: 'currency',
        currency: 'CLP',
        maximumFractionDigits: 0
    }).format(amount);
}

function minimumPurchaseStatus(method) {
    const minimum = minimumForMethod(method);
    const subtotal = subtotalAmount();
    const missing = Math.max(0, minimum - subtotal);

    return {
        minimum,
        subtotal,
        missing,
        valid: subtotal >= minimum
    };
}

function showMinimumFeedback(method) {
    const status = minimumPurchaseStatus(method);

    if (status.valid) {
        return true;
    }

    const deliveryName = method === 'retiro_en_tienda'
        ? 'retiro en tienda'
        : 'despacho';

    showFeedback(
        'La compra mínima para '
        + deliveryName
        + ' es de '
        + formatMoney(status.minimum)
        + '. Te faltan '
        + formatMoney(status.missing)
        + '.',
        'error'
    );

    return false;
}

function currentDeliveryMethod() {
    const selected = deliveryInputs.find(function (input) {
        return input instanceof HTMLInputElement && input.checked;
    });

    return selected instanceof HTMLInputElement ? selected.value : '';
}

function showFeedback(message, type) {
    if (!(feedback instanceof HTMLElement)) {
        return;
    }

    feedback.hidden = false;
    feedback.textContent = message;
    feedback.className = `checkout-feedback checkout-feedback--${type}`;
}

function hideFeedback() {
    if (feedback instanceof HTMLElement) {
        feedback.hidden = true;
        feedback.textContent = '';
        feedback.className = 'checkout-feedback';
    }
}

function subtotalFormatted() {
    if (!(checkoutForm instanceof HTMLFormElement)) {
        return '$0';
    }

    return checkoutForm.dataset.subtotalFormatted || '$0';
}

function resetShippingResult() {
    shippingCalculated = false;

    if (shippingCost instanceof HTMLElement) {
        shippingCost.textContent = 'Por calcular';
    }

    if (total instanceof HTMLElement) {
        total.textContent = subtotalFormatted();
    }

    if (payButton instanceof HTMLButtonElement) {
        payButton.disabled = true;
        payButton.textContent = 'Continuar al pago';
        delete payButton.dataset.orderCreated;
    }
}

function setShippingFieldsEnabled(enabled) {
    const fields = shippingSection instanceof HTMLElement
        ? shippingSection.querySelectorAll('input, select, textarea')
        : [];

    fields.forEach(function (field) {
        if (
            field instanceof HTMLInputElement
            || field instanceof HTMLSelectElement
            || field instanceof HTMLTextAreaElement
        ) {
            field.disabled = !enabled;
        }
    });

    if (enabled) {
        if (regionSelect instanceof HTMLSelectElement) {
            regionSelect.required = true;
        }
        if (communeSelect instanceof HTMLSelectElement) {
            communeSelect.required = true;
            communeSelect.disabled = regionSelect instanceof HTMLSelectElement
                ? regionSelect.value === ''
                : false;
        }
        const address = document.querySelector('#direccion');
        if (address instanceof HTMLInputElement) {
            address.required = true;
        }
    }
}

function applyDeliveryMethod() {
    const method = currentDeliveryMethod();
    const isPickup = method === 'retiro_en_tienda';
    const isShipping = method === 'despacho';
    const minimumStatus = minimumPurchaseStatus(method);

    if (shippingSection instanceof HTMLElement) {
        shippingSection.hidden = !isShipping;
    }

    setShippingFieldsEnabled(isShipping);

    if (pickupNote instanceof HTMLElement) {
        pickupNote.hidden = !isPickup;
    }

    if (checkoutWeight instanceof HTMLElement) {
        checkoutWeight.hidden = !isShipping;
    }

    if (calculateButton instanceof HTMLButtonElement) {
        calculateButton.hidden = !isShipping;
        calculateButton.disabled = isShipping
            ? (!checkoutValid || !minimumStatus.valid)
            : false;
    }

  hideFeedback();

    if (method !== '' && !minimumStatus.valid) {
        showMinimumFeedback(method);
    }

    if (isPickup) {
        shippingCalculated = true;

        if (deliveryCostLabel instanceof HTMLElement) {
            deliveryCostLabel.textContent = 'Retiro';
        }

        if (shippingCost instanceof HTMLElement) {
            shippingCost.textContent = 'Sin costo';
        }

        if (total instanceof HTMLElement) {
            total.textContent = subtotalFormatted();
        }

        if (payButton instanceof HTMLButtonElement) {
            payButton.disabled = !checkoutValid || !minimumStatus.valid;
            payButton.textContent = 'Continuar al pago';
            delete payButton.dataset.orderCreated;
        }

        if (deliveryNotice instanceof HTMLElement) {
            deliveryNotice.textContent =
                'Tras confirmar el pago, prepararemos tu pedido y te avisaremos cuando esté listo para retiro.';
        }

        return;
    }

    if (deliveryCostLabel instanceof HTMLElement) {
        deliveryCostLabel.textContent = 'Despacho';
    }

    if (deliveryNotice instanceof HTMLElement) {
        deliveryNotice.textContent =
            'El pedido quedará registrado como pendiente hasta confirmar el pago.';
    }

    resetShippingResult();
}

/* =========================================================
   MODALIDAD DE ENTREGA
   ========================================================= */

deliveryInputs.forEach(function (input) {
    if (input instanceof HTMLInputElement) {
        input.addEventListener('change', applyDeliveryMethod);
    }
});

/* =========================================================
   REGIÓN / COMUNA
   ========================================================= */

if (
    regionSelect instanceof HTMLSelectElement
    && communeSelect instanceof HTMLSelectElement
) {
    const communeOptions = Array.from(
        communeSelect.querySelectorAll('option[data-region]')
    );

    regionSelect.addEventListener('change', function () {
        const regionId = regionSelect.value;

        communeSelect.value = '';
        communeSelect.disabled = regionId === '';

        communeOptions.forEach(function (option) {
            if (!(option instanceof HTMLOptionElement)) {
                return;
            }

            const belongsToRegion = option.dataset.region === regionId;
            option.hidden = !belongsToRegion;
            option.disabled = !belongsToRegion;
        });

        if (currentDeliveryMethod() === 'despacho') {
            resetShippingResult();
        }
    });

    communeSelect.addEventListener('change', function () {
        if (currentDeliveryMethod() === 'despacho') {
            resetShippingResult();
        }
    });
}

/* =========================================================
   CAMBIOS EN DATOS DE DESPACHO
   ========================================================= */

if (checkoutForm instanceof HTMLFormElement) {
    checkoutForm.addEventListener('input', function (event) {
        if (currentDeliveryMethod() !== 'despacho') {
            return;
        }

        const target = event.target;

        if (
            target instanceof HTMLInputElement
            || target instanceof HTMLTextAreaElement
        ) {
            if (shippingCalculated) {
                resetShippingResult();
            }
        }
    });
}

/* =========================================================
   CALCULAR DESPACHO
   ========================================================= */

if (checkoutForm instanceof HTMLFormElement) {
    checkoutForm.addEventListener('submit', async function (event) {
        event.preventDefault();

        if (currentDeliveryMethod() !== 'despacho') {
            return;
        }

        if (!showMinimumFeedback('despacho')) {
            return;
        }

        if (!checkoutForm.reportValidity()) {
            return;
        }

        const submitButton = calculateButton;

        if (submitButton instanceof HTMLButtonElement) {
            submitButton.disabled = true;
            submitButton.setAttribute('aria-busy', 'true');
            submitButton.textContent = 'Calculando…';
        }

        resetShippingResult();

        try {
            const formData = new FormData(checkoutForm);
            const response = await fetch(checkoutForm.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await response.json();

            if (!response.ok || data.ok !== true) {
                throw new Error(
                    typeof data.mensaje === 'string'
                        ? data.mensaje
                        : 'No pudimos calcular el despacho.'
                );
            }

            if (shippingCost instanceof HTMLElement) {
                shippingCost.textContent = data.despacho.aplica_envio_gratis
                    ? 'Gratis'
                    : data.despacho.costo_despacho_formateado;
            }

            if (total instanceof HTMLElement) {
                total.textContent = data.despacho.total_formateado;
            }

            shippingCalculated = true;

            if (payButton instanceof HTMLButtonElement) {
                payButton.disabled = false;
            }

            let successMessage = 'Despacho a '
                + data.despacho.comuna
                + ': '
                + data.despacho.costo_despacho_formateado
                + '. Ya puedes continuar.';

            if (data.despacho.aplica_envio_gratis) {
                successMessage = '¡Tienes despacho gratis en '
                    + data.despacho.comuna
                    + '! Ya puedes continuar.';
            } else if (
                data.despacho.faltante_envio_gratis_formateado
                && Number(data.despacho.faltante_envio_gratis) > 0
            ) {
                successMessage += ' Te faltan '
                    + data.despacho.faltante_envio_gratis_formateado
                    + ' en productos para obtener despacho gratis en esta comuna.';
            }

            showFeedback(successMessage, 'success');
        } catch (error) {
            resetShippingResult();

            showFeedback(
                error instanceof Error
                    ? error.message
                    : 'No pudimos calcular el despacho.',
                'error'
            );
        } finally {
            if (submitButton instanceof HTMLButtonElement) {
                submitButton.disabled = false;
                submitButton.removeAttribute('aria-busy');
                submitButton.textContent = 'Calcular despacho';
            }
        }
    });
}

/* =========================================================
   CREAR PEDIDO E IR A WEBPAY
   ========================================================= */

if (payButton instanceof HTMLButtonElement) {
    payButton.addEventListener('click', async function () {
        if (!(checkoutForm instanceof HTMLFormElement)) {
            return;
        }

        const method = currentDeliveryMethod();

        if (method === '') {
            showFeedback('Selecciona una modalidad de entrega.', 'error');
            return;
        }

        if (!showMinimumFeedback(method)) {
            return;
        }

        if (method === 'despacho' && !shippingCalculated) {
            showFeedback('Primero debes calcular el despacho.', 'error');
            return;
        }

        if (!checkoutForm.reportValidity()) {
            return;
        }

        const createOrderUrl = checkoutForm.dataset.createOrderUrl;

        if (!createOrderUrl) {
            showFeedback('No encontramos la ruta para crear el pedido.', 'error');
            return;
        }

        payButton.disabled = true;
        payButton.setAttribute('aria-busy', 'true');
        payButton.textContent = 'Preparando pedido…';

        try {
            const formData = new FormData(checkoutForm);
            const response = await fetch(createOrderUrl, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await response.json();

            if (!response.ok || data.ok !== true) {
                throw new Error(
                    typeof data.mensaje === 'string'
                        ? data.mensaje
                        : 'No pudimos preparar el pedido.'
                );
            }

            const webpayUrl = typeof data.iniciar_webpay_url === 'string'
                ? data.iniciar_webpay_url
                : '';

            if (webpayUrl === '') {
                throw new Error(
                    'El pedido fue creado, pero no encontramos la ruta de Webpay.'
                );
            }

            showFeedback(
                'Pedido '
                + data.pedido.codigo_pedido
                + ' preparado. Redirigiendo a Webpay…',
                'success'
            );

            payButton.textContent = 'Redirigiendo…';
            payButton.dataset.orderCreated = 'true';
            window.location.href = webpayUrl;
        } catch (error) {
            payButton.disabled = false;
            payButton.textContent = 'Continuar al pago';

            showFeedback(
                error instanceof Error
                    ? error.message
                    : 'No pudimos preparar el pedido.',
                'error'
            );
        } finally {
            payButton.removeAttribute('aria-busy');
        }
    });
}

applyDeliveryMethod();
