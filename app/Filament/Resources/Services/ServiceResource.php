<?php

namespace App\Filament\Resources\Services;

use App\Enums\ActiveStatus;
use App\Enums\Fulfillment;
use App\Enums\ServiceType;
use App\Filament\NavGroup;
use App\Filament\Resources\Services\Pages\CreateService;
use App\Filament\Resources\Services\Pages\EditService;
use App\Filament\Resources\Services\Pages\ListServices;
use App\Filament\Support\Fmt;
use App\Models\Service;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::CATALOG;

    protected static ?string $modelLabel = 'service';

    protected static ?string $pluralModelLabel = 'Services';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Details')->columnSpan(2)->columns(2)->schema([
                TextInput::make('name')->label('Service name')->required()->maxLength(255)->columnSpanFull(),
                Select::make('category_id')->label('Categories')->relationship('category', 'name')->searchable()->preload()->required(),
                Select::make('type')->label('Type')->options(ServiceType::class)->required()->default(ServiceType::Default),
                Textarea::make('description')->label('Description (shown to customers)')->rows(5)->columnSpanFull(),
            ]),
            Section::make('Status')->columnSpan(1)->schema([
                Select::make('status')->label('Status')->options(ActiveStatus::class)->required()->default(ActiveStatus::Active),
                TextInput::make('sort')->label('Sort order')->numeric()->default(0),
            ]),
            Section::make('Fulfillment source')->columnSpan(2)->columns(2)->schema([
                Select::make('fulfillment')->label('Fulfillment')->options(Fulfillment::class)
                    ->required()->default(Fulfillment::Api)->live(),
                Select::make('provider_id')->label('Provider')->relationship('provider', 'name')
                    ->visible(fn (Get $get) => self::isApi($get))->required(fn (Get $get) => self::isApi($get)),
                TextInput::make('provider_service_id')->label('Provider service ID')->maxLength(50)
                    ->visible(fn (Get $get) => self::isApi($get))->required(fn (Get $get) => self::isApi($get)),
            ]),
            Section::make('Options')->columnSpan(1)->schema([
                Toggle::make('refill')->label('Allow refill')->live(),
                TextInput::make('refill_days')->label('Refill guarantee (days)')->integer()->minValue(1)
                    ->helperText('Leave empty = no limit')->visible(fn (Get $get) => (bool) $get('refill')),
                Toggle::make('cancel')->label('Allow cancel'),
                Toggle::make('dripfeed')->label('Drip-feed supported'),
            ]),
            Section::make('Pricing & limits')->columnSpan(2)->columns(2)->schema([
                TextInput::make('rate')->label('Selling rate (USD)')->numeric()->minValue(0)->required()->prefix('$')
                    ->helperText('Per 1000 (per package for package services). Customers get extra level discounts.'),
                TextInput::make('provider_rate')->label('Cost rate (USD)')->numeric()->minValue(0)->prefix('$')
                    ->helperText('Synced from the provider every hour.'),
                TextInput::make('min')->label('Min. quantity')->integer()->minValue(1)->required()->default(10),
                TextInput::make('max')->label('Max. quantity')->integer()->minValue(1)->required()->default(10000)->gte('min'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('name')->label('Services')->searchable()->limit(60)->wrap()
                    ->description(fn (Service $r) => $r->category?->name),
                TextColumn::make('provider.name')->label('Source')->placeholder('Manual')
                    ->description(fn (Service $r) => $r->provider_service_id ? "#{$r->provider_service_id}" : null),
                TextColumn::make('type')->label('Type')->badge()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('rate')->label('Rate')->formatStateUsing(fn ($state) => Fmt::usd($state))->sortable(),
                TextColumn::make('provider_rate')->label('Cost')->formatStateUsing(fn ($state) => Fmt::usd($state)),
                TextColumn::make('margin')->label('Margin')
                    ->state(fn (Service $r) => self::marginPercent($r))
                    ->formatStateUsing(fn ($state) => $state === null ? '—' : "{$state}%")
                    ->color(fn ($state) => $state !== null && $state < 10 ? 'danger' : 'success'),
                TextColumn::make('min')->label('Min / Max')->formatStateUsing(fn (Service $r) => number_format($r->min).' – '.number_format($r->max)),
                TextColumn::make('status')->label('Status')->badge(),
            ])
            ->filters([
                SelectFilter::make('category_id')->label('Categories')->relationship('category', 'name')->searchable()->preload(),
                SelectFilter::make('provider_id')->label('Provider')->relationship('provider', 'name'),
                SelectFilter::make('status')->label('Status')->options(ActiveStatus::class),
                SelectFilter::make('fulfillment')->label('Fulfillment')->options(Fulfillment::class),
                SelectFilter::make('type')->label('Type')->options(ServiceType::class),
                TrashedFilter::make(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('enable')->label('Enable')->icon(Heroicon::OutlinedCheckCircle)
                        ->action(fn (Collection $records) => $records->each->update(['status' => ActiveStatus::Active])),
                    BulkAction::make('disable')->label('Disabled')->icon(Heroicon::OutlinedPauseCircle)
                        ->action(fn (Collection $records) => $records->each->update(['status' => ActiveStatus::Disabled])),
                    BulkAction::make('markup')->label('Set rate by markup %')->icon(Heroicon::OutlinedCalculator)
                        ->schema([TextInput::make('percent')->label('Markup % over cost')->numeric()->minValue(0)->required()])
                        ->action(function (Collection $records, array $data) {
                            $factor = Money::add(1, Money::div($data['percent'], 100, 12), 12);
                            $records->filter(fn (Service $s) => $s->provider_rate !== null)
                                ->each(fn (Service $s) => $s->update(['rate' => Money::mul($s->provider_rate, $factor)]));
                        }),
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['category', 'provider'])->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServices::route('/'),
            'create' => CreateService::route('/create'),
            'edit' => EditService::route('/{record}/edit'),
        ];
    }

    private static function isApi(Get $get): bool
    {
        $value = $get('fulfillment');

        return ($value instanceof Fulfillment ? $value : Fulfillment::tryFrom((string) $value)) === Fulfillment::Api;
    }

    private static function marginPercent(Service $service): ?int
    {
        if (! $service->provider_rate || Money::cmp($service->provider_rate, 0) === 0) {
            return null;
        }

        return (int) round(((float) $service->rate - (float) $service->provider_rate) / (float) $service->provider_rate * 100);
    }
}
