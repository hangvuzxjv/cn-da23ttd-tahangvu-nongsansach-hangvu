(() => {
    const menuToggles = Array.from(document.querySelectorAll('[data-admin-menu-toggle]'));
    const navigation = document.getElementById('adminNavigation');
    const navigationToggle = document.querySelector('[data-admin-nav-toggle]');

    const setMenuOpen = (toggle, open) => {
        const menu = document.getElementById(toggle.getAttribute('aria-controls'));
        if (!menu) return;
        toggle.setAttribute('aria-expanded', String(open));
        menu.hidden = !open;
        menu.classList.toggle('show', open);
    };

    const closeMenus = (restoreFocus = false) => {
        let focusedToggle = null;
        menuToggles.forEach((toggle) => {
            if (toggle.getAttribute('aria-expanded') === 'true') focusedToggle = toggle;
            setMenuOpen(toggle, false);
        });
        if (restoreFocus) focusedToggle?.focus();
    };

    const setNavigationOpen = (open) => {
        if (!navigation || !navigationToggle) return;
        navigation.classList.toggle('show', open);
        navigationToggle.setAttribute('aria-expanded', String(open));
        navigationToggle.setAttribute('aria-label', open ? 'Đóng menu' : 'Mở menu');
    };

    menuToggles.forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const open = toggle.getAttribute('aria-expanded') !== 'true';
            closeMenus();
            setMenuOpen(toggle, open);
        });
    });

    navigationToggle?.addEventListener('click', () => {
        setNavigationOpen(navigationToggle.getAttribute('aria-expanded') !== 'true');
    });

    document.addEventListener('click', (event) => {
        const clickedDropdown = event.target.closest('.admin-header-dropdown');
        menuToggles.forEach((toggle) => {
            if (toggle.closest('.admin-header-dropdown') !== clickedDropdown) setMenuOpen(toggle, false);
        });
        if (navigation?.classList.contains('show')
            && !event.target.closest('.admin-sidebar')
            && !event.target.closest('[data-admin-nav-toggle]')) {
            setNavigationOpen(false);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        closeMenus(true);
        if (navigation?.classList.contains('show')) {
            setNavigationOpen(false);
            navigationToggle?.focus();
        }
    });
})();
