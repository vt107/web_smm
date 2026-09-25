<?php

namespace App\Filament\Resources\Refills;

use App\Enums\RefillStatus;
use App\Filament\NavGroup;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Refills\Pages\ManageOrderRefills;
use App\Models\OrderRefill;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class OrderRefillResource extends Resource
{
    protected static ?string $model = OrderRefill::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPathRoundedSquare;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::ORDERS;

    protected static ?string $slug = 'refills';

    protected static ?string $modelLabel = 'refill';

    protected static ?string $pluralModelLabel = 'Refill';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['order.service', 'user']))
            ->columns([
                TextColumn::make('id')->label('ID'),
                TextColumn::make('order_id')->label('Order')->prefix('#')
                    ->url(fn (OrderRefill $r) => OrderResource::getUrl('view', ['record' => $r->order_id]))
                    ->description(fn (OrderRefill $r) => str($r->order->service->name)->limit(40)),
                TextColumn::make('user.username')->label('Customer')->searchable(),
                TextColumn::make('provider_refill_id')->label('Refill ID provider')->placeholder('—'),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('provider_response')->label('Response')->limit(50)->placeholder('—'),
                TextColumn::make('created_at')->label('Requested at')->dateTime('d/m/Y H:i'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(RefillStatus::class),
            ])
            ->recordActions([
                Action::make('setStatus')
                    ->label('Change status')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->fillForm(fn (OrderRefill $r) => ['status' => $r->status])
                    ->schema([Select::make('status')->label('Status')->options(RefillStatus::class)->required()])
                    ->action(fn (OrderRefill $r, array $data) => $r->update(['status' => $data['status']])),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ManageOrderRefills::route('/')];
    }
}
