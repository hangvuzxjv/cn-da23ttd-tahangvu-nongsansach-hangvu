<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h2 mb-0">Đơn hàng</h1><a class="btn btn-outline-success" href="<?= e(url('/admin/orders.csv')) ?>">Xuất CSV</a></div>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<form class="row g-2 mb-3" method="get">
    <div class="col-md-3"><input class="form-control" name="q" value="<?= e($filters['q']) ?>" placeholder="Mã đơn, người nhận, điện thoại"></div>
    <div class="col-md-2"><select class="form-select" name="status"><option value="">Mọi trạng thái</option><?php foreach (['pending'=>'Chờ xác nhận','confirmed'=>'Đã xác nhận','processing'=>'Đang đóng gói','shipping'=>'Đang giao','completed'=>'Đã giao','cancelled'=>'Đã hủy','refunded'=>'Hoàn trả','delivery_failed'=>'Giao thất bại'] as $key=>$label): ?><option value="<?= e($key) ?>" <?= $filters['status']===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><select class="form-select" name="payment_status"><option value="">Mọi thanh toán</option><?php foreach (PaymentLabels::statuses() as $value => $label): ?><option <?= $filters['payment_status']===$value?'selected':'' ?> value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><input class="form-control" type="date" name="from" value="<?= e($filters['from']) ?>"></div>
    <div class="col-md-2"><input class="form-control" type="date" name="to" value="<?= e($filters['to']) ?>"></div>
    <div class="col-md-1"><button class="btn btn-success w-100">Lọc</button></div>
</form>
<?php if ($canQuickAdvance): ?><form method="post" action="<?= e(url('/admin/orders/bulk-next')) ?>">
    <?= csrf_field() ?>
<?php endif; ?>
    <div class="table-responsive"><table class="table table-hover align-middle admin-order-table">
    <thead><tr><?php if ($canQuickAdvance): ?><th><span class="visually-hidden">Chọn đơn</span></th><?php endif; ?><th>Mã đơn</th><th>Khách hàng</th><th>Người nhận</th><th>Ngày</th><th>Trạng thái</th><th>Thanh toán</th><th class="text-end">Tổng</th><th class="admin-table-actions-heading">Thao tác</th></tr></thead><tbody>
    <?php foreach ($orders as $order): ?><tr>
        <?php if ($canQuickAdvance): ?><td><input class="form-check-input" type="checkbox" name="order_ids[]" value="<?= (int) $order['id'] ?>" aria-label="Chọn đơn <?= e($order['order_no']) ?>"></td><?php endif; ?>
        <td><strong><?= e($order['order_no']) ?></strong></td><td><?= e($order['customer_name']) ?><div class="small text-secondary"><?= e($order['customer_email']) ?></div></td>
        <td><?= e($order['recipient_name']) ?><div class="small text-secondary"><?= e($order['recipient_phone']) ?></div></td><td><?= e(PaymentLabels::dateTime($order['placed_at'])) ?></td>
        <?php $orderStatusLabel = $order['status'] === 'pending' && $order['payment_status'] === 'pending'
            ? (($order['payment_method'] ?? '') === 'bank_transfer' ? 'Chờ xác nhận thanh toán' : 'Chờ thanh toán')
            : (['pending'=>'Chờ xác nhận','confirmed'=>'Đã xác nhận','processing'=>'Đang đóng gói','shipping'=>'Đang giao','completed'=>'Đã giao','cancelled'=>'Đã hủy','refunded'=>'Hoàn trả','delivery_failed'=>'Giao thất bại'][$order['status']] ?? $order['status']); ?>
        <td><?= e($orderStatusLabel) ?></td>
        <td><span class="status-badge <?= e(PaymentLabels::statusBadgeClass((string) $order['payment_status'])) ?>"><?= e(PaymentLabels::status((string) $order['payment_status'])) ?></span></td>
        <td class="text-end"><?= number_format((float) $order['total_amount'], 0, ',', '.') ?> ₫</td>
        <td class="admin-table-actions-cell"><a class="btn btn-outline-success admin-table-action-btn" href="<?= e(url('/admin/orders/' . $order['id'])) ?>">Xem</a></td>
    </tr><?php endforeach; ?>
    <?php if (!$orders): ?><tr><td colspan="<?= $canQuickAdvance ? 9 : 8 ?>" class="text-center text-secondary py-4">Không có đơn hàng phù hợp.</td></tr><?php endif; ?>
    </tbody>
</table></div>
<?php if ($canQuickAdvance): ?><button class="btn btn-success" type="submit">Chuyển các đơn đã chọn sang bước kế tiếp</button></form><?php endif; ?>
<p class="small text-secondary">Tổng <?= (int) $total ?> đơn.</p>
<?php if ($pages > 1): ?><nav><ul class="pagination"><?php for ($index=1;$index<=$pages;$index++): ?><li class="page-item <?= $index===$page?'active':'' ?>"><a class="page-link" href="?<?= e(http_build_query($filters + ['page'=>$index])) ?>"><?= $index ?></a></li><?php endfor; ?></ul></nav><?php endif; ?>
