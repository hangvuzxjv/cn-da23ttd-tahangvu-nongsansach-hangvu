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
