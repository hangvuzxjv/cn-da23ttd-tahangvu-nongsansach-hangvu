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
    const pauseButton = slider.querySelector('[data-home-banner-pause]');
    const reducedMotionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
    const interval = Number(slider.dataset.interval) || 6000;
    let current = 0;
    let timer = null;
    let manuallyPaused = false;
    let temporarilyPaused = false;
    let hidden = document.visibilityState !== 'visible';
    let touchStartX = null;

    const isPaused = () => manuallyPaused || temporarilyPaused || hidden;

    const updatePauseButton = () => {
        if (!pauseButton) {
            return;
        }
        pauseButton.setAttribute('aria-pressed', manuallyPaused ? 'true' : 'false');
        pauseButton.dataset.paused = manuallyPaused ? 'true' : 'false';
    };

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

    const setManualPause = (value) => {
        manuallyPaused = value;
        updatePauseButton();
        schedule();
    };

    const setTemporaryPause = (value) => {
        temporarilyPaused = value;
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
    pauseButton?.addEventListener('click', () => {
        setTemporaryPause(false);
        setManualPause(!manuallyPaused);
    });
    dots.forEach((dot) => dot.addEventListener('click', () => {
        show(Number(dot.dataset.homeBannerDot));
        schedule();
    }));

    slider.addEventListener('pointerenter', () => setTemporaryPause(true));
    slider.addEventListener('pointerleave', () => setTemporaryPause(false));
    slider.addEventListener('focusin', (event) => {
        if (event.target !== pauseButton) {
            setTemporaryPause(true);
        }
    });
    slider.addEventListener('focusout', (event) => {
        if (!slider.contains(event.relatedTarget)) {
            setTemporaryPause(false);
        }
    });
    slider.addEventListener('touchstart', (event) => {
        touchStartX = event.changedTouches[0].clientX;
        setTemporaryPause(true);
    }, { passive: true });
    slider.addEventListener('touchend', (event) => {
        if (touchStartX !== null) {
            const distance = event.changedTouches[0].clientX - touchStartX;
            if (Math.abs(distance) > 40) {
                show(current + (distance < 0 ? 1 : -1));
            }
        }
        touchStartX = null;
        setTemporaryPause(false);
    }, { passive: true });
    document.addEventListener('visibilitychange', () => {
        hidden = document.visibilityState !== 'visible';
        schedule();
    });
    reducedMotionQuery.addEventListener?.('change', () => {
        slider.classList.toggle('is-reduced-motion', reducedMotionQuery.matches);
    });

    slider.classList.toggle('is-reduced-motion', reducedMotionQuery.matches);
    updatePauseButton();
    schedule();
})();
