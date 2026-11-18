<?php

declare(strict_types=1);

final class AdminSearchController extends Controller
{
    public function index(): Response
    {
        $query = $_GET['q'] ?? '';
        if (!is_string($query)) {
            return Response::json(false, 'Từ khóa tìm kiếm không hợp lệ.', [], 422);
        }

        $query = trim($query);
        $length = mb_strlen($query, 'UTF-8');
        if ($length < 2) {
            return Response::json(true, 'Nhập ít nhất 2 ký tự để tìm kiếm.', ['groups' => []]);
        }
        if ($length > 100) {
            return Response::json(false, 'Từ khóa tìm kiếm quá dài.', [], 422);
        }

        $database = Database::connection();
        $groups = [];

        if (Auth::can('orders.view')) {
            $orderQuery = preg_replace('/\A#\s*/u', '', $query);
            $statement = $database->prepare(
                'SELECT o.id, o.order_no, o.recipient_name, o.recipient_phone
                 FROM orders o
                 WHERE ' . $this->likeCondition('o.order_no', 'order_no') . '
                    OR ' . $this->likeCondition('o.recipient_name', 'recipient_name') . '
                    OR ' . $this->likeCondition('o.recipient_phone', 'recipient_phone') . '
                 ORDER BY o.placed_at DESC, o.id DESC LIMIT 5'
            );
            $statement->execute([
                'order_no' => $this->likeValue($orderQuery ?? $query),
                'recipient_name' => $this->likeValue($query),
                'recipient_phone' => $this->likeValue($query),
            ]);
            $groups['orders'] = [
                'label' => 'Đơn hàng',
                'items' => array_map(static fn (array $row): array => [
                    'title' => (string) $row['order_no'],
                    'detail' => implode(' · ', array_filter(
                        [(string) $row['recipient_name'], (string) $row['recipient_phone']],
                        static fn (string $value): bool => $value !== ''
                    )),
                    'url' => '/admin/orders/' . (int) $row['id'],
                ], $statement->fetchAll()),
            ];
        }

        if (Auth::can('customers.view')) {
            $statement = $database->prepare(
                'SELECT u.name, u.email, u.phone
                 FROM users u
                 WHERE u.role = \'customer\'
                   AND (' . $this->likeCondition('u.name', 'customer_name') . '
                    OR ' . $this->likeCondition('u.email', 'customer_email') . '
                    OR ' . $this->likeCondition('u.phone', 'customer_phone') . ')
                 ORDER BY u.id DESC LIMIT 5'
            );
            $statement->execute([
                'customer_name' => $this->likeValue($query),
                'customer_email' => $this->likeValue($query),
                'customer_phone' => $this->likeValue($query),
            ]);
            $groups['customers'] = [
                'label' => 'Khách hàng',
                'items' => array_map(static fn (array $row): array => [
                    'title' => (string) $row['name'],
                    'detail' => implode(' · ', array_filter(
                        [(string) $row['email'], (string) $row['phone']],
                        static fn (string $value): bool => $value !== ''
                    )),
                    'url' => '/admin/customers?q=' . rawurlencode((string) $row['name']),
                ], $statement->fetchAll()),
            ];
        }

        if (Auth::can('products.view')) {
            $statement = $database->prepare(
                'SELECT p.name, p.sku
                 FROM products p
                 WHERE p.deleted_at IS NULL
                   AND (' . $this->likeCondition('p.name', 'product_name') . '
                    OR ' . $this->likeCondition('p.sku', 'product_sku') . ')
                 ORDER BY p.created_at DESC, p.id DESC LIMIT 5'
            );
            $statement->execute([
                'product_name' => $this->likeValue($query),
                'product_sku' => $this->likeValue($query),
            ]);
            $groups['products'] = [
                'label' => 'Sản phẩm',
                'items' => array_map(static fn (array $row): array => [
                    'title' => (string) $row['name'],
                    'detail' => (string) $row['sku'],
                    'url' => '/admin/products?q=' . rawurlencode((string) $row['name']),
                ], $statement->fetchAll()),
            ];
        }

        if (Auth::can('batches.view')) {
            $statement = $database->prepare(
                'SELECT b.id, b.batch_code, p.name AS product_name
                 FROM product_batches b
                 JOIN products p ON p.id = b.product_id
                 WHERE ' . $this->likeCondition('b.batch_code', 'batch_code') . '
                 ORDER BY b.created_at DESC, b.id DESC LIMIT 5'
            );
            $statement->execute(['batch_code' => $this->likeValue($query)]);
            $groups['batches'] = [
                'label' => 'Lô hàng',
                'items' => array_map(static fn (array $row): array => [
                    'title' => (string) $row['batch_code'],
                    'detail' => (string) $row['product_name'],
                    'url' => '/admin/batches/' . (int) $row['id'],
                ], $statement->fetchAll()),
            ];
        }

        return Response::json(true, 'Đã tìm kiếm.', ['groups' => $groups]);
    }

    private function likeCondition(string $field, string $parameter): string
    {
        return "REPLACE(REPLACE($field, 'đ', 'd'), 'Đ', 'd') COLLATE utf8mb4_unicode_ci"
            . " LIKE :$parameter ESCAPE '='";
    }

    private function likeValue(string $query): string
    {
        $query = str_replace(['đ', 'Đ'], 'd', $query);
        $query = str_replace(['=', '%', '_'], ['==', '=%', '=_'], $query);
        return '%' . $query . '%';
    }
}
