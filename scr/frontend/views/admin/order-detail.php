<?php $statusLabels = ['pending'=>'Chờ xác nhận','confirmed'=>'Đã xác nhận','processing'=>'Đang đóng gói','shipping'=>'Đang giao','completed'=>'Đã giao','cancelled'=>'Đã hủy','refunded'=>'Hoàn trả','delivery_failed'=>'Giao thất bại']; ?>
<?php $latestPayment = $payments[0] ?? null; ?>
<?php $statusLabel = $order['status'] === 'pending' && $order['payment_status'] === 'pending'
    ? (($latestPayment['method'] ?? '') === 'bank_transfer' ? 'Chờ xác nhận thanh toán' : 'Chờ thanh toán')
    : ($statusLabels[$order['status']] ?? $order['status']); ?>
<div class="d-flex justify-content-between align-items-center mb-3"><div><h1 class="h2 mb-1">Đơn <?= e($order['order_no']) ?></h1><span class="badge text-bg-success"><?= e($statusLabel) ?></span></div><div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="<?= e(url('/admin/orders')) ?>">Danh sách</a><a class="btn btn-outline-primary" href="<?= e(url('/admin/orders/' . $order['id'] . '?print=1')) ?>" onclick="window.print();return false;">In phiếu / hóa đơn</a></div></div>
<?php if ($success): ?><div class="alert alert-success no-print"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger no-print"><?= e($error) ?></div><?php endif; ?>
<div class="row g-4">
    <section class="col-lg-8">
        <div class="card mb-4"><div class="card-header"><h2 class="h5 mb-0">Sản phẩm đã phân bổ theo lô</h2></div><div class="table-responsive"><table class="table mb-0 align-middle">
            <thead><tr><th>Sản phẩm</th><th>Lô</th><th>Đơn giá</th><th>Số lượng</th><th>Tổng</th></tr></thead><tbody>
            <?php foreach ($items as $item): ?><tr><td><?= e($item['product_name']) ?><div class="small text-secondary"><?= e($item['sku']) ?></div></td><td><?= e($item['batch_code'] ?? '—') ?></td><td><?= number_format((float) $item['unit_price'],0,',','.') ?> ₫ / <?= e($item['unit']) ?></td><td><?= (int) $item['quantity'] ?></td><td><?= number_format((float) $item['line_total'],0,',','.') ?> ₫</td></tr><?php endforeach; ?>
            </tbody></table></div></div>
        <div class="card"><div class="card-header"><h2 class="h5 mb-0">Lịch sử trạng thái</h2></div><div class="card-body"><ol class="mb-0"><?php foreach ($history as $event): ?><li class="mb-2"><strong><?= e($statusLabels[$event['status']] ?? $event['status']) ?></strong> · <?= e(PaymentLabels::dateTime($event['created_at'])) ?> · <?= e($event['changed_by_name'] ?? 'Hệ thống') ?><?php if ($event['note']): ?><div class="small text-secondary"><?= e($event['note']) ?></div><?php endif; ?></li><?php endforeach; ?></ol></div></div>
    </section>
    <aside class="col-lg-4">
        <div class="card mb-3"><div class="card-header"><h2 class="h5 mb-0">Khách hàng & giao hàng</h2></div><div class="card-body"><strong><?= e($order['customer_name']) ?></strong><div><?= e($order['customer_email']) ?></div><hr><strong><?= e($order['recipient_name']) ?></strong><div><?= e($order['recipient_phone']) ?></div><p class="mb-0"><?= e($order['shipping_address']) ?></p><?php if ($order['customer_note']): ?><hr><p class="small mb-0">Ghi chú: <?= e($order['customer_note']) ?></p><?php endif; ?></div></div>
        <div class="card mb-3"><div class="card-body">
            <div class="d-flex justify-content-between"><span>Tạm tính</span><span><?= number_format((float) $order['subtotal'],0,',','.') ?> ₫</span></div>
            <div class="d-flex justify-content-between"><span>Voucher <?= e($order['voucher_code'] ?? '') ?></span><span>-<?= number_format((float) $order['discount_amount'],0,',','.') ?> ₫</span></div>
            <div class="d-flex justify-content-between"><span>Phí ship</span><span><?= number_format((float) $order['shipping_fee'],0,',','.') ?> ₫</span></div><hr>
            <div class="d-flex justify-content-between fs-5"><strong>Tổng</strong><strong><?= number_format((float) $order['total_amount'],0,',','.') ?> ₫</strong></div>
            <?php foreach ($payments as $payment): ?><div class="small text-secondary mt-2"><?= e(PaymentLabels::method((string) $payment['method'])) ?> · <span class="status-badge <?= e(PaymentLabels::statusBadgeClass((string) $payment['status'])) ?>"><?= e(PaymentLabels::status((string) $payment['status'])) ?></span> · <?= number_format((float) $payment['amount'],0,',','.') ?> ₫<?php if (!empty($payment['refund_required'])): ?> · <span class="badge text-bg-warning">Cần hoàn tiền</span> <a href="<?= e(url('/admin/payments/' . $payment['id'])) ?>">Xử lý</a><?php elseif (!empty($payment['refund_reference'])): ?> · Hoàn tiền: <?= e($payment['refund_reference']) ?><?php endif; ?></div><?php endforeach; ?>
        </div></div>
        <?php $transitions = ['pending'=>['confirmed','cancelled'],'confirmed'=>['processing','cancelled'],'processing'=>['shipping','cancelled'],'shipping'=>['completed','refunded'],'completed'=>['refunded']][$order['status']] ?? []; ?>
        <?php $quickNextStatus = $quickNextStep['status']; ?>
        <?php if ($order['status'] === 'shipping'): ?><div class="alert alert-info no-print">
            Đang chờ khách xác nhận đã nhận hàng. Tự hoàn tất sau <?= (int) $autoCompletionDays ?> ngày kể từ lúc chuyển Đang giao.
            <div class="small mt-1">Thời điểm tự hoàn tất dự kiến: <strong><?= e($autoCompleteAt === null ? 'Chưa xác định' : PaymentLabels::dateTime($autoCompleteAt)) ?></strong></div>
        </div><?php endif; ?>
        <?php if ($order['status'] === 'delivery_failed' && !empty(array_filter($payments, static fn (array $payment): bool => !empty($payment['refund_required'])))): ?><div class="alert alert-warning no-print">Khoản thanh toán trực tuyến cần được hoàn tiền. <a href="<?= e(url('/admin/payments')) ?>">Mở trang thanh toán</a> để ghi nhận mã tham chiếu hoàn tiền.</div><?php endif; ?>
        <?php if ($quickNextStatus !== null): ?><form class="card mb-3 no-print" method="post" action="<?= e(url('/admin/orders/' . $order['id'] . '/next')) ?>">
            <?= csrf_field() ?><div class="card-body">
                <h2 class="h5">Bước tiếp theo</h2>
                <button class="btn btn-success w-100" type="submit">Chuyển sang: <?= e($statusLabels[$quickNextStatus] ?? $quickNextStatus) ?></button>
            </div>
        </form><?php endif; ?>
        <?php if ($order['status'] === 'shipping' && $canManage): ?><form class="card mb-3 no-print" method="post" action="<?= e(url('/admin/orders/' . $order['id'] . '/delivery-failure')) ?>">
            <?= csrf_field() ?><div class="card-header"><h2 class="h5 mb-0">Ghi nhận giao hàng thất bại</h2></div><div class="card-body">
                <label class="form-label" for="deliveryFailureReason">Lý do</label>
                <select class="form-select mb-2" id="deliveryFailureReason" name="reason" required><?php foreach ($deliveryFailureReasons as $reason => $label): ?><option value="<?= e($reason) ?>"><?= e($label) ?></option><?php endforeach; ?></select>
                <label class="form-label" for="deliveryFailureNote">Ghi chú <span class="text-secondary">(bắt buộc nếu chọn lý do khác)</span></label>
                <textarea class="form-control mb-3" id="deliveryFailureNote" name="note" rows="2" maxlength="450"></textarea>
                <button class="btn btn-outline-danger w-100" type="submit">Ghi nhận giao thất bại</button>
            </div>
        </form><script>
            (function () {
                var reason = document.getElementById('deliveryFailureReason');
                var note = document.getElementById('deliveryFailureNote');
                var updateRequirement = function () { note.required = reason.value === 'other'; };
                reason.addEventListener('change', updateRequirement);
                updateRequirement();
            }());
        </script><?php endif; ?>
        <?php
        $otherTransitions = array_values(array_filter(
            $transitions,
            static fn (string $status): bool => !in_array($status, ['confirmed', 'processing', 'shipping'], true)
                && $canManage
        ));
        ?>
        <?php if ($otherTransitions): ?><form class="card no-print" method="post" action="<?= e(url('/admin/orders/' . $order['id'] . '/status')) ?>">
            <?= csrf_field() ?><div class="card-header"><h2 class="h5 mb-0">Thao tác khác</h2></div><div class="card-body">
            <label class="form-label" for="orderStatus">Chuyển sang</label><select class="form-select mb-2" id="orderStatus" name="status"><?php foreach ($otherTransitions as $status): ?><option value="<?= e($status) ?>"><?= e($statusLabels[$status]) ?></option><?php endforeach; ?></select>
            <label class="form-label" for="orderNote">Lý do</label><textarea class="form-control mb-3" id="orderNote" name="note" rows="2" required></textarea>
            <button class="btn btn-outline-danger w-100" type="submit">Thực hiện</button></div></form><?php endif; ?>
    </aside>
</div>
<style>@media print { body { background: white !important; } .no-print, nav, aside .card:last-child { display: none !important; } main { width: 100% !important; } }</style>
