<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h2 mb-1">Tặng voucher <?= e($voucher['code']) ?></h1>
        <p class="text-muted mb-0"><?= e($voucher['description'] ?: 'Chọn khách hàng để thêm voucher vào ví.') ?></p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= e(url('/admin/vouchers/' . $voucher['id'] . '/edit')) ?>">Quay lại sửa voucher</a>
</div>
<?php if (!empty($success)): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<section class="card mb-4">
    <div class="card-header">
        <h2 class="h5 mb-0">Tìm và chọn khách hàng</h2>
        <p class="small text-muted mb-0">Tối đa 100 khách mỗi lần. Khách đã có voucher này sẽ được bỏ qua.</p>
    </div>
    <div class="card-body">
        <form class="row g-2 align-items-end mb-3" method="get" action="<?= e(url('/admin/vouchers/' . $voucher['id'] . '/gift')) ?>">
            <div class="col-md-9">
                <label class="form-label" for="voucher-customer-search">Tìm theo tên, email hoặc số điện thoại</label>
                <input class="form-control" id="voucher-customer-search" type="search" name="q" value="<?= e($search) ?>" placeholder="Nhập thông tin khách hàng">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-outline-primary flex-grow-1" type="submit">Tìm khách</button>
                <a class="btn btn-outline-secondary" href="<?= e(url('/admin/vouchers/' . $voucher['id'] . '/gift')) ?>">Xóa</a>
            </div>
        </form>

        <form method="post" action="<?= e(url('/admin/vouchers/' . $voucher['id'] . '/gift')) ?>" id="voucher-gift-selected">
            <?= csrf_field() ?>
            <?php if (!$customers): ?>
                <p class="text-muted mb-0">Không tìm thấy khách hàng đang hoạt động.</p>
            <?php else: ?>
                <div class="voucher-picker-list mb-3" aria-label="Khách hàng đang hoạt động">
                    <?php foreach ($customers as $customer): ?>
                        <label class="form-check voucher-picker-option">
                            <input class="form-check-input voucher-gift-choice" type="checkbox" name="user_ids[]" value="<?= (int) $customer['id'] ?>">
                            <span class="form-check-label"><?= e($customer['name']) ?> <span class="text-muted">(<?= e($customer['email']) ?><?= $customer['phone'] ? ' · ' . e($customer['phone']) : '' ?>)</span></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <button class="btn btn-success" type="submit" <?= !$customers ? 'disabled' : '' ?>>Tặng cho khách đã chọn</button>
                <span class="small text-muted">Đã chọn <span id="voucher-gift-count">0</span> / 100 khách</span>
            </div>
        </form>
    </div>
</section>

<section class="card border-warning">
    <div class="card-header bg-warning-subtle">
        <h2 class="h5 mb-0">Tặng cho tất cả khách đang hoạt động</h2>
    </div>
    <div class="card-body">
        <p>Thao tác này sẽ thêm voucher vào ví của tất cả tài khoản khách hàng đang hoạt động. Khách đã có voucher sẽ được bỏ qua.</p>
        <form method="post" action="<?= e(url('/admin/vouchers/' . $voucher['id'] . '/gift')) ?>" onsubmit="return confirm('Bạn chắc chắn muốn tặng voucher này cho toàn bộ khách đang hoạt động?')">
            <?= csrf_field() ?>
            <input type="hidden" name="all_active" value="1">
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="confirm-gift-all" name="confirm_all" value="1" required>
                <label class="form-check-label" for="confirm-gift-all">Tôi xác nhận tặng voucher cho toàn bộ khách đang hoạt động.</label>
            </div>
            <button class="btn btn-outline-warning" type="submit">Xác nhận và tặng tất cả</button>
        </form>
    </div>
</section>

<script>
(() => {
    const form = document.getElementById('voucher-gift-selected');
    const count = document.getElementById('voucher-gift-count');
    const choices = Array.from(form.querySelectorAll('.voucher-gift-choice'));
    const update = () => {
        const selected = choices.filter((choice) => choice.checked);
        count.textContent = String(selected.length);
        choices.forEach((choice) => {
            choice.disabled = !choice.checked && selected.length >= 100;
        });
    };
    choices.forEach((choice) => choice.addEventListener('change', update));
    form.addEventListener('submit', (event) => {
        if (choices.filter((choice) => choice.checked).length > 100) {
            event.preventDefault();
            window.alert('Mỗi lần chỉ có thể tặng tối đa 100 khách.');
        }
    });
    update();
})();
</script>
