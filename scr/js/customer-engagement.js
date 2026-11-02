(() => {
    async function sendForm(form) {
        const response = await fetch(form.dataset.endpoint || form.action, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new FormData(form),
            credentials: 'same-origin'
        });
        const body = await response.text();
        let result;
        try {
            result = JSON.parse(body);
        } catch (error) {
            console.error('Phản hồi không phải JSON.', { status: response.status, error });
            throw new Error('Máy chủ trả về phản hồi không hợp lệ. Vui lòng tải lại trang.');
        }
        if (!result || typeof result.success !== 'boolean'
            || typeof result.message !== 'string' || !result.data || typeof result.data !== 'object') {
            throw new Error('Máy chủ trả về dữ liệu không hợp lệ. Vui lòng thử lại.');
        }
        if (response.status === 401 && form.dataset.loginUrl) {
            window.location.assign(form.dataset.loginUrl);
            return null;
        }
        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Không thể hoàn tất thao tác.');
        }
        return result;
    }

    document.querySelectorAll('form[data-wishlist-toggle]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const button = form.querySelector('button[type="submit"]');
            const feedback = form.querySelector('[data-wishlist-feedback]');
            const message = document.querySelector('[data-wishlist-message]');
            if (button) button.disabled = true;
            try {
                const result = await sendForm(form);
                if (!result) return;
                const { wishlisted } = result.data;
                let removedLastWishlistItem = false;
                if (button) {
                    button.classList.toggle('is-saved', wishlisted);
                    button.setAttribute('aria-pressed', wishlisted ? 'true' : 'false');
                    button.setAttribute(
                        'aria-label',
                        `${wishlisted ? 'Bỏ yêu thích ' : 'Thêm yêu thích '}${form.dataset.productName || ''}`
                    );
                    button.title = result.message;
                }
                if (!wishlisted && form.dataset.removeOnToggle === '1') {
                    form.closest('.col')?.remove();
                    removedLastWishlistItem = !document.querySelector('[data-wishlist-list] .col');
                }
                if (message) {
                    message.className = removedLastWishlistItem ? 'alert alert-light border' : 'visually-hidden';
                    message.textContent = removedLastWishlistItem
                        ? 'Bạn chưa lưu sản phẩm yêu thích nào.'
                        : result.message;
                }
                if (feedback) {
                    feedback.hidden = true;
                    feedback.textContent = '';
                }
            } catch (error) {
                if (feedback) {
                    feedback.hidden = false;
                    feedback.textContent = error.message;
                } else if (message) {
                    message.className = 'alert alert-danger';
                    message.textContent = error.message;
                } else if (button) {
                    button.title = error.message;
                }
            } finally {
                if (button && button.isConnected) button.disabled = false;
            }
        });
    });

    document.querySelectorAll('form[data-review-submit]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const button = form.querySelector('button[type="submit"]');
            const message = form.querySelector('[data-review-message]');
            if (button) button.disabled = true;
            try {
                const result = await sendForm(form);
                if (!result) return;
                if (message) {
                    message.className = 'alert alert-success py-2';
                    message.textContent = result.message;
                }
                window.setTimeout(() => window.location.reload(), 900);
            } catch (error) {
                if (message) {
                    message.className = 'alert alert-danger py-2';
                    message.textContent = error.message;
                }
            } finally {
                if (button) button.disabled = false;
            }
        });
    });
})();
