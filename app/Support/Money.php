<?php

namespace App\Support;

/**
 * Tính toán tiền bằng bcmath để không sai số dấu phẩy động.
 * Mọi giá trị trả về là chuỗi thập phân với SCALE chữ số lẻ (khớp cột DECIMAL(18,6)).
 */
final class Money
{
    public const SCALE = 6;

    /** Độ chính xác trung gian trước khi làm tròn. */
    private const WORK_SCALE = 12;

    public static function add(string|int|float $a, string|int|float $b, int $scale = self::SCALE): string
    {
        return bcadd(self::n($a), self::n($b), $scale);
    }

    public static function sub(string|int|float $a, string|int|float $b, int $scale = self::SCALE): string
    {
        return bcsub(self::n($a), self::n($b), $scale);
    }

    /** Nhân rồi làm tròn half-up. */
    public static function mul(string|int|float $a, string|int|float $b, int $scale = self::SCALE): string
    {
        return self::round(bcmul(self::n($a), self::n($b), self::WORK_SCALE), $scale);
    }

    /** Chia rồi làm tròn half-up. */
    public static function div(string|int|float $a, string|int|float $b, int $scale = self::SCALE): string
    {
        return self::round(bcdiv(self::n($a), self::n($b), self::WORK_SCALE), $scale);
    }

    public static function cmp(string|int|float $a, string|int|float $b, int $scale = self::SCALE): int
    {
        return bccomp(self::n($a), self::n($b), $scale);
    }

    public static function isPositive(string|int|float $a): bool
    {
        return self::cmp($a, 0) > 0;
    }

    public static function min(string|int|float $a, string|int|float $b): string
    {
        return self::cmp($a, $b) <= 0 ? self::add($a, 0) : self::add($b, 0);
    }

    /** Giá trị tuyệt đối. */
    public static function abs(string|int|float $a, int $scale = self::SCALE): string
    {
        $v = self::add($a, 0, $scale);

        return str_starts_with($v, '-') ? substr($v, 1) : $v;
    }

    /** Hiển thị gọn: bỏ số 0 thừa nhưng giữ tối thiểu 2 chữ số lẻ ("0.900000" → "0.90", "0.001230" → "0.00123"). */
    public static function format(string|int|float $value): string
    {
        $value = self::add($value, 0);
        [$int, $dec] = explode('.', $value) + [1 => ''];
        $dec = rtrim($dec, '0');

        return $int.'.'.str_pad($dec, 2, '0');
    }

    /** Làm tròn half-up (bcmath mặc định cắt bỏ). */
    public static function round(string|int|float $value, int $scale = self::SCALE): string
    {
        $value = self::n($value);
        $offset = '0.'.str_repeat('0', $scale).'5';

        return str_starts_with($value, '-')
            ? bcsub($value, $offset, $scale)
            : bcadd($value, $offset, $scale);
    }

    /** Làm tròn lên (dùng cho số tiền khách phải trả, vd VND). Chỉ áp dụng cho số dương. */
    public static function ceil(string|int|float $value, int $scale = 0): string
    {
        $value = self::n($value);
        $truncated = bcadd($value, '0', $scale);

        if (bccomp($value, $truncated, self::WORK_SCALE) > 0) {
            $step = $scale === 0 ? '1' : '0.'.str_repeat('0', $scale - 1).'1';
            $truncated = bcadd($truncated, $step, $scale);
        }

        return $truncated;
    }

    /** Chuẩn hoá đầu vào về chuỗi số thập phân mà bcmath chấp nhận. */
    private static function n(string|int|float $value): string
    {
        if (is_float($value)) {
            return rtrim(rtrim(number_format($value, self::WORK_SCALE, '.', ''), '0'), '.') ?: '0';
        }

        $value = trim((string) $value);

        return $value === '' ? '0' : $value;
    }
}
