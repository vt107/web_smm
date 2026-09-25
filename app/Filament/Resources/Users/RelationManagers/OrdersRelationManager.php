<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Support\Fmt;
use App\Models\Order;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $title = 'Orders';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->recordUrl(fn (Order $record) => OrderResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('id')->label('ID'),
                TextColumn::make('service.name')->label('Services')->limit(40),
                TextColumn::make('quantity')->label('Quantity')->numeric(),
                TextColumn::make('charge')->label('Charge')->formatStateUsing(fn ($state) => Fmt::usd($state)),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('created_at')->label('Created')->dateTime('d/m/Y H:i'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(OrderStatus::class),
            ]);
    }
}
