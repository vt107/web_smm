<?php

namespace App\Filament\Concerns;

use App\Filament\Access;
use BackedEnum;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Dùng cho MỌI resource trong trang admin: Admin toàn quyền; nhân viên Support chỉ có các quyền
 * khai báo trong supportAbilities() (mặc định không có gì → resource bị ẩn và trả 403).
 * Quyền theo tên policy của Filament: viewAny, view, create, update, delete, deleteAny, restore…
 */
trait StaffPermissions
{
    /** @return list<string> */
    protected static function supportAbilities(): array
    {
        return [];
    }

    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        $name = match (true) {
            $action instanceof BackedEnum => (string) $action->value,
            $action instanceof UnitEnum => $action->name,
            default => $action,
        };

        if (! Access::isAdmin() && ! in_array($name, static::supportAbilities(), true)) {
            return Response::deny();
        }

        return parent::getAuthorizationResponse($action, $record);
    }
}
