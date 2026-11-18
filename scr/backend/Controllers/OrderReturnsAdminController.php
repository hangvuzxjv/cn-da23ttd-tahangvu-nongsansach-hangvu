<?php

declare(strict_types=1);

final class OrderReturnsAdminController extends Controller
{
    public function index(): void
    {
        $filters = [
            'status' => is_string($_GET['status'] ?? null) ? $_GET['status'] : '',
            'q' => is_string($_GET['q'] ?? null) ? $_GET['q'] : '',
        ];
        $requestedPage = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
        $listing = (new OrderReturnService())->adminReturns(
            $filters,
            $requestedPage === false ? 1 : (int) $requestedPage
        );
        $this->view('admin/order-returns', [
            'title' => 'Yêu cầu trả hàng / hoàn tiền',
            'returns' => $listing['returns'],
            'filters' => $listing['filters'],
            'statuses' => OrderReturnService::statuses(),
            'reasons' => OrderReturnService::reasons(),
            'page' => $listing['page'],
            'pages' => $listing['pages'],
            'total' => $listing['total'],
            'paginationUrl' => '/admin/returns',
            'paginationQuery' => array_filter($listing['filters'], static fn (string $value): bool => $value !== ''),
            'paginationLabel' => 'yêu cầu',
            'menu' => (new AdminController())->menu(),
        ], 'admin');
    }

    public function show(string $id): void
    {
        try {
            $return = (new OrderReturnService())->adminReturn((int) $id);
        } catch (HttpException $exception) {
            if ($exception->status() !== 404) {
                throw $exception;
            }
            $this->renderNotFound();
            return;
        }
        $this->view('admin/order-return-detail', [
            'title' => 'Yêu cầu trả hàng #' . (int) $id,
            'return' => $return,
            'statuses' => OrderReturnService::statuses(),
            'reasons' => OrderReturnService::reasons(),
            'success' => flash('success'),
            'error' => flash('error'),
            'menu' => (new AdminController())->menu(),
        ], 'admin');
    }

    public function approve(string $id): void
    {
        $this->perform((int) $id, function (OrderReturnService $service, int $returnId): void {
            $service->approve($returnId, (int) Auth::user()['id'], $_POST);
            flash('success', 'Đã duyệt yêu cầu trả hàng.');
        });
    }

    public function reject(string $id): void
    {
        $this->perform((int) $id, function (OrderReturnService $service, int $returnId): void {
            $reason = is_string($_POST['reason'] ?? null) ? $_POST['reason'] : '';
            $service->reject($returnId, (int) Auth::user()['id'], $reason);
            flash('success', 'Đã từ chối yêu cầu trả hàng.');
        });
    }

    public function markRefunded(string $id): void
    {
        $this->perform((int) $id, function (OrderReturnService $service, int $returnId): void {
            $reference = is_string($_POST['refund_reference'] ?? null) ? $_POST['refund_reference'] : '';
            $restock = isset($_POST['restock']) && $_POST['restock'] === '1';
            $service->markRefunded($returnId, (int) Auth::user()['id'], $reference, $restock);
            flash('success', 'Đã ghi nhận hoàn tiền.');
        });
    }

    public function image(string $id, string $imageId): Response
    {
        $image = (new OrderReturnService())->adminImage((int) $id, (int) $imageId);
        $contents = file_get_contents($image['path']);
        if ($contents === false) {
            throw new HttpException(404, 'Không tìm thấy ảnh bằng chứng.');
        }
        return new Response($contents, 200, [
            'Content-Type' => $image['mime'],
            'Content-Length' => (string) strlen($contents),
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function perform(int $returnId, callable $action): void
    {
        try {
            $action(new OrderReturnService(), $returnId);
        } catch (InvalidArgumentException $exception) {
            flash('error', $exception->getMessage());
        } catch (HttpException $exception) {
            if ($exception->status() !== 404) {
                throw $exception;
            }
            $this->renderNotFound();
            return;
        } catch (Throwable $exception) {
            error_log('Admin order return update failed (return=' . $returnId . '): ' . $exception->getMessage());
            flash('error', 'Không thể cập nhật yêu cầu lúc này. Vui lòng thử lại sau.');
        }
        redirect('/admin/returns/' . $returnId);
    }

    private function renderNotFound(): void
    {
        http_response_code(404);
        View::renderError(404);
    }
}
