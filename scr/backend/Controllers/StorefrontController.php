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
            "SELECT title, description, image_url, button_label, link_url FROM banners
             WHERE status = 'active' AND (starts_at IS NULL OR starts_at <= NOW())
               AND (ends_at IS NULL OR ends_at >= NOW())
             ORDER BY sort_order, id"
        )->fetchAll();
        $regions = $this->homepageRegions($database);
        $categories = $this->homepageCategories($database);
        $featured = $this->fetchProducts('p.is_featured = 1', [], 'p.created_at DESC', 8);
        $newest = $this->fetchProducts('1 = 1', [], 'p.created_at DESC', 8);
        $bestselling = $this->fetchProducts('1 = 1', [], 'COALESCE(sales.sold, 0) DESC, p.created_at DESC', 8);
        $userId = $this->currentUserId();
        $voucherWallet = new VoucherWalletService($database);
        $publicVouchers = $voucherWallet->publicVouchers($userId, 3);
        $walletVouchers = $userId === null ? [] : $voucherWallet->walletForUser($userId);
        $usableVoucherCount = count(array_filter(
            $walletVouchers,
            static fn (array $voucher): bool => $voucher['wallet_status'] === 'available'
        ));
        $welcomeVoucherAvailable = count(array_filter(
            $walletVouchers,
            static fn (array $voucher): bool =>
                $voucher['code'] === 'CHAOMUNG' && $voucher['wallet_status'] === 'available'
        )) > 0;
        $engagement = new CustomerEngagementService($database);
        $recentProducts = $this->recentlyViewedProducts();
        $flashService = new FlashSaleService($database);
        $flashSaleCampaigns = $this->groupFlashSaleOffers(
            $flashService->customerCampaigns($this->currentUserId())
        );
        $activeCampaigns = array_values(array_filter(
            $flashSaleCampaigns,
            static fn (array $campaign): bool => $campaign['campaign_status'] === 'active'
        ));
        $scheduledCampaigns = array_values(array_filter(
            $flashSaleCampaigns,
            static fn (array $campaign): bool => $campaign['campaign_status'] === 'scheduled'
        ));
        $homeFlashSale = $activeCampaigns[0] ?? $scheduledCampaigns[0] ?? null;
        $this->view('client/home', [
            'title' => 'Nông sản sạch theo mùa',
            'metaDescription' => 'Khám phá nông sản sạch, truy xuất nguồn gốc từ các vùng miền Việt Nam.',
            'banners' => $banners,
            'regions' => $regions,
            'categories' => $categories,
            'featured' => $featured,
            'newest' => $newest,
            'bestselling' => $bestselling,
            'flashSaleCampaign' => $homeFlashSale,
            'flashSaleServerTime' => $flashService->serverTime(),
            'flashSaleLoggedIn' => $userId !== null,
            'wishlistIds' => $userId === null ? [] : $engagement->wishlistProductIds($userId),
            'recentProducts' => $recentProducts,
            'publicVouchers' => $publicVouchers,
            'usableVoucherCount' => $usableVoucherCount,
            'showWelcomeOffer' => $userId === null || $welcomeVoucherAvailable,
            'welcomeVoucherAvailable' => $welcomeVoucherAvailable,
            'loadProductSearch' => true,
        ]);
    }

    public function flashSales(): void
    {
        $service = new FlashSaleService();
        $userId = $this->currentUserId();
        $campaigns = $this->groupFlashSaleOffers($service->customerCampaigns($userId, true));
        $this->view('client/flash-sales', [
            'title' => 'Flash Sale',
            'metaDescription' => 'Khám phá các chương trình Flash Sale đang diễn ra và sắp bắt đầu.',
            'campaigns' => $campaigns,
            'serverTime' => $service->serverTime(),
            'loggedIn' => $userId !== null,
            'wishlistIds' => $userId === null
                ? []
                : (new CustomerEngagementService())->wishlistProductIds($userId),
        ]);
    }

    public function flashSaleProgress(): Response
    {
        try {
            return Response::json(true, 'Đã cập nhật trạng thái Flash Sale.', (new FlashSaleService())->liveProgress());
        } catch (Throwable $exception) {
            error_log('Flash Sale customer progress failed: ' . $exception->getMessage());
            return Response::json(
                false,
                'Không thể cập nhật số suất Flash Sale lúc này.',
                [],
                503
            );
        }
    }

    public function shop(): void
    {
        $this->listing();
    }

    public function search(): void
    {
        $queryInput = $_GET['q'] ?? '';
        if (!is_scalar($queryInput)) {
            http_response_code(400);
            View::renderError(400);
            return;
        }

        $query = trim((string) $queryInput);
        $query = app_utf8_substr($query, 0, 100);
        try {
            $batchCode = $query === '' ? null : $this->findExactBatchCode($query);
        } catch (Throwable $exception) {
            app_log_exception('Storefront search failed', $exception);
            $this->renderSearchUnavailable();
            return;
        }
        if ($batchCode !== null) {
            redirect('/truy-xuat/' . rawurlencode($batchCode));
        }

        redirect($query === '' ? '/san-pham' : '/san-pham?' . http_build_query(['q' => $query]));
    }

    public function suggestions(): Response
    {
        $queryInput = $_GET['q'] ?? '';
        if (!is_scalar($queryInput)) {
            return new Response(
                json_encode(['items' => []], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                400,
                ['Content-Type' => 'application/json; charset=UTF-8', 'Cache-Control' => 'no-store']
            );
        }

        $query = app_utf8_substr(trim((string) $queryInput), 0, 100);
        $items = [];
        try {
            if (app_utf8_length($query) >= 2) {
                $parameters = [];
                $condition = $this->productSearchCondition($query, $parameters, 'suggest');
                $statement = Database::connection()->prepare(
                    'SELECT p.name, p.slug, COALESCE(p.sale_price, p.price) AS price,
                            COALESCE(images.image_url, \'/public/images/no-image.svg\') AS image_url
                     FROM products p
                     JOIN categories c ON c.id = p.category_id AND c.is_active = 1
                     JOIN regions r ON r.id = p.region_id AND r.is_active = 1
                     LEFT JOIN (
                         SELECT product_id, SUBSTRING_INDEX(GROUP_CONCAT(image_url ORDER BY is_primary DESC, sort_order, id), \',\', 1) AS image_url
                         FROM product_images GROUP BY product_id
                     ) images ON images.product_id = p.id
                     WHERE p.status = \'active\' AND p.deleted_at IS NULL AND ' . $condition . '
                     ORDER BY p.is_featured DESC, p.created_at DESC, p.id DESC LIMIT 6'
                );
                $statement->execute($parameters);
                foreach ($statement->fetchAll() as $product) {
                    $items[] = [
                        'type' => 'product',
                        'name' => (string) $product['name'],
                        'price' => (float) $product['price'],
                        'image' => asset((string) $product['image_url']),
                        'url' => url('/san-pham/' . rawurlencode((string) $product['slug'])),
                    ];
                }

                $batchCode = $this->findExactBatchCode($query);
                if ($batchCode !== null) {
                    $items[] = [
                        'type' => 'batch',
                        'label' => 'Truy xuất lô ' . $batchCode,
                        'url' => url('/truy-xuat/' . rawurlencode($batchCode)),
                    ];
                }
            }
        } catch (Throwable $exception) {
            app_log_exception('Storefront search suggestions failed', $exception);
            return Response::json(
                false,
                'Tìm kiếm tạm thời gặp sự cố, vui lòng thử lại',
                [],
                503
            );
        }

        return new Response(
            json_encode(
                ['items' => $items],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
            ),
            200,
            ['Content-Type' => 'application/json; charset=UTF-8', 'Cache-Control' => 'no-store']
        );
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
        $this->recordRecentlyViewed((int) $product['id']);
        $userId = $this->currentUserId();
        $engagement = new CustomerEngagementService();
        $reviewSummary = $engagement->reviewSummary((int) $product['id']);
        $rawReviewPage = $_GET['reviews_page'] ?? 1;
        $reviewPage = is_scalar($rawReviewPage) ? max(1, (int) $rawReviewPage) : 1;
        $reviewPageData = $engagement->publishedReviews((int) $product['id'], $reviewPage);
        $myReview = $userId === null ? null : $engagement->reviewForUser($userId, (int) $product['id']);
        $canReview = $userId !== null && $engagement->canReview($userId, (int) $product['id']);
        $recentProducts = $this->recentlyViewedProducts((int) $product['id']);
        $flashService = new FlashSaleService();
        $flashDeal = $flashService->activeDeal((int) $product['id'], $userId);
        if ($flashDeal !== null
            && PricingService::moneyToVnd((string) $flashDeal['sale_price'])
                < PricingService::moneyToVnd((string) ($product['sale_price'] ?: $product['price']))) {
            $flashDeal['remaining'] = max(0, (int) $flashDeal['quota'] - (int) $flashDeal['sold_quantity']);
            $flashDeal['user_remaining'] = max(
                0,
                (int) $flashDeal['per_user_limit'] - (int) $flashDeal['user_purchased']
            );
            $flashDeal['max_buy_quantity'] = min(
                $flashDeal['remaining'],
                $flashDeal['user_remaining'],
                (int) $product['actual_stock']
            );
        } else {
            $flashDeal = null;
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
            'title' => ($product['seo_title'] ?? null) ?: $product['name'],
            'metaDescription' => ($product['seo_description'] ?? null) ?: ($product['short_description'] ?: app_utf8_substr(strip_tags((string) $product['description']), 0, 155)),
            'product' => $product,
            'flashSale' => $flashDeal,
            'flashSaleServerTime' => $flashService->serverTime(),
            'flashSaleLoggedIn' => $userId !== null,
            'wishlistIds' => $userId === null ? [] : $engagement->wishlistProductIds($userId),
            'reviewSummary' => $reviewSummary,
            'reviews' => $reviewPageData['reviews'],
            'reviewsPage' => $reviewPageData['page'],
            'reviewsPages' => $reviewPageData['pages'],
            'canReview' => $canReview,
            'myReview' => $myReview,
            'reviewLoginUrl' => url('/login?return_to=' . rawurlencode('/san-pham/' . $product['slug'] . '#reviews')),
            'recentProducts' => $recentProducts,
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

    private function groupFlashSaleOffers(array $offers): array
    {
        $campaigns = [];
        foreach ($offers as $offer) {
            $campaignId = (int) $offer['campaign_id'];
            if (!isset($campaigns[$campaignId])) {
                $campaigns[$campaignId] = [
                    'id' => $campaignId,
                    'name' => $offer['campaign_name'],
                    'description' => $offer['description'],
                    'banner_url' => $offer['banner_url'],
                    'starts_at' => $offer['starts_at'],
                    'ends_at' => $offer['ends_at'],
                    'campaign_status' => $offer['campaign_status'],
                    'items' => [],
                ];
            }
            $campaigns[$campaignId]['items'][] = $offer;
        }
        return array_values($campaigns);
    }

    private function currentUserId(): ?int
    {
        $user = Auth::user();
        return $user === null ? null : (int) $user['id'];
    }

    public function sitemap(): Response
    {
        $database = Database::connection();
        $urls = [
            absolute_url('/'),
            absolute_url('/san-pham'),
            absolute_url('/lien-he'),
            absolute_url('/cau-hoi-thuong-gap'),
        ];
        $pageSlugs = StaticPageService::publishedSlugs();
        $pagePlaceholders = implode(',', array_fill(0, count($pageSlugs), '?'));
        $pageQuery = $database->prepare(
            "SELECT slug FROM pages WHERE status = 'published' AND slug IN (" . $pagePlaceholders . ')'
        );
        $pageQuery->execute($pageSlugs);
        foreach ($pageQuery->fetchAll(PDO::FETCH_COLUMN) as $slug) {
            $urls[] = absolute_url('/' . rawurlencode((string) $slug));
        }
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
        try {
            $this->renderListing($scope, $scopeType);
        } catch (Throwable $exception) {
            app_log_exception('Storefront product listing search failed', $exception);
            $this->renderSearchUnavailable();
        }
    }

    private function renderListing(array $scope = [], string $scopeType = ''): void
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
        if (app_utf8_length($query) > 100) {
            $query = app_utf8_substr($query, 0, 100);
            $filterErrors[] = 'Từ khóa tìm kiếm đã được giới hạn ở 100 ký tự.';
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
            $conditions[] = $this->productSearchCondition($query, $parameters, 'search');
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
            'SELECT p.id, p.name, p.slug, p.price, p.sale_price, p.unit, p.sale_unit, p.pack_size, p.pack_size_unit,
                    p.min_stock, p.short_description,
                    r.name AS region_name,
                    COALESCE(stock.quantity, 0) AS actual_stock,
                    COALESCE(images.image_url, \'/public/images/no-image.svg\') AS image_url,
                    COALESCE(review_summary.average_rating, 0) AS average_rating,
                    COALESCE(review_summary.review_count, 0) AS review_count,
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
                 SELECT product_id, AVG(rating) AS average_rating, COUNT(*) AS review_count
                 FROM reviews WHERE status = \'published\' GROUP BY product_id
             ) review_summary ON review_summary.product_id = p.id
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
            'metaDescription' => app_utf8_substr(strip_tags($description), 0, 160),
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
            'wishlistIds' => $this->currentUserId() === null
                ? []
                : (new CustomerEngagementService())->wishlistProductIds((int) $this->currentUserId()),
        ]);
    }

    private function fetchProducts(string $extraCondition, array $parameters, string $order, int $limit): array
    {
        $statement = Database::connection()->prepare(
            'SELECT p.id, p.name, p.slug, p.price, p.sale_price, p.unit, p.sale_unit, p.pack_size, p.pack_size_unit,
                    p.min_stock, p.short_description,
                    COALESCE(stock.quantity, 0) AS actual_stock,
                    COALESCE(images.image_url, \'/public/images/no-image.svg\') AS image_url,
                    COALESCE(review_summary.average_rating, 0) AS average_rating,
                    COALESCE(review_summary.review_count, 0) AS review_count,
                    r.name AS region_name
             FROM products p
             LEFT JOIN ' . $this->availableStockSubquery() . ' stock ON stock.product_id = p.id
             LEFT JOIN (
                 SELECT product_id, SUBSTRING_INDEX(GROUP_CONCAT(image_url ORDER BY is_primary DESC, sort_order, id), \',\', 1) AS image_url
                 FROM product_images GROUP BY product_id
             ) images ON images.product_id = p.id
             LEFT JOIN (
                 SELECT product_id, AVG(rating) AS average_rating, COUNT(*) AS review_count
                 FROM reviews WHERE status = \'published\' GROUP BY product_id
             ) review_summary ON review_summary.product_id = p.id
             LEFT JOIN (
                 SELECT oi.product_id, SUM(oi.quantity) AS sold
                 FROM order_items oi JOIN orders o ON o.id = oi.order_id
                 WHERE o.status NOT IN (\'cancelled\', \'refunded\') AND oi.product_id IS NOT NULL
                 GROUP BY oi.product_id
             ) sales ON sales.product_id = p.id
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

    private function homepageCategories(PDO $database): array
    {
        $statement = $database->prepare(
            "SELECT c.id, c.parent_id, c.name, c.slug, c.description, c.image_path, c.sort_order,
                    COUNT(DISTINCT p.id) AS product_count,
                    (
                        SELECT image_product.id
                        FROM products image_product
                        JOIN product_images category_image ON category_image.product_id = image_product.id
                        WHERE image_product.category_id = c.id
                          AND image_product.status = 'active' AND image_product.deleted_at IS NULL
                          AND EXISTS (
                              SELECT 1 FROM regions image_region
                              WHERE image_region.id = image_product.region_id AND image_region.is_active = 1
                          )
                        ORDER BY image_product.id, category_image.is_primary DESC,
                                 category_image.sort_order, category_image.id
                        LIMIT 1
                    ) AS thumbnail_product_id,
                    (
                        SELECT category_image.image_url
                        FROM products image_product
                        JOIN product_images category_image ON category_image.product_id = image_product.id
                        WHERE image_product.category_id = c.id
                          AND image_product.status = 'active' AND image_product.deleted_at IS NULL
                          AND EXISTS (
                              SELECT 1 FROM regions image_region
                              WHERE image_region.id = image_product.region_id AND image_region.is_active = 1
                          )
                        ORDER BY image_product.id, category_image.is_primary DESC,
                                 category_image.sort_order, category_image.id
                        LIMIT 1
                    ) AS thumbnail_url
             FROM categories c
             LEFT JOIN products p
               ON p.category_id = c.id AND p.status = 'active' AND p.deleted_at IS NULL
              AND EXISTS (
                  SELECT 1 FROM regions active_region
                  WHERE active_region.id = p.region_id AND active_region.is_active = 1
              )
             WHERE c.is_active = 1
             GROUP BY c.id, c.parent_id, c.name, c.slug, c.description, c.image_path, c.sort_order
             ORDER BY c.sort_order, c.name"
        );
        $statement->execute();
        $categoryRows = $statement->fetchAll();

        $categoryById = [];
        foreach ($categoryRows as $category) {
            $category['product_count'] = 0;
            $category['thumbnail_product_id'] = null;
            $category['thumbnail_url'] = null;
            $categoryById[(int) $category['id']] = $category;
        }

        foreach ($categoryRows as $category) {
            $rootId = (int) $category['id'];
            $visited = [];
            while ($categoryById[$rootId]['parent_id'] !== null) {
                if (isset($visited[$rootId])) {
                    $rootId = 0;
                    break;
                }
                $visited[$rootId] = true;
                $parentId = (int) $categoryById[$rootId]['parent_id'];
                if (!isset($categoryById[$parentId])) {
                    $rootId = 0;
                    break;
                }
                $rootId = $parentId;
            }

            if ($rootId === 0 || !isset($categoryById[$rootId]) || $categoryById[$rootId]['parent_id'] !== null) {
                continue;
            }

            $categoryById[$rootId]['product_count'] += (int) $category['product_count'];
            if ($category['thumbnail_product_id'] !== null) {
                $thumbnailProductId = (int) $category['thumbnail_product_id'];
                $currentThumbnailId = $categoryById[$rootId]['thumbnail_product_id'];
                if ($currentThumbnailId === null || $thumbnailProductId < (int) $currentThumbnailId) {
                    $categoryById[$rootId]['thumbnail_product_id'] = $thumbnailProductId;
                    $categoryById[$rootId]['thumbnail_url'] = $category['thumbnail_url'];
                }
            }
        }

        foreach ($categoryById as &$category) {
            if ($category['parent_id'] === null
                && ImageUploadService::existsStored((string) ($category['image_path'] ?? ''), 'categories')) {
                $category['thumbnail_url'] = $category['image_path'];
            }
        }
        unset($category);

        $categories = array_values(array_filter(
            $categoryById,
            static fn (array $category): bool => $category['parent_id'] === null
        ));
        return array_slice($categories, 0, 8);
    }

    private function homepageRegions(PDO $database): array
    {
        $statement = $database->prepare(
            'SELECT home_region.id AS region_id, home_region.name AS region_name,
                    home_region.slug AS region_slug, home_region.description AS region_description,
                    home_region.image_path AS region_image_path,
                    p.id AS product_id, p.name AS product_name, p.created_at,
                    COALESCE(stock.quantity, 0) AS available_stock,
                    COALESCE(sales.sold, 0) AS sold_quantity,
                    images.image_url, active_farm.id AS farm_id
             FROM regions home_region
             LEFT JOIN (
                 SELECT id AS region_id, id AS root_id
                 FROM regions WHERE level = 1 AND is_active = 1
                 UNION ALL
                 SELECT id AS region_id, parent_id AS root_id
                 FROM regions WHERE level = 2 AND is_active = 1
             ) region_scope ON region_scope.root_id = home_region.id
             LEFT JOIN products p
               ON p.region_id = region_scope.region_id
              AND p.status = \'active\' AND p.deleted_at IS NULL
              AND EXISTS (
                  SELECT 1 FROM categories active_category
                  WHERE active_category.id = p.category_id AND active_category.is_active = 1
              )
             LEFT JOIN ' . $this->availableStockSubquery() . ' stock ON stock.product_id = p.id
             LEFT JOIN (
                 SELECT oi.product_id, SUM(oi.quantity) AS sold
                 FROM order_items oi JOIN orders o ON o.id = oi.order_id
                 WHERE o.status NOT IN (\'cancelled\', \'refunded\') AND oi.product_id IS NOT NULL
                 GROUP BY oi.product_id
             ) sales ON sales.product_id = p.id
             LEFT JOIN (
                 SELECT product_id,
                        SUBSTRING_INDEX(GROUP_CONCAT(image_url ORDER BY is_primary DESC, sort_order, id), \',\', 1) AS image_url
                 FROM product_images GROUP BY product_id
             ) images ON images.product_id = p.id
             LEFT JOIN farms active_farm
               ON active_farm.id = p.farm_id AND active_farm.status = \'active\'
             WHERE home_region.level = 1 AND home_region.is_active = 1
             ORDER BY home_region.id, COALESCE(sales.sold, 0) DESC, p.created_at DESC, p.id DESC'
        );
        $statement->execute();
        $regionRows = $statement->fetchAll();

        $regions = [];
        foreach ($regionRows as $row) {
            $regionId = (int) $row['region_id'];
            if (!isset($regions[$regionId])) {
                $regions[$regionId] = [
                    'name' => $row['region_name'],
                    'slug' => $row['region_slug'],
                    'description' => $row['region_description'],
                    'image_path' => ImageUploadService::existsStored(
                        (string) ($row['region_image_path'] ?? ''),
                        'regions'
                    ) ? $row['region_image_path'] : null,
                    'product_count' => 0,
                    'farm_count' => 0,
                    'images' => [],
                    'highlight_products' => [],
                    '_product_ids' => [],
                    '_farm_ids' => [],
                ];
            }

            if ($row['product_id'] === null) {
                continue;
            }

            $productId = (int) $row['product_id'];
            if (isset($regions[$regionId]['_product_ids'][$productId])) {
                continue;
            }
            $regions[$regionId]['_product_ids'][$productId] = true;
            $regions[$regionId]['product_count']++;

            if ($row['farm_id'] !== null) {
                $regions[$regionId]['_farm_ids'][(int) $row['farm_id']] = true;
            }
            if ($row['image_url'] !== null && count($regions[$regionId]['images']) < 3) {
                $regions[$regionId]['images'][] = [
                    'name' => $row['product_name'],
                    'image_url' => $row['image_url'],
                ];
            }
            if ((int) $row['available_stock'] > 0 && count($regions[$regionId]['highlight_products']) < 3) {
                $regions[$regionId]['highlight_products'][] = [
                    'name' => $row['product_name'],
                ];
            }
        }

        foreach ($regions as &$region) {
            $region['farm_count'] = count($region['_farm_ids']);
            unset($region['_farm_ids'], $region['_product_ids']);
        }
        unset($region);

        return array_values($regions);
    }

    private function productSearchCondition(string $query, array &$parameters, string $prefix): string
    {
        $normalized = str_replace(['đ', 'Đ'], 'd', $query);
        $escaped = str_replace(['=', '%', '_'], ['==', '=%', '=_'], $normalized);
        $fields = [
            'name' => 'p.name',
            'sku' => 'p.sku',
            'category' => 'c.name',
            'region' => 'r.name',
        ];
        $conditions = [];
        foreach ($fields as $key => $field) {
            $parameter = $prefix . '_' . $key;
            $parameters[$parameter] = '%' . $escaped . '%';
            $conditions[] = "REPLACE(REPLACE(
                $field COLLATE utf8mb4_unicode_ci,
                _utf8mb4'đ' COLLATE utf8mb4_unicode_ci,
                _utf8mb4'd' COLLATE utf8mb4_unicode_ci
            ), _utf8mb4'Đ' COLLATE utf8mb4_unicode_ci, _utf8mb4'd' COLLATE utf8mb4_unicode_ci)
                COLLATE utf8mb4_unicode_ci LIKE :$parameter COLLATE utf8mb4_unicode_ci ESCAPE '='";
        }

        return '(' . implode(' OR ', $conditions) . ')';
    }

    private function findExactBatchCode(string $query): ?string
    {
        $normalized = preg_replace('/[\s\x{00A0}]+/u', '', $query);
        if ($normalized === null || $normalized === '') {
            return null;
        }

        $statement = Database::connection()->prepare(
            "SELECT batch_code FROM product_batches
             WHERE LOWER(
                 REPLACE(
                     REPLACE(
                         REPLACE(
                             REPLACE(
                                 REPLACE(
                                     REPLACE(
                                         REPLACE(
                                             batch_code COLLATE utf8mb4_unicode_ci,
                                             _utf8mb4' ' COLLATE utf8mb4_unicode_ci,
                                             _utf8mb4'' COLLATE utf8mb4_unicode_ci
                                         ),
                                         CONVERT(CHAR(9) USING utf8mb4) COLLATE utf8mb4_unicode_ci,
                                         _utf8mb4'' COLLATE utf8mb4_unicode_ci
                                     ),
                                     CONVERT(CHAR(10) USING utf8mb4) COLLATE utf8mb4_unicode_ci,
                                     _utf8mb4'' COLLATE utf8mb4_unicode_ci
                                 ),
                                 CONVERT(CHAR(11) USING utf8mb4) COLLATE utf8mb4_unicode_ci,
                                 _utf8mb4'' COLLATE utf8mb4_unicode_ci
                             ),
                             CONVERT(CHAR(12) USING utf8mb4) COLLATE utf8mb4_unicode_ci,
                             _utf8mb4'' COLLATE utf8mb4_unicode_ci
                         ),
                         CONVERT(CHAR(13) USING utf8mb4) COLLATE utf8mb4_unicode_ci,
                         _utf8mb4'' COLLATE utf8mb4_unicode_ci
                     ),
                     CONVERT(0xC2A0 USING utf8mb4) COLLATE utf8mb4_unicode_ci,
                     _utf8mb4'' COLLATE utf8mb4_unicode_ci
                 ) COLLATE utf8mb4_unicode_ci
             ) COLLATE utf8mb4_unicode_ci = :batch_code COLLATE utf8mb4_unicode_ci
             LIMIT 2"
        );
        $statement->execute(['batch_code' => app_utf8_lower($normalized)]);
        $matches = $statement->fetchAll(PDO::FETCH_COLUMN);
        return count($matches) === 1 ? (string) $matches[0] : null;
    }

    private function renderSearchUnavailable(): void
    {
        http_response_code(503);
        $this->view('errors/search-unavailable', [], 'error');
    }

    private function recordRecentlyViewed(int $productId): void
    {
        Session::start();
        $stored = $_SESSION['recent_product_ids'] ?? [];
        $recent = array_values(array_filter(
            is_array($stored) ? $stored : [],
            static fn (mixed $id): bool => filter_var($id, FILTER_VALIDATE_INT) !== false && (int) $id > 0
        ));
        $recent = array_values(array_unique(array_merge([$productId], array_map('intval', $recent))));
        $_SESSION['recent_product_ids'] = array_slice($recent, 0, 8);
    }

    private function recentlyViewedProducts(?int $excludeProductId = null): array
    {
        Session::start();
        $stored = $_SESSION['recent_product_ids'] ?? [];
        $ids = array_values(array_filter(
            array_map('intval', is_array($stored) ? $stored : []),
            static fn (int $id): bool => $id > 0 && $id !== $excludeProductId
        ));
        if ($ids === []) {
            return [];
        }
        $conditions = [];
        $parameters = [];
        foreach (array_slice($ids, 0, 8) as $index => $id) {
            $conditions[] = ':recent_' . $index;
            $parameters['recent_' . $index] = $id;
        }
        return $this->fetchProducts(
            'p.id IN (' . implode(', ', $conditions) . ')',
            $parameters,
            'FIELD(p.id, ' . implode(', ', array_slice($ids, 0, 8)) . ')',
            8
        );
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
