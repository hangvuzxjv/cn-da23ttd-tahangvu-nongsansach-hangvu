<?php
$statusTone = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger', 'refunded' => 'success', 'cancelled' => 'neutral'][$return['status']] ?? 'neutral';
$reasonLabels = OrderReturnService::reasons();
?>
<header class="page-heading">
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/')) ?>">Trang chủ</a></li><li class="breadcrumb-item"><a href="<?= e(url('/tai-khoan/don-hang/' . (int) $return['order_id'])) ?>">Đơn <?= e($return['order_no']) ?></a></li><li class="breadcrumb-item active" aria-current="page">Yêu cầu #<?= (int) $return['id'] ?></li></ol></nav>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div><h1 class="h2 mb-1">Yêu cầu trả hàng #<?= (int) $return['id'] ?></h1><span class="status-badge status-badge--<?= e($statusTone) ?>"><?= e($statuses[$return['status']] ?? $return['status']) ?></span></div>
        <a class="btn btn-outline-secondary" href="<?= e(url('/tai-khoan/don-hang/' . (int) $return['order_id'])) ?>">Xem đơn <?= e($return['order_no']) ?></a>
    </div>
</header>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<div class="row g-4">
    <div class="col-lg-7">
        <section class="card mb-4">
            <div class="card-header"><h2 class="h5 mb-0">Nội dung yêu cầu</h2></div>
            <div class="card-body">
                <p><strong>Lý do:</strong> <?= e($reasonLabels[$return['reason_code']] ?? $return['reason_code']) ?></p>
                <p class="text-break"><?= nl2br(e($return['description'])) ?></p>
                <?php if ($return['items']): ?><h3 class="h6">Sản phẩm cần xử lý</h3><ul><?php foreach ($return['items'] as $item): ?><li><?= e($item['product_name']) ?> × <?= (int) $item['return_quantity'] ?> <?= e($item['unit']) ?></li><?php endforeach; ?></ul><?php endif; ?>
                <?php if ($return['images']): ?>
                    <h3 class="h6 mt-4">Ảnh bằng chứng</h3>
                    <div class="row g-2"><?php foreach ($return['images'] as $image): ?><div class="col-6 col-md-4"><a href="<?= e(url('/tai-khoan/yeu-cau-tra-hang/' . (int) $return['id'] . '/anh/' . (int) $image['id'])) ?>"><img class="img-fluid rounded border" src="<?= e(url('/tai-khoan/yeu-cau-tra-hang/' . (int) $return['id'] . '/anh/' . (int) $image['id'])) ?>" alt="Ảnh bằng chứng <?= e($image['original_name']) ?>"></a><div class="small text-secondary text-truncate"><?= e($image['original_name']) ?></div></div><?php endforeach; ?></div>
                <?php endif; ?>
                <?php if ($return['admin_note']): ?><div class="alert alert-info mt-4 mb-0"><strong>Phản hồi của cửa hàng:</strong><br><?= nl2br(e($return['admin_note'])) ?></div><?php endif; ?>
                <?php if ($return['refund_amount'] !== null): ?><p class="mb-0 mt-3"><strong>Số tiền hoàn:</strong> <?= number_format((float) $return['refund_amount'], 0, ',', '.') ?> đ</p><?php endif; ?>
            </div>
        </section>
        <section class="card">
            <div class="card-header"><h2 class="h5 mb-0">Lịch sử xử lý</h2></div>
            <div class="card-body"><ol class="mb-0"><?php foreach ($return['history'] as $event): ?><li class="mb-3"><strong><?= e($statuses[$event['status']] ?? $event['status']) ?></strong> · <time datetime="<?= e($event['created_at']) ?>"><?= e($event['created_at']) ?></time><?php if ($event['note']): ?><div class="small text-secondary"><?= nl2br(e($event['note'])) ?></div><?php endif; ?></li><?php endforeach; ?></ol></div>
        </section>
    </div>
    <div class="col-lg-5">
        <section class="card">
            <div class="card-header"><h2 class="h5 mb-0">Thông tin</h2></div>
            <div class="card-body">
                <div>Gửi lúc: <?= e($return['requested_at']) ?></div>
                <?php if ($return['decided_at']): ?><div>Phản hồi lúc: <?= e($return['decided_at']) ?></div><?php endif; ?>
                <?php if ($return['refunded_at']): ?><div>Hoàn tiền lúc: <?= e($return['refunded_at']) ?></div><?php endif; ?>
                <?php if ($return['status'] === 'pending'): ?><form class="mt-4" method="post" action="<?= e(url('/tai-khoan/yeu-cau-tra-hang/' . (int) $return['id'] . '/huy')) ?>" data-confirm="Bạn có chắc muốn hủy yêu cầu này?"><?= csrf_field() ?><button class="btn btn-outline-danger" type="submit">Hủy yêu cầu</button></form><?php endif; ?>
            </div>
        </section>
    </div>
</div>
