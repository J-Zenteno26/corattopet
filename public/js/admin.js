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
        detail.textContent = config.detail || '';
        detailWrap.hidden = !config.detail;
        primaryButton.textContent = config.primaryText || 'Aceptar';
        primaryButton.href = config.primaryUrl || '#';
        primaryAction = typeof config.onPrimary === 'function' ? config.onPrimary : null;
        secondaryButton.textContent = config.secondaryText || '';
        secondaryButton.hidden = !config.secondaryText;
        document.body.classList.add('admin-modal-open');
        setBackgroundInert(true);
        requestAnimationFrame(() => (primaryButton || closeButton).focus());
    };

    closeButton.addEventListener('click', close);
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

    window.AdminModal = { open, close };
    document.querySelectorAll('[data-admin-confirm-form]').forEach((button) => {
        button.addEventListener('click', () => {
            const form = document.getElementById(button.dataset.adminConfirmForm);
            if (!form) return;
            open({
                type: 'confirm', title: button.dataset.modalTitle || 'Confirmar acción',
                message: button.dataset.modalMessage || '¿Deseas continuar?',
                primaryText: button.dataset.modalPrimary || 'Confirmar', secondaryText: button.dataset.modalSecondary || 'Cancelar',
                closeOnOverlay: false, onPrimary: () => form.requestSubmit(),
            });
        });
    });
    const autoConfig = document.getElementById('admin-modal-auto-config');
    if (autoConfig) {
        try { open(JSON.parse(autoConfig.textContent)); } catch (error) { console.error('Invalid admin modal configuration.', error); }
    }
})();
