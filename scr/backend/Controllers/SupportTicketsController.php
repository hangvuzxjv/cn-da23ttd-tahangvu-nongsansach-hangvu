<?php

declare(strict_types=1);

final class SupportTicketsController extends Controller
{
    public function index(): void
    {
        $service = new SupportTicketService();
        $this->view('client/support-tickets', [
            'title' => 'Phiếu hỗ trợ',
            'tickets' => $service->ticketsForUser((int) Auth::user()['id']),
            'categories' => SupportTicketService::categories(),
            'statuses' => SupportTicketService::statuses(),
        ]);
    }

    public function createForm(): void
    {
        $userId = (int) Auth::user()['id'];
        $service = new SupportTicketService();
        $orders = $service->ordersForUser($userId);
        $categories = SupportTicketService::categories();
        $oldInput = flash('support_old') ?? [];
        if (!array_key_exists('order_id', $oldInput)) {
            $requestedOrderId = filter_var($_GET['order_id'] ?? null, FILTER_VALIDATE_INT);
            $oldInput['order_id'] = $requestedOrderId === false ? '' : (string) $requestedOrderId;
        }
        if (!array_key_exists('category', $oldInput)) {
            $requestedCategory = (string) ($_GET['category'] ?? '');
            $oldInput['category'] = isset($categories[$requestedCategory]) ? $requestedCategory : '';
        }
        $this->view('client/support-ticket-create', [
            'title' => 'Tạo phiếu hỗ trợ',
            'orders' => $orders,
            'categories' => $categories,
            'oldInput' => $oldInput,
            'error' => flash('support_error'),
        ]);
    }

    public function create(): void
    {
        $userId = (int) Auth::user()['id'];
        try {
            $ticketId = (new SupportTicketService())->create($userId, $_POST);
            flash('success', 'Phiếu hỗ trợ đã được tạo.');
            redirect('/ho-tro/' . $ticketId);
        } catch (InvalidArgumentException $exception) {
            flash('support_error', $exception->getMessage());
            flash('support_old', [
                'subject' => is_string($_POST['subject'] ?? null) ? $_POST['subject'] : '',
                'category' => is_string($_POST['category'] ?? null) ? $_POST['category'] : '',
                'order_id' => is_scalar($_POST['order_id'] ?? '') ? (string) ($_POST['order_id'] ?? '') : '',
                'body' => is_string($_POST['body'] ?? null) ? $_POST['body'] : '',
            ]);
            redirect('/ho-tro/tao');
        } catch (PDOException $exception) {
            error_log('Support ticket creation failed: ' . $exception->getMessage());
            flash('support_error', 'Không thể tạo phiếu hỗ trợ lúc này. Vui lòng thử lại sau.');
            redirect('/ho-tro/tao');
        }
    }

    public function show(string $id): void
    {
        try {
            $conversation = (new SupportTicketService())->conversationForUser(
                (int) $id,
                (int) Auth::user()['id']
            );
        } catch (HttpException $exception) {
            if ($exception->status() !== 404) {
                throw $exception;
            }
            $this->renderNotFound();
            return;
        }
        $this->view('client/support-ticket-detail', [
            'title' => 'Phiếu hỗ trợ #' . (int) $id,
            'ticket' => $conversation['ticket'],
            'messages' => $conversation['messages'],
            'categories' => SupportTicketService::categories(),
            'statuses' => SupportTicketService::statuses(),
            'success' => flash('success'),
            'error' => flash('support_error'),
            'oldBody' => flash('support_reply_old') ?? '',
        ]);
    }

    public function reply(string $id): void
    {
        $ticketId = (int) $id;
        try {
            $body = $_POST['body'] ?? null;
            (new SupportTicketService())->reply($ticketId, (int) Auth::user()['id'], $body);
            flash('success', 'Đã gửi phản hồi của bạn.');
        } catch (InvalidArgumentException $exception) {
            flash('support_error', $exception->getMessage());
            flash('support_reply_old', is_string($_POST['body'] ?? null) ? $_POST['body'] : '');
        } catch (HttpException $exception) {
            if ($exception->status() !== 404) {
                throw $exception;
            }
            $this->renderNotFound();
            return;
        }
        redirect('/ho-tro/' . $ticketId);
    }

    public function close(string $id): void
    {
        $ticketId = (int) $id;
        try {
            (new SupportTicketService())->close($ticketId, (int) Auth::user()['id']);
        } catch (PDOException $exception) {
            error_log('Support ticket close failed: ' . $exception->getMessage());
            flash('support_error', 'Không thể đóng phiếu hỗ trợ lúc này. Vui lòng thử lại sau.');
            redirect('/ho-tro/' . $ticketId);
        } catch (HttpException $exception) {
            if ($exception->status() !== 404) {
                throw $exception;
            }
            $this->renderNotFound();
            return;
        }
        flash('success', 'Phiếu hỗ trợ đã được đóng.');
        redirect('/ho-tro/' . $ticketId);
    }

    private function renderNotFound(): void
    {
        http_response_code(404);
        View::renderError(404);
    }
}
