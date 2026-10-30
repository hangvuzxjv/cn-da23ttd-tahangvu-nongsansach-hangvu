<?php

declare(strict_types=1);

final class VnpaySignature
{
    public static function signatureData(array $parameters): string
    {
        $signedParameters = [];
        foreach ($parameters as $key => $value) {
            if (!is_string($key)
                || !str_starts_with($key, 'vnp_')
                || in_array($key, ['vnp_SecureHash', 'vnp_SecureHashType'], true)) {
                continue;
            }
            if (!is_scalar($value) && $value !== null) {
                throw new InvalidArgumentException('Tham số ký VNPay không hợp lệ.');
            }
            if ($value === null || (string) $value === '') {
                continue;
            }
            $signedParameters[$key] = (string) $value;
        }

        ksort($signedParameters, SORT_STRING);
        $pairs = [];
        foreach ($signedParameters as $key => $value) {
            $pairs[] = $key . '=' . urlencode($value);
        }
        return implode('&', $pairs);
    }

    public static function sign(array $parameters, string $hashSecret): string
    {
        if ($hashSecret === '') {
            throw new InvalidArgumentException('Hash secret VNPay không được để trống.');
        }
        return hash_hmac('sha512', self::signatureData($parameters), $hashSecret);
    }

    public static function verify(array $parameters, string $hashSecret, string $signature): bool
    {
        if ($hashSecret === '' || preg_match('/\A[a-f0-9]{128}\z/i', $signature) !== 1) {
            return false;
        }
        try {
            return hash_equals(self::sign($parameters, $hashSecret), $signature);
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    public static function amountVnd(mixed $vnpAmount): ?int
    {
        if (!is_string($vnpAmount) && !is_int($vnpAmount)) {
            return null;
        }
        $amount = (string) $vnpAmount;
        if (preg_match('/\A\d+\z/', $amount) !== 1 || strlen($amount) > 15) {
            return null;
        }
        $minorUnits = (int) $amount;
        return $minorUnits % 100 === 0 ? intdiv($minorUnits, 100) : null;
    }

    public static function paymentMismatchReason(
        array $parameters,
        string $expectedReference,
        int $expectedAmountVnd,
        string $expectedOrderInfo
    ): ?string {
        if ((string) ($parameters['vnp_TxnRef'] ?? '') !== $expectedReference) {
            return 'transaction_reference_mismatch';
        }
        if (self::amountVnd($parameters['vnp_Amount'] ?? null) !== $expectedAmountVnd) {
            return 'amount_mismatch';
        }
        if (array_key_exists('vnp_CurrCode', $parameters)
            && (string) $parameters['vnp_CurrCode'] !== 'VND') {
            return 'currency_mismatch';
        }
        if (array_key_exists('vnp_OrderInfo', $parameters)
            && (string) $parameters['vnp_OrderInfo'] !== $expectedOrderInfo) {
            return 'order_information_mismatch';
        }
        return null;
    }
}
