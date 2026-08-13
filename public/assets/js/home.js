let homeGsap = null;
let homeScrollTrigger = null;
let homeReducedMotion = false;
let homeScrollRefreshFrame = null;

const showFinalState = (elements) => {
    const targets = Array.from(elements || []).filter(Boolean);
    if (homeGsap) {
        homeGsap.set(targets, {
            autoAlpha: 1,
            x: 0,
            y: 0,
            scale: 1,
            rotation: 0,
            clearProps: 'visibility'
        });
        return;
    }
    targets.forEach((element) => {
        element.style.opacity = '1';
        element.style.visibility = 'visible';
    });
};

const refreshHomeScrollTriggers = () => {
    if (!homeScrollTrigger) return;
    window.cancelAnimationFrame(homeScrollRefreshFrame);
    homeScrollRefreshFrame = window.requestAnimationFrame(() => {
        window.requestAnimationFrame(() => {
            homeScrollTrigger.refresh();
        });
    });
};

const createSectionTrigger = ({
    section,
    enter,
    showFinal,
    start = 'top 88%'
}) => {
    if (!section) return null;

    if (
        homeReducedMotion
        || typeof enter !== 'function'
        || !('IntersectionObserver' in window)
    ) {
        showFinal?.();
        return null;
    }

    let completed = false;

    const match = start.match(/top\s+(\d+(?:\.\d+)?)%/);
    const activationPercent = match
        ? Math.min(100, Math.max(0, Number(match[1])))
        : 88;

    const bottomMargin = -(100 - activationPercent);

    const finish = () => {
        if (completed) return;

        completed = true;
        observer.disconnect();
        enter();
    };

    const observer = new IntersectionObserver(
        (entries) => {
            const entry = entries[0];

            if (!entry || !entry.isIntersecting) return;

            finish();
        },
        {
            root: null,
            threshold: 0,
            rootMargin: `0px 0px ${bottomMargin}% 0px`
        }
    );

    observer.observe(section);

    return observer;
};

function initHomeGeneral() {
    const toggle = document.querySelector('.nav-toggle');
    const nav = document.querySelector('#main-nav');

    if (toggle && nav && nav.dataset.publicNavReady !== 'true') {
        toggle.addEventListener('click', () => {
            const open = nav.classList.toggle('open');
            toggle.setAttribute('aria-expanded', String(open));
        });

        nav.addEventListener('click', () => {
            nav.classList.remove('open');
            toggle.setAttribute('aria-expanded', 'false');
        });
    }

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
}

function initHomeFaq() {
    const section = document.querySelector('.home-faq');
    const list = section?.querySelector('[data-faq]');
    if (!section || !list || section.dataset.faqReady === 'true') return;

    const items = [...list.querySelectorAll('.home-faq__item')];
    const intro = section.querySelector('.home-faq__intro');
    const contact = section.querySelector('.home-faq__contact');
    const gsap = homeGsap;

    section.dataset.faqReady = 'true';

    const setItemState = (item, open) => {
        const button = item.querySelector('.home-faq__question');
        const answer = item.querySelector('.home-faq__answer');
        if (!button || !answer) return;

        item.classList.toggle('is-open', open);
        button.setAttribute('aria-expanded', String(open));
        answer.setAttribute('aria-hidden', String(!open));
        answer.style.maxHeight = open ? `${answer.scrollHeight}px` : '0px';
    };

    items.forEach((item) => {
        setItemState(item, false);
        item.querySelector('.home-faq__question')?.addEventListener('click', () => {
            const wasOpen = item.classList.contains('is-open');
            items.forEach((candidate) => setItemState(candidate, !wasOpen && candidate === item));
        });
    });

    window.addEventListener('resize', () => {
        const openItem = items.find((item) => item.classList.contains('is-open'));
        if (openItem) setItemState(openItem, true);
    }, { passive: true });

    const showFinal = () => {
        intro?.style.setProperty('--home-faq-line', '1');
        showFinalState([intro, ...items, contact]);
    };

    if (!gsap || !homeScrollTrigger || homeReducedMotion) return showFinal();

    gsap.set(intro, { autoAlpha: 0, y: 24 });
    gsap.set(items, { autoAlpha: 0, x: 24 });
    gsap.set(contact, { autoAlpha: 0, y: 18 });

    const reveal = () => {
        gsap.timeline()
            .to(intro, { autoAlpha: 1, y: 0, duration: .6, ease: 'power2.out' }, 0)
            .to(intro, { '--home-faq-line': 1, duration: .7, ease: 'power2.inOut' }, .1)
            .to(items, { autoAlpha: 1, x: 0, duration: .5, stagger: .055, ease: 'power2.out' }, .22)
            .to(contact, { autoAlpha: 1, y: 0, duration: .45, ease: 'power2.out' }, .62);
    };

    createSectionTrigger({ section, enter: reveal, showFinal });
}

function initHomeLearningEditorial() {
    const section = document.querySelector('.home-learning');
    if (!section || section.dataset.learningReady === 'true') return;

    const header = section.querySelector('.home-learning__header');
    const featured = section.querySelector('.home-learning__featured');
    const notes = [...section.querySelectorAll('.home-learning__note')];
    const ticker = section.querySelector('.home-learning__ticker');
    const tickerGroup = section.querySelector('.home-learning__ticker-group');
    const signature = section.querySelector('.home-learning__signature');
    const gsap = homeGsap;

    section.dataset.learningReady = 'true';

    const updateTickerDistance = () => {
        if (!ticker || !tickerGroup) return;
        ticker.style.setProperty('--learning-ticker-distance', `${tickerGroup.offsetWidth}px`);
    };

    updateTickerDistance();
    window.addEventListener('resize', updateTickerDistance, { passive: true });
    window.requestAnimationFrame(() => window.requestAnimationFrame(updateTickerDistance));
    document.fonts?.ready.then(updateTickerDistance);
    if ('ResizeObserver' in window && tickerGroup) {
        new ResizeObserver(updateTickerDistance).observe(tickerGroup);
    }

    const showFinal = () => {
        section.style.setProperty('--learning-line-scale', '1');
        ticker?.classList.remove('is-running');
        showFinalState([header, featured, ...notes, signature]);
    };

    if (!gsap || !homeScrollTrigger || homeReducedMotion) return showFinal();

    gsap.set(header, { autoAlpha: 0, y: 22 });
    gsap.set(featured, { autoAlpha: 0, y: 52, rotation: -1.2, transformOrigin: 'center bottom' });
    gsap.set(notes, { autoAlpha: 0, x: 26 });
    gsap.set(signature, { autoAlpha: 0, y: 12 });

    const reveal = () => {
        gsap.timeline()
            .to(header, { autoAlpha: 1, y: 0, duration: .6, ease: 'power2.out' }, 0)
            .to(header, { '--learning-line-scale': 1, duration: .75, ease: 'power2.inOut' }, .12)
            .to(featured, { autoAlpha: 1, y: 0, rotation: 0, duration: .8, ease: 'power3.out' }, .28)
            .to(notes, { autoAlpha: 1, x: 0, duration: .55, stagger: .13, ease: 'power2.out' }, .48)
            .add(() => ticker?.classList.add('is-running'), .7)
            .to(signature, { autoAlpha: 1, y: 0, duration: .4, ease: 'power2.out' }, .82);
    };

    createSectionTrigger({ section, enter: reveal, showFinal });
}

function initHomeCoreAnimations() {
    const gsap = homeGsap;
    const ScrollTrigger = homeScrollTrigger;
    const reducedMotion = homeReducedMotion;

    if (!gsap) return;

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
            paused: true,
            speed: window.matchMedia('(max-width: 640px)').matches ? 0.35 : 0.42,
            snap: false
        });

        if (ScrollTrigger) {
            const brandSection = marquee.closest('.brand-marquee') || marquee;
            const syncBrandLoop = () => {
                const bounds = brandSection.getBoundingClientRect();
                const isVisible = bounds.top < window.innerHeight && bounds.bottom > 0;

                if (isVisible) {
                    loop.play();
                    return;
                }

                loop.pause();
            };
            ScrollTrigger.create({
                trigger: brandSection,
                start: 'top bottom',
                end: 'bottom top',
                onEnter: syncBrandLoop,
                onEnterBack: syncBrandLoop,
                onLeave: syncBrandLoop,
                onLeaveBack: syncBrandLoop
            });
        }

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

    const categories = document.querySelector('.home-categories');
    if (categories && productCards.length && !reducedMotion && ScrollTrigger) {
        gsap.set(productCards, {
            autoAlpha: 0,
            y: 65,
            scale: 0.78,
            rotation: (index) => index % 2 ? 3 : -3,
            transformOrigin: 'center bottom'
        });

        const enterCategories = () => {
            gsap.to(productCards, {
                autoAlpha: 1,
                y: 0,
                scale: 1,
                rotation: 0,
                duration: 0.9,
                ease: 'power3.out',
                stagger: 0.12,
                overwrite: true
            });

        if (productCurve) {
            gsap.to(productCurve, {
                strokeDashoffset: 0,
                duration: 1.5,
                ease: 'power2.out'
            });
        }
        };
        const showCategories = () => {
            gsap.set(productCards, { autoAlpha: 1, clearProps: 'transform' });
            if (productCurve) gsap.set(productCurve, { strokeDashoffset: 0 });
        };
        createSectionTrigger({ section: categories, enter: enterCategories, showFinal: showCategories });
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
}

function initHomeHeroOrigin() {
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
}

function initHomeGuide() {
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
    const gsap = homeGsap;

    const applySceneFinalState = () => {
        const hiddenElements = [scoop, baseBowl, fallingFood, ...sparkles].filter(Boolean);

        if (gsap) {
            gsap.set(hiddenElements, { autoAlpha: 0 });

            if (filledBowl) {
                gsap.set(filledBowl, {
                    autoAlpha: 1,
                    x: 0,
                    y: 0,
                    scale: 1,
                    rotation: 0
                });
            }

            return;
        }

        hiddenElements.forEach((element) => {
            element.style.opacity = '0';
            element.style.visibility = 'hidden';
        });

        if (filledBowl) {
            filledBowl.style.opacity = '1';
            filledBowl.style.visibility = 'visible';
            filledBowl.style.transform = '';
        }
    };

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

    const showFinal = () => {
        showFinalState([heading, ...cards, filledBowl]);
        applySceneFinalState();
    };

    if (homeReducedMotion || !gsap || !homeScrollTrigger) return showFinal();
    if (guide.getBoundingClientRect().bottom <= 0) return showFinal();

    const floatLayers = buttons.map((button) => button.querySelector('.home-need-orbit__float')).filter(Boolean);
    const timeline = gsap.timeline({
        paused: true,
        defaults: { ease: 'power3.out' }
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
            x: -190,
            y: 18,
            rotation: -7
        },
        {
            autoAlpha: 1,
            x: 0,
            y: 0,
            rotation: 0,
            duration: 1.35,
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
        .addLabel('feedingComplete', 3.55)
        .to(
            fallingFood,
            {
                autoAlpha: 0,
                duration: 0.22,
                ease: 'power1.out'
            },
            'feedingComplete'
        )
    .to(
        scoop,
        {
            x: -150,
            y: 12,
            rotation: -7,
            autoAlpha: 0,
            duration: 0.78,
            ease: 'power2.in'
        },
            'feedingComplete+=0.08'
        )
        .to(
            filledBowl,
            {
                scale: 1.018,
                duration: 0.18,
                ease: 'power1.out'
            },
            'feedingComplete+=0.22'
        )
        .to(
            filledBowl,
            {
                scale: 1,
                duration: 0.24,
                ease: 'power2.out'
            },
            'feedingComplete+=0.40'
        )
        .fromTo(
            sparkles,
            {
                autoAlpha: 0,
                scale: 0.3,
                rotation: -14
            },
            {
                autoAlpha: 1,
                scale: 1,
                rotation: 0,
                duration: 0.42,
                stagger: 0.08,
                ease: 'back.out(1.8)'
            },
            'feedingComplete+=0.92'
        )
        .to(
            sparkles,
            {
                autoAlpha: 0,
                scale: 0.86,
                duration: 0.42,
                stagger: 0.05,
                ease: 'power2.out'
            },
            'feedingComplete+=1.55'
        )
        .call(applySceneFinalState, null, 'feedingComplete+=2.15')
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
        }, null, 'feedingComplete+=2.2');

    createSectionTrigger({ section: guide, enter: () => timeline.play(), showFinal, start: 'top 68%' });
}

function initHomeIngredientUniverse() {
    const universe = document.querySelector('.ingredient-universe');
    if (!universe) return;

    const gsap = homeGsap;
    const heading = universe.querySelector('.home-section-heading');
    const plate = universe.querySelector('.ingredient-universe__plate');
    const ingredients = Array.from(universe.querySelectorAll('.ingredient-universe__ingredient'));
    const chips = Array.from(universe.querySelectorAll('.ingredient-chip'));
    const reducedMotion = homeReducedMotion;
    const mobile = window.matchMedia('(max-width: 699px)').matches;
    const tablet = window.matchMedia('(min-width: 700px) and (max-width: 1099px)').matches;
    const startAngles = [-90, 30, 150];

    if (!gsap) {
        [heading, plate, ...ingredients, ...chips].forEach((element) => {
            if (element) element.style.opacity = '1';
        });
        return;
    }

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

    const showFinal = () => {
        gsap.set([heading, plate, ...chips], { autoAlpha: 1, clearProps: 'transform' });
        renderOrbit();
        gsap.set(ingredients, { autoAlpha: 1 });
    };

    if (reducedMotion || !homeScrollTrigger) return showFinal();

    gsap.set(heading, {
        autoAlpha: 0,
        y: 28
    });

    gsap.set(plate, {
        autoAlpha: 0,
        scale: 0.82
    });

    gsap.set(chips, {
        autoAlpha: 0,
        x: (index) => [0, 1, 6, 7].includes(index) ? -28 : 28
    });

    gsap.set(ingredients, {
        autoAlpha: 0,
        scale: 0.25,
        xPercent: -50,
        yPercent: -50,
        x: 0,
        y: 0
    });

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
        paused: true,
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

    createSectionTrigger({
        section: universe,
        enter: () => timeline.play(),
        showFinal,
        start: 'top 72%'
    });
}
/* Recalcula ScrollTrigger cuando cambian las dimensiones reales del sitio. */
/*
 * Las imágenes bajo el primer viewport pueden cargar después del evento load.
 * Cada una fuerza un nuevo cálculo cuando obtiene sus dimensiones definitivas.
 */
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
    const controls = stage.querySelector('.home-trial__controls');
    const gsap = homeGsap;
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
            .to(controls, { autoAlpha: 1, y: 0, duration: 0.6 }, 0.85);
    };

    const showFinal = () => {
        hasEntered = true;
        if (spiralPath) spiralPath.style.strokeDashoffset = '0';
        render({ animate: false, updateInfo: false });
        showFinalState([copy, info, controls]);
    };

    if (gsap) {
        gsap.set(items, { xPercent: -50, yPercent: -50, x: 0, y: 0, scale: 0.34, opacity: 0 });
        if (!motionQuery.matches) gsap.set([copy, info, controls].filter(Boolean), { autoAlpha: 0, y: 18 });
        if (homeScrollTrigger && !motionQuery.matches) {
            createSectionTrigger({ section, enter, showFinal, start: 'top 90%' });
        } else showFinal();
    } else {
        showFinal();
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

function initHomeTrialPet() {
    const section = document.querySelector('.home-trial');
    const slot = section?.querySelector('.home-trial__pet-slot');
    const petImage = section?.querySelector('.home-trial__pet-image');
    const petWow = section?.querySelector('.home-trial__pet-message strong');
    const petText = section?.querySelector('.home-trial__pet-message p');
    if (!section || !slot || !petImage || section.dataset.trialPetReady === 'true') return;

    section.dataset.trialPetReady = 'true';
    const gsap = homeGsap;
    const motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
    const hoverQuery = window.matchMedia('(hover: hover) and (pointer: fine)');
    let hasEntered = false;

    const showImmediately = () => {
        if (gsap) gsap.set([petImage, petWow, petText].filter(Boolean), { autoAlpha: 1, x: 0, y: 0, rotation: 0, scale: 1 });
        else [petImage, petWow, petText].filter(Boolean).forEach((element) => {
            element.style.opacity = '1';
            element.style.visibility = 'visible';
        });
    };

    const enter = () => {
        if (hasEntered) return;
        hasEntered = true;
        if (!gsap || motionQuery.matches) {
            showImmediately();
            return;
        }
        gsap.timeline()
            .fromTo(petImage, { autoAlpha: 0, x: -75, y: 22, rotation: -4, scale: 0.92 }, { autoAlpha: 1, x: 0, y: 0, rotation: 0, scale: 1, duration: 0.85, ease: 'power3.out' }, 0)
            .fromTo(petWow, { autoAlpha: 0, y: 12, scale: 0.82 }, { autoAlpha: 1, y: 0, scale: 1, duration: 0.4, ease: 'power3.out' }, 0.85)
            .fromTo(petText, { autoAlpha: 0, y: 14, scale: 0.9 }, { autoAlpha: 1, y: 0, scale: 1, duration: 0.45, ease: 'power3.out' }, 1.25);
    };

    if (gsap && !motionQuery.matches) {
        gsap.set(petImage, { autoAlpha: 0, x: -75, y: 22, rotation: -4, scale: 0.92 });
        gsap.set([petWow, petText].filter(Boolean), { autoAlpha: 0 });
        if (homeScrollTrigger) createSectionTrigger({ section, enter, showFinal: showImmediately, start: 'top 90%' });
        else showImmediately();
    } else {
        showImmediately();
    }

    if (gsap && hoverQuery.matches && !motionQuery.matches) {
        const moveX = gsap.quickTo(petImage, 'x', { duration: 0.35, ease: 'power2.out' });
        const moveY = gsap.quickTo(petImage, 'y', { duration: 0.35, ease: 'power2.out' });
        const rotate = gsap.quickTo(petImage, 'rotation', { duration: 0.35, ease: 'power2.out' });
        const scale = gsap.quickTo(petImage, 'scale', { duration: 0.35, ease: 'power2.out' });
        slot.addEventListener('pointermove', (event) => {
            const bounds = slot.getBoundingClientRect();
            const xRatio = ((event.clientX - bounds.left) / bounds.width) * 2 - 1;
            const yRatio = ((event.clientY - bounds.top) / bounds.height) * 2 - 1;
            moveX(xRatio * 10);
            moveY(yRatio * 7);
            rotate(xRatio * 2.5);
            scale(1 + (1 - Math.min(1, Math.hypot(xRatio, yRatio))) * 0.025);
        });
        slot.addEventListener('pointerleave', () => {
            moveX(0);
            moveY(0);
            rotate(0);
            scale(1);
        });
    }

    motionQuery.addEventListener?.('change', showImmediately);
}

const initHome = () => {
    homeGsap = window.gsap || null;
    homeScrollTrigger = window.ScrollTrigger || null;
    homeReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (homeGsap && homeScrollTrigger) homeGsap.registerPlugin(homeScrollTrigger);

    initHomeGeneral();
    initHomeHeroOrigin();
    initHomeCoreAnimations();
    initHomeGuide();
    initHomeIngredientUniverse();
    initHomeTrialShowcase();
    initHomeTrialPet();
    initHomeLearningEditorial();
    initHomeFaq();

    window.addEventListener('load', refreshHomeScrollTriggers, { once: true });
    document.fonts?.ready.then(refreshHomeScrollTriggers);
    document.querySelectorAll('img').forEach((image) => {
        if (image.complete) return;
        image.addEventListener('load', refreshHomeScrollTriggers, { once: true });
        image.addEventListener('error', refreshHomeScrollTriggers, { once: true });
    });
    window.addEventListener('pageshow', refreshHomeScrollTriggers);
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initHome, { once: true });
} else {
    initHome();
}
