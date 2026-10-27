<?php

declare(strict_types=1);

final class BatchCodeGenerator
{
    public static function next(PDO $database, string $sku): string
    {
        $month = date('ym');
        $prefix = 'NS-' . $month . '-' . strtoupper($sku) . '-';
        $sequenceKey = 'batch:' . $month . ':' . substr(hash('sha256', strtoupper($sku)), 0, 16);
        $database->prepare(
            'INSERT IGNORE INTO document_sequences (sequence_key, next_value) VALUES (:key, 1)'
        )->execute(['key' => $sequenceKey]);
        $sequenceQuery = $database->prepare(
            'SELECT next_value FROM document_sequences WHERE sequence_key = :key FOR UPDATE'
        );
        $sequenceQuery->execute(['key' => $sequenceKey]);
        $nextValue = (int) $sequenceQuery->fetchColumn();
        $existingQuery = $database->prepare(
            'SELECT MAX(CAST(SUBSTRING(batch_code, CHAR_LENGTH(:prefix_length) + 1) AS UNSIGNED))
             FROM product_batches
             WHERE LEFT(batch_code, CHAR_LENGTH(:prefix_for_length)) = :prefix_for_value'
        );
        $existingQuery->execute([
            'prefix_length' => $prefix,
            'prefix_for_length' => $prefix,
            'prefix_for_value' => $prefix,
        ]);
        $number = max($nextValue, (int) $existingQuery->fetchColumn() + 1);
        $database->prepare('UPDATE document_sequences SET next_value = :next WHERE sequence_key = :key')
            ->execute(['next' => $number + 1, 'key' => $sequenceKey]);
        return $prefix . str_pad((string) $number, 3, '0', STR_PAD_LEFT);
    }
}
