<?php

namespace App\Filament\Resources\Levels;

use App\Filament\NavGroup;
use App\Filament\Resources\Levels\Pages\ManageLevels;
use App\Filament\Support\Fmt;
use App\Models\Level;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class LevelResource extends Resource
{
    protected static ?string $model = Level::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::CUSTOMERS;

    protected static ?string $modelLabel = 'cấp bậc';

    protected static ?string $pluralModelLabel = 'Cấp bậc';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Tên cấp')->required()->maxLength(50),
            ColorPicker::make('color')->label('Màu badge'),
            TextInput::make('min_spent')->label('Tổng chi tiêu tối thiểu (USD)')->numeric()->minValue(0)->required()->default(0),
            TextInput::make('discount_percent')->label('Giảm giá (%)')->numeric()->minValue(0)->maxValue(100)->required()->default(0),
            Toggle::make('auto_upgrade')->label('Tự lên cấp khi đủ chi tiêu')->default(true)
                ->helperText('Tắt nếu cấp này chỉ admin gán tay (vd đại lý đặc biệt).'),
            Toggle::make('is_default')->label('Cấp mặc định cho user mới'),
            TextInput::make('sort')->label('Thứ tự')->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('min_spent')
            ->columns([
                ColorColumn::make('color')->label(''),
                TextColumn::make('name')->label('Tên cấp')->weight('bold'),
                TextColumn::make('min_spent')->label('Chi tiêu tối thiểu')->formatStateUsing(fn ($state) => Fmt::usd($state))->sortable(),
                TextColumn::make('discount_percent')->label('Giảm giá')->suffix('%')->sortable(),
                IconColumn::make('auto_upgrade')->label('Tự lên cấp')->boolean(),
                IconColumn::make('is_default')->label('Mặc định')->boolean(),
                TextColumn::make('users_count')->label('Số user')->counts('users'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->hidden(fn (Level $record) => $record->is_default || $record->users()->exists()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageLevels::route('/')];
    }
}
