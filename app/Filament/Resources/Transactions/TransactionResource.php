<?php

namespace App\Filament\Resources\Transactions;

use App\Enums\TransactionType;
use App\Filament\NavGroup;
use App\Filament\Resources\Transactions\Pages\ManageTransactions;
use App\Filament\Support\Fmt;
use App\Models\Transaction;
use App\Support\Money;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::FINANCE;

    protected static ?string $modelLabel = 'transaction';

    protected static ?string $pluralModelLabel = 'Balance ledger';

    protected static ?int $navigationSort = 4;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'creator']))
            ->columns([
                TextColumn::make('id')->label('ID'),
                TextColumn::make('user.username')->label('Customer')->searchable(),
                TextColumn::make('type')->label('Type')->badge(),
                TextColumn::make('amount')->label('Amount')
                    ->formatStateUsing(fn ($state) => (Money::cmp($state, 0) > 0 ? '+' : '').Fmt::usd($state))
                    ->color(fn ($state) => Money::cmp($state, 0) > 0 ? 'success' : 'danger'),
                TextColumn::make('balance_before')->label('Before')->formatStateUsing(fn ($state) => Fmt::usd($state)),
                TextColumn::make('balance_after')->label('Sau')->formatStateUsing(fn ($state) => Fmt::usd($state)),
                TextColumn::make('description')->label('Description')->limit(50)->searchable(),
                TextColumn::make('creator.username')->label('Admin')->placeholder('—')->toggleable(),
                TextColumn::make('created_at')->label('Time')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->label('Type')->options(TransactionType::class)->multiple(),
                Filter::make('created_at')->schema([
                    DatePicker::make('from')->label('From'),
                    DatePicker::make('until')->label('Until'),
                ])->query(fn (Builder $query, array $data) => $query
                    ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                    ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ManageTransactions::route('/')];
    }
}
