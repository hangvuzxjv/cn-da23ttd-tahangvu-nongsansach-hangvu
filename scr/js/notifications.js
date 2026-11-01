(() => {
    const list = document.getElementById('notificationList');
    const count = document.getElementById('notificationCount');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const baseUrl = document.querySelector('meta[name="app-base-url"]')?.content || '/';
    if (!list || !count) return;

    const render = (data) => {
        count.textContent = String(data.unread_count || 0);
        count.classList.toggle('d-none', !data.unread_count);
        list.replaceChildren();
        if (!data.items?.length) {
            const empty = document.createElement('span');
            empty.className = 'dropdown-item-text text-muted';
            empty.textContent = 'Chưa có thông báo.';
            list.append(empty);
            return;
        }
        data.items.forEach((item) => {
            const link = document.createElement('a');
            link.className = `dropdown-item text-wrap${item.read_at ? '' : ' fw-semibold'}`;
            const target = typeof item.link_url === 'string' && /^\/(?!\/)/.test(item.link_url)
                ? `${baseUrl.replace(/\/$/, '')}${item.link_url}`
                : '/';
            link.href = target;
            const title = document.createElement('span');
            title.className = 'd-block';
            title.textContent = item.title;
            const detail = document.createElement('small');
            detail.className = 'text-muted';
            detail.textContent = item.message;
            link.append(title, detail);
            if (!item.read_at) {
                link.addEventListener('click', async (event) => {
                    event.preventDefault();
                    try {
                        await fetch(`${baseUrl.replace(/\/$/, '')}/notifications/${encodeURIComponent(item.id)}/read`, {
                            method: 'POST',
                            headers: { 'X-CSRF-Token': csrf, Accept: 'application/json' },
                        });
                    } finally {
                        window.location.assign(target);
                    }
                });
            }
            list.append(link);
        });
    };

    const refresh = async () => {
        if (document.visibilityState !== 'visible') return;
        try {
            const response = await fetch(`${baseUrl.replace(/\/$/, '')}/notifications`, { headers: { Accept: 'application/json' } });
            const result = await response.json();
            if (response.ok && result.success) render(result.data);
        } catch {
            list.textContent = 'Chưa thể tải thông báo.';
        }
    };

    document.getElementById('resend-verification-form')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const output = document.getElementById('verification-resend-message');
        const button = form.querySelector('button[type="submit"]');
        button.disabled = true;
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { Accept: 'application/json' },
            });
            const result = await response.json();
            output.textContent = result.message;
            output.className = `small ms-2 ${result.success ? 'text-success' : 'text-danger'}`;
        } catch {
            output.textContent = 'Chưa thể gửi yêu cầu. Vui lòng thử lại sau.';
            output.className = 'small ms-2 text-danger';
        } finally {
            button.disabled = false;
        }
    });

    refresh();
    window.setInterval(refresh, 30000);
    document.addEventListener('visibilitychange', refresh);
})();
