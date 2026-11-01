<?php
$wishlistButtonClass = $wishlistButtonClass ?? 'position-absolute top-0 end-0 m-2 z-1';
$wishlistIsSaved = in_array((int) $wishlistProductId, array_map('intval', $wishlistIds ?? []), true);
?>
<form class="<?= e($wishlistButtonClass) ?>" method="post"
      action="<?= e(url('/yeu-thich/chuyen-doi')) ?>" data-wishlist-toggle
      data-endpoint="<?= e(url('/yeu-thich/chuyen-doi')) ?>"
      data-login-url="<?= e(url('/login?return_to=' . rawurlencode('/san-pham/' . $wishlistProductSlug))) ?>"
      data-product-name="<?= e($wishlistProductName) ?>"
      <?= !empty($removeFromWishlist) ? 'data-remove-on-toggle="1"' : '' ?>>
    <?= csrf_field() ?>
    <input type="hidden" name="product_id" value="<?= (int) $wishlistProductId ?>">
    <button class="btn btn-light rounded-circle shadow-sm" type="submit"
            aria-label="<?= $wishlistIsSaved ? 'Bỏ yêu thích ' : 'Thêm yêu thích ' ?><?= e($wishlistProductName) ?>"
            aria-pressed="<?= $wishlistIsSaved ? 'true' : 'false' ?>"
            data-wishlist-heart><?= $wishlistIsSaved ? '&#9829;' : '&#9825;' ?></button>
    <span class="position-absolute top-100 end-0 small text-danger bg-white border rounded p-1"
          data-wishlist-feedback role="status" aria-live="polite" hidden></span>
</form>
