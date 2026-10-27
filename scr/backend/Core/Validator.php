<?php

declare(strict_types=1);

final class Validator
{
    public static function validate(array $data, array $rules): array
    {
        $errors = [];
        foreach ($rules as $field => $fieldRules) {
            foreach (explode('|', $fieldRules) as $rule) {
                if ($rule === 'required' && (!isset($data[$field]) || trim((string) $data[$field]) === '')) {
                    $errors[$field][] = 'Trường này là bắt buộc.';
                } elseif ($rule === 'email' && isset($data[$field]) && filter_var($data[$field], FILTER_VALIDATE_EMAIL) === false) {
                    $errors[$field][] = 'Địa chỉ email không hợp lệ.';
                }
            }
        }
        return $errors;
    }
}
