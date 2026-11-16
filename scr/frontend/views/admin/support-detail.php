<?php $statusTone = ['open' => 'warning', 'answered' => 'success', 'closed' => 'neutral'][$ticket['status']] ?? 'neutral'; ?>
<header class="page-heading">
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/admin')) ?>">Quản trị</a></li><li class="breadcrumb-item"><a href="<?= e(url('/admin/support')) ?>">Phiếu hỗ trợ</a></li><li class="breadcrumb-item active" aria-current="page">#<?= (int) $ticket['id'] ?></li></ol></nav>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div><h1 class="h2 mb-1"><?= e($ticket['subject']) ?></h1><span class="status-badge status-badge--<?= e($statusTone) ?>"><?= e($statuses[$ticket['status']] ?? $ticket['status']) ?></span></div>
        <a class="btn btn-outline-secondary" href="<?= e(url('/admin/support')) ?>">Danh sách phiếu</a>
    </div>
</header>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <section class="card h-100">
            <div class="card-header"><h2 class="h5 mb-0">Khách hàng</h2></div>
            <div class="card-body">
                <strong><?= e($ticket['customer_name']) ?></strong>
                <div><a href="mailto:<?= e($ticket['customer_email']) ?>"><?= e($ticket['customer_email']) ?></a></div>
                <div><?= e($ticket['customer_phone'] ?? 'Chưa có số điện thoại') ?></div>
            </div>
        </section>
    </div>
    <div class="col-md-6">
        <section class="card h-100">
            <div class="card-header"><h2 class="h5 mb-0">Thông tin phiếu</h2></div>
            <div class="card-body">
                <div>Loại: <?= e($categories[$ticket['category']] ?? $ticket['category']) ?></div>
                <div>Tạo lúc: <?= e($ticket['created_at']) ?></div>
                <div>Cập nhật lúc: <?= e($ticket['updated_at']) ?></div>
                <?php if ($ticket['closed_at']): ?><div>Đóng lúc: <?= e($ticket['closed_at']) ?></div><?php endif; ?>
                <div>Đơn hàng:
                    <?php if ($ticket['order_id']): ?><a href="<?= e(url('/admin/orders/' . (int) $ticket['order_id'])) ?>"><?= e($ticket['order_no'] ?? ('#' . (int) $ticket['order_id'])) ?></a>
                    <?php else: ?>Không liên kết<?php endif; ?>
                </div>
            </div>
        </section>
    </div>
</div>
<section class="card mb-4" aria-label="Cuộc hội thoại">
    <div class="card-header"><h2 class="h5 mb-0">Trao đổi hỗ trợ</h2></div>
    <div class="card-body d-grid gap-3">
        <?php foreach ($messages as $message): ?>
            <article class="border rounded p-3 <?= $message['sender_type'] === 'customer' ? 'bg-light' : 'border-success' ?>">
                <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                    <strong><?= e($message['sender_name'] ?? 'Khách hàng') ?></strong>
                    <time class="small text-secondary" datetime="<?= e($message['created_at']) ?>"><?= e($message['created_at']) ?></time>
                </div>
                <div class="text-break"><?= nl2br(e($message['body'])) ?></div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<div class="row g-3">
    <div class="col-lg-8">
        <?php if ($ticket['status'] === 'closed'): ?>
            <div class="alert alert-secondary">Phiếu đã đóng. Hãy đổi trạng thái sang Đang mở trước khi trả lời.</div>
        <?php else: ?>
            <form class="card card-body gap-3" method="post" action="<?= e(url('/admin/support/' . (int) $ticket['id'] . '/reply')) ?>">
                <?= csrf_field() ?>
                <div>
                    <label class="form-label" for="supportAdminReply">Trả lời khách hàng</label>
                    <textarea class="form-control" id="supportAdminReply" name="body" rows="5" minlength="2" maxlength="3000" required></textarea>
                </div>
                <button class="btn btn-success align-self-start" type="submit">Gửi trả lời</button>
            </form>
        <?php endif; ?>
    </div>
    <div class="col-lg-4">
        <form class="card card-body gap-3" method="post" action="<?= e(url('/admin/support/' . (int) $ticket['id'] . '/status')) ?>">
            <?= csrf_field() ?>
            <div>
                <label class="form-label" for="supportTicketStatus">Trạng thái phiếu</label>
                <select class="form-select" id="supportTicketStatus" name="status" required>
                    <?php foreach ($statuses as $key => $label): ?><option value="<?= e($key) ?>" <?= $ticket['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-outline-success align-self-start" type="submit">Cập nhật trạng thái</button>
        </form>
    </div>
</div>
