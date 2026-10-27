<h1 class="h2 mb-4">Sổ địa chỉ</h1>
<?php if (!empty($success)): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<div class="row g-4">
    <div class="col-lg-5">
        <div class="card"><div class="card-body">
            <h2 class="h5">Thêm địa chỉ</h2>
            <form method="post" action="<?= e(url('/addresses')) ?>">
                <?= csrf_field() ?>
                <?php foreach (['recipient_name' => 'Tên người nhận', 'phone' => 'Số điện thoại', 'address_line' => 'Địa chỉ'] as $field => $label): ?>
                    <div class="mb-3">
                        <label class="form-label" for="new_<?= e($field) ?>"><?= e($label) ?></label>
                        <input class="form-control<?= isset($errors[$field]) ? ' is-invalid' : '' ?>" id="new_<?= e($field) ?>" name="<?= e($field) ?>" value="<?= e(old($field)) ?>" required>
                        <?php if (isset($errors[$field])): ?><div class="invalid-feedback"><?= e($errors[$field]) ?></div><?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <div class="mb-3">
                    <label class="form-label" for="new_region_id">Tỉnh/vùng</label>
                    <select class="form-select<?= isset($errors['region_id']) ? ' is-invalid' : '' ?>" id="new_region_id" name="region_id" required>
                        <option value="">Chọn tỉnh/vùng</option>
                        <?php foreach ($regions as $region): ?><option value="<?= (int) $region['id'] ?>" <?= (string) old('region_id') === (string) $region['id'] ? 'selected' : '' ?>><?= e($region['name']) ?></option><?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['region_id'])): ?><div class="invalid-feedback"><?= e($errors['region_id']) ?></div><?php endif; ?>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="new_ward">Phường/xã (không bắt buộc)</label>
                    <input class="form-control" id="new_ward" name="ward" value="<?= e(old('ward')) ?>">
                </div>
                <div class="form-check mb-3"><input class="form-check-input" id="new_is_default" name="is_default" type="checkbox" value="1"><label class="form-check-label" for="new_is_default">Đặt làm địa chỉ mặc định</label></div>
                <button class="btn btn-success" type="submit">Thêm địa chỉ</button>
            </form>
        </div></div>
    </div>
    <div class="col-lg-7">
        <?php if (!$addresses): ?><div class="alert alert-light border">Bạn chưa lưu địa chỉ nào.</div><?php endif; ?>
        <?php foreach ($addresses as $address): ?>
            <div class="card mb-3"><div class="card-body">
                <div class="d-flex justify-content-between">
                    <h2 class="h6"><?= e($address['recipient_name']) ?> <?= (int) $address['is_default'] === 1 ? '<span class="badge text-bg-success">Mặc định</span>' : '' ?></h2>
                    <form method="post" action="<?= e(url('/addresses/' . $address['id'] . '/delete')) ?>" onsubmit="return confirm('Xóa địa chỉ này?')">
                        <?= csrf_field() ?><button class="btn btn-sm btn-outline-danger" type="submit">Xóa</button>
                    </form>
                </div>
                <details>
                    <summary><?= e($address['phone']) ?> · <?= e($address['address_line']) ?>, <?= e($address['region_name'] ?? '') ?></summary>
                    <form class="mt-3" method="post" action="<?= e(url('/addresses/' . $address['id'])) ?>">
                        <?= csrf_field() ?>
                        <?php foreach (['recipient_name' => 'Tên người nhận', 'phone' => 'Số điện thoại', 'address_line' => 'Địa chỉ'] as $field => $label): ?>
                            <div class="mb-2"><label class="form-label" for="a<?= (int) $address['id'] ?>_<?= e($field) ?>"><?= e($label) ?></label>
                                <input class="form-control" id="a<?= (int) $address['id'] ?>_<?= e($field) ?>" name="<?= e($field) ?>" value="<?= e($address[$field]) ?>" required></div>
                        <?php endforeach; ?>
                        <div class="mb-2"><label class="form-label" for="a<?= (int) $address['id'] ?>_region">Tỉnh/vùng</label>
                            <select class="form-select" id="a<?= (int) $address['id'] ?>_region" name="region_id" required>
                                <?php foreach ($regions as $region): ?><option value="<?= (int) $region['id'] ?>" <?= (int) $region['id'] === (int) $address['region_id'] ? 'selected' : '' ?>><?= e($region['name']) ?></option><?php endforeach; ?>
                            </select></div>
                        <div class="mb-2"><label class="form-label" for="a<?= (int) $address['id'] ?>_ward">Phường/xã</label>
                            <input class="form-control" id="a<?= (int) $address['id'] ?>_ward" name="ward" value="<?= e($address['ward']) ?>"></div>
                        <div class="form-check mb-2"><input class="form-check-input" id="a<?= (int) $address['id'] ?>_default" name="is_default" type="checkbox" value="1" <?= (int) $address['is_default'] === 1 ? 'checked' : '' ?>><label class="form-check-label" for="a<?= (int) $address['id'] ?>_default">Đặt làm mặc định</label></div>
                        <button class="btn btn-sm btn-success" type="submit">Lưu địa chỉ</button>
                    </form>
                </details>
            </div></div>
        <?php endforeach; ?>
    </div>
</div>
