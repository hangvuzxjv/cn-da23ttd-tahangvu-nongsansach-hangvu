<?php $activeAccountTab = 'support'; ?>
<div class="client-account-layout">
    <aside class="client-account-layout__sidebar">
        <?php include FRONTEND_PATH . '/views/client/_account-navigation.php'; ?>
    </aside>
    <div class="client-account-layout__content">
<header class="page-heading">
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/')) ?>">Trang chủ</a></li><li class="breadcrumb-item active" aria-current="page">Phiếu hỗ trợ</li></ol></nav>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div><h1 class="h2 mb-1">Phiếu hỗ trợ</h1><p class="text-secondary mb-0">Theo dõi các yêu cầu chăm sóc khách hàng của bạn.</p></div>
        <a class="btn btn-success" href="<?= e(url('/ho-tro/tao')) ?>">Tạo phiếu mới</a>
    </div>
</header>
<div class="table-responsive">
    <table class="table align-middle">
        <thead><tr><th>Mã phiếu</th><th>Tiêu đề</th><th>Loại</th><th>Trạng thái</th><th>Cập nhật lúc</th></tr></thead>
        <tbody>
        <?php foreach ($tickets as $ticket): ?>
            <?php $statusTone = ['open' => 'warning', 'answered' => 'success', 'closed' => 'neutral'][$ticket['status']] ?? 'neutral'; ?>
            <tr>
                <td><a href="<?= e(url('/ho-tro/' . (int) $ticket['id'])) ?>">#<?= (int) $ticket['id'] ?></a></td>
                <td><a class="text-body text-decoration-none" href="<?= e(url('/ho-tro/' . (int) $ticket['id'])) ?>"><?= e($ticket['subject']) ?></a></td>
                <td><?= e($categories[$ticket['category']] ?? $ticket['category']) ?></td>
                <td><span class="status-badge status-badge--<?= e($statusTone) ?>"><?= e($statuses[$ticket['status']] ?? $ticket['status']) ?></span></td>
                <td><?= e($ticket['updated_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$tickets): ?><tr><td colspan="5"><div class="empty-state">Bạn chưa có phiếu hỗ trợ nào.</div></td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
    </div>
</div>
