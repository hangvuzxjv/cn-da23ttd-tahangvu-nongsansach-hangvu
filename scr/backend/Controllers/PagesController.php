<?php

declare(strict_types=1);

final class PagesController extends Controller
{
    public function show(string $slug): void
    {
        if (!in_array($slug, StaticPageService::publishedSlugs(), true)) {
            $this->notFound();
            return;
        }
        $query = Database::connection()->prepare(
            "SELECT title, slug, content FROM pages WHERE slug = :slug AND status = 'published' LIMIT 1"
        );
        $query->execute(['slug' => $slug]);
        $page = $query->fetch();
        if (!$page) {
            $this->notFound();
            return;
        }
        $page['content'] = StaticPageService::sanitizeHtml((string) $page['content']);
        $this->view('client/static-page', [
            'title' => $page['title'],
            'page' => $page,
            'metaDescription' => mb_substr(trim(strip_tags((string) $page['content'])), 0, 155, 'UTF-8'),
        ]);
    }

    public function contact(): void
    {
        $this->view('client/contact', [
            'title' => 'Liên hệ',
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public function submitContact(): Response
    {
        $input = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'phone' => trim((string) ($_POST['phone'] ?? '')),
            'subject' => trim((string) ($_POST['subject'] ?? '')),
            'message' => trim((string) ($_POST['message'] ?? '')),
        ];
        if (trim((string) ($_POST['company'] ?? '')) !== '') {
            return $this->contactResponse(true, 'Cảm ơn bạn đã gửi liên hệ.');
        }
        if (mb_strlen($input['name'], 'UTF-8') < 2 || mb_strlen($input['name'], 'UTF-8') > 150
            || filter_var($input['email'], FILTER_VALIDATE_EMAIL) === false
            || mb_strlen($input['email'], 'UTF-8') > 190
            || mb_strlen($input['phone'], 'UTF-8') > 30
            || mb_strlen($input['subject'], 'UTF-8') > 200
            || mb_strlen($input['message'], 'UTF-8') < 10
            || mb_strlen($input['message'], 'UTF-8') > 5000) {
            return $this->contactResponse(false, 'Vui lòng kiểm tra họ tên, email và nội dung liên hệ (10–5.000 ký tự).', 422);
        }

        try {
            $database = Database::connection();
            $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
            $keys = [
                hash('sha256', 'ip:' . $ip),
                hash('sha256', 'email:' . mb_strtolower($input['email'], 'UTF-8')),
            ];
            $database->exec('DELETE FROM contact_rate_limits WHERE window_started_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
            foreach ($keys as $key) {
                $limit = $database->prepare(
                    'INSERT INTO contact_rate_limits (key_hash, window_started_at, request_count)
                     VALUES (:key_hash, NOW(), 1)
                     ON DUPLICATE KEY UPDATE
                        request_count = IF(window_started_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE), 1, request_count + 1),
                        window_started_at = IF(window_started_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE), NOW(), window_started_at)'
                );
                $limit->execute(['key_hash' => $key]);
                $check = $database->prepare('SELECT request_count FROM contact_rate_limits WHERE key_hash = :key_hash');
                $check->execute(['key_hash' => $key]);
                if ((int) $check->fetchColumn() > 3) {
                    return $this->contactResponse(false, 'Bạn đã gửi liên hệ quá thường xuyên. Vui lòng thử lại sau 15 phút.', 429);
                }
            }

            $insert = $database->prepare(
                "INSERT INTO contacts (name, email, phone, subject, message, status)
                 VALUES (:name, :email, :phone, :subject, :message, 'new')"
            );
            $insert->execute([
                'name' => $input['name'],
                'email' => $input['email'],
                'phone' => $input['phone'] === '' ? null : $input['phone'],
                'subject' => $input['subject'] === '' ? null : $input['subject'],
                'message' => $input['message'],
            ]);
            $recipient = $database->query(
                "SELECT setting_value FROM settings WHERE setting_key = 'contact.notification_email' LIMIT 1"
            )->fetchColumn();
            if (is_string($recipient) && filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                try {
                    (new MailService($database))->sendTemplate(
                        $recipient,
                        'Tin nhắn liên hệ mới - Nông Sản Sạch',
                        'Có liên hệ mới từ khách hàng',
                        [
                            'Khách hàng: ' . $input['name'],
                            'Email: ' . $input['email'],
                            'Tiêu đề: ' . ($input['subject'] ?: 'Không có'),
                            'Nội dung: ' . $input['message'],
                        ],
                        'Tin nhắn liên hệ mới từ cửa hàng Nông Sản Sạch.'
                    );
                } catch (Throwable) {
                    error_log('Contact notification email failed; the contact message was saved.');
                }
            }
        } catch (PDOException) {
            error_log('Contact submission could not be saved.');
            return $this->contactResponse(false, 'Chưa thể lưu liên hệ lúc này. Vui lòng thử lại sau.', 503);
        }
        return $this->contactResponse(true, 'Cảm ơn bạn. Cửa hàng đã nhận được liên hệ và sẽ phản hồi sớm.');
    }

    private function contactResponse(bool $success, string $message, int $status = 200): Response
    {
        if (str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
            || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest') {
            return Response::json($success, $message, [], $status);
        }
        flash($success ? 'success' : 'error', $message);
        return Response::redirect('/lien-he');
    }

    private function notFound(): void
    {
        http_response_code(404);
        View::renderError(404);
    }
}
