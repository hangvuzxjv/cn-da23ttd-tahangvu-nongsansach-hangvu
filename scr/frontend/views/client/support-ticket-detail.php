<?php $statusTone = ['open' => 'warning', 'answered' => 'success', 'closed' => 'neutral'][$ticket['status']] ?? 'neutral'; ?>
<?php $activeAccountTab = 'support'; ?>
<div class="client-account-layout">
    <aside class="client-account-layout__sidebar">
        <?php include FRONTEND_PATH . '/views/client/_account-navigation.php'; ?>
    </aside>
    <div class="client-account-layout__content">
<header class="page-heading">
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/')) ?>">Trang chủ</a></li><li class="breadcrumb-item"><a href="<?= e(url('/ho-tro')) ?>">Phiếu hỗ trợ</a></li><li class="breadcrumb-item active" aria-current="page">#<?= (int) $ticket['id'] ?></li></ol></nav>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h1 class="h2 mb-1"><?= e($ticket['subject']) ?></h1>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="status-badge status-badge--<?= e($statusTone) ?>"><?= e($statuses[$ticket['status']] ?? $ticket['status']) ?></span>
                <span class="small text-secondary"><?= e($categories[$ticket['category']] ?? $ticket['category']) ?></span>
                <?php if ($ticket['order_no']): ?><span class="small text-secondary">Đơn hàng <?= e($ticket['order_no']) ?></span><?php endif; ?>
            </div>
        </div>
        <a class="btn btn-outline-secondary" href="<?= e(url('/ho-tro')) ?>">Danh sách phiếu</a>
    </div>
</header>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<section class="card mb-4" aria-label="Cuộc hội thoại">
    <div class="card-header"><h2 class="h5 mb-0">Trao đổi hỗ trợ</h2></div>
    <div class="card-body d-grid gap-3">
        <?php foreach ($messages as $message): ?>
            <article class="border rounded p-3 <?= $message['sender_type'] === 'customer' ? 'bg-light' : 'border-success' ?>">
                <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                    <strong><?= $message['sender_type'] === 'customer' ? 'Bạn' : 'Nông Sản Sạch' ?></strong>
                    <time class="small text-secondary" datetime="<?= e($message['created_at']) ?>"><?= e($message['created_at']) ?></time>
                </div>
                <div class="text-break"><?= nl2br(e($message['body'])) ?></div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php if ($ticket['status'] !== 'closed'): ?>
    <form class="card card-body gap-3 mb-3" method="post" action="<?= e(url('/ho-tro/' . (int) $ticket['id'] . '/tra-loi')) ?>">
        <?= csrf_field() ?>
        <div>
            <label class="form-label" for="supportReply">Trả lời</label>
            <textarea class="form-control" id="supportReply" name="body" rows="5" minlength="10" maxlength="3000" required><?= e($oldBody) ?></textarea>
        </div>
        <button class="btn btn-success align-self-start" type="submit">Gửi trả lời</button>
    </form>
    <form method="post" action="<?= e(url('/ho-tro/' . (int) $ticket['id'] . '/dong')) ?>" data-confirm="Bạn có chắc muốn đóng phiếu hỗ trợ này?">
        <?= csrf_field() ?>
        <button class="btn btn-outline-danger" type="submit">Đóng phiếu</button>
    </form>
<?php else: ?>
    <div class="alert alert-secondary">Phiếu đã đóng và không thể gửi thêm trả lời.</div>
<?php endif; ?>
</div>
</div>
