<?php

declare(strict_types=1);

final class FaqService
{
    private PDO $database;

    private const CATEGORIES = [
        'Đặt hàng',
        'Thanh toán',
        'Giao hàng',
        'Đổi trả và hoàn tiền',
        'Truy xuất nguồn gốc',
        'Flash Sale',
        'Tài khoản',
    ];

    public function __construct(?PDO $database = null)
    {
        $this->database = $database ?? Database::connection();
    }

    public static function categories(): array
    {
        return self::CATEGORIES;
    }

    public function activeItems(): array
    {
        return $this->database->query(
            'SELECT id, category, question, answer, sort_order
             FROM faq_items WHERE is_active = 1 ORDER BY sort_order, id'
        )->fetchAll();
    }

    public function allItems(): array
    {
        return $this->database->query(
            'SELECT id, category, question, answer, sort_order, is_active, created_at, updated_at
             FROM faq_items ORDER BY sort_order, id'
        )->fetchAll();
    }

    public function item(int $id): array
    {
        $query = $this->database->prepare('SELECT * FROM faq_items WHERE id = :id LIMIT 1');
        $query->execute(['id' => $id]);
        $item = $query->fetch();
        if (!$item) {
            throw new HttpException(404, 'Không tìm thấy câu hỏi thường gặp.');
        }
        return $item;
    }

    public function create(array $input, int $actorId): int
    {
        $values = $this->validatedInput($input);
        $this->requireActor($actorId);
        $this->database->beginTransaction();
        try {
            $this->database->prepare(
                'INSERT INTO faq_items (category, question, answer, sort_order, is_active)
                 VALUES (:category, :question, :answer, :sort_order, :is_active)'
            )->execute($values);
            $id = (int) $this->database->lastInsertId();
            AuditLog::record($actorId, 'faq_item_created', 'faq_item', $id, 'category=' . $values['category'], $this->database);
            $this->database->commit();
            return $id;
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    public function update(int $id, array $input, int $actorId): void
    {
        $values = $this->validatedInput($input);
        $this->requireActor($actorId);
        $this->database->beginTransaction();
        try {
            $this->lockItem($id);
            $values['id'] = $id;
            $this->database->prepare(
                'UPDATE faq_items SET category = :category, question = :question, answer = :answer,
                    sort_order = :sort_order, is_active = :is_active WHERE id = :id'
            )->execute($values);
            AuditLog::record($actorId, 'faq_item_updated', 'faq_item', $id, 'category=' . $values['category'], $this->database);
            $this->database->commit();
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    public function toggle(int $id, int $actorId): bool
    {
        $this->requireActor($actorId);
        $this->database->beginTransaction();
        try {
            $item = $this->lockItem($id);
            $active = (int) $item['is_active'] === 0;
            $this->database->prepare('UPDATE faq_items SET is_active = :active WHERE id = :id')
                ->execute(['active' => $active ? 1 : 0, 'id' => $id]);
            AuditLog::record($actorId, 'faq_item_status_changed', 'faq_item', $id, $active ? 'active' : 'inactive', $this->database);
            $this->database->commit();
            return $active;
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    public function delete(int $id, int $actorId): void
    {
        $this->requireActor($actorId);
        $this->database->beginTransaction();
        try {
            $this->lockItem($id);
            $this->database->prepare('DELETE FROM faq_items WHERE id = :id')->execute(['id' => $id]);
            AuditLog::record($actorId, 'faq_item_deleted', 'faq_item', $id, null, $this->database);
            $this->database->commit();
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    private function validatedInput(array $input): array
    {
        $category = $input['category'] ?? null;
        $question = $input['question'] ?? null;
        $answer = $input['answer'] ?? null;
        $rawSortOrder = $input['sort_order'] ?? '0';
        $rawActive = $input['is_active'] ?? '0';
        if (!is_string($category) || !in_array($category, self::CATEGORIES, true)
            || !is_string($question) || !is_string($answer)
            || !is_scalar($rawSortOrder)
            || !in_array($rawActive, [true, false, 0, 1, '0', '1'], true)) {
            throw new InvalidArgumentException('Thông tin câu hỏi thường gặp không hợp lệ.');
        }
        $question = trim($question);
        $answer = trim($answer);
        $sortOrder = filter_var($rawSortOrder, FILTER_VALIDATE_INT);
        if (mb_strlen($question, 'UTF-8') < 5 || mb_strlen($question, 'UTF-8') > 255
            || mb_strlen($answer, 'UTF-8') < 2 || mb_strlen($answer, 'UTF-8') > 10000
            || $sortOrder === false || $sortOrder < 0 || $sortOrder > 1000000) {
            throw new InvalidArgumentException('Tiêu đề, nội dung hoặc thứ tự câu hỏi chưa hợp lệ.');
        }
        return [
            'category' => $category,
            'question' => $question,
            'answer' => $answer,
            'sort_order' => $sortOrder,
            'is_active' => in_array($rawActive, [true, 1, '1'], true) ? 1 : 0,
        ];
    }

    private function lockItem(int $id): array
    {
        $query = $this->database->prepare('SELECT id, is_active FROM faq_items WHERE id = :id LIMIT 1 FOR UPDATE');
        $query->execute(['id' => $id]);
        $item = $query->fetch();
        if (!$item) {
            throw new HttpException(404, 'Không tìm thấy câu hỏi thường gặp.');
        }
        return $item;
    }

    private function requireActor(int $actorId): void
    {
        if ($actorId < 1) {
            throw new InvalidArgumentException('Tài khoản quản trị không hợp lệ.');
        }
    }
}
