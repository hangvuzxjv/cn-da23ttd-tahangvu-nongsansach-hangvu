<?php

declare(strict_types=1);

final class AdminContentController extends Controller
{
    public function pages(): void
    {
        $pages = [];
        $error = flash('error');
        try {
            $slugs = StaticPageService::publishedSlugs();
            $placeholders = implode(',', array_fill(0, count($slugs), '?'));
            $query = Database::connection()->prepare(
                'SELECT id, title, slug, content, status, updated_at
                 FROM pages WHERE slug IN (' . $placeholders . ') ORDER BY id'
            );
            $query->execute($slugs);
            $pages = $query->fetchAll();
        } catch (PDOException) {
            error_log('Admin static-page list could not be loaded.');
            http_response_code(503);
            $error = 'Không thể tải danh sách trang lúc này. Vui lòng thử lại sau.';
        }
        $this->adminView('admin/content-pages', [
            'title' => 'Quản lý trang tĩnh',
            'pages' => $pages,
            'success' => flash('success'),
            'error' => $error,
        ]);
    }

    public function savePage(string $id): void
    {
        $pageId = filter_var($id, FILTER_VALIDATE_INT);
        $title = trim((string) ($_POST['title'] ?? ''));
        $content = (string) ($_POST['content'] ?? '');
        $status = (string) ($_POST['status'] ?? '');
        if (!$pageId || mb_strlen($title, 'UTF-8') < 2 || mb_strlen($title, 'UTF-8') > 180
            || mb_strlen($content, 'UTF-8') > 100000
            || !in_array($status, ['draft', 'published'], true)) {
            flash('error', 'Thông tin trang chưa hợp lệ.');
            redirect('/admin/content/pages');
        }
        try {
            $pageCheck = Database::connection()->prepare('SELECT slug FROM pages WHERE id = :id LIMIT 1');
            $pageCheck->execute(['id' => $pageId]);
            if (!in_array((string) $pageCheck->fetchColumn(), StaticPageService::publishedSlugs(), true)) {
                flash('error', 'Không tìm thấy trang cần cập nhật.');
                redirect('/admin/content/pages');
            }
        } catch (PDOException) {
            error_log('Admin static-page lookup failed.');
            flash('error', 'Không thể cập nhật trang lúc này. Vui lòng thử lại sau.');
            redirect('/admin/content/pages');
        }
        try {
            $safeContent = StaticPageService::sanitizeHtml($content);
        } catch (InvalidArgumentException | RuntimeException) {
            flash('error', 'Không thể lọc nội dung trang. Vui lòng kiểm tra lại nội dung.');
            redirect('/admin/content/pages');
        }
        try {
            $query = Database::connection()->prepare(
                'UPDATE pages SET title = :title, content = :content, status = :status,
                    published_at = CASE WHEN :published = \'published\' THEN COALESCE(published_at, NOW()) ELSE NULL END
                 WHERE id = :id'
            );
            $query->execute([
                'title' => $title,
                'content' => $safeContent,
                'status' => $status,
                'published' => $status,
                'id' => $pageId,
            ]);
        } catch (PDOException) {
            error_log('Admin static-page update failed.');
            flash('error', 'Không thể lưu trang lúc này. Vui lòng thử lại sau.');
            redirect('/admin/content/pages');
        }
        flash('success', 'Đã lưu nội dung trang.');
        redirect('/admin/content/pages');
    }

    public function contacts(): void
    {
        try {
        $database = Database::connection();
        $status = (string) ($_GET['status'] ?? '');
        $validStatuses = ['new', 'processing', 'replied', 'closed'];
        $where = '';
        $parameters = [];
        if (in_array($status, $validStatuses, true)) {
            $where = ' WHERE status = :status';
            $parameters['status'] = $status;
        } else {
            $status = '';
        }
        $count = $database->prepare('SELECT COUNT(*) FROM contacts' . $where);
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $perPage = 20;
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, (int) ($_GET['page'] ?? 1)), $pages);
        $query = $database->prepare(
            'SELECT id, name, email, phone, subject, message, status, created_at
             FROM contacts' . $where . ' ORDER BY id DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($parameters as $key => $value) {
            $query->bindValue(':' . $key, $value);
        }
        $query->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $query->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $query->execute();
        $recipient = $database->query(
            "SELECT setting_value FROM settings WHERE setting_key = 'contact.notification_email' LIMIT 1"
        )->fetchColumn();
        $this->adminView('admin/contacts', [
            'title' => 'Tin nhắn liên hệ',
            'contacts' => $query->fetchAll(),
            'status' => $status,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'notificationEmail' => is_string($recipient) ? $recipient : '',
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
        } catch (PDOException) {
            error_log('Admin contact list could not be loaded.');
            http_response_code(503);
            $this->adminView('admin/contacts', [
                'title' => 'Tin nhắn liên hệ',
                'contacts' => [],
                'status' => '',
                'page' => 1,
                'pages' => 1,
                'total' => 0,
                'notificationEmail' => '',
                'success' => flash('success'),
                'error' => 'Không thể tải danh sách liên hệ lúc này. Vui lòng thử lại sau.',
            ]);
        }
    }

    public function updateContact(string $id): void
    {
        $contactId = filter_var($id, FILTER_VALIDATE_INT);
        $status = (string) ($_POST['status'] ?? '');
        if (!$contactId || !in_array($status, ['new', 'processing', 'replied', 'closed'], true)) {
            flash('error', 'Trạng thái liên hệ không hợp lệ.');
            redirect('/admin/contacts');
        }
        try {
            $query = Database::connection()->prepare('UPDATE contacts SET status = :status WHERE id = :id');
            $query->execute(['status' => $status, 'id' => $contactId]);
        } catch (PDOException) {
            error_log('Admin contact status update failed.');
            flash('error', 'Không thể cập nhật liên hệ lúc này. Vui lòng thử lại sau.');
            redirect('/admin/contacts');
        }
        flash('success', 'Đã cập nhật trạng thái liên hệ.');
        redirect('/admin/contacts');
    }

    public function saveContactEmail(): void
    {
        $email = trim((string) ($_POST['email'] ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            flash('error', 'Địa chỉ email nhận thông báo không hợp lệ.');
            redirect('/admin/contacts');
        }
        try {
            $query = Database::connection()->prepare(
                'INSERT INTO settings (setting_key, setting_value, description)
                 VALUES (\'contact.notification_email\', :email, \'Email nhận thông báo liên hệ mới\')
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
            );
            $query->execute(['email' => $email === '' ? null : $email]);
        } catch (PDOException) {
            error_log('Admin contact notification setting could not be saved.');
            flash('error', 'Không thể lưu cấu hình lúc này. Vui lòng thử lại sau.');
            redirect('/admin/contacts');
        }
        flash('success', 'Đã lưu cấu hình thông báo liên hệ.');
        redirect('/admin/contacts');
    }

    private function adminView(string $template, array $data): void
    {
        $data['menu'] = (new AdminController())->menu();
        $this->view($template, $data, 'admin');
    }
}
