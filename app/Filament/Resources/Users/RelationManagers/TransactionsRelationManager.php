<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Enums\TransactionType;
use App\Filament\Support\Fmt;
use App\Support\Money;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $title = 'Biến động số dư';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Thời gian')->dateTime('d/m/Y H:i'),
                TextColumn::make('type')->label('Loại')->badge(),
                TextColumn::make('amount')->label('Số tiền')
                    ->formatStateUsing(fn ($state) => (Money::cmp($state, 0) > 0 ? '+' : '').Fmt::usd($state))
                    ->color(fn ($state) => Money::cmp($state, 0) > 0 ? 'success' : 'danger'),
                TextColumn::make('balance_after')->label('Số dư sau')->formatStateUsing(fn ($state) => Fmt::usd($state)),
                TextColumn::make('description')->label('Nội dung')->limit(60),
                TextColumn::make('creator.username')->label('Admin')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('type')->label('Loại')->options(TransactionType::class),
            ]);
    }
}
