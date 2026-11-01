<?php

declare(strict_types=1);

final class CustomerFeaturesController extends Controller
{
    public function wishlist(): void
    {
        $userId = (int) Auth::user()['id'];
        $error = null;
        $products = [];
        try {
            $products = (new CustomerEngagementService())->wishlistProducts($userId);
        } catch (Throwable $exception) {
            error_log('Customer wishlist page failed: ' . $exception->getMessage());
            $error = 'Không thể tải danh sách yêu thích lúc này. Vui lòng thử lại sau.';
        }
        $this->view('client/wishlist', [
            'title' => 'Sản phẩm yêu thích',
            'products' => $products,
            'wishlistIds' => array_column($products, 'id'),
            'error' => $error,
        ]);
    }

    public function toggleWishlist(): Response
    {
        try {
            $rawProductId = $_POST['product_id'] ?? null;
            $productId = is_scalar($rawProductId) ? filter_var($rawProductId, FILTER_VALIDATE_INT) : false;
            if ($productId === false || $productId < 1) {
                throw new InvalidArgumentException('Sản phẩm không hợp lệ.');
            }
            $isWishlisted = (new CustomerEngagementService())->toggleWishlist(
                (int) Auth::user()['id'],
                (int) $productId
            );
            return Response::json(
                true,
                $isWishlisted ? 'Đã thêm vào sản phẩm yêu thích.' : 'Đã bỏ khỏi sản phẩm yêu thích.',
                ['product_id' => (int) $productId, 'wishlisted' => $isWishlisted]
            );
        } catch (InvalidArgumentException $exception) {
            return Response::json(false, $exception->getMessage(), [], 422);
        } catch (PDOException $exception) {
            error_log('Customer wishlist update failed: ' . $exception->getMessage());
            return Response::json(false, 'Không thể cập nhật yêu thích lúc này. Vui lòng thử lại sau.', [], 503);
        } catch (Throwable $exception) {
            error_log('Customer wishlist update failed: ' . $exception->getMessage());
            return Response::json(false, 'Đã xảy ra lỗi khi cập nhật yêu thích. Vui lòng thử lại sau.', [], 503);
        }
    }

    public function saveReview(): Response
    {
        try {
            $rawProductId = $_POST['product_id'] ?? null;
            $rawRating = $_POST['rating'] ?? null;
            $productId = is_scalar($rawProductId) ? filter_var($rawProductId, FILTER_VALIDATE_INT) : false;
            $rating = is_scalar($rawRating) ? filter_var($rawRating, FILTER_VALIDATE_INT) : false;
            $content = $_POST['content'] ?? null;
            if ($productId === false || $productId < 1 || $rating === false || !is_string($content)) {
                throw new InvalidArgumentException('Thông tin đánh giá không hợp lệ.');
            }
            $result = (new CustomerEngagementService())->saveReview(
                (int) Auth::user()['id'],
                (int) $productId,
                (int) $rating,
                $content
            );
            return Response::json(
                true,
                $result['created']
                    ? 'Đánh giá đã được gửi và đang chờ quản trị viên duyệt.'
                    : 'Đánh giá đã được cập nhật và đang chờ quản trị viên duyệt.',
                $result
            );
        } catch (InvalidArgumentException $exception) {
            return Response::json(false, $exception->getMessage(), [], 422);
        } catch (HttpException $exception) {
            return Response::json(false, $exception->getMessage(), [], $exception->status());
        } catch (PDOException $exception) {
            error_log('Customer review submission failed: ' . $exception->getMessage());
            return Response::json(false, 'Không thể lưu đánh giá lúc này. Vui lòng thử lại sau.', [], 503);
        } catch (Throwable $exception) {
            error_log('Customer review submission failed: ' . $exception->getMessage());
            return Response::json(false, 'Đã xảy ra lỗi khi gửi đánh giá. Vui lòng thử lại sau.', [], 503);
        }
    }
}
