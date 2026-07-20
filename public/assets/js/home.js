(() => {
    const toggle = document.querySelector('.nav-toggle');
    const nav = document.querySelector('#main-nav');

    if (toggle && nav) {
        toggle.addEventListener('click', () => {
            const open = nav.classList.toggle('open');
            toggle.setAttribute('aria-expanded', String(open));
        });

        nav.addEventListener('click', () => {
            nav.classList.remove('open');
            toggle.setAttribute('aria-expanded', 'false');
        });
    }

    const newsletter = document.querySelector('.newsletter form');
    newsletter?.addEventListener('submit', (event) => event.preventDefault());
})();
