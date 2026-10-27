<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h2 mb-0">Tồn kho theo lô</h1>
    <a class="btn btn-outline-success" href="<?= e(url('/admin/inventory/stock.csv?' . http_build_query(['q' => $query, 'filter' => $filter]))) ?>">Xuất CSV</a>
</div>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<form class="row g-2 mb-3" method="get" action="<?= e(url('/admin/inventory/stock')) ?>">
    <div class="col-md-5"><input class="form-control" name="q" value="<?= e($query) ?>" placeholder="Tìm sản phẩm, SKU hoặc mã lô"></div>
    <div class="col-md-4"><select class="form-select" name="filter">
        <option value="">Tất cả lô</option>
        <option value="low" <?= $filter === 'low' ? 'selected' : '' ?>>Sản phẩm dưới ngưỡng</option>
        <option value="near_expiry" <?= $filter === 'near_expiry' ? 'selected' : '' ?>>Sắp hết hạn (<?= (int) $warningDays ?> ngày)</option>
        <option value="expired" <?= $filter === 'expired' ? 'selected' : '' ?>>Đã hết hạn</option>
        <option value="out_of_stock" <?= $filter === 'out_of_stock' ? 'selected' : '' ?>>Hết hàng</option>
    </select></div>
    <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary">Lọc</button><a class="btn btn-outline-secondary" href="<?= e(url('/admin/inventory/stock')) ?>">Xóa lọc</a></div>
</form>
<div class="table-responsive"><table class="table table-striped align-middle">
    <thead><tr><th>Sản phẩm / SKU</th><th>Mã lô</th><th>Hạn dùng</th><th>Trạng thái</th><th>Tồn</th><th>Giữ chỗ</th><th>Khả dụng</th><th>Giá nhập TB</th><?php if ($canManage): ?><th>Thao tác</th><?php endif; ?></tr></thead><tbody>
    <?php foreach ($rows as $row): ?>
        <tr><td><?= e($row['product_name']) ?><br><small class="text-secondary"><?= e($row['sku']) ?> · tổng <?= (int) $row['total_stock'] ?> / ngưỡng <?= (int) $row['min_stock'] ?></small></td>
            <td><a href="<?= e(url('/admin/batches/' . $row['batch_id'])) ?>"><code><?= e($row['batch_code']) ?></code></a></td>
            <td><?= e($row['expires_at'] ?? '—') ?></td><td><?= e($row['status']) ?></td>
            <td><?= (int) $row['quantity_remaining'] ?></td><td><?= (int) $row['reserved_quantity'] ?></td><td><strong><?= (int) $row['available_quantity'] ?></strong></td>
            <td><?= number_format((float) $row['average_unit_cost'], 0, ',', '.') ?> ₫</td>
            <?php if ($canManage): ?><td>
                <form method="post" action="<?= e(url('/admin/inventory/movement')) ?>" class="d-flex flex-column gap-1">
                    <?= csrf_field() ?><input type="hidden" name="batch_id" value="<?= (int) $row['batch_id'] ?>">
                    <select class="form-select form-select-sm" name="action"><option value="issue">Xuất kho</option><option value="adjust">Điều chỉnh</option></select>
                    <select class="form-select form-select-sm" name="direction"><option value="decrease">Giảm</option><option value="increase">Tăng</option></select>
                    <input class="form-control form-control-sm" type="number" name="quantity" min="1" placeholder="Số lượng" required>
                    <select class="form-select form-select-sm" name="reason_code" required><option value="">Lý do</option><option value="damage">Hư hỏng</option><option value="expired">Hết hạn</option><option value="loss">Mất mát</option><option value="sample">Hàng mẫu</option><option value="other">Khác</option></select>
                    <input class="form-control form-control-sm" name="notes" placeholder="Ghi chú / lý do khác">
                    <button class="btn btn-sm btn-outline-primary">Ghi nhận</button>
                </form>
            </td><?php endif; ?>
        </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="<?= $canManage ? 9 : 8 ?>" class="text-secondary">Không tìm thấy lô phù hợp.</td></tr><?php endif; ?>
    </tbody>
</table></div>
<p class="text-secondary small">Hiển thị tối đa 500 lô mỗi trang kết quả; tệp CSV xuất toàn bộ theo bộ lọc.</p>
