(() => {
    const form = document.querySelector('[data-product-search]');
    if (!form) return;

    const input = form.querySelector('[data-product-search-input]');
    const list = form.querySelector('[data-product-search-list]');
    const status = form.querySelector('[data-product-search-status]');
    if (!input || !list || !status) return;

    let timer = null;
    let controller = null;
    let activeIndex = -1;

    const options = () => Array.from(list.querySelectorAll('[role="option"]'));
    const close = () => {
        if (controller) {
            controller.abort();
            controller = null;
        }
        list.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        activeIndex = -1;
    };
    const setActive = (index) => {
        const items = options();
        if (!items.length) return;
        activeIndex = (index + items.length) % items.length;
        items.forEach((item, itemIndex) => {
            const selected = itemIndex === activeIndex;
            item.setAttribute('aria-selected', selected ? 'true' : 'false');
            item.classList.toggle('is-active', selected);
        });
        input.setAttribute('aria-activedescendant', items[activeIndex].id);
        items[activeIndex].scrollIntoView({ block: 'nearest' });
    };
    const appendOption = (item, index) => {
        const link = document.createElement('a');
        link.className = 'product-search__option';
        link.id = `productSearchOption${index}`;
        link.href = item.url;
        link.setAttribute('role', 'option');
        link.setAttribute('aria-selected', 'false');

        if (item.type === 'batch') {
            link.textContent = item.label;
        } else {
            const image = document.createElement('img');
            image.src = item.image;
            image.alt = '';
            image.loading = 'lazy';
            const details = document.createElement('span');
            details.className = 'product-search__details';
            const name = document.createElement('span');
            name.className = 'product-search__name';
            name.textContent = item.name;
            const price = document.createElement('span');
            price.className = 'product-search__price';
            price.textContent = `${new Intl.NumberFormat('vi-VN').format(item.price)} ₫`;
            details.append(name, price);
            link.append(image, details);
        }

        link.addEventListener('pointermove', () => setActive(index));
        list.append(link);
    };

    const loadSuggestions = async (selectFirst = false) => {
        const query = input.value.trim();
        if ([...query].length < 2) {
            close();
            status.textContent = '';
            return;
        }

        if (controller) controller.abort();
        controller = new AbortController();
        const requestController = controller;
        const url = new URL(form.dataset.suggestionsUrl, window.location.href);
        url.searchParams.set('q', query.slice(0, 100));

        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                signal: requestController.signal,
            });
            if (!response.ok) throw new Error('Suggestions request failed.');
            const result = await response.json();
            if (requestController !== controller) return;

            list.replaceChildren();
            activeIndex = -1;
            (Array.isArray(result.items) ? result.items : []).forEach(appendOption);
            list.hidden = list.childElementCount === 0;
            input.setAttribute('aria-expanded', list.hidden ? 'false' : 'true');
            input.removeAttribute('aria-activedescendant');
            status.textContent = list.hidden ? 'Không có gợi ý.' : `${list.childElementCount} gợi ý.`;
            if (selectFirst && !list.hidden) setActive(0);
        } catch (error) {
            if (error.name === 'AbortError') return;
            close();
            status.textContent = 'Không thể tải gợi ý tìm kiếm.';
        }
    };

    input.addEventListener('input', () => {
        window.clearTimeout(timer);
        if (controller) controller.abort();
        timer = window.setTimeout(loadSuggestions, 250);
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown') {
            if (list.hidden) {
                event.preventDefault();
                window.clearTimeout(timer);
                loadSuggestions(true);
            } else {
                event.preventDefault();
                setActive(activeIndex + 1);
            }
        } else if (event.key === 'ArrowUp' && !list.hidden) {
            event.preventDefault();
            setActive(activeIndex < 0 ? options().length - 1 : activeIndex - 1);
        } else if (event.key === 'Enter' && activeIndex >= 0 && !list.hidden) {
            event.preventDefault();
            const active = options()[activeIndex];
            if (active) window.location.assign(active.href);
        } else if (event.key === 'Escape') {
            close();
        }
    });

    form.addEventListener('submit', () => {
        window.clearTimeout(timer);
        if (controller) controller.abort();
        close();
    });

    document.addEventListener('pointerdown', (event) => {
        if (!form.contains(event.target)) close();
    });
})();
