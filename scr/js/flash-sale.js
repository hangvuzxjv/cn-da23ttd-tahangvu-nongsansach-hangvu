(() => {
    const roots = [...document.querySelectorAll('[data-flash-sale-root]')];

    roots.forEach((root) => {
        let serverEpoch = Date.parse(root.dataset.serverTime || '');
        let synchronizedAt = performance.now();
        let reloading = false;
        const progressUrl = root.dataset.progressUrl;
        const hasActiveCampaign = () => root.dataset.hasActive === '1';

        const estimatedServerNow = () => serverEpoch + (performance.now() - synchronizedAt);
        const lockActions = () => {
            root.querySelectorAll('[data-flash-action]').forEach((action) => {
                action.replaceChildren();
                const button = document.createElement('button');
                button.type = 'button';
                button.disabled = true;
                button.className = 'btn btn-secondary w-100';
                button.textContent = 'Chương trình đã kết thúc; đang cập nhật…';
                action.append(button);
            });
        };
        const updateCountdowns = () => {
            const now = estimatedServerNow();
            root.querySelectorAll('[data-countdown-target]').forEach((counter) => {
                const target = Date.parse(counter.dataset.countdownTarget || '');
                const seconds = Math.max(0, Math.ceil((target - now) / 1000));
                const days = Math.floor(seconds / 86400);
                const hours = Math.floor((seconds % 86400) / 3600);
                const minutes = Math.floor((seconds % 3600) / 60);
                const remainingSeconds = seconds % 60;
                const clock = [hours, minutes, remainingSeconds].map((value) => String(value).padStart(2, '0')).join(':');
                counter.textContent = days > 0 ? `${days} ngày ${clock}` : clock;
                if (seconds === 0 && !reloading) {
                    reloading = true;
                    lockActions();
                    window.setTimeout(() => window.location.reload(), 800);
                }
            });
        };
        const updateProgress = (items) => {
            items.forEach((item) => {
                const card = root.querySelector(`[data-flash-item="${Number(item.item_id)}"]`);
                if (!card) return;
                const quota = Number(item.quota);
                const sold = Number(item.sold_quantity);
                const remaining = Math.max(0, quota - sold);
                const percent = quota > 0 ? Math.min(100, Math.round(sold * 100 / quota)) : 0;
                card.dataset.sold = String(sold);
                const soldCount = card.querySelector('[data-sold-count]');
                if (soldCount) soldCount.textContent = sold.toLocaleString('vi-VN');
                const remainingCount = card.querySelector('[data-remaining]');
                if (remainingCount) remainingCount.textContent = remaining.toLocaleString('vi-VN');
                const progress = card.querySelector('[role="progressbar"]');
                if (progress) progress.setAttribute('aria-valuenow', String(percent));
                const bar = card.querySelector('[data-sold-progress]');
                if (bar) bar.style.width = `${percent}%`;
                if (remaining === 0) {
                    const action = card.querySelector('[data-flash-action]');
                    if (action) {
                        action.replaceChildren();
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.disabled = true;
                        button.className = 'btn btn-secondary w-100';
                        button.textContent = 'Đã hết suất';
                        action.append(button);
                    }
                    return;
                }
                const action = card.querySelector('[data-flash-action]');
                if (action?.querySelector('button:disabled')?.textContent === 'Đã hết suất') {
                    window.location.reload();
                    return;
                }
                card.querySelectorAll('input[name="quantity"][data-max-buy]').forEach((input) => {
                    input.max = String(Math.min(Number(input.dataset.maxBuy), remaining));
                    if (Number(input.value) > Number(input.max)) input.value = input.max;
                });
            });
        };
        const poll = async () => {
            if (!progressUrl || !hasActiveCampaign() || document.visibilityState !== 'visible') return;
            const message = root.querySelector('[data-flash-poll-message]');
            try {
                const response = await fetch(progressUrl, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    cache: 'no-store'
                });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Không thể cập nhật số suất Flash Sale.');
                const data = result.data;
                serverEpoch = Date.parse(data.server_time);
                synchronizedAt = performance.now();
                updateProgress(data.items || []);
                if (!data.has_active_campaign && hasActiveCampaign()) {
                    window.location.reload();
                    return;
                }
                if (message) message.textContent = '';
            } catch (error) {
                if (message) message.textContent = error.message || 'Không thể cập nhật số suất. Vui lòng tải lại trang.';
            }
        };

        updateCountdowns();
        window.setInterval(updateCountdowns, 1000);
        if (hasActiveCampaign()) {
            window.setInterval(poll, 20000);
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') poll();
            });
        }
    });
})();
