<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h2 mb-0">Thanh toán</h1>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-warning" href="<?= e(url('/admin/payments?method=bank_transfer&status=pending')) ?>">Thanh toán chờ xác nhận</a>
        <?php if ($canManage): ?><a class="btn btn-outline-success" href="<?= e(url('/admin/payments/settings')) ?>">Cấu hình phương thức</a><?php endif; ?>
    </div>
</div>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<div class="row g-3 mb-4">
    <?php foreach ($totals as $total): ?>
        <div class="col-md-4 col-xl-3"><div class="card h-100"><div class="card-body">
            <h2 class="h6"><?= e($total['method']) ?></h2>
            <div>Đã thu: <strong><?= number_format((float) $total['received'], 0, ',', '.') ?> ₫</strong></div>
            <div class="text-secondary">Đã hoàn: <?= number_format((float) $total['refunded'], 0, ',', '.') ?> ₫</div>
        </div></div></div>
    <?php endforeach; ?>
</div>
<form class="row g-2 align-items-end mb-3" method="get" action="<?= e(url('/admin/payments')) ?>">
    <div class="col-sm-3"><label class="form-label">Phương thức</label><select class="form-select" name="method"><option value="">Tất cả</option><?php foreach (['cod','bank_transfer','vnpay','online'] as $method): ?><option value="<?= e($method) ?>" <?= $filters['method'] === $method ? 'selected' : '' ?>><?= e($method) ?></option><?php endforeach; ?></select></div>
    <div class="col-sm-2"><label class="form-label">Trạng thái</label><select class="form-select" name="status"><option value="">Tất cả</option><?php foreach (['pending','paid','failed','refunded'] as $status): ?><option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></div>
    <div class="col-sm-2"><label class="form-label">Từ ngày</label><input class="form-control" type="date" name="from" value="<?= e($filters['from']) ?>"></div>
    <div class="col-sm-2"><label class="form-label">Đến ngày</label><input class="form-control" type="date" name="to" value="<?= e($filters['to']) ?>"></div>
    <div class="col-sm-2"><button class="btn btn-success">Lọc</button></div>
</form>
<div class="table-responsive bg-white border rounded">
    <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Ngày</th><th>Mã đơn</th><th>Khách hàng</th><th>Phương thức</th><th>Số tiền</th><th>Trạng thái</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($payments as $payment): ?>
            <tr>
                <td><?= e($payment['created_at']) ?></td>
                <td><?= e($payment['order_no']) ?></td>
                <td><?= e($payment['customer_name']) ?></td>
                <td><?= e($payment['method']) ?></td>
                <td><?= number_format((float) $payment['amount'], 0, ',', '.') ?> ₫</td>
                <td><?= e($payment['status']) ?></td>
                <td><a class="btn btn-sm btn-outline-success" href="<?= e(url('/admin/payments/' . $payment['id'])) ?>">Chi tiết</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$payments): ?><tr><td colspan="7" class="text-center text-secondary py-4">Không có giao dịch phù hợp.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
