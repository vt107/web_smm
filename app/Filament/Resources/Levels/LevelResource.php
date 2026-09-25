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

    protected static ?string $modelLabel = 'level';

    protected static ?string $pluralModelLabel = 'Levels';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Level name')->required()->maxLength(50),
            ColorPicker::make('color')->label('Badge color'),
            TextInput::make('min_spent')->label('Min. total spending (USD)')->numeric()->minValue(0)->required()->default(0),
            TextInput::make('discount_percent')->label('Discount (%)')->numeric()->minValue(0)->maxValue(100)->required()->default(0),
            Toggle::make('auto_upgrade')->label('Auto upgrade when spending is reached')->default(true)
                ->helperText('Turn off if this level is assigned manually by admins only (e.g. special resellers).'),
            Toggle::make('is_default')->label('Default level for new users'),
            TextInput::make('sort')->label('Sort order')->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('min_spent')
            ->columns([
                ColorColumn::make('color')->label(''),
                TextColumn::make('name')->label('Level name')->weight('bold'),
                TextColumn::make('min_spent')->label('Min. spending')->formatStateUsing(fn ($state) => Fmt::usd($state))->sortable(),
                TextColumn::make('discount_percent')->label('Discount')->suffix('%')->sortable(),
                IconColumn::make('auto_upgrade')->label('Auto upgrade')->boolean(),
                IconColumn::make('is_default')->label('Default')->boolean(),
                TextColumn::make('users_count')->label('Users')->counts('users'),
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
