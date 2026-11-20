<?php

declare(strict_types=1);

final class CatalogAdminController extends Controller
{
    private const ADMIN_LIST_PAGE_SIZE = 20;

    public function regions(): void
    {
        $database = Database::connection();
        $total = (int) $database->query(
            'SELECT COUNT(*) FROM regions AS region
             WHERE NOT EXISTS (
                 SELECT 1 FROM region_aliases AS alias
                 WHERE alias.legacy_region_id = region.id
                   AND alias.region_id <> alias.legacy_region_id
             )'
        )->fetchColumn();
        $pages = max(1, (int) ceil($total / self::ADMIN_LIST_PAGE_SIZE));
        $requestedPage = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
        $page = min(max(1, $requestedPage === false ? 1 : $requestedPage), $pages);
        $regionQuery = $database->prepare(
            'SELECT r.*, COUNT(DISTINCT child.id) AS child_count,
                    COUNT(DISTINCT p.id) AS product_count,
                    COUNT(DISTINCT f.id) AS farm_count
             FROM regions r
             LEFT JOIN regions child ON child.parent_id = r.id AND child.is_active = 1
             LEFT JOIN products p ON p.region_id = r.id
             LEFT JOIN farms f ON f.region_id = r.id
             WHERE NOT EXISTS (
                 SELECT 1 FROM region_aliases AS alias
                 WHERE alias.legacy_region_id = r.id
                   AND alias.region_id <> alias.legacy_region_id
             )
             GROUP BY r.id ORDER BY r.level, r.parent_id, r.name
             LIMIT :limit OFFSET :offset'
        );
        $regionQuery->bindValue(':limit', self::ADMIN_LIST_PAGE_SIZE, PDO::PARAM_INT);
        $regionQuery->bindValue(':offset', ($page - 1) * self::ADMIN_LIST_PAGE_SIZE, PDO::PARAM_INT);
        $regionQuery->execute();
        $regions = $regionQuery->fetchAll();
        $this->view('admin/regions', [
            'title' => 'Vùng miền',
            'regions' => $regions,
            'regionOptions' => $database->query(
                'SELECT region.id, region.name, region.level, region.parent_id FROM regions AS region
                 WHERE NOT EXISTS (
                     SELECT 1 FROM region_aliases AS alias
                     WHERE alias.legacy_region_id = region.id
                       AND alias.region_id <> alias.legacy_region_id
                 )
                 ORDER BY region.level, region.parent_id, region.name'
            )->fetchAll(),
            'page' => $page,
            'pages' => $pages,
            'paginationUrl' => '/admin/regions',
            'paginationQuery' => [],
            'paginationLabel' => 'vùng miền',
            'canManage' => Auth::can('regions.manage'),
            'menu' => $this->menu(),
            'errors' => form_errors(),
            'success' => flash('success'),
            'error' => flash('error'),
        ], 'admin');
    }

    public function productsMissingImages(): void
    {
        $statement = Database::connection()->prepare(
            'SELECT p.id, p.name, p.sku, p.status, images.image_url
             FROM products p
             LEFT JOIN (
                 SELECT product_id,
                        SUBSTRING_INDEX(GROUP_CONCAT(image_url ORDER BY is_primary DESC, sort_order, id), \',\', 1) AS image_url
                 FROM product_images GROUP BY product_id
             ) images ON images.product_id = p.id
             WHERE p.deleted_at IS NULL
             ORDER BY p.name, p.id'
        );
        $statement->execute();
        $products = array_values(array_filter(
            $statement->fetchAll(),
            fn (array $product): bool => !$this->imageFileExists((string) ($product['image_url'] ?? ''))
        ));
        $this->view('admin/products-missing-images', [
            'title' => 'Sản phẩm thiếu ảnh',
            'products' => $products,
            'total' => count($products),
            'canManage' => Auth::can('products.manage'),
            'menu' => $this->menu(),
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
        $storedImage = $this->storeCoverImage('regions', '/admin/regions');
        try {
            $this->writeRegion(
                'INSERT INTO regions (parent_id, name, level, slug, description, image_path, is_active)
                 VALUES (:parent_id, :name, :level, :slug, :description, :image_path, :is_active)',
                [
                    'parent_id' => $parentId ?: null,
                    'name' => $name,
                    'level' => $level,
                    'slug' => $slug,
                    'description' => $description,
                    'image_path' => $storedImage['url'] ?? null,
                    'is_active' => $active,
                ],
                null,
                'region_created'
            );
        } catch (Throwable $exception) {
            $this->discardNewImage($storedImage, 'regions');
            throw $exception;
        }
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
        $storedImage = $this->storeCoverImage('regions', '/admin/regions');
        $imagePath = $storedImage['url'] ?? (
            $this->postChecked('remove_image') ? null : ($current['image_path'] ?? null)
        );
        $statement = Database::connection()->prepare(
            'UPDATE regions SET parent_id = :parent_id, name = :name, slug = :slug,
                description = :description, image_path = :image_path, is_active = :is_active WHERE id = :id'
        );
        try {
            $this->writeStatement($statement, [
                'parent_id' => (int) $current['level'] === 1 ? null : $parentId,
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'image_path' => $imagePath,
                'is_active' => $this->postChecked('is_active') ? 1 : 0,
                'id' => $regionId,
            ], 'region_updated', 'region', $regionId);
        } catch (Throwable $exception) {
            $this->discardNewImage($storedImage, 'regions');
            throw $exception;
        }
        if ($imagePath !== ($current['image_path'] ?? null)) {
            try {
                ImageUploadService::deleteStored((string) ($current['image_path'] ?? ''), 'regions');
            } catch (RuntimeException $exception) {
                error_log('Không thể xóa ảnh vùng miền cũ: ' . $exception->getMessage());
                flash('error', 'Vùng miền đã lưu nhưng không thể xóa ảnh cũ.');
                redirect('/admin/regions');
            }
        }
        flash('success', 'Đã cập nhật vùng miền.');
        redirect('/admin/regions');
    }

    public function deleteRegion(string $id): void
    {
        $regionId = (int) $id;
        $region = $this->findRow('regions', $regionId);
        $counts = [];
        foreach ([
            'tỉnh/vùng trực thuộc' => ['SELECT COUNT(*) FROM regions WHERE parent_id = :id', ['id' => $regionId]],
            'sản phẩm' => ['SELECT COUNT(*) FROM products WHERE region_id = :id', ['id' => $regionId]],
            'nông trại' => ['SELECT COUNT(*) FROM farms WHERE region_id = :id', ['id' => $regionId]],
            'địa chỉ khách hàng' => ['SELECT COUNT(*) FROM addresses WHERE region_id = :id', ['id' => $regionId]],
            'mức phí vận chuyển' => ['SELECT COUNT(*) FROM shipping_fees WHERE region_id = :id', ['id' => $regionId]],
            'ánh xạ tỉnh/thành cũ' => [
                'SELECT COUNT(*) FROM region_aliases
                 WHERE (legacy_region_id = :legacy_id AND region_id <> legacy_region_id)
                    OR (region_id = :current_id AND legacy_region_id <> region_id)',
                ['legacy_id' => $regionId, 'current_id' => $regionId],
            ],
        ] as $label => [$sql, $parameters]) {
            $statement = Database::connection()->prepare($sql);
            $statement->execute($parameters);
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
        try {
            ImageUploadService::deleteStored((string) ($region['image_path'] ?? ''), 'regions');
        } catch (RuntimeException $exception) {
            error_log('Không thể xóa ảnh vùng miền đã xóa: ' . $exception->getMessage());
            flash('error', 'Đã xóa vùng miền nhưng không thể xóa ảnh cũ.');
            redirect('/admin/regions');
        }
        flash('success', 'Đã xóa vùng miền.');
        redirect('/admin/regions');
    }

    public function categories(): void
    {
        $database = Database::connection();
        $total = (int) $database->query('SELECT COUNT(*) FROM categories')->fetchColumn();
        $pages = max(1, (int) ceil($total / self::ADMIN_LIST_PAGE_SIZE));
        $requestedPage = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
        $page = min(max(1, $requestedPage === false ? 1 : $requestedPage), $pages);
        $categoryQuery = $database->prepare(
            'SELECT c.*, p.name AS parent_name, COUNT(DISTINCT child.id) AS child_count,
                    COUNT(DISTINCT product.id) AS product_count
             FROM categories c
             LEFT JOIN categories p ON p.id = c.parent_id
             LEFT JOIN categories child ON child.parent_id = c.id
             LEFT JOIN products product ON product.category_id = c.id
             GROUP BY c.id ORDER BY c.parent_id, c.sort_order, c.name
             LIMIT :limit OFFSET :offset'
        );
        $categoryQuery->bindValue(':limit', self::ADMIN_LIST_PAGE_SIZE, PDO::PARAM_INT);
        $categoryQuery->bindValue(':offset', ($page - 1) * self::ADMIN_LIST_PAGE_SIZE, PDO::PARAM_INT);
        $categoryQuery->execute();
        $categories = $categoryQuery->fetchAll();
        $this->view('admin/categories', [
            'title' => 'Danh mục',
            'categories' => $categories,
            'parents' => $database->query('SELECT id, name FROM categories ORDER BY sort_order, name')->fetchAll(),
            'page' => $page,
            'pages' => $pages,
            'paginationUrl' => '/admin/categories',
            'paginationQuery' => [],
            'paginationLabel' => 'danh mục',
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
        $storedImage = $this->storeCoverImage('categories', '/admin/categories');
        $statement = Database::connection()->prepare(
            'INSERT INTO categories (parent_id, name, slug, description, image_path, sort_order, is_active)
             VALUES (:parent_id, :name, :slug, :description, :image_path, :sort_order, :is_active)'
        );
        try {
            $this->writeStatement($statement, [
                'parent_id' => $parentId ?: null,
                'name' => $name,
                'slug' => $slug,
                'description' => $this->postText('description'),
                'image_path' => $storedImage['url'] ?? null,
                'sort_order' => $this->sortOrder(),
                'is_active' => $this->postChecked('is_active') ? 1 : 0,
            ], 'category_created', 'category');
        } catch (Throwable $exception) {
            $this->discardNewImage($storedImage, 'categories');
            throw $exception;
        }
        flash('success', 'Đã thêm danh mục.');
        redirect('/admin/categories');
    }

    public function updateCategory(string $id): void
    {
        $categoryId = (int) $id;
        $current = $this->findRow('categories', $categoryId);
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
        $storedImage = $this->storeCoverImage('categories', '/admin/categories');
        $imagePath = $storedImage['url'] ?? (
            $this->postChecked('remove_image') ? null : ($current['image_path'] ?? null)
        );
        $statement = Database::connection()->prepare(
            'UPDATE categories SET parent_id = :parent_id, name = :name, slug = :slug,
                description = :description, image_path = :image_path,
                sort_order = :sort_order, is_active = :is_active WHERE id = :id'
        );
        try {
            $this->writeStatement($statement, [
                'parent_id' => $parentId ?: null,
                'name' => $name,
                'slug' => $slug,
                'description' => $this->postText('description'),
                'image_path' => $imagePath,
                'sort_order' => $this->sortOrder(),
                'is_active' => $this->postChecked('is_active') ? 1 : 0,
                'id' => $categoryId,
            ], 'category_updated', 'category', $categoryId);
        } catch (Throwable $exception) {
            $this->discardNewImage($storedImage, 'categories');
            throw $exception;
        }
        if ($imagePath !== ($current['image_path'] ?? null)) {
            try {
                ImageUploadService::deleteStored((string) ($current['image_path'] ?? ''), 'categories');
            } catch (RuntimeException $exception) {
                error_log('Không thể xóa ảnh danh mục cũ: ' . $exception->getMessage());
                flash('error', 'Danh mục đã lưu nhưng không thể xóa ảnh cũ.');
                redirect('/admin/categories');
            }
        }
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
        try {
            ImageUploadService::deleteStored((string) ($category['image_path'] ?? ''), 'categories');
        } catch (RuntimeException $exception) {
            error_log('Không thể xóa ảnh danh mục đã xóa: ' . $exception->getMessage());
            flash('error', 'Đã xóa danh mục nhưng không thể xóa ảnh cũ.');
            redirect('/admin/categories');
        }
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
            $where[] = "(
                REPLACE(REPLACE(
                    p.name COLLATE utf8mb4_unicode_ci,
                    _utf8mb4'đ' COLLATE utf8mb4_unicode_ci,
                    _utf8mb4'd' COLLATE utf8mb4_unicode_ci
                ), _utf8mb4'Đ' COLLATE utf8mb4_unicode_ci, _utf8mb4'd' COLLATE utf8mb4_unicode_ci)
                    COLLATE utf8mb4_unicode_ci LIKE :name_query COLLATE utf8mb4_unicode_ci ESCAPE '='
                OR REPLACE(REPLACE(
                    p.sku COLLATE utf8mb4_unicode_ci,
                    _utf8mb4'đ' COLLATE utf8mb4_unicode_ci,
                    _utf8mb4'd' COLLATE utf8mb4_unicode_ci
                ), _utf8mb4'Đ' COLLATE utf8mb4_unicode_ci, _utf8mb4'd' COLLATE utf8mb4_unicode_ci)
                    COLLATE utf8mb4_unicode_ci LIKE :sku_query COLLATE utf8mb4_unicode_ci ESCAPE '='
            )";
            $normalized = str_replace(['đ', 'Đ'], 'd', $query);
            $escaped = str_replace(['=', '%', '_'], ['==', '=%', '=_'], $normalized);
            $params['name_query'] = '%' . $escaped . '%';
            $params['sku_query'] = '%' . $escaped . '%';
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
            'SELECT p.id, p.name, p.sku, p.price, p.sale_price, p.total_stock, p.min_stock, p.status,
                    p.is_featured, c.name AS category_name, r.name AS region_name,
                    i.image_url
             FROM products p
             JOIN categories c ON c.id = p.category_id
             JOIN regions r ON r.id = p.region_id
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
            $productLock = $database->prepare(
                'SELECT id FROM products WHERE id = :id AND deleted_at IS NULL FOR UPDATE'
            );
            $productLock->execute(['id' => $productId]);
            if (!$productLock->fetchColumn()) {
                throw new RuntimeException('Không tìm thấy sản phẩm để xóa.');
            }
            $imageQuery = $database->prepare('SELECT image_url FROM product_images WHERE product_id = :product_id');
            $imageQuery->execute(['product_id' => $productId]);
            $imageUrls = $imageQuery->fetchAll(PDO::FETCH_COLUMN);
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
        $fileCleanupFailures = 0;
        foreach ($imageUrls as $imageUrl) {
            try {
                $this->removeStoredImage((string) $imageUrl);
            } catch (RuntimeException $exception) {
                $fileCleanupFailures++;
                error_log('Không thể xóa ảnh sản phẩm sau khi ẩn sản phẩm: ' . $exception->getMessage());
            }
        }
        if ($fileCleanupFailures > 0) {
            throw new RuntimeException('Sản phẩm đã được ẩn nhưng không thể xóa ' . $fileCleanupFailures . ' tệp ảnh.');
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
            'sale_unit' => $this->postText('sale_unit'),
            'pack_size' => $this->postText('pack_size'),
            'pack_size_unit' => $this->postText('pack_size_unit'),
            'price' => $this->postText('price'),
            'sale_price' => $this->postText('sale_price'),
            'short_description' => $this->postText('short_description'),
            'description' => $this->postText('description'),
            'storage_instructions' => $this->postText('storage_instructions'),
            'season' => $this->postText('season'),
            'nutrition' => $this->postText('nutrition'),
            'preparation' => $this->postText('preparation'),
            'tags' => $this->postText('tags'),
            'seo_title' => $this->postText('seo_title'),
            'seo_description' => $this->postText('seo_description'),
            'certifications' => $this->postText('certifications'),
            'status' => $this->postText('status') ?: 'draft',
            'is_featured' => $this->postChecked('is_featured') ? 1 : 0,
            'suggested_shelf_life_days' => $this->postText('suggested_shelf_life_days'),
            'min_stock' => $this->postText('min_stock'),
        ];
        $input['slug'] = $input['slug'] !== '' ? CatalogSlug::from($input['slug']) : CatalogSlug::from($input['name']);
        $errors = [];
        $primaryNewIndexRaw = $_POST['primary_new_image'] ?? '';
        if (!is_scalar($primaryNewIndexRaw)
            || ((string) $primaryNewIndexRaw !== '' && !preg_match('/\A[0-7]\z/', (string) $primaryNewIndexRaw))) {
            $errors['images'] = 'Lựa chọn ảnh đại diện không hợp lệ.';
        }
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
        if (!in_array($input['sale_unit'], ProductUnitFormatter::SALE_UNITS, true)) {
            $errors['sale_unit'] = 'Vui lòng chọn đơn vị bán hợp lệ.';
        }
        $packSize = null;
        $packSizeUnit = null;
        if ($input['pack_size'] !== '') {
            $parsedPackSize = filter_var($input['pack_size'], FILTER_VALIDATE_FLOAT);
            if ($parsedPackSize === false
                || !preg_match('/\A[0-9]+(?:\.[0-9]{1,2})?\z/', $input['pack_size'])
                || (float) $parsedPackSize <= 0
                || (float) $parsedPackSize > 99999999.99) {
                $errors['pack_size'] = 'Quy cách phải là số lớn hơn 0, tối đa 2 chữ số thập phân.';
            } else {
                $packSize = (float) $parsedPackSize;
            }
            if (!in_array($input['pack_size_unit'], ProductUnitFormatter::PACK_SIZE_UNITS, true)) {
                $errors['pack_size_unit'] = 'Vui lòng chọn đơn vị quy cách hợp lệ.';
            } else {
                $packSizeUnit = $input['pack_size_unit'];
            }
        } elseif ($input['pack_size_unit'] !== '') {
            $errors['pack_size_unit'] = 'Vui lòng nhập số quy cách khi đã chọn đơn vị quy cách.';
        }
        if ($input['sale_unit'] === 'Kg') {
            $packSize = 1.0;
            $packSizeUnit = 'kg';
        }
        $unitLabel = ProductUnitFormatter::shortLabel($input['sale_unit'], $packSize, $packSizeUnit);
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
        foreach (['season' => 255, 'tags' => 500, 'seo_title' => 255, 'seo_description' => 500] as $field => $maxLength) {
            if (mb_strlen($input[$field], 'UTF-8') > $maxLength) {
                $errors[$field] = 'Trường này vượt quá ' . $maxLength . ' ký tự.';
            }
        }
        $shelfLife = $input['suggested_shelf_life_days'] === '' ? null : filter_var($input['suggested_shelf_life_days'], FILTER_VALIDATE_INT);
        if ($input['suggested_shelf_life_days'] !== '' && ($shelfLife === false || $shelfLife < 0 || $shelfLife > 65535)) {
            $errors['suggested_shelf_life_days'] = 'Hạn dùng gợi ý phải là số ngày hợp lệ.';
        }
        $minStock = filter_var($input['min_stock'], FILTER_VALIDATE_INT);
        if ($minStock === false || $minStock < 0 || $minStock > 4294967295) {
            $errors['min_stock'] = 'Tồn kho tối thiểu phải là số nguyên không âm.';
        }
        if ($errors !== []) {
            remember_form_data($input);
            flash('validation_errors', $errors);
            redirect($productId === null ? '/admin/products/new' : '/admin/products/' . $productId . '/edit');
        }

        $imageCount = 0;
        if ($productId !== null) {
            $imageCountQuery = Database::connection()->prepare(
                'SELECT COUNT(*) FROM product_images WHERE product_id = :product_id'
            );
            $imageCountQuery->execute(['product_id' => $productId]);
            $imageCount = (int) $imageCountQuery->fetchColumn();
        }
        try {
            $newFiles = $this->storeUploadedFiles($imageCount);
        } catch (RuntimeException $exception) {
            remember_form_data($input);
            flash('error', $exception->getMessage());
            redirect($productId === null ? '/admin/products/new' : '/admin/products/' . $productId . '/edit');
        }
        $primaryNewIndex = null;
        if ($newFiles !== [] && (string) $primaryNewIndexRaw !== '') {
            $primaryNewIndex = (int) $primaryNewIndexRaw;
            if ($primaryNewIndex >= count($newFiles)) {
                $this->removeUploadedFiles($newFiles);
                remember_form_data($input);
                flash('error', 'Vui lòng chọn ảnh đại diện trong danh sách ảnh mới.');
                redirect($productId === null ? '/admin/products/new' : '/admin/products/' . $productId . '/edit');
            }
        }
        $database = Database::connection();
        $database->beginTransaction();
        try {
            if ($productId !== null) {
                $productLock = $database->prepare(
                    'SELECT id FROM products WHERE id = :id AND deleted_at IS NULL FOR UPDATE'
                );
                $productLock->execute(['id' => $productId]);
                if (!$productLock->fetchColumn()) {
                    throw new RuntimeException('Không tìm thấy sản phẩm để lưu ảnh.');
                }
            }
            $currentImageCount = 0;
            if ($productId !== null) {
                $currentImageCountQuery = $database->prepare(
                    'SELECT COUNT(*) FROM product_images WHERE product_id = :product_id'
                );
                $currentImageCountQuery->execute(['product_id' => $productId]);
                $currentImageCount = (int) $currentImageCountQuery->fetchColumn();
            }
            if ($newFiles !== [] && $currentImageCount + count($newFiles) > 8) {
                $database->rollBack();
                $this->removeUploadedFiles($newFiles);
                remember_form_data($input);
                flash('error', 'Mỗi sản phẩm được lưu tối đa 8 ảnh. Hãy xóa bớt ảnh trước khi tải thêm.');
                redirect($productId === null ? '/admin/products/new' : '/admin/products/' . $productId . '/edit');
            }
            $hasPrimaryImage = $productId !== null && $this->hasUsablePrimaryImage($productId);
            $values = [
                'category_id' => $input['category_id'],
                'region_id' => $input['region_id'],
                'farm_id' => $input['farm_id'],
                'name' => $input['name'],
                'slug' => $input['slug'],
                'sku' => $input['sku'],
                'unit' => $unitLabel,
                'sale_unit' => $input['sale_unit'],
                'pack_size' => $packSize,
                'pack_size_unit' => $packSizeUnit,
                'price' => $price,
                'sale_price' => $salePrice,
                'suggested_shelf_life_days' => $shelfLife,
                'storage_instructions' => $input['storage_instructions'],
                'season' => $input['season'],
                'nutrition' => $input['nutrition'],
                'preparation' => $input['preparation'],
                'tags' => $input['tags'],
                'seo_title' => $input['seo_title'],
                'seo_description' => $input['seo_description'],
                'certifications' => $input['certifications'],
                'short_description' => $input['short_description'],
                'status' => $input['status'],
                'is_featured' => $input['is_featured'],
                'min_stock' => $minStock,
                'description' => $input['description'],
            ];
            if ($productId === null) {
                $statement = $database->prepare(
                    'INSERT INTO products
                     (category_id, region_id, farm_id, name, slug, sku, unit, sale_unit, pack_size, pack_size_unit, price, sale_price,
                      suggested_shelf_life_days, storage_instructions, season, nutrition, preparation, tags,
                      seo_title, seo_description, certifications, short_description,
                      status, is_featured, min_stock, description)
                     VALUES
                     (:category_id, :region_id, :farm_id, :name, :slug, :sku, :unit, :sale_unit, :pack_size, :pack_size_unit, :price, :sale_price,
                      :suggested_shelf_life_days, :storage_instructions, :season, :nutrition, :preparation, :tags,
                      :seo_title, :seo_description, :certifications, :short_description,
                      :status, :is_featured, :min_stock, :description)'
                );
                $statement->execute($values);
                $productId = (int) $database->lastInsertId();
                $action = 'product_created';
            } else {
                $statement = $database->prepare(
                    'UPDATE products SET category_id = :category_id, region_id = :region_id, farm_id = :farm_id,
                        name = :name, slug = :slug, sku = :sku, unit = :unit, sale_unit = :sale_unit,
                        pack_size = :pack_size, pack_size_unit = :pack_size_unit, price = :price, sale_price = :sale_price,
                        suggested_shelf_life_days = :suggested_shelf_life_days, storage_instructions = :storage_instructions,
                        season = :season, nutrition = :nutrition, preparation = :preparation, tags = :tags,
                        seo_title = :seo_title, seo_description = :seo_description,
                        certifications = :certifications, short_description = :short_description, status = :status,
                        is_featured = :is_featured, min_stock = :min_stock, description = :description WHERE id = :id AND deleted_at IS NULL'
                );
                $values['id'] = $productId;
                $statement->execute($values);
                $action = 'product_updated';
            }
            if ($primaryNewIndex !== null) {
                $database->prepare('UPDATE product_images SET is_primary = 0 WHERE product_id = :product_id')
                    ->execute(['product_id' => $productId]);
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
                    'is_primary' => $primaryNewIndex !== null
                        ? (int) ($index === $primaryNewIndex)
                        : (int) ($index === 0 && !$hasPrimaryImage),
                ]);
            }
            AuditLog::record((int) Auth::user()['id'], $action, 'product', $productId, $input['sku']);
            $database->commit();
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            $this->removeUploadedFiles($newFiles);
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
        $images = $product ? $this->productImages($productId) : [];
        $hasStoredPrimaryImage = count(array_filter(
            $images,
            static fn (array $image): bool => (int) $image['is_primary'] === 1
        )) > 0;
        $primaryImage = $hasStoredPrimaryImage
            ? array_values(array_filter($images, static fn (array $image): bool => (int) $image['is_primary'] === 1))[0]
            : ($images[0] ?? null);
        $hasPrimaryImage = $primaryImage !== null && $this->imageFileExists((string) $primaryImage['image_url']);
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
            'images' => $images,
            'hasPrimaryImage' => $hasPrimaryImage,
            'hasStoredPrimaryImage' => $hasStoredPrimaryImage,
            'errors' => form_errors(),
            'success' => flash('success'),
            'error' => flash('error'),
            'menu' => $this->menu(),
        ], 'admin');
    }

    private function storeUploadedFiles(int $existingCount = 0): array
    {
        if (!isset($_FILES['images'])) {
            return [];
        }
        $files = $_FILES['images'];
        if (!is_array($files) || !is_array($files['name'] ?? null)) {
            throw new RuntimeException('Danh sách ảnh tải lên không hợp lệ.');
        }
        foreach (['tmp_name', 'error', 'size'] as $field) {
            if (!is_array($files[$field] ?? null) || count($files[$field]) !== count($files['name'])) {
                throw new RuntimeException('Danh sách ảnh tải lên không hợp lệ.');
            }
        }
        $uploadCount = 0;
        foreach ($files['error'] as $error) {
            if ((int) $error !== UPLOAD_ERR_NO_FILE) {
                $uploadCount++;
            }
        }
        if ($existingCount + $uploadCount > 8) {
            throw new RuntimeException('Mỗi sản phẩm được lưu tối đa 8 ảnh. Hãy xóa bớt ảnh trước khi tải thêm.');
        }
        if ($uploadCount === 0) {
            return [];
        }
        $stored = [];
        foreach (array_keys($files['name']) as $index) {
            $error = (int) ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            try {
                $storedFile = ImageUploadService::store([
                    'name' => $files['name'][$index] ?? '',
                    'type' => $files['type'][$index] ?? '',
                    'tmp_name' => $files['tmp_name'][$index] ?? '',
                    'error' => $error,
                    'size' => $files['size'][$index] ?? 0,
                ], 'products');
            } catch (Throwable $exception) {
                $this->removeUploadedFiles($stored);
                throw $exception;
            }
            if ($storedFile !== null) {
                $stored[] = $storedFile;
            }
        }
        return $stored;
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

    private function hasUsablePrimaryImage(int $productId): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT image_url FROM product_images WHERE product_id = :product_id
             ORDER BY is_primary DESC, sort_order, id LIMIT 1'
        );
        $statement->execute(['product_id' => $productId]);
        $imageUrl = $statement->fetchColumn();
        return $imageUrl !== false && $this->imageFileExists((string) $imageUrl);
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
        ImageUploadService::deleteStored($url, 'products');
    }

    private function imageFileExists(string $imageUrl): bool
    {
        $parts = parse_url($imageUrl);
        if ($parts === false || isset($parts['scheme']) || isset($parts['host'])) {
            return false;
        }
        $imagePath = $parts['path'] ?? null;
        if (!is_string($imagePath) || !str_starts_with($imagePath, '/')) {
            return false;
        }
        $allowedDirectories = ['/uploads/products', '/img', '/public/images'];
        foreach ($allowedDirectories as $allowedDirectory) {
            if (!str_starts_with($imagePath, $allowedDirectory . '/')) {
                continue;
            }
            $directory = realpath(ROOT_PATH . str_replace('/', DIRECTORY_SEPARATOR, $allowedDirectory));
            $path = realpath(ROOT_PATH . str_replace('/', DIRECTORY_SEPARATOR, $imagePath));
            $directoryPrefix = $directory === false ? '' : rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            return $path !== false && $directoryPrefix !== ''
                && str_starts_with($path, $directoryPrefix) && is_file($path);
        }
        return false;
    }

    private function removeUploadedFiles(array $files): void
    {
        foreach ($files as $file) {
            try {
                ImageUploadService::deleteStored((string) ($file['url'] ?? ''), 'products');
            } catch (Throwable $exception) {
                error_log('Không thể dọn ảnh tải lên sau lỗi: ' . $exception->getMessage());
            }
        }
    }

    private function discardNewImage(?array $image, string $directoryName): void
    {
        if ($image === null) {
            return;
        }
        try {
            ImageUploadService::deleteStored((string) ($image['url'] ?? ''), $directoryName);
        } catch (Throwable $exception) {
            error_log('Không thể dọn ảnh mới sau lỗi lưu dữ liệu: ' . $exception->getMessage());
        }
    }

    private function storeCoverImage(string $directoryName, string $route): ?array
    {
        try {
            return ImageUploadService::store($_FILES['image'] ?? null, $directoryName);
        } catch (RuntimeException $exception) {
            $this->fail($route, ['image' => $exception->getMessage()]);
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
            ['permission' => 'reports.view', 'label' => 'Báo cáo', 'url' => '/admin/reports'],
            ['permission' => 'content.view', 'label' => 'Trang tĩnh', 'url' => '/admin/content/pages'],
            ['permission' => 'contacts.view', 'label' => 'Liên hệ', 'url' => '/admin/contacts'],
            ['permission' => 'regions.view', 'label' => 'Vùng miền', 'url' => '/admin/regions'],
            ['permission' => 'categories.view', 'label' => 'Danh mục', 'url' => '/admin/categories'],
            ['permission' => 'products.view', 'label' => 'Sản phẩm', 'url' => '/admin/products'],
            ['permission' => 'products.view', 'label' => 'Sản phẩm thiếu ảnh', 'url' => '/admin/products/missing-images'],
            ['permission' => 'orders.view', 'label' => 'Đơn hàng', 'url' => '/admin/orders'],
            ['permission' => 'vouchers.view', 'label' => 'Voucher', 'url' => '/admin/vouchers'],
            ['permission' => 'reviews.view', 'label' => 'Đánh giá', 'url' => '/admin/reviews'],
            ['permission' => 'customers.view', 'label' => 'Khách hàng', 'url' => '/admin/customers'],
            ['permission' => 'inventory.view', 'label' => 'Kho hàng', 'url' => '/admin/inventory'],
            ['permission' => 'flash_sales.view', 'label' => 'Flash Sale', 'url' => '/admin/flash-sales'],
            ['permission' => 'mail.view', 'label' => 'Email / Hộp thư chờ', 'url' => '/admin/mail'],
            ['permission' => 'batches.view', 'label' => 'Lô hàng', 'url' => '/admin/batches'],
            ['permission' => 'users.view', 'label' => 'Người dùng', 'url' => '/admin/users'],
        ];
        return array_values(array_filter($items, static fn (array $item): bool => Auth::can($item['permission'])));
    }
}
