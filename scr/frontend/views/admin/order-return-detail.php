<?php
$statusTone = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger', 'refunded' => 'success', 'cancelled' => 'neutral'][$return['status']] ?? 'neutral';
$orderStatusLabels = ['pending' => 'Chờ xác nhận', 'confirmed' => 'Đã xác nhận', 'processing' => 'Đang đóng gói', 'shipping' => 'Đang giao', 'completed' => 'Đã giao', 'cancelled' => 'Đã hủy', 'refunded' => 'Hoàn trả', 'delivery_failed' => 'Giao thất bại'];
?>
<header class="page-heading">
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/admin')) ?>">Quản trị</a></li><li class="breadcrumb-item"><a href="<?= e(url('/admin/returns')) ?>">Trả hàng / Hoàn tiền</a></li><li class="breadcrumb-item active" aria-current="page">#<?= (int) $return['id'] ?></li></ol></nav>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div><h1 class="h2 mb-1">Yêu cầu #<?= (int) $return['id'] ?></h1><span class="status-badge status-badge--<?= e($statusTone) ?>"><?= e($statuses[$return['status']] ?? $return['status']) ?></span></div>
        <a class="btn btn-outline-secondary" href="<?= e(url('/admin/returns')) ?>">Danh sách yêu cầu</a>
    </div>
</header>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<div class="row g-3 mb-4">
    <div class="col-md-6"><section class="card h-100"><div class="card-header"><h2 class="h5 mb-0">Khách hàng</h2></div><div class="card-body"><strong><?= e($return['customer_name']) ?></strong><div><a href="mailto:<?= e($return['customer_email']) ?>"><?= e($return['customer_email']) ?></a></div><div><?= e($return['customer_phone'] ?? 'Chưa có số điện thoại') ?></div></div></section></div>
    <div class="col-md-6"><section class="card h-100"><div class="card-header"><h2 class="h5 mb-0">Đơn hàng</h2></div><div class="card-body"><div><a href="<?= e(url('/admin/orders/' . (int) $return['order_id'])) ?>"><?= e($return['order_no']) ?></a></div><div>Trạng thái: <?= e($orderStatusLabels[$return['order_status']] ?? $return['order_status']) ?></div><div>Tổng thanh toán: <?= number_format((float) $return['total_amount'], 0, ',', '.') ?> đ</div><div>Thanh toán: <span class="status-badge <?= e(PaymentLabels::statusBadgeClass((string) $return['payment_status'])) ?>"><?= e(PaymentLabels::status((string) $return['payment_status'])) ?></span></div><div>Yêu cầu lúc: <?= e(PaymentLabels::dateTime($return['requested_at'])) ?></div></div></section></div>
</div>
<div class="row g-3">
    <div class="col-lg-7">
        <section class="card mb-3">
            <div class="card-header"><h2 class="h5 mb-0">Nội dung khách gửi</h2></div>
            <div class="card-body">
                <p><strong>Lý do:</strong> <?= e($reasons[$return['reason_code']] ?? $return['reason_code']) ?></p>
                <div class="text-break"><?= nl2br(e($return['description'])) ?></div>
                <div class="table-responsive mt-3"><table class="table table-sm"><thead><tr><th>Sản phẩm</th><th>Đã mua</th><th>Yêu cầu trả</th><th>Thành tiền tối đa</th></tr></thead><tbody>
                    <?php foreach ($return['items'] as $item): ?><tr><td><?= e($item['product_name']) ?></td><td><?= (int) $item['ordered_quantity'] ?> <?= e($item['unit']) ?></td><td><?= (int) $item['return_quantity'] ?> <?= e($item['unit']) ?></td><td><?= number_format((float) $item['unit_price'] * (int) $item['return_quantity'], 0, ',', '.') ?> đ</td></tr><?php endforeach; ?>
                </tbody></table></div>
                <p class="small mb-1">Mức hoàn tối đa theo sản phẩm yêu cầu: <strong><?= number_format((float) $return['maximum_refund'], 0, ',', '.') ?> đ</strong></p>
                <?php if ($return['images']): ?><h3 class="h6 mt-4">Ảnh bằng chứng</h3><div class="row g-2"><?php foreach ($return['images'] as $image): ?><div class="col-6 col-md-4"><a href="<?= e(url('/admin/returns/' . (int) $return['id'] . '/anh/' . (int) $image['id'])) ?>" target="_blank" rel="noopener"><img class="img-fluid rounded border" src="<?= e(url('/admin/returns/' . (int) $return['id'] . '/anh/' . (int) $image['id'])) ?>" alt="Ảnh bằng chứng <?= e($image['original_name']) ?>"></a><div class="small text-secondary text-truncate"><?= e($image['original_name']) ?></div></div><?php endforeach; ?></div><?php endif; ?>
            </div>
        </section>
        <section class="card">
            <div class="card-header"><h2 class="h5 mb-0">Lịch sử</h2></div>
            <div class="card-body"><ol class="mb-0"><?php foreach ($return['history'] as $event): ?><li class="mb-3"><strong><?= e($statuses[$event['status']] ?? $event['status']) ?></strong> · <time datetime="<?= e($event['created_at']) ?>"><?= e(PaymentLabels::dateTime($event['created_at'])) ?></time><?php if ($event['actor_name']): ?> · <?= e($event['actor_name']) ?><?php endif; ?><?php if ($event['note']): ?><div class="small text-secondary"><?= nl2br(e($event['note'])) ?></div><?php endif; ?></li><?php endforeach; ?></ol></div>
        </section>
    </div>
    <div class="col-lg-5">
        <?php if ($return['status'] === 'pending'): ?>
            <form class="card card-body gap-3 mb-3" method="post" action="<?= e(url('/admin/returns/' . (int) $return['id'] . '/approve')) ?>">
                <?= csrf_field() ?>
                <h2 class="h5 mb-0">Duyệt yêu cầu</h2>
                <div><label class="form-label" for="returnResolution">Cách giải quyết</label><select class="form-select" id="returnResolution" name="resolution" required><option value="refund">Hoàn tiền</option><option value="replace">Gửi đổi / bù hàng</option></select></div>
                <div><label class="form-label" for="returnRefundAmount">Số tiền hoàn (đ)</label><input class="form-control" id="returnRefundAmount" name="refund_amount" type="number" min="0.01" max="<?= e(number_format((float) $return['maximum_refund'], 2, '.', '')) ?>" step="0.01"><div class="form-text">Không vượt <?= number_format((float) $return['maximum_refund'], 0, ',', '.') ?> đ.</div></div>
                <div><label class="form-label" for="returnAdminNote">Ghi chú cho khách</label><textarea class="form-control" id="returnAdminNote" name="admin_note" rows="3" maxlength="1000"></textarea><div class="form-text">Bắt buộc nếu chọn đổi/bù hàng.</div></div>
                <button class="btn btn-success align-self-start" type="submit">Duyệt</button>
            </form>
            <form class="card card-body gap-3 mb-3" method="post" action="<?= e(url('/admin/returns/' . (int) $return['id'] . '/reject')) ?>">
                <?= csrf_field() ?><h2 class="h5 mb-0">Từ chối yêu cầu</h2>
                <div><label class="form-label" for="returnRejectReason">Lý do từ chối</label><textarea class="form-control" id="returnRejectReason" name="reason" rows="3" minlength="3" maxlength="1000" required></textarea></div>
                <button class="btn btn-outline-danger align-self-start" type="submit">Từ chối</button>
            </form>
        <?php elseif ($return['status'] === 'approved' && $return['resolution'] === 'refund'): ?>
            <form class="card card-body gap-3" method="post" action="<?= e(url('/admin/returns/' . (int) $return['id'] . '/refunded')) ?>" data-confirm="Xác nhận đã thực hiện hoàn tiền bên ngoài cổng thanh toán?">
                <?= csrf_field() ?>
                <h2 class="h5 mb-0">Ghi nhận đã hoàn tiền</h2>
                <p class="mb-0">Số tiền đã duyệt: <strong><?= number_format((float) $return['refund_amount'], 0, ',', '.') ?> đ</strong>. Với COD, hãy hoàn tiền thủ công trước khi xác nhận.</p>
                <div><label class="form-label" for="returnRefundReference">Mã / ghi chú tham chiếu hoàn tiền</label><input class="form-control" id="returnRefundReference" name="refund_reference" maxlength="150" required></div>
                <div class="form-check"><input class="form-check-input" id="returnRestock" type="checkbox" name="restock" value="1"><label class="form-check-label" for="returnRestock">Nhập lại kho theo đúng lô đã xuất (mặc định tắt)</label></div>
                <button class="btn btn-success align-self-start" type="submit">Đã hoàn tiền</button>
            </form>
        <?php elseif ($return['resolution'] === 'replace' && $return['status'] === 'approved'): ?>
            <div class="alert alert-info"><strong>Đã duyệt đổi/bù hàng.</strong><?php if ($return['admin_note']): ?><div><?= nl2br(e($return['admin_note'])) ?></div><?php endif; ?></div>
        <?php endif; ?>
        <?php if ($return['admin_note'] && $return['status'] !== 'approved'): ?><div class="alert alert-secondary mt-3"><strong>Ghi chú / phản hồi:</strong><br><?= nl2br(e($return['admin_note'])) ?></div><?php endif; ?>
    </div>
</div>
