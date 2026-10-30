<?php

declare(strict_types=1);

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

final class VietQr
{
    public static function svg(string $bankBin, string $accountNumber, string $accountName, string $orderNo, int $amount): string
    {
        $payload = self::payload($bankBin, $accountNumber, $accountName, $orderNo, $amount);
        return (new Writer(new ImageRenderer(new RendererStyle(300), new SvgImageBackEnd())))->writeString($payload);
    }

    public static function payload(string $bankBin, string $accountNumber, string $accountName, string $orderNo, int $amount): string
    {
        foreach ([$bankBin, $accountNumber, $accountName, $orderNo] as $value) {
            if ($value === '' || strlen($value) > 99) {
                throw new InvalidArgumentException('Cấu hình nhận VietQR hoặc mã đơn không hợp lệ.');
            }
        }
        if ($amount < 1) {
            throw new InvalidArgumentException('Số tiền VietQR không hợp lệ.');
        }

        $merchantAccount = self::field('00', 'A000000727')
            . self::field('01', self::field('00', $bankBin) . self::field('01', $accountNumber))
            . self::field('02', 'QRIBFTTA');
        $payload = self::field('00', '01')
            . self::field('01', '12')
            . self::field('38', $merchantAccount)
            . self::field('52', '0000')
            . self::field('53', '704')
            . self::field('54', (string) $amount)
            . self::field('58', 'VN')
            . self::field('59', self::asciiName($accountName))
            . self::field('60', 'Vietnam')
            . self::field('62', self::field('08', $orderNo))
            . '6304';
        return $payload . self::crc16($payload);
    }

    private static function field(string $tag, string $value): string
    {
        $length = strlen($value);
        if ($length > 99) {
            throw new InvalidArgumentException('Một trường trong dữ liệu VietQR quá dài.');
        }
        return $tag . str_pad((string) $length, 2, '0', STR_PAD_LEFT) . $value;
    }

    private static function crc16(string $payload): string
    {
        $crc = 0xFFFF;
        foreach (unpack('C*', $payload) as $byte) {
            $crc ^= $byte << 8;
            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 0x8000) !== 0 ? (($crc << 1) ^ 0x1021) & 0xFFFF : ($crc << 1) & 0xFFFF;
            }
        }
        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }

    private static function asciiName(string $name): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        $ascii = strtoupper(trim((string) $ascii));
        if ($ascii === '' || preg_match('/\A[A-Z0-9 .&-]+\z/', $ascii) !== 1) {
            throw new InvalidArgumentException('Tên chủ tài khoản VietQR không hợp lệ.');
        }
        return substr($ascii, 0, 25);
    }
}
