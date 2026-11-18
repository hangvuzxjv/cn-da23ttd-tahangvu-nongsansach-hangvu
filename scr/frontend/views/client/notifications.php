<header class="page-heading">
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/')) ?>">Trang chủ</a></li><li class="breadcrumb-item active" aria-current="page">Thông báo</li></ol></nav>
    <h1 class="h2">Thông báo</h1>
    <p class="text-secondary">Các cập nhật mới nhất về đơn hàng và sản phẩm.</p>
</header>
<?php if (!empty($success)): ?><div class="alert alert-success" role="status"><?= e($success) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<section class="card">
    <div class="card-header notification-page-header">
        <h2 class="h5 mb-0">Tất cả thông báo</h2>
        <div class="notification-page-actions">
            <form method="post" action="<?= e(url('/notifications/delete-read')) ?>" data-confirm="Bạn có chắc muốn xóa tất cả thông báo đã đọc?">
                <?= csrf_field() ?>
                <input type="hidden" name="page" value="<?= (int) $page ?>">
                <button class="btn btn-outline-danger btn-sm" type="submit" <?= $total > $unread_count ? '' : 'disabled' ?>>Xóa thông báo đã đọc</button>
            </form>
            <form method="post" action="<?= e(url('/notifications/delete-all')) ?>" data-confirm="Bạn có chắc muốn xóa tất cả thông báo?">
                <?= csrf_field() ?>
                <input type="hidden" name="page" value="<?= (int) $page ?>">
                <button class="btn btn-outline-danger btn-sm" type="submit" <?= $total ? '' : 'disabled' ?>>Xóa tất cả</button>
            </form>
            <button class="btn btn-outline-success btn-sm" type="button" data-mark-all-notifications data-reload-after-read <?= $unread_count ? '' : 'disabled' ?>>Đánh dấu đã đọc tất cả</button>
        </div>
    </div>
    <div class="list-group list-group-flush notification-page-list">
        <?php foreach ($items as $item): ?>
            <?php
            $notificationPath = is_string($item['link_url']) && str_starts_with($item['link_url'], '/') && !str_starts_with($item['link_url'], '//')
                ? $item['link_url']
                : '/notifications?view=all';
            ?>
            <article class="list-group-item notification-page-item<?= $item['read_at'] ? '' : ' is-unread' ?>">
                <a class="notification-page-item__link" href="<?= e(url($notificationPath)) ?>">
                    <span class="notification-page-item__title"><?= e($item['title']) ?></span>
                    <span class="notification-page-item__message"><?= e($item['message']) ?></span>
                    <time class="notification-page-item__time" datetime="<?= e($item['created_at']) ?>"><?= e(date('d/m/Y H:i', strtotime($item['created_at']))) ?></time>
                </a>
                <form method="post" action="<?= e(url('/notifications/' . (int) $item['id'] . '/delete')) ?>" data-confirm="Bạn có chắc muốn xóa thông báo này?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="page" value="<?= (int) $page ?>">
                    <button class="btn btn-outline-danger btn-sm notification-page-item__delete" type="submit">Xóa</button>
                </form>
            </article>
        <?php endforeach; ?>
        <?php if (!$items): ?><div class="empty-state"><h2 class="h5">Bạn chưa có thông báo nào</h2><p class="mb-0">Các cập nhật sẽ xuất hiện tại đây.</p></div><?php endif; ?>
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
