<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div><h1 class="h2 mb-1">Giao dịch #<?= (int) $payment['id'] ?></h1><p class="mb-0">Đơn <?= e($payment['order_no']) ?></p></div>
    <a class="btn btn-outline-secondary" href="<?= e(url('/admin/payments')) ?>">Danh sách thanh toán</a>
</div>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<div class="card mb-3"><div class="card-body">
    <div class="row g-3">
        <div class="col-md-4"><strong>Khách hàng:</strong> <?= e($payment['customer_name']) ?> (<?= e($payment['customer_email']) ?>)</div>
        <div class="col-md-4"><strong>Phương thức:</strong> <?= e($payment['method']) ?></div>
        <div class="col-md-4"><strong>Trạng thái giao dịch:</strong> <?= e($payment['status']) ?></div>
        <div class="col-md-4"><strong>Số tiền giao dịch:</strong> <?= number_format((float) $payment['amount'], 0, ',', '.') ?> ₫</div>
        <div class="col-md-4"><strong>Tổng đơn:</strong> <?= number_format((float) $payment['total_amount'], 0, ',', '.') ?> ₫</div>
        <div class="col-md-4"><strong>Mã tham chiếu:</strong> <?= e($payment['transaction_ref'] ?? '—') ?></div>
        <div class="col-md-4"><strong>Tạo lúc:</strong> <?= e($payment['created_at']) ?></div>
        <div class="col-md-4"><strong>Thanh toán lúc:</strong> <?= e($payment['paid_at'] ?? '—') ?></div>
    </div>
    <?php if ($payment['proof_path']): ?><hr><a href="<?= e(url('/admin/payments/' . $payment['id'] . '/proof')) ?>" target="_blank" rel="noopener">Xem ảnh biên lai</a><?php endif; ?>
    <?php if ($payment['raw_response']): ?><hr><h2 class="h6">Thông tin giao dịch đã lọc</h2><pre class="small mb-0"><?= e(json_encode($payment['raw_response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre><?php endif; ?>
</div></div>
<?php if ($canManage && $payment['method'] === 'bank_transfer' && $payment['status'] === 'pending'): ?>
    <div class="row g-3">
        <div class="col-lg-6"><form class="card" method="post" action="<?= e(url('/admin/payments/' . $payment['id'] . '/confirm')) ?>">
            <?= csrf_field() ?><div class="card-body">
                <h2 class="h5">Xác nhận đã nhận tiền</h2>
                <label class="form-label" for="receivedAmount">Số tiền thực nhận (VND)</label>
                <input class="form-control mb-3" id="receivedAmount" name="received_amount" type="number" min="1" value="<?= (int) round((float) $payment['amount']) ?>" required>
                <button class="btn btn-success">Xác nhận và trừ kho</button>
            </div>
        </form></div>
        <div class="col-lg-6"><form class="card" method="post" action="<?= e(url('/admin/payments/' . $payment['id'] . '/reject')) ?>">
            <?= csrf_field() ?><div class="card-body">
                <h2 class="h5">Từ chối thanh toán</h2>
                <label class="form-label" for="rejectReason">Lý do</label>
                <textarea class="form-control mb-3" id="rejectReason" name="reason" maxlength="500" required></textarea>
                <button class="btn btn-outline-danger">Từ chối, hủy đơn và giải phóng</button>
            </div>
        </form></div>
    </div>
<?php endif; ?>
<?php if ($canManage && $payment['method'] === 'vnpay' && $payment['status'] === 'pending' && $payment['payment_status'] === 'pending'): ?>
    <form class="card mt-3" method="post" action="<?= e(url('/admin/payments/' . $payment['id'] . '/reconcile-vnpay')) ?>" onsubmit="return confirm('Chỉ xác nhận nếu đã kiểm tra giao dịch thành công trên VNPay sandbox/cổng thanh toán.')">
        <?= csrf_field() ?><div class="card-body">
            <h2 class="h5">Đối soát thủ công VNPay</h2>
            <p class="text-muted">Chỉ dùng khi VNPay đã báo thanh toán thành công nhưng hệ thống chưa ghi nhận. Thao tác sẽ xác nhận đơn và trừ kho.</p>
            <label class="form-label" for="vnpTransactionNo">Mã giao dịch VNPay (vnp_TransactionNo)</label>
            <input class="form-control mb-3" id="vnpTransactionNo" name="transaction_no" maxlength="100" pattern="[A-Za-z0-9._-]+" required>
            <label class="form-label" for="vnpReconcileReason">Lý do đối soát</label>
            <textarea class="form-control mb-3" id="vnpReconcileReason" name="reason" maxlength="500" required></textarea>
            <button class="btn btn-warning">Đối soát, xác nhận thanh toán và trừ kho</button>
        </div>
    </form>
<?php endif; ?>
<?php if ($canManage && $payment['status'] === 'paid' && $payment['order_status'] !== 'refunded'): ?>
    <form class="card mt-3" method="post" action="<?= e(url('/admin/payments/' . $payment['id'] . '/refund')) ?>" onsubmit="return confirm('Ghi nhận hoàn tiền thủ công và hoàn lại kho?')">
        <?= csrf_field() ?><div class="card-body">
            <h2 class="h5">Hoàn tiền thủ công</h2>
            <label class="form-label" for="refundReason">Lý do</label>
            <textarea class="form-control mb-3" id="refundReason" name="reason" maxlength="500" required></textarea>
            <button class="btn btn-outline-danger">Đánh dấu đã hoàn tiền</button>
        </div>
    </form>
<?php endif; ?>
