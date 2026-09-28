<?php

namespace App\Enums;

/** Kết quả 1 lần gửi đơn sang provider. */
enum SendResult
{
    /** Provider đã nhận đơn. */
    case Sent;

    /** Provider từ chối rõ ràng ({"error": ...}). */
    case Rejected;

    /** Lỗi mạng / timeout / response lạ – không chắc provider đã nhận hay chưa. */
    case Failed;

    /** Không gửi: đơn đang được gửi ở nơi khác, đã gửi, đã huỷ… */
    case Skipped;
}
