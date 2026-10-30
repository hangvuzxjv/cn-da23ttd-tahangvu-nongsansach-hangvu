<?php

declare(strict_types=1);

final class StorefrontController extends Controller
{
    public function home(): void
    {
        (new PaymentService())->expirePending();
        (new InventoryService())->expireReservations();
        $database = Database::connection();
        $banners = $database->query(
            "SELECT title, image_url, link_url FROM banners
             WHERE status = 'active' AND (starts_at IS NULL OR starts_at <= NOW())
               AND (ends_at IS NULL OR ends_at >= NOW())
             ORDER BY sort_order, id LIMIT 5"
        )->fetchAll();
        $regions = $database->query(
            'SELECT id, name, slug, description FROM regions
             WHERE level = 1 AND is_active = 1 ORDER BY id'
        )->fetchAll();
        $featured = $this->fetchProducts('p.is_featured = 1', [], 'p.created_at DESC', 8);
        $newest = $this->fetchProducts('1 = 1', [], 'p.created_at DESC', 8);
        $flashSales = $database->query(
            "SELECT fs.id, fs.name, fs.ends_at, p.name AS product_name, p.slug AS product_slug,
                    fsi.sale_price, fsi.quota, fsi.sold_quantity,
                    COALESCE(pi.image_url, '/img/product-placeholder.svg') AS image_url
             FROM flash_sales fs
             JOIN flash_sale_items fsi ON fsi.flash_sale_id = fs.id
             JOIN products p ON p.id = fsi.product_id
             LEFT JOIN (
                 SELECT product_id, SUBSTRING_INDEX(GROUP_CONCAT(image_url ORDER BY is_primary DESC, sort_order, id), ',', 1) AS image_url
                 FROM product_images GROUP BY product_id
             ) pi ON pi.product_id = p.id
             WHERE fs.status = 'active' AND fs.starts_at <= NOW() AND fs.ends_at >= NOW()
               AND fsi.sold_quantity < fsi.quota AND p.status = 'active' AND p.deleted_at IS NULL
             ORDER BY fs.ends_at, fsi.id LIMIT 8"
        )->fetchAll();
        $this->view('client/home', [
            'title' => 'Nông sản sạch theo mùa',
            'metaDescription' => 'Khám phá nông sản sạch, truy xuất nguồn gốc từ các vùng miền Việt Nam.',
            'banners' => $banners,
            'regions' => $regions,
            'featured' => $featured,
            'newest' => $newest,
            'flashSales' => $flashSales,
        ]);
    }

    public function shop(): void
    {
        $this->listing();
    }

    public function region(string $slug): void
    {
        $statement = Database::connection()->prepare(
            'SELECT id, name, slug, description, level FROM regions WHERE slug = :slug AND is_active = 1 LIMIT 1'
        );
        $statement->execute(['slug' => $slug]);
        $region = $statement->fetch();
        if (!$region) {
            $this->notFound();
            return;
        }
        $this->listing(['region' => $region], 'region');
    }

    public function category(string $slug): void
    {
        $statement = Database::connection()->prepare(
            'SELECT id, name, slug, description FROM categories WHERE slug = :slug AND is_active = 1 LIMIT 1'
        );
        $statement->execute(['slug' => $slug]);
        $category = $statement->fetch();
        if (!$category) {
            $this->notFound();
            return;
        }
        $category['category_ids'] = $this->categoryAndDescendantIds((int) $category['id']);
        $this->listing(['category' => $category], 'category');
    }

    public function product(string $slug): void
    {
        (new PaymentService())->expirePending();
        (new InventoryService())->expireReservations();
        $statement = Database::connection()->prepare(
            "SELECT p.*, c.name AS category_name, c.slug AS category_slug,
                    r.name AS region_name, r.slug AS region_slug, parent.name AS parent_region_name,
                    f.name AS farm_name, f.certifications AS farm_certifications,
                    COALESCE(stock.quantity, 0) AS actual_stock
             FROM products p
             JOIN categories c ON c.id = p.category_id AND c.is_active = 1
             JOIN regions r ON r.id = p.region_id AND r.is_active = 1
             LEFT JOIN regions parent ON parent.id = r.parent_id
             JOIN farms f ON f.id = p.farm_id AND f.status = 'active'
             LEFT JOIN " . $this->availableStockSubquery() . " stock ON stock.product_id = p.id
             WHERE p.slug = :slug AND p.status = 'active' AND p.deleted_at IS NULL
             LIMIT 1"
        );
        $statement->execute(['slug' => $slug]);
        $product = $statement->fetch();
        if (!$product) {
            $this->notFound();
            return;
        }
        $images = Database::connection()->prepare(
            'SELECT image_url, alt_text FROM product_images WHERE product_id = :product_id
             ORDER BY is_primary DESC, sort_order, id'
        );
        $images->execute(['product_id' => $product['id']]);
        $related = $this->fetchProducts(
            'p.id <> :current_id AND (p.category_id = :category_id OR p.region_id = :region_id)',
            ['current_id' => (int) $product['id'], 'category_id' => (int) $product['category_id'], 'region_id' => (int) $product['region_id']],
            'p.is_featured DESC, p.created_at DESC',
            4
        );
        $batchQuery = Database::connection()->prepare(
            "SELECT b.id, b.batch_code, b.harvested_at, b.packed_at, b.expires_at,
                    GREATEST(0, b.quantity_remaining - COALESCE(r.quantity, 0)) AS quantity_remaining
             FROM product_batches b
             LEFT JOIN (
                 SELECT batch_id, SUM(quantity) AS quantity FROM stock_reservations
                 WHERE status = 'reserved' AND expires_at > NOW() GROUP BY batch_id
             ) r ON r.batch_id = b.id
             WHERE b.product_id = :product_id AND b.status = 'selling' AND b.inspection_result <> 'failed'
               AND b.quantity_remaining > COALESCE(r.quantity, 0)
               AND (b.expires_at IS NULL OR b.expires_at >= CURDATE())
             ORDER BY b.expires_at IS NULL, b.expires_at, b.harvested_at, b.id"
        );
        $batchQuery->execute(['product_id' => $product['id']]);
        $stock = (int) $product['actual_stock'];
        $this->view('client/product', [
            'title' => $product['name'],
            'metaDescription' => $product['short_description'] ?: mb_substr(strip_tags((string) $product['description']), 0, 155, 'UTF-8'),
            'product' => $product,
            'images' => $images->fetchAll(),
            'related' => $related,
            'traceBatches' => $batchQuery->fetchAll(),
            'stockStatus' => $stock <= 0 ? 'Hết hàng' : ($stock <= (int) $product['min_stock'] ? 'Sắp hết hàng' : 'Còn hàng'),
            'breadcrumbs' => [
                ['name' => 'Sản phẩm', 'url' => '/san-pham'],
                ['name' => $product['category_name'], 'url' => '/danh-muc/' . $product['category_slug']],
                ['name' => $product['name'], 'url' => null],
            ],
        ]);
    }

    public function sitemap(): Response
    {
        $database = Database::connection();
        $urls = [absolute_url('/') , absolute_url('/san-pham')];
        foreach ([
            'SELECT slug FROM regions WHERE is_active = 1',
            'SELECT slug FROM categories WHERE is_active = 1',
            "SELECT slug FROM products WHERE status = 'active' AND deleted_at IS NULL",
        ] as $queryIndex => $sql) {
            foreach ($database->query($sql)->fetchAll(PDO::FETCH_COLUMN) as $slug) {
                $prefix = $queryIndex === 0 ? '/vung-mien/' : ($queryIndex === 1 ? '/danh-muc/' : '/san-pham/');
                $urls[] = absolute_url($prefix . rawurlencode((string) $slug));
            }
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($urls as $url) {
            $xml .= '<url><loc>' . htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc></url>';
        }
        $xml .= '</urlset>';
        return new Response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    private function listing(array $scope = [], string $scopeType = ''): void
    {
        (new PaymentService())->expirePending();
        (new InventoryService())->expireReservations();
        $filterErrors = [];
        $queryInput = $_GET['q'] ?? '';
        $query = is_scalar($queryInput) ? trim((string) $queryInput) : '';
        if (!is_scalar($queryInput)) {
            $filterErrors[] = 'Từ khóa tìm kiếm không hợp lệ.';
        }
        $categoryInput = $_GET['category'] ?? '';
        $regionInput = $_GET['region'] ?? '';
        $categoryId = is_scalar($categoryInput) ? filter_var($categoryInput, FILTER_VALIDATE_INT) : false;
        $regionId = is_scalar($regionInput) ? filter_var($regionInput, FILTER_VALIDATE_INT) : false;
        if (!is_scalar($categoryInput) || (trim((string) $categoryInput) !== '' && $categoryId === false)) {
            $filterErrors[] = 'Danh mục lọc không hợp lệ.';
        }
        if (!is_scalar($regionInput) || (trim((string) $regionInput) !== '' && $regionId === false)) {
            $filterErrors[] = 'Vùng miền lọc không hợp lệ.';
        }
        $minimumInput = $_GET['min_price'] ?? '';
        $maximumInput = $_GET['max_price'] ?? '';
        $minimum = $this->validPrice($minimumInput);
        $maximum = $this->validPrice($maximumInput);
        if (!is_scalar($minimumInput) || (isset($_GET['min_price']) && trim((string) $minimumInput) !== '' && $minimum === null)) {
            $filterErrors[] = 'Giá tối thiểu phải là số không âm.';
        }
        if (!is_scalar($maximumInput) || (isset($_GET['max_price']) && trim((string) $maximumInput) !== '' && $maximum === null)) {
            $filterErrors[] = 'Giá tối đa phải là số không âm.';
        }
        if ($minimum !== null && $maximum !== null && $minimum > $maximum) {
            $filterErrors[] = 'Giá tối thiểu không thể lớn hơn giá tối đa.';
        }
        $validPriceRange = $minimum === null || $maximum === null || $minimum <= $maximum;
        if (strlen($query) > 200) {
            $query = mb_substr($query, 0, 200, 'UTF-8');
            $filterErrors[] = 'Từ khóa tìm kiếm đã được giới hạn ở 200 ký tự.';
        }
        $sortInput = $_GET['sort'] ?? 'newest';
        $sort = is_scalar($sortInput) ? (string) $sortInput : '';
        if (!is_scalar($sortInput)) {
            $filterErrors[] = 'Kiểu sắp xếp không hợp lệ.';
        }
        if (!in_array($sort, ['newest', 'price_asc', 'price_desc', 'best_selling'], true)) {
            $sort = 'newest';
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $conditions = ['p.status = \'active\'', 'p.deleted_at IS NULL'];
        $parameters = [];
        if ($query !== '') {
            $conditions[] = 'p.name LIKE :search_query';
            $parameters['search_query'] = '%' . $query . '%';
        }
        if ($scopeType === 'category') {
            $conditions[] = 'p.category_id IN (' . $this->inClause($scope['category']['category_ids'], 'scope_category', $parameters) . ')';
        }
        if ($categoryId && $categoryId > 0) {
            $categoryIds = $this->categoryAndDescendantIds($categoryId);
            $conditions[] = 'p.category_id IN (' . $this->inClause($categoryIds, 'filter_category', $parameters) . ')';
        }
        $scopedRegion = $scopeType === 'region' ? $scope['region'] : null;
        if ($scopedRegion) {
            $scopeRegionId = (int) $scopedRegion['id'];
            $conditions[] = '(p.region_id = :scope_region_id OR r.parent_id = :scope_parent_id)';
            $parameters['scope_region_id'] = $scopeRegionId;
            $parameters['scope_parent_id'] = $scopeRegionId;
        }
        if ($regionId && $regionId > 0) {
            $conditions[] = '(p.region_id = :filter_region_id OR r.parent_id = :filter_parent_id)';
            $parameters['filter_region_id'] = $regionId;
            $parameters['filter_parent_id'] = $regionId;
        }
        if ($validPriceRange && $minimum !== null) {
            $conditions[] = 'COALESCE(p.sale_price, p.price) >= :minimum_price';
            $parameters['minimum_price'] = $minimum;
        }
        if ($validPriceRange && $maximum !== null) {
            $conditions[] = 'COALESCE(p.sale_price, p.price) <= :maximum_price';
            $parameters['maximum_price'] = $maximum;
        }
        $where = implode(' AND ', $conditions);
        $countQuery = Database::connection()->prepare(
            'SELECT COUNT(*) FROM products p
             JOIN regions r ON r.id = p.region_id AND r.is_active = 1
             JOIN categories c ON c.id = p.category_id AND c.is_active = 1
             WHERE ' . $where
        );
        $countQuery->execute($parameters);
        $total = (int) $countQuery->fetchColumn();
        $perPage = 12;
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $sortSql = match ($sort) {
            'price_asc' => 'COALESCE(p.sale_price, p.price) ASC, p.id DESC',
            'price_desc' => 'COALESCE(p.sale_price, p.price) DESC, p.id DESC',
            'best_selling' => 'COALESCE(sales.sold, 0) DESC, p.created_at DESC',
            default => 'p.created_at DESC, p.id DESC',
        };
        $statement = Database::connection()->prepare(
            'SELECT p.id, p.name, p.slug, p.price, p.sale_price, p.unit, p.min_stock, p.short_description,
                    COALESCE(stock.quantity, 0) AS actual_stock,
                    COALESCE(images.image_url, \'/img/product-placeholder.svg\') AS image_url,
                    COALESCE(sales.sold, 0) AS sold_quantity
             FROM products p
             JOIN regions r ON r.id = p.region_id AND r.is_active = 1
             JOIN categories c ON c.id = p.category_id AND c.is_active = 1
             LEFT JOIN ' . $this->availableStockSubquery() . ' stock ON stock.product_id = p.id
             LEFT JOIN (
                 SELECT product_id, SUBSTRING_INDEX(GROUP_CONCAT(image_url ORDER BY is_primary DESC, sort_order, id), \',\', 1) AS image_url
                 FROM product_images GROUP BY product_id
             ) images ON images.product_id = p.id
             LEFT JOIN (
                 SELECT oi.product_id, SUM(oi.quantity) AS sold
                 FROM order_items oi JOIN orders o ON o.id = oi.order_id
                 WHERE o.status NOT IN (\'cancelled\', \'refunded\') AND oi.product_id IS NOT NULL
                 GROUP BY oi.product_id
             ) sales ON sales.product_id = p.id
             WHERE ' . $where . '
             ORDER BY ' . $sortSql . ' LIMIT :limit OFFSET :offset'
        );
        foreach ($parameters as $key => $value) {
            $statement->bindValue(':' . $key, $value);
        }
        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $statement->execute();

        $categories = Database::connection()->query(
            'SELECT id, name, slug, parent_id FROM categories WHERE is_active = 1 ORDER BY parent_id, sort_order, name'
        )->fetchAll();
        $regions = Database::connection()->query(
            'SELECT id, name, slug, parent_id, level FROM regions WHERE is_active = 1 ORDER BY level, parent_id, name'
        )->fetchAll();
        $title = $scopeType === 'region' ? $scope['region']['name'] : ($scopeType === 'category' ? $scope['category']['name'] : 'Cửa hàng nông sản sạch');
        $description = $scopeType === 'region' ? ($scope['region']['description'] ?: 'Nông sản sạch theo vùng miền.') : ($scopeType === 'category' ? ($scope['category']['description'] ?: 'Sản phẩm thuộc danh mục ' . $title . '.') : 'Lọc và tìm nông sản sạch theo vùng miền, danh mục và khoảng giá.');
        $breadcrumbs = [['name' => 'Sản phẩm', 'url' => '/san-pham']];
        if ($scopeType === 'region') {
            $breadcrumbs[] = ['name' => $title, 'url' => null];
        } elseif ($scopeType === 'category') {
            $breadcrumbs[] = ['name' => $title, 'url' => null];
        }
        $this->view('client/shop', [
            'title' => $title,
            'metaDescription' => mb_substr(strip_tags($description), 0, 160, 'UTF-8'),
            'description' => $description,
            'products' => $statement->fetchAll(),
            'categories' => $categories,
            'regions' => $regions,
            'query' => $query,
            'categoryId' => $categoryId ?: 0,
            'regionId' => $regionId ?: 0,
            'minimum' => is_scalar($minimumInput) ? $minimumInput : '',
            'maximum' => is_scalar($maximumInput) ? $maximumInput : '',
            'filterErrors' => $filterErrors,
            'sort' => $sort,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'breadcrumbs' => $breadcrumbs,
            'scope' => $scope,
            'scopeType' => $scopeType,
        ]);
    }

    private function fetchProducts(string $extraCondition, array $parameters, string $order, int $limit): array
    {
        $statement = Database::connection()->prepare(
            'SELECT p.id, p.name, p.slug, p.price, p.sale_price, p.unit, p.min_stock, p.short_description,
                    COALESCE(stock.quantity, 0) AS actual_stock,
                    COALESCE(images.image_url, \'/img/product-placeholder.svg\') AS image_url
             FROM products p
             LEFT JOIN ' . $this->availableStockSubquery() . ' stock ON stock.product_id = p.id
             LEFT JOIN (
                 SELECT product_id, SUBSTRING_INDEX(GROUP_CONCAT(image_url ORDER BY is_primary DESC, sort_order, id), \',\', 1) AS image_url
                 FROM product_images GROUP BY product_id
             ) images ON images.product_id = p.id
             JOIN categories c ON c.id = p.category_id AND c.is_active = 1
             JOIN regions r ON r.id = p.region_id AND r.is_active = 1
             WHERE p.status = \'active\' AND p.deleted_at IS NULL AND ' . $extraCondition . '
             ORDER BY ' . $order . ' LIMIT :limit'
        );
        foreach ($parameters as $key => $value) {
            $statement->bindValue(':' . $key, $value, PDO::PARAM_INT);
        }
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    private function validPrice(mixed $value): ?float
    {
        if (!is_scalar($value) || trim((string) $value) === '') {
            return null;
        }
        $price = filter_var($value, FILTER_VALIDATE_FLOAT);
        return $price !== false && $price >= 0 ? (float) $price : null;
    }

    private function availableStockSubquery(): string
    {
        return '(SELECT b.product_id,
                       SUM(GREATEST(0, b.quantity_remaining - COALESCE(r.quantity, 0))) AS quantity
                FROM product_batches b
                LEFT JOIN (
                    SELECT batch_id, SUM(quantity) AS quantity
                    FROM stock_reservations
                    WHERE status = \'reserved\' AND expires_at > NOW()
                    GROUP BY batch_id
                ) r ON r.batch_id = b.id
                WHERE b.status = \'selling\' AND b.inspection_result <> \'failed\'
                  AND b.quantity_remaining > 0
                  AND (b.expires_at IS NULL OR b.expires_at >= CURDATE())
                GROUP BY b.product_id)';
    }

    private function categoryAndDescendantIds(int $categoryId): array
    {
        $rows = Database::connection()->query('SELECT id, parent_id FROM categories WHERE is_active = 1')->fetchAll();
        $children = [];
        foreach ($rows as $row) {
            $children[(int) $row['parent_id']][] = (int) $row['id'];
        }
        $ids = [];
        $pending = [$categoryId];
        while ($pending !== []) {
            $current = array_pop($pending);
            if (in_array($current, $ids, true)) {
                continue;
            }
            $ids[] = $current;
            foreach ($children[$current] ?? [] as $childId) {
                $pending[] = $childId;
            }
        }
        return $ids;
    }

    private function inClause(array $values, string $prefix, array &$parameters): string
    {
        $placeholders = [];
        foreach (array_values($values) as $index => $value) {
            $key = $prefix . '_' . $index;
            $placeholders[] = ':' . $key;
            $parameters[$key] = (int) $value;
        }
        return implode(', ', $placeholders);
    }

    private function notFound(): void
    {
        http_response_code(404);
        View::renderError(404);
    }
}
