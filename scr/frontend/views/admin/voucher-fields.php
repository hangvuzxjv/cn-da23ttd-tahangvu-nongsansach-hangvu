<?php
$voucherValues = $formValues ?? [];
$voucherErrors = $errors ?? [];
$selectedIds = static function (mixed $value): array {
    if (is_array($value)) {
        return array_map('intval', $value);
    }
    return array_map('intval', array_filter(explode(',', (string) $value), static fn (string $id): bool => $id !== ''));
};
$selectedCategories = $selectedIds($voucherValues['category_ids'] ?? []);
$selectedProducts = $selectedIds($voucherValues['product_ids'] ?? []);
$startValue = $voucherValues['starts_at'] ?? date('Y-m-d\TH:i');
$endValue = $voucherValues['ends_at'] ?? date('Y-m-d\TH:i', strtotime('+30 days'));
if (str_contains((string) $startValue, ' ')) {
    $startValue = date('Y-m-d\TH:i', strtotime((string) $startValue));
}
if (str_contains((string) $endValue, ' ')) {
    $endValue = date('Y-m-d\TH:i', strtotime((string) $endValue));
}
$fieldError = static function (string $name) use ($voucherErrors): string {
    return isset($voucherErrors[$name])
        ? '<div class="invalid-feedback d-block">' . e($voucherErrors[$name]) . '</div>'
        : '';
};
?>
<section class="card mb-3">
    <div class="card-header">
        <h2 class="h5 mb-0">Thông tin chung</h2>
        <p class="small text-muted mb-0">Tên mã và trạng thái hiển thị của voucher.</p>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="voucher-code">Mã voucher</label>
                <input class="form-control <?= isset($voucherErrors['code']) ? 'is-invalid' : '' ?>" id="voucher-code" name="code" required maxlength="30" pattern="[A-Za-z0-9_-]{3,30}" value="<?= e($voucherValues['code'] ?? '') ?>" aria-describedby="voucher-code-help">
                <div class="form-text" id="voucher-code-help">3–30 ký tự; hệ thống lưu dạng in hoa, chỉ gồm chữ A–Z, số, dấu - hoặc _.</div>
                <?= $fieldError('code') ?>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="voucher-description">Mô tả</label>
                <input class="form-control <?= isset($voucherErrors['description']) ? 'is-invalid' : '' ?>" id="voucher-description" name="description" maxlength="255" value="<?= e($voucherValues['description'] ?? '') ?>" aria-describedby="voucher-description-help">
                <div class="form-text" id="voucher-description-help">Ghi chú ngắn để nhân viên dễ nhận biết chương trình.</div>
                <?= $fieldError('description') ?>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="voucher-status">Trạng thái bật/tắt</label>
                <select class="form-select <?= isset($voucherErrors['status']) ? 'is-invalid' : '' ?>" id="voucher-status" name="status">
                    <option value="active" <?= ($voucherValues['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Bật</option>
                    <option value="inactive" <?= ($voucherValues['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Tắt</option>
                </select>
                <div class="form-text">Trạng thái sử dụng còn phụ thuộc thời gian và số lượt.</div>
                <?= $fieldError('status') ?>
            </div>
        </div>
    </div>
</section>

<section class="card mb-3">
    <div class="card-header">
        <h2 class="h5 mb-0">Đối tượng và cách phát hành</h2>
        <p class="small text-muted mb-0">Chọn khách nào có thể dùng mã và cách voucher được đưa vào ví.</p>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="voucher-audience">Đối tượng</label>
                <select class="form-select <?= isset($voucherErrors['audience']) ? 'is-invalid' : '' ?>" id="voucher-audience" name="audience">
                    <option value="code" <?= ($voucherValues['audience'] ?? 'code') === 'code' ? 'selected' : '' ?>>Nhập mã</option>
                    <option value="public" <?= ($voucherValues['audience'] ?? '') === 'public' ? 'selected' : '' ?>>Kho công khai</option>
                    <option value="targeted" <?= ($voucherValues['audience'] ?? '') === 'targeted' ? 'selected' : '' ?>>Chỉ người được tặng</option>
                </select>
                <div class="form-text">Mã và Kho công khai vẫn dùng được khi khách nhập mã; Chỉ người được tặng yêu cầu có voucher trong ví.</div>
                <?= $fieldError('audience') ?>
            </div>
            <div class="col-md-4" id="voucher-auto-rule-wrap" <?= ($voucherValues['audience'] ?? 'code') !== 'targeted' && !isset($voucherErrors['auto_rule']) ? 'hidden' : '' ?>>
                <label class="form-label" for="voucher-auto-rule">Tự tặng</label>
                <select class="form-select <?= isset($voucherErrors['auto_rule']) ? 'is-invalid' : '' ?>" id="voucher-auto-rule" name="auto_rule">
                    <option value="none" <?= ($voucherValues['auto_rule'] ?? 'none') === 'none' ? 'selected' : '' ?>>Không</option>
                    <option value="welcome" <?= ($voucherValues['auto_rule'] ?? '') === 'welcome' ? 'selected' : '' ?>>Khi đăng ký</option>
                    <option value="first_delivered_order" <?= ($voucherValues['auto_rule'] ?? '') === 'first_delivered_order' ? 'selected' : '' ?>>Khi hoàn tất đơn đầu tiên</option>
                </select>
                <div class="form-text">Chỉ áp dụng với voucher dành cho khách được tặng.</div>
                <?= $fieldError('auto_rule') ?>
            </div>
            <div class="col-md-4" id="voucher-valid-days-wrap" <?= ($voucherValues['audience'] ?? 'code') !== 'targeted' && !isset($voucherErrors['valid_days_after_grant']) ? 'hidden' : '' ?>>
                <label class="form-label" for="voucher-valid-days">Hạn dùng sau khi nhận (ngày)</label>
                <input class="form-control <?= isset($voucherErrors['valid_days_after_grant']) ? 'is-invalid' : '' ?>" id="voucher-valid-days" type="number" name="valid_days_after_grant" min="1" step="1" value="<?= e($voucherValues['valid_days_after_grant'] ?? '') ?>">
                <div class="form-text">Để trống để dùng theo hạn chung của voucher.</div>
                <?= $fieldError('valid_days_after_grant') ?>
            </div>
        </div>
    </div>
</section>

<section class="card mb-3">
    <div class="card-header">
        <h2 class="h5 mb-0">Mức giảm</h2>
        <p class="small text-muted mb-0">Chọn kiểu giảm và nhập số tiền hoặc phần trăm áp dụng.</p>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="voucher-discount-type">Loại giảm</label>
                <select class="form-select <?= isset($voucherErrors['discount_type']) ? 'is-invalid' : '' ?>" id="voucher-discount-type" name="discount_type">
                    <option value="fixed" <?= ($voucherValues['discount_type'] ?? 'fixed') === 'fixed' ? 'selected' : '' ?>>Số tiền (đ)</option>
                    <option value="percent" <?= ($voucherValues['discount_type'] ?? '') === 'percent' ? 'selected' : '' ?>>Phần trăm (%)</option>
                </select>
                <div class="form-text">Chọn một trong hai cách tính mức giảm.</div>
                <?= $fieldError('discount_type') ?>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="voucher-discount-value">Giá trị giảm</label>
                <input class="form-control <?= isset($voucherErrors['discount_value']) ? 'is-invalid' : '' ?>" id="voucher-discount-value" type="number" name="discount_value" min="1" step="1" required value="<?= e($voucherValues['discount_value'] ?? '') ?>" aria-describedby="voucher-discount-value-help">
                <div class="form-text" id="voucher-discount-value-help">Số tiền phải lớn hơn 0; phần trăm từ 1 đến 100.</div>
                <?= $fieldError('discount_value') ?>
            </div>
            <div class="col-md-4" id="voucher-maximum-wrap" <?= ($voucherValues['discount_type'] ?? 'fixed') !== 'percent' ? 'hidden' : '' ?>>
                <label class="form-label" for="voucher-maximum">Giảm tối đa (đ)</label>
                <input class="form-control <?= isset($voucherErrors['maximum_discount']) ? 'is-invalid' : '' ?>" id="voucher-maximum" type="number" name="maximum_discount" min="0" step="1" value="<?= e($voucherValues['maximum_discount'] ?? '') ?>" aria-describedby="voucher-maximum-help">
                <div class="form-text" id="voucher-maximum-help">Chỉ dùng với giảm theo phần trăm; để trống nếu không giới hạn.</div>
                <?= $fieldError('maximum_discount') ?>
            </div>
        </div>
    </div>
</section>

<section class="card mb-3">
    <div class="card-header">
        <h2 class="h5 mb-0">Điều kiện áp dụng</h2>
        <p class="small text-muted mb-0">Không chọn danh mục và sản phẩm thì áp dụng cho tất cả sản phẩm.</p>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="voucher-minimum">Giá trị đơn tối thiểu (đ)</label>
                <input class="form-control <?= isset($voucherErrors['minimum_order']) ? 'is-invalid' : '' ?>" id="voucher-minimum" type="number" name="minimum_order" min="0" step="1" required value="<?= e($voucherValues['minimum_order'] ?? '0') ?>">
                <div class="form-text">Nhập 0 nếu không yêu cầu giá trị tối thiểu.</div>
                <?= $fieldError('minimum_order') ?>
            </div>
            <div class="col-12">
                <p class="small text-muted mb-2">Nếu có chọn phạm vi, sản phẩm được áp dụng khi thuộc ít nhất một danh mục đã chọn HOẶC được chọn trực tiếp bên dưới.</p>
                <?= $fieldError('category_ids') ?>
                <?= $fieldError('product_ids') ?>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="voucher-category-search">Danh mục áp dụng</label>
                <div class="form-text mb-2">Tìm rồi đánh dấu một hoặc nhiều danh mục.</div>
                <input class="form-control mb-2" id="voucher-category-search" type="search" placeholder="Tìm danh mục...">
                <div class="voucher-picker-list" id="voucher-category-list">
                    <?php foreach ($categories as $category): ?>
                        <label class="form-check voucher-picker-option" data-search="<?= e(mb_strtolower($category['name'], 'UTF-8')) ?>">
                            <input class="form-check-input voucher-category-choice" type="checkbox" name="category_ids[]" value="<?= (int) $category['id'] ?>" <?= in_array((int) $category['id'], $selectedCategories, true) ? 'checked' : '' ?>>
                            <span class="form-check-label"><?= e($category['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                    <?php if (!$categories): ?><div class="text-muted small">Không có danh mục đang hoạt động.</div><?php endif; ?>
                </div>
                <div class="small text-muted mt-2">Đã chọn: <span id="voucher-category-count">0</span> danh mục</div>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="voucher-product-search">Sản phẩm áp dụng</label>
                <div class="form-text mb-2">Tìm theo tên hoặc SKU rồi đánh dấu sản phẩm.</div>
                <input class="form-control mb-2" id="voucher-product-search" type="search" placeholder="Tìm tên hoặc mã SKU...">
                <div class="voucher-picker-list" id="voucher-product-list">
                    <?php foreach ($products as $product): ?>
                        <label class="form-check voucher-picker-option" data-search="<?= e(mb_strtolower($product['name'] . ' ' . $product['sku'], 'UTF-8')) ?>">
                            <input class="form-check-input voucher-product-choice" type="checkbox" name="product_ids[]" value="<?= (int) $product['id'] ?>" <?= in_array((int) $product['id'], $selectedProducts, true) ? 'checked' : '' ?>>
                            <span class="form-check-label"><?= e($product['name']) ?> <span class="text-muted">(<?= e($product['sku']) ?>)</span></span>
                        </label>
                    <?php endforeach; ?>
                    <?php if (!$products): ?><div class="text-muted small">Không có sản phẩm đang hoạt động.</div><?php endif; ?>
                </div>
                <div class="small text-muted mt-2">Đã chọn: <span id="voucher-product-count">0</span> sản phẩm</div>
            </div>
        </div>
    </div>
</section>

<section class="card mb-3">
    <div class="card-header">
        <h2 class="h5 mb-0">Giới hạn và thời gian</h2>
        <p class="small text-muted mb-0">Để trống tổng lượt nếu voucher không giới hạn số lần sử dụng.</p>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label" for="voucher-usage-limit">Tổng lượt</label>
                <input class="form-control <?= isset($voucherErrors['usage_limit']) ? 'is-invalid' : '' ?>" id="voucher-usage-limit" type="number" name="usage_limit" min="1" step="1" value="<?= e($voucherValues['usage_limit'] ?? '') ?>">
                <div class="form-text">Để trống = không giới hạn.</div>
                <?= $fieldError('usage_limit') ?>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="voucher-per-user">Lượt mỗi người</label>
                <input class="form-control <?= isset($voucherErrors['per_user_limit']) ? 'is-invalid' : '' ?>" id="voucher-per-user" type="number" name="per_user_limit" min="1" step="1" required value="<?= e($voucherValues['per_user_limit'] ?? '1') ?>">
                <div class="form-text">Phải từ 1 trở lên và không vượt tổng lượt.</div>
                <?= $fieldError('per_user_limit') ?>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="voucher-starts">Bắt đầu</label>
                <input class="form-control <?= isset($voucherErrors['starts_at']) ? 'is-invalid' : '' ?>" id="voucher-starts" type="datetime-local" name="starts_at" required value="<?= e($startValue) ?>">
                <div class="form-text">Thời điểm voucher bắt đầu có hiệu lực.</div>
                <?= $fieldError('starts_at') ?>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="voucher-ends">Kết thúc</label>
                <input class="form-control <?= isset($voucherErrors['ends_at']) ? 'is-invalid' : '' ?>" id="voucher-ends" type="datetime-local" name="ends_at" required value="<?= e($endValue) ?>">
                <div class="form-text">Phải sau thời điểm bắt đầu.</div>
                <?= $fieldError('ends_at') ?>
            </div>
        </div>
    </div>
</section>

<script>
(() => {
    const discountType = document.getElementById('voucher-discount-type');
    const discountValue = document.getElementById('voucher-discount-value');
    const maximumWrap = document.getElementById('voucher-maximum-wrap');
    const maximumInput = document.getElementById('voucher-maximum');
    const audience = document.getElementById('voucher-audience');
    const autoRuleWrap = document.getElementById('voucher-auto-rule-wrap');
    const validDaysWrap = document.getElementById('voucher-valid-days-wrap');
    const showAutoRuleError = <?= isset($voucherErrors['auto_rule']) ? 'true' : 'false' ?>;
    const showValidDaysError = <?= isset($voucherErrors['valid_days_after_grant']) ? 'true' : 'false' ?>;
    const updateMaximumVisibility = () => {
        const isPercent = discountType.value === 'percent';
        maximumWrap.hidden = !isPercent;
        maximumInput.disabled = !isPercent;
        if (isPercent) {
            discountValue.max = '100';
        } else {
            discountValue.removeAttribute('max');
        }
    };
    discountType.addEventListener('change', updateMaximumVisibility);
    updateMaximumVisibility();

    const updateTargetedVisibility = () => {
        const isTargeted = audience.value === 'targeted';
        autoRuleWrap.hidden = !isTargeted && !showAutoRuleError;
        validDaysWrap.hidden = !isTargeted && !showValidDaysError;
    };
    audience.addEventListener('change', updateTargetedVisibility);
    updateTargetedVisibility();

    const setupPicker = (searchId, listId, choiceClass, countId) => {
        const search = document.getElementById(searchId);
        const list = document.getElementById(listId);
        const choices = Array.from(list.querySelectorAll('.' + choiceClass));
        const count = document.getElementById(countId);
        const update = () => {
            const term = search.value.trim().toLocaleLowerCase();
            list.querySelectorAll('.voucher-picker-option').forEach((option) => {
                option.hidden = !option.dataset.search.includes(term);
            });
            count.textContent = String(choices.filter((choice) => choice.checked).length);
        };
        search.addEventListener('input', update);
        choices.forEach((choice) => choice.addEventListener('change', update));
        update();
    };
    setupPicker('voucher-category-search', 'voucher-category-list', 'voucher-category-choice', 'voucher-category-count');
    setupPicker('voucher-product-search', 'voucher-product-list', 'voucher-product-choice', 'voucher-product-count');
})();
</script>
