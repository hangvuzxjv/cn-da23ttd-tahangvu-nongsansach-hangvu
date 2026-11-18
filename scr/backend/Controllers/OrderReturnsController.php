<?php

declare(strict_types=1);

final class OrderReturnsController extends Controller
{
    public function createForm(string $id): void
    {
        $this->assertCustomer();
        $orderId = (int) $id;
        $context = (new OrderReturnService())->customerOrderContext($orderId, (int) Auth::user()['id']);
        if (!$context['eligible']) {
            flash('error', 'Đơn hàng hiện không đủ điều kiện gửi yêu cầu trả hàng.');
            redirect('/tai-khoan/don-hang/' . $orderId);
        }
        $this->view('client/order-return-create', [
            'title' => 'Yêu cầu trả hàng / hoàn tiền',
            'context' => $context,
            'reasons' => OrderReturnService::reasons(),
            'oldInput' => flash('return_old') ?? [],
            'error' => flash('return_error'),
        ]);
    }

    public function create(string $id): void
    {
        $this->assertCustomer();
        $orderId = (int) $id;
        $userId = (int) Auth::user()['id'];
        $storedImages = [];
        try {
            $context = (new OrderReturnService())->customerOrderContext($orderId, $userId);
            if (!$context['eligible']) {
                throw new InvalidArgumentException('Đơn hàng hiện không đủ điều kiện gửi yêu cầu trả hàng.');
            }
            $storedImages = OrderReturnService::storeEvidenceFiles($this->uploadedFiles($_FILES['images'] ?? []));
            $returnId = (new OrderReturnService())->createRequest($orderId, $userId, $_POST, $storedImages);
            flash('success', 'Đã gửi yêu cầu trả hàng/hoàn tiền.');
            redirect('/tai-khoan/yeu-cau-tra-hang/' . $returnId);
        } catch (InvalidArgumentException $exception) {
            OrderReturnService::deleteStoredEvidence($storedImages);
            flash('return_error', $exception->getMessage());
            flash('return_old', [
                'reason_code' => is_string($_POST['reason_code'] ?? null) ? $_POST['reason_code'] : '',
                'description' => is_string($_POST['description'] ?? null) ? $_POST['description'] : '',
                'quantities' => is_array($_POST['quantities'] ?? null) ? $_POST['quantities'] : [],
            ]);
            redirect('/tai-khoan/don-hang/' . $orderId . '/tra-hang');
        } catch (HttpException $exception) {
            OrderReturnService::deleteStoredEvidence($storedImages);
            if ($exception->status() !== 404) {
                throw $exception;
            }
            $this->renderNotFound();
        } catch (Throwable $exception) {
            OrderReturnService::deleteStoredEvidence($storedImages);
            error_log('Order return request creation failed (order=' . $orderId . '): ' . $exception->getMessage());
            flash('return_error', 'Không thể gửi yêu cầu lúc này. Vui lòng thử lại sau.');
            redirect('/tai-khoan/don-hang/' . $orderId . '/tra-hang');
        }
    }

    public function show(string $id): void
    {
        $this->assertCustomer();
        try {
            $return = (new OrderReturnService())->customerReturn((int) $id, (int) Auth::user()['id']);
        } catch (HttpException $exception) {
            if ($exception->status() !== 404) {
                throw $exception;
            }
            $this->renderNotFound();
            return;
        }
        $this->view('client/order-return-detail', [
            'title' => 'Yêu cầu trả hàng #' . (int) $id,
            'return' => $return,
            'statuses' => OrderReturnService::statuses(),
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public function cancel(string $id): void
    {
        $this->assertCustomer();
        $returnId = (int) $id;
        try {
            (new OrderReturnService())->cancelRequest($returnId, (int) Auth::user()['id']);
            flash('success', 'Đã hủy yêu cầu trả hàng.');
        } catch (InvalidArgumentException $exception) {
            flash('error', $exception->getMessage());
        } catch (HttpException $exception) {
            if ($exception->status() !== 404) {
                throw $exception;
            }
            $this->renderNotFound();
            return;
        }
        redirect('/tai-khoan/yeu-cau-tra-hang/' . $returnId);
    }

    public function customerImage(string $id, string $imageId): Response
    {
        $this->assertCustomer();
        return $this->imageResponse((new OrderReturnService())->customerImage(
            (int) $id,
            (int) $imageId,
            (int) Auth::user()['id']
        ));
    }

    private function uploadedFiles(mixed $field): array
    {
        if (!is_array($field) || !is_array($field['name'] ?? null)) {
            return [];
        }
        $files = [];
        foreach ($field['name'] as $index => $name) {
            if ((int) ($field['error'][$index] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $files[] = [
                'name' => $name,
                'type' => $field['type'][$index] ?? '',
                'tmp_name' => $field['tmp_name'][$index] ?? '',
                'error' => $field['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $field['size'][$index] ?? 0,
            ];
        }
        return $files;
    }

    private function imageResponse(array $image): Response
    {
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

    private function assertCustomer(): void
    {
        if ((Auth::user()['role'] ?? '') !== 'customer') {
            throw new HttpException(403, 'Chỉ khách hàng mới có thể gửi yêu cầu trả hàng.');
        }
    }

    private function renderNotFound(): void
    {
        http_response_code(404);
        View::renderError(404);
    }
}
