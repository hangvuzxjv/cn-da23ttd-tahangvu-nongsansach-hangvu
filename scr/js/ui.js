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

    const updateEstimatedWeight = (form) => {
        const quantityInput = form.querySelector('[data-estimate-weight-grams]');
        const output = form.querySelector('[data-estimated-total-weight]');
        if (!(quantityInput instanceof HTMLInputElement) || !output) return;
        const perUnitGrams = Number(quantityInput.dataset.estimateWeightGrams);
        const quantity = Number(quantityInput.value);
        if (!Number.isFinite(perUnitGrams) || perUnitGrams <= 0 || !Number.isInteger(quantity) || quantity < 1) {
            output.textContent = '';
            return;
        }
        const totalGrams = perUnitGrams * quantity;
        output.textContent = `Tổng khoảng ${totalGrams.toLocaleString('vi-VN', { maximumFractionDigits: 2 })}g`;
    };
    document.querySelectorAll('form[data-estimated-weight-form]').forEach(updateEstimatedWeight);
    document.addEventListener('input', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLInputElement) || !target.matches('[data-estimate-weight-grams]')) return;
        const form = target.closest('form[data-estimated-weight-form]');
        if (form) updateEstimatedWeight(form);
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
