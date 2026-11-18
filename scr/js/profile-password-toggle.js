(() => {
    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        if (!(button instanceof HTMLButtonElement)) return;
        const input = document.getElementById(button.getAttribute('aria-controls') || '');
        if (!(input instanceof HTMLInputElement)) return;

        const eye = button.querySelector('[data-password-eye]');
        const eyeSlash = button.querySelector('[data-password-eye-slash]');
        button.addEventListener('click', () => {
            const showPassword = input.type === 'password';
            input.type = showPassword ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(showPassword));
            button.setAttribute('aria-label', `${showPassword ? 'Ẩn' : 'Hiện'} ${button.dataset.passwordLabel || 'mật khẩu'}`);
            if (eye instanceof SVGElement) eye.hidden = showPassword;
            if (eyeSlash instanceof SVGElement) eyeSlash.hidden = !showPassword;
        });
    });
})();
