(() => {
    const section = document.getElementById('product-images');
    const input = document.getElementById('images');
    const preview = document.getElementById('new-image-preview');
    const primaryInput = document.getElementById('primary-new-image');
    const message = document.getElementById('image-upload-message');
    const imageList = document.getElementById('image-order-list');
    const entries = [];

    if (section && input && preview && primaryInput && message) {
        const maxImages = Number.parseInt(section.dataset.maxNewImages || '0', 10);
        const hasExistingPrimary = section.dataset.existingPrimary === '1';
        let primaryEntry = null;
        let existingOrderChanged = false;

        const updateExistingLabels = () => {
            if (!imageList) return;
            const items = Array.from(imageList.querySelectorAll('.image-order-item'));
            items.forEach((item, index) => {
                const label = item.querySelector('[data-image-primary-label]');
                const isPrimary = primaryEntry !== null
                    ? false
                    : existingOrderChanged
                        ? index === 0
                        : item.dataset.initialPrimary === '1';
                if (!label) return;
                label.textContent = isPrimary ? 'Ảnh đại diện' : 'Ảnh phụ';
                label.classList.toggle('text-bg-success', isPrimary);
                label.classList.toggle('text-bg-secondary', !isPrimary);
                const selectButton = item.querySelector('[data-set-existing-primary]');
                if (selectButton) selectButton.disabled = isPrimary;
            });
        };

        const syncFiles = () => {
            const transfer = new DataTransfer();
            entries.forEach((entry) => transfer.items.add(entry.file));
            input.files = transfer.files;
            primaryInput.value = primaryEntry === null ? '' : String(entries.indexOf(primaryEntry));
        };

        const renderPreview = () => {
            preview.replaceChildren();
            entries.forEach((entry, index) => {
                const column = document.createElement('div');
                column.className = 'col-6 col-md-3';
                const card = document.createElement('div');
                card.className = 'border rounded p-2 h-100';
                const image = document.createElement('img');
                image.className = 'img-fluid rounded catalog-preview';
                image.src = entry.url;
                image.alt = entry.file.name;
                const description = document.createElement('div');
                description.className = 'd-flex justify-content-between align-items-center gap-2 mt-2';
                const badge = document.createElement('span');
                badge.className = 'badge';
                badge.textContent = entry === primaryEntry ? 'Ảnh đại diện' : 'Ảnh phụ';
                badge.classList.add(entry === primaryEntry ? 'text-bg-success' : 'text-bg-secondary');
                const controls = document.createElement('span');
                controls.className = 'text-nowrap';

                [['up', '↑', 'Chuyển ảnh lên'], ['down', '↓', 'Chuyển ảnh xuống']].forEach(([direction, text, label]) => {
                    const button = document.createElement('button');
                    button.className = 'btn btn-sm btn-outline-secondary';
                    button.type = 'button';
                    button.dataset.newImageMove = direction;
                    button.dataset.index = String(index);
                    button.setAttribute('aria-label', label);
                    button.textContent = text;
                    button.disabled = direction === 'up' ? index === 0 : index === entries.length - 1;
                    controls.append(button);
                });
                description.append(badge, controls);

                const actions = document.createElement('div');
                actions.className = 'd-flex flex-wrap gap-2 mt-2';
                const primaryButton = document.createElement('button');
                primaryButton.className = 'btn btn-sm btn-outline-success';
                primaryButton.type = 'button';
                primaryButton.dataset.newImagePrimary = String(index);
                primaryButton.textContent = 'Chọn làm ảnh đại diện';
                primaryButton.disabled = entry === primaryEntry;
                const removeButton = document.createElement('button');
                removeButton.className = 'btn btn-sm btn-outline-danger';
                removeButton.type = 'button';
                removeButton.dataset.removeNewImage = String(index);
                removeButton.textContent = 'Bỏ ảnh';
                actions.append(primaryButton, removeButton);
                card.append(image, description, actions);
                column.append(card);
                preview.append(column);
            });
            updateExistingLabels();
        };

        const clearEntries = () => {
            entries.forEach((entry) => URL.revokeObjectURL(entry.url));
            entries.length = 0;
            primaryEntry = null;
        };

        input.addEventListener('change', () => {
            message.textContent = '';
            clearEntries();
            const selectedFiles = Array.from(input.files || []);
            if (selectedFiles.length > maxImages) {
                input.value = '';
                message.textContent = `Chỉ có thể tải thêm tối đa ${maxImages} ảnh cho sản phẩm này.`;
                renderPreview();
                syncFiles();
                return;
            }
            selectedFiles.forEach((file) => entries.push({ file, url: URL.createObjectURL(file) }));
            if (!hasExistingPrimary && entries.length > 0) primaryEntry = entries[0];
            renderPreview();
            syncFiles();
        });

        preview.addEventListener('click', (event) => {
            const button = event.target.closest('button');
            if (!button) return;
            const index = Number.parseInt(button.dataset.index || button.dataset.newImagePrimary
                || button.dataset.removeNewImage || '', 10);
            const entry = entries[index];
            if (!entry) return;

            if (button.hasAttribute('data-new-image-primary')) {
                primaryEntry = entry;
            } else if (button.hasAttribute('data-remove-new-image')) {
                if (!window.confirm('Bỏ ảnh này khỏi danh sách tải lên?')) return;
                URL.revokeObjectURL(entry.url);
                entries.splice(index, 1);
                if (primaryEntry === entry) primaryEntry = hasExistingPrimary ? null : (entries[0] || null);
            } else if (button.hasAttribute('data-new-image-move')) {
                const direction = button.dataset.newImageMove;
                const targetIndex = direction === 'up' ? index - 1 : index + 1;
                if (targetIndex < 0 || targetIndex >= entries.length) return;
                [entries[index], entries[targetIndex]] = [entries[targetIndex], entries[index]];
            } else {
                return;
            }
            renderPreview();
            syncFiles();
        });

        if (imageList) {
            imageList.addEventListener('click', (event) => {
                const button = event.target.closest('button');
                if (!button) return;
                const item = button.closest('.image-order-item');
                if (!item) return;
                if (button.hasAttribute('data-set-existing-primary')) {
                    const firstItem = imageList.querySelector('.image-order-item');
                    if (firstItem !== item) imageList.insertBefore(item, firstItem);
                    primaryEntry = null;
                    existingOrderChanged = true;
                } else if (button.hasAttribute('data-existing-move')) {
                    const direction = button.dataset.existingMove;
                    const sibling = direction === 'up' ? item.previousElementSibling : item.nextElementSibling;
                    if (!sibling) return;
                    if (direction === 'up') imageList.insertBefore(item, sibling);
                    else imageList.insertBefore(sibling, item);
                    primaryEntry = null;
                    existingOrderChanged = true;
                } else {
                    return;
                }
                renderPreview();
            });
            updateExistingLabels();
        }

        window.addEventListener('beforeunload', clearEntries, { once: true });
    }
})();
