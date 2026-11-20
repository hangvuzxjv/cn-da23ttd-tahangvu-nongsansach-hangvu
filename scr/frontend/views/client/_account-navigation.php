<?php
$activeAccountTab = $activeAccountTab ?? '';
$accountNavigationItems = [
    ['key' => 'profile', 'label' => 'Hồ sơ', 'url' => '/profile', 'icon' => '<circle cx="12" cy="8" r="3.5"></circle><path d="M4.5 20a7.5 7.5 0 0 1 15 0"></path>'],
    ['key' => 'addresses', 'label' => 'Sổ địa chỉ', 'url' => '/addresses', 'icon' => '<path d="M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 1 1 14 0Z"></path><circle cx="12" cy="10" r="2"></circle>'],
    ['key' => 'orders', 'label' => 'Đơn hàng', 'url' => '/tai-khoan/don-hang', 'icon' => '<path d="M6 3h12l2 3v15H4V6l2-3Z"></path><path d="M8 9h8M8 13h8M8 17h5"></path>'],
    ['key' => 'vouchers', 'label' => 'Voucher của tôi', 'url' => '/voucher-cua-toi', 'icon' => '<path d="M3 7V4h18v3a3 3 0 0 0 0 6v7H3v-7a3 3 0 0 0 0-6Z"></path><path d="M13 8v1m0 3v1m0 3v1"></path>'],
    ['key' => 'missions', 'label' => 'Nhiệm vụ nhận voucher', 'url' => '/tai-khoan/nhiem-vu', 'icon' => '<path d="m12 3 1.8 5.7L20 11l-6.2 2.3L12 19l-1.8-5.7L4 11l6.2-2.3L12 3Z"></path><path d="m19 16 .8 2.2L22 19l-2.2.8L19 22l-.8-2.2L16 19l2.2-.8L19 16Z"></path>'],
    ['key' => 'wishlist', 'label' => 'Yêu thích', 'url' => '/tai-khoan/yeu-thich', 'icon' => '<path d="M20.8 8.7c0 5.2-8.8 11-8.8 11s-8.8-5.8-8.8-11A4.7 4.7 0 0 1 12 6.2a4.7 4.7 0 0 1 8.8 2.5Z"></path>'],
];
?>
<nav class="client-account-navigation" aria-label="Điều hướng tài khoản">
    <?php foreach ($accountNavigationItems as $item): ?>
        <a class="client-account-navigation__link<?= $activeAccountTab === $item['key'] ? ' is-active' : '' ?>"
           href="<?= e(url($item['url'])) ?>"<?= $activeAccountTab === $item['key'] ? ' aria-current="page"' : '' ?>>
            <svg class="client-nav-icon" viewBox="0 0 24 24" aria-hidden="true"><?= $item['icon'] ?></svg>
            <span><?= e($item['label']) ?></span>
        </a>
    <?php endforeach; ?>
</nav>
