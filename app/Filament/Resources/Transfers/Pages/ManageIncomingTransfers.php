<?php

namespace App\Filament\Resources\Transfers\Pages;

use App\Filament\Resources\Transfers\IncomingTransferResource;
use Filament\Resources\Pages\ManageRecords;

class ManageIncomingTransfers extends ManageRecords
{
    protected static string $resource = IncomingTransferResource::class;
}
