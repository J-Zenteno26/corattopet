(() => {
    const toggle = document.querySelector('.admin-menu-toggle');
    const sidebar = document.querySelector('.admin-sidebar');
    const overlay = document.querySelector('[data-menu-close]');
    if (toggle && sidebar && overlay) {
        const setMenuOpen = (isOpen) => {
            document.body.classList.toggle('admin-menu-open', isOpen);
            toggle.setAttribute('aria-expanded', String(isOpen));
            toggle.setAttribute('aria-label', isOpen ? 'Cerrar menú administrativo' : 'Abrir menú administrativo');
            overlay.hidden = !isOpen;
        };
        toggle.addEventListener('click', () => setMenuOpen(toggle.getAttribute('aria-expanded') !== 'true'));
        overlay.addEventListener('click', () => setMenuOpen(false));
        sidebar.addEventListener('click', (event) => { if (event.target.closest('a')) setMenuOpen(false); });
        document.addEventListener('keydown', (event) => { if (event.key === 'Escape') setMenuOpen(false); });
    }
})();

(() => {
    const modal = document.querySelector('[data-admin-modal]');
    if (!modal) return;

    const dialog = modal.querySelector('.admin-modal__dialog');
    const closeButton = modal.querySelector('[data-admin-modal-close]');
    const secondaryButton = modal.querySelector('[data-admin-modal-secondary]');
    const primaryButton = modal.querySelector('[data-admin-modal-primary]');
    const title = modal.querySelector('[data-admin-modal-title]');
    const message = modal.querySelector('[data-admin-modal-message]');
    const icon = modal.querySelector('[data-admin-modal-icon]');
    const referenceWrap = modal.querySelector('[data-admin-modal-reference-wrap]');
    const reference = modal.querySelector('[data-admin-modal-reference]');
    const detailWrap = modal.querySelector('[data-admin-modal-detail-wrap]');
    const detail = modal.querySelector('[data-admin-modal-detail]');
    const content = modal.querySelector('[data-admin-modal-content]');
    const actions = modal.querySelector('.admin-modal__actions');
    const icons = {
        success: '<svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg>',
        error: '<svg viewBox="0 0 24 24"><path d="M12 8v5m0 3h.01M4.5 19h15L12 4 4.5 19Z"/></svg>',
        warning: '<svg viewBox="0 0 24 24"><path d="M12 8v5m0 3h.01M4.5 19h15L12 4 4.5 19Z"/></svg>',
        info: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 11v6m0-9h.01"/></svg>',
        confirm: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.6 2.6 0 0 1 5 1c0 2-2.5 2-2.5 4m0 3h.01"/></svg>',
    };
    let previousFocus = null;
    let primaryAction = null;
    let closeOnOverlay = true;
    let inertElements = [];

    const focusable = () => [...dialog.querySelectorAll('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])')].filter((element) => !element.hidden);
    const setBackgroundInert = (isInert) => {
        if (isInert) {
            inertElements = [...document.body.children].filter((element) => element !== modal && !element.inert);
            inertElements.forEach((element) => { element.inert = true; });
        } else {
            inertElements.forEach((element) => { element.inert = false; });
            inertElements = [];
        }
    };
    const close = () => {
        if (!modal.classList.contains('is-open')) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('admin-modal-open');
        setBackgroundInert(false);
        primaryAction = null;
        if (previousFocus instanceof HTMLElement) previousFocus.focus();
    };
    const open = (config = {}) => {
        const type = ['success', 'error', 'warning', 'info', 'confirm'].includes(config.type) ? config.type : 'info';
        previousFocus = document.activeElement;
        closeOnOverlay = config.closeOnOverlay !== false;
        modal.className = `admin-modal admin-modal--${type} is-open`;
        modal.setAttribute('aria-hidden', 'false');
        title.textContent = config.title || 'Información';
        message.textContent = config.message || '';
        icon.innerHTML = icons[type];
        reference.textContent = config.reference || '';
        referenceWrap.hidden = !config.reference;
        const detailText = String(config.detail || '').trim();
        detail.textContent = detailText;
        detailWrap.hidden = !detailText || detailText === String(config.message || '').trim();
        content.replaceChildren();
        content.hidden = true;
        actions.hidden = false;
        if (config.contentTemplate) {
            const template = document.querySelector(config.contentTemplate);
            if (template instanceof HTMLTemplateElement) {
                content.append(template.content.cloneNode(true));
                content.hidden = false;
                actions.hidden = config.hideDefaultActions !== false;
            }
        }
        primaryButton.textContent = config.primaryText || 'Aceptar';
        primaryButton.href = config.primaryUrl || '#';
        primaryButton.className = `admin-modal__button admin-modal__button--primary${config.destructive || type === 'error' ? ' admin-modal__button--destructive' : ''}`;
        primaryAction = typeof config.onPrimary === 'function' ? config.onPrimary : null;
        secondaryButton.textContent = config.secondaryText || '';
        secondaryButton.hidden = !config.secondaryText;
        document.body.classList.add('admin-modal-open');
        setBackgroundInert(true);
        requestAnimationFrame(() => {
            const initialFocus = config.initialFocus ? content.querySelector(config.initialFocus) : null;
            (initialFocus || primaryButton || closeButton).focus();
        });
    };

    closeButton.addEventListener('click', close);
    modal.addEventListener('click', (event) => {
        if (event.target.closest('[data-admin-modal-cancel]')) close();
    });
    secondaryButton.addEventListener('click', close);
    modal.querySelector('[data-admin-modal-overlay]').addEventListener('click', () => { if (closeOnOverlay) close(); });
    primaryButton.addEventListener('click', (event) => {
        if (primaryAction) { event.preventDefault(); const action = primaryAction; primaryAction = null; action(); }
        else if (!primaryButton.getAttribute('href') || primaryButton.getAttribute('href') === '#') { event.preventDefault(); close(); }
    });
    document.addEventListener('keydown', (event) => {
        if (!modal.classList.contains('is-open')) return;
        if (event.key === 'Escape') { event.preventDefault(); close(); }
        if (event.key === 'Tab') {
            const elements = focusable();
            if (elements.length === 0) { event.preventDefault(); dialog.focus(); return; }
            const first = elements[0]; const last = elements[elements.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        }
    });

    const confirm = (config = {}) => open({
        ...config,
        type: 'confirm',
        primaryText: config.confirmText || config.primaryText || 'Confirmar',
        secondaryText: config.cancelText || config.secondaryText || 'Cancelar',
        closeOnOverlay: config.closeOnOverlay ?? false,
        onPrimary: config.onConfirm || config.onPrimary,
    });

    window.AdminModal = { open, close, confirm };
    document.querySelectorAll('[data-admin-confirm-form]').forEach((button) => {
        button.addEventListener('click', () => {
            const form = document.getElementById(button.dataset.adminConfirmForm);
            if (!form) return;
            confirm({
                type: 'confirm', title: button.dataset.modalTitle || 'Confirmar acción',
                message: button.dataset.modalMessage || '¿Deseas continuar?',
                confirmText: button.dataset.modalPrimary || 'Confirmar', cancelText: button.dataset.modalSecondary || 'Cancelar',
                destructive: button.dataset.modalDestructive === 'true',
                onConfirm: () => form.requestSubmit(),
            });
        });
    });
    const autoConfig = document.getElementById('admin-modal-auto-config');
    if (autoConfig) {
        try { open(JSON.parse(autoConfig.textContent)); } catch (error) { console.error('Invalid admin modal configuration.', error); }
    }

    const brandState = document.getElementById('brand-modal-state');
    const openBrandForm = (config) => {
        const mode = config.mode === 'edit' ? 'edit' : 'create';
        const titleText = config.hasErrors
            ? (mode === 'edit' ? 'No fue posible actualizar la marca' : 'No fue posible guardar la marca')
            : (mode === 'edit' ? 'Editar marca' : 'Nueva marca');
        open({
            type: config.hasErrors ? 'error' : 'info',
            title: titleText,
            message: config.hasErrors
                ? (config.hasFieldErrors ? 'Revisa los campos marcados antes de continuar.' : 'No se pudo completar la acción.')
                : (mode === 'edit' ? 'Actualiza los datos de la marca.' : 'Registra una marca para asociarla a los productos del catálogo.'),
            detail: config.detail || '', reference: config.reference || '',
            contentTemplate: '#brand-form-template', initialFocus: '[name="nombre"]',
            hideDefaultActions: true,
        });
        const form = content.querySelector('[data-brand-form]');
        if (!form) return;
        form.action = mode === 'edit' ? form.dataset.editAction : form.dataset.createAction;
        form.querySelector('[name="id_marca"]').value = mode === 'edit' ? String(config.id || '') : '';
        form.querySelector('[name="nombre"]').value = config.name || '';
        form.querySelector('[name="activo"]').checked = mode === 'edit' ? config.active !== false : true;
        form.querySelector('[data-brand-submit]').textContent = mode === 'edit' ? 'Guardar cambios' : 'Guardar marca';
        if (!config.hasErrors) {
            form.querySelectorAll('.admin-field--invalid').forEach((field) => field.classList.remove('admin-field--invalid'));
            form.querySelectorAll('.admin-field__error').forEach((error) => error.remove());
            const nameInput = form.querySelector('[name="nombre"]');
            nameInput.removeAttribute('aria-invalid');
            nameInput.setAttribute('aria-describedby', 'marca-modal-help');
        }
    };
    document.querySelectorAll('[data-brand-modal-open]').forEach((button) => {
        button.addEventListener('click', () => openBrandForm({
            mode: button.dataset.brandModalOpen,
            id: button.dataset.brandId || '', name: button.dataset.brandName || '',
            active: button.dataset.brandActive !== '0', hasErrors: false,
        }));
    });
    if (brandState) {
        try { openBrandForm(JSON.parse(brandState.textContent)); } catch (error) { console.error('Invalid brand modal state.', error); }
    }

    document.querySelectorAll('[data-stock-confirm-form]').forEach((button) => {
        const stockForm = document.getElementById(button.dataset.stockConfirmForm);
        if (stockForm) {
            stockForm.addEventListener('submit', (event) => {
                if (stockForm.dataset.stockConfirmed === '1') {
                    delete stockForm.dataset.stockConfirmed;
                    return;
                }
                event.preventDefault();
                button.click();
            });
        }
        button.addEventListener('click', () => {
            const form = document.getElementById(button.dataset.stockConfirmForm);
            if (!form || !form.reportValidity()) return;
            const type = form.querySelector('[name="tipo_movimiento"]');
            const quantity = form.querySelector('[name="cantidad"]');
            const reason = form.querySelector('[name="motivo"]');
            const observation = form.querySelector('[name="observacion"]');
            const movementType = type.value;
            const fractionable = button.dataset.stockFractionable === '1';
            const numericQuantity = Number.parseInt(quantity.value, 10);
            const formattedQuantity = fractionable
                ? (numericQuantity >= 1000 ? `${new Intl.NumberFormat('es-CL', { maximumFractionDigits: 3 }).format(numericQuantity / 1000)} kg` : `${numericQuantity} g`)
                : `${numericQuantity} ${numericQuantity === 1 ? 'unidad' : 'unidades'}`;
            const settings = {
                entrada: { title: 'Confirmar entrada de stock', message: 'Se sumará esta cantidad al stock actual del producto.', primary: 'Registrar entrada' },
                salida: { title: 'Confirmar salida de stock', message: `Se descontará esta cantidad del stock actual del producto. Esta acción quedará registrada en el historial.${fractionable ? ' Recuerda que los alimentos descuentan stock en gramos.' : ''}`, primary: 'Registrar salida' },
                ajuste: { title: 'Confirmar ajuste de stock', message: 'Se registrará un ajuste manual de stock. Revisa que la cantidad y el motivo sean correctos.', primary: 'Registrar ajuste' },
            };
            const config = settings[movementType];
            if (!config) return;
            confirm({
                title: config.title, message: config.message,
                confirmText: config.primary, cancelText: 'Cancelar',
                destructive: movementType === 'salida', closeOnOverlay: false,
                contentTemplate: '#stock-confirm-template', hideDefaultActions: false,
                onConfirm: () => {
                    form.dataset.stockConfirmed = '1';
                    form.requestSubmit();
                },
            });
            const summary = content.querySelector('[data-stock-confirm-summary]');
            if (!summary) return;
            summary.querySelector('[data-stock-summary-product]').textContent = button.dataset.stockProductName || '';
            summary.querySelector('[data-stock-summary-type]').textContent = type.options[type.selectedIndex]?.text || '';
            summary.querySelector('[data-stock-summary-quantity]').textContent = formattedQuantity;
            summary.querySelector('[data-stock-summary-reason]').textContent = reason.options[reason.selectedIndex]?.text || '';
            const observationRow = summary.querySelector('[data-stock-summary-observation-row]');
            summary.querySelector('[data-stock-summary-observation]').textContent = observation.value.trim();
            observationRow.hidden = observation.value.trim() === '';
        });
    });

    document.querySelectorAll('[data-image-preview-input]').forEach((input) => {
        const targetId = input.dataset.previewTarget;
        const preview = targetId ? document.getElementById(targetId) : null;
        const container = preview?.parentElement;
        const placeholder = container?.querySelector('[data-image-preview-placeholder]');
        if (!preview) return;

        let objectUrl = '';
        input.addEventListener('change', () => {
            if (objectUrl) {
                URL.revokeObjectURL(objectUrl);
                objectUrl = '';
            }
            const file = input.files?.[0];
            if (!file || !['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                preview.removeAttribute('src');
                preview.hidden = true;
                if (placeholder) placeholder.hidden = false;
                return;
            }
            objectUrl = URL.createObjectURL(file);
            preview.src = objectUrl;
            preview.hidden = false;
            if (placeholder) placeholder.hidden = true;
        });
        window.addEventListener('pagehide', () => {
            if (objectUrl) URL.revokeObjectURL(objectUrl);
        }, { once: true });
    });

    const createProduct = document.querySelector('.admin-product-create-layout');
    if (createProduct) {
        const name = createProduct.querySelector('[name="nombre"]');
        const sku = createProduct.querySelector('[name="sku"]');
        const category = createProduct.querySelector('[name="id_categoria"]');
        const brand = createProduct.querySelector('[name="id_marca"]');
        const stock = createProduct.querySelector('[name="stock_inicial"]');
        const price = createProduct.querySelector('[name="precio_venta"]');
        const preview = {
            name: createProduct.querySelector('[data-create-preview-name]'),
            sku: createProduct.querySelector('[data-create-preview-sku]'),
            category: createProduct.querySelector('[data-create-preview-category]'),
            brand: createProduct.querySelector('[data-create-preview-brand]'),
            type: createProduct.querySelector('[data-create-preview-type]'),
            pet: createProduct.querySelector('[data-create-preview-pet]'),
            stock: createProduct.querySelector('[data-create-preview-stock]'),
            price: createProduct.querySelector('[data-create-preview-price]'),
        };
        const selectedLabel = (select, fallback) => {
            const option = select?.selectedOptions?.[0];
            return option?.value ? option.textContent.trim() : fallback;
        };
        const updateCatalogPreview = () => {
            const fractionable = category?.selectedOptions?.[0]?.dataset.fraccionable === '1';
            const petInput = createProduct.querySelector('[name="tipo_mascota"]:checked');
            const petLabel = petInput?.closest('label')?.textContent.trim() || 'Por definir';
            const stockValue = Number.parseInt(stock?.value || '0', 10);
            const priceValue = Number.parseInt(String(price?.value || '').replace(/\D/g, ''), 10);
            preview.name.textContent = name?.value.trim() || 'Nuevo producto';
            preview.sku.textContent = sku?.value.trim() || 'Sin SKU';
            preview.category.textContent = selectedLabel(category, 'Categoría pendiente');
            preview.brand.textContent = selectedLabel(brand, 'Marca pendiente');
            preview.type.textContent = fractionable ? 'Alimento fraccionable' : 'Producto por unidad';
            preview.pet.textContent = petLabel;
            preview.stock.textContent = (Number.isNaN(stockValue) ? 0 : stockValue) + (fractionable ? ' g' : ' unidades');
            preview.price.textContent = fractionable
                ? 'Precio por presentación'
                : (Number.isNaN(priceValue) ? 'Por definir' : '$' + new Intl.NumberFormat('es-CL').format(priceValue));
        };
        createProduct.addEventListener('input', updateCatalogPreview);
        createProduct.addEventListener('change', updateCatalogPreview);
        updateCatalogPreview();
    }
})();
