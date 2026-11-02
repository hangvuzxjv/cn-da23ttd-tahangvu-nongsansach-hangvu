(() => {
    const list = document.getElementById('notificationList');
    const itemsList = document.querySelector('[data-notification-items]');
    const count = document.getElementById('notificationCount');
    const markAllButtons = document.querySelectorAll('[data-mark-all-notifications], #markAllNotificationsRead');
    const message = document.querySelector('[data-notification-message]');
    const pageMessage = document.querySelector('[data-notification-page-message]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const baseUrl = document.querySelector('meta[name="app-base-url"]')?.content || '/';
    if (!list || !itemsList || !count) return;

    const render = (data) => {
        count.textContent = String(data.unread_count || 0);
        count.classList.toggle('d-none', !data.unread_count);
        markAllButtons.forEach((button) => { button.disabled = !data.unread_count; });
        itemsList.replaceChildren();
        if (!data.items?.length) {
            const empty = document.createElement('span');
            empty.className = 'dropdown-item-text text-muted';
            empty.textContent = 'Chưa có thông báo.';
            itemsList.append(empty);
            return;
        }
        data.items.slice(0, 8).forEach((item) => {
            const link = document.createElement('a');
            link.className = `notification-item${item.read_at ? '' : ' is-unread'}`;
            const target = typeof item.link_url === 'string' && /^\/(?!\/)/.test(item.link_url)
                ? `${baseUrl.replace(/\/$/, '')}${item.link_url}`
                : '/';
            link.href = target;
            const title = document.createElement('span');
            title.className = 'notification-item__title';
            title.textContent = item.title;
            const detail = document.createElement('small');
            detail.className = 'notification-item__message';
            detail.textContent = item.message;
            const time = document.createElement('time');
            time.className = 'notification-item__time';
            time.dateTime = item.created_at;
            time.textContent = new Date(item.created_at.replace(' ', 'T')).toLocaleString('vi-VN', {
                dateStyle: 'short',
                timeStyle: 'short',
            });
            link.append(title, detail, time);
            if (!item.read_at) {
                link.addEventListener('click', async (event) => {
                    event.preventDefault();
                    try {
                        const response = await fetch(`${baseUrl.replace(/\/$/, '')}/notifications/${encodeURIComponent(item.id)}/read`, {
                            method: 'POST',
                            headers: { 'X-CSRF-Token': csrf, Accept: 'application/json' },
                        });
                        const result = await response.json();
                        if (!response.ok || !result.success) throw new Error(result.message || 'Chưa thể cập nhật thông báo.');
                        window.location.assign(target);
                    } catch (error) {
                        if (message) message.textContent = error.message || 'Chưa thể cập nhật thông báo.';
                    }
                });
            }
            itemsList.append(link);
        });
    };

    const refresh = async () => {
        if (document.visibilityState !== 'visible') return;
        try {
            const response = await fetch(`${baseUrl.replace(/\/$/, '')}/notifications`, { headers: { Accept: 'application/json' } });
            const result = await response.json();
            if (response.ok && result.success) render(result.data);
            else if (message) message.textContent = result.message || 'Chưa thể tải thông báo.';
        } catch {
            if (message) message.textContent = 'Chưa thể tải thông báo.';
        }
    };

    markAllButtons.forEach((button) => button.addEventListener('click', async () => {
        button.disabled = true;
        if (message) message.textContent = '';
        if (pageMessage) pageMessage.textContent = '';
        try {
            const response = await fetch(`${baseUrl.replace(/\/$/, '')}/notifications/0/read`, {
                method: 'POST',
                headers: { 'X-CSRF-Token': csrf, Accept: 'application/json' },
                body: new URLSearchParams({ _all: '1' }),
            });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'Chưa thể cập nhật thông báo.');
            if (button.hasAttribute('data-reload-after-read')) {
                window.location.reload();
                return;
            }
            await refresh();
        } catch (error) {
            const output = button.hasAttribute('data-reload-after-read') ? pageMessage : message;
            if (output) output.textContent = error.message || 'Chưa thể cập nhật thông báo.';
            button.disabled = false;
        }
    }));

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
