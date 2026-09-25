<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /** role / status / level_id cố ý không nằm trong $fillable của User nên gán bằng forceFill. */
    protected function handleRecordCreation(array $data): Model
    {
        return tap((new User)->forceFill($data))->save();
    }
}
