<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [UserResource::adjustBalanceAction(), UserResource::resetTwoFactorAction()];
    }

    /** role / status / level_id cố ý không nằm trong $fillable của User nên gán bằng forceFill. */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return tap($record->forceFill($data))->save();
    }
}
