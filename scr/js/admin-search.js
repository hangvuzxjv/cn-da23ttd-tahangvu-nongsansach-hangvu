(() => {
    const search = document.querySelector('[data-admin-search]');
    const input = document.querySelector('[data-admin-search-input]');
    const toggle = document.querySelector('[data-admin-search-toggle]');
    const results = document.getElementById('adminSearchResults');
    const resultItems = document.querySelector('[data-admin-search-results]');
    const baseUrl = (document.querySelector('meta[name="app-base-url"]')?.content || '/').replace(/\/$/, '');
    if (!search || !input || !toggle || !results || !resultItems) return;

    let debounceTimer = 0;
    let activeRequest = null;
    let activeIndex = -1;

    const closeResults = () => {
        window.clearTimeout(debounceTimer);
        if (activeRequest) activeRequest.abort();
        results.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        activeIndex = -1;
    };

    const openMobileSearch = () => {
        search.classList.add('is-open');
        toggle.setAttribute('aria-expanded', 'true');
        toggle.setAttribute('aria-label', 'Đóng tìm kiếm');
        input.focus();
    };

    const closeMobileSearch = () => {
        search.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Mở tìm kiếm');
        closeResults();
    };

    const setActiveOption = (index) => {
        const options = Array.from(results.querySelectorAll('[role="option"]'));
        if (!options.length) return;
        activeIndex = (index + options.length) % options.length;
        options.forEach((option, optionIndex) => {
            const active = optionIndex === activeIndex;
            option.classList.toggle('is-active', active);
            option.setAttribute('aria-selected', String(active));
        });
        input.setAttribute('aria-activedescendant', options[activeIndex].id);
        options[activeIndex].scrollIntoView({ block: 'nearest' });
    };

    const renderResults = (groups) => {
        resultItems.replaceChildren();
        activeIndex = -1;
        let total = 0;
        let optionId = 0;

        Object.values(groups || {}).forEach((group) => {
            if (!Array.isArray(group.items) || group.items.length === 0) return;
            const section = document.createElement('section');
            section.className = 'admin-search-results__group';
            section.setAttribute('role', 'group');
            section.setAttribute('aria-label', group.label);

            const heading = document.createElement('h2');
            heading.className = 'admin-search-results__heading';
            heading.textContent = group.label;
            section.append(heading);

            group.items.forEach((item) => {
                if (typeof item.url !== 'string' || !item.url.startsWith('/') || item.url.startsWith('//')) return;
                const link = document.createElement('a');
                link.className = 'admin-search-results__item';
                link.href = `${baseUrl}${item.url}`;
                link.id = `admin-search-option-${optionId}`;
                optionId += 1;
                link.setAttribute('role', 'option');
                link.setAttribute('aria-selected', 'false');

                const title = document.createElement('strong');
                title.textContent = item.title || '';
                link.append(title);
                if (item.detail) {
                    const detail = document.createElement('small');
                    detail.textContent = item.detail;
                    link.append(detail);
                }
                section.append(link);
                total += 1;
            });
            if (section.querySelector('[role="option"]')) resultItems.append(section);
        });

        if (total === 0) {
            const empty = document.createElement('p');
            empty.className = 'admin-search-results__empty';
            empty.textContent = 'Không tìm thấy';
            resultItems.append(empty);
        }
        results.hidden = false;
        input.setAttribute('aria-expanded', 'true');
    };

    const searchItems = async (query) => {
        if (activeRequest) activeRequest.abort();
        activeRequest = new AbortController();
        resultItems.replaceChildren();
        const loading = document.createElement('p');
        loading.className = 'admin-search-results__empty';
        loading.textContent = 'Đang tìm kiếm…';
        resultItems.append(loading);
        results.hidden = false;
        input.setAttribute('aria-expanded', 'true');

        try {
            const parameters = new URLSearchParams({ q: query });
            const response = await fetch(`${baseUrl}/admin/search?${parameters}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                signal: activeRequest.signal,
            });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'Không thể tìm kiếm lúc này.');
            renderResults(result.data?.groups || {});
        } catch (error) {
            if (error.name === 'AbortError') return;
            resultItems.replaceChildren();
            const message = document.createElement('p');
            message.className = 'admin-search-results__empty';
            message.textContent = error.message || 'Không thể tìm kiếm lúc này.';
            resultItems.append(message);
            results.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        }
    };

    input.addEventListener('input', () => {
        window.clearTimeout(debounceTimer);
        const query = input.value.trim();
        if (Array.from(query).length < 2) {
            if (activeRequest) activeRequest.abort();
            closeResults();
            return;
        }
        debounceTimer = window.setTimeout(() => searchItems(query), 300);
    });

    input.addEventListener('keydown', (event) => {
        const options = results.querySelectorAll('[role="option"]');
        if (event.key === 'ArrowDown' && options.length) {
            event.preventDefault();
            setActiveOption(activeIndex + 1);
        } else if (event.key === 'ArrowUp' && options.length) {
            event.preventDefault();
            setActiveOption(activeIndex < 0 ? options.length - 1 : activeIndex - 1);
        } else if (event.key === 'Enter' && options.length) {
            event.preventDefault();
            options[activeIndex < 0 ? 0 : activeIndex].click();
        } else if (event.key === 'Escape') {
            closeResults();
            if (window.matchMedia('(max-width: 768px)').matches) {
                closeMobileSearch();
                toggle.focus();
            }
        }
    });

    toggle.addEventListener('click', () => {
        if (search.classList.contains('is-open')) {
            closeMobileSearch();
        } else {
            openMobileSearch();
        }
    });

    document.addEventListener('keydown', (event) => {
        const target = event.target;
        const isEditing = target instanceof HTMLElement
            && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName));
        if (event.key === '/' && !event.ctrlKey && !event.metaKey && !event.altKey && !isEditing) {
            event.preventDefault();
            if (window.matchMedia('(max-width: 768px)').matches) openMobileSearch();
            else input.focus();
        }
    });

    document.addEventListener('click', (event) => {
        if (search.contains(event.target)) return;
        closeResults();
        if (window.matchMedia('(max-width: 768px)').matches && search.classList.contains('is-open')) {
            closeMobileSearch();
        }
    });
})();
