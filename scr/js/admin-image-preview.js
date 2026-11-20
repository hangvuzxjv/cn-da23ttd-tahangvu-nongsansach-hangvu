(function () {
    'use strict';

    document.querySelectorAll('[data-image-preview]').forEach((input) => {
        const form = input.closest('form');
        const preview = form && form.querySelector('[data-image-preview-target]');
        const current = form && form.querySelector('[data-image-current]');
        const removeInput = form && form.querySelector('[data-image-clear]');
        const removeButton = form && form.querySelector('[data-image-clear-button]');
        let previewUrl = null;

        input.addEventListener('change', () => {
            const file = input.files && input.files[0];
            if (!file || !preview) {
                return;
            }
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
            }
            previewUrl = URL.createObjectURL(file);
            preview.src = previewUrl;
            preview.hidden = false;
            if (current) {
                current.hidden = true;
            }
            if (removeInput) {
                removeInput.checked = false;
            }
            if (removeButton) {
                removeButton.setAttribute('aria-pressed', 'false');
                removeButton.textContent = 'Xóa ảnh hiện tại';
                removeButton.disabled = true;
            }
        });

        if (removeButton && removeInput) {
            removeButton.addEventListener('click', () => {
                if (!removeInput.checked && !window.confirm('Xóa ảnh đại diện hiện tại?')) {
                    return;
                }
                removeInput.checked = !removeInput.checked;
                removeButton.setAttribute('aria-pressed', removeInput.checked ? 'true' : 'false');
                removeButton.textContent = removeInput.checked ? 'Giữ ảnh hiện tại' : 'Xóa ảnh hiện tại';
                if (current) {
                    current.hidden = removeInput.checked;
                }
            });
        }

        input.addEventListener('change', () => {
            if (input.files && input.files.length === 0 && previewUrl) {
                URL.revokeObjectURL(previewUrl);
                previewUrl = null;
                if (preview) {
                    preview.hidden = true;
                }
                if (removeButton) {
                    removeButton.disabled = false;
                }
                if (current) {
                    current.hidden = Boolean(removeInput && removeInput.checked);
                }
            }
        });
    });
})();
