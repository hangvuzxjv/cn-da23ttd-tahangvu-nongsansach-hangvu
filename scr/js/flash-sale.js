(() => {
    const roots = [...document.querySelectorAll('[data-flash-sale-root]')];

    roots.forEach((root) => {
        let serverEpoch = Date.parse(root.dataset.serverTime || '');
        let synchronizedAt = performance.now();
        let reloading = false;
        const progressUrl = root.dataset.progressUrl;
        const hasActiveCampaign = () => root.dataset.hasActive === '1';

        const filters = [...root.querySelectorAll('[data-campaign-filter]')];
        const campaigns = [...root.querySelectorAll('[data-flash-campaign]')];
        if (filters.length) {
            const applyCampaignFilter = (state) => {
                let visibleCampaigns = 0;
                campaigns.forEach((campaign) => {
                    const visible = campaign.dataset.campaignState === state;
                    campaign.hidden = !visible;
                    if (visible) visibleCampaigns += 1;
                });
                filters.forEach((filter) => {
                    const selected = filter.dataset.campaignFilter === state;
                    filter.classList.toggle('active', selected);
                    filter.setAttribute('aria-pressed', String(selected));
                });
                const emptyState = root.querySelector('[data-flash-filter-empty]');
                if (emptyState) emptyState.hidden = visibleCampaigns > 0;
            };

            const defaultFilter = ['active', 'upcoming', 'ended'].includes(root.dataset.defaultFilter)
                ? root.dataset.defaultFilter
                : 'active';
            applyCampaignFilter(defaultFilter);
            filters.forEach((filter) => {
                filter.addEventListener('click', () => applyCampaignFilter(filter.dataset.campaignFilter));
            });
        }

        const sortSelect = root.querySelector('[data-flash-sort]');
        let currentSort = sortSelect?.value || 'discount';
        const sortCampaigns = (sortBy) => {
            root.querySelectorAll('[data-flash-grid]').forEach((grid) => {
                const cards = [...grid.querySelectorAll('[data-flash-item]')];
                cards.sort((left, right) => {
                    const soldOutOrder = Number(left.dataset.sortSoldOut) - Number(right.dataset.sortSoldOut);
                    if (soldOutOrder !== 0) return soldOutOrder;
                    const leftValue = Number(left.dataset[`sort${sortBy[0].toUpperCase()}${sortBy.slice(1)}`]);
                    const rightValue = Number(right.dataset[`sort${sortBy[0].toUpperCase()}${sortBy.slice(1)}`]);
                    return sortBy === 'discount' ? rightValue - leftValue : leftValue - rightValue;
                });
                cards.forEach((card) => grid.append(card));
            });
        };
        if (sortSelect) {
            sortCampaigns(currentSort);
            sortSelect.addEventListener('change', () => {
                currentSort = sortSelect.value;
                sortCampaigns(currentSort);
            });
        }

        const syncQuantityButtons = (input) => {
            const controls = input.closest('[data-flash-quantity-controls]');
            if (!controls) return;
            const minimum = Number(input.min) || 1;
            const maximum = Math.max(minimum, Number(input.max) || Number(input.dataset.maxBuy) || minimum);
            const value = Number(input.value);
            const quantity = Number.isFinite(value) ? value : minimum;
            const decrease = controls.querySelector('[data-flash-quantity-step="-1"]');
            const increase = controls.querySelector('[data-flash-quantity-step="1"]');
            if (decrease) decrease.disabled = quantity <= minimum;
            if (increase) increase.disabled = quantity >= maximum;
        };
        root.querySelectorAll('[data-flash-quantity-controls] input[name="quantity"]').forEach((input) => {
            syncQuantityButtons(input);
            input.addEventListener('input', () => syncQuantityButtons(input));
            input.addEventListener('change', () => syncQuantityButtons(input));
        });
        root.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof Element)) return;
            const button = target.closest('[data-flash-quantity-step]');
            if (!(button instanceof HTMLButtonElement) || button.disabled) return;
            const controls = button.closest('[data-flash-quantity-controls]');
            const input = controls?.querySelector('input[name="quantity"][data-max-buy]');
            if (!input) return;
            const minimum = Number(input.min) || 1;
            const maximum = Math.max(minimum, Number(input.max) || Number(input.dataset.maxBuy) || minimum);
            const current = Number(input.value);
            const quantity = Number.isFinite(current) ? Math.max(minimum, Math.min(maximum, current)) : minimum;
            const step = Number(button.dataset.flashQuantityStep);
            input.value = String(Math.max(minimum, Math.min(maximum, quantity + step)));
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
            syncQuantityButtons(input);
        });

        const carousel = root.querySelector('[data-flash-carousel]');
        const carouselControls = root.querySelector('[data-flash-carousel-controls]');
        if (carousel && carouselControls) {
            const previous = carouselControls.querySelector('[data-flash-carousel-prev]');
            const next = carouselControls.querySelector('[data-flash-carousel-next]');
            const updateCarouselControls = () => {
                const canScroll = carousel.scrollWidth > carousel.clientWidth + 1;
                carouselControls.hidden = !canScroll;
                carousel.classList.toggle('is-centered', !canScroll);
                if (!canScroll) return;
                const maxScroll = carousel.scrollWidth - carousel.clientWidth;
                if (previous) previous.disabled = carousel.scrollLeft <= 1;
                if (next) next.disabled = carousel.scrollLeft >= maxScroll - 1;
            };
            const scrollCarousel = (direction) => {
                const firstItem = carousel.querySelector('[data-flash-carousel-item]');
                if (!firstItem) return;
                const gap = Number.parseFloat(getComputedStyle(carousel).columnGap) || 0;
                carousel.scrollBy({
                    left: direction * (firstItem.getBoundingClientRect().width + gap),
                    behavior: 'smooth'
                });
            };

            previous?.addEventListener('click', () => scrollCarousel(-1));
            next?.addEventListener('click', () => scrollCarousel(1));
            carousel.addEventListener('scroll', updateCarouselControls, { passive: true });
            window.addEventListener('resize', updateCarouselControls);
            updateCarouselControls();
        }

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
                const hoursDisplay = counter.querySelector('[data-countdown-hours]');
                const minutesDisplay = counter.querySelector('[data-countdown-minutes]');
                const secondsDisplay = counter.querySelector('[data-countdown-seconds]');
                if (hoursDisplay && minutesDisplay && secondsDisplay) {
                    const daysDisplay = counter.querySelector('[data-countdown-days]');
                    if (daysDisplay) {
                        daysDisplay.hidden = days === 0;
                        daysDisplay.textContent = days > 0 ? `${days} ngày` : '';
                    }
                    hoursDisplay.textContent = String(hours).padStart(2, '0');
                    minutesDisplay.textContent = String(minutes).padStart(2, '0');
                    secondsDisplay.textContent = String(remainingSeconds).padStart(2, '0');
                    counter.classList.toggle('flash-sale-countdown--urgent', seconds > 0 && seconds < 3600);
                } else {
                    counter.textContent = days > 0 ? `${days} ngày ${clock}` : clock;
                }
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
                card.dataset.sortRemaining = String(remaining);
                const soldCount = card.querySelector('[data-sold-count]');
                if (soldCount) soldCount.textContent = sold.toLocaleString('vi-VN');
                const remainingCount = card.querySelector('[data-remaining]');
                if (remainingCount) remainingCount.textContent = remaining.toLocaleString('vi-VN');
                const progress = card.querySelector('[role="progressbar"]');
                if (progress) progress.setAttribute('aria-valuenow', String(percent));
                const bar = card.querySelector('[data-sold-progress]');
                const flashSaleCard = card.querySelector('.product-card--flash-sale');
                if (bar) {
                    bar.style.width = `${percent}%`;
                    if (flashSaleCard) {
                        bar.dataset.progress = String(percent);
                        bar.classList.remove('flash-sale-progress--green', 'flash-sale-progress--orange', 'flash-sale-progress--red');
                        bar.classList.add(percent >= 80 ? 'flash-sale-progress--red' : (percent >= 50 ? 'flash-sale-progress--orange' : 'flash-sale-progress--green'));
                    }
                }
                if (flashSaleCard) {
                    const soldOut = remaining === 0;
                    card.dataset.sortSoldOut = soldOut ? '1' : '0';
                    flashSaleCard.classList.toggle('product-card--flash-sale-sold-out', soldOut);
                    const status = card.querySelector('[data-flash-sale-status]');
                    if (status) {
                        const statusKey = soldOut ? 'sold-out'
                            : (sold === 0 ? 'new' : (quota > 0 && remaining / quota <= .2 ? 'low' : ''));
                        status.dataset.status = statusKey;
                        status.textContent = statusKey === 'sold-out' ? 'Đã hết suất'
                            : (statusKey === 'new' ? 'Mới mở bán' : (statusKey === 'low' ? '🔥 Sắp hết' : ''));
                        status.hidden = statusKey === '';
                    }
                }
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
                    syncQuantityButtons(input);
                });
            });
            sortCampaigns(currentSort);
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
