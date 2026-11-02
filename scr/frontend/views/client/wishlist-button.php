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
    <button class="btn btn-light rounded-circle shadow-sm<?= $wishlistIsSaved ? ' is-saved' : '' ?>" type="submit"
            aria-label="<?= $wishlistIsSaved ? 'Bỏ yêu thích ' : 'Thêm yêu thích ' ?><?= e($wishlistProductName) ?>"
            aria-pressed="<?= $wishlistIsSaved ? 'true' : 'false' ?>"
            data-wishlist-heart><svg class="wishlist-heart" viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21l7.8-7.5 1.1-1.1a5.5 5.5 0 0 0-.1-7.8Z"></path></svg></button>
    <span class="position-absolute top-100 end-0 small text-danger bg-white border rounded p-1"
          data-wishlist-feedback role="status" aria-live="polite" hidden></span>
</form>
