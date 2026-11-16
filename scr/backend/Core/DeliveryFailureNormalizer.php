<?php

declare(strict_types=1);

final class DeliveryFailureNormalizer
{
    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if (!is_string($digits)) {
            throw new InvalidArgumentException('Số điện thoại không hợp lệ.');
        }

        if (str_starts_with($digits, '0084') && strlen($digits) > 12) {
            $digits = substr($digits, 4);
        } elseif (str_starts_with($digits, '84') && strlen($digits) > 10) {
            $digits = substr($digits, 2);
        }

        if ($digits !== '' && !str_starts_with($digits, '0')) {
            $digits = '0' . $digits;
        }

        return $digits;
    }

    public static function addressKey(string $address, string $ward = '', string $province = ''): string
    {
        $combined = implode(' ', array_filter(
            [$address, $ward, $province],
            static fn (string $part): bool => trim($part) !== ''
        ));
        $accentGroups = [
            'a' => 'àáảãạăằắẳẵặâầấẩẫậ',
            'e' => 'èéẻẽẹêềếểễệ',
            'i' => 'ìíỉĩị',
            'o' => 'òóỏõọôồốổỗộơờớởỡợ',
            'u' => 'ùúủũụưừứửữự',
            'y' => 'ỳýỷỹỵ',
        ];
        $transliteration = ['đ' => 'd', 'Đ' => 'D'];
        foreach ($accentGroups as $plain => $accented) {
            $lowerCharacters = preg_split('//u', $accented, -1, PREG_SPLIT_NO_EMPTY);
            $upperCharacters = preg_split('//u', mb_strtoupper($accented, 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY);
            if (!is_array($lowerCharacters) || !is_array($upperCharacters)) {
                throw new RuntimeException('Không thể chuẩn hóa địa chỉ giao hàng.');
            }
            foreach ($lowerCharacters as $character) {
                $transliteration[$character] = $plain;
            }
            foreach ($upperCharacters as $character) {
                $transliteration[$character] = strtoupper($plain);
            }
        }
        $transliterated = strtr($combined, $transliteration);
        $normalized = strtolower($transliterated);
        $normalized = preg_replace('/[^a-z0-9]+/', ' ', $normalized);
        if (!is_string($normalized)) {
            throw new RuntimeException('Không thể chuẩn hóa địa chỉ giao hàng.');
        }
        $normalized = preg_replace('/\s+/', ' ', trim($normalized));
        if (!is_string($normalized)) {
            throw new RuntimeException('Không thể chuẩn hóa địa chỉ giao hàng.');
        }

        return hash('sha256', $normalized);
    }
}
