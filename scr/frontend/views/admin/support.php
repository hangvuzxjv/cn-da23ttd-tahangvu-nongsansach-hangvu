<header class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div><h1 class="h2 mb-1">Phiếu hỗ trợ</h1><p class="text-secondary mb-0">Tổng <?= (int) $total ?> phiếu</p></div>
</header>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<form class="row g-2 mb-3" method="get" action="<?= e(url('/admin/support')) ?>">
    <div class="col-md-5"><label class="visually-hidden" for="supportSearch">Tìm phiếu hoặc khách hàng</label><input class="form-control" id="supportSearch" name="q" value="<?= e($filters['q']) ?>" placeholder="Mã phiếu, tên hoặc email khách"></div>
    <div class="col-md-3"><label class="visually-hidden" for="supportStatus">Lọc trạng thái</label><select class="form-select" id="supportStatus" name="status"><option value="">Mọi trạng thái</option><?php foreach ($statuses as $key => $label): ?><option value="<?= e($key) ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><label class="visually-hidden" for="supportCategory">Lọc loại phiếu</label><select class="form-select" id="supportCategory" name="category"><option value="">Mọi loại</option><?php foreach ($categories as $key => $label): ?><option value="<?= e($key) ?>" <?= $filters['category'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-1"><button class="btn btn-success w-100" type="submit">Lọc</button></div>
</form>
<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead><tr><th>Mã phiếu</th><th>Khách hàng</th><th>Tiêu đề</th><th>Loại</th><th>Trạng thái</th><th>Đơn hàng</th><th>Cập nhật lúc</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($tickets as $ticket): ?>
            <?php $statusTone = ['open' => 'warning', 'answered' => 'success', 'closed' => 'neutral'][$ticket['status']] ?? 'neutral'; ?>
            <tr>
                <td><a href="<?= e(url('/admin/support/' . (int) $ticket['id'])) ?>">#<?= (int) $ticket['id'] ?></a></td>
                <td><?= e($ticket['customer_name']) ?><div class="small text-secondary"><?= e($ticket['customer_email']) ?></div></td>
                <td><?= e($ticket['subject']) ?></td>
                <td><?= e($categories[$ticket['category']] ?? $ticket['category']) ?></td>
                <td><span class="status-badge status-badge--<?= e($statusTone) ?>"><?= e($statuses[$ticket['status']] ?? $ticket['status']) ?></span></td>
                <td><?= $ticket['order_no'] ? e($ticket['order_no']) : '—' ?></td>
                <td><?= e($ticket['updated_at']) ?></td>
                <td><a class="btn btn-sm btn-outline-success" href="<?= e(url('/admin/support/' . (int) $ticket['id'])) ?>">Mở</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$tickets): ?><tr><td colspan="8" class="text-center text-secondary py-4">Không có phiếu hỗ trợ phù hợp.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php include FRONTEND_PATH . '/views/admin/_pagination.php'; ?>
