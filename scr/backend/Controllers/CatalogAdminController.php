<?php

declare(strict_types=1);

final class CatalogAdminController extends Controller
{
    public function regions(): void
    {
        $regions = Database::connection()->query(
            'SELECT r.*, COUNT(DISTINCT child.id) AS child_count,
                    COUNT(DISTINCT p.id) AS product_count,
                    COUNT(DISTINCT f.id) AS farm_count
             FROM regions r
             LEFT JOIN regions child ON child.parent_id = r.id
             LEFT JOIN products p ON p.region_id = r.id
             LEFT JOIN farms f ON f.region_id = r.id
             GROUP BY r.id ORDER BY r.level, r.parent_id, r.name'
        )->fetchAll();
        $this->view('admin/regions', [
            'title' => 'Vùng miền',
            'regions' => $regions,
            'canManage' => Auth::can('regions.manage'),
            'menu' => $this->menu(),
            'errors' => form_errors(),
            'success' => flash('success'),
            'error' => flash('error'),
        ], 'admin');
    }

    public function storeRegion(): void
    {
        $name = $this->postText('name');
        $parentId = $this->postId('parent_id');
        $description = $this->postText('description');
        $slug = $this->uniqueSlug('regions', $this->postText('slug') ?: $name);
        $active = $this->postChecked('is_active') ? 1 : 0;
        if ($name === '' || mb_strlen($name, 'UTF-8') > 120) {
            $this->fail('/admin/regions', ['name' => 'Tên vùng miền bắt buộc, tối đa 120 ký tự.']);
        }
        if ($slug === '' || mb_strlen($slug, 'UTF-8') > 140) {
            $this->fail('/admin/regions', ['slug' => 'Slug vùng miền không hợp lệ hoặc dài hơn 140 ký tự.']);
        }
        $level = 1;
        if ($parentId > 0) {
            $parent = Database::connection()->prepare('SELECT id, level FROM regions WHERE id = :id');
            $parent->execute(['id' => $parentId]);
            $parentRow = $parent->fetch();
            if (!$parentRow || (int) $parentRow['level'] !== 1) {
                $this->fail('/admin/regions', ['parent_id' => 'Tỉnh/vùng phải thuộc một miền cấp 1.']);
            }
            $level = 2;
        }
        $this->writeRegion(
            'INSERT INTO regions (parent_id, name, level, slug, description, is_active)
             VALUES (:parent_id, :name, :level, :slug, :description, :is_active)',
            [
                'parent_id' => $parentId ?: null,
                'name' => $name,
                'level' => $level,
                'slug' => $slug,
                'description' => $description,
                'is_active' => $active,
            ],
            null,
            'region_created'
        );
        flash('success', 'Đã thêm vùng miền.');
        redirect('/admin/regions');
    }

    public function updateRegion(string $id): void
    {
        $regionId = (int) $id;
        $current = $this->findRow('regions', $regionId);
        $name = $this->postText('name');
        $parentId = $this->postId('parent_id');
        $description = $this->postText('description');
        $slug = $this->uniqueSlug('regions', $this->postText('slug') ?: $name, $regionId);
        if ($name === '' || mb_strlen($name, 'UTF-8') > 120) {
            $this->fail('/admin/regions', ['name' => 'Tên vùng miền bắt buộc, tối đa 120 ký tự.']);
        }
        if ($slug === '' || mb_strlen($slug, 'UTF-8') > 140) {
            $this->fail('/admin/regions', ['slug' => 'Slug vùng miền không hợp lệ hoặc dài hơn 140 ký tự.']);
        }
        if ((int) $current['level'] === 1 && $parentId !== 0) {
            $this->fail('/admin/regions', ['parent_id' => 'Không thể đặt một miền dưới miền khác.']);
        }
        if ((int) $current['level'] === 2) {
            $parent = Database::connection()->prepare('SELECT id, level FROM regions WHERE id = :id AND id <> :self');
            $parent->execute(['id' => $parentId, 'self' => $regionId]);
            $parentRow = $parent->fetch();
            if (!$parentRow || (int) $parentRow['level'] !== 1 || $parentId === 0) {
                $this->fail('/admin/regions', ['parent_id' => 'Tỉnh/vùng phải thuộc một miền cấp 1.']);
            }
        }
        $statement = Database::connection()->prepare(
            'UPDATE regions SET parent_id = :parent_id, name = :name, slug = :slug,
                description = :description, is_active = :is_active WHERE id = :id'
        );
        $this->writeStatement($statement, [
            'parent_id' => (int) $current['level'] === 1 ? null : $parentId,
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'is_active' => $this->postChecked('is_active') ? 1 : 0,
            'id' => $regionId,
        ], 'region_updated', 'region', $regionId);
        flash('success', 'Đã cập nhật vùng miền.');
        redirect('/admin/regions');
    }

    public function deleteRegion(string $id): void
    {
        $regionId = (int) $id;
        $region = $this->findRow('regions', $regionId);
        $counts = [];
        foreach ([
            'tỉnh/vùng trực thuộc' => ['SELECT COUNT(*) FROM regions WHERE parent_id = :id', []],
            'sản phẩm' => ['SELECT COUNT(*) FROM products WHERE region_id = :id', []],
            'nông trại' => ['SELECT COUNT(*) FROM farms WHERE region_id = :id', []],
            'địa chỉ khách hàng' => ['SELECT COUNT(*) FROM addresses WHERE region_id = :id', []],
            'mức phí vận chuyển' => ['SELECT COUNT(*) FROM shipping_fees WHERE region_id = :id', []],
        ] as $label => [$sql]) {
            $statement = Database::connection()->prepare($sql);
            $statement->execute(['id' => $regionId]);
            $count = (int) $statement->fetchColumn();
            if ($count > 0) {
                $counts[] = $count . ' ' . $label;
            }
        }
        if ($counts !== []) {
            flash('error', 'Không thể xóa ' . $region['name'] . ': còn ' . implode(', ', $counts) . '.');
            redirect('/admin/regions');
        }
        $statement = Database::connection()->prepare('DELETE FROM regions WHERE id = :id');
        $this->writeStatement($statement, ['id' => $regionId], 'region_deleted', 'region', $regionId, $region['name']);
        flash('success', 'Đã xóa vùng miền.');
        redirect('/admin/regions');
    }

    public function categories(): void
    {
        $categories = Database::connection()->query(
            'SELECT c.*, p.name AS parent_name, COUNT(DISTINCT child.id) AS child_count,
                    COUNT(DISTINCT product.id) AS product_count
             FROM categories c
             LEFT JOIN categories p ON p.id = c.parent_id
             LEFT JOIN categories child ON child.parent_id = c.id
             LEFT JOIN products product ON product.category_id = c.id
             GROUP BY c.id ORDER BY c.parent_id, c.sort_order, c.name'
        )->fetchAll();
        $this->view('admin/categories', [
            'title' => 'Danh mục',
            'categories' => $categories,
            'parents' => Database::connection()->query('SELECT id, name FROM categories ORDER BY sort_order, name')->fetchAll(),
            'canManage' => Auth::can('categories.manage'),
            'menu' => $this->menu(),
            'errors' => form_errors(),
            'success' => flash('success'),
            'error' => flash('error'),
        ], 'admin');
    }

    public function storeCategory(): void
    {
        $name = $this->postText('name');
        $parentId = $this->postId('parent_id');
        $slug = $this->uniqueSlug('categories', $this->postText('slug') ?: $name);
        if ($name === '' || mb_strlen($name, 'UTF-8') > 120) {
            $this->fail('/admin/categories', ['name' => 'Tên danh mục bắt buộc, tối đa 120 ký tự.']);
        }
        if ($slug === '' || mb_strlen($slug, 'UTF-8') > 140) {
            $this->fail('/admin/categories', ['slug' => 'Slug danh mục không hợp lệ hoặc dài hơn 140 ký tự.']);
        }
        if ($parentId > 0) {
            $this->findRow('categories', $parentId);
        }
        $statement = Database::connection()->prepare(
            'INSERT INTO categories (parent_id, name, slug, description, sort_order, is_active)
             VALUES (:parent_id, :name, :slug, :description, :sort_order, :is_active)'
        );
        $this->writeStatement($statement, [
            'parent_id' => $parentId ?: null,
            'name' => $name,
            'slug' => $slug,
            'description' => $this->postText('description'),
            'sort_order' => $this->sortOrder(),
            'is_active' => $this->postChecked('is_active') ? 1 : 0,
        ], 'category_created', 'category');
        flash('success', 'Đã thêm danh mục.');
        redirect('/admin/categories');
    }

    public function updateCategory(string $id): void
    {
        $categoryId = (int) $id;
        $this->findRow('categories', $categoryId);
        $name = $this->postText('name');
        $parentId = $this->postId('parent_id');
        $slug = $this->uniqueSlug('categories', $this->postText('slug') ?: $name, $categoryId);
        if ($name === '' || mb_strlen($name, 'UTF-8') > 120) {
            $this->fail('/admin/categories', ['name' => 'Tên danh mục bắt buộc, tối đa 120 ký tự.']);
        }
        if ($slug === '' || mb_strlen($slug, 'UTF-8') > 140) {
            $this->fail('/admin/categories', ['slug' => 'Slug danh mục không hợp lệ hoặc dài hơn 140 ký tự.']);
        }
        if ($parentId === $categoryId) {
            $this->fail('/admin/categories', ['parent_id' => 'Danh mục không thể là cha của chính nó.']);
        }
        if ($parentId > 0) {
            $ancestorId = $parentId;
            $visited = [];
            while ($ancestorId > 0) {
                if ($ancestorId === $categoryId) {
                    $this->fail('/admin/categories', ['parent_id' => 'Không thể tạo quan hệ vòng giữa danh mục cha/con.']);
                }
                if (isset($visited[$ancestorId])) {
                    $this->fail('/admin/categories', ['parent_id' => 'Cây danh mục đang có quan hệ vòng; không thể cập nhật.']);
                }
                $visited[$ancestorId] = true;
                $ancestor = $this->findRow('categories', $ancestorId);
                $ancestorId = (int) ($ancestor['parent_id'] ?? 0);
            }
        }
        $statement = Database::connection()->prepare(
            'UPDATE categories SET parent_id = :parent_id, name = :name, slug = :slug,
                description = :description, sort_order = :sort_order, is_active = :is_active WHERE id = :id'
        );
        $this->writeStatement($statement, [
            'parent_id' => $parentId ?: null,
            'name' => $name,
            'slug' => $slug,
            'description' => $this->postText('description'),
            'sort_order' => $this->sortOrder(),
            'is_active' => $this->postChecked('is_active') ? 1 : 0,
            'id' => $categoryId,
        ], 'category_updated', 'category', $categoryId);
        flash('success', 'Đã cập nhật danh mục.');
        redirect('/admin/categories');
    }

    public function deleteCategory(string $id): void
    {
        $categoryId = (int) $id;
        $category = $this->findRow('categories', $categoryId);
        $productCount = $this->countFor('products', 'category_id', $categoryId);
        $childCount = $this->countFor('categories', 'parent_id', $categoryId);
        if ($productCount || $childCount) {
            $reason = [];
            if ($productCount) {
                $reason[] = $productCount . ' sản phẩm';
            }
            if ($childCount) {
                $reason[] = $childCount . ' danh mục con';
            }
            flash('error', 'Không thể xóa ' . $category['name'] . ': còn ' . implode(', ', $reason) . '.');
            redirect('/admin/categories');
        }
        $statement = Database::connection()->prepare('DELETE FROM categories WHERE id = :id');
        $this->writeStatement($statement, ['id' => $categoryId], 'category_deleted', 'category', $categoryId, $category['name']);
        flash('success', 'Đã xóa danh mục.');
        redirect('/admin/categories');
    }

    public function products(): void
    {
        $query = trim((string) ($_GET['q'] ?? ''));
        $categoryId = filter_var($_GET['category'] ?? '', FILTER_VALIDATE_INT) ?: 0;
        $regionId = filter_var($_GET['region'] ?? '', FILTER_VALIDATE_INT) ?: 0;
        $status = (string) ($_GET['status'] ?? '');
        $statuses = ['draft', 'active', 'inactive'];
        if (!in_array($status, $statuses, true)) {
            $status = '';
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $where = ['p.deleted_at IS NULL'];
        $params = [];
        if ($query !== '') {
            $where[] = '(p.name LIKE :name_query OR p.sku LIKE :sku_query)';
            $params['name_query'] = '%' . $query . '%';
            $params['sku_query'] = '%' . $query . '%';
        }
        if ($categoryId > 0) {
            $where[] = 'p.category_id = :category_id';
            $params['category_id'] = $categoryId;
        }
        if ($regionId > 0) {
            $where[] = 'p.region_id = :region_id';
            $params['region_id'] = $regionId;
        }
        if ($status !== '') {
            $where[] = 'p.status = :status';
            $params['status'] = $status;
        }
        $predicate = implode(' AND ', $where);
        $count = Database::connection()->prepare('SELECT COUNT(*) FROM products p WHERE ' . $predicate);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $limit = 15;
        $pages = max(1, (int) ceil($total / $limit));
        $page = min($page, $pages);
        $statement = Database::connection()->prepare(
            'SELECT p.id, p.name, p.sku, p.price, p.sale_price, COALESCE(stock.quantity, 0) AS total_stock, p.status,
                    p.is_featured, c.name AS category_name, r.name AS region_name,
                    i.image_url
             FROM products p
             JOIN categories c ON c.id = p.category_id
             JOIN regions r ON r.id = p.region_id
             LEFT JOIN (
                 SELECT product_id, SUM(quantity_remaining) AS quantity
                 FROM product_batches
                 WHERE inspection_result <> \'failed\' AND (expires_at IS NULL OR expires_at >= CURDATE())
                 GROUP BY product_id
             ) stock ON stock.product_id = p.id
             LEFT JOIN (
                 SELECT product_id, SUBSTRING_INDEX(GROUP_CONCAT(image_url ORDER BY is_primary DESC, sort_order, id), \',\', 1) AS image_url
                 FROM product_images GROUP BY product_id
             ) i ON i.product_id = p.id
             WHERE ' . $predicate . '
             ORDER BY p.created_at DESC, p.id DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $statement->bindValue(':' . $key, $value);
        }
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * $limit, PDO::PARAM_INT);
        $statement->execute();
        $this->view('admin/products', [
            'title' => 'Sản phẩm',
            'products' => $statement->fetchAll(),
            'categories' => Database::connection()->query('SELECT id, name FROM categories ORDER BY sort_order, name')->fetchAll(),
            'regions' => Database::connection()->query('SELECT id, name FROM regions WHERE level = 2 ORDER BY name')->fetchAll(),
            'query' => $query,
            'categoryId' => $categoryId,
            'regionId' => $regionId,
            'status' => $status,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'canManage' => Auth::can('products.manage'),
            'menu' => $this->menu(),
            'success' => flash('success'),
            'error' => flash('error'),
        ], 'admin');
    }

    public function newProduct(): void
    {
        $this->productForm(null);
    }

    public function editProduct(string $id): void
    {
        $this->productForm((int) $id);
    }

    public function storeProduct(): void
    {
        $this->saveProduct(null);
    }

    public function updateProduct(string $id): void
    {
        $this->saveProduct((int) $id);
    }

    public function deleteProduct(string $id): void
    {
        $productId = (int) $id;
        $product = $this->findProduct($productId);
        $database = Database::connection();
        $database->beginTransaction();
        try {
            $database->prepare('UPDATE products SET deleted_at = NOW(), status = \'inactive\' WHERE id = :id')
                ->execute(['id' => $productId]);
            AuditLog::record((int) Auth::user()['id'], 'product_soft_deleted', 'product', $productId, $product['name']);
            $database->commit();
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
        flash('success', 'Đã ẩn sản phẩm khỏi cửa hàng (xóa mềm; dữ liệu đơn hàng/lô được giữ nguyên).');
        redirect('/admin/products');
    }

    public function deleteImage(string $imageId): void
    {
        $image = $this->findImage((int) $imageId);
        $database = Database::connection();
        $database->beginTransaction();
        try {
            $database->prepare('DELETE FROM product_images WHERE id = :id')->execute(['id' => $imageId]);
            $remaining = $database->prepare(
                'SELECT id FROM product_images WHERE product_id = :product_id ORDER BY is_primary DESC, sort_order, id LIMIT 1'
            );
            $remaining->execute(['product_id' => $image['product_id']]);
            $remainingId = $remaining->fetchColumn();
            if ($remainingId !== false) {
                $database->prepare('UPDATE product_images SET is_primary = 0 WHERE product_id = :product_id')
                    ->execute(['product_id' => $image['product_id']]);
                $database->prepare('UPDATE product_images SET is_primary = 1 WHERE id = :id')
                    ->execute(['id' => $remainingId]);
            }
            AuditLog::record((int) Auth::user()['id'], 'product_image_deleted', 'product', (int) $image['product_id']);
            $database->commit();
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
        $this->removeStoredImage($image['image_url']);
        flash('success', 'Đã xóa ảnh sản phẩm.');
        redirect('/admin/products/' . $image['product_id'] . '/edit');
    }

    public function reorderImages(string $id): void
    {
        $product = $this->findProduct((int) $id);
        $imageIds = $_POST['image_ids'] ?? [];
        if (!is_array($imageIds)) {
            flash('error', 'Thứ tự ảnh không hợp lệ.');
            redirect('/admin/products/' . $id . '/edit');
        }
        $imageIds = array_values(array_unique(array_filter(array_map('intval', $imageIds))));
        $database = Database::connection();
        $database->beginTransaction();
        try {
            $ownedQuery = $database->prepare('SELECT id FROM product_images WHERE product_id = :product_id');
            $ownedQuery->execute(['product_id' => $id]);
            $ownedIds = array_map('intval', $ownedQuery->fetchAll(PDO::FETCH_COLUMN));
            sort($ownedIds);
            $checkIds = $imageIds;
            sort($checkIds);
            if ($checkIds !== $ownedIds) {
                throw new InvalidArgumentException('Danh sách ảnh không khớp với sản phẩm.');
            }
            $update = $database->prepare('UPDATE product_images SET sort_order = :sort_order, is_primary = :is_primary WHERE id = :id AND product_id = :product_id');
            foreach ($imageIds as $index => $imageId) {
                $update->execute([
                    'sort_order' => $index,
                    'is_primary' => $index === 0 ? 1 : 0,
                    'id' => $imageId,
                    'product_id' => $id,
                ]);
            }
            AuditLog::record((int) Auth::user()['id'], 'product_images_reordered', 'product', (int) $id, $product['name']);
            $database->commit();
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            if ($exception instanceof InvalidArgumentException) {
                flash('error', $exception->getMessage());
                redirect('/admin/products/' . $id . '/edit');
            }
            throw $exception;
        }
        flash('success', 'Đã lưu thứ tự ảnh; ảnh đầu tiên là ảnh đại diện.');
        redirect('/admin/products/' . $id . '/edit');
    }

    private function saveProduct(?int $productId): void
    {
        if ($productId !== null) {
            $this->findProduct($productId);
        }
        $input = [
            'name' => $this->postText('name'),
            'slug' => $this->postText('slug'),
            'sku' => strtoupper($this->postText('sku')),
            'category_id' => $this->postId('category_id'),
            'region_id' => $this->postId('region_id'),
            'farm_id' => $this->postId('farm_id'),
            'unit' => $this->postText('unit'),
            'price' => $this->postText('price'),
            'sale_price' => $this->postText('sale_price'),
            'short_description' => $this->postText('short_description'),
            'description' => $this->postText('description'),
            'storage_instructions' => $this->postText('storage_instructions'),
            'certifications' => $this->postText('certifications'),
            'status' => $this->postText('status') ?: 'draft',
            'is_featured' => $this->postChecked('is_featured') ? 1 : 0,
            'suggested_shelf_life_days' => $this->postText('suggested_shelf_life_days'),
        ];
        $input['slug'] = $input['slug'] !== '' ? CatalogSlug::from($input['slug']) : CatalogSlug::from($input['name']);
        $errors = [];
        if ($input['name'] === '' || mb_strlen($input['name'], 'UTF-8') > 200) {
            $errors['name'] = 'Tên sản phẩm bắt buộc, tối đa 200 ký tự.';
        }
        if ($input['slug'] === '' || mb_strlen($input['slug'], 'UTF-8') > 220) {
            $errors['slug'] = 'Slug không hợp lệ.';
        } else {
            $statement = Database::connection()->prepare(
                'SELECT id FROM products WHERE slug = :slug AND (:id = 0 OR id <> :exclude_id) LIMIT 1'
            );
            $statement->execute(['slug' => $input['slug'], 'id' => $productId ?? 0, 'exclude_id' => $productId ?? 0]);
            if ($statement->fetch()) {
                $errors['slug'] = 'Slug đã được dùng.';
            }
        }
        if (!preg_match('/\A[A-Za-z0-9._-]{2,80}\z/', $input['sku'])) {
            $errors['sku'] = 'SKU chỉ gồm chữ, số, dấu chấm, gạch ngang hoặc gạch dưới (2-80 ký tự).';
        } else {
            $statement = Database::connection()->prepare(
                'SELECT id FROM products WHERE sku = :sku AND (:id = 0 OR id <> :exclude_id) LIMIT 1'
            );
            $statement->execute(['sku' => $input['sku'], 'id' => $productId ?? 0, 'exclude_id' => $productId ?? 0]);
            if ($statement->fetch()) {
                $errors['sku'] = 'SKU đã được dùng.';
            }
        }
        $price = $this->parsePrice($input['price']);
        if ($price === null || $price <= 0) {
            $errors['price'] = 'Giá bán phải là số lớn hơn 0.';
        }
        $salePrice = $input['sale_price'] === '' ? null : $this->parsePrice($input['sale_price']);
        if ($input['sale_price'] !== '' && ($salePrice === null || $salePrice <= 0 || ($price !== null && $salePrice >= $price))) {
            $errors['sale_price'] = 'Giá khuyến mãi phải lớn hơn 0 và thấp hơn giá bán.';
        }
        if ($input['unit'] === '' || mb_strlen($input['unit'], 'UTF-8') > 40) {
            $errors['unit'] = 'Đơn vị tính là bắt buộc, tối đa 40 ký tự.';
        }
        if (!in_array($input['status'], ['draft', 'active', 'inactive'], true)) {
            $errors['status'] = 'Trạng thái sản phẩm không hợp lệ.';
        }
        foreach (['category_id' => 'categories', 'region_id' => 'regions', 'farm_id' => 'farms'] as $field => $table) {
            if (!$this->exists($table, $input[$field])) {
                $errors[$field] = 'Vui lòng chọn dữ liệu hợp lệ.';
            }
        }
        if ($input['region_id'] > 0 && $input['farm_id'] > 0) {
            $farmRegion = Database::connection()->prepare('SELECT region_id FROM farms WHERE id = :id');
            $farmRegion->execute(['id' => $input['farm_id']]);
            if ((int) $farmRegion->fetchColumn() !== $input['region_id']) {
                $errors['farm_id'] = 'Nông trại phải thuộc đúng tỉnh/vùng đã chọn.';
            }
        }
        if (mb_strlen($input['short_description'], 'UTF-8') > 500) {
            $errors['short_description'] = 'Mô tả ngắn tối đa 500 ký tự.';
        }
        $shelfLife = $input['suggested_shelf_life_days'] === '' ? null : filter_var($input['suggested_shelf_life_days'], FILTER_VALIDATE_INT);
        if ($input['suggested_shelf_life_days'] !== '' && ($shelfLife === false || $shelfLife < 0 || $shelfLife > 65535)) {
            $errors['suggested_shelf_life_days'] = 'Hạn dùng gợi ý phải là số ngày hợp lệ.';
        }
        if ($errors !== []) {
            remember_form_data($input);
            flash('validation_errors', $errors);
            redirect($productId === null ? '/admin/products/new' : '/admin/products/' . $productId . '/edit');
        }

        try {
            $newFiles = $this->storeUploadedFiles();
        } catch (RuntimeException $exception) {
            remember_form_data($input);
            flash('error', $exception->getMessage());
            redirect($productId === null ? '/admin/products/new' : '/admin/products/' . $productId . '/edit');
        }
        $database = Database::connection();
        $database->beginTransaction();
        try {
            $values = [
                'category_id' => $input['category_id'],
                'region_id' => $input['region_id'],
                'farm_id' => $input['farm_id'],
                'name' => $input['name'],
                'slug' => $input['slug'],
                'sku' => $input['sku'],
                'unit' => $input['unit'],
                'price' => $price,
                'sale_price' => $salePrice,
                'suggested_shelf_life_days' => $shelfLife,
                'storage_instructions' => $input['storage_instructions'],
                'certifications' => $input['certifications'],
                'short_description' => $input['short_description'],
                'status' => $input['status'],
                'is_featured' => $input['is_featured'],
                'description' => $input['description'],
            ];
            if ($productId === null) {
                $statement = $database->prepare(
                    'INSERT INTO products
                     (category_id, region_id, farm_id, name, slug, sku, unit, price, sale_price,
                      suggested_shelf_life_days, storage_instructions, certifications, short_description,
                      status, is_featured, description)
                     VALUES
                     (:category_id, :region_id, :farm_id, :name, :slug, :sku, :unit, :price, :sale_price,
                      :suggested_shelf_life_days, :storage_instructions, :certifications, :short_description,
                      :status, :is_featured, :description)'
                );
                $statement->execute($values);
                $productId = (int) $database->lastInsertId();
                $action = 'product_created';
            } else {
                $statement = $database->prepare(
                    'UPDATE products SET category_id = :category_id, region_id = :region_id, farm_id = :farm_id,
                        name = :name, slug = :slug, sku = :sku, unit = :unit, price = :price, sale_price = :sale_price,
                        suggested_shelf_life_days = :suggested_shelf_life_days, storage_instructions = :storage_instructions,
                        certifications = :certifications, short_description = :short_description, status = :status,
                        is_featured = :is_featured, description = :description WHERE id = :id AND deleted_at IS NULL'
                );
                $values['id'] = $productId;
                $statement->execute($values);
                $action = 'product_updated';
            }
            foreach ($newFiles as $index => $file) {
                $database->prepare(
                    'INSERT INTO product_images (product_id, image_url, alt_text, sort_order, is_primary)
                     VALUES (:product_id, :image_url, :alt_text, :sort_order, :is_primary)'
                )->execute([
                    'product_id' => $productId,
                    'image_url' => $file['url'],
                    'alt_text' => $input['name'],
                    'sort_order' => $index + 100,
                    'is_primary' => $index === 0 && !$this->hasPrimaryImage($productId) ? 1 : 0,
                ]);
            }
            AuditLog::record((int) Auth::user()['id'], $action, 'product', $productId, $input['sku']);
            $database->commit();
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            foreach ($newFiles as $file) {
                if (is_file($file['path']) && !unlink($file['path'])) {
                    error_log('Không thể dọn ảnh tải lên sau lỗi lưu sản phẩm: ' . $file['path']);
                }
            }
            if ($exception instanceof PDOException && $exception->getCode() === '23000') {
                remember_form_data($input);
                flash('error', 'Slug hoặc SKU đã được sản phẩm khác sử dụng.');
                redirect($productId === null ? '/admin/products/new' : '/admin/products/' . $productId . '/edit');
            }
            throw $exception;
        }
        flash('success', 'Đã lưu sản phẩm và ảnh.');
        redirect('/admin/products/' . $productId . '/edit');
    }

    private function productForm(?int $productId): void
    {
        $product = $productId === null ? null : $this->findProduct($productId);
        $statement = Database::connection()->query(
            'SELECT id, parent_id, name FROM categories WHERE is_active = 1 ORDER BY parent_id, sort_order, name'
        );
        $categories = $statement->fetchAll();
        $this->view('admin/product-form', [
            'title' => $product ? 'Sửa sản phẩm' : 'Thêm sản phẩm',
            'product' => $product,
            'categories' => $categories,
            'regions' => Database::connection()->query('SELECT id, name FROM regions WHERE level = 2 AND is_active = 1 ORDER BY name')->fetchAll(),
            'farms' => Database::connection()->query('SELECT id, name, region_id FROM farms WHERE status = \'active\' ORDER BY name')->fetchAll(),
            'images' => $product ? $this->productImages($productId) : [],
            'errors' => form_errors(),
            'success' => flash('success'),
            'error' => flash('error'),
            'menu' => $this->menu(),
        ], 'admin');
    }

    private function storeUploadedFiles(): array
    {
        if (!isset($_FILES['images']) || !is_array($_FILES['images']['name'] ?? null)) {
            return [];
        }
        $files = $_FILES['images'];
        $stored = [];
        $allowed = [
            'image/jpeg' => ['jpg', 'imagecreatefromjpeg'],
            'image/png' => ['png', 'imagecreatefrompng'],
            'image/webp' => ['webp', 'imagecreatefromwebp'],
        ];
        $directory = ROOT_PATH . '/uploads/products';
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Không thể tạo thư mục tải ảnh sản phẩm.');
        }
        foreach ($files['name'] as $index => $originalName) {
            $error = (int) ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($error !== UPLOAD_ERR_OK) {
                $this->removeUploadedFiles($stored);
                throw new RuntimeException('Tải ảnh "' . basename((string) $originalName) . '" thất bại (mã ' . $error . ').');
            }
            $temporary = (string) $files['tmp_name'][$index];
            $size = (int) $files['size'][$index];
            if ($size <= 0 || $size > 2 * 1024 * 1024) {
                $this->removeUploadedFiles($stored);
                throw new RuntimeException('Mỗi ảnh phải có dung lượng tối đa 2 MB.');
            }
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($temporary);
            if (!isset($allowed[$mime])) {
                $this->removeUploadedFiles($stored);
                throw new RuntimeException('Chỉ chấp nhận ảnh JPEG, PNG hoặc WebP.');
            }
            $imageInfo = getimagesize($temporary);
            if ($imageInfo === false || ($imageInfo['mime'] ?? '') !== $mime) {
                $this->removeUploadedFiles($stored);
                throw new RuntimeException('Tệp tải lên không phải ảnh hợp lệ.');
            }
            $filename = bin2hex(random_bytes(20)) . '.' . $allowed[$mime][0];
            $path = $directory . '/' . $filename;
            if (!move_uploaded_file($temporary, $path)) {
                $this->removeUploadedFiles($stored);
                throw new RuntimeException('Không thể lưu ảnh tải lên.');
            }
            try {
                $this->resizeImage($path, $mime, $allowed[$mime][1]);
            } catch (Throwable $exception) {
                @unlink($path);
                $this->removeUploadedFiles($stored);
                throw $exception;
            }
            $stored[] = ['path' => $path, 'url' => '/uploads/products/' . $filename];
        }
        return $stored;
    }

    private function resizeImage(string $path, string $mime, string $decoder): void
    {
        if (!extension_loaded('gd') || !function_exists($decoder)) {
            return;
        }
        $source = $decoder($path);
        if ($source === false) {
            throw new RuntimeException('Không thể giải mã ảnh để xử lý.');
        }
        try {
            $width = imagesx($source);
            $height = imagesy($source);
            $longest = max($width, $height);
            if ($longest <= 1000) {
                return;
            }
            $scale = 1000 / $longest;
            $newWidth = max(1, (int) round($width * $scale));
            $newHeight = max(1, (int) round($height * $scale));
            $target = imagecreatetruecolor($newWidth, $newHeight);
            if ($mime === 'image/png' || $mime === 'image/webp') {
                imagealphablending($target, false);
                imagesavealpha($target, true);
                $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
                imagefill($target, 0, 0, $transparent);
            }
            imagecopyresampled($target, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            $saved = match ($mime) {
                'image/jpeg' => imagejpeg($target, $path, 88),
                'image/png' => imagepng($target, $path, 7),
                'image/webp' => imagewebp($target, $path, 88),
                default => false,
            };
            imagedestroy($target);
            if (!$saved) {
                throw new RuntimeException('Không thể thu nhỏ hoặc lưu ảnh.');
            }
        } finally {
            imagedestroy($source);
        }
    }

    private function productImages(int $productId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, image_url, alt_text, sort_order, is_primary
             FROM product_images WHERE product_id = :product_id
             ORDER BY is_primary DESC, sort_order, id'
        );
        $statement->execute(['product_id' => $productId]);
        return $statement->fetchAll();
    }

    private function hasPrimaryImage(int $productId): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) FROM product_images WHERE product_id = :product_id AND is_primary = 1'
        );
        $statement->execute(['product_id' => $productId]);
        return (int) $statement->fetchColumn() > 0;
    }

    private function findProduct(int $id): array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM products WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $product = $statement->fetch();
        if (!$product) {
            http_response_code(404);
            View::renderError(404);
            exit;
        }
        return $product;
    }

    private function findImage(int $id): array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, product_id, image_url FROM product_images WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $image = $statement->fetch();
        if (!$image) {
            http_response_code(404);
            View::renderError(404);
            exit;
        }
        return $image;
    }

    private function removeStoredImage(string $url): void
    {
        if (!str_starts_with($url, '/uploads/products/')) {
            return;
        }
        $path = ROOT_PATH . $url;
        if (is_file($path) && !unlink($path)) {
            throw new RuntimeException('Không thể xóa tệp ảnh khỏi ổ đĩa: ' . basename($path));
        }
    }

    private function removeUploadedFiles(array $files): void
    {
        foreach ($files as $file) {
            if (is_file($file['path']) && !unlink($file['path'])) {
                error_log('Không thể dọn ảnh tải lên sau lỗi: ' . $file['path']);
            }
        }
    }

    private function findRow(string $table, int $id): array
    {
        if (!in_array($table, ['regions', 'categories'], true)) {
            throw new InvalidArgumentException('Bảng danh mục không hợp lệ.');
        }
        $statement = Database::connection()->prepare('SELECT * FROM `' . $table . '` WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        if (!$row) {
            http_response_code(404);
            View::renderError(404);
            exit;
        }
        return $row;
    }

    private function exists(string $table, int $id): bool
    {
        if (!in_array($table, ['categories', 'regions', 'farms'], true) || $id <= 0) {
            return false;
        }
        $conditions = [
            'categories' => 'is_active = 1',
            'regions' => 'is_active = 1 AND level = 2',
            'farms' => "status = 'active'",
        ];
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) FROM `' . $table . '` WHERE id = :id AND ' . $conditions[$table]
        );
        $statement->execute(['id' => $id]);
        return (int) $statement->fetchColumn() > 0;
    }

    private function countFor(string $table, string $column, int $id): int
    {
        $allowed = ['products.category_id', 'categories.parent_id'];
        if (!in_array($table . '.' . $column, $allowed, true)) {
            throw new InvalidArgumentException('Điều kiện đếm không hợp lệ.');
        }
        $statement = Database::connection()->prepare('SELECT COUNT(*) FROM `' . $table . '` WHERE `' . $column . '` = :id');
        $statement->execute(['id' => $id]);
        return (int) $statement->fetchColumn();
    }

    private function uniqueSlug(string $table, string $name, ?int $excludeId = null): string
    {
        if (!in_array($table, ['regions', 'categories'], true)) {
            throw new InvalidArgumentException('Bảng tạo slug không hợp lệ.');
        }
        $base = CatalogSlug::from($name);
        if ($base === '') {
            return '';
        }
        $candidate = $base;
        for ($suffix = 2; ; $suffix++) {
            $sql = 'SELECT id FROM `' . $table . '` WHERE slug = :slug'
                . ($excludeId === null ? '' : ' AND id <> :id') . ' LIMIT 1';
            $statement = Database::connection()->prepare($sql);
            $parameters = ['slug' => $candidate];
            if ($excludeId !== null) {
                $parameters['id'] = $excludeId;
            }
            $statement->execute($parameters);
            if (!$statement->fetch()) {
                return $candidate;
            }
            $candidate = $base . '-' . $suffix;
        }
    }

    private function parsePrice(string $value): ?float
    {
        if (!preg_match('/\A(?:[0-9]+)(?:\.[0-9]{1,2})?\z/', $value)) {
            return null;
        }
        $price = (float) $value;
        return $price <= 9999999999.99 ? $price : null;
    }

    private function postText(string $key): string
    {
        $value = $_POST[$key] ?? '';
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function postId(string $key): int
    {
        $value = filter_var($_POST[$key] ?? 0, FILTER_VALIDATE_INT);
        return $value === false ? 0 : (int) $value;
    }

    private function postChecked(string $key): bool
    {
        $value = $_POST[$key] ?? null;
        return is_scalar($value) && in_array((string) $value, ['1', 'on', 'true'], true);
    }

    private function sortOrder(): int
    {
        $value = filter_var($_POST['sort_order'] ?? 0, FILTER_VALIDATE_INT);
        if ($value === false || $value < -32768 || $value > 32767) {
            $this->fail('/admin/categories', ['sort_order' => 'Thứ tự sắp xếp không hợp lệ.']);
        }
        return $value;
    }

    private function fail(string $path, array $errors): never
    {
        remember_form_data($_POST);
        flash('validation_errors', $errors);
        redirect($path);
    }

    private function writeRegion(string $sql, array $parameters, ?int $entityId, string $action): void
    {
        $database = Database::connection();
        $database->beginTransaction();
        try {
            $statement = $database->prepare($sql);
            $statement->execute($parameters);
            $entityId ??= (int) $database->lastInsertId();
            AuditLog::record((int) Auth::user()['id'], $action, 'region', $entityId, $parameters['slug']);
            $database->commit();
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    private function writeStatement(
        PDOStatement $statement,
        array $parameters,
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?string $details = null
    ): void {
        $database = Database::connection();
        $database->beginTransaction();
        try {
            $statement->execute($parameters);
            $entityId ??= (int) $database->lastInsertId();
            AuditLog::record((int) Auth::user()['id'], $action, $entityType, $entityId, $details ?? ($parameters['slug'] ?? null));
            $database->commit();
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    private function menu(): array
    {
        $items = [
            ['permission' => 'dashboard.view', 'label' => 'Bảng điều khiển', 'url' => '/admin'],
            ['permission' => 'regions.view', 'label' => 'Vùng miền', 'url' => '/admin/regions'],
            ['permission' => 'categories.view', 'label' => 'Danh mục', 'url' => '/admin/categories'],
            ['permission' => 'products.view', 'label' => 'Sản phẩm', 'url' => '/admin/products'],
            ['permission' => 'orders.view', 'label' => 'Đơn hàng', 'url' => '/admin/orders'],
            ['permission' => 'vouchers.view', 'label' => 'Voucher', 'url' => '/admin/vouchers'],
            ['permission' => 'reviews.view', 'label' => 'Đánh giá', 'url' => '/admin/reviews'],
            ['permission' => 'customers.view', 'label' => 'Khách hàng', 'url' => '/admin/customers'],
            ['permission' => 'inventory.view', 'label' => 'Kho hàng', 'url' => '/admin/inventory'],
            ['permission' => 'batches.view', 'label' => 'Lô hàng', 'url' => '/admin/batches'],
            ['permission' => 'users.view', 'label' => 'Người dùng', 'url' => '/admin/users'],
        ];
        return array_values(array_filter($items, static fn (array $item): bool => Auth::can($item['permission'])));
    }
}
