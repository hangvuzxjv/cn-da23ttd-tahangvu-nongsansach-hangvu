<?php

declare(strict_types=1);

final class ReportService
{
    private PDO $database;

    public function __construct(?PDO $database = null)
    {
        $this->database = $database ?? Database::connection();
    }

    public function dashboard(): array
    {
        $revenue = (new PaymentRevenueService($this->database))->dashboardTotals();

        $statusCounts = array_fill_keys(
            ['pending', 'confirmed', 'processing', 'shipping', 'completed', 'cancelled', 'refunded', 'delivery_failed'],
            0
        );
        foreach ($this->database->query('SELECT status, COUNT(*) AS quantity FROM orders GROUP BY status')->fetchAll() as $row) {
            $statusCounts[(string) $row['status']] = (int) $row['quantity'];
        }
        $pendingPaymentOrders = (int) $this->database->query(
            "SELECT COUNT(*)
             FROM orders o
             WHERE o.status = 'pending' AND o.payment_status = 'pending'
               AND EXISTS (
                   SELECT 1 FROM payments p
                   WHERE p.order_id = o.id AND p.method IN ('bank_transfer', 'vnpay')
               )"
        )->fetchColumn();
        $actionablePendingOrders = max(0, $statusCounts['pending'] - $pendingPaymentOrders);

        $newCustomers = (int) $this->database->query(
            "SELECT COUNT(*) FROM users WHERE role = 'customer' AND created_at >= CURDATE()"
        )->fetchColumn();
        $lowStock = (int) $this->database->query(
            'SELECT COUNT(*) FROM products WHERE deleted_at IS NULL AND total_stock < min_stock'
        )->fetchColumn();
        $nearExpiry = (int) $this->database->query(
            'SELECT COUNT(*) FROM product_batches
             WHERE status = \'selling\' AND quantity_remaining > 0
               AND expires_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)'
        )->fetchColumn();

        $timezone = new DateTimeZone('Asia/Ho_Chi_Minh');
        $today = new DateTimeImmutable('today', $timezone);
        $tomorrow = $today->modify('+1 day');
        $chartStart = $today->modify('-13 days');
        $revenueEvents = PaymentRevenueService::revenueEventsSubquery();
        $dailyQuery = $this->database->prepare(
            'SELECT DATE(event.event_at) AS report_date, SUM(event.amount) AS revenue
             FROM ' . $revenueEvents . ' event
             WHERE event.event_at >= :from_date AND event.event_at < :to_date
             GROUP BY DATE(event.event_at)
             ORDER BY report_date'
        );
        $dailyQuery->execute([
            'from_date' => $chartStart->format('Y-m-d H:i:s'),
            'to_date' => $tomorrow->format('Y-m-d H:i:s'),
        ]);
        $daily = $dailyQuery->fetchAll();
        $dailyByDate = [];
        foreach ($daily as $row) {
            $dailyByDate[(string) $row['report_date']] = (float) $row['revenue'];
        }
        $dailyRevenue = [];
        for ($offset = 13; $offset >= 0; $offset--) {
            $date = $today->modify('-' . $offset . ' days')->format('Y-m-d');
            $dailyRevenue[] = ['date' => $date, 'revenue' => $dailyByDate[$date] ?? 0.0];
        }

        $regionalQuery = $this->database->prepare(
            'SELECT COALESCE(parent.name, region.name, NULLIF(o.region_name, \'\'), \'Chưa phân vùng\') AS region_name,
                    SUM(event.amount) AS revenue
             FROM orders o
             JOIN ' . $revenueEvents . ' event ON event.order_id = o.id
             LEFT JOIN regions region ON region.id = o.region_id
             LEFT JOIN regions parent ON parent.id = region.parent_id
             WHERE event.event_at >= :from_date AND event.event_at < :to_date
             GROUP BY COALESCE(parent.name, region.name, NULLIF(o.region_name, \'\'), \'Chưa phân vùng\')
             ORDER BY revenue DESC
             LIMIT 8'
        );
        $regionalQuery->execute([
            'from_date' => $today->modify('-29 days')->format('Y-m-d H:i:s'),
            'to_date' => $tomorrow->format('Y-m-d H:i:s'),
        ]);
        $regionalRevenue = $regionalQuery->fetchAll();

        $productEvents = PaymentRevenueService::productRevenueEventsSubquery();
        $topProducts = $this->database->query(
            'SELECT event.product_name, SUM(event.quantity) AS quantity, SUM(event.amount) AS revenue
             FROM ' . $productEvents . ' event
             GROUP BY event.product_name
             ORDER BY quantity DESC, revenue DESC
             LIMIT 10'
        )->fetchAll();
        $paidOrders = PaymentRevenueService::paidOrdersSubquery();
        $topCustomers = $this->database->query(
            'SELECT u.name, u.email, COUNT(DISTINCT o.id) AS order_count, SUM(received.received_amount) AS revenue
             FROM orders o
             JOIN users u ON u.id = o.user_id
             JOIN ' . $paidOrders . ' received ON received.order_id = o.id
             GROUP BY u.id, u.name, u.email
             ORDER BY revenue DESC
             LIMIT 10'
        )->fetchAll();

        return [
            'revenue' => array_map('floatval', $revenue ?: ['today' => 0, 'last_7_days' => 0, 'last_30_days' => 0]),
            'statusCounts' => $statusCounts,
            'pendingOrders' => $actionablePendingOrders,
            'pendingPaymentOrders' => $pendingPaymentOrders,
            'newCustomers' => $newCustomers,
            'lowStock' => $lowStock,
            'nearExpiry' => $nearExpiry,
            'dailyRevenue' => $dailyRevenue,
            'regionalRevenue' => $regionalRevenue,
            'topProducts' => $topProducts,
            'topCustomers' => $topCustomers,
        ];
    }

    public static function definitions(): array
    {
        return [
            'revenue' => [
                'title' => 'Tiền đã nhận theo ngày',
                'columns' => ['report_date' => 'Ngày', 'order_count' => 'Số đơn', 'revenue' => 'Doanh thu'],
            ],
            'products' => [
                'title' => 'Doanh thu sản phẩm sau hoàn tiền',
                'columns' => ['product_name' => 'Sản phẩm', 'quantity' => 'Đã bán', 'revenue' => 'Doanh thu'],
            ],
            'regions' => [
                'title' => 'Tiền đã nhận theo vùng miền',
                'columns' => ['region_name' => 'Vùng miền', 'order_count' => 'Số đơn', 'revenue' => 'Doanh thu'],
            ],
            'payments' => [
                'title' => 'Tiền đã nhận theo phương thức thanh toán',
                'columns' => ['method_name' => 'Phương thức', 'order_count' => 'Số đơn', 'revenue' => 'Doanh thu'],
            ],
            'inventory' => [
                'title' => 'Nhập - xuất - tồn theo lô',
                'columns' => [
                    'batch_code' => 'Mã lô', 'product_name' => 'Sản phẩm', 'expires_at' => 'Hạn dùng',
                    'expiry_status' => 'Tình trạng hạn dùng',
                    'opening_stock' => 'Tồn đầu kỳ', 'incoming' => 'Nhập / trả', 'outgoing' => 'Xuất',
                    'closing_stock' => 'Tồn hiện tại',
                ],
            ],
            'flash_sales' => [
                'title' => 'Hiệu quả Flash Sale',
                'columns' => [
                    'campaign_name' => 'Chương trình', 'product_name' => 'Sản phẩm', 'quota' => 'Suất',
                    'sold_quantity' => 'Đã bán', 'revenue' => 'Doanh thu',
                ],
            ],
        ];
    }

    public function report(string $type, string $from, string $to, int $page = 1, int $perPage = 30): array
    {
        if (!isset(self::definitions()[$type])) {
            throw new InvalidArgumentException('Loại báo cáo không hợp lệ.');
        }
        [$sql, $parameters] = $this->queryParts($type, $from, $to);
        $count = $this->database->prepare('SELECT COUNT(*) FROM (' . $sql . ') report_rows');
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $query = $this->database->prepare($sql . ' LIMIT :limit OFFSET :offset');
        foreach ($parameters as $key => $value) {
            $query->bindValue(':' . $key, $value);
        }
        $query->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $query->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $query->execute();

        return [
            'definition' => self::definitions()[$type],
            'rows' => $query->fetchAll(),
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
        ];
    }

    public function stream(string $type, string $from, string $to): Generator
    {
        if (!isset(self::definitions()[$type])) {
            throw new InvalidArgumentException('Loại báo cáo không hợp lệ.');
        }
        [$sql, $parameters] = $this->queryParts($type, $from, $to);
        return (function () use ($sql, $parameters): Generator {
            $database = $this->database;
            $wasBuffered = $database->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY);
            $query = null;
            $database->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
            try {
                $query = $database->prepare($sql);
                $query->execute($parameters);
                while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                    yield $row;
                }
            } finally {
                if ($query instanceof PDOStatement) {
                    $query->closeCursor();
                }
                $database->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, $wasBuffered);
            }
        })();
    }

    public static function csvText(mixed $value): string
    {
        $text = (string) $value;
        return preg_match('/\A[\x00-\x20]*[=+\-@]/', $text) === 1 ? "'" . $text : $text;
    }

    private function queryParts(string $type, string $from, string $to): array
    {
        $parameters = PaymentRevenueService::dateBounds($from, $to);
        $dateRange = 'event.event_at >= :from_date AND event.event_at < :to_date';
        $revenueEvents = PaymentRevenueService::revenueEventsSubquery();
        $productEvents = PaymentRevenueService::productRevenueEventsSubquery();

        switch ($type) {
            case 'revenue':
                $sql = 'SELECT DATE(event.event_at) AS report_date,
                               COUNT(DISTINCT CASE WHEN event.event_type = \'payment\' THEN event.order_id END) AS order_count,
                               SUM(event.amount) AS revenue
                        FROM ' . $revenueEvents . ' event
                        WHERE ' . $dateRange . '
                        GROUP BY DATE(event.event_at)
                        ORDER BY report_date DESC';
                break;
            case 'products':
                $sql = 'SELECT event.product_name, SUM(event.quantity) AS quantity, SUM(event.amount) AS revenue
                        FROM ' . $productEvents . ' event
                        WHERE ' . $dateRange . '
                        GROUP BY event.product_name
                        ORDER BY revenue DESC, quantity DESC';
                break;
            case 'regions':
                $sql = 'SELECT COALESCE(parent.name, region.name, NULLIF(o.region_name, \'\'), \'Chưa phân vùng\') AS region_name,
                               COUNT(DISTINCT CASE WHEN event.event_type = \'payment\' THEN event.order_id END) AS order_count,
                               SUM(event.amount) AS revenue
                        FROM orders o
                        JOIN ' . $revenueEvents . ' event ON event.order_id = o.id
                        LEFT JOIN regions region ON region.id = o.region_id
                        LEFT JOIN regions parent ON parent.id = region.parent_id
                        WHERE ' . $dateRange . '
                        GROUP BY COALESCE(parent.name, region.name, NULLIF(o.region_name, \'\'), \'Chưa phân vùng\')
                        ORDER BY revenue DESC';
                break;
            case 'payments':
                $sql = 'SELECT event.method AS method_name,
                               COUNT(DISTINCT CASE WHEN event.event_type = \'payment\' THEN event.order_id END) AS order_count,
                               SUM(event.amount) AS revenue
                        FROM ' . $revenueEvents . ' event
                        WHERE ' . $dateRange . '
                        GROUP BY event.method
                        ORDER BY revenue DESC';
                break;
            case 'inventory':
                $parameters = [
                    'movement_from' => $parameters['from_date'],
                    'movement_to' => $parameters['to_date'],
                ];
                $sql = 'SELECT b.batch_code, p.name AS product_name, b.expires_at,
                               CASE WHEN b.expires_at < CURDATE() THEN \'Đã hết hạn\'
                                    WHEN b.expires_at <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN \'Sắp hết hạn\'
                                    ELSE \'Còn hạn\' END AS expiry_status,
                               b.quantity_remaining AS closing_stock,
                               COALESCE(m.incoming, 0) AS incoming, COALESCE(m.outgoing, 0) AS outgoing,
                               b.quantity_remaining - COALESCE(m.net_change, 0) AS opening_stock
                        FROM product_batches b
                        JOIN products p ON p.id = b.product_id
                        LEFT JOIN (
                            SELECT batch_id,
                                SUM(CASE WHEN movement_type IN (\'in\',\'return\') THEN ABS(quantity)
                                         WHEN movement_type = \'adjustment\' AND quantity > 0 THEN quantity ELSE 0 END) AS incoming,
                                SUM(CASE WHEN movement_type = \'out\' THEN ABS(quantity)
                                         WHEN movement_type = \'adjustment\' AND quantity < 0 THEN ABS(quantity) ELSE 0 END) AS outgoing,
                                SUM(CASE WHEN movement_type IN (\'in\',\'return\') THEN ABS(quantity)
                                         WHEN movement_type = \'out\' THEN -ABS(quantity)
                                         ELSE quantity END) AS net_change
                            FROM inventory_movements
                            WHERE created_at >= :movement_from AND created_at < :movement_to
                            GROUP BY batch_id
                        ) m ON m.batch_id = b.id
                        ORDER BY b.expires_at, b.id';
                break;
            case 'flash_sales':
                $parameters['campaign_from'] = $parameters['from_date'];
                $parameters['campaign_to'] = $parameters['to_date'];
                $sql = 'SELECT campaign.name AS campaign_name, p.name AS product_name, item.quota,
                               item.sold_quantity,
                               COALESCE(SUM(event.amount), 0) AS revenue
                        FROM flash_sale_items item
                        JOIN flash_sales campaign ON campaign.id = item.flash_sale_id
                        JOIN products p ON p.id = item.product_id
                        LEFT JOIN ' . $productEvents . ' event
                          ON event.flash_sale_item_id = item.id AND ' . $dateRange . '
                        WHERE campaign.starts_at < :campaign_to AND campaign.ends_at >= :campaign_from
                        GROUP BY campaign.id, campaign.name, campaign.starts_at,
                                 item.id, p.id, p.name, item.quota, item.sold_quantity
                        ORDER BY campaign.starts_at DESC, campaign.name, p.name';
                break;
            default:
                throw new InvalidArgumentException('Loại báo cáo không hợp lệ.');
        }

        return [$sql, $parameters];
    }
}
