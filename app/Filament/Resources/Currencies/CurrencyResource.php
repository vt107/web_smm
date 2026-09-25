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

    protected static ?string $modelLabel = 'tiền tệ';

    protected static ?string $pluralModelLabel = 'Tiền tệ hiển thị';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('code')->label('Mã')->required()->length(3)->alpha()
                ->unique(ignoreRecord: true)->disabledOn('edit'),
            TextInput::make('name')->label('Tên')->required()->maxLength(50),
            TextInput::make('symbol')->label('Ký hiệu')->required()->maxLength(5),
            Select::make('symbol_position')->label('Vị trí ký hiệu')->required()->default('before')
                ->options(['before' => 'Trước ($1.20)', 'after' => 'Sau (30.000 ₫)']),
            TextInput::make('rate')->label('Tỉ giá (= 1 USD)')->numeric()->minValue(0.000001)->required(),
            TextInput::make('decimals')->label('Số chữ số lẻ')->integer()->minValue(0)->maxValue(6)->required()->default(2),
            Toggle::make('auto_update')->label('Tự cập nhật tỉ giá hằng ngày'),
            Select::make('status')->label('Trạng thái')->options(ActiveStatus::class)->required()->default(ActiveStatus::Active),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Mã')->weight('bold'),
                TextColumn::make('name')->label('Tên'),
                TextColumn::make('symbol')->label('Ký hiệu'),
                TextColumn::make('rate')->label('Tỉ giá / 1 USD')->numeric(decimalPlaces: 4),
                IconColumn::make('auto_update')->label('Tự cập nhật')->boolean(),
                TextColumn::make('status')->label('Trạng thái')->badge(),
                TextColumn::make('updated_at')->label('Cập nhật')->since(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCurrencies::route('/')];
    }
}
