(() => {
    'use strict';

    const root = document.querySelector('[data-history-experience]');
    if (!(root instanceof HTMLElement)) return;

    const book = root.querySelector('[data-history-book]');
    const stage = root.querySelector('[data-history-stage]');
    const sheets = [...root.querySelectorAll('[data-history-sheet]')];
    const previous = root.querySelector('[data-history-previous]');
    const next = root.querySelector('[data-history-next]');
    const progress = root.querySelector('[data-history-progress]');
    const mobileChapters = [...root.querySelectorAll('[data-mobile-chapter]')];

    const desktopMedia = window.matchMedia('(min-width: 900px)');
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    if (!(book instanceof HTMLElement) || !(stage instanceof HTMLElement) || sheets.length === 0) {
        return;
    }

    let current = 0;
    let busy = false;
    let mobileObserver = null;

    const formatProgress = () => {
        if (!(progress instanceof HTMLElement)) return;

        progress.textContent =
            `${String(current + 1).padStart(2, '0')}/${String(sheets.length).padStart(2, '0')}`;
    };

    const updateButtons = () => {
        if (previous instanceof HTMLButtonElement) {
            previous.disabled = busy || current === 0;
        }

        if (next instanceof HTMLButtonElement) {
            next.disabled = busy || current === sheets.length - 1;
        }
    };

    const setStageState = (direction = '') => {
        stage.classList.remove('is-turning-next', 'is-turning-previous');

        if (direction === 'next') {
            stage.classList.add('is-turning-next');
        }

        if (direction === 'previous') {
            stage.classList.add('is-turning-previous');
        }
    };

    const syncSheets = () => {
        sheets.forEach((sheet, index) => {
            if (!(sheet instanceof HTMLElement)) return;

            sheet.getAnimations().forEach((animation) => animation.cancel());
            sheet.style.removeProperty('z-index');
            sheet.classList.remove('is-turning');
            sheet.classList.toggle('is-flipped', index < current);
        });
    };

    const focusCurrentHeading = () => {
        const heading = book.querySelector(
            `[data-desktop-chapter="${current}"][data-page-type="story"] h2`
        );

        if (heading instanceof HTMLElement) {
            heading.focus({ preventScroll: true });
        }
    };

    const animateSheet = async (sheet, direction) => {
        if (!(sheet instanceof HTMLElement)) return;

        const nextTurn = direction === 'next';

        const keyframes = nextTurn
            ? [
                {
                    transform: 'translateZ(0) rotateY(0deg)',
                    offset: 0,
                },
                {
                    transform: 'translateZ(8px) rotateY(-24deg)',
                    offset: 0.18,
                },
                {
                    transform: 'translateZ(24px) rotateY(-82deg) scaleX(0.995)',
                    offset: 0.46,
                },
                {
                    transform: 'translateZ(18px) rotateY(-112deg) scaleX(0.993)',
                    offset: 0.58,
                },
                {
                    transform: 'translateZ(7px) rotateY(-158deg)',
                    offset: 0.82,
                },
                {
                    transform: 'translateZ(0) rotateY(-180deg)',
                    offset: 1,
                },
            ]
            : [
                {
                    transform: 'translateZ(0) rotateY(-180deg)',
                    offset: 0,
                },
                {
                    transform: 'translateZ(7px) rotateY(-158deg)',
                    offset: 0.18,
                },
                {
                    transform: 'translateZ(18px) rotateY(-112deg) scaleX(0.993)',
                    offset: 0.42,
                },
                {
                    transform: 'translateZ(24px) rotateY(-82deg) scaleX(0.995)',
                    offset: 0.54,
                },
                {
                    transform: 'translateZ(8px) rotateY(-24deg)',
                    offset: 0.82,
                },
                {
                    transform: 'translateZ(0) rotateY(0deg)',
                    offset: 1,
                },
            ];

        const animation = sheet.animate(keyframes, {
            duration: 760,
            easing: 'cubic-bezier(.45, .03, .18, 1)',
            fill: 'forwards',
        });

        try {
            await animation.finished;
        } catch {
            // La animación puede cancelarse al cambiar de breakpoint.
        }

        animation.cancel();
    };

    const goTo = async (target, moveFocus = true) => {
        if (!desktopMedia.matches || busy) return;

        const boundedTarget = Math.max(0, Math.min(sheets.length - 1, target));
        if (boundedTarget === current) return;

        if (Math.abs(boundedTarget - current) !== 1 || reduceMotion.matches) {
            current = boundedTarget;
            syncSheets();
            formatProgress();
            updateButtons();

            if (moveFocus) focusCurrentHeading();
            return;
        }

        const direction = boundedTarget > current ? 'next' : 'previous';
        const sheetIndex = direction === 'next' ? current : current - 1;
        const sheet = sheets[sheetIndex];

        if (!(sheet instanceof HTMLElement)) return;

        busy = true;
        updateButtons();
        setStageState(direction);

        sheet.style.zIndex = '200';
        sheet.classList.add('is-turning');

        await animateSheet(sheet, direction);

        if (direction === 'next') {
            sheet.classList.add('is-flipped');
            current += 1;
        } else {
            sheet.classList.remove('is-flipped');
            current -= 1;
        }

        sheet.classList.remove('is-turning');
        sheet.style.removeProperty('z-index');
        setStageState('');
        busy = false;

        formatProgress();
        updateButtons();

        if (moveFocus) focusCurrentHeading();
    };

    const toggleMemory = (button) => {
        if (!(button instanceof HTMLButtonElement)) return;

        const memoryId = button.getAttribute('aria-controls');
        const memory = memoryId ? document.getElementById(memoryId) : null;

        if (!(memory instanceof HTMLElement)) return;

        const expanded = button.getAttribute('aria-expanded') === 'true';

        button.setAttribute('aria-expanded', String(!expanded));
        button.textContent = expanded ? 'Leer este recuerdo' : 'Cerrar recuerdo';
        memory.hidden = expanded;
    };

    root.querySelectorAll('[data-memory-toggle]').forEach((button) => {
        button.addEventListener('click', () => toggleMemory(button));
    });

    previous?.addEventListener('click', () => goTo(current - 1));
    next?.addEventListener('click', () => goTo(current + 1));

    book.addEventListener('keydown', (event) => {
        if (!desktopMedia.matches || busy) return;

        if (event.key === 'ArrowLeft') {
            event.preventDefault();
            goTo(current - 1);
        }

        if (event.key === 'ArrowRight') {
            event.preventDefault();
            goTo(current + 1);
        }

        if (event.key === 'Home') {
            event.preventDefault();
            current = 0;
            syncSheets();
            formatProgress();
            updateButtons();
            focusCurrentHeading();
        }

        if (event.key === 'End') {
            event.preventDefault();
            current = sheets.length - 1;
            syncSheets();
            formatProgress();
            updateButtons();
            focusCurrentHeading();
        }
    });

    const teardownMobileObserver = () => {
        if (mobileObserver) {
            mobileObserver.disconnect();
            mobileObserver = null;
        }

        mobileChapters.forEach((chapter) => chapter.classList.remove('is-visible'));
    };

    const setupMobileObserver = () => {
        teardownMobileObserver();

        if (!('IntersectionObserver' in window)) {
            mobileChapters.forEach((chapter) => chapter.classList.add('is-visible'));
            return;
        }

        mobileObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                }
            });
        }, {
            threshold: 0.12,
            rootMargin: '0px 0px -7% 0px',
        });

        mobileChapters.forEach((chapter) => mobileObserver.observe(chapter));
    };

    const applyMode = () => {
        busy = false;
        setStageState('');

        if (desktopMedia.matches) {
            teardownMobileObserver();
            syncSheets();
            formatProgress();
            updateButtons();
            return;
        }

        sheets.forEach((sheet) => {
            if (!(sheet instanceof HTMLElement)) return;

            sheet.getAnimations().forEach((animation) => animation.cancel());
            sheet.style.removeProperty('z-index');
        });

        setupMobileObserver();
    };

    desktopMedia.addEventListener('change', applyMode);

    reduceMotion.addEventListener('change', () => {
        if (!busy) return;

        busy = false;
        setStageState('');
        syncSheets();
        updateButtons();
    });

    syncSheets();
    formatProgress();
    updateButtons();
    applyMode();
})();
