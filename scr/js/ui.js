(() => {
    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) return;
        const galleryButton = target.closest('[data-image]');
        if (galleryButton) {
            const main = document.getElementById('productMainImage');
            if (main instanceof HTMLImageElement) {
                main.src = galleryButton.dataset.image || '';
                main.alt = galleryButton.dataset.alt || '';
            }
        }
    });

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.dataset.confirm) return;
        if (!window.confirm(form.dataset.confirm)) event.preventDefault();
    });

    const timer = document.getElementById('paymentCountdown');
    if (timer) {
        const deadline = new Date((timer.dataset.deadline || '').replace(' ', 'T')).getTime();
        if (Number.isFinite(deadline)) {
            const render = () => {
                const remaining = Math.max(0, Math.floor((deadline - Date.now()) / 1000));
                timer.textContent = `${Math.floor(remaining / 60)} phút ${remaining % 60} giây`;
                if (remaining === 0) {
                    window.clearInterval(countdown);
                    window.location.reload();
                }
            };
            const countdown = window.setInterval(render, 1000);
            render();
        }
    }

    document.querySelectorAll('.progress-bar[data-progress]').forEach((bar) => {
        const progress = Math.max(0, Math.min(100, Number(bar.dataset.progress) || 0));
        bar.style.width = `${progress}%`;
    });

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || event.defaultPrevented) return;
        if (
            form.hasAttribute('data-ajax-form')
            || form.hasAttribute('data-cart-add')
            || form.hasAttribute('data-cart-update')
            || form.hasAttribute('data-wishlist-toggle')
            || form.hasAttribute('data-review-submit')
            || form.id === 'resend-verification-form'
        ) return;
        if (form.dataset.submitting === '1') {
            event.preventDefault();
            return;
        }

        form.dataset.submitting = '1';
        form.setAttribute('aria-busy', 'true');
        form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((button) => {
            button.disabled = true;
        });
    });
})();
