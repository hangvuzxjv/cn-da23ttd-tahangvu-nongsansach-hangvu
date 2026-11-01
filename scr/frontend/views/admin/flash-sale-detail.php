<?php
$isNew = $isNew ?? ($campaign === null);
$editable = $isNew || $campaign['status'] !== 'cancelled';
$statusNames = [
    'draft' => 'Nháp',
    'scheduled' => 'Sắp diễn ra',
    'active' => 'Đang diễn ra',
    'ended' => 'Đã kết thúc',
    'cancelled' => 'Đã hủy / ẩn',
];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <a class="small text-decoration-none" href="<?= e(url('/admin/flash-sales')) ?>">&larr; Danh sách Flash Sale</a>
        <h1 class="h2 mt-1 mb-0"><?= $isNew ? 'Tạo chương trình Flash Sale' : e($campaign['name']) ?></h1>
    </div>
    <?php if (!$isNew): ?>
        <span class="badge text-bg-secondary"><?= e($statusNames[$campaign['campaign_status']] ?? 'Không xác định') ?></span>
    <?php endif; ?>
</div>
<?php if (!empty($error)): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<?php if ($campaign !== null || $isNew): ?>
    <div id="flash-sale-message" class="alert d-none" role="alert"></div>
    <div class="card mb-4">
        <div class="card-header fw-semibold">Thông tin chương trình</div>
        <div class="card-body">
            <?php if (!$editable): ?>
                <div class="alert alert-warning">Chương trình đã hủy/ẩn và không thể chỉnh sửa.</div>
            <?php endif; ?>
            <form id="flash-sale-form">
                <?= csrf_field() ?>
                <?php if (!$isNew): ?><input type="hidden" name="id" value="<?= (int) $campaign['id'] ?>"><?php endif; ?>
                <div class="row g-3">
                    <div class="col-12 col-lg-6">
                        <label class="form-label" for="campaign-name">Tên chương trình</label>
                        <input class="form-control" id="campaign-name" name="name" maxlength="180" required value="<?= e($campaign['name'] ?? '') ?>" <?= $editable ? '' : 'disabled' ?>>
                    </div>
                    <div class="col-12 col-lg-3">
                        <label class="form-label" for="campaign-starts">Bắt đầu</label>
                        <input class="form-control" id="campaign-starts" type="datetime-local" name="starts_at" required value="<?= $isNew ? '' : e(str_replace(' ', 'T', substr($campaign['starts_at'], 0, 16))) ?>" <?= $editable ? '' : 'disabled' ?>>
                    </div>
                    <div class="col-12 col-lg-3">
                        <label class="form-label" for="campaign-ends">Kết thúc</label>
                        <input class="form-control" id="campaign-ends" type="datetime-local" name="ends_at" required value="<?= $isNew ? '' : e(str_replace(' ', 'T', substr($campaign['ends_at'], 0, 16))) ?>" <?= $editable ? '' : 'disabled' ?>>
                    </div>
                    <div class="col-12 col-lg-3">
                        <label class="form-label" for="campaign-status">Trạng thái lưu</label>
                        <?php $saveStatus = in_array($campaign['status'] ?? '', ['draft', 'scheduled'], true) ? $campaign['status'] : 'scheduled'; ?>
                        <select class="form-select" id="campaign-status" name="status" <?= $editable ? '' : 'disabled' ?>>
                            <option value="draft" <?= $saveStatus === 'draft' ? 'selected' : '' ?>>Nháp</option>
                            <option value="scheduled" <?= $saveStatus === 'scheduled' ? 'selected' : '' ?>>Sắp diễn ra</option>
                        </select>
                    </div>
                    <div class="col-12 col-lg-9">
                        <label class="form-label" for="campaign-description">Mô tả (không bắt buộc)</label>
                        <input class="form-control" id="campaign-description" name="description" maxlength="5000" value="<?= e($campaign['description'] ?? '') ?>" <?= $editable ? '' : 'disabled' ?>>
                    </div>
                    <?php if ($campaign && $campaign['campaign_status'] === 'active'): ?>
                        <div class="col-12">
                            <label class="form-label" for="adjustment-reason">Lý do điều chỉnh chương trình đang diễn ra</label>
                            <input class="form-control" id="adjustment-reason" name="adjustment_reason" maxlength="500" <?= $editable ? '' : 'disabled' ?>>
                        </div>
                    <?php endif; ?>
                </div>

                <hr class="my-4">
                <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
                    <div>
                        <h2 class="h5 mb-1">Sản phẩm tham gia</h2>
                        <p class="small text-muted mb-0">Giá Flash Sale phải thấp hơn giá bán hiện tại. Các suất vẫn được kiểm tra tồn kho thật khi đặt hàng.</p>
                    </div>
                    <?php if ($editable): ?>
                        <div class="d-flex gap-2">
                            <input id="product-search" class="form-control" type="search" placeholder="Tìm tên hoặc mã sản phẩm" autocomplete="off">
                            <button id="search-products" class="btn btn-outline-secondary text-nowrap" type="button">Tìm sản phẩm</button>
                        </div>
                    <?php endif; ?>
                </div>
                <div id="product-search-results" class="list-group mb-3"></div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                        <tr><th>Sản phẩm</th><th style="min-width:130px">Giá flash</th><th style="min-width:105px">Quota</th><th style="min-width:105px">Giới hạn/người</th><th class="text-end">Đã bán</th><th class="text-end">Còn lại</th><th class="text-end">Doanh thu</th><th></th></tr>
                        </thead>
                        <tbody id="flash-sale-items">
                        <?php foreach (($campaign['items'] ?? []) as $item): ?>
                            <?php $stockWarning = (int) $item['quota'] > (int) $item['available_stock']; ?>
                            <tr class="flash-sale-item-row" data-product-id="<?= (int) $item['product_id'] ?>" data-sold="<?= (int) $item['sold_quantity'] ?>" data-available-stock="<?= (int) $item['available_stock'] ?>">
                                <td>
                                    <input type="hidden" name="product_id[]" value="<?= (int) $item['product_id'] ?>">
                                    <div class="fw-semibold"><?= e($item['product_name']) ?></div>
                                    <div class="small text-muted"><?= e($item['sku'] ?? '') ?> · Giá hiện tại <?= number_format((int) $item['current_price']) ?>₫ · Tồn khả dụng <?= number_format((int) $item['available_stock']) ?></div>
                                    <div class="small text-warning-emphasis stock-warning <?= $stockWarning ? '' : 'd-none' ?>">Cảnh báo: quota vượt tồn kho khả dụng.</div>
                                </td>
                                <td><input class="form-control form-control-sm" type="number" name="sale_price[]" min="1" required value="<?= (int) $item['sale_price'] ?>" <?= $editable ? '' : 'disabled' ?>></td>
                                <td><input class="form-control form-control-sm" type="number" name="quota[]" data-quota-input min="<?= max(1, (int) $item['sold_quantity']) ?>" required value="<?= (int) $item['quota'] ?>" <?= $editable ? '' : 'disabled' ?>></td>
                                <td><input class="form-control form-control-sm" type="number" name="per_user_limit[]" min="1" required value="<?= (int) $item['per_user_limit'] ?>" <?= $editable ? '' : 'disabled' ?>></td>
                                <td class="text-end"><?= number_format((int) $item['sold_quantity']) ?></td>
                                <td class="text-end"><?= number_format((int) $item['remaining']) ?></td>
                                <td class="text-end text-nowrap"><?= number_format((float) $item['revenue']) ?>₫</td>
                                <td><input type="hidden" name="sort_order[]" value="<?= (int) $item['sort_order'] ?>"><?php if ($editable): ?><button class="btn btn-sm btn-outline-danger remove-flash-item" type="button">Gỡ</button><?php endif; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($editable): ?><button class="btn btn-primary" type="submit"><?= $isNew ? 'Tạo chương trình' : 'Lưu thay đổi' ?></button><?php endif; ?>
            </form>
        </div>
    </div>
    <?php if ($campaign && Auth::can('flash_sales.manage')): ?>
        <div class="card">
            <div class="card-header fw-semibold">Thao tác chương trình</div>
            <div class="card-body d-flex flex-wrap gap-3 align-items-end">
                <?php if ($campaign['status'] !== 'cancelled'): ?>
                    <form class="flash-sale-action-form" method="post" action="<?= e(url('/admin/flash-sales/' . $campaign['id'] . '/cancel')) ?>" data-confirm="Hủy/ẩn chương trình này?">
                        <?= csrf_field() ?>
                        <label class="form-label" for="cancel-reason">Lý do hủy</label>
                        <div class="d-flex gap-2">
                            <input class="form-control" id="cancel-reason" name="reason" maxlength="500" required>
                            <button class="btn btn-warning text-nowrap" type="submit">Hủy / ẩn chương trình</button>
                        </div>
                    </form>
                <?php endif; ?>
                <form class="flash-sale-action-form" method="post" action="<?= e(url('/admin/flash-sales/' . $campaign['id'] . '/delete')) ?>" data-confirm="Xóa hẳn chương trình này? Chỉ xóa được chương trình chưa có đơn hàng.">
                    <?= csrf_field() ?>
                    <button class="btn btn-outline-danger" type="submit">Xóa chương trình</button>
                </form>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>
<script>
(() => {
    const form = document.getElementById('flash-sale-form');
    if (!form) return;
    const message = document.getElementById('flash-sale-message');
    const itemList = document.getElementById('flash-sale-items');
    const resultList = document.getElementById('product-search-results');
    const csrf = form.querySelector('[name="_token"]').value;
    const showMessage = (text, success = false) => {
        message.textContent = text;
        message.className = `alert ${success ? 'alert-success' : 'alert-danger'}`;
        message.scrollIntoView({ behavior: 'smooth', block: 'center' });
    };
    const request = async (url, options = {}) => {
        const response = await fetch(url, {
            ...options,
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(options.headers || {}) }
        });
        const payload = await response.json();
        if (!response.ok || !payload.success) throw new Error(payload.message || 'Yêu cầu không thành công.');
        return payload;
    };
    document.getElementById('search-products')?.addEventListener('click', async () => {
        const query = document.getElementById('product-search').value.trim();
        if (!query) {
            resultList.replaceChildren();
            return;
        }
        try {
            const payload = await request(`<?= e(url('/admin/flash-sales/products')) ?>?q=${encodeURIComponent(query)}`);
            resultList.replaceChildren();
            payload.data.products.forEach(product => {
                if ([...itemList.querySelectorAll('[data-product-id]')].some(row => Number(row.dataset.productId) === Number(product.id))) return;
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'list-group-item list-group-item-action';
                button.textContent = `${product.name} (${product.sku || 'chưa có mã'}) · Giá ${Number(product.current_price).toLocaleString('vi-VN')}₫ · Tồn ${Number(product.available_stock).toLocaleString('vi-VN')}`;
                button.addEventListener('click', () => {
                    const row = document.createElement('tr');
                    row.className = 'flash-sale-item-row';
                    row.dataset.productId = product.id;
                    row.dataset.sold = '0';
                    row.dataset.availableStock = Math.max(0, Number(product.available_stock));
                    row.innerHTML = `<td><input type="hidden" name="product_id[]" value="${Number(product.id)}"><div class="fw-semibold"></div><div class="small text-muted"></div><div class="small text-warning-emphasis stock-warning d-none">Cảnh báo: quota vượt tồn kho khả dụng.</div></td>
                        <td><input class="form-control form-control-sm" type="number" name="sale_price[]" min="1" required></td>
                        <td><input class="form-control form-control-sm" type="number" name="quota[]" data-quota-input min="1" value="1" required></td>
                        <td><input class="form-control form-control-sm" type="number" name="per_user_limit[]" min="1" value="1" required></td>
                        <td class="text-end">0</td><td class="text-end">0</td><td class="text-end">0₫</td>
                        <td><input type="hidden" name="sort_order[]" value="${itemList.rows.length}"><button class="btn btn-sm btn-outline-danger remove-flash-item" type="button">Gỡ</button></td>`;
                    row.querySelector('.fw-semibold').textContent = product.name;
                    row.querySelector('.small.text-muted').textContent = `${product.sku || ''} · Giá hiện tại ${Number(product.current_price).toLocaleString('vi-VN')}₫ · Tồn khả dụng ${Number(product.available_stock).toLocaleString('vi-VN')}`;
                    row.querySelector('[name="sale_price[]"]').value = Math.max(1, Number(product.current_price) - 1);
                    itemList.append(row);
                    row.querySelector('[name="quota[]"]').dispatchEvent(new Event('input', { bubbles: true }));
                    resultList.replaceChildren();
                });
                resultList.append(button);
            });
            if (!payload.data.products.length) resultList.textContent = 'Không tìm thấy sản phẩm phù hợp.';
        } catch (error) {
            showMessage(error.message);
        }
    });
    itemList.addEventListener('click', event => {
        if (event.target.closest('.remove-flash-item')) event.target.closest('tr').remove();
    });
    itemList.addEventListener('input', event => {
        if (!event.target.matches('[data-quota-input]')) return;
        const row = event.target.closest('tr');
        const warning = row.querySelector('.stock-warning');
        warning.classList.toggle('d-none', Number(event.target.value) <= Number(row.dataset.availableStock));
    });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        try {
            const payload = await request(`<?= e(url('/admin/flash-sales')) ?>`, {
                method: 'POST',
                headers: { 'X-CSRF-Token': csrf },
                body: new FormData(form)
            });
            showMessage(payload.message, true);
            window.location.assign(payload.data.url);
        } catch (error) {
            showMessage(error.message);
        }
    });
    document.querySelectorAll('.flash-sale-action-form').forEach(actionForm => {
        actionForm.addEventListener('submit', async event => {
            event.preventDefault();
            if (!window.confirm(actionForm.dataset.confirm)) return;
            try {
                const payload = await request(actionForm.action, {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': csrf },
                    body: new FormData(actionForm)
                });
                showMessage(payload.message, true);
                if (actionForm.action.endsWith('/delete')) window.location.assign(`<?= e(url('/admin/flash-sales')) ?>`);
                else window.location.reload();
            } catch (error) {
                showMessage(error.message);
            }
        });
    });
})();
</script>
