(() => {
    const toggles = Array.from(document.querySelectorAll('[data-voucher-menu-toggle]'));
    if (!toggles.length) return;

    const positionMenu = (toggle, menu) => {
        const toggleBounds = toggle.getBoundingClientRect();
        const menuBounds = menu.getBoundingClientRect();
        const left = Math.max(8, Math.min(window.innerWidth - menuBounds.width - 8, toggleBounds.right - menuBounds.width));
        const top = toggleBounds.bottom + menuBounds.height + 8 > window.innerHeight
            ? Math.max(8, toggleBounds.top - menuBounds.height - 4)
            : toggleBounds.bottom + 4;
        menu.style.left = `${left}px`;
        menu.style.top = `${top}px`;
    };

    const setOpen = (toggle, open) => {
        const menu = document.getElementById(toggle.getAttribute('aria-controls'));
        if (!menu) return;
        menu.hidden = !open;
        toggle.setAttribute('aria-expanded', String(open));
        if (open) positionMenu(toggle, menu);
        else {
            menu.style.removeProperty('left');
            menu.style.removeProperty('top');
        }
    };

    const closeMenus = (restoreFocus = false) => {
        let lastOpenToggle = null;
        toggles.forEach((toggle) => {
            if (toggle.getAttribute('aria-expanded') === 'true') lastOpenToggle = toggle;
            setOpen(toggle, false);
        });
        if (restoreFocus) lastOpenToggle?.focus();
    };

    toggles.forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const shouldOpen = toggle.getAttribute('aria-expanded') !== 'true';
            closeMenus();
            setOpen(toggle, shouldOpen);
        });
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.voucher-actions-menu')) closeMenus();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeMenus(true);
    });

    const repositionOpenMenus = () => {
        toggles.forEach((toggle) => {
            if (toggle.getAttribute('aria-expanded') === 'true') {
                const menu = document.getElementById(toggle.getAttribute('aria-controls'));
                if (menu) positionMenu(toggle, menu);
            }
        });
    };
    window.addEventListener('resize', repositionOpenMenus);
    window.addEventListener('scroll', repositionOpenMenus, true);
})();
