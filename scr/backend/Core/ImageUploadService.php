<?php

declare(strict_types=1);

final class ImageUploadService
{
    private const DIRECTORIES = ['products', 'categories', 'regions', 'banners'];
    private const MAX_FILE_SIZE = 8 * 1024 * 1024;
    private const MAX_DIMENSION = 2560;
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => ['jpg', 'imagecreatefromjpeg'],
        'image/png' => ['png', 'imagecreatefrompng'],
        'image/webp' => ['webp', 'imagecreatefromwebp'],
    ];

    public static function store(mixed $file, string $directoryName): ?array
    {
        self::assertDirectory($directoryName);
        if ($file === null) {
            return null;
        }
        if (!is_array($file)) {
            throw new RuntimeException('Tệp tải lên không hợp lệ.');
        }

        $errorValue = $file['error'] ?? null;
        if (!is_int($errorValue) && !(is_string($errorValue) && ctype_digit($errorValue))) {
            throw new RuntimeException('Tệp tải lên không hợp lệ.');
        }
        $error = (int) $errorValue;
        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new RuntimeException('Ảnh vượt giới hạn tải lên của máy chủ. Vui lòng giảm dung lượng hoặc liên hệ quản trị viên.');
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Tải ảnh lên thất bại. Vui lòng chọn lại tệp ảnh.');
        }

        $temporary = $file['tmp_name'] ?? null;
        if (!is_string($temporary) || !is_uploaded_file($temporary)) {
            throw new RuntimeException('Tệp tải lên không hợp lệ.');
        }
        $size = filesize($temporary);
        if ($size === false || $size < 1 || $size > self::MAX_FILE_SIZE) {
            throw new RuntimeException('Mỗi ảnh phải có dung lượng tối đa 8 MB.');
        }

        if (!class_exists('finfo')) {
            throw new RuntimeException('Máy chủ chưa hỗ trợ kiểm tra định dạng ảnh.');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporary);
        if (!is_string($mime) || !isset(self::ALLOWED_MIME_TYPES[$mime])) {
            throw new RuntimeException('Chỉ chấp nhận ảnh JPG, JPEG, PNG hoặc WebP.');
        }
        $imageInfo = getimagesize($temporary);
        if ($imageInfo === false || ($imageInfo['mime'] ?? '') !== $mime) {
            throw new RuntimeException('Tệp tải lên không phải ảnh hợp lệ.');
        }
        if ((int) $imageInfo[0] < 1 || (int) $imageInfo[1] < 1
            || (int) $imageInfo[0] > intdiv(40000000, (int) $imageInfo[1])) {
            throw new RuntimeException('Kích thước ảnh vượt quá giới hạn xử lý 40 megapixel.');
        }

        $directory = self::uploadDirectory($directoryName, true);
        $extension = self::ALLOWED_MIME_TYPES[$mime][0];
        $filename = bin2hex(random_bytes(20)) . '.' . $extension;
        $destination = $directory . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($temporary, $destination)) {
            throw new RuntimeException('Không thể lưu ảnh tải lên.');
        }

        try {
            self::resize($destination, $mime, self::ALLOWED_MIME_TYPES[$mime][1]);
        } catch (Throwable $exception) {
            if (is_file($destination) && !unlink($destination)) {
                error_log('Không thể dọn ảnh tải lên sau lỗi xử lý: ' . $destination);
            }
            throw $exception;
        }

        return [
            'path' => $destination,
            'url' => '/uploads/' . $directoryName . '/' . $filename,
        ];
    }

    public static function resize(string $path, string $mime, string $decoder): void
    {
        $encoder = match ($mime) {
            'image/jpeg' => 'imagejpeg',
            'image/png' => 'imagepng',
            'image/webp' => 'imagewebp',
            default => '',
        };
        if (!extension_loaded('gd') || !function_exists($decoder) || $encoder === '' || !function_exists($encoder)) {
            return;
        }

        $source = $decoder($path);
        if ($source === false) {
            throw new RuntimeException('Không thể giải mã ảnh để xử lý.');
        }

        try {
            $width = imagesx($source);
            $height = imagesy($source);
            if (max($width, $height) <= self::MAX_DIMENSION) {
                return;
            }

            $scale = self::MAX_DIMENSION / max($width, $height);
            $newWidth = max(1, (int) round($width * $scale));
            $newHeight = max(1, (int) round($height * $scale));
            $target = imagecreatetruecolor($newWidth, $newHeight);
            if ($target === false) {
                throw new RuntimeException('Không đủ bộ nhớ để thu nhỏ ảnh.');
            }

            try {
                if ($mime === 'image/png' || $mime === 'image/webp') {
                    imagealphablending($target, false);
                    imagesavealpha($target, true);
                    $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
                    imagefill($target, 0, 0, $transparent);
                }

                imagecopyresampled($target, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                $saved = match ($mime) {
                    'image/jpeg' => imagejpeg($target, $path, 92),
                    'image/png' => imagepng($target, $path, 7),
                    'image/webp' => imagewebp($target, $path, 92),
                    default => false,
                };
                if (!$saved) {
                    throw new RuntimeException('Không thể thu nhỏ hoặc lưu ảnh.');
                }
            } finally {
                imagedestroy($target);
            }
        } finally {
            imagedestroy($source);
        }
    }

    public static function deleteStored(string $url, string $directoryName): void
    {
        self::assertDirectory($directoryName);
        $path = self::storedFilePath($url, $directoryName);
        if ($path === null) {
            return;
        }
        if (!unlink($path)) {
            throw new RuntimeException('Không thể xóa tệp ảnh khỏi ổ đĩa: ' . basename($path));
        }
    }

    public static function existsStored(string $url, string $directoryName): bool
    {
        self::assertDirectory($directoryName);
        return self::storedFilePath($url, $directoryName) !== null;
    }

    private static function storedFilePath(string $url, string $directoryName): ?string
    {
        $prefix = '/uploads/' . $directoryName . '/';
        if (!str_starts_with($url, $prefix)) {
            return null;
        }
        $filename = substr($url, strlen($prefix));
        if (!preg_match('/\A[a-z0-9][a-z0-9._-]*\.(?:jpe?g|png|webp)\z/i', $filename)) {
            return null;
        }

        $directory = self::uploadDirectory($directoryName, false);
        if ($directory === null) {
            return null;
        }
        $path = realpath($directory . DIRECTORY_SEPARATOR . $filename);
        if ($path === false || !is_file($path) || dirname($path) !== $directory) {
            return null;
        }
        return $path;
    }

    private static function uploadDirectory(string $directoryName, bool $create): ?string
    {
        $root = realpath(ROOT_PATH);
        $uploads = realpath(ROOT_PATH . DIRECTORY_SEPARATOR . 'uploads');
        if ($root === false || $uploads === false || !self::isPathInside($uploads, $root)) {
            throw new RuntimeException('Thư mục lưu ảnh không hợp lệ.');
        }

        $directory = $uploads . DIRECTORY_SEPARATOR . $directoryName;
        if ($create && !is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Không thể tạo thư mục lưu ảnh.');
        }
        $resolved = realpath($directory);
        if ($resolved === false) {
            return null;
        }
        $expected = $uploads . DIRECTORY_SEPARATOR . $directoryName;
        if (!self::samePath($resolved, $expected) || !self::isPathInside($resolved, $uploads)) {
            throw new RuntimeException('Đường dẫn lưu ảnh không hợp lệ.');
        }
        return $resolved;
    }

    private static function assertDirectory(string $directoryName): void
    {
        if (!in_array($directoryName, self::DIRECTORIES, true)) {
            throw new RuntimeException('Thư mục lưu ảnh không hợp lệ.');
        }
    }

    private static function isPathInside(string $path, string $parent): bool
    {
        $prefix = rtrim($parent, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        return str_starts_with(strtolower($path), strtolower($prefix));
    }

    private static function samePath(string $first, string $second): bool
    {
        return strtolower(rtrim($first, DIRECTORY_SEPARATOR)) === strtolower(rtrim($second, DIRECTORY_SEPARATOR));
    }
}
