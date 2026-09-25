<?php

namespace App\Filament\Resources\Categories;

use App\Enums\ActiveStatus;
use App\Enums\Platform;
use App\Filament\Concerns\StaffPermissions;
use App\Filament\NavGroup;
use App\Filament\Resources\Categories\Pages\ManageCategories;
use App\Models\Category;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class CategoryResource extends Resource
{
    use StaffPermissions;

    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::CATALOG;

    protected static ?string $modelLabel = 'category';

    protected static ?string $pluralModelLabel = 'Categories';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('platform')->label('Platform')->options(Platform::class)->required(),
            TextInput::make('name')->label('Category name')->required()->maxLength(191),
            TextInput::make('sort')->label('Sort order')->numeric()->default(0),
            Select::make('status')->label('Status')->options(ActiveStatus::class)->required()->default(ActiveStatus::Active),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                TextColumn::make('platform')->label('Platform')->badge(),
                TextColumn::make('name')->label('Category name')->searchable(),
                TextColumn::make('services_count')->label('Services')->counts('services'),
                TextColumn::make('status')->label('Status')->badge(),
            ])
            ->filters([
                SelectFilter::make('platform')->label('Platform')->options(Platform::class),
                SelectFilter::make('status')->label('Status')->options(ActiveStatus::class),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->hidden(fn (Category $record) => $record->services()->withTrashed()->exists()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCategories::route('/')];
    }
}
