<?php

declare(strict_types=1);

final class TawkChatService
{
    private PDO $database;

    public function __construct(?PDO $database = null)
    {
        $this->database = $database ?? Database::connection();
    }

    public static function isValidPropertyId(string $propertyId): bool
    {
        return preg_match('/\A[a-f0-9]{24}\z/i', $propertyId) === 1;
    }

    public static function isValidWidgetId(string $widgetId): bool
    {
        return preg_match('/\A[a-z0-9]{1,32}\z/i', $widgetId) === 1;
    }

    public static function validateConfiguration(string $propertyId, string $widgetId, bool $enabled): void
    {
        if (($propertyId !== '' && !self::isValidPropertyId($propertyId))
            || ($widgetId !== '' && !self::isValidWidgetId($widgetId))) {
            throw new InvalidArgumentException('Property ID hoặc Widget ID Tawk.to không hợp lệ.');
        }
        if ($enabled && ($propertyId === '' || $widgetId === '')) {
            throw new InvalidArgumentException('Cần nhập đủ Property ID và Widget ID trước khi bật khung chat.');
        }
    }

    public function configuration(): array
    {
        $query = $this->database->prepare(
            'SELECT setting_key, setting_value FROM settings
             WHERE setting_key IN (\'tawk_enabled\', \'tawk_property_id\', \'tawk_widget_id\')'
        );
        $query->execute();
        $values = [];
        foreach ($query->fetchAll() as $row) {
            $values[$row['setting_key']] = (string) ($row['setting_value'] ?? '');
        }
        $propertyId = $values['tawk_property_id'] ?? '';
        $widgetId = $values['tawk_widget_id'] ?? '';
        $enabled = ($values['tawk_enabled'] ?? '0') === '1';
        $valid = $propertyId !== '' && $widgetId !== ''
            && self::isValidPropertyId($propertyId)
            && self::isValidWidgetId($widgetId);
        return [
            'enabled' => $enabled && $valid,
            'property_id' => $propertyId,
            'widget_id' => $widgetId,
        ];
    }

    public function isConfigured(): bool
    {
        return $this->configuration()['enabled'];
    }

    public function embedScript(): string
    {
        $configuration = $this->configuration();
        if (!$configuration['enabled']) {
            return '';
        }
        $source = 'https://embed.tawk.to/'
            . $configuration['property_id'] . '/'
            . $configuration['widget_id'];
        $encodedSource = json_encode(
            $source,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        return '<script>var Tawk_API=Tawk_API||{},Tawk_LoadStart=new Date();'
            . '(function(){var s1=document.createElement("script"),'
            . 's0=document.getElementsByTagName("script")[0];s1.async=true;s1.src=' . $encodedSource . ';'
            . 's1.charset="UTF-8";s1.setAttribute("crossorigin","*");s0.parentNode.insertBefore(s1,s0);'
            . '})();</script>';
    }

    public function saveConfiguration(int $actorId, string $propertyId, string $widgetId, bool $enabled): void
    {
        if ($actorId < 1) {
            throw new InvalidArgumentException('Tài khoản quản trị không hợp lệ.');
        }
        self::validateConfiguration($propertyId, $widgetId, $enabled);
        $this->database->beginTransaction();
        try {
            $save = $this->database->prepare(
                'INSERT INTO settings (setting_key, setting_value, description)
                 VALUES (:setting_key, :setting_value, :description)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
            );
            foreach ([
                ['tawk_property_id', $propertyId, 'Property ID của Tawk.to'],
                ['tawk_widget_id', $widgetId, 'Widget ID của Tawk.to'],
                ['tawk_enabled', $enabled ? '1' : '0', 'Bật/tắt khung chat Tawk.to'],
            ] as [$key, $value, $description]) {
                $save->execute([
                    'setting_key' => $key,
                    'setting_value' => $value,
                    'description' => $description,
                ]);
            }
            AuditLog::record(
                $actorId,
                'tawk_chat_settings_updated',
                'settings',
                null,
                'enabled=' . ($enabled ? '1' : '0'),
                $this->database
            );
            $this->database->commit();
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }
}
