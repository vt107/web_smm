<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Lỗi nghiệp vụ có thể hiển thị thẳng cho khách.
 * getMessage() luôn là tiếng Anh (API chuẩn PerfectPanel), localized() dịch theo locale hiện tại (web).
 *
 * @param  array<string, string|int>  $replace  tham số thay cho :placeholder trong message
 */
class SmmException extends RuntimeException
{
    public function __construct(private readonly string $template, private readonly array $replace = [])
    {
        parent::__construct((string) trans($template, $replace, 'en'));
    }

    public function localized(): string
    {
        return (string) __($this->template, $this->replace);
    }
}
