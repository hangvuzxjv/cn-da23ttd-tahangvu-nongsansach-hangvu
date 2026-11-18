<?php $activeAccountTab = 'support'; ?>
<div class="client-account-layout">
    <aside class="client-account-layout__sidebar">
        <?php include FRONTEND_PATH . '/views/client/_account-navigation.php'; ?>
    </aside>
    <div class="client-account-layout__content">
<header class="page-heading">
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/')) ?>">Trang chủ</a></li><li class="breadcrumb-item"><a href="<?= e(url('/ho-tro')) ?>">Phiếu hỗ trợ</a></li><li class="breadcrumb-item active" aria-current="page">Tạo phiếu</li></ol></nav>
    <h1 class="h2">Tạo phiếu hỗ trợ</h1>
    <p class="text-secondary">Mô tả vấn đề để đội ngũ chăm sóc khách hàng hỗ trợ bạn.</p>
</header>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<form class="card card-body gap-3" method="post" action="<?= e(url('/ho-tro/tao')) ?>">
    <?= csrf_field() ?>
    <div>
        <label class="form-label" for="supportSubject">Tiêu đề</label>
        <input class="form-control" id="supportSubject" name="subject" minlength="5" maxlength="150" value="<?= e($oldInput['subject'] ?? '') ?>" required>
    </div>
    <div>
        <label class="form-label" for="supportCategory">Loại hỗ trợ</label>
        <select class="form-select" id="supportCategory" name="category" required>
            <option value="">Chọn loại hỗ trợ</option>
            <?php foreach ($categories as $key => $label): ?><option value="<?= e($key) ?>" <?= ($oldInput['category'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="form-label" for="supportOrder">Đơn hàng liên quan <span class="text-secondary">(không bắt buộc)</span></label>
        <select class="form-select" id="supportOrder" name="order_id">
            <option value="">Không chọn đơn hàng</option>
            <?php foreach ($orders as $order): ?><option value="<?= (int) $order['id'] ?>" <?= (string) ($oldInput['order_id'] ?? '') === (string) $order['id'] ? 'selected' : '' ?>><?= e($order['order_no']) ?> · <?= e($order['placed_at']) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="form-label" for="supportBody">Nội dung</label>
        <textarea class="form-control" id="supportBody" name="body" rows="6" minlength="10" maxlength="3000" required><?= e($oldInput['body'] ?? '') ?></textarea>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-success" type="submit">Gửi phiếu</button>
        <a class="btn btn-outline-secondary" href="<?= e(url('/ho-tro')) ?>">Hủy</a>
    </div>
</form>
</div>
</div>
