<?php

namespace App\Enums;

enum Fulfillment: string
{
    /** Gửi đơn sang provider qua API. */
    case Api = 'api';

    /** Admin xử lý tay và tự cập nhật trạng thái. */
    case Manual = 'manual';
}
