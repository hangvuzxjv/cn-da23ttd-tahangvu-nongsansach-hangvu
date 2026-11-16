<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h2 mb-0">Lịch sử giao thất bại</h1>
    <span class="text-secondary">Tổng <?= (int) $total ?> lượt</span>
</div>
<form class="row g-2 mb-3" method="get" action="<?= e(url('/admin/delivery-failures')) ?>">
    <div class="col-md-4">
        <label class="form-label" for="failureReason">Lọc theo lý do</label>
        <select class="form-select" id="failureReason" name="reason">
            <option value="">Tất cả lý do</option>
            <?php foreach ($reasons as $key => $label): ?>
                <option value="<?= e($key) ?>" <?= $reason === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto align-self-end"><button class="btn btn-success">Lọc</button></div>
</form>
<div class="table-responsive bg-white border rounded">
    <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Ngày</th><th>Đơn</th><th>Khách hàng</th><th>SĐT người nhận</th><th>Lý do</th><th>Tính vào bom hàng</th></tr></thead>
        <tbody>
        <?php foreach ($failures as $failure): ?>
            <tr>
                <td><?= e($failure['created_at']) ?></td>
                <td><a href="<?= e(url('/admin/orders/' . $failure['order_id'])) ?>"><?= e($failure['order_no']) ?></a></td>
                <td><?= e($failure['customer_name']) ?><div class="small text-secondary"><?= e($failure['customer_email']) ?></div></td>
                <td><?= e($failure['phone_norm']) ?></td>
                <td><?= e($reasons[$failure['reason']] ?? $failure['reason']) ?><?php if (!empty($failure['note'])): ?><div class="small text-secondary"><?= e($failure['note']) ?></div><?php endif; ?></td>
                <td><?= (int) $failure['counts_against_customer'] === 1 ? 'Có' : 'Không' ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$failures): ?><tr><td colspan="6" class="text-center text-secondary py-4">Chưa có lượt giao thất bại phù hợp.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php include FRONTEND_PATH . '/views/admin/_pagination.php'; ?>
