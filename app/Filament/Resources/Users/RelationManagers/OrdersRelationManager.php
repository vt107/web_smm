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

    protected static ?string $title = 'Đơn hàng';

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
                TextColumn::make('service.name')->label('Dịch vụ')->limit(40),
                TextColumn::make('quantity')->label('Số lượng')->numeric(),
                TextColumn::make('charge')->label('Tiền')->formatStateUsing(fn ($state) => Fmt::usd($state)),
                TextColumn::make('status')->label('Trạng thái')->badge(),
                TextColumn::make('created_at')->label('Ngày đặt')->dateTime('d/m/Y H:i'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Trạng thái')->options(OrderStatus::class),
            ]);
    }
}
