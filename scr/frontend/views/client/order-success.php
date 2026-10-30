<div class="text-center mb-4">
    <div class="display-5 text-success">✓</div>
    <h1 class="h2">Đặt hàng thành công</h1>
    <?php $paymentMethodLabels = ['vnpay' => 'VNPay', 'bank_transfer' => 'VietQR', 'cod' => 'COD', 'online' => 'Thanh toán trực tuyến']; ?>
    <p class="text-secondary">Mã đơn hàng <strong><?= e($order['order_no']) ?></strong>. Phương thức: <?= e($paymentMethodLabels[$payment['method'] ?? 'cod'] ?? ($payment['method'] ?? 'COD')) ?>.</p>
</div>
<?php if ($emailWarning): ?><div class="alert alert-warning mx-auto" style="max-width:760px"><?= e($emailWarning) ?></div><?php endif; ?>
<div class="card mx-auto" style="max-width: 760px">
    <div class="card-body">
        <?php foreach ($items as $item): ?><div class="d-flex justify-content-between border-bottom py-2"><span><?= e($item['product_name']) ?> × <?= (int) $item['quantity'] ?> <?= e($item['unit']) ?></span><strong><?= number_format((float) $item['line_total'], 0, ',', '.') ?> ₫</strong></div><?php endforeach; ?>
        <div class="d-flex justify-content-between pt-3"><span>Tạm tính</span><span><?= number_format((float) $order['subtotal'], 0, ',', '.') ?> ₫</span></div>
        <div class="d-flex justify-content-between"><span>Giảm giá</span><span>-<?= number_format((float) $order['discount_amount'], 0, ',', '.') ?> ₫</span></div>
        <div class="d-flex justify-content-between"><span>Phí vận chuyển</span><span><?= number_format((float) $order['shipping_fee'], 0, ',', '.') ?> ₫</span></div>
        <hr><div class="d-flex justify-content-between fs-5"><strong>Tổng thanh toán</strong><strong><?= number_format((float) $order['total_amount'], 0, ',', '.') ?> ₫</strong></div>
        <div class="d-flex flex-wrap gap-2 mt-4"><a class="btn btn-success" href="<?= e(url('/tai-khoan/don-hang/' . $order['id'])) ?>">Theo dõi đơn hàng</a><a class="btn btn-outline-success" href="<?= e(url('/san-pham')) ?>">Tiếp tục mua sắm</a></div>
    </div>
</div>
