(() => {
    const toggle = document.querySelector('.admin-menu-toggle');
    const sidebar = document.querySelector('.admin-sidebar');
    const overlay = document.querySelector('[data-menu-close]');

    if (!toggle || !sidebar || !overlay) {
        return;
    }

    const setMenuOpen = (isOpen) => {
        document.body.classList.toggle('admin-menu-open', isOpen);
        toggle.setAttribute('aria-expanded', String(isOpen));
        toggle.setAttribute('aria-label', isOpen ? 'Cerrar menú administrativo' : 'Abrir menú administrativo');
        overlay.hidden = !isOpen;
    };

    toggle.addEventListener('click', () => {
        setMenuOpen(toggle.getAttribute('aria-expanded') !== 'true');
    });

    overlay.addEventListener('click', () => setMenuOpen(false));

    sidebar.addEventListener('click', (event) => {
        if (event.target.closest('a')) {
            setMenuOpen(false);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setMenuOpen(false);
        }
    });
})();
