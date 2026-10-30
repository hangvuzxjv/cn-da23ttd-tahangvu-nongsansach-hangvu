<h1 class="h2 mb-4">Đơn hàng của tôi</h1>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-warning"><?= e($error) ?></div><?php endif; ?>
<form class="row g-2 mb-3" method="get">
    <div class="col-auto"><select class="form-select" name="status"><option value="">Tất cả trạng thái</option><?php foreach (['pending'=>'Chờ xác nhận','confirmed'=>'Đã xác nhận','processing'=>'Đang đóng gói','shipping'=>'Đang giao','completed'=>'Đã giao','cancelled'=>'Đã hủy','refunded'=>'Hoàn trả'] as $key=>$label): ?><option value="<?= e($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
    <div class="col-auto"><button class="btn btn-outline-success">Lọc</button></div>
</form>
<div class="table-responsive"><table class="table align-middle">
    <thead><tr><th>Mã đơn</th><th>Ngày đặt</th><th>Số mặt hàng</th><th>Trạng thái</th><th>Tổng thanh toán</th><th></th></tr></thead><tbody>
    <?php foreach ($orders as $order): ?><tr>
        <td><strong><?= e($order['order_no']) ?></strong></td><td><?= e($order['placed_at']) ?></td><td><?= (int) $order['item_count'] ?></td>
        <?php $orderStatusLabel = $order['status'] === 'pending' && $order['payment_status'] === 'pending'
            ? (($order['payment_method'] ?? '') === 'bank_transfer' ? 'Chờ xác nhận thanh toán' : 'Chờ thanh toán')
            : (['pending'=>'Chờ xác nhận','confirmed'=>'Đã xác nhận','processing'=>'Đang đóng gói','shipping'=>'Đang giao','completed'=>'Đã giao','cancelled'=>'Đã hủy','refunded'=>'Hoàn trả'][$order['status']] ?? $order['status']); ?>
        <td><?= e($orderStatusLabel) ?></td>
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
    <?php if (!$orders): ?><tr><td colspan="6" class="text-secondary">Chưa có đơn hàng.</td></tr><?php endif; ?>
    </tbody>
</table></div>
<?php if ($pages > 1): ?><nav><ul class="pagination"><?php for ($index=1;$index<=$pages;$index++): ?><li class="page-item <?= $index===$page?'active':'' ?>"><a class="page-link" href="?status=<?= e($status) ?>&page=<?= $index ?>"><?= $index ?></a></li><?php endfor; ?></ul></nav><?php endif; ?>
