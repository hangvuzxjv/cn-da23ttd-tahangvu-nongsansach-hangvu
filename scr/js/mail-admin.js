(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const message = document.getElementById('mail-admin-message');

    const submit = async (url, body = null) => {
        const headers = { 'X-CSRF-Token': csrf, Accept: 'application/json' };
        if (body) headers['Content-Type'] = 'application/x-www-form-urlencoded;charset=UTF-8';
        const response = await fetch(url, { method: 'POST', headers, body });
        const result = await response.json();
        message.className = `alert ${result.success ? 'alert-success' : 'alert-danger'}`;
        message.textContent = result.message;
        return result;
    };

    document.getElementById('mail-driver-form')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        try {
            const result = await submit(form.action, new URLSearchParams(new FormData(form)));
            if (result.success) window.location.reload();
        } catch {
            message.className = 'alert alert-danger';
            message.textContent = 'Chưa thể lưu cấu hình gửi email.';
        }
    });

    document.getElementById('mail-test-button')?.addEventListener('click', async (event) => {
        try {
            await submit(event.currentTarget.dataset.url);
        } catch {
            message.className = 'alert alert-danger';
            message.textContent = 'Chưa thể gửi thư kiểm tra.';
        }
    });

    document.querySelectorAll('.mail-resend-button').forEach((button) => {
        button.addEventListener('click', async () => {
            try {
                await submit(button.dataset.url);
                window.setTimeout(() => window.location.reload(), 700);
            } catch {
                message.className = 'alert alert-danger';
                message.textContent = 'Chưa thể gửi lại email.';
            }
        });
    });
})();
