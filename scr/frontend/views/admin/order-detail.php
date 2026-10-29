<?php $statusLabels = ['pending'=>'Chờ xác nhận','confirmed'=>'Đã xác nhận','processing'=>'Đang đóng gói','shipping'=>'Đang giao','completed'=>'Đã giao','cancelled'=>'Đã hủy','refunded'=>'Hoàn trả']; ?>
<div class="d-flex justify-content-between align-items-center mb-3"><div><h1 class="h2 mb-1">Đơn <?= e($order['order_no']) ?></h1><span class="badge text-bg-success"><?= e($statusLabels[$order['status']] ?? $order['status']) ?></span></div><div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="<?= e(url('/admin/orders')) ?>">Danh sách</a><a class="btn btn-outline-primary" href="<?= e(url('/admin/orders/' . $order['id'] . '?print=1')) ?>" onclick="window.print();return false;">In phiếu / hóa đơn</a></div></div>
<?php if ($success): ?><div class="alert alert-success no-print"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger no-print"><?= e($error) ?></div><?php endif; ?>
<div class="row g-4">
    <section class="col-lg-8">
        <div class="card mb-4"><div class="card-header"><h2 class="h5 mb-0">Sản phẩm đã phân bổ theo lô</h2></div><div class="table-responsive"><table class="table mb-0 align-middle">
            <thead><tr><th>Sản phẩm</th><th>Lô</th><th>Đơn giá</th><th>Số lượng</th><th>Tổng</th></tr></thead><tbody>
            <?php foreach ($items as $item): ?><tr><td><?= e($item['product_name']) ?><div class="small text-secondary"><?= e($item['sku']) ?></div></td><td><?= e($item['batch_code'] ?? '—') ?></td><td><?= number_format((float) $item['unit_price'],0,',','.') ?> ₫ / <?= e($item['unit']) ?></td><td><?= (int) $item['quantity'] ?></td><td><?= number_format((float) $item['line_total'],0,',','.') ?> ₫</td></tr><?php endforeach; ?>
            </tbody></table></div></div>
        <div class="card"><div class="card-header"><h2 class="h5 mb-0">Lịch sử trạng thái</h2></div><div class="card-body"><ol class="mb-0"><?php foreach ($history as $event): ?><li class="mb-2"><strong><?= e($statusLabels[$event['status']] ?? $event['status']) ?></strong> · <?= e($event['created_at']) ?> · <?= e($event['changed_by_name'] ?? 'Hệ thống') ?><?php if ($event['note']): ?><div class="small text-secondary"><?= e($event['note']) ?></div><?php endif; ?></li><?php endforeach; ?></ol></div></div>
    </section>
    <aside class="col-lg-4">
        <div class="card mb-3"><div class="card-header"><h2 class="h5 mb-0">Khách hàng & giao hàng</h2></div><div class="card-body"><strong><?= e($order['customer_name']) ?></strong><div><?= e($order['customer_email']) ?></div><hr><strong><?= e($order['recipient_name']) ?></strong><div><?= e($order['recipient_phone']) ?></div><p class="mb-0"><?= e($order['shipping_address']) ?></p><?php if ($order['customer_note']): ?><hr><p class="small mb-0">Ghi chú: <?= e($order['customer_note']) ?></p><?php endif; ?></div></div>
        <div class="card mb-3"><div class="card-body">
            <div class="d-flex justify-content-between"><span>Tạm tính</span><span><?= number_format((float) $order['subtotal'],0,',','.') ?> ₫</span></div>
            <div class="d-flex justify-content-between"><span>Voucher <?= e($order['voucher_code'] ?? '') ?></span><span>-<?= number_format((float) $order['discount_amount'],0,',','.') ?> ₫</span></div>
            <div class="d-flex justify-content-between"><span>Phí ship</span><span><?= number_format((float) $order['shipping_fee'],0,',','.') ?> ₫</span></div><hr>
            <div class="d-flex justify-content-between fs-5"><strong>Tổng</strong><strong><?= number_format((float) $order['total_amount'],0,',','.') ?> ₫</strong></div>
            <?php foreach ($payments as $payment): ?><div class="small text-secondary mt-2">COD · <?= e($payment['status']) ?> · <?= number_format((float) $payment['amount'],0,',','.') ?> ₫</div><?php endforeach; ?>
        </div></div>
        <?php $transitions = ['pending'=>['confirmed','cancelled'],'confirmed'=>['processing','cancelled'],'processing'=>['shipping','cancelled'],'shipping'=>['completed','refunded'],'completed'=>['refunded']][$order['status']] ?? []; ?>
        <?php $permittedTransitions = array_values(array_filter($transitions, static fn (string $status): bool => $canManage || in_array($status, ['processing','shipping','completed'], true))); ?>
        <?php if ($permittedTransitions): ?><form class="card no-print" method="post" action="<?= e(url('/admin/orders/' . $order['id'] . '/status')) ?>">
            <?= csrf_field() ?><div class="card-header"><h2 class="h5 mb-0">Cập nhật trạng thái</h2></div><div class="card-body">
            <label class="form-label" for="orderStatus">Chuyển sang</label><select class="form-select mb-2" id="orderStatus" name="status"><?php foreach ($permittedTransitions as $status): ?><option value="<?= e($status) ?>"><?= e($statusLabels[$status]) ?></option><?php endforeach; ?></select>
            <label class="form-label" for="orderNote">Ghi chú / lý do</label><textarea class="form-control mb-3" id="orderNote" name="note" rows="2" required></textarea>
            <button class="btn btn-success w-100" type="submit">Lưu trạng thái</button></div></form><?php endif; ?>
    </aside>
</div>
<style>@media print { body { background: white !important; } .no-print, nav, aside .card:last-child { display: none !important; } main { width: 100% !important; } }</style>
