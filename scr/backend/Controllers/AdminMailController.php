<?php

declare(strict_types=1);

final class AdminMailController extends Controller
{
    public function index(): void
    {
        $status = '';
        $messages = [];
        $driver = 'outbox';
        $smtpConfigured = false;
        $brevoConfigured = false;
        $fromConfigured = false;
        $error = null;
        $total = 0;
        $page = 1;
        $pages = 1;
        try {
            $database = Database::connection();
            $status = (string) ($_GET['status'] ?? '');
            $conditions = [];
            $parameters = [];
            if (in_array($status, ['pending', 'sent'], true)) {
                $conditions[] = 'status = :status';
                $parameters['status'] = $status;
            } else {
                $status = '';
            }
            $where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
            $countQuery = $database->prepare('SELECT COUNT(*) FROM mail_outbox' . $where);
            $countQuery->execute($parameters);
            $total = (int) $countQuery->fetchColumn();
            $perPage = 20;
            $pages = max(1, (int) ceil($total / $perPage));
            $requestedPage = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
            $page = min(max(1, $requestedPage === false ? 1 : $requestedPage), $pages);
            $sql = 'SELECT id, recipient, subject, driver, status, attempts, last_error, created_at, sent_at
                    FROM mail_outbox' . $where . ' ORDER BY id DESC LIMIT :limit OFFSET :offset';
            $statement = $database->prepare($sql);
            foreach ($parameters as $name => $value) {
                $statement->bindValue(':' . $name, $value, PDO::PARAM_STR);
            }
            $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $statement->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
            $statement->execute();
            $messages = $statement->fetchAll();
            foreach ($messages as &$message) {
                $message['masked_recipient'] = MailService::maskedRecipient((string) $message['recipient']);
            }
            unset($message);

            $mailerService = new MailService($database);
            $driver = $mailerService->selectedDriver();
            $config = require BACKEND_PATH . '/config/config.php';
            $mailer = $config['mailer'] ?? [];
            $smtpConfigured = !empty($mailer['smtp']['host'])
                && !empty($mailer['smtp']['username']) && !empty($mailer['smtp']['password']);
            $brevoConfigured = !empty($mailer['brevo']['api_key']);
            $fromConfigured = !empty($mailer['from_address']);
        } catch (Throwable) {
            error_log('Admin mail page could not load its data.');
            $error = 'Chưa thể tải dữ liệu email. Vui lòng kiểm tra kết nối và migration rồi thử lại.';
        }
        $this->view('admin/mail', [
            'title' => 'Email và hộp thư chờ',
            'messages' => $messages,
            'status' => $status,
            'page' => $page,
            'pages' => $pages,
            'paginationUrl' => '/admin/mail',
            'paginationQuery' => $status === '' ? [] : ['status' => $status],
            'paginationLabel' => 'email',
            'driver' => $driver,
            'smtpConfigured' => $smtpConfigured,
            'brevoConfigured' => $brevoConfigured,
            'fromConfigured' => $fromConfigured,
            'adminEmail' => (string) (Auth::user()['email'] ?? ''),
            'loadError' => $error,
            'menu' => (new AdminController())->menu(),
        ], 'admin');
    }

    public function saveDriver(): Response
    {
        $driver = (string) ($_POST['driver'] ?? '');
        if (!in_array($driver, ['smtp', 'brevo', 'outbox'], true)) {
            return Response::json(false, 'Kiểu gửi email không hợp lệ.', [], 422);
        }
        try {
            $database = Database::connection();
            $database->prepare(
                "INSERT INTO settings (setting_key, setting_value, description)
                 VALUES ('mailer.driver', :driver, 'Kênh gửi email đang được chọn')
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
            )->execute(['driver' => $driver]);
            AuditLog::record((int) Auth::user()['id'], 'mailer_driver_changed', 'setting', null, $driver, $database);
            return Response::json(true, 'Đã lưu kiểu gửi email.', ['driver' => $driver]);
        } catch (Throwable) {
            error_log('Mail driver settings could not be saved.');
            return Response::json(false, 'Chưa thể lưu cấu hình gửi email. Vui lòng thử lại sau.', [], 503);
        }
    }

    public function sendTest(): Response
    {
        $user = Auth::user();
        try {
            $result = (new MailService())->sendTemplate(
                (string) $user['email'],
                'Thư kiểm tra email - Nông Sản Sạch',
                'Thư kiểm tra gửi email',
                ['Thư này được gửi từ trang quản trị để kiểm tra cấu hình hiện tại.'],
                null,
                null,
                ['type' => 'admin_test', 'admin_id' => (int) $user['id']]
            );
            if (!$result['accepted']) {
                return Response::json(false, 'Không thể lưu thư kiểm tra vào hộp thư chờ.', [], 503);
            }
            $message = $result['delivered']
                ? 'Đã gửi thư kiểm tra tới email quản trị.'
                : 'Thư kiểm tra đã được lưu vào hộp thư chờ; chưa gửi thành công.';
            AuditLog::record((int) $user['id'], 'mailer_test_sent', 'mail', (int) $result['id'], null);
            return Response::json(true, $message, ['queued' => $result['queued'], 'id' => $result['id']]);
        } catch (Throwable) {
            error_log('Admin test email request failed.');
            return Response::json(false, 'Chưa thể gửi thư kiểm tra. Vui lòng thử lại sau.', [], 503);
        }
    }

    public function resend(string $id): Response
    {
        try {
            $messageId = (int) $id;
            $mailer = new MailService();
            $query = Database::connection()->prepare(
                'SELECT status FROM mail_outbox WHERE id = :id LIMIT 1'
            );
            $query->execute(['id' => $messageId]);
            $status = $query->fetchColumn();
            if ($status !== 'pending') {
                return Response::json(false, 'Không tìm thấy email đang chờ gửi.', [], 404);
            }
            if ($mailer->selectedDriver() === 'outbox') {
                return Response::json(false, 'Kênh đang chọn chỉ lưu thư; hãy chọn SMTP hoặc Brevo để gửi lại.', [], 422);
            }
            $sent = $mailer->resend($messageId);
            AuditLog::record((int) Auth::user()['id'], 'mailer_message_retried', 'mail', $messageId);
            return $sent
                ? Response::json(true, 'Đã gửi lại email.', ['id' => $messageId])
                : Response::json(false, 'Gửi lại chưa thành công; email vẫn được giữ trong hộp thư chờ.', ['id' => $messageId], 422);
        } catch (Throwable) {
            error_log('Admin email retry request failed.');
            return Response::json(false, 'Chưa thể gửi lại email. Vui lòng thử lại sau.', [], 503);
        }
    }
}
