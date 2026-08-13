(() => {
    'use strict';

    const form = document.querySelector('[data-tariffs-form]');

    if (!form) {
        return;
    }

    const tariffInputs = Array.from(
        form.querySelectorAll('[data-tariff-input]')
    );
    const activeInputs = Array.from(
        form.querySelectorAll('[data-active-input]')
    );
    const freeShippingInputs = Array.from(
        form.querySelectorAll('[data-free-shipping-input]')
    );
    const counter = form.querySelector('[data-change-counter]');
    const saveButton = form.querySelector('[data-save-changes]');
    const discardButton = form.querySelector('[data-discard-changes]');
    const feedback = form.querySelector('[data-tariffs-feedback]');
    const csrfInput = form.querySelector('[data-csrf-token]');
    const saveUrl = form.dataset.saveUrl || '';

    let saving = false;

    const normalizeNumber = (value) => {
        const parsed = Number.parseInt(String(value), 10);

        return Number.isFinite(parsed) ? String(parsed) : '';
    };

    const isTariffChanged = (input) => {
        return normalizeNumber(input.value) !== normalizeNumber(
            input.dataset.original || ''
        );
    };

    const isActiveChanged = (input) => {
        const current = input.checked ? '1' : '0';

        return current !== (input.dataset.original || '0');
    };

    const isFreeShippingChanged = (input) => {
        return normalizeNumber(input.value) !== normalizeNumber(
            input.dataset.original || ''
        );
    };

    const setFeedback = (type, message) => {
        if (!feedback) {
            return;
        }

        feedback.hidden = false;
        feedback.classList.remove(
            'is-success',
            'is-error',
            'is-info'
        );
        feedback.classList.add(`is-${type}`);
        feedback.textContent = message;
    };

    const clearFeedback = () => {
        if (!feedback) {
            return;
        }

        feedback.hidden = true;
        feedback.textContent = '';
        feedback.classList.remove(
            'is-success',
            'is-error',
            'is-info'
        );
    };

    const updateRowState = (row) => {
        if (!row) {
            return;
        }

        const changed = Boolean(
            row.querySelector(
                '[data-tariff-input].is-edited, '
                + '[data-free-shipping-input].is-edited, '
                + '[data-active-input].is-edited'
            )
        );
        const status = row.querySelector('[data-row-status]');

        row.classList.toggle('is-edited', changed);

        if (status) {
            status.hidden = !changed;
        }
    };

    const getChangedControls = () => {
        return [
            ...tariffInputs.filter(isTariffChanged),
            ...freeShippingInputs.filter(isFreeShippingChanged),
            ...activeInputs.filter(isActiveChanged),
        ];
    };

    const refreshControlState = (control) => {
        let changed = false;

        if (control.matches('[data-tariff-input]')) {
            changed = isTariffChanged(control);
        } else if (control.matches('[data-free-shipping-input]')) {
            changed = isFreeShippingChanged(control);
        } else {
            changed = isActiveChanged(control);
        }

        control.classList.toggle('is-edited', changed);

        const wrapper = control.closest(
            '.shipping-tariffs-money, .shipping-tariffs-toggle'
        );

        if (wrapper) {
            wrapper.classList.toggle('is-edited', changed);
        }

        if (control.matches('[data-active-input]')) {
            const label = control
                .closest('.shipping-tariffs-toggle')
                ?.querySelector('[data-active-label]');

            if (label) {
                label.textContent = control.checked ? 'Sí' : 'No';
            }
        }

        updateRowState(control.closest('[data-tariff-row]'));
    };

    const refreshSummary = () => {
        const changedCount = getChangedControls().length;
        const hasChanges = changedCount > 0;

        if (counter) {
            counter.textContent = hasChanges
                ? `${changedCount} ${
                    changedCount === 1
                        ? 'cambio pendiente'
                        : 'cambios pendientes'
                }`
                : 'Sin cambios pendientes';

            counter.classList.toggle('has-changes', hasChanges);
        }

        if (saveButton) {
            saveButton.disabled = !hasChanges || saving;
            saveButton.textContent = saving
                ? 'Guardando…'
                : hasChanges
                    ? `Guardar ${changedCount} ${
                        changedCount === 1 ? 'cambio' : 'cambios'
                    }`
                    : 'Guardar cambios';
        }

        if (discardButton) {
            discardButton.disabled = !hasChanges || saving;
        }
    };

    const refreshAll = () => {
        [...tariffInputs, ...freeShippingInputs, ...activeInputs].forEach(
            refreshControlState
        );
        refreshSummary();
    };

    tariffInputs.forEach((input) => {
        input.addEventListener('input', () => {
            clearFeedback();
            refreshControlState(input);
            refreshSummary();
        });

        input.addEventListener('blur', () => {
            if (input.value.trim() === '') {
                return;
            }

            input.value = normalizeNumber(input.value);
            refreshControlState(input);
            refreshSummary();
        });
    });

    freeShippingInputs.forEach((input) => {
        input.addEventListener('input', () => {
            clearFeedback();
            refreshControlState(input);
            refreshSummary();
        });

        input.addEventListener('blur', () => {
            if (input.value.trim() !== '') {
                input.value = normalizeNumber(input.value);
            }

            refreshControlState(input);
            refreshSummary();
        });
    });

    activeInputs.forEach((input) => {
        input.addEventListener('change', () => {
            clearFeedback();
            refreshControlState(input);
            refreshSummary();
        });
    });

    discardButton?.addEventListener('click', () => {
        getChangedControls().forEach((control) => {
            if (
                control.matches('[data-tariff-input]')
                || control.matches('[data-free-shipping-input]')
            ) {
                control.value = control.dataset.original || '';
            } else {
                control.checked = (control.dataset.original || '0') === '1';
            }

            refreshControlState(control);
        });

        clearFeedback();
        refreshSummary();
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (saving) {
            return;
        }

        const changedControls = getChangedControls();

        if (changedControls.length === 0) {
            setFeedback('info', 'No hay cambios pendientes.');
            return;
        }

        const invalidTariff = changedControls.find((control) => {
            if (!control.matches('[data-tariff-input]')) {
                return false;
            }

            const value = Number.parseInt(control.value, 10);

            return (
                !Number.isInteger(value)
                || value < 0
                || value > 1000000
            );
        });

        if (invalidTariff) {
            invalidTariff.focus();
            setFeedback(
                'error',
                'Revisa el valor marcado. Debe ser un número entero igual o mayor que cero.'
            );
            return;
        }

        const invalidFreeShipping = changedControls.find((control) => {
            if (!control.matches('[data-free-shipping-input]')) {
                return false;
            }

            if (control.value.trim() === '') {
                return false;
            }

            const value = Number.parseInt(control.value, 10);

            return (
                !Number.isInteger(value)
                || value < 0
                || value > 10000000
            );
        });

        if (invalidFreeShipping) {
            invalidFreeShipping.focus();
            setFeedback(
                'error',
                'El monto de despacho gratis debe quedar vacío o ser un entero igual o mayor que cero.'
            );
            return;
        }

        const changes = changedControls.map((control) => {
            const communeId = Number.parseInt(
                control.dataset.communeId || '',
                10
            );

            if (control.matches('[data-active-input]')) {
                return {
                    tipo: 'estado',
                    id_comuna: communeId,
                    activo: control.checked,
                };
            }

            if (control.matches('[data-free-shipping-input]')) {
                return {
                    tipo: 'gratis_desde',
                    id_comuna: communeId,
                    monto_envio_gratis: control.value.trim() === ''
                        ? null
                        : Number.parseInt(control.value, 10),
                };
            }

            return {
                tipo: 'tarifa',
                id_comuna: communeId,
                peso_maximo_gramos: Number.parseInt(
                    control.dataset.weight || '',
                    10
                ),
                valor: Number.parseInt(control.value, 10),
            };
        });

        saving = true;
        clearFeedback();
        refreshSummary();

        try {
            const response = await fetch(saveUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    csrf_token: csrfInput?.value || '',
                    cambios: changes,
                }),
            });

            const result = await response.json().catch(() => null);

            if (!response.ok || !result?.ok) {
                throw new Error(
                    result?.message
                    || 'No fue posible guardar los cambios.'
                );
            }

            changedControls.forEach((control) => {
                if (
                    control.matches('[data-tariff-input]')
                    || control.matches('[data-free-shipping-input]')
                ) {
                    control.dataset.original = normalizeNumber(
                        control.value
                    );
                } else {
                    control.dataset.original = control.checked
                        ? '1'
                        : '0';
                }

                refreshControlState(control);
            });

            setFeedback(
                'success',
                result.message || 'Cambios guardados correctamente.'
            );
        } catch (error) {
            setFeedback(
                'error',
                error instanceof Error
                    ? error.message
                    : 'No fue posible guardar los cambios.'
            );
        } finally {
            saving = false;
            refreshSummary();
        }
    });

    window.addEventListener('beforeunload', (event) => {
        if (saving || getChangedControls().length === 0) {
            return;
        }

        event.preventDefault();
        event.returnValue = '';
    });

    refreshAll();
})();
