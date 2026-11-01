<?php

declare(strict_types=1);

final class NotificationController extends Controller
{
    public function index(): Response
    {
        try {
            $data = (new NotificationService())->forUser((int) Auth::user()['id']);
            return Response::json(true, 'Đã tải thông báo.', $data);
        } catch (Throwable) {
            error_log('Notification list unavailable.');
            return Response::json(false, 'Chưa thể tải thông báo. Vui lòng thử lại sau.', [], 503);
        }
    }

    public function markRead(string $id): Response
    {
        try {
            $updated = (new NotificationService())->markRead((int) Auth::user()['id'], (int) $id);
            return Response::json(true, 'Đã cập nhật trạng thái thông báo.', ['updated' => $updated]);
        } catch (Throwable) {
            error_log('Notification update unavailable.');
            return Response::json(false, 'Chưa thể cập nhật thông báo. Vui lòng thử lại sau.', [], 503);
        }
    }
}
