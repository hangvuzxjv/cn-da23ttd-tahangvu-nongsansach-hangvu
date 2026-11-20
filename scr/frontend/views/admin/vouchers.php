<?php
$voucherStatuses = [
    'inactive' => ['Tạm tắt', 'secondary'],
    'upcoming' => ['Chưa bắt đầu', 'info'],
    'running' => ['Đang chạy', 'success'],
    'expired' => ['Hết hạn', 'dark'],
    'exhausted' => ['Hết lượt', 'warning'],
];
$voucherAudiences = [
    'code' => 'Nhập mã',
    'public' => 'Kho công khai',
    'targeted' => 'Chỉ người được tặng',
];
$formatVoucherMoney = static fn (mixed $amount): string => number_format((float) $amount, 0, ',', '.') . ' đ';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h2 mb-1">Quản lý voucher</h1>
        <p class="text-muted mb-0">Tìm, theo dõi và chỉnh sửa mã giảm giá.</p>
    </div>
    <?php if ($canManage): ?>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-success" href="<?= e(url('/admin/vouchers/missions')) ?>">Thưởng nhiệm vụ</a>
            <a class="btn btn-success" href="<?= e(url('/admin/vouchers/create')) ?>">Tạo voucher</a>
        </div>
    <?php endif; ?>
</div>
<?php if (!empty($success)): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<form class="card card-body mb-3" method="get" action="<?= e(url('/admin/vouchers')) ?>">
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label" for="voucher-search">Tìm theo mã</label>
            <input class="form-control" id="voucher-search" name="q" value="<?= e($filters['q']) ?>" placeholder="Nhập mã voucher">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="voucher-status-filter">Lọc theo trạng thái</label>
            <select class="form-select" id="voucher-status-filter" name="status">
                <option value="">Tất cả trạng thái</option>
                <?php foreach ($voucherStatuses as $key => [$label]): ?>
                    <option value="<?= e($key) ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="voucher-audience-filter">Lọc theo đối tượng</label>
            <select class="form-select" id="voucher-audience-filter" name="audience">
                <option value="">Tất cả đối tượng</option>
                <?php foreach ($voucherAudiences as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $filters['audience'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button class="btn btn-primary flex-grow-1" type="submit">Lọc</button>
            <a class="btn btn-outline-secondary" href="<?= e(url('/admin/vouchers')) ?>">Xóa lọc</a>
        </div>
    </div>
</form>
<div class="text-muted small mb-2">Tìm thấy <?= number_format((int) $total) ?> voucher.</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 voucher-table">
            <thead class="table-light">
            <tr>
                <th>Mã</th>
                <th>Mô tả</th>
                <th>Mức giảm</th>
                <th>Điều kiện</th>
                <th>Hiệu lực</th>
                <th>Đã dùng / Tổng lượt</th>
                <th>Đối tượng</th>
                <th>Trạng thái</th>
                <th class="text-end">Thao tác</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$vouchers): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">Không tìm thấy voucher phù hợp.</td></tr>
            <?php endif; ?>
            <?php foreach ($vouchers as $voucher): ?>
                <?php [$statusLabel, $statusColor] = $voucherStatuses[$voucher['computed_status']] ?? ['Không xác định', 'secondary']; ?>
                <tr>
                    <td><code class="fw-semibold"><?= e($voucher['code']) ?></code></td>
                    <td><?= e($voucher['description'] ?: '—') ?></td>
                    <td class="text-nowrap">
                        <?php if ($voucher['discount_type'] === 'percent'): ?>
                            Giảm <?= e(number_format((float) $voucher['discount_value'], 0, ',', '.')) ?>%
                            <?php if ($voucher['maximum_discount'] !== null): ?><div class="small text-muted">Tối đa <?= e($formatVoucherMoney($voucher['maximum_discount'])) ?></div><?php endif; ?>
                        <?php else: ?>
                            Giảm <?= e($formatVoucherMoney($voucher['discount_value'])) ?>
                        <?php endif; ?>
                    </td>
                    <td class="text-nowrap"><?= (float) $voucher['minimum_order'] > 0 ? 'Đơn từ ' . e($formatVoucherMoney($voucher['minimum_order'])) : 'Không yêu cầu' ?></td>
                    <td class="text-nowrap">
                        <?= e(date('d/m/Y H:i', strtotime($voucher['starts_at']))) ?><br>
                        <span class="text-muted">đến <?= e(date('d/m/Y H:i', strtotime($voucher['ends_at']))) ?></span>
                    </td>
                    <td class="text-nowrap"><?= number_format((int) $voucher['used_count']) ?> / <?= $voucher['usage_limit'] === null ? '∞' : number_format((int) $voucher['usage_limit']) ?></td>
                    <td class="text-nowrap"><?= e($voucherAudiences[$voucher['audience']] ?? 'Không xác định') ?></td>
                    <td><span class="badge text-bg-<?= e($statusColor) ?>"><?= e($statusLabel) ?></span></td>
                    <td class="voucher-actions-cell">
                        <div class="voucher-row-actions">
                            <a class="btn btn-primary admin-table-action-btn" href="<?= e(url('/admin/vouchers/' . $voucher['id'] . '/edit')) ?>">Sửa</a>
                            <?php if ($canManage): ?>
                                <?php $actionsMenuId = 'voucher-actions-' . (int) $voucher['id']; ?>
                                <div class="voucher-actions-menu">
                                    <button class="btn btn-outline-secondary admin-table-action-btn voucher-actions-toggle" type="button" aria-label="Thao tác voucher <?= e($voucher['code']) ?>" aria-controls="<?= e($actionsMenuId) ?>" aria-expanded="false" data-voucher-menu-toggle>⋯</button>
                                    <div class="voucher-actions-dropdown" id="<?= e($actionsMenuId) ?>" hidden>
                                        <?php if ($voucher['audience'] === 'targeted'): ?>
                                            <a class="voucher-actions-dropdown__item" href="<?= e(url('/admin/vouchers/' . $voucher['id'] . '/gift')) ?>">Tặng voucher</a>
                                        <?php endif; ?>
                                        <form class="voucher-actions-dropdown__form" method="post" action="<?= e(url('/admin/vouchers/' . $voucher['id'] . '/toggle')) ?>">
                                            <?= csrf_field() ?>
                                            <button class="voucher-actions-dropdown__item" type="submit"><?= $voucher['status'] === 'active' ? 'Tắt' : 'Bật' ?></button>
                                        </form>
                                        <form class="voucher-actions-dropdown__form" method="post" action="<?= e(url('/admin/vouchers/' . $voucher['id'] . '/duplicate')) ?>">
                                            <?= csrf_field() ?>
                                            <button class="voucher-actions-dropdown__item" type="submit">Nhân bản</button>
                                        </form>
                                        <?php if (!(int) $voucher['has_ever_been_used'] && (int) $voucher['used_count'] === 0): ?>
                                            <form class="voucher-actions-dropdown__form" method="post" action="<?= e(url('/admin/vouchers/' . $voucher['id'] . '/delete')) ?>" onsubmit="return confirm('Xóa voucher chưa từng được sử dụng?')">
                                                <?= csrf_field() ?>
                                                <button class="voucher-actions-dropdown__item voucher-actions-dropdown__item--danger" type="submit">Xóa</button>
                                            </form>
                                        <?php else: ?>
                                            <button class="voucher-actions-dropdown__item voucher-actions-dropdown__item--danger" type="button" disabled title="Voucher đã được sử dụng, chỉ có thể tắt.">Xóa</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include FRONTEND_PATH . '/views/admin/_pagination.php'; ?>
<script src="<?= e(asset('/js/voucher-actions.js')) ?>" defer></script>
