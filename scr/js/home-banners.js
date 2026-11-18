(function () {
    'use strict';

    const slider = document.querySelector('[data-home-banner-slider]');
    if (!slider) {
        return;
    }

    const slides = Array.from(slider.querySelectorAll('[data-home-banner-slide]'));
    if (slides.length < 2) {
        return;
    }

    const dots = Array.from(slider.querySelectorAll('[data-home-banner-dot]'));
    const reducedMotionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
    const interval = Number(slider.dataset.interval) || 6000;
    let current = 0;
    let timer = null;
    let pointerOver = false;
    let focusWithin = false;
    let touching = false;
    let hidden = document.visibilityState !== 'visible';
    let touchStartX = null;

    const isPaused = () => pointerOver || focusWithin || touching || hidden || reducedMotionQuery.matches;

    const show = (next) => {
        current = (next + slides.length) % slides.length;
        slides.forEach((slide, index) => {
            const active = index === current;
            slide.classList.toggle('is-active', active);
            slide.setAttribute('aria-hidden', active ? 'false' : 'true');
        });
        dots.forEach((dot, index) => {
            const active = index === current;
            dot.classList.toggle('is-active', active);
            dot.setAttribute('aria-current', active ? 'true' : 'false');
        });
    };

    const stopTimer = () => {
        if (timer !== null) {
            window.clearInterval(timer);
            timer = null;
        }
    };

    const schedule = () => {
        stopTimer();
        if (!isPaused()) {
            timer = window.setInterval(() => show(current + 1), interval);
        }
    };

    const setPauseState = (state, value) => {
        if (state === 'pointer') {
            pointerOver = value;
        } else if (state === 'focus') {
            focusWithin = value;
        } else if (state === 'touch') {
            touching = value;
        }
        schedule();
    };

    slider.querySelector('[data-home-banner-prev]')?.addEventListener('click', () => {
        show(current - 1);
        schedule();
    });
    slider.querySelector('[data-home-banner-next]')?.addEventListener('click', () => {
        show(current + 1);
        schedule();
    });
    dots.forEach((dot) => dot.addEventListener('click', () => {
        show(Number(dot.dataset.homeBannerDot));
        schedule();
    }));

    slider.addEventListener('pointerenter', () => setPauseState('pointer', true));
    slider.addEventListener('pointerleave', () => setPauseState('pointer', false));
    slider.addEventListener('focusin', () => setPauseState('focus', true));
    slider.addEventListener('focusout', (event) => {
        if (!slider.contains(event.relatedTarget)) {
            setPauseState('focus', false);
        }
    });
    slider.addEventListener('touchstart', (event) => {
        touchStartX = event.changedTouches[0].clientX;
        setPauseState('touch', true);
    }, { passive: true });
    const endTouch = (event) => {
        const touch = event.changedTouches[0];
        if (touchStartX !== null && touch) {
            const distance = touch.clientX - touchStartX;
            if (Math.abs(distance) > 40) {
                show(current + (distance < 0 ? 1 : -1));
            }
        }
        touchStartX = null;
        setPauseState('touch', false);
    };
    slider.addEventListener('touchend', endTouch, { passive: true });
    slider.addEventListener('touchcancel', endTouch, { passive: true });
    document.addEventListener('visibilitychange', () => {
        hidden = document.visibilityState !== 'visible';
        schedule();
    });
    const handleReducedMotionChange = () => {
        slider.classList.toggle('is-reduced-motion', reducedMotionQuery.matches);
        schedule();
    };
    if (reducedMotionQuery.addEventListener) {
        reducedMotionQuery.addEventListener('change', handleReducedMotionChange);
    } else {
        reducedMotionQuery.addListener(handleReducedMotionChange);
    }

    slider.classList.toggle('is-reduced-motion', reducedMotionQuery.matches);
    schedule();
})();
