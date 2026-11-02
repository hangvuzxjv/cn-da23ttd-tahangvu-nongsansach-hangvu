<header class="page-heading"><nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/')) ?>">Trang chủ</a></li><li class="breadcrumb-item"><a href="<?= e(url('/profile')) ?>">Tài khoản</a></li><li class="breadcrumb-item active" aria-current="page">Đơn hàng của tôi</li></ol></nav><h1 class="h2">Đơn hàng của tôi</h1><p class="text-secondary">Theo dõi trạng thái, thanh toán và chi tiết các đơn đã đặt.</p></header>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-warning"><?= e($error) ?></div><?php endif; ?>
<form class="row g-2 mb-3 order-filter" method="get">
    <div class="col-auto"><label class="visually-hidden" for="orderStatus">Lọc đơn theo trạng thái</label><select class="form-select" id="orderStatus" name="status"><option value="">Tất cả trạng thái</option><?php foreach (['pending'=>'Chờ xác nhận','confirmed'=>'Đã xác nhận','processing'=>'Đang đóng gói','shipping'=>'Đang giao','completed'=>'Đã giao','cancelled'=>'Đã hủy','refunded'=>'Hoàn trả'] as $key=>$label): ?><option value="<?= e($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
    <div class="col-auto"><button class="btn btn-outline-success">Lọc</button></div>
</form>
<div class="table-responsive order-table"><table class="table align-middle order-history-table">
    <thead><tr><th>Mã đơn</th><th>Ngày đặt</th><th>Số mặt hàng</th><th>Trạng thái</th><th>Tổng thanh toán</th><th></th></tr></thead><tbody>
    <?php foreach ($orders as $order): ?><tr>
        <td><strong><?= e($order['order_no']) ?></strong></td><td><?= e($order['placed_at']) ?></td><td><?= (int) $order['item_count'] ?></td>
        <?php $orderStatusLabel = $order['status'] === 'pending' && $order['payment_status'] === 'pending'
            ? (($order['payment_method'] ?? '') === 'bank_transfer' ? 'Chờ xác nhận thanh toán' : 'Chờ thanh toán')
            : (['pending'=>'Chờ xác nhận','confirmed'=>'Đã xác nhận','processing'=>'Đang đóng gói','shipping'=>'Đang giao','completed'=>'Đã giao','cancelled'=>'Đã hủy','refunded'=>'Hoàn trả'][$order['status']] ?? $order['status']); ?>
        <?php $orderStatusTone = in_array($order['status'], ['completed'], true) ? 'success' : (in_array($order['status'], ['cancelled', 'refunded'], true) ? 'danger' : (in_array($order['status'], ['pending', 'confirmed', 'processing'], true) ? 'warning' : 'neutral')); ?>
        <td><span class="status-badge status-badge--<?= $orderStatusTone ?>"><?= e($orderStatusLabel) ?></span></td>
        <td>
            <strong><?= number_format((float) $order['total_amount'], 0, ',', '.') ?> ₫</strong>
            <div class="small text-secondary">
                Tạm tính <?= number_format((float) $order['subtotal'], 0, ',', '.') ?> ₫
                · Giảm <?= number_format((float) $order['discount_amount'], 0, ',', '.') ?> ₫
                · Ship <?= number_format((float) $order['shipping_fee'], 0, ',', '.') ?> ₫
            </div>
            <?php if (($order['payment_method'] ?? '') === 'vnpay' && $order['payment_status'] === 'pending'): ?><div class="small text-warning-emphasis">VNPay - chờ thanh toán</div><?php endif; ?>
        </td><td><a class="btn btn-sm btn-outline-success" href="<?= e(url('/tai-khoan/don-hang/' . $order['id'])) ?>">Chi tiết</a></td>
    </tr><?php endforeach; ?>
    <?php if (!$orders): ?><tr><td colspan="6"><div class="empty-state">Chưa có đơn hàng phù hợp.</div></td></tr><?php endif; ?>
    </tbody>
</table></div>
<?php if ($pages > 1): ?><nav><ul class="pagination"><?php for ($index=1;$index<=$pages;$index++): ?><li class="page-item <?= $index===$page?'active':'' ?>"><a class="page-link" href="?status=<?= e($status) ?>&page=<?= $index ?>"><?= $index ?></a></li><?php endfor; ?></ul></nav><?php endif; ?>
