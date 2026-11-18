<?php

declare(strict_types=1);

final class PaymentRevenueService
{
    private const TIMEZONE = 'Asia/Ho_Chi_Minh';
    private PDO $database;

    public function __construct(?PDO $database = null)
    {
        $this->database = $database ?? Database::connection();
    }

    public static function paidCondition(string $alias = 'p'): string
    {
        if (preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $alias) !== 1) {
            throw new InvalidArgumentException('Payment table alias is invalid.');
        }
        return $alias . ".status = 'paid'";
    }

    public static function paidOrdersSubquery(): string
    {
        return '(SELECT event.order_id, SUM(event.amount) AS received_amount,
                       MAX(CASE WHEN event.event_type = \'payment\' THEN event.event_at END) AS paid_at
                FROM ' . self::revenueEventsSubquery() . ' event
                GROUP BY event.order_id
                HAVING SUM(event.amount) > 0)';
    }

    public static function revenueEventsSubquery(): string
    {
        return '(SELECT payment.order_id, payment.method, payment.paid_at AS event_at,
                       payment.amount AS amount, \'payment\' AS event_type, payment.refund_required
                FROM payments payment
                WHERE payment.status IN (\'paid\', \'refunded\')
                  AND payment.paid_at IS NOT NULL
                  AND (payment.status = \'paid\' OR payment.refunded_at IS NOT NULL)
                UNION ALL
                SELECT returned.order_id,
                       COALESCE((
                           SELECT settled.method FROM payments settled
                           WHERE settled.order_id = returned.order_id
                             AND settled.status IN (\'paid\', \'refunded\')
                           ORDER BY settled.paid_at DESC, settled.id DESC LIMIT 1
                       ), \'cod\') AS method,
                       returned.refunded_at AS event_at,
                       -returned.refund_amount AS amount, \'refund\' AS event_type, 0 AS refund_required
                FROM order_returns returned
                WHERE returned.status = \'refunded\' AND returned.refunded_at IS NOT NULL
                  AND returned.refund_amount > 0
                UNION ALL
                SELECT payment.order_id, payment.method, payment.refunded_at AS event_at,
                       -payment.amount AS amount, \'refund\' AS event_type, 0 AS refund_required
                FROM payments payment
                WHERE payment.status = \'refunded\' AND payment.refunded_at IS NOT NULL
                  AND NOT EXISTS (
                      SELECT 1 FROM order_returns returned
                      WHERE returned.order_id = payment.order_id AND returned.status = \'refunded\'
                  ))';
    }

    public static function productRevenueEventsSubquery(): string
    {
        return '(SELECT item.product_id, item.product_name, item.flash_sale_item_id,
                       payment.paid_at AS event_at, item.quantity AS quantity, item.line_total AS amount
                FROM order_items item
                JOIN payments payment ON payment.order_id = item.order_id
                WHERE payment.status IN (\'paid\', \'refunded\') AND payment.paid_at IS NOT NULL
                  AND (payment.status = \'paid\' OR payment.refunded_at IS NOT NULL)
                UNION ALL
                SELECT item.product_id, item.product_name, item.flash_sale_item_id,
                       returned.refunded_at AS event_at, -returned_item.quantity AS quantity,
                       -(returned.refund_amount * (item.unit_price * returned_item.quantity
                           / NULLIF(return_total.merchandise_amount, 0))) AS amount
                FROM order_returns returned
                JOIN order_return_items returned_item ON returned_item.return_id = returned.id
                JOIN order_items item ON item.id = returned_item.order_item_id
                JOIN (
                    SELECT ri.return_id, SUM(oi.unit_price * ri.quantity) AS merchandise_amount
                    FROM order_return_items ri JOIN order_items oi ON oi.id = ri.order_item_id
                    GROUP BY ri.return_id
                ) return_total ON return_total.return_id = returned.id
                WHERE returned.status = \'refunded\' AND returned.refunded_at IS NOT NULL
                  AND returned.refund_amount > 0
                  AND return_total.merchandise_amount > 0
                UNION ALL
                SELECT item.product_id, item.product_name, item.flash_sale_item_id,
                       payment.refunded_at AS event_at, -item.quantity AS quantity,
                       -(LEAST(payment.amount, order_total.merchandise_amount)
                           * (item.line_total / NULLIF(order_total.merchandise_amount, 0))) AS amount
                FROM payments payment
                JOIN order_items item ON item.order_id = payment.order_id
                JOIN (
                    SELECT order_id, SUM(line_total) AS merchandise_amount
                    FROM order_items GROUP BY order_id
                ) order_total ON order_total.order_id = payment.order_id
                WHERE payment.status = \'refunded\' AND payment.refunded_at IS NOT NULL
                  AND NOT EXISTS (
                      SELECT 1 FROM order_returns returned
                      WHERE returned.order_id = payment.order_id AND returned.status = \'refunded\'
                  )
                  AND order_total.merchandise_amount > 0)';
    }

    public static function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone(self::TIMEZONE)))->format('Y-m-d H:i:s');
    }

    public static function today(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone(self::TIMEZONE)))->format('Y-m-d');
    }

    public static function daysBeforeToday(int $days): string
    {
        return (new DateTimeImmutable('today', new DateTimeZone(self::TIMEZONE)))
            ->modify('-' . max(0, $days) . ' days')
            ->format('Y-m-d');
    }

    public static function dateBounds(string $from, string $to): array
    {
        $timezone = new DateTimeZone(self::TIMEZONE);
        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $from, $timezone);
        $lastDay = DateTimeImmutable::createFromFormat('!Y-m-d', $to, $timezone);
        if (!$start || !$lastDay || $start->format('Y-m-d') !== $from
            || $lastDay->format('Y-m-d') !== $to || $from > $to) {
            throw new InvalidArgumentException('Khoảng ngày báo cáo không hợp lệ.');
        }
        return [
            'from_date' => $start->format('Y-m-d H:i:s'),
            'to_date' => $lastDay->modify('+1 day')->format('Y-m-d H:i:s'),
        ];
    }

    public function dashboardTotals(): array
    {
        $timezone = new DateTimeZone(self::TIMEZONE);
        $today = new DateTimeImmutable('today', $timezone);
        $tomorrow = $today->modify('+1 day');
        $query = $this->database->prepare(
            'SELECT
                COALESCE(SUM(CASE WHEN event.event_at >= :today_start AND event.event_at < :today_end THEN event.amount ELSE 0 END), 0) AS today,
                COALESCE(SUM(CASE WHEN event.event_at >= :last_7_start AND event.event_at < :last_7_end THEN event.amount ELSE 0 END), 0) AS last_7_days,
                COALESCE(SUM(CASE WHEN event.event_at >= :last_30_start AND event.event_at < :last_30_end THEN event.amount ELSE 0 END), 0) AS last_30_days
             FROM ' . self::revenueEventsSubquery() . ' event
             WHERE event.event_at >= :range_start AND event.event_at < :range_end'
        );
        $rangeStart = $today->modify('-29 days')->format('Y-m-d H:i:s');
        $rangeEnd = $tomorrow->format('Y-m-d H:i:s');
        $query->execute([
            'today_start' => $today->format('Y-m-d H:i:s'),
            'today_end' => $tomorrow->format('Y-m-d H:i:s'),
            'last_7_start' => $today->modify('-6 days')->format('Y-m-d H:i:s'),
            'last_7_end' => $tomorrow->format('Y-m-d H:i:s'),
            'last_30_start' => $today->modify('-29 days')->format('Y-m-d H:i:s'),
            'last_30_end' => $tomorrow->format('Y-m-d H:i:s'),
            'range_start' => $rangeStart,
            'range_end' => $rangeEnd,
        ]);
        return array_map('floatval', $query->fetch() ?: ['today' => 0, 'last_7_days' => 0, 'last_30_days' => 0]);
    }

    public function totalsByMethod(): array
    {
        $timezone = new DateTimeZone(self::TIMEZONE);
        $today = new DateTimeImmutable('today', $timezone);
        $query = $this->database->prepare(
            'SELECT event.method,
                    COALESCE(SUM(event.amount), 0) AS received,
                    COALESCE(SUM(CASE WHEN event.event_at >= :today_start AND event.event_at < :today_end
                                      THEN event.amount ELSE 0 END), 0) AS received_today,
                    COALESCE(SUM(CASE WHEN event.event_type = \'payment\' AND event.refund_required = 1
                                      THEN event.amount ELSE 0 END), 0) AS refund_required,
                    COALESCE(SUM(CASE WHEN event.event_type = \'refund\' THEN -event.amount ELSE 0 END), 0) AS refunded
             FROM ' . self::revenueEventsSubquery() . ' event
             GROUP BY event.method
             ORDER BY event.method'
        );
        $query->execute([
            'today_start' => $today->format('Y-m-d H:i:s'),
            'today_end' => $today->modify('+1 day')->format('Y-m-d H:i:s'),
        ]);
        return $query->fetchAll();
    }
}
