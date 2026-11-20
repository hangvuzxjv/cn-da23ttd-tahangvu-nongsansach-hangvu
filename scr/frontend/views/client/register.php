<?php $oldReferralCode = old('referral_code'); ?>
<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6 col-xl-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 p-md-5">
                <div class="mb-4">
                    <span class="badge rounded-pill bg-success text-white mb-2">Tài khoản khách hàng</span>
                    <h1 class="h3 mb-2">Tạo tài khoản</h1>
                    <p class="text-secondary mb-0">Nhập thông tin để bắt đầu mua sắm. Mã xác minh sẽ được gửi đến Gmail của bạn.</p>
                </div>
                <form method="post" action="<?= e(url('/register')) ?>" novalidate>
                    <?= csrf_field() ?>
                    <?php foreach (['name' => 'Họ tên', 'email' => 'Email', 'phone' => 'Số điện thoại'] as $field => $label): ?>
                        <div class="mb-3">
                            <label class="form-label" for="<?= e($field) ?>"><?= e($label) ?></label>
                            <input class="form-control<?= isset($errors[$field]) ? ' is-invalid' : '' ?>" id="<?= e($field) ?>" name="<?= e($field) ?>" value="<?= e(old($field)) ?>" <?= $field === 'email' ? 'type="email" autocomplete="email" maxlength="190" placeholder="tenban@gmail.com"' : ($field === 'phone' ? 'type="tel" autocomplete="tel" maxlength="30"' : 'type="text" autocomplete="name" maxlength="150"') ?> required>
                            <?php if (isset($errors[$field])): ?><div class="invalid-feedback"><?= e($errors[$field]) ?></div><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <div class="mb-3">
                        <label class="form-label" for="referral_code">Mã giới thiệu <span class="text-secondary">(không bắt buộc)</span></label>
                        <input class="form-control<?= isset($errors['referral_code']) ? ' is-invalid' : '' ?>" id="referral_code" name="referral_code" value="<?= e($oldReferralCode !== '' ? $oldReferralCode : ($referralCode ?? '')) ?>" maxlength="16" autocomplete="off" placeholder="Nhập mã của bạn bè">
                        <?php if (isset($errors['referral_code'])): ?><div class="invalid-feedback"><?= e($errors['referral_code']) ?></div><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Mật khẩu</label>
                        <input class="form-control<?= isset($errors['password']) ? ' is-invalid' : '' ?>" id="password" name="password" type="password" autocomplete="new-password" required>
                        <?php if (isset($errors['password'])): ?><div class="invalid-feedback"><?= e($errors['password']) ?></div><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password_confirmation">Nhập lại mật khẩu</label>
                        <input class="form-control<?= isset($errors['password_confirmation']) ? ' is-invalid' : '' ?>" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                        <?php if (isset($errors['password_confirmation'])): ?><div class="invalid-feedback"><?= e($errors['password_confirmation']) ?></div><?php endif; ?>
                    </div>
                    <button class="btn btn-success w-100" type="submit">Tạo tài khoản</button>
                </form>
                <p class="text-center text-secondary mt-4 mb-0">Đã có tài khoản? <a href="<?= e(url('/login')) ?>">Đăng nhập</a></p>
            </div>
        </div>
    </div>
</div>
