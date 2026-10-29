<?php $statusLabels = ['pending'=>'Chờ xác nhận','confirmed'=>'Đã xác nhận','processing'=>'Đang đóng gói','shipping'=>'Đang giao','completed'=>'Đã giao','cancelled'=>'Đã hủy','refunded'=>'Hoàn trả']; ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div><h1 class="h2 mb-1">Đơn <?= e($order['order_no']) ?></h1><span class="badge text-bg-success"><?= e($statusLabels[$order['status']] ?? $order['status']) ?></span></div>
    <a class="btn btn-outline-secondary" href="<?= e(url('/tai-khoan/don-hang')) ?>">Danh sách đơn</a>
</div>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card mb-4"><div class="card-header"><h2 class="h5 mb-0">Sản phẩm</h2></div><div class="card-body">
            <?php foreach ($items as $item): ?><div class="d-flex justify-content-between gap-3 border-bottom py-2">
                <div><strong><?= e($item['product_name']) ?></strong> × <?= (int) $item['quantity'] ?> <?= e($item['unit']) ?>
                    <?php if ($item['batch_code']): ?><div class="small"><a href="<?= e(url('/truy-xuat/' . $item['batch_code'])) ?>">Truy xuất lô <?= e($item['batch_code']) ?></a></div><?php endif; ?>
                </div><span><?= number_format((float) $item['line_total'], 0, ',', '.') ?> ₫</span>
            </div><?php endforeach; ?>
        </div></div>
        <div class="card"><div class="card-header"><h2 class="h5 mb-0">Lịch sử trạng thái</h2></div><div class="card-body">
            <ol class="mb-0"><?php foreach ($history as $event): ?><li class="mb-2"><strong><?= e($statusLabels[$event['status']] ?? $event['status']) ?></strong> · <?= e($event['created_at']) ?><?php if ($event['note']): ?><div class="small text-secondary"><?= e($event['note']) ?></div><?php endif; ?></li><?php endforeach; ?></ol>
        </div></div>
    </div>
    <div class="col-lg-4">
        <div class="card mb-3"><div class="card-header"><h2 class="h5 mb-0">Giao hàng & thanh toán</h2></div><div class="card-body">
            <p><strong><?= e($order['recipient_name']) ?></strong><br><?= e($order['recipient_phone']) ?><br><?= e($order['shipping_address']) ?></p>
            <div class="d-flex justify-content-between"><span>Tạm tính</span><span><?= number_format((float) $order['subtotal'], 0, ',', '.') ?> ₫</span></div>
            <div class="d-flex justify-content-between"><span>Giảm giá</span><span>-<?= number_format((float) $order['discount_amount'], 0, ',', '.') ?> ₫</span></div>
            <div class="d-flex justify-content-between"><span>Vận chuyển</span><span><?= number_format((float) $order['shipping_fee'], 0, ',', '.') ?> ₫</span></div>
            <hr><div class="d-flex justify-content-between"><strong>Tổng COD</strong><strong><?= number_format((float) $order['total_amount'], 0, ',', '.') ?> ₫</strong></div>
            <?php if ($order['customer_note']): ?><p class="small text-secondary mt-3">Ghi chú: <?= e($order['customer_note']) ?></p><?php endif; ?>
        </div></div>
        <div class="d-flex gap-2">
            <?php if ($order['status'] === 'pending'): ?><form method="post" action="<?= e(url('/tai-khoan/don-hang/' . $order['id'] . '/huy')) ?>" onsubmit="return confirm('Hủy đơn hàng này?')"><?= csrf_field() ?><input class="form-control form-control-sm mb-2" name="reason" value="Khách hàng hủy đơn" maxlength="500" required><button class="btn btn-outline-danger">Hủy đơn</button></form><?php endif; ?>
            <form method="post" action="<?= e(url('/tai-khoan/don-hang/' . $order['id'] . '/mua-lai')) ?>"><?= csrf_field() ?><button class="btn btn-outline-success">Mua lại</button></form>
        </div>
    </div>
</div>
