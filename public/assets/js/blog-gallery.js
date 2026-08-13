(() => {
    'use strict';

    document.querySelectorAll('[data-blog-gallery]').forEach((gallery) => {
        const slides = Array.from(gallery.querySelectorAll('[data-blog-gallery-slide]'));
        const previous = gallery.querySelector('[data-blog-gallery-previous]');
        const next = gallery.querySelector('[data-blog-gallery-next]');
        const current = gallery.querySelector('[data-blog-gallery-current]');
        if (slides.length < 2 || !previous || !next || !current) return;

        let activeIndex = 0;
        let touchStartX = null;

        const show = (index) => {
            activeIndex = (index + slides.length) % slides.length;
            slides.forEach((slide, slideIndex) => {
                slide.hidden = slideIndex !== activeIndex;
            });
            current.textContent = String(activeIndex + 1);
        };

        previous.addEventListener('click', () => show(activeIndex - 1));
        next.addEventListener('click', () => show(activeIndex + 1));
        gallery.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowLeft') {
                event.preventDefault();
                show(activeIndex - 1);
            } else if (event.key === 'ArrowRight') {
                event.preventDefault();
                show(activeIndex + 1);
            }
        });
        gallery.addEventListener('touchstart', (event) => {
            touchStartX = event.touches[0]?.clientX ?? null;
        }, { passive: true });
        gallery.addEventListener('touchend', (event) => {
            if (touchStartX === null) return;
            const touchEndX = event.changedTouches[0]?.clientX ?? touchStartX;
            const distance = touchEndX - touchStartX;
            touchStartX = null;
            if (Math.abs(distance) < 45) return;
            show(activeIndex + (distance < 0 ? 1 : -1));
        }, { passive: true });
    });
})();
