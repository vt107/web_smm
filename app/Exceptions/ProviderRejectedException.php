<?php

namespace App\Exceptions;

/** Provider trả lời rõ ràng {"error": ...} – chắc chắn không tạo đơn/thao tác bên đó. */
class ProviderRejectedException extends ProviderException {}
