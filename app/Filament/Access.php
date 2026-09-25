<?php

namespace App\Filament;

/** Kiểm tra quyền trong trang admin: Admin toàn quyền, Support staff chỉ xem đơn / trả lời ticket. */
final class Access
{
    public static function isAdmin(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }
}
