'use strict';

const assignmentForm = document.querySelector('[data-shipping-assignment-form]');

if (assignmentForm instanceof HTMLFormElement) {
    const selectAll = assignmentForm.querySelector('[data-select-all]');
    const productCheckboxes = Array.from(
        assignmentForm.querySelectorAll('[data-product-checkbox]:not(:disabled)')
    );
    const selectedCount = assignmentForm.querySelector('[data-selected-count]');
    const assignButton = assignmentForm.querySelector('[data-assign-button]');
    const categorySelect = assignmentForm.querySelector(
        'select[name="id_categoria_despacho"]'
    );

    const updateState = () => {
        const checked = productCheckboxes.filter((checkbox) => checkbox.checked);
        const count = checked.length;
        const hasCategory = categorySelect instanceof HTMLSelectElement
            && categorySelect.value !== '';

        if (selectedCount instanceof HTMLElement) {
            selectedCount.textContent = count === 1
                ? '1 seleccionado'
                : `${count} seleccionados`;
        }

        if (assignButton instanceof HTMLButtonElement) {
            assignButton.disabled = count === 0 || !hasCategory;
        }

        if (selectAll instanceof HTMLInputElement) {
            selectAll.checked = count > 0 && count === productCheckboxes.length;
            selectAll.indeterminate = count > 0 && count < productCheckboxes.length;
        }
    };

    if (selectAll instanceof HTMLInputElement) {
        selectAll.addEventListener('change', () => {
            productCheckboxes.forEach((checkbox) => {
                checkbox.checked = selectAll.checked;
            });

            updateState();
        });
    }

    productCheckboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', updateState);
    });

    categorySelect?.addEventListener('change', updateState);
    updateState();
}
