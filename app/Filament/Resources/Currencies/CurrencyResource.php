<?php

namespace App\Filament\Resources\Currencies;

use App\Enums\ActiveStatus;
use App\Filament\NavGroup;
use App\Filament\Resources\Currencies\Pages\ManageCurrencies;
use App\Models\Currency;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class CurrencyResource extends Resource
{
    protected static ?string $model = Currency::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::SYSTEM;

    protected static ?string $modelLabel = 'currency';

    protected static ?string $pluralModelLabel = 'Display currencies';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('code')->label('Code')->required()->length(3)->alpha()
                ->unique(ignoreRecord: true)->disabledOn('edit'),
            TextInput::make('name')->label('Name')->required()->maxLength(50),
            TextInput::make('symbol')->label('Symbol')->required()->maxLength(5),
            Select::make('symbol_position')->label('Symbol position')->required()->default('before')
                ->options(['before' => 'Before ($1.20)', 'after' => 'Sau (30.000 ₫)']),
            TextInput::make('rate')->label('Exchange rate (per 1 USD)')->numeric()->minValue(0.000001)->required(),
            TextInput::make('decimals')->label('Decimals')->integer()->minValue(0)->maxValue(6)->required()->default(2),
            Toggle::make('auto_update')->label('Update exchange rate daily'),
            Select::make('status')->label('Status')->options(ActiveStatus::class)->required()->default(ActiveStatus::Active),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Code')->weight('bold'),
                TextColumn::make('name')->label('Name'),
                TextColumn::make('symbol')->label('Symbol'),
                TextColumn::make('rate')->label('Rate per 1 USD')->numeric(decimalPlaces: 4),
                IconColumn::make('auto_update')->label('Auto update')->boolean(),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('updated_at')->label('Updated')->since(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCurrencies::route('/')];
    }
}
