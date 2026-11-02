<header class="page-heading"><nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/')) ?>">Trang chủ</a></li><li class="breadcrumb-item active" aria-current="page">Sản phẩm yêu thích</li></ol></nav><div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div>
        <h1 class="h2 mb-1">Sản phẩm yêu thích</h1>
        <p class="text-secondary mb-0">Danh sách được lưu trong tài khoản của bạn.</p>
    </div>
    <a class="btn btn-outline-success" href="<?= e(url('/san-pham')) ?>">Tiếp tục mua sắm</a>
</div></header>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<?php if (!$error): ?>
    <?php if (!$products): ?>
        <div class="empty-state"><h2 class="h4">Chưa có sản phẩm yêu thích</h2><p class="text-secondary">Lưu sản phẩm để xem lại sau.</p><a class="btn btn-success" href="<?= e(url('/san-pham')) ?>">Khám phá sản phẩm</a></div>
    <?php else: ?>
        <div data-wishlist-list>
            <?php $removeFromWishlist = true; require FRONTEND_PATH . '/views/client/product-cards.php'; ?>
        </div>
        <div class="visually-hidden" data-wishlist-message role="status" aria-live="polite"></div>
    <?php endif; ?>
<?php endif; ?>
