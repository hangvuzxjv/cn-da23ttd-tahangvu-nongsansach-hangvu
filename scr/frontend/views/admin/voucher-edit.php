<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h2 mb-1"><?= $voucherId === null ? 'Tạo voucher' : 'Sửa voucher' ?></h1>
        <p class="text-muted mb-0"><?= $voucherId === null ? 'Điền thông tin để tạo mã giảm giá mới.' : 'Các thay đổi sẽ áp dụng cho những lần sử dụng tiếp theo.' ?></p>
    </div>
    <div class="d-flex gap-2">
        <?php if ($voucherId !== null && ($voucher['audience'] ?? '') === 'targeted' && Auth::can('vouchers.manage')): ?>
            <a class="btn btn-outline-success" href="<?= e(url('/admin/vouchers/' . $voucherId . '/gift')) ?>">Tặng voucher</a>
        <?php endif; ?>
        <a class="btn btn-outline-secondary" href="<?= e(url('/admin/vouchers')) ?>">Quay lại danh sách</a>
    </div>
</div>
<?php if (!empty($success)): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if (!empty($errors['form'])): ?><div class="alert alert-danger"><?= e($errors['form']) ?></div><?php endif; ?>
<form method="post" action="<?= e(url($formAction)) ?>" novalidate>
    <?= csrf_field() ?>
    <?php require FRONTEND_PATH . '/views/admin/voucher-fields.php'; ?>
    <div class="d-flex flex-wrap gap-2 mt-3">
        <button class="btn btn-primary" type="submit"><?= $voucherId === null ? 'Tạo voucher' : 'Lưu thay đổi' ?></button>
        <a class="btn btn-outline-secondary" href="<?= e(url('/admin/vouchers')) ?>">Hủy</a>
    </div>
</form>

<?php if ($voucherId !== null): ?>
    <section class="card mt-4" aria-labelledby="voucher-holders-heading">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h2 class="h5 mb-0" id="voucher-holders-heading">Khách đang giữ voucher</h2>
                <p class="small text-muted mb-0">
                    <?= number_format((int) $walletHolderTotal) ?> khách · trang <?= (int) $walletHolderPage ?> / <?= (int) $walletHolderPages ?>
                </p>
            </div>
            <?php if (($voucher['audience'] ?? '') === 'targeted' && Auth::can('vouchers.manage')): ?>
                <a class="btn btn-sm btn-outline-success" href="<?= e(url('/admin/vouchers/' . $voucherId . '/gift')) ?>">Tặng cho khách</a>
            <?php endif; ?>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Khách hàng</th><th>Ngày nhận</th><th>Hạn dùng</th><th>Trạng thái</th><th class="text-end">Thao tác</th></tr></thead>
                <tbody>
                <?php if (!$walletHolders): ?><tr><td colspan="5" class="text-center text-muted py-3">Chưa có khách nào giữ voucher này.</td></tr><?php endif; ?>
                <?php foreach ($walletHolders as $holder): ?>
                    <?php $holderUsed = $holder['used_order_id'] !== null || $holder['used_at'] !== null; ?>
                    <tr>
                        <td><?= e($holder['customer_name']) ?></td>
                        <td><?= e(date('d/m/Y H:i', strtotime($holder['granted_at']))) ?></td>
                        <td><?= e(date('d/m/Y H:i', strtotime($holder['expires_at']))) ?></td>
                        <td><?= $holderUsed ? 'Đã sử dụng' : 'Chưa sử dụng' ?></td>
                        <td class="text-end">
                            <?php if (!$holderUsed && Auth::can('vouchers.manage')): ?>
                                <form method="post" action="<?= e(url('/admin/vouchers/' . $voucherId . '/revoke')) ?>" onsubmit="return confirm('Thu hồi voucher này khỏi ví khách?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="user_voucher_id" value="<?= (int) $holder['user_voucher_id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Thu hồi</button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($walletHolderPages > 1): ?>
            <div class="card-footer d-flex justify-content-between">
                <?php if ($walletHolderPage > 1): ?><a href="<?= e(url('/admin/vouchers/' . $voucherId . '/edit?holder_page=' . ($walletHolderPage - 1))) ?>">Trang trước</a><?php else: ?><span></span><?php endif; ?>
                <?php if ($walletHolderPage < $walletHolderPages): ?><a href="<?= e(url('/admin/vouchers/' . $voucherId . '/edit?holder_page=' . ($walletHolderPage + 1))) ?>">Trang sau</a><?php endif; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="card mt-4" aria-labelledby="voucher-history-heading">
        <div class="card-header">
            <h2 class="h5 mb-0" id="voucher-history-heading">Lịch sử sử dụng gần nhất</h2>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Mã đơn</th><th>Khách hàng</th><th>Thời gian</th><th class="text-end">Số tiền giảm</th><th>Ghi chú</th></tr></thead>
                <tbody>
                <?php if (!$usageHistory): ?><tr><td colspan="5" class="text-center text-muted py-3">Chưa có lịch sử sử dụng.</td></tr><?php endif; ?>
                <?php foreach ($usageHistory as $usage): ?>
                    <tr>
                        <td><?= e($usage['order_no']) ?></td>
                        <td><?= e($usage['customer_name']) ?></td>
                        <td><?= e(date('d/m/Y H:i', strtotime($usage['used_at']))) ?></td>
                        <td class="text-end"><?= e(number_format((float) $usage['discount_amount'], 0, ',', '.')) ?> đ</td>
                        <td><?= $usage['status'] === 'reversed' ? 'Đã hoàn lượt' : 'Đã sử dụng' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>
