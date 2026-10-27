<div class="row g-4">
    <div class="col-12"><h1 class="h2">Hồ sơ của tôi</h1></div>
    <div class="col-lg-6">
        <div class="card"><div class="card-body">
            <h2 class="h5">Thông tin tài khoản</h2>
            <?php if (!empty($success)): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
            <?php if (!empty($error)): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
            <p class="small">Trạng thái email: <?= !empty($profile['email_verified_at']) ? 'Đã xác thực' : 'Chưa xác thực (không ảnh hưởng việc mua hàng)' ?></p>
            <form method="post" action="<?= e(url('/profile')) ?>">
                <?= csrf_field() ?>
                <?php foreach (['name' => 'Họ tên', 'email' => 'Email', 'phone' => 'Số điện thoại'] as $field => $label): ?>
                    <?php $value = old($field, $profile[$field] ?? ''); ?>
                    <div class="mb-3">
                        <label class="form-label" for="<?= e($field) ?>"><?= e($label) ?></label>
                        <input class="form-control<?= isset($errors[$field]) ? ' is-invalid' : '' ?>" id="<?= e($field) ?>" name="<?= e($field) ?>" type="<?= $field === 'email' ? 'email' : ($field === 'phone' ? 'tel' : 'text') ?>" value="<?= e($value) ?>" required>
                        <?php if (isset($errors[$field])): ?><div class="invalid-feedback"><?= e($errors[$field]) ?></div><?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <button class="btn btn-success" type="submit">Lưu thông tin</button>
                <a class="btn btn-outline-success" href="<?= e(url('/addresses')) ?>">Sổ địa chỉ</a>
            </form>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card"><div class="card-body">
            <h2 class="h5">Đổi mật khẩu</h2>
            <form method="post" action="<?= e(url('/profile/password')) ?>">
                <?= csrf_field() ?>
                <?php foreach (['current_password' => 'Mật khẩu hiện tại', 'new_password' => 'Mật khẩu mới', 'password_confirmation' => 'Nhập lại mật khẩu mới'] as $field => $label): ?>
                    <div class="mb-3">
                        <label class="form-label" for="<?= e($field) ?>"><?= e($label) ?></label>
                        <input class="form-control<?= isset($errors[$field]) ? ' is-invalid' : '' ?>" id="<?= e($field) ?>" name="<?= e($field) ?>" type="password" autocomplete="current-password" required>
                        <?php if (isset($errors[$field])): ?><div class="invalid-feedback"><?= e($errors[$field]) ?></div><?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <button class="btn btn-outline-success" type="submit">Đổi mật khẩu</button>
            </form>
        </div></div>
    </div>
</div>
