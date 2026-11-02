<header class="page-heading">
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/')) ?>">Trang chủ</a></li><li class="breadcrumb-item active" aria-current="page">Thông báo</li></ol></nav>
    <h1 class="h2">Thông báo</h1>
    <p class="text-secondary">Các cập nhật mới nhất về đơn hàng và sản phẩm.</p>
</header>
<section class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h2 class="h5 mb-0">Tất cả thông báo</h2>
        <button class="btn btn-outline-success btn-sm" type="button" data-mark-all-notifications data-reload-after-read <?= $unread_count ? '' : 'disabled' ?>>Đánh dấu đã đọc tất cả</button>
    </div>
    <div class="list-group list-group-flush notification-page-list">
        <?php foreach ($items as $item): ?>
            <?php
            $notificationPath = is_string($item['link_url']) && str_starts_with($item['link_url'], '/') && !str_starts_with($item['link_url'], '//')
                ? $item['link_url']
                : '/notifications?view=all';
            ?>
            <a class="list-group-item list-group-item-action notification-page-item<?= $item['read_at'] ? '' : ' is-unread' ?>" href="<?= e(url($notificationPath)) ?>">
                <span class="notification-page-item__title"><?= e($item['title']) ?></span>
                <span class="notification-page-item__message"><?= e($item['message']) ?></span>
                <time class="notification-page-item__time" datetime="<?= e($item['created_at']) ?>"><?= e(date('d/m/Y H:i', strtotime($item['created_at']))) ?></time>
            </a>
        <?php endforeach; ?>
        <?php if (!$items): ?><div class="empty-state"><h2 class="h5">Chưa có thông báo</h2><p class="mb-0">Các cập nhật sẽ xuất hiện tại đây.</p></div><?php endif; ?>
    </div>
    <div class="px-3" data-notification-page-message role="status" aria-live="polite"></div>
    <?php if ($pages > 1): ?>
        <nav class="p-3" aria-label="Phân trang thông báo"><ul class="pagination mb-0">
            <?php for ($index = 1; $index <= $pages; $index++): ?>
                <li class="page-item <?= $index === $page ? 'active' : '' ?>"><a class="page-link" href="<?= e(url('/notifications?view=all&page=' . $index)) ?>" <?= $index === $page ? 'aria-current="page"' : '' ?>><?= $index ?></a></li>
            <?php endfor; ?>
        </ul></nav>
    <?php endif; ?>
</section>
