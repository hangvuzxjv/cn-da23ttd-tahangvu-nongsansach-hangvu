<?php

declare(strict_types=1);

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

final class TraceabilityController extends Controller
{
    private const EVENT_LABELS = [
        'planting' => 'Gieo trồng',
        'care' => 'Chăm sóc',
        'harvest' => 'Thu hoạch',
        'processing' => 'Sơ chế',
        'packaging' => 'Đóng gói',
        'transport' => 'Vận chuyển',
    ];

    public function lookup(): void
    {
        $code = trim((string) ($_GET['batch_code'] ?? ''));
        if ($code === '' || strlen($code) > 100) {
            http_response_code(400);
            $this->view('client/traceability-not-found', [
                'title' => 'Tra cứu nguồn gốc',
                'metaDescription' => 'Tra cứu nguồn gốc nông sản theo mã lô.',
                'batchCode' => '',
                'error' => 'Vui lòng nhập mã lô hợp lệ.',
            ]);
            return;
        }
        redirect('/truy-xuat/' . rawurlencode($code));
    }

    public function show(string $batchCode): void
    {
        $statement = Database::connection()->prepare(
            "SELECT b.*, p.id AS product_id, p.name AS product_name, p.slug AS product_slug,
                    COALESCE(images.image_url, '/img/product-placeholder.svg') AS product_image,
                    f.id AS farm_id, f.name AS farm_name, f.address AS farm_address, f.contact_name,
                    f.phone AS farm_phone, f.certifications, f.certification_number,
                    f.certification_expires_at, f.latitude, f.longitude, f.image_url AS farm_image,
                    f.certificate_image_url, r.name AS region_name, r.slug AS region_slug,
                    parent.name AS parent_region_name
             FROM product_batches b
             JOIN products p ON p.id = b.product_id
             LEFT JOIN farms f ON f.id = COALESCE(b.farm_id, p.farm_id)
             LEFT JOIN regions r ON r.id = f.region_id
             LEFT JOIN regions parent ON parent.id = r.parent_id
             LEFT JOIN (
                 SELECT product_id, SUBSTRING_INDEX(GROUP_CONCAT(image_url ORDER BY is_primary DESC, sort_order, id), ',', 1) AS image_url
                 FROM product_images GROUP BY product_id
             ) images ON images.product_id = p.id
             WHERE b.batch_code = :batch_code
             LIMIT 1"
        );
        $statement->execute(['batch_code' => $batchCode]);
        $batch = $statement->fetch();
        if (!$batch) {
            http_response_code(404);
            $this->view('client/traceability-not-found', [
                'title' => 'Không tìm thấy mã lô',
                'metaDescription' => 'Tra cứu nguồn gốc nông sản theo mã lô.',
                'batchCode' => $batchCode,
                'error' => null,
            ]);
            return;
        }

        $this->recordScan((int) $batch['id']);
        $eventsQuery = Database::connection()->prepare(
            'SELECT event_type, event_at, location, description, image_url
             FROM trace_events WHERE batch_id = :batch_id
             ORDER BY sort_order, event_at, id'
        );
        $eventsQuery->execute(['batch_id' => $batch['id']]);
        $events = $eventsQuery->fetchAll();
        foreach ($events as &$event) {
            $event['event_label'] = self::EVENT_LABELS[$event['event_type']] ?? $event['event_type'];
        }
        unset($event);

        $daysToExpiry = $batch['expires_at'] === null
            ? null
            : (int) (new DateTimeImmutable('today'))->diff(new DateTimeImmutable($batch['expires_at']))->format('%r%a');
        $expiryStatus = $daysToExpiry === null
            ? 'Chưa có ngày hết hạn'
            : ($daysToExpiry < 0 ? 'Đã hết hạn' : ($daysToExpiry <= 7 ? 'Sắp hết hạn trong 7 ngày' : 'Còn hạn'));
        $statusLabels = [
            'selling' => 'Đang bán',
            'suspended' => 'Tạm ngưng',
            'recalled' => 'Đã thu hồi',
            'ended' => 'Đã hết',
        ];
        $inspectionLabels = ['pending' => 'Đang chờ kiểm định', 'passed' => 'Đạt', 'failed' => 'Không đạt'];
        $mapsQuery = $batch['latitude'] !== null && $batch['longitude'] !== null
            ? $batch['latitude'] . ',' . $batch['longitude']
            : $batch['farm_address'];
        $this->view('client/traceability', [
            'title' => 'Truy xuất lô ' . $batch['batch_code'],
            'metaDescription' => 'Thông tin truy xuất nguồn gốc sản phẩm ' . $batch['product_name'] . ', mã lô ' . $batch['batch_code'] . '.',
            'batch' => $batch,
            'events' => $events,
            'expiryStatus' => $expiryStatus,
            'daysToExpiry' => $daysToExpiry,
            'batchStatusLabel' => $statusLabels[$batch['status']] ?? $batch['status'],
            'inspectionLabel' => $inspectionLabels[$batch['inspection_result']] ?? $batch['inspection_result'],
            'mapsUrl' => 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode((string) $mapsQuery),
            'scanCount' => $this->scanCount((int) $batch['id']),
        ]);
    }

    public function qr(string $batchCode): Response
    {
        $statement = Database::connection()->prepare(
            'SELECT batch_code FROM product_batches WHERE batch_code = :batch_code LIMIT 1'
        );
        $statement->execute(['batch_code' => $batchCode]);
        $storedCode = $statement->fetchColumn();
        if ($storedCode === false) {
            return new Response('Not found', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $targetUrl = absolute_url('/truy-xuat/' . rawurlencode((string) $storedCode));
        $renderer = new ImageRenderer(new RendererStyle(320, 8), new SvgImageBackEnd());
        $svg = (new Writer($renderer))->writeString($targetUrl);
        $filename = preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $storedCode) . '.svg';
        return new Response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function recordScan(int $batchId): void
    {
        $database = Database::connection();
        $remoteIp = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $ipHash = hash('sha256', $remoteIp);
        $userAgent = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $database->beginTransaction();
        try {
            $lock = $database->prepare('SELECT id FROM product_batches WHERE id = :id FOR UPDATE');
            $lock->execute(['id' => $batchId]);
            $recent = $database->prepare(
                'SELECT id FROM trace_scans
                 WHERE batch_id = :batch_id AND ip_hash = :ip_hash
                   AND scanned_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
                 LIMIT 1'
            );
            $recent->execute(['batch_id' => $batchId, 'ip_hash' => $ipHash]);
            if (!$recent->fetch()) {
                $database->prepare(
                    'INSERT INTO trace_scans (batch_id, ip_hash, user_agent)
                     VALUES (:batch_id, :ip_hash, :user_agent)'
                )->execute([
                    'batch_id' => $batchId,
                    'ip_hash' => $ipHash,
                    'user_agent' => $userAgent === '' ? null : mb_substr($userAgent, 0, 200, 'UTF-8'),
                ]);
            }
            $database->commit();
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    private function scanCount(int $batchId): int
    {
        $statement = Database::connection()->prepare('SELECT COUNT(*) FROM trace_scans WHERE batch_id = :batch_id');
        $statement->execute(['batch_id' => $batchId]);
        return (int) $statement->fetchColumn();
    }
}
