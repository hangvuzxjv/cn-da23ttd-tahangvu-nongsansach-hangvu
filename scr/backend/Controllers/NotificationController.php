<?php

declare(strict_types=1);

final class NotificationController extends Controller
{
    public function index(): ?Response
    {
        try {
            $userId = (int) Auth::user()['id'];
            if (($_GET['view'] ?? '') === 'all') {
                $data = (new NotificationService())->forUserPage($userId, (int) ($_GET['page'] ?? 1));
                $data['title'] = 'Thông báo';
                $data['success'] = flash('success');
                $data['error'] = flash('error');
                $this->view('client/notifications', $data);
                return null;
            }

            $data = (new NotificationService())->forUser($userId, 8);
            return Response::json(true, 'Đã tải thông báo.', $data);
        } catch (Throwable) {
            error_log('Notification list unavailable.');
            if (($_GET['view'] ?? '') === 'all') {
                http_response_code(503);
                $this->view('client/user', ['message' => 'Chưa thể tải thông báo. Vui lòng thử lại sau.']);
                return null;
            }
            return Response::json(false, 'Chưa thể tải thông báo. Vui lòng thử lại sau.', [], 503);
        }
    }

    public function delete(string $id): Response
    {
        $userId = (int) Auth::user()['id'];
        $page = $this->requestedPage();
        try {
            $service = new NotificationService();
            if (!$service->deleteForUser($userId, (int) $id)) {
                return new Response('Không tìm thấy thông báo.', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
            }
            return $this->deletionRedirect($userId, $page, 1, $service);
        } catch (Throwable) {
            error_log('Notification deletion unavailable.');
            flash('error', 'Chưa thể xóa thông báo. Vui lòng thử lại sau.');
            return Response::redirect('/notifications?view=all&page=' . $page);
        }
    }

    public function deleteRead(): Response
    {
        return $this->deleteMultiple(true);
    }

    public function deleteAll(): Response
    {
        return $this->deleteMultiple(false);
    }

    private function deleteMultiple(bool $readOnly): Response
    {
        $userId = (int) Auth::user()['id'];
        $page = $this->requestedPage();
        try {
            $service = new NotificationService();
            $deleted = $readOnly
                ? $service->deleteReadForUser($userId)
                : $service->deleteAllForUser($userId);
            return $this->deletionRedirect($userId, $page, $deleted, $service);
        } catch (Throwable) {
            error_log('Bulk notification deletion unavailable.');
            flash('error', 'Chưa thể xóa thông báo. Vui lòng thử lại sau.');
            return Response::redirect('/notifications?view=all&page=' . $page);
        }
    }

    private function deletionRedirect(
        int $userId,
        int $page,
        int $deleted,
        ?NotificationService $service = null
    ): Response {
        $service ??= new NotificationService();
        $page = $service->pageAfterDelete($userId, $page);
        flash('success', 'Đã xóa ' . $deleted . ' thông báo');
        return Response::redirect('/notifications?view=all&page=' . $page);
    }

    private function requestedPage(): int
    {
        $page = filter_var($_POST['page'] ?? 1, FILTER_VALIDATE_INT);
        return is_int($page) && $page > 0 ? $page : 1;
    }

    public function markRead(string $id): Response
    {
        try {
            $service = new NotificationService();
            if (($_POST['_all'] ?? '') === '1') {
                $updated = $service->markAllRead((int) Auth::user()['id']);
                return Response::json(true, 'Đã đánh dấu tất cả thông báo là đã đọc.', ['updated' => $updated]);
            }

            $updated = $service->markRead((int) Auth::user()['id'], (int) $id);
            return Response::json(true, 'Đã cập nhật trạng thái thông báo.', ['updated' => $updated]);
        } catch (Throwable) {
            error_log('Notification update unavailable.');
            return Response::json(false, 'Chưa thể cập nhật thông báo. Vui lòng thử lại sau.', [], 503);
        }
    }
}
