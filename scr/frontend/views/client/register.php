<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h3 mb-3">Tạo tài khoản</h1>
                <form method="post" action="<?= e(url('/register')) ?>" novalidate>
                    <?= csrf_field() ?>
                    <?php foreach (['name' => 'Họ tên', 'email' => 'Email', 'phone' => 'Số điện thoại'] as $field => $label): ?>
                        <div class="mb-3">
                            <label class="form-label" for="<?= e($field) ?>"><?= e($label) ?></label>
                            <input class="form-control<?= isset($errors[$field]) ? ' is-invalid' : '' ?>" id="<?= e($field) ?>" name="<?= e($field) ?>" value="<?= e(old($field)) ?>" <?= $field === 'email' ? 'type="email" autocomplete="email"' : ($field === 'phone' ? 'type="tel" autocomplete="tel"' : 'type="text" autocomplete="name"') ?> required>
                            <?php if (isset($errors[$field])): ?><div class="invalid-feedback"><?= e($errors[$field]) ?></div><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <div class="mb-3">
                        <label class="form-label" for="password">Mật khẩu</label>
                        <input class="form-control<?= isset($errors['password']) ? ' is-invalid' : '' ?>" id="password" name="password" type="password" autocomplete="new-password" required>
                        <div class="form-text">Ít nhất 8 ký tự, gồm chữ và số (tối đa 72 byte khi dùng bcrypt).</div>
                        <?php if (isset($errors['password'])): ?><div class="invalid-feedback"><?= e($errors['password']) ?></div><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password_confirmation">Nhập lại mật khẩu</label>
                        <input class="form-control<?= isset($errors['password_confirmation']) ? ' is-invalid' : '' ?>" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                        <?php if (isset($errors['password_confirmation'])): ?><div class="invalid-feedback"><?= e($errors['password_confirmation']) ?></div><?php endif; ?>
                    </div>
                    <button class="btn btn-success" type="submit">Đăng ký</button>
                    <a class="btn btn-link" href="<?= e(url('/login')) ?>">Đã có tài khoản?</a>
                </form>
            </div>
        </div>
    </div>
</div>
