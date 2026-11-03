(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    document.querySelectorAll('form[data-review-admin-action]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) return;
            const button = form.querySelector('button[type="submit"]');
            const message = document.querySelector('[data-review-admin-message]');
            if (button) button.disabled = true;
            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': csrf
                    },
                    body: (() => {
                        const body = new FormData(form);
                        if (!body.has('_token')) body.set('_token', csrf);
                        return body;
                    })(),
                    credentials: 'same-origin'
                });
                const body = await response.text();
                let result;
                try {
                    result = JSON.parse(body);
                } catch (error) {
                    console.error('Phản hồi quản trị đánh giá không phải JSON.', { status: response.status, error });
                    throw new Error('Máy chủ trả về phản hồi không hợp lệ.');
                }
                if (!result || typeof result.success !== 'boolean'
                    || typeof result.message !== 'string' || !result.data || typeof result.data !== 'object') {
                    throw new Error('Máy chủ trả về dữ liệu không hợp lệ.');
                }
                if (!response.ok || !result.success) throw new Error(result.message || 'Không thể cập nhật đánh giá.');
                if (form.dataset.reviewDelete === '1') {
                    form.closest('tr')?.remove();
                } else {
                    const row = form.closest('tr');
                    const status = row?.querySelector('[data-review-status]');
                    if (status) {
                        status.textContent = result.data.status === 'published'
                            ? 'Đã duyệt'
                            : (result.data.status === 'hidden' ? 'Đã ẩn' : 'Chờ duyệt');
                    }
                }
                if (message) {
                    message.className = 'alert alert-success';
                    message.textContent = result.message;
                }
            } catch (error) {
                if (message) {
                    message.className = 'alert alert-danger';
                    message.textContent = error.message;
                }
            } finally {
                if (button) button.disabled = false;
            }
        });
    });
})();
