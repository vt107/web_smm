<?php

namespace App\Filament\Resources\Refills\Pages;

use App\Filament\Resources\Refills\OrderRefillResource;
use Filament\Resources\Pages\ManageRecords;

class ManageOrderRefills extends ManageRecords
{
    protected static string $resource = OrderRefillResource::class;
}
