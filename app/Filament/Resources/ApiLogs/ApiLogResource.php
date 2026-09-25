<?php

namespace App\Filament\Resources\ApiLogs;

use App\Filament\NavGroup;
use App\Filament\Resources\ApiLogs\Pages\ManageApiLogs;
use App\Models\ApiLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ApiLogResource extends Resource
{
    protected static ?string $model = ApiLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCommandLine;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::SYSTEM;

    protected static ?string $modelLabel = 'API log';

    protected static ?string $pluralModelLabel = 'API log (khách)';

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
            ->columns([
                TextColumn::make('created_at')->label('Thời gian')->dateTime('d/m/Y H:i:s'),
                TextColumn::make('user.username')->label('Khách')->placeholder('Key sai')->searchable(),
                TextColumn::make('action')->label('Action')->badge()->color('gray'),
                TextColumn::make('ip')->label('IP')->searchable(),
                TextColumn::make('error')->label('Lỗi')->color('danger')->placeholder('—')->limit(50),
                TextColumn::make('request')->label('Request')->limit(80)->toggleable(isToggledHiddenByDefault: true)
                    ->formatStateUsing(fn (ApiLog $r) => json_encode($r->request, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            ])
            ->filters([
                SelectFilter::make('action')->label('Action')
                    ->options(array_combine($a = ['services', 'add', 'status', 'refill', 'refill_status', 'cancel', 'balance'], $a)),
                Filter::make('errors')->label('Chỉ request lỗi')->query(fn (Builder $query) => $query->whereNotNull('error')),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ManageApiLogs::route('/')];
    }
}
