<?php

declare(strict_types=1);

final class ReportService
{
    private const VALID_ORDER = "o.status NOT IN ('cancelled','refunded')";
    private PDO $database;

    public function __construct(?PDO $database = null)
    {
        $this->database = $database ?? Database::connection();
    }

    public function dashboard(): array
    {
        $revenue = $this->database->query(
            'SELECT
                COALESCE(SUM(CASE WHEN placed_at >= CURDATE() THEN total_amount ELSE 0 END), 0) AS today,
                COALESCE(SUM(CASE WHEN placed_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) THEN total_amount ELSE 0 END), 0) AS last_7_days,
                COALESCE(SUM(CASE WHEN placed_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) THEN total_amount ELSE 0 END), 0) AS last_30_days
             FROM orders o
             WHERE ' . self::VALID_ORDER
        )->fetch();

        $statusCounts = array_fill_keys(
            ['pending', 'confirmed', 'processing', 'shipping', 'completed', 'cancelled', 'refunded'],
            0
        );
        foreach ($this->database->query('SELECT status, COUNT(*) AS quantity FROM orders GROUP BY status')->fetchAll() as $row) {
            $statusCounts[(string) $row['status']] = (int) $row['quantity'];
        }

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

        $daily = $this->database->query(
            'SELECT DATE(o.placed_at) AS report_date, SUM(o.total_amount) AS revenue
             FROM orders o
             WHERE ' . self::VALID_ORDER . '
               AND o.placed_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
               AND o.placed_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
             GROUP BY DATE(o.placed_at)
             ORDER BY report_date'
        )->fetchAll();
        $dailyByDate = [];
        foreach ($daily as $row) {
            $dailyByDate[(string) $row['report_date']] = (float) $row['revenue'];
        }
        $dailyRevenue = [];
        for ($offset = 13; $offset >= 0; $offset--) {
            $date = date('Y-m-d', strtotime('-' . $offset . ' days'));
            $dailyRevenue[] = ['date' => $date, 'revenue' => $dailyByDate[$date] ?? 0.0];
        }

        $regionalRevenue = $this->database->query(
            'SELECT COALESCE(parent.name, region.name, NULLIF(o.region_name, \'\'), \'Chưa phân vùng\') AS region_name,
                    SUM(o.total_amount) AS revenue
             FROM orders o
             LEFT JOIN regions region ON region.id = o.region_id
             LEFT JOIN regions parent ON parent.id = region.parent_id
             WHERE ' . self::VALID_ORDER . '
               AND o.placed_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
             GROUP BY COALESCE(parent.name, region.name, NULLIF(o.region_name, \'\'), \'Chưa phân vùng\')
             ORDER BY revenue DESC
             LIMIT 8'
        )->fetchAll();

        $topProducts = $this->database->query(
            'SELECT oi.product_name, SUM(oi.quantity) AS quantity, SUM(oi.line_total) AS revenue
             FROM order_items oi
             JOIN orders o ON o.id = oi.order_id
             WHERE ' . self::VALID_ORDER . '
             GROUP BY oi.product_name
             ORDER BY quantity DESC, revenue DESC
             LIMIT 10'
        )->fetchAll();
        $topCustomers = $this->database->query(
            'SELECT u.name, u.email, COUNT(o.id) AS order_count, SUM(o.total_amount) AS revenue
             FROM orders o
             JOIN users u ON u.id = o.user_id
             WHERE ' . self::VALID_ORDER . '
             GROUP BY u.id, u.name, u.email
             ORDER BY revenue DESC
             LIMIT 10'
        )->fetchAll();

        return [
            'revenue' => array_map('floatval', $revenue ?: ['today' => 0, 'last_7_days' => 0, 'last_30_days' => 0]),
            'statusCounts' => $statusCounts,
            'pendingOrders' => $statusCounts['pending'],
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
                'title' => 'Doanh thu theo ngày',
                'columns' => ['report_date' => 'Ngày', 'order_count' => 'Số đơn', 'revenue' => 'Doanh thu'],
            ],
            'products' => [
                'title' => 'Doanh thu theo sản phẩm',
                'columns' => ['product_name' => 'Sản phẩm', 'quantity' => 'Đã bán', 'revenue' => 'Doanh thu'],
            ],
            'regions' => [
                'title' => 'Doanh thu theo vùng miền',
                'columns' => ['region_name' => 'Vùng miền', 'order_count' => 'Số đơn', 'revenue' => 'Doanh thu'],
            ],
            'payments' => [
                'title' => 'Doanh thu theo phương thức thanh toán',
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
        $parameters = ['from_date' => $from . ' 00:00:00', 'to_date' => date('Y-m-d 00:00:00', strtotime($to . ' +1 day'))];
        $valid = self::VALID_ORDER;
        $dateRange = 'o.placed_at >= :from_date AND o.placed_at < :to_date';

        switch ($type) {
            case 'revenue':
                $sql = 'SELECT DATE(o.placed_at) AS report_date, COUNT(*) AS order_count,
                               SUM(o.total_amount) AS revenue
                        FROM orders o
                        WHERE ' . $valid . ' AND ' . $dateRange . '
                        GROUP BY DATE(o.placed_at)
                        ORDER BY report_date DESC';
                break;
            case 'products':
                $sql = 'SELECT oi.product_name, SUM(oi.quantity) AS quantity, SUM(oi.line_total) AS revenue
                        FROM order_items oi JOIN orders o ON o.id = oi.order_id
                        WHERE ' . $valid . ' AND ' . $dateRange . '
                        GROUP BY oi.product_name
                        ORDER BY revenue DESC, quantity DESC';
                break;
            case 'regions':
                $sql = 'SELECT COALESCE(parent.name, region.name, NULLIF(o.region_name, \'\'), \'Chưa phân vùng\') AS region_name,
                               COUNT(*) AS order_count, SUM(o.total_amount) AS revenue
                        FROM orders o
                        LEFT JOIN regions region ON region.id = o.region_id
                        LEFT JOIN regions parent ON parent.id = region.parent_id
                        WHERE ' . $valid . ' AND ' . $dateRange . '
                        GROUP BY COALESCE(parent.name, region.name, NULLIF(o.region_name, \'\'), \'Chưa phân vùng\')
                        ORDER BY revenue DESC';
                break;
            case 'payments':
                $sql = 'SELECT COALESCE(payment.method, \'unknown\') AS method_name, COUNT(*) AS order_count,
                               SUM(o.total_amount) AS revenue
                        FROM orders o
                        LEFT JOIN payments payment ON payment.id = (
                            SELECT MAX(latest.id) FROM payments latest WHERE latest.order_id = o.id
                        )
                        WHERE ' . $valid . ' AND ' . $dateRange . '
                        GROUP BY COALESCE(payment.method, \'unknown\')
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
                $sql = 'SELECT campaign.name AS campaign_name, p.name AS product_name, item.quota,
                               item.sold_quantity,
                               COALESCE(SUM(CASE WHEN o.id IS NOT NULL THEN oi.line_total ELSE 0 END), 0) AS revenue
                        FROM flash_sale_items item
                        JOIN flash_sales campaign ON campaign.id = item.flash_sale_id
                        JOIN products p ON p.id = item.product_id
                        LEFT JOIN order_items oi ON oi.flash_sale_item_id = item.id
                        LEFT JOIN orders o ON o.id = oi.order_id AND ' . $valid . '
                        WHERE campaign.starts_at < :to_date AND campaign.ends_at >= :from_date
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
