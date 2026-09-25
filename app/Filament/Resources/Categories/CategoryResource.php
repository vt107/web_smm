<?php

namespace App\Filament\Resources\Categories;

use App\Enums\ActiveStatus;
use App\Enums\Platform;
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
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::CATALOG;

    protected static ?string $modelLabel = 'danh mục';

    protected static ?string $pluralModelLabel = 'Danh mục';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('platform')->label('Nền tảng')->options(Platform::class)->required(),
            TextInput::make('name')->label('Tên danh mục')->required()->maxLength(191),
            TextInput::make('sort')->label('Thứ tự')->numeric()->default(0),
            Select::make('status')->label('Trạng thái')->options(ActiveStatus::class)->required()->default(ActiveStatus::Active),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                TextColumn::make('platform')->label('Nền tảng')->badge(),
                TextColumn::make('name')->label('Tên danh mục')->searchable(),
                TextColumn::make('services_count')->label('Số dịch vụ')->counts('services'),
                TextColumn::make('status')->label('Trạng thái')->badge(),
            ])
            ->filters([
                SelectFilter::make('platform')->label('Nền tảng')->options(Platform::class),
                SelectFilter::make('status')->label('Trạng thái')->options(ActiveStatus::class),
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
