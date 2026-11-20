<div class="row justify-content-center">
    <div class="col-12 col-md-7 col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 p-md-5">
                <span class="badge rounded-pill bg-success text-white mb-2">Bảo mật tài khoản</span>
                <h1 class="h3 mb-3">Tạo mật khẩu mới</h1>
                <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
                <?php if (!empty($token)): ?>
                    <form method="post" action="<?= e(url('/reset-password/' . rawurlencode($token))) ?>">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label" for="password">Mật khẩu mới</label>
                            <input class="form-control<?= isset($errors['password']) ? ' is-invalid' : '' ?>" id="password" name="password" type="password" autocomplete="new-password" required>
                            <div class="form-text">Ít nhất 8 ký tự, gồm chữ và số.</div>
                            <?php if (isset($errors['password'])): ?><div class="invalid-feedback"><?= e($errors['password']) ?></div><?php endif; ?>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="password_confirmation">Nhập lại mật khẩu</label>
                            <input class="form-control<?= isset($errors['password_confirmation']) ? ' is-invalid' : '' ?>" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                            <?php if (isset($errors['password_confirmation'])): ?><div class="invalid-feedback"><?= e($errors['password_confirmation']) ?></div><?php endif; ?>
                        </div>
                        <button class="btn btn-success w-100" type="submit">Cập nhật mật khẩu</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
