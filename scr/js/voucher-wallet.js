(() => {
    const copyText = async (text) => {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(text);
            return;
        }
        const field = document.createElement('textarea');
        field.value = text;
        field.setAttribute('readonly', '');
        field.style.position = 'fixed';
        field.style.opacity = '0';
        document.body.appendChild(field);
        field.select();
        const copied = document.execCommand('copy');
        field.remove();
        if (!copied) throw new Error('Không thể sao chép mã.');
    };

    document.addEventListener('click', async (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-copy-voucher]') : null;
        if (!button) return;
        const feedback = button.closest('.voucher-card')?.querySelector('[data-copy-feedback]');
        try {
            await copyText(button.getAttribute('data-copy-voucher') || '');
            if (feedback) feedback.textContent = 'Đã sao chép';
        } catch (error) {
            if (feedback) feedback.textContent = error instanceof Error ? error.message : 'Không thể sao chép mã.';
        }
    });
})();
