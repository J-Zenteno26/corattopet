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

    document.querySelectorAll('[data-faq]').forEach((faq) => {
        const items = faq.querySelectorAll('.faq-item');

        const setPanelHeight = (item, open) => {
            const panel = item.querySelector('.faq-answer');
            if (!panel) return;
            panel.style.maxHeight = open ? `${panel.scrollHeight}px` : '0px';
        };

        items.forEach((item) => {
            const button = item.querySelector('.faq-question');
            setPanelHeight(item, item.classList.contains('is-open'));

            button?.addEventListener('click', () => {
                const nextOpen = !item.classList.contains('is-open');
                item.classList.toggle('is-open', nextOpen);
                button.setAttribute('aria-expanded', String(nextOpen));
                setPanelHeight(item, nextOpen);
            });
        });

        window.addEventListener('resize', () => {
            items.forEach((item) => {
                if (item.classList.contains('is-open')) setPanelHeight(item, true);
            });
        });
    });

    document.querySelectorAll('[data-slider="selection"]').forEach((viewport) => {
        const section = viewport.closest('.home-selection');
        const rail = viewport.querySelector('.home-selection__rail');
        const previous = section?.querySelector('[data-slider-prev="selection"]');
        const next = section?.querySelector('[data-slider-next="selection"]');
        const progress = section?.querySelector('[data-slider-progress="selection"]');
        if (!rail || !previous || !next || !progress) return;
        const products = [...rail.querySelectorAll('.selection-product')];

        const scrollLimit = () => Math.max(0, viewport.scrollWidth - viewport.clientWidth);
        const syncCardWidth = () => {
            if (!window.matchMedia('(min-width: 75rem)').matches) {
                rail.style.removeProperty('--selection-card-width');
                return;
            }
            const gap = parseFloat(getComputedStyle(rail).columnGap) || 0;
            const width = (viewport.clientWidth - gap * 4) / 4.1;
            rail.style.setProperty('--selection-card-width', `${Math.max(250, width)}px`);
        };
        const updatePeekingCards = () => {
            const viewportBounds = viewport.getBoundingClientRect();
            products.forEach((product) => {
                const bounds = product.getBoundingClientRect();
                const visible = Math.max(0, Math.min(bounds.right, viewportBounds.right) - Math.max(bounds.left, viewportBounds.left));
                const ratio = bounds.width > 0 ? visible / bounds.width : 0;
                product.classList.toggle('is-peeking', ratio > 0 && ratio < 0.55);
            });
        };
        const updateControls = () => {
            const limit = scrollLimit();
            const current = Math.min(limit, Math.max(0, viewport.scrollLeft));
            const ratio = limit > 0 ? current / limit : 1;
            progress.style.setProperty('--selection-progress', String(ratio));
            previous.disabled = current <= 2;
            next.disabled = current >= limit - 2;
            updatePeekingCards();
        };
        const step = () => {
            const product = rail.querySelector('.selection-product');
            if (!product) return viewport.clientWidth * 0.8;
            const gap = parseFloat(getComputedStyle(rail).columnGap) || 0;
            return product.getBoundingClientRect().width + gap;
        };
        const move = (direction) => viewport.scrollBy({
            left: direction * step(),
            behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'
        });

        previous.addEventListener('click', () => move(-1));
        next.addEventListener('click', () => move(1));
        viewport.addEventListener('scroll', updateControls, { passive: true });
        window.addEventListener('resize', () => {
            syncCardWidth();
            updateControls();
        });
        syncCardWidth();
        updateControls();
    });
})();

document.addEventListener('DOMContentLoaded', () => {
    initHomeTrialShowcase();

    const { gsap, ScrollTrigger } = window;
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (!gsap) return;
    if (ScrollTrigger) gsap.registerPlugin(ScrollTrigger);

    // Adaptado del helper oficial horizontalLoop() de GSAP.
    const horizontalLoop = (items, config = {}) => {
        const elements = gsap.utils.toArray(items);
        if (!elements.length) return null;

        const timeline = gsap.timeline({
            repeat: config.repeat,
            paused: config.paused,
            defaults: { ease: 'none' },
            onReverseComplete: () => timeline.totalTime(timeline.rawTime() + timeline.duration() * 100)
        });
        const length = elements.length;
        const startX = elements[0].offsetLeft;
        const times = [];
        const widths = [];
        const xPercents = [];
        const pixelsPerSecond = (config.speed || 1) * 100;
        const snap = config.snap === false ? (value) => value : gsap.utils.snap(config.snap || 1);
        let currentIndex = 0;

        gsap.set(elements, {
            xPercent: (index, element) => {
                const width = widths[index] = parseFloat(gsap.getProperty(element, 'width', 'px'));
                xPercents[index] = snap(
                    (parseFloat(gsap.getProperty(element, 'x', 'px')) / width) * 100
                    + gsap.getProperty(element, 'xPercent')
                );
                return xPercents[index];
            }
        });
        gsap.set(elements, { x: 0 });

        const last = elements[length - 1];
        const totalWidth = last.offsetLeft
            + (xPercents[length - 1] / 100) * widths[length - 1]
            - startX
            + last.offsetWidth * gsap.getProperty(last, 'scaleX')
            + (parseFloat(config.paddingRight) || 0);

        elements.forEach((element, index) => {
            const currentX = (xPercents[index] / 100) * widths[index];
            const distanceToStart = element.offsetLeft + currentX - startX;
            const distanceToLoop = distanceToStart + widths[index] * gsap.getProperty(element, 'scaleX');

            timeline.to(element, {
                xPercent: snap(((currentX - distanceToLoop) / widths[index]) * 100),
                duration: distanceToLoop / pixelsPerSecond
            }, 0).fromTo(element, {
                xPercent: snap(((currentX - distanceToLoop + totalWidth) / widths[index]) * 100)
            }, {
                xPercent: xPercents[index],
                duration: (totalWidth - distanceToLoop) / pixelsPerSecond,
                immediateRender: false
            }, distanceToLoop / pixelsPerSecond).add(`label${index}`, distanceToStart / pixelsPerSecond);

            times[index] = distanceToStart / pixelsPerSecond;
        });

        const toIndex = (index, vars = {}) => {
            if (Math.abs(index - currentIndex) > length / 2) {
                index += index > currentIndex ? -length : length;
            }
            const nextIndex = gsap.utils.wrap(0, length, index);
            let time = times[nextIndex];
            if ((time > timeline.time()) !== (index > currentIndex)) {
                vars.modifiers = { time: gsap.utils.wrap(0, timeline.duration()) };
                time += timeline.duration() * (index > currentIndex ? 1 : -1);
            }
            currentIndex = nextIndex;
            vars.overwrite = true;
            return timeline.tweenTo(time, vars);
        };

        timeline.next = (vars) => toIndex(currentIndex + 1, vars);
        timeline.previous = (vars) => toIndex(currentIndex - 1, vars);
        timeline.current = () => currentIndex;
        timeline.toIndex = toIndex;
        timeline.times = times;
        timeline.progress(1, true).progress(0, true);
        return timeline;
    };

    const marquee = document.querySelector('.brand-marquee__viewport');
    const brandGroups = marquee?.querySelectorAll('.brand-marquee__group');
    const brandItems = marquee ? gsap.utils.toArray('.brand-marquee__item', marquee) : [];
    const brandLogos = marquee ? gsap.utils.toArray('.brand-marquee__logo', marquee) : [];

    if (marquee && brandGroups?.length && !reducedMotion) {
        const loop = horizontalLoop(brandGroups, {
            repeat: -1,
            speed: window.matchMedia('(max-width: 640px)').matches ? 0.35 : 0.42,
            snap: false
        });

        marquee.addEventListener('pointerenter', () => {
            gsap.to(loop, { timeScale: 0.15, duration: 0.65, ease: 'power2.out', overwrite: true });
        });
        marquee.addEventListener('pointerleave', () => {
            gsap.to(loop, { timeScale: 1, duration: 0.75, ease: 'power2.out', overwrite: true });
        });

        const desktopFinePointer = window.matchMedia('(min-width: 641px) and (hover: hover) and (pointer: fine)');
        const emphasizeBrand = (item) => {
            const selected = item.querySelector('.brand-marquee__logo');
            if (!selected) return;
            item.style.zIndex = '4';
            if (desktopFinePointer.matches) {
                gsap.to(brandLogos.filter((logo) => logo !== selected), {
                    opacity: 0.42,
                    scale: 0.96,
                    duration: 0.28,
                    ease: 'power2.out',
                    overwrite: true
                });
                gsap.to(selected, {
                    opacity: 1,
                    scale: 1.16,
                    y: -7,
                    filter: 'drop-shadow(0 10px 14px rgba(69, 43, 22, .15))',
                    duration: 0.28,
                    ease: 'power2.out',
                    overwrite: true
                });
            } else {
                gsap.to(selected, { scale: 1.07, y: -3, duration: 0.28, ease: 'power2.out', overwrite: true });
            }
        };
        const restoreBrands = (item) => {
            item.style.zIndex = '';
            gsap.to(brandLogos, {
                opacity: 1,
                scale: 1,
                y: 0,
                filter: 'drop-shadow(0 0 0 rgba(0, 0, 0, 0))',
                duration: 0.28,
                ease: 'power2.out',
                overwrite: true,
                clearProps: 'opacity,transform,filter'
            });
        };

        brandItems.forEach((item) => {
            item.addEventListener('pointerenter', () => emphasizeBrand(item));
            item.addEventListener('pointerleave', () => restoreBrands(item));
            item.addEventListener('focus', () => emphasizeBrand(item));
            item.addEventListener('blur', () => restoreBrands(item));
        });
    }

    const productCards = gsap.utils.toArray('.home-category-card');
    const productCurve = document.querySelector('.home-categories__curve path');

    if (productCards.length && !reducedMotion && ScrollTrigger) {
        gsap.set(productCards, {
            autoAlpha: 0,
            y: 65,
            scale: 0.78,
            rotation: (index) => index % 2 ? 3 : -3,
            transformOrigin: 'center bottom'
        });

        ScrollTrigger.batch(productCards, {
            start: 'top 88%',
            once: true,
            invalidateOnRefresh: true,
            onEnter: (batch) => gsap.to(batch, {
                autoAlpha: 1,
                y: 0,
                scale: 1,
                rotation: 0,
                duration: 0.9,
                ease: 'power3.out',
                stagger: 0.12,
                overwrite: true
            })
        });

        if (productCurve) {
            gsap.to(productCurve, {
                strokeDashoffset: 0,
                duration: 1.5,
                ease: 'power2.out',
                scrollTrigger: {
                    trigger: '.home-categories',
                    start: 'top 75%',
                    once: true
                }
            });
        }
    } else {
        gsap.set(productCards, { autoAlpha: 1, clearProps: 'transform' });
        if (productCurve) gsap.set(productCurve, { strokeDashoffset: 0 });
    }

    if (!reducedMotion && window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
        productCards.forEach((card) => {
            const visual = card.querySelector('.home-category-card__visual');
            if (!visual) return;
            const moveX = gsap.quickTo(visual, 'x', { duration: 0.45, ease: 'power3.out' });
            const moveY = gsap.quickTo(visual, 'y', { duration: 0.45, ease: 'power3.out' });
            const rotate = gsap.quickTo(visual, 'rotation', { duration: 0.5, ease: 'power3.out' });

            card.addEventListener('pointermove', (event) => {
                const bounds = card.getBoundingClientRect();
                const xRatio = (event.clientX - bounds.left) / bounds.width - 0.5;
                const yRatio = (event.clientY - bounds.top) / bounds.height - 0.5;
                moveX(xRatio * 16);
                moveY(yRatio * 16);
                rotate(xRatio * 10);
            });
            card.addEventListener('pointerleave', () => {
                moveX(0);
                moveY(0);
                rotate(0);
            });
        });
    }
});

document.addEventListener('DOMContentLoaded', () => {
    const message = document.querySelector('.home-hero__origin');

    if (!message) {
        return;
    }

    const reducedMotion = window.matchMedia(
        '(prefers-reduced-motion: reduce)'
    ).matches;

    const revealMessage = () => {
        message.classList.add('is-visible');

        if (reducedMotion || typeof window.gsap === 'undefined') {
            message.classList.add('is-highlighted');
            return;
        }

        const photo = message.querySelector('.home-hero__origin-photo');
        const copyItems = message.querySelectorAll(
            '.home-hero__origin-copy > *'
        );
        const arrow = message.querySelector('.home-hero__origin-arrow');

        window.gsap.timeline({
            defaults: {
                overwrite: 'auto'
            },
            onComplete: () => {
                window.gsap.set(message, {
                    clearProps: 'transform,opacity'
                });

                message.classList.add('is-highlighted');
            }
        })
            .fromTo(
                message,
                {
                    xPercent: 112,
                    rotation: 8,
                    scale: 0.76,
                    opacity: 0,
                    transformOrigin: 'right center'
                },
                {
                    xPercent: -3,
                    rotation: -2,
                    scale: 1.04,
                    opacity: 1,
                    duration: 0.72,
                    ease: 'power3.out'
                }
            )
            .to(message, {
                xPercent: 1.3,
                rotation: 0.9,
                scale: 0.985,
                duration: 0.18,
                ease: 'power1.inOut'
            })
            .to(message, {
                xPercent: 0,
                rotation: 0,
                scale: 1,
                duration: 0.42,
                ease: 'elastic.out(1, 0.45)'
            })
            .from(
                photo,
                {
                    x: 30,
                    rotation: 8,
                    scale: 0.78,
                    opacity: 0,
                    duration: 0.45,
                    ease: 'back.out(2.2)'
                },
                0.24
            )
            .from(
                copyItems,
                {
                    x: 24,
                    opacity: 0,
                    duration: 0.36,
                    stagger: 0.07,
                    ease: 'power2.out'
                },
                0.28
            )
            .from(
                arrow,
                {
                    x: 34,
                    rotation: 140,
                    scale: 0.4,
                    opacity: 0,
                    duration: 0.5,
                    ease: 'back.out(2.8)'
                },
                0.34
            )
            .to(
                arrow,
                {
                    x: -4,
                    duration: 0.12,
                    repeat: 3,
                    yoyo: true,
                    ease: 'sine.inOut'
                },
                '>-0.08'
            );
    };

    window.setTimeout(
        revealMessage,
        reducedMotion ? 0 : 2000
    );
});

document.addEventListener('DOMContentLoaded', () => {
    const guide = document.querySelector('.home-guide');
    if (!guide) return;

    const buttons = Array.from(guide.querySelectorAll('.home-need-orbit'));
    const cards = Array.from(guide.querySelectorAll('.home-need-card'));
    const scoop = guide.querySelector('.home-guide__scoop');
    const bowl = guide.querySelector('.home-guide__center');
    const baseBowl = guide.querySelector('.home-guide__bowl--base');
    const filledBowl = guide.querySelector('.home-guide__bowl--filled');
    const fallingFood = guide.querySelector('.home-guide__falling-food');
    const sparkles = guide.querySelectorAll('.home-guide__sparkles span');
    const heading = guide.querySelector('.home-section-heading');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const { gsap, ScrollTrigger } = window;

    const setActiveCard = (button) => {
        buttons.forEach((item) => {
            const active = item === button;
            item.setAttribute('aria-pressed', String(active));
            item.closest('.home-need-card')?.classList.toggle('is-active', active);
        });
    };

    buttons.forEach((button) => button.addEventListener('click', () => setActiveCard(button)));
    const activeButton = buttons.find((button) => button.getAttribute('aria-pressed') === 'true');
    if (activeButton) setActiveCard(activeButton);

    if (reducedMotion || !gsap) {
        if (baseBowl) baseBowl.style.opacity = '0';
        if (fallingFood) fallingFood.style.opacity = '0';
        if (filledBowl) filledBowl.style.opacity = '1';
        sparkles.forEach((sparkle) => {
            sparkle.style.opacity = '.32';
        });
        return;
    }

    if (ScrollTrigger) gsap.registerPlugin(ScrollTrigger);
    const floatLayers = buttons.map((button) => button.querySelector('.home-need-orbit__float')).filter(Boolean);
    const timeline = gsap.timeline({
        defaults: { ease: 'power3.out' },
        scrollTrigger: ScrollTrigger ? {
            trigger: guide,
            start: 'top 68%',
            once: true,
            invalidateOnRefresh: true
        } : undefined
    });

    timeline
        .fromTo(heading, { autoAlpha: 0, y: 30 }, { autoAlpha: 1, y: 0, duration: .5 })
        .fromTo(cards, { autoAlpha: 0, x: 28 }, {
            autoAlpha: 1,
            x: 0,
            duration: .48,
            stagger: .065,
            onComplete: () => gsap.set(cards, { clearProps: 'transform' })
        }, .18)
        .fromTo(
            scoop,
            {
                autoAlpha: 0,
                x: -125,
                y: -80,
                rotation: -14
            },
            {
                autoAlpha: 1,
                x: 0,
                y: 0,
                rotation: 0,
                duration: 1.4,
                ease: 'power2.out'
            },
            .55
        )
        .fromTo(
            fallingFood,
            {
                autoAlpha: 0,
                scaleY: .12
            },
            {
                autoAlpha: 1,
                scaleY: 1,
                transformOrigin: 'top center',
                duration: 1.8,
                ease: 'power2.out'
            },
            1.75
        )
        .to(
            baseBowl,
            {
                autoAlpha: 0,
                duration: .9,
                ease: 'power1.inOut'
            },
            2.65
        )
        .fromTo(
            filledBowl,
            {
                autoAlpha: 0,
                scale: .985,
                y: 5
            },
            {
                autoAlpha: 1,
                scale: 1,
                y: 0,
                duration: 1,
                ease: 'power2.out'
            },
            2.65
        )
        .to(
            scoop,
            {
                y: -34,
                x: 20,
                autoAlpha: .72,
                duration: .85,
                ease: 'power2.inOut'
            },
            3.35
        )
        .to(
            fallingFood,
            {
                autoAlpha: 0,
                duration: .5,
                ease: 'power1.out'
            },
            3.55
        )
        .to(
            filledBowl,
            {
                scale: 1.018,
                duration: .22,
                ease: 'power1.out'
            },
            3.72
        )
        .to(
            filledBowl,
            {
                scale: 1,
                duration: .28,
                ease: 'power2.out'
            },
            3.94
        )
        .fromTo(
            sparkles,
            {
                autoAlpha: 0,
                scale: .25,
                rotation: -18
            },
            {
                autoAlpha: 1,
                scale: 1.12,
                rotation: 0,
                duration: .52,
                stagger: .1,
                ease: 'back.out(2)'
            },
            4.18
        )
        .to(
            sparkles,
            {
                autoAlpha: .38,
                scale: 1.28,
                duration: .8,
                ease: 'sine.out'
            },
            4.78
        )
        .call(() => {
            const compactFloat = window.matchMedia('(max-width: 699px)').matches;
            floatLayers.forEach((layer, index) => {
                gsap.to(layer, {
                    y: index % 2 ? -(compactFloat ? 3 : 4 + (index % 3)) : (compactFloat ? 3 : 3 + (index % 4)),
                    rotation: compactFloat ? 0 : (index % 2 ? -0.35 : 0.4),
                    duration: 3.4 + (index % 4) * .45,
                    delay: (index % 4) * .18,
                    ease: 'sine.inOut',
                    repeat: -1,
                    yoyo: true
                });
            });
        }, null, 4.65);
});

document.addEventListener('DOMContentLoaded', () => {
    const universe = document.querySelector('.ingredient-universe');
    if (!universe) return;

    const { gsap, ScrollTrigger } = window;
    const heading = universe.querySelector('.home-section-heading');
    const plate = universe.querySelector('.ingredient-universe__plate');
    const ingredients = Array.from(universe.querySelectorAll('.ingredient-universe__ingredient'));
    const chips = Array.from(universe.querySelectorAll('.ingredient-chip'));
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const mobile = window.matchMedia('(max-width: 699px)').matches;
    const tablet = window.matchMedia('(min-width: 700px) and (max-width: 1099px)').matches;
    const startAngles = [-90, 30, 150];

    if (!gsap) {
        [heading, plate, ...ingredients, ...chips].forEach((element) => {
            if (element) element.style.opacity = '1';
        });
        return;
    }
    if (ScrollTrigger) gsap.registerPlugin(ScrollTrigger);

    const orbitState = { progress: 0, radius: 1 };
    const renderOrbit = () => {
        const radiusX = mobile ? 115 : tablet ? 225 : 300;
        const radiusY = mobile ? 82 : tablet ? 126 : 170;

        ingredients.forEach((ingredient, index) => {
            const angle = startAngles[index] * Math.PI / 180 + orbitState.progress * Math.PI * 2;
            const depth = (Math.sin(angle) + 1) / 2;
            const scale = mobile ? .9 + depth * .18 : .78 + depth * .26;
            const opacity = mobile ? .82 + depth * .18 : .58 + depth * .42;
            gsap.set(ingredient, {
                xPercent: -50,
                yPercent: -50,
                x: Math.cos(angle) * radiusX * orbitState.radius,
                y: Math.sin(angle) * radiusY * orbitState.radius,
                scale,
                opacity,
                zIndex: depth > .58 ? 7 : depth < .35 ? 3 : 4
            });
        });
    };

    if (reducedMotion) {
        gsap.set([heading, plate, ...chips], { autoAlpha: 1, clearProps: 'transform' });
        renderOrbit();
        return;
    }

    const startAmbientMotion = () => {
        chips.forEach((chip, index) => {
            gsap.to(chip, {
                y: index % 2 ? 2 : -2,
                duration: 4.3 + index * .17,
                delay: index * .11,
                ease: 'sine.inOut',
                repeat: -1,
                yoyo: true
            });
        });

        gsap.to(plate, {
            y: 5,
            duration: 5.2,
            ease: 'sine.inOut',
            repeat: -1,
            yoyo: true
        });

        gsap.to(orbitState, {
            progress: 1,
            duration: mobile ? 30 : 34,
            ease: 'none',
            repeat: -1,
            onUpdate: renderOrbit
        });
    };

    const timeline = gsap.timeline({
        scrollTrigger: ScrollTrigger ? {
            trigger: universe,
            start: 'top 72%',
            once: true,
            invalidateOnRefresh: true
        } : undefined,
        onComplete: () => {
            orbitState.radius = 0;
            renderOrbit();
            gsap.to(orbitState, {
                radius: 1,
                duration: .75,
                ease: 'power2.out',
                onUpdate: renderOrbit,
                onComplete: startAmbientMotion
            });
        }
    });

    timeline
        .fromTo(heading, { autoAlpha: 0, y: 28 }, {
            autoAlpha: 1,
            y: 0,
            duration: .65,
            ease: 'power2.out'
        })
        .fromTo(plate, { autoAlpha: 0, scale: .82 }, {
            autoAlpha: 1,
            scale: 1,
            duration: .95,
            ease: 'power3.out'
        }, .18)
        .fromTo(chips, {
            autoAlpha: 0,
            x: (index) => [0, 1, 6, 7].includes(index) ? -28 : 28
        }, {
            autoAlpha: 1,
            x: 0,
            duration: .52,
            stagger: .07,
            ease: 'power2.out'
        }, .62)
        .fromTo(ingredients, {
            autoAlpha: 0,
            scale: .25,
            xPercent: -50,
            yPercent: -50,
            x: 0,
            y: 0
        }, {
            autoAlpha: 1,
            scale: 1,
            duration: .7,
            stagger: .12,
            ease: 'power2.out'
        }, 1.05);
});
/* Recalcula ScrollTrigger cuando cambian las dimensiones reales del sitio. */
let homeScrollRefreshFrame = null;

const refreshHomeScrollTriggers = () => {
    if (!window.ScrollTrigger) return;

    window.cancelAnimationFrame(homeScrollRefreshFrame);

    homeScrollRefreshFrame = window.requestAnimationFrame(() => {
        window.requestAnimationFrame(() => {
            window.ScrollTrigger.refresh();
        });
    });
};

window.addEventListener('load', refreshHomeScrollTriggers, {
    once: true
});

document.fonts?.ready.then(refreshHomeScrollTriggers);

/*
 * Las imágenes bajo el primer viewport pueden cargar después del evento load.
 * Cada una fuerza un nuevo cálculo cuando obtiene sus dimensiones definitivas.
 */
document.querySelectorAll('img').forEach((image) => {
    if (image.complete) return;

    image.addEventListener('load', refreshHomeScrollTriggers, {
        once: true
    });

    image.addEventListener('error', refreshHomeScrollTriggers, {
        once: true
    });
});

window.addEventListener('pageshow', refreshHomeScrollTriggers);

function initHomeTrialShowcase() {
    const stage = document.querySelector('[data-trial-showcase]');
    if (!stage || stage.dataset.trialReady === 'true') return;

    const section = stage.closest('.home-trial');
    const items = [...stage.querySelectorAll('[data-trial-item]')];
    const indicators = [...stage.querySelectorAll('[data-trial-indicator]')];
    const previous = stage.querySelector('[data-trial-previous]');
    const next = stage.querySelector('[data-trial-next]');
    const info = stage.querySelector('.home-trial__active-info');
    const animal = stage.querySelector('[data-trial-animal]');
    const format = stage.querySelector('[data-trial-format]');
    const description = stage.querySelector('[data-trial-description]');
    const spiralPath = stage.querySelector('.home-trial__spiral path');
    const copy = section?.querySelector('.home-trial__copy');
    const { gsap, ScrollTrigger } = window;
    const motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
    const hoverQuery = window.matchMedia('(hover: hover) and (pointer: fine)');
    let activeIndex = 0;
    let hasEntered = false;
    let autoplayTimer = null;
    let autoplayPaused = false;
    let floatTween = null;
    let floatTimer = null;
    let infoTween = null;
    let resizeFrame = null;
    let pointerStart = null;

    if (!section || items.length !== 4 || !info || !animal || !format || !description) return;
    stage.dataset.trialReady = 'true';

    const slotSets = {
        desktop: [
            { x: 0, y: 22, scale: 1, rotation: 0, opacity: 1, zIndex: 10, blur: 0 },
            { x: 220, y: -28, scale: 0.7, rotation: 5, opacity: 0.7, zIndex: 6, blur: 0.4 },
            { x: 24, y: -154, scale: 0.47, rotation: 0, opacity: 0.36, zIndex: 3, blur: 1.3 },
            { x: -210, y: 8, scale: 0.74, rotation: -5, opacity: 0.78, zIndex: 7, blur: 0.25 }
        ],
        tablet: [
            { x: 0, y: 18, scale: 1, rotation: 0, opacity: 1, zIndex: 10, blur: 0 },
            { x: 168, y: -20, scale: 0.68, rotation: 5, opacity: 0.7, zIndex: 6, blur: 0.4 },
            { x: 18, y: -126, scale: 0.46, rotation: 0, opacity: 0.34, zIndex: 3, blur: 1.2 },
            { x: -164, y: 4, scale: 0.72, rotation: -5, opacity: 0.76, zIndex: 7, blur: 0.25 }
        ],
        mobile: [
            { x: 0, y: 10, scale: 1, rotation: 0, opacity: 1, zIndex: 10, blur: 0 },
            { x: 120, y: 4, scale: 0.62, rotation: 4, opacity: 0.68, zIndex: 6, blur: 0.45 },
            { x: 0, y: -68, scale: 0.34, rotation: 0, opacity: 0.25, zIndex: 3, blur: 1.2 },
            { x: -120, y: 4, scale: 0.62, rotation: -4, opacity: 0.68, zIndex: 6, blur: 0.45 }
        ]
    };

    const getSlots = () => {
        if (window.matchMedia('(max-width: 32rem)').matches) return slotSets.mobile;
        if (window.matchMedia('(max-width: 68rem)').matches) return slotSets.tablet;
        return slotSets.desktop;
    };

    const slotForItem = (itemIndex) => (itemIndex - activeIndex + items.length) % items.length;
    const formatKind = (value) => value === '250 g' ? 'FORMATO DE PRUEBA' : 'FORMATO DE CONFIRMACIÓN';

    const stopAutoplay = () => {
        window.clearTimeout(autoplayTimer);
        autoplayTimer = null;
    };

    const scheduleAutoplay = () => {
        stopAutoplay();
        if (!hasEntered || autoplayPaused || motionQuery.matches) return;
        const delay = window.matchMedia('(max-width: 32rem)').matches ? 2500 : 2500;
        autoplayTimer = window.setTimeout(() => select(activeIndex + 1), delay);
    };

    const updateContent = (animate = true) => {
        const item = items[activeIndex];
        const apply = () => {
            animal.textContent = `${item.dataset.animal.toUpperCase()} · ${formatKind(item.dataset.format)}`;
            format.textContent = item.dataset.format;
            description.textContent = item.dataset.description;
        };
        if (!animate || motionQuery.matches || !gsap) {
            apply();
            return;
        }
        infoTween?.kill();
        infoTween = gsap.timeline()
            .to(info, { autoAlpha: 0, y: 8, duration: 0.18, ease: 'power2.in' })
            .add(apply, 0.48)
            .fromTo(info, { autoAlpha: 0, y: 8 }, { autoAlpha: 1, y: 0, duration: 0.28, ease: 'power2.out' }, 0.56);
    };

    const startFloat = () => {
        floatTween?.kill();
        if (!gsap || motionQuery.matches || !hasEntered) return;
        const active = items[activeIndex];
        floatTween = gsap.to(active, { y: '+=3', duration: 2.2, ease: 'sine.inOut', repeat: -1, yoyo: true });
    };

    const render = ({ animate = true, updateInfo = true } = {}) => {
        const slots = getSlots();
        floatTween?.kill();
        window.clearTimeout(floatTimer);
        items.forEach((item, itemIndex) => {
            const slot = slots[slotForItem(itemIndex)];
            const values = {
                xPercent: -50,
                yPercent: -50,
                x: slot.x,
                y: slot.y,
                scale: slot.scale,
                rotation: slot.rotation,
                opacity: slot.opacity,
                filter: `blur(${slot.blur}px)`,
                zIndex: slot.zIndex
            };
            item.setAttribute('aria-pressed', String(itemIndex === activeIndex));
            item.tabIndex = itemIndex === activeIndex ? 0 : -1;
            if (gsap && animate && !motionQuery.matches) {
                gsap.to(item, { ...values, duration: 0.6, ease: 'power3.inOut', overwrite: true });
            } else if (gsap) {
                gsap.set(item, values);
            } else {
                item.style.transform = `translate(-50%, -50%) translate(${slot.x}px, ${slot.y}px) scale(${slot.scale}) rotate(${slot.rotation}deg)`;
                item.style.opacity = slot.opacity;
                item.style.filter = `blur(${slot.blur}px)`;
                item.style.zIndex = slot.zIndex;
            }
        });
        indicators.forEach((indicator, index) => indicator.setAttribute('aria-current', String(index === activeIndex)));
        if (updateInfo) updateContent(animate);
        floatTimer = window.setTimeout(startFloat, animate && !motionQuery.matches ? 920 : 0);
    };

    const select = (index) => {
        const normalized = (index + items.length) % items.length;
        if (normalized !== activeIndex) {
            activeIndex = normalized;
            render();
        }
        scheduleAutoplay();
    };

    items.forEach((item, index) => item.addEventListener('click', () => select(index)));
    indicators.forEach((indicator, index) => indicator.addEventListener('click', () => select(index)));
    previous?.addEventListener('click', () => select(activeIndex - 1));
    next?.addEventListener('click', () => select(activeIndex + 1));

    stage.addEventListener('keydown', (event) => {
        if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
        event.preventDefault();
        select(activeIndex + (event.key === 'ArrowRight' ? 1 : -1));
    });

    stage.addEventListener('pointerdown', (event) => {
        if (event.pointerType === 'mouse' && event.button !== 0) return;
        pointerStart = { x: event.clientX, y: event.clientY, id: event.pointerId };
    });
    stage.addEventListener('pointerup', (event) => {
        if (!pointerStart || pointerStart.id !== event.pointerId) return;
        const deltaX = event.clientX - pointerStart.x;
        const deltaY = event.clientY - pointerStart.y;
        pointerStart = null;
        if (Math.abs(deltaX) < 45 || Math.abs(deltaX) <= Math.abs(deltaY)) return;
        select(activeIndex + (deltaX < 0 ? 1 : -1));
    });
    stage.addEventListener('pointercancel', () => { pointerStart = null; });
    stage.addEventListener('mouseenter', () => {
        if (!hoverQuery.matches) return;
        autoplayPaused = true;
        stopAutoplay();
    });
    stage.addEventListener('mouseleave', () => {
        if (!hoverQuery.matches) return;
        autoplayPaused = false;
        scheduleAutoplay();
    });

    const enter = () => {
        if (hasEntered) return;
        hasEntered = true;
        if (!gsap || motionQuery.matches) {
            if (spiralPath) spiralPath.style.strokeDashoffset = '0';
            render({ animate: false, updateInfo: false });
            scheduleAutoplay();
            return;
        }
        gsap.timeline({ onComplete: () => { startFloat(); scheduleAutoplay(); } })
            .to(copy, { autoAlpha: 1, y: 0, duration: 0.65, ease: 'power2.out' }, 0)
            .to(spiralPath, { strokeDashoffset: 0, duration: 1.2, ease: 'power2.inOut' }, 0.1)
            .add(() => render({ animate: true, updateInfo: false }), 0)
            .to(info, { autoAlpha: 1, y: 0, duration: 0.45 }, 0.75)
            .to(stage.querySelector('.home-trial__controls'), { autoAlpha: 1, y: 0, duration: 0.6 }, 0.85);
    };

    if (gsap) {
        gsap.set(items, { xPercent: -50, yPercent: -50, x: 0, y: 0, scale: 0.34, opacity: 0 });
        if (!motionQuery.matches) gsap.set([copy, info, stage.querySelector('.home-trial__controls')], { autoAlpha: 0, y: 18 });
        if (ScrollTrigger && !motionQuery.matches) {
            gsap.registerPlugin(ScrollTrigger);
            ScrollTrigger.create({ trigger: section, start: 'top 85%', once: true, invalidateOnRefresh: true, onEnter: enter });
        } else {
            enter();
        }
    } else {
        enter();
    }

    window.addEventListener('resize', () => {
        window.cancelAnimationFrame(resizeFrame);
        resizeFrame = window.requestAnimationFrame(() => render({ animate: false, updateInfo: false }));
    });
    motionQuery.addEventListener?.('change', () => {
        render({ animate: false, updateInfo: false });
        scheduleAutoplay();
    });
}
