<h1 class="h2 mb-3">Quản lý voucher</h1>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<details class="card mb-4"><summary class="card-header fw-semibold">Tạo voucher mới</summary><div class="card-body">
    <form method="post" action="<?= e(url('/admin/vouchers')) ?>"><?= csrf_field() ?>
        <?php require FRONTEND_PATH . '/views/admin/voucher-fields.php'; ?>
        <button class="btn btn-success">Tạo voucher</button>
    </form>
</div></details>
<div class="accordion" id="voucherList"><?php foreach ($vouchers as $index=>$voucher): ?>
    <details class="card mb-2"><summary class="card-header d-flex justify-content-between"><span><strong><?= e($voucher['code']) ?></strong> · <?= e($voucher['status']) ?></span><span>Dùng <?= (int) $voucher['active_uses'] ?> / <?= e($voucher['usage_limit'] ?? '∞') ?></span></summary>
    <div class="card-body"><form method="post" action="<?= e(url('/admin/vouchers/' . $voucher['id'])) ?>"><?= csrf_field() ?>
        <?php $voucherValues = $voucher; ?>
        <?php require FRONTEND_PATH . '/views/admin/voucher-fields.php'; ?>
        <button class="btn btn-primary">Lưu thay đổi</button>
    </form></div></details>
<?php endforeach; ?></div>
