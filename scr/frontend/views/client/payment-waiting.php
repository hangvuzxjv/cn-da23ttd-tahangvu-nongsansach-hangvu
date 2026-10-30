<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h2 mb-1">Chờ thanh toán</h1>
        <p class="text-secondary mb-0">Đơn hàng <?= e($order['order_no']) ?></p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= e(url('/tai-khoan/don-hang/' . $order['id'])) ?>">Chi tiết đơn</a>
</div>
<?php if ($success = flash('success')): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error = flash('error')): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header"><h2 class="h5 mb-0">Chi tiết thanh toán</h2></div>
            <div class="card-body">
                <div class="d-flex justify-content-between"><span>Tạm tính</span><span><?= number_format((float) $order['subtotal'], 0, ',', '.') ?> ₫</span></div>
                <div class="d-flex justify-content-between"><span>Giảm giá</span><span>-<?= number_format((float) $order['discount_amount'], 0, ',', '.') ?> ₫</span></div>
                <div class="d-flex justify-content-between"><span>Phí vận chuyển</span><span><?= number_format((float) $order['shipping_fee'], 0, ',', '.') ?> ₫</span></div>
                <hr>
                <div class="d-flex justify-content-between"><strong>Tổng thanh toán</strong><strong><?= number_format((float) $order['total_amount'], 0, ',', '.') ?> ₫</strong></div>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h2 class="h5 mb-0">Quét mã VietQR để chuyển khoản</h2></div>
            <div class="card-body text-center">
                <div class="mx-auto mb-3" style="max-width:320px"><?= $qrSvg ?></div>
                <p class="mb-1">Ngân hàng BIN: <strong><?= e($bankSettings['vietqr_bank_bin']) ?></strong></p>
                <p class="mb-1">Số tài khoản: <strong><?= e($bankSettings['vietqr_account_number']) ?></strong></p>
                <p class="mb-1">Chủ tài khoản: <strong><?= e($bankSettings['vietqr_account_name']) ?></strong></p>
                <p class="mb-1">Số tiền: <strong><?= number_format((float) $order['total_amount'], 0, ',', '.') ?> ₫</strong></p>
                <p class="mb-0">Nội dung bắt buộc: <strong><?= e($order['order_no']) ?></strong></p>
            </div>
        </div>
        <?php if ($payment['proof_path']): ?>
            <div class="alert alert-info mt-3">Đã nhận ảnh biên lai. Đơn hàng đang chờ quản trị viên đối chiếu.</div>
        <?php endif; ?>
        <form class="card mt-3" method="post" enctype="multipart/form-data" action="<?= e(url('/tai-khoan/don-hang/' . $order['id'] . '/thanh-toan/bien-lai')) ?>">
            <?= csrf_field() ?>
            <div class="card-body">
                <label class="form-label" for="paymentProof">Tôi đã chuyển khoản — tải ảnh biên lai (tùy chọn)</label>
                <input class="form-control mb-3" id="paymentProof" name="proof" type="file" accept="image/jpeg,image/png,image/webp">
                <button class="btn btn-success" type="submit">Gửi xác nhận chuyển khoản</button>
            </div>
        </form>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-body">
                <h2 class="h5">Thời gian giữ hàng</h2>
                <p class="mb-1">Đơn hàng được giữ đến:</p>
                <strong id="paymentCountdown" data-deadline="<?= e((string) $deadline) ?>">Đang tải đồng hồ…</strong>
                <hr>
                <p class="small text-secondary mb-0">Đơn sẽ tự hủy khi hết hạn nếu chưa thanh toán. Hệ thống giải phóng tồn kho và mã giảm giá khi có lượt truy cập.</p>
            </div>
        </div>
    </div>
</div>
<script>
(() => {
    const timer = document.getElementById('paymentCountdown');
    if (!timer) return;
    const deadline = new Date(timer.dataset.deadline.replace(' ', 'T')).getTime();
    const render = () => {
        const remaining = Math.max(0, Math.floor((deadline - Date.now()) / 1000));
        timer.textContent = `${Math.floor(remaining / 60)} phút ${remaining % 60} giây`;
        if (remaining === 0) window.location.reload();
    };
    render();
    window.setInterval(render, 1000);
})();
</script>
