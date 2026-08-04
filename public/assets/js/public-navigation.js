(() => {
    const header = document.querySelector('.site-header');
    const nav = header?.querySelector('#main-nav');
    const navToggle = header?.querySelector('.nav-toggle');
    const dropdown = header?.querySelector('[data-nav-dropdown]');
    const dropdownButton = dropdown?.querySelector('button');
    const search = header?.querySelector('#public-search');
    const searchButton = header?.querySelector('.header-action--search');
    const searchClose = search?.querySelector('.public-search__close');
    const searchInput = search?.querySelector('input');
    if (!header || !nav || !navToggle) return;

    nav.dataset.publicNavReady = 'true';
    const closeDropdown = () => {
        dropdown?.classList.remove('is-open');
        dropdownButton?.setAttribute('aria-expanded', 'false');
    };
    const toggleDropdown = () => {
        const open = !dropdown?.classList.contains('is-open');
        dropdown?.classList.toggle('is-open', open);
        dropdownButton?.setAttribute('aria-expanded', String(open));
    };
    const closeNav = () => {
        nav.classList.remove('open');
        navToggle.setAttribute('aria-expanded', 'false');
        closeDropdown();
    };
    const closeSearch = (restoreFocus = true) => {
        if (!search || !searchButton) return;
        search.hidden = true;
        searchButton.setAttribute('aria-expanded', 'false');
        if (restoreFocus) searchButton.focus();
    };

    navToggle.addEventListener('click', () => {
        const open = nav.classList.toggle('open');
        navToggle.setAttribute('aria-expanded', String(open));
    });
    dropdownButton?.addEventListener('click', (event) => {
        event.stopPropagation();
        toggleDropdown();
    });
    dropdown?.addEventListener('mouseenter', () => {
        if (matchMedia('(hover: hover)').matches) {
            dropdown.classList.add('is-open');
            dropdownButton?.setAttribute('aria-expanded', 'true');
        }
    });
    dropdown?.addEventListener('mouseleave', () => {
        if (matchMedia('(hover: hover)').matches) closeDropdown();
    });
    dropdown?.addEventListener('focusout', (event) => {
        if (!dropdown.contains(event.relatedTarget)) closeDropdown();
    });
    nav.addEventListener('click', (event) => {
        if (event.target.closest('a')) closeNav();
    });
    searchButton?.addEventListener('click', () => {
        if (!search) return;
        search.hidden = false;
        searchButton.setAttribute('aria-expanded', 'true');
        searchInput?.focus();
    });
    searchClose?.addEventListener('click', () => closeSearch());
    document.addEventListener('click', (event) => {
        if (!header.contains(event.target)) {
            closeDropdown();
            closeNav();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        closeDropdown();
        closeNav();
        if (search && !search.hidden) closeSearch();
    });
})();
