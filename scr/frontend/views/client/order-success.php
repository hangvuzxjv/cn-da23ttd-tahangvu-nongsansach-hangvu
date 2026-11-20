<nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/')) ?>">Trang chủ</a></li><li class="breadcrumb-item active" aria-current="page">Đặt hàng thành công</li></ol></nav>
<div class="order-confirmation-card mb-4">
    <div class="card card-body text-center">
    <h1 class="h2">Đặt hàng thành công</h1>
    <?php $paymentMethodLabels = ['vnpay' => 'VNPay', 'bank_transfer' => 'VietQR', 'cod' => 'COD', 'online' => 'Thanh toán trực tuyến']; ?>
    <p class="text-secondary mb-0">Mã đơn hàng <strong><?= e($order['order_no']) ?></strong>. Phương thức: <?= e($paymentMethodLabels[$payment['method'] ?? 'cod'] ?? ($payment['method'] ?? 'COD')) ?>.</p>
    </div>
</div>
<?php if ($emailWarning): ?><div class="alert alert-warning order-confirmation-card"><?= e($emailWarning) ?></div><?php endif; ?>
<div class="card order-confirmation-card">
    <div class="card-body">
        <?php foreach ($items as $item): ?>
            <?php $estimatedWeight = ProductUnitFormatter::approximateWeightLabel((string) $item['unit'], (int) $item['quantity']); ?>
            <div class="d-flex justify-content-between gap-3 border-bottom py-2">
                <div class="d-flex align-items-start gap-2">
                    <img src="<?= e(asset($item['image_url'] ?: '/public/images/no-image.svg')) ?>"<?= product_image_fallback_attributes() ?> alt="<?= e($item['product_name']) ?>" width="56" height="56" class="rounded object-fit-cover flex-shrink-0">
                    <span><?php if (!empty($item['flash_sale_item_id'])): ?><span class="badge text-bg-danger">Flash Sale</span> <?php endif; ?><?= e($item['product_name']) ?> · <?= e($item['unit']) ?>
                        <span class="d-block small text-secondary"><?= (int) $item['quantity'] ?> × <?= number_format((float) $item['unit_price'], 0, ',', '.') ?> ₫</span>
                        <?php if ($estimatedWeight !== null): ?><span class="d-block small text-secondary"><?= e($estimatedWeight) ?></span><?php endif; ?>
                    </span>
                </div>
                <strong><?= number_format((float) $item['line_total'], 0, ',', '.') ?> ₫</strong>
            </div>
        <?php endforeach; ?>
        <div class="d-flex justify-content-between pt-3"><span>Tạm tính</span><span><?= number_format((float) $order['subtotal'], 0, ',', '.') ?> ₫</span></div>
        <div class="d-flex justify-content-between"><span>Giảm giá</span><span>-<?= number_format((float) $order['discount_amount'], 0, ',', '.') ?> ₫</span></div>
        <div class="d-flex justify-content-between"><span>Phí vận chuyển</span><span><?= number_format((float) $order['shipping_fee'], 0, ',', '.') ?> ₫</span></div>
        <hr><div class="d-flex justify-content-between fs-5"><strong>Tổng thanh toán</strong><strong><?= number_format((float) $order['total_amount'], 0, ',', '.') ?> ₫</strong></div>
        <div class="d-flex flex-wrap gap-2 mt-4"><a class="btn btn-success" href="<?= e(url('/tai-khoan/don-hang/' . $order['id'])) ?>">Theo dõi đơn hàng</a><a class="btn btn-outline-success" href="<?= e(url('/san-pham')) ?>">Tiếp tục mua sắm</a></div>
    </div>
</div>
