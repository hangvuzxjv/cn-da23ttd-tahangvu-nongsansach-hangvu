<?php

declare(strict_types=1);

final class ReviewAdminController extends Controller
{
    public function index(): void
    {
        $filters = [
            'q' => is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '',
            'product_id' => filter_var($_GET['product_id'] ?? 0, FILTER_VALIDATE_INT),
            'status' => is_string($_GET['status'] ?? null) ? $_GET['status'] : '',
        ];
        $filters['product_id'] = $filters['product_id'] === false ? 0 : max(0, $filters['product_id']);
        if (mb_strlen($filters['q'], 'UTF-8') > 200) {
            $filters['q'] = mb_substr($filters['q'], 0, 200, 'UTF-8');
        }
        if (!in_array($filters['status'], ['pending', 'published', 'hidden'], true)) {
            $filters['status'] = '';
        }

        $error = null;
        $reviews = ['reviews' => [], 'page' => 1, 'pages' => 1, 'total' => 0];
        $products = [];
        try {
            $service = new CustomerEngagementService();
            $rawPage = $_GET['page'] ?? 1;
            $page = is_scalar($rawPage) ? max(1, (int) $rawPage) : 1;
            $reviews = $service->adminReviewList($filters, $page);
            $products = Database::connection()->query(
                "SELECT id, name FROM products WHERE deleted_at IS NULL ORDER BY name"
            )->fetchAll();
        } catch (Throwable $exception) {
            error_log('Admin review list failed: ' . $exception->getMessage());
            $error = 'Không thể tải danh sách đánh giá lúc này. Vui lòng thử lại sau.';
        }
        $this->view('admin/reviews', [
            'title' => 'Quản lý đánh giá',
            'reviews' => $reviews['reviews'],
            'page' => $reviews['page'],
            'pages' => $reviews['pages'],
            'total' => $reviews['total'],
            'products' => $products,
            'filters' => $filters,
            'error' => $error,
            'success' => flash('success'),
            'menu' => (new AdminController())->menu(),
            'canManage' => Auth::can('reviews.manage'),
        ], 'admin');
    }

    public function updateStatus(string $id): Response
    {
        return $this->jsonOperation(function () use ($id): array {
            $status = $_POST['status'] ?? null;
            if (!is_string($status)) {
                throw new InvalidArgumentException('Trạng thái đánh giá không hợp lệ.');
            }
            (new CustomerEngagementService())->setReviewStatus(
                (int) $id,
                (int) Auth::user()['id'],
                $status
            );
            return ['id' => (int) $id, 'status' => $status];
        }, 'Đã cập nhật trạng thái đánh giá.');
    }

    public function delete(string $id): Response
    {
        return $this->jsonOperation(function () use ($id): array {
            (new CustomerEngagementService())->deleteReview((int) $id, (int) Auth::user()['id']);
            return ['id' => (int) $id];
        }, 'Đã xóa đánh giá.');
    }

    private function jsonOperation(callable $operation, string $successMessage): Response
    {
        try {
            return Response::json(true, $successMessage, $operation());
        } catch (InvalidArgumentException $exception) {
            return Response::json(false, $exception->getMessage(), [], 422);
        } catch (PDOException $exception) {
            error_log('Admin review update failed: ' . $exception->getMessage());
            return Response::json(false, 'Không thể cập nhật đánh giá lúc này. Vui lòng thử lại sau.', [], 503);
        } catch (Throwable $exception) {
            error_log('Admin review update failed: ' . $exception->getMessage());
            return Response::json(false, 'Đã xảy ra lỗi khi xử lý đánh giá. Vui lòng thử lại sau.', [], 503);
        }
    }
}
