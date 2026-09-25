<?php

namespace App\Exceptions;

use RuntimeException;

/** Lỗi nghiệp vụ có thể hiển thị thẳng cho khách (web hoặc API). */
class SmmException extends RuntimeException {}
