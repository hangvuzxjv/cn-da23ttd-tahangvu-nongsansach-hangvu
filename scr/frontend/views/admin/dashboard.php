<div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
        <h1 class="h2">Bảng điều khiển</h1>
        <p class="text-secondary mb-0">Xin chào <?= e($user['name'] ?? '') ?> · Vai trò: <?= e($user['role'] ?? '') ?></p>
    </div>
    <a class="btn btn-outline-success" href="<?= e(url('/profile')) ?>">Hồ sơ</a>
</div>
