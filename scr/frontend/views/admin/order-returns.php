<header class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div><h1 class="h2 mb-1">Yêu cầu trả hàng / hoàn tiền</h1><p class="text-secondary mb-0">Tổng <?= (int) $total ?> yêu cầu</p></div>
</header>
<form class="row g-2 mb-3" method="get" action="<?= e(url('/admin/returns')) ?>">
    <div class="col-md-6"><label class="visually-hidden" for="returnSearch">Tìm yêu cầu</label><input class="form-control" id="returnSearch" name="q" value="<?= e($filters['q']) ?>" placeholder="Mã đơn, tên, email hoặc số điện thoại khách"></div>
    <div class="col-md-4"><label class="visually-hidden" for="returnStatus">Lọc trạng thái</label><select class="form-select" id="returnStatus" name="status"><option value="">Mọi trạng thái</option><?php foreach ($statuses as $key => $label): ?><option value="<?= e($key) ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><button class="btn btn-success w-100" type="submit">Lọc yêu cầu</button></div>
</form>
<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead><tr><th>Yêu cầu</th><th>Đơn hàng</th><th>Khách hàng</th><th>Lý do</th><th>Trạng thái</th><th>Gửi lúc</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($returns as $return): ?>
            <?php $tone = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger', 'refunded' => 'success', 'cancelled' => 'neutral'][$return['status']] ?? 'neutral'; ?>
            <tr>
                <td><a href="<?= e(url('/admin/returns/' . (int) $return['id'])) ?>">#<?= (int) $return['id'] ?></a></td>
                <td><a href="<?= e(url('/admin/orders/' . (int) $return['order_id'])) ?>"><?= e($return['order_no']) ?></a></td>
                <td><?= e($return['customer_name']) ?><div class="small text-secondary"><?= e($return['customer_email']) ?></div><div class="small text-secondary"><?= e($return['customer_phone'] ?? '') ?></div></td>
                <td><?= e($reasons[$return['reason_code']] ?? $return['reason_code']) ?></td>
                <td><span class="status-badge status-badge--<?= e($tone) ?>"><?= e($statuses[$return['status']] ?? $return['status']) ?></span></td>
                <td><?= e($return['requested_at']) ?></td>
                <td><a class="btn btn-sm btn-outline-success" href="<?= e(url('/admin/returns/' . (int) $return['id'])) ?>">Mở</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$returns): ?><tr><td colspan="7" class="text-center text-secondary py-4">Không có yêu cầu phù hợp.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php include FRONTEND_PATH . '/views/admin/_pagination.php'; ?>
