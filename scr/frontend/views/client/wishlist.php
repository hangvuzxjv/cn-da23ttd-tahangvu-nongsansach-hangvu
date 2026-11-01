<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h2 mb-1">Sản phẩm yêu thích</h1>
        <p class="text-secondary mb-0">Danh sách được lưu trong tài khoản của bạn.</p>
    </div>
    <a class="btn btn-outline-success" href="<?= e(url('/san-pham')) ?>">Tiếp tục mua sắm</a>
</div>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<?php if (!$error): ?>
    <?php if (!$products): ?>
        <div class="alert alert-light border">Bạn chưa lưu sản phẩm yêu thích nào.</div>
    <?php else: ?>
        <div data-wishlist-list>
            <?php $removeFromWishlist = true; require FRONTEND_PATH . '/views/client/product-cards.php'; ?>
        </div>
        <div class="visually-hidden" data-wishlist-message role="status" aria-live="polite"></div>
    <?php endif; ?>
<?php endif; ?>
