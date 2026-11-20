(() => {
    const button = document.querySelector('[data-otp-resend]');
    const label = document.querySelector('[data-otp-resend-label]');
    if (!button || !label) {
        return;
    }

    let remaining = Number.parseInt(button.dataset.seconds || '0', 10);
    if (!Number.isFinite(remaining) || remaining <= 0) {
        button.disabled = false;
        return;
    }

    const update = () => {
        if (remaining <= 0) {
            button.disabled = false;
            label.textContent = 'Gửi lại mã';
            window.clearInterval(timer);
            return;
        }
        button.disabled = true;
        label.textContent = `Gửi lại mã sau ${remaining} giây`;
        remaining -= 1;
    };

    update();
    const timer = window.setInterval(update, 1000);
})();
