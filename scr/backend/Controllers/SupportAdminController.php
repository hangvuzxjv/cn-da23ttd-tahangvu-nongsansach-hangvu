<?php

declare(strict_types=1);

final class SupportAdminController extends Controller
{
    public function index(): void
    {
        $filters = [
            'status' => is_string($_GET['status'] ?? null) ? $_GET['status'] : '',
            'category' => is_string($_GET['category'] ?? null) ? $_GET['category'] : '',
            'q' => is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 100, 'UTF-8') : '',
        ];
        $requestedPage = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
        $listing = (new SupportTicketService())->adminTickets(
            $filters,
            $requestedPage === false ? 1 : (int) $requestedPage
        );
        $this->view('admin/support', [
            'title' => 'Phiếu hỗ trợ',
            'tickets' => $listing['tickets'],
            'filters' => $listing['filters'],
            'total' => $listing['total'],
            'page' => $listing['page'],
            'pages' => $listing['pages'],
            'paginationUrl' => '/admin/support',
            'paginationQuery' => array_filter(
                $listing['filters'],
                static fn (string $value): bool => $value !== ''
            ),
            'paginationLabel' => 'phiếu hỗ trợ',
            'categories' => SupportTicketService::categories(),
            'statuses' => SupportTicketService::statuses(),
            'success' => flash('success'),
            'error' => flash('error'),
            'menu' => (new AdminController())->menu(),
        ], 'admin');
    }

    public function show(string $id): void
    {
        try {
            $conversation = (new SupportTicketService())->adminConversation((int) $id);
        } catch (HttpException $exception) {
            if ($exception->status() !== 404) {
                throw $exception;
            }
            $this->renderNotFound();
            return;
        }

        $this->view('admin/support-detail', [
            'title' => 'Phiếu hỗ trợ #' . (int) $id,
            'ticket' => $conversation['ticket'],
            'messages' => $conversation['messages'],
            'categories' => SupportTicketService::categories(),
            'statuses' => SupportTicketService::statuses(),
            'success' => flash('success'),
            'error' => flash('error'),
            'menu' => (new AdminController())->menu(),
        ], 'admin');
    }

    public function reply(string $id): void
    {
        $ticketId = (int) $id;
        try {
            (new SupportTicketService())->adminReply(
                $ticketId,
                (int) Auth::user()['id'],
                $_POST['body'] ?? null
            );
            flash('success', 'Đã gửi phản hồi và thông báo cho khách hàng.');
        } catch (InvalidArgumentException $exception) {
            flash('error', $exception->getMessage());
        } catch (HttpException $exception) {
            if ($exception->status() !== 404) {
                throw $exception;
            }
            $this->renderNotFound();
            return;
        } catch (PDOException $exception) {
            error_log('Admin support reply failed: ' . $exception->getMessage());
            flash('error', 'Không thể lưu phản hồi lúc này. Vui lòng thử lại sau.');
        }
        redirect('/admin/support/' . $ticketId);
    }

    public function updateStatus(string $id): void
    {
        $ticketId = (int) $id;
        try {
            $status = is_string($_POST['status'] ?? null) ? $_POST['status'] : '';
            $changed = (new SupportTicketService())->adminSetStatus(
                $ticketId,
                (int) Auth::user()['id'],
                $status
            );
            flash('success', $changed ? 'Đã cập nhật trạng thái phiếu.' : 'Trạng thái phiếu không thay đổi.');
        } catch (InvalidArgumentException $exception) {
            flash('error', $exception->getMessage());
        } catch (HttpException $exception) {
            if ($exception->status() !== 404) {
                throw $exception;
            }
            $this->renderNotFound();
            return;
        } catch (PDOException $exception) {
            error_log('Admin support ticket status update failed: ' . $exception->getMessage());
            flash('error', 'Không thể cập nhật trạng thái lúc này. Vui lòng thử lại sau.');
        }
        redirect('/admin/support/' . $ticketId);
    }

    private function renderNotFound(): void
    {
        http_response_code(404);
        View::renderError(404);
    }
}
