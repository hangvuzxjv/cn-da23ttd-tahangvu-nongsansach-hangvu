<?php

declare(strict_types=1);

final class AdminContentController extends Controller
{
    public function banners(): void
    {
        $database = Database::connection();
        $banners = $database->query(
            'SELECT id, title, description, image_url, button_label, link_url, sort_order, status
             FROM banners ORDER BY sort_order, id'
        )->fetchAll();
        $this->adminView('admin/banners', [
            'title' => 'Quản lý banner',
            'banners' => $banners,
            'editBanner' => null,
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public function editBanner(string $id): void
    {
        $bannerId = filter_var($id, FILTER_VALIDATE_INT);
        $statement = Database::connection()->prepare('SELECT * FROM banners WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $bannerId ?: 0]);
        $banner = $statement->fetch();
        if (!$banner) {
            flash('error', 'Không tìm thấy banner cần sửa.');
            redirect('/admin/content/banners');
        }
        $banners = Database::connection()->query(
            'SELECT id, title, description, image_url, button_label, link_url, sort_order, status
             FROM banners ORDER BY sort_order, id'
        )->fetchAll();
        $this->adminView('admin/banners', [
            'title' => 'Sửa banner',
            'banners' => $banners,
            'editBanner' => $banner,
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public function saveBanner(string $id = '0'): void
    {
        $bannerId = filter_var($id, FILTER_VALIDATE_INT) ?: 0;
        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $buttonLabel = trim((string) ($_POST['button_label'] ?? ''));
        $linkUrl = trim((string) ($_POST['link_url'] ?? ''));
        $sortOrder = filter_var($_POST['sort_order'] ?? 0, FILTER_VALIDATE_INT);
        $status = (string) ($_POST['status'] ?? 'inactive');
        if (mb_strlen($title, 'UTF-8') < 2 || mb_strlen($title, 'UTF-8') > 180
            || mb_strlen($description, 'UTF-8') > 300 || mb_strlen($buttonLabel, 'UTF-8') > 80
            || mb_strlen($linkUrl, 'UTF-8') > 500 || !is_int($sortOrder)
            || !in_array($status, ['active', 'inactive'], true)) {
            flash('error', 'Thông tin banner chưa hợp lệ.');
            redirect('/admin/content/banners' . ($bannerId ? '/' . $bannerId . '/edit' : ''));
        }
        $database = Database::connection();
        $existing = null;
        if ($bannerId) {
            $lookup = $database->prepare('SELECT image_url FROM banners WHERE id = :id LIMIT 1');
            $lookup->execute(['id' => $bannerId]);
            $existing = $lookup->fetch();
            if (!$existing) {
                flash('error', 'Không tìm thấy banner cần cập nhật.');
                redirect('/admin/content/banners');
            }
        }
        try {
            $imageUrl = $this->storeBannerUpload($_FILES['image'] ?? null)
                ?? (string) ($existing['image_url'] ?? '');
            if ($imageUrl === '') {
                throw new InvalidArgumentException('Ảnh banner là bắt buộc.');
            }
            if ($bannerId) {
                $query = $database->prepare(
                    'UPDATE banners SET title = :title, description = :description, image_url = :image_url,
                        button_label = :button_label, link_url = :link_url, sort_order = :sort_order, status = :status
                     WHERE id = :id'
                );
                $query->execute([
                    'title' => $title, 'description' => $description, 'image_url' => $imageUrl,
                    'button_label' => $buttonLabel, 'link_url' => $linkUrl, 'sort_order' => $sortOrder,
                    'status' => $status, 'id' => $bannerId,
                ]);
            } else {
                $query = $database->prepare(
                    'INSERT INTO banners (title, description, image_url, button_label, link_url, sort_order, status)
                     VALUES (:title, :description, :image_url, :button_label, :link_url, :sort_order, :status)'
                );
                $query->execute([
                    'title' => $title, 'description' => $description, 'image_url' => $imageUrl,
                    'button_label' => $buttonLabel, 'link_url' => $linkUrl, 'sort_order' => $sortOrder,
                    'status' => $status,
                ]);
            }
        } catch (InvalidArgumentException | RuntimeException $exception) {
            flash('error', $exception->getMessage());
            redirect('/admin/content/banners' . ($bannerId ? '/' . $bannerId . '/edit' : ''));
        } catch (PDOException $exception) {
            error_log('Admin banner save failed: ' . $exception->getMessage());
            flash('error', 'Không thể lưu banner lúc này. Vui lòng thử lại sau.');
            redirect('/admin/content/banners' . ($bannerId ? '/' . $bannerId . '/edit' : ''));
        }
        flash('success', $bannerId ? 'Đã cập nhật banner.' : 'Đã thêm banner.');
        redirect('/admin/content/banners');
    }

    public function deleteBanner(string $id): void
    {
        $bannerId = filter_var($id, FILTER_VALIDATE_INT);
        if (!$bannerId) {
            flash('error', 'Banner không hợp lệ.');
            redirect('/admin/content/banners');
        }
        $query = Database::connection()->prepare('DELETE FROM banners WHERE id = :id');
        $query->execute(['id' => $bannerId]);
        flash('success', 'Đã xóa banner.');
        redirect('/admin/content/banners');
    }

    public function toggleBanner(string $id): void
    {
        $bannerId = filter_var($id, FILTER_VALIDATE_INT);
        $query = Database::connection()->prepare(
            "UPDATE banners SET status = CASE WHEN status = 'active' THEN 'inactive' ELSE 'active' END WHERE id = :id"
        );
        $query->execute(['id' => $bannerId ?: 0]);
        flash('success', 'Đã cập nhật trạng thái banner.');
        redirect('/admin/content/banners');
    }

    private function storeBannerUpload(mixed $file): ?string
    {
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK
            || (int) ($file['size'] ?? 0) < 1 || (int) $file['size'] > 2 * 1024 * 1024
            || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) {
            throw new InvalidArgumentException('Ảnh phải là tệp hợp lệ và không vượt quá 2MB.');
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'text/xml' => 'svg',
            'application/xml' => 'svg',
        ];
        if (!isset($allowed[$mime])) {
            throw new InvalidArgumentException('Chỉ chấp nhận ảnh JPG, PNG, WebP hoặc SVG.');
        }
        $contents = file_get_contents($file['tmp_name']);
        if (!is_string($contents) || $contents === '') {
            throw new InvalidArgumentException('Không thể đọc ảnh tải lên.');
        }
        if ($allowed[$mime] === 'svg') {
            $contents = preg_replace('/<script\b[^>]*>.*?<\/script\s*>/is', '', $contents);
            $contents = preg_replace('/\s+on[a-z]+\s*=\s*(["\']).*?\1/is', '', (string) $contents);
            $contents = preg_replace('/\s+(?:href|xlink:href)\s*=\s*(["\'])(?!#).*?\1/is', '', (string) $contents);
            $contents = preg_replace('/url\s*\(\s*(?!#)[^)]*\)/i', '', (string) $contents);
            $contents = preg_replace('/<(!DOCTYPE|\\?xml-stylesheet)\b[^>]*>/i', '', (string) $contents);
            if (!is_string($contents) || stripos($contents, '<svg') === false) {
                throw new InvalidArgumentException('SVG không hợp lệ.');
            }
        }
        $directory = dirname(__DIR__, 2) . '/img/banners';
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Không thể tạo thư mục ảnh banner.');
        }
        $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
        $destination = $directory . '/' . $filename;
        if ($allowed[$mime] === 'svg') {
            if (file_put_contents($destination, $contents, LOCK_EX) === false) {
                throw new RuntimeException('Không thể lưu ảnh banner.');
            }
        } elseif (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new RuntimeException('Không thể lưu ảnh banner.');
        }
        return '/img/banners/' . $filename;
    }

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
        $settingKeys = [
            'contact.notification_email',
            'store_contact_address',
            'store_contact_phone',
            'store_contact_email',
            'store_business_hours',
        ];
        $settingsQuery = $database->prepare(
            'SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('
            . implode(',', array_fill(0, count($settingKeys), '?')) . ')'
        );
        $settingsQuery->execute($settingKeys);
        $settings = [];
        foreach ($settingsQuery->fetchAll() as $setting) {
            $settings[$setting['setting_key']] = (string) ($setting['setting_value'] ?? '');
        }
        $this->adminView('admin/contacts', [
            'title' => 'Tin nhắn liên hệ',
            'contacts' => $query->fetchAll(),
            'status' => $status,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'notificationEmail' => $settings['contact.notification_email'] ?? '',
            'storeContact' => [
                'address' => $settings['store_contact_address'] ?? '',
                'phone' => $settings['store_contact_phone'] ?? '',
                'email' => $settings['store_contact_email'] ?? '',
                'business_hours' => $settings['store_business_hours'] ?? '',
            ],
            'tawkConfiguration' => (new TawkChatService($database))->configuration(),
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
                'storeContact' => ['address' => '', 'phone' => '', 'email' => '', 'business_hours' => ''],
                'tawkConfiguration' => ['enabled' => false, 'property_id' => '', 'widget_id' => ''],
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

    public function saveStoreContactSettings(): void
    {
        $fields = [
            'address' => ['store_contact_address', 500, 'Địa chỉ'],
            'phone' => ['store_contact_phone', 30, 'Số điện thoại'],
            'email' => ['store_contact_email', 190, 'Email'],
            'business_hours' => ['store_business_hours', 180, 'Giờ làm việc'],
        ];
        $values = [];
        foreach ($fields as $input => [$key, $maximum, $label]) {
            $value = $_POST[$input] ?? '';
            if (!is_string($value)) {
                flash('error', $label . ' không hợp lệ.');
                redirect('/admin/contacts');
            }
            $value = trim($value);
            if (mb_strlen($value, 'UTF-8') > $maximum) {
                flash('error', $label . ' vượt quá độ dài cho phép.');
                redirect('/admin/contacts');
            }
            $values[$key] = $value;
        }
        if ($values['store_contact_email'] !== ''
            && filter_var($values['store_contact_email'], FILTER_VALIDATE_EMAIL) === false) {
            flash('error', 'Email cửa hàng không hợp lệ.');
            redirect('/admin/contacts');
        }

        $database = Database::connection();
        $database->beginTransaction();
        try {
            $save = $database->prepare(
                'INSERT INTO settings (setting_key, setting_value, description)
                 VALUES (:setting_key, :setting_value, :description)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
            );
            $descriptions = [
                'store_contact_address' => 'Địa chỉ hiển thị trên trang liên hệ',
                'store_contact_phone' => 'Số điện thoại hiển thị trên trang liên hệ',
                'store_contact_email' => 'Email hiển thị trên trang liên hệ',
                'store_business_hours' => 'Giờ làm việc hiển thị trên trang liên hệ',
            ];
            foreach ($values as $key => $value) {
                $save->execute([
                    'setting_key' => $key,
                    'setting_value' => $value,
                    'description' => $descriptions[$key],
                ]);
            }
            AuditLog::record(
                (int) Auth::user()['id'],
                'store_contact_settings_updated',
                'settings',
                null,
                'fields=' . implode(',', array_keys($values)),
                $database
            );
            $database->commit();
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            if ($exception instanceof PDOException) {
                error_log('Store contact settings could not be saved: ' . $exception->getMessage());
                flash('error', 'Không thể lưu thông tin cửa hàng lúc này.');
                redirect('/admin/contacts');
            }
            throw $exception;
        }
        flash('success', 'Đã lưu thông tin cửa hàng.');
        redirect('/admin/contacts');
    }

    public function saveTawkSettings(): void
    {
        $rawPropertyId = $_POST['property_id'] ?? '';
        $rawWidgetId = $_POST['widget_id'] ?? '';
        if (!is_string($rawPropertyId) || !is_string($rawWidgetId)) {
            flash('error', 'Property ID hoặc Widget ID Tawk.to không hợp lệ.');
            redirect('/admin/contacts');
        }
        $propertyId = trim($rawPropertyId);
        $widgetId = trim($rawWidgetId);
        $enabledValue = $_POST['enabled'] ?? '0';
        if (!is_string($enabledValue) || !in_array($enabledValue, ['0', '1'], true)) {
            flash('error', 'Trạng thái khung chat không hợp lệ.');
            redirect('/admin/contacts');
        }
        try {
            (new TawkChatService())->saveConfiguration(
                (int) Auth::user()['id'],
                $propertyId,
                $widgetId,
                $enabledValue === '1'
            );
        } catch (InvalidArgumentException $exception) {
            flash('error', $exception->getMessage());
            redirect('/admin/contacts');
        } catch (PDOException $exception) {
            error_log('Tawk.to settings could not be saved: ' . $exception->getMessage());
            flash('error', 'Không thể lưu cấu hình khung chat lúc này.');
            redirect('/admin/contacts');
        }
        flash('success', 'Đã lưu cấu hình khung chat.');
        redirect('/admin/contacts');
    }

    private function adminView(string $template, array $data): void
    {
        $data['menu'] = (new AdminController())->menu();
        $this->view($template, $data, 'admin');
    }
}
