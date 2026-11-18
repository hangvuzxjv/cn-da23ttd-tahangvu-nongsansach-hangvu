(() => {
    const details = document.querySelector('[data-stocktake-details]');
    if (!details) {
        return;
    }

    const rows = Array.from(details.querySelectorAll('[data-stocktake-row]'));
    const search = details.querySelector('[data-stocktake-search]');
    const filter = details.querySelector('[data-stocktake-filter]');
    const summary = details.querySelector('[data-stocktake-summary]');
    const noResults = details.querySelector('[data-stocktake-no-results]');

    const rowCount = (row) => {
        const input = row.querySelector('[data-stocktake-count]');
        return input ? input.value : row.dataset.countedQuantity;
    };

    const rowDifference = (row) => {
        const count = rowCount(row);
        return count === '' ? null : Number(count) - Number(row.dataset.systemQuantity);
    };

    const updateDifference = (row) => {
        const difference = rowDifference(row);
        const output = row.querySelector('[data-stocktake-difference]');
        output.classList.remove('inventory-stocktake-difference--surplus', 'inventory-stocktake-difference--shortage');
        if (difference === null || !Number.isFinite(difference)) {
            output.textContent = '—';
        } else if (difference > 0) {
            output.classList.add('inventory-stocktake-difference--surplus');
            output.textContent = `+${difference} · Dư`;
        } else if (difference < 0) {
            output.classList.add('inventory-stocktake-difference--shortage');
            output.textContent = `${difference} · Thiếu`;
        } else {
            output.textContent = '0';
        }
    };

    const updateSummary = () => {
        const countedRows = rows.filter((row) => rowCount(row) !== '');
        const differenceRows = countedRows.filter((row) => {
            const difference = rowDifference(row);
            return difference !== null && difference !== 0;
        });
        summary.textContent = `Đã đếm ${countedRows.length}/${rows.length} dòng, có ${differenceRows.length} dòng chênh lệch.`;
    };

    const applyFilters = () => {
        const query = search.value.trim().toLocaleLowerCase('vi');
        let visibleRows = 0;
        rows.forEach((row) => {
            updateDifference(row);
            const searchableText = `${row.dataset.productName} ${row.dataset.batchCode}`.toLocaleLowerCase('vi');
            const difference = rowDifference(row);
            const isCounted = rowCount(row) !== '';
            const matchesFilter = filter.value === 'uncounted'
                ? !isCounted
                : (filter.value === 'difference' ? isCounted && difference !== 0 : true);
            const matchesSearch = searchableText.includes(query);
            row.hidden = !matchesFilter || !matchesSearch;
            if (!row.hidden) {
                visibleRows++;
            }
        });
        noResults.classList.toggle('d-none', rows.length === 0 || visibleRows > 0);
        updateSummary();
    };

    search.addEventListener('input', applyFilters);
    filter.addEventListener('change', applyFilters);
    details.addEventListener('input', (event) => {
        if (event.target.matches('[data-stocktake-count]')) {
            const row = event.target.closest('[data-stocktake-row]');
            row.dataset.countedQuantity = event.target.value;
            applyFilters();
        }
    });
    applyFilters();

    const confirmForm = details.querySelector('[data-stocktake-confirm]');
    if (confirmForm) {
        confirmForm.addEventListener('submit', (event) => {
            const uncounted = rows.filter((row) => row.dataset.savedCountedQuantity === '').length;
            const message = uncounted > 0
                ? `Còn ${uncounted} dòng chưa đếm, các dòng này sẽ được giữ nguyên tồn. Bạn có muốn tiếp tục xác nhận kiểm kê?`
                : 'Xác nhận kiểm kê và ghi nhận chênh lệch vào nhật ký kho?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    }
})();
