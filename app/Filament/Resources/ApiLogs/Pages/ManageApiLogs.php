<?php

namespace App\Filament\Resources\ApiLogs\Pages;

use App\Filament\Resources\ApiLogs\ApiLogResource;
use Filament\Resources\Pages\ManageRecords;

class ManageApiLogs extends ManageRecords
{
    protected static string $resource = ApiLogResource::class;
}
