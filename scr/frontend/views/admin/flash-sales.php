<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h2 mb-1">Flash Sale</h1>
        <p class="text-muted mb-0">Quản lý chương trình, suất bán và doanh thu ưu đãi.</p>
    </div>
    <?php if (Auth::can('flash_sales.manage')): ?>
        <a class="btn btn-success" href="<?= e(url('/admin/flash-sales/new')) ?>">Tạo chương trình</a>
    <?php endif; ?>
</div>
<?php if (!empty($error)): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th>Chương trình</th><th>Thời gian</th><th>Trạng thái</th>
                <th class="text-end">Tổng suất</th><th class="text-end">Đã bán</th><th></th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$campaigns): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">Chưa có chương trình Flash Sale.</td></tr>
            <?php endif; ?>
            <?php foreach ($campaigns as $campaign): ?>
                <?php
                $statuses = [
                    'draft' => ['Nháp', 'secondary'],
                    'scheduled' => ['Sắp diễn ra', 'info'],
                    'active' => ['Đang diễn ra', 'success'],
                    'ended' => ['Đã kết thúc', 'dark'],
                    'cancelled' => ['Đã hủy / ẩn', 'danger'],
                ];
                [$statusLabel, $statusColor] = $statuses[$campaign['campaign_status']] ?? ['Không xác định', 'secondary'];
                ?>
                <tr>
                    <td>
                        <a class="fw-semibold text-decoration-none" href="<?= e(url('/admin/flash-sales/' . $campaign['id'])) ?>"><?= e($campaign['name']) ?></a>
                        <div class="small text-muted"><?= (int) $campaign['item_count'] ?> sản phẩm</div>
                    </td>
                    <td class="text-nowrap">
                        <?= e(date('d/m/Y H:i', strtotime($campaign['starts_at']))) ?><br>
                        <span class="text-muted">đến <?= e(date('d/m/Y H:i', strtotime($campaign['ends_at']))) ?></span>
                    </td>
                    <td><span class="badge text-bg-<?= e($statusColor) ?>"><?= e($statusLabel) ?></span></td>
                    <td class="text-end"><?= number_format((int) $campaign['quota']) ?></td>
                    <td class="text-end"><?= number_format((int) $campaign['sold']) ?></td>
                    <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(url('/admin/flash-sales/' . $campaign['id'])) ?>">Theo dõi / sửa</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include FRONTEND_PATH . '/views/admin/_pagination.php'; ?>
