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

    protected static ?string $modelLabel = 'dịch vụ';

    protected static ?string $pluralModelLabel = 'Dịch vụ';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Thông tin')->columnSpan(2)->columns(2)->schema([
                TextInput::make('name')->label('Tên dịch vụ')->required()->maxLength(255)->columnSpanFull(),
                Select::make('category_id')->label('Danh mục')->relationship('category', 'name')->searchable()->preload()->required(),
                Select::make('type')->label('Loại')->options(ServiceType::class)->required()->default(ServiceType::Default),
                Textarea::make('description')->label('Mô tả hiển thị cho khách')->rows(5)->columnSpanFull(),
            ]),
            Section::make('Trạng thái')->columnSpan(1)->schema([
                Select::make('status')->label('Trạng thái')->options(ActiveStatus::class)->required()->default(ActiveStatus::Active),
                TextInput::make('sort')->label('Thứ tự')->numeric()->default(0),
            ]),
            Section::make('Nguồn xử lý')->columnSpan(2)->columns(2)->schema([
                Select::make('fulfillment')->label('Cách xử lý')->options(Fulfillment::class)
                    ->required()->default(Fulfillment::Api)->live(),
                Select::make('provider_id')->label('Provider')->relationship('provider', 'name')
                    ->visible(fn (Get $get) => self::isApi($get))->required(fn (Get $get) => self::isApi($get)),
                TextInput::make('provider_service_id')->label('Service ID bên provider')->maxLength(50)
                    ->visible(fn (Get $get) => self::isApi($get))->required(fn (Get $get) => self::isApi($get)),
            ]),
            Section::make('Tuỳ chọn')->columnSpan(1)->schema([
                Toggle::make('refill')->label('Cho phép refill')->live(),
                TextInput::make('refill_days')->label('Bảo hành refill (ngày)')->integer()->minValue(1)
                    ->helperText('Bỏ trống = không giới hạn')->visible(fn (Get $get) => (bool) $get('refill')),
                Toggle::make('cancel')->label('Cho phép huỷ'),
                Toggle::make('dripfeed')->label('Hỗ trợ drip-feed'),
            ]),
            Section::make('Giá & giới hạn')->columnSpan(2)->columns(2)->schema([
                TextInput::make('rate')->label('Giá bán (USD)')->numeric()->minValue(0)->required()->prefix('$')
                    ->helperText('Giá / 1000; loại package là giá / gói. Khách được giảm thêm theo cấp bậc.'),
                TextInput::make('provider_rate')->label('Giá vốn (USD)')->numeric()->minValue(0)->prefix('$')
                    ->helperText('Tự đồng bộ từ provider mỗi giờ.'),
                TextInput::make('min')->label('Số lượng tối thiểu')->integer()->minValue(1)->required()->default(10),
                TextInput::make('max')->label('Số lượng tối đa')->integer()->minValue(1)->required()->default(10000)->gte('min'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('name')->label('Dịch vụ')->searchable()->limit(60)->wrap()
                    ->description(fn (Service $r) => $r->category?->name),
                TextColumn::make('provider.name')->label('Nguồn')->placeholder('Làm tay')
                    ->description(fn (Service $r) => $r->provider_service_id ? "#{$r->provider_service_id}" : null),
                TextColumn::make('type')->label('Loại')->badge()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('rate')->label('Giá bán')->formatStateUsing(fn ($state) => Fmt::usd($state))->sortable(),
                TextColumn::make('provider_rate')->label('Giá vốn')->formatStateUsing(fn ($state) => Fmt::usd($state)),
                TextColumn::make('margin')->label('Lãi')
                    ->state(fn (Service $r) => self::marginPercent($r))
                    ->formatStateUsing(fn ($state) => $state === null ? '—' : "{$state}%")
                    ->color(fn ($state) => $state !== null && $state < 10 ? 'danger' : 'success'),
                TextColumn::make('min')->label('Min / Max')->formatStateUsing(fn (Service $r) => number_format($r->min).' – '.number_format($r->max)),
                TextColumn::make('status')->label('Trạng thái')->badge(),
            ])
            ->filters([
                SelectFilter::make('category_id')->label('Danh mục')->relationship('category', 'name')->searchable()->preload(),
                SelectFilter::make('provider_id')->label('Provider')->relationship('provider', 'name'),
                SelectFilter::make('status')->label('Trạng thái')->options(ActiveStatus::class),
                SelectFilter::make('fulfillment')->label('Cách xử lý')->options(Fulfillment::class),
                SelectFilter::make('type')->label('Loại')->options(ServiceType::class),
                TrashedFilter::make(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('enable')->label('Bật')->icon(Heroicon::OutlinedCheckCircle)
                        ->action(fn (Collection $records) => $records->each->update(['status' => ActiveStatus::Active])),
                    BulkAction::make('disable')->label('Tắt')->icon(Heroicon::OutlinedPauseCircle)
                        ->action(fn (Collection $records) => $records->each->update(['status' => ActiveStatus::Disabled])),
                    BulkAction::make('markup')->label('Đặt giá theo % lãi')->icon(Heroicon::OutlinedCalculator)
                        ->schema([TextInput::make('percent')->label('% lãi trên giá vốn')->numeric()->minValue(0)->required()])
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
