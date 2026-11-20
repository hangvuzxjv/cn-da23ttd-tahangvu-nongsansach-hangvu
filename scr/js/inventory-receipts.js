(() => {
    const form = document.querySelector('[data-receipt-form]');
    if (!form) {
        return;
    }

    const farm = form.querySelector('[data-receipt-farm]');
    const lines = form.querySelector('[data-receipt-lines]');
    const template = form.querySelector('[data-receipt-line-template]');
    const addButton = form.querySelector('[data-receipt-add-line]');
    const errorMessage = form.querySelector('[data-receipt-error]');

    const showError = (message, field) => {
        errorMessage.textContent = message;
        errorMessage.classList.remove('d-none');
        if (field) {
            field.focus();
        }
    };

    const clearError = () => {
        errorMessage.textContent = '';
        errorMessage.classList.add('d-none');
    };

    const updateLineUnit = (line) => {
        const product = line.querySelector('[data-receipt-field="product"]');
        const unitLabel = product.selectedOptions[0]?.dataset.unitLabel || '';
        const unitOutput = line.querySelector('[data-receipt-unit]');
        const costLabel = line.querySelector('[data-receipt-cost-label]');
        const note = line.querySelector('[data-receipt-note]');
        unitOutput.textContent = unitLabel;
        costLabel.textContent = unitLabel ? `Giá nhập / ${unitLabel}` : 'Giá nhập / đơn vị';
        note.hidden = unitLabel === '';
        note.textContent = unitLabel ? `Nhập theo số ${unitLabel} đã đóng gói sẵn.` : '';
    };

    const updateLineOptions = (line) => {
        const product = line.querySelector('[data-receipt-field="product"]');
        const batch = line.querySelector('[data-receipt-field="batch"]');

        Array.from(product.options).forEach((option) => {
            const wrongFarm = farm.value !== ''
                && option.dataset.farmId
                && option.dataset.farmId !== farm.value;
            option.disabled = Boolean(wrongFarm);
        });
        if (product.selectedOptions[0] && product.selectedOptions[0].disabled) {
            product.value = '';
        }

        Array.from(batch.options).forEach((option) => {
            const wrongProduct = product.value !== ''
                && option.dataset.productId
                && option.dataset.productId !== product.value;
            option.disabled = Boolean(wrongProduct);
        });
        if (batch.selectedOptions[0] && batch.selectedOptions[0].disabled) {
            batch.value = '';
        }
        updateLineUnit(line);
    };

    const allLines = () => Array.from(lines.querySelectorAll('[data-receipt-line]'));
    allLines().forEach(updateLineOptions);
    farm.addEventListener('change', () => {
        allLines().forEach(updateLineOptions);
        clearError();
    });

    form.addEventListener('change', (event) => {
        const line = event.target.closest('[data-receipt-line]');
        if (line && event.target.matches('[data-receipt-field="product"]')) {
            updateLineOptions(line);
        }
        clearError();
    });
    form.addEventListener('input', clearError);

    addButton.addEventListener('click', () => {
        clearError();
        if (allLines().length >= 50) {
            showError('Phiếu nhập không thể có quá 50 dòng sản phẩm.', addButton);
            return;
        }

        const line = template.content.firstElementChild.cloneNode(true);
        lines.appendChild(line);
        updateLineOptions(line);
        line.querySelector('[data-receipt-field="product"]').focus();
    });

    lines.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-receipt-remove-line]');
        if (!removeButton) {
            return;
        }

        removeButton.closest('[data-receipt-line]').remove();
        clearError();
    });

    form.addEventListener('submit', (event) => {
        clearError();
        if (!farm.value) {
            event.preventDefault();
            showError('Vui lòng chọn nông trại.', farm);
            return;
        }

        let validLineCount = 0;
        for (const [index, line] of allLines().entries()) {
            const product = line.querySelector('[data-receipt-field="product"]');
            const batch = line.querySelector('[data-receipt-field="batch"]');
            const quantity = line.querySelector('[data-receipt-field="quantity"]');
            const cost = line.querySelector('[data-receipt-field="cost"]');
            const harvested = line.querySelector('[data-receipt-field="harvested"]');
            const packed = line.querySelector('[data-receipt-field="packed"]');
            const expires = line.querySelector('[data-receipt-field="expires"]');
            const dates = [harvested, packed, expires];
            const enteredCost = cost.value !== '' && Number(cost.value) !== 0;
            const hasValues = product.value !== ''
                || batch.value !== ''
                || quantity.value !== ''
                || dates.some((field) => field.value !== '')
                || enteredCost;

            if (!hasValues) {
                continue;
            }

            if (!product.value) {
                event.preventDefault();
                showError(`Vui lòng chọn sản phẩm ở dòng ${index + 1}.`, product);
                return;
            }
            if (!quantity.value || !Number.isInteger(Number(quantity.value)) || Number(quantity.value) < 1) {
                event.preventDefault();
                showError(`Vui lòng nhập số lượng hợp lệ ở dòng ${index + 1}.`, quantity);
                return;
            }
            if (cost.value !== '' && (!Number.isFinite(Number(cost.value)) || Number(cost.value) < 0)) {
                event.preventDefault();
                showError(`Vui lòng kiểm tra giá nhập ở dòng ${index + 1}.`, cost);
                return;
            }

            if (!batch.value) {
                if (dates.some((field) => field.value === '')) {
                    event.preventDefault();
                    showError(`Vui lòng nhập đủ ngày thu hoạch, ngày đóng gói và hạn dùng cho lô mới ở dòng ${index + 1}.`, dates.find((field) => field.value === ''));
                    return;
                }
                if (harvested.value > packed.value || packed.value > expires.value) {
                    event.preventDefault();
                    showError(`Ngày thu hoạch, đóng gói và hạn dùng chưa đúng thứ tự ở dòng ${index + 1}.`, harvested);
                    return;
                }
            }

            validLineCount++;
        }

        if (validLineCount === 0) {
            event.preventDefault();
            showError('Vui lòng thêm ít nhất một sản phẩm và nhập số lượng.', addButton);
        }
    });
})();
