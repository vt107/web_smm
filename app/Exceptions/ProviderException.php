<?php

namespace App\Exceptions;

use RuntimeException;

/** Lỗi khi gọi API provider (mạng, response sai định dạng, hoặc provider trả {"error": ...}). */
class ProviderException extends RuntimeException {}
