<?php

declare(strict_types=1);

final class FlashSaleAdminController extends Controller
{
    public function index(): void
    {
        $error = null;
        try {
            $campaigns = (new FlashSaleService())->adminCampaigns();
        } catch (Throwable $exception) {
            error_log('Flash Sale admin list failed: ' . $exception->getMessage());
            $campaigns = [];
            $error = 'Không thể tải danh sách Flash Sale lúc này. Vui lòng thử lại sau.';
        }
        $this->view('admin/flash-sales', [
            'title' => 'Flash Sale',
            'campaigns' => $campaigns,
            'error' => $error,
            'menu' => (new AdminController())->menu(),
        ], 'admin');
    }

    public function create(): void
    {
        $this->view('admin/flash-sale-detail', [
            'title' => 'Tạo Flash Sale',
            'campaign' => null,
            'isNew' => true,
            'error' => null,
            'menu' => (new AdminController())->menu(),
        ], 'admin');
    }

    public function show(string $id): void
    {
        $error = null;
        $campaign = null;
        try {
            $campaign = (new FlashSaleService())->campaignForAdmin((int) $id);
            if ($campaign === null) {
                http_response_code(404);
                $error = 'Không tìm thấy chương trình Flash Sale.';
            }
        } catch (Throwable $exception) {
            error_log('Flash Sale admin detail failed: ' . $exception->getMessage());
            $error = 'Không thể tải chương trình Flash Sale lúc này. Vui lòng thử lại sau.';
        }
        $this->view('admin/flash-sale-detail', [
            'title' => $campaign ? 'Flash Sale: ' . $campaign['name'] : 'Flash Sale',
            'campaign' => $campaign,
            'isNew' => false,
            'error' => $error,
            'menu' => (new AdminController())->menu(),
        ], 'admin');
    }

    public function products(): Response
    {
        return $this->jsonOperation(function (): array {
            return ['products' => (new FlashSaleService())->searchProducts((string) ($_GET['q'] ?? ''))];
        }, 'Đã tìm sản phẩm.');
    }

    public function save(): Response
    {
        return $this->jsonOperation(function (): array {
            $id = (new FlashSaleService())->saveCampaign(
                $_POST,
                isset($_POST['id']) && (int) $_POST['id'] > 0 ? (int) $_POST['id'] : null,
                (int) Auth::user()['id']
            );
            return ['id' => $id, 'url' => url('/admin/flash-sales/' . $id)];
        }, 'Đã lưu chương trình Flash Sale.');
    }

    public function cancel(string $id): Response
    {
        return $this->jsonOperation(function () use ($id): array {
            (new FlashSaleService())->cancelCampaign(
                (int) $id,
                (int) Auth::user()['id'],
                (string) ($_POST['reason'] ?? '')
            );
            return ['id' => (int) $id];
        }, 'Đã hủy chương trình Flash Sale.');
    }

    public function delete(string $id): Response
    {
        return $this->jsonOperation(function () use ($id): array {
            (new FlashSaleService())->deleteCampaign((int) $id, (int) Auth::user()['id']);
            return ['id' => (int) $id];
        }, 'Đã xóa chương trình Flash Sale.');
    }

    private function jsonOperation(callable $operation, string $successMessage): Response
    {
        try {
            return Response::json(true, $successMessage, $operation());
        } catch (InvalidArgumentException $exception) {
            return Response::json(false, $exception->getMessage(), [], 422);
        } catch (PDOException $exception) {
            error_log('Flash Sale admin database operation failed: ' . $exception->getMessage());
            if (str_starts_with((string) $exception->getCode(), '23')) {
                return Response::json(false, 'Dữ liệu đang xung đột với một chương trình khác. Vui lòng tải lại và thử lại.', [], 409);
            }
            return Response::json(false, 'Không thể lưu dữ liệu Flash Sale lúc này. Vui lòng thử lại sau.', [], 503);
        } catch (Throwable $exception) {
            error_log('Flash Sale admin operation failed: ' . $exception->getMessage());
            return Response::json(false, 'Đã xảy ra lỗi khi xử lý Flash Sale. Vui lòng thử lại sau.', [], 503);
        }
    }
}
