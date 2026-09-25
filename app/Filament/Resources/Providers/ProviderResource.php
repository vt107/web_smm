<?php

namespace App\Filament\Resources\Providers;

use App\Enums\ActiveStatus;
use App\Exceptions\ProviderException;
use App\Filament\NavGroup;
use App\Filament\Resources\Providers\Pages\ManageProviders;
use App\Filament\Support\Fmt;
use App\Models\Provider;
use App\Services\Providers\ServiceSyncService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ProviderResource extends Resource
{
    protected static ?string $model = Provider::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::CATALOG;

    protected static ?string $modelLabel = 'provider';

    protected static ?string $pluralModelLabel = 'Provider';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Tên')->required()->maxLength(100),
            Select::make('driver')->label('Chuẩn API')->options(['perfectpanel' => 'PerfectPanel API v2'])
                ->required()->default('perfectpanel'),
            TextInput::make('api_url')->label('API URL')->url()->required()->placeholder('https://provider.com/api/v2'),
            TextInput::make('api_key')->label('API key')->password()->revealable()
                // Không đổ key cũ ra form; chỉ lưu khi nhập key mới.
                ->formatStateUsing(fn () => null)
                ->dehydrated(fn ($state) => filled($state))
                ->required(fn (string $operation) => $operation === 'create')
                ->helperText(fn (string $operation) => $operation === 'edit' ? 'Bỏ trống để giữ key hiện tại.' : null),
            Select::make('status')->label('Trạng thái')->options(ActiveStatus::class)->required()->default(ActiveStatus::Active),
            Textarea::make('note')->label('Ghi chú')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Tên')->weight('bold')->description(fn (Provider $r) => $r->api_url),
                TextColumn::make('balance')->label('Số dư')->formatStateUsing(fn ($state) => Fmt::usd($state)),
                TextColumn::make('services_count')->label('Dịch vụ')->counts('services'),
                TextColumn::make('status')->label('Trạng thái')->badge(),
                TextColumn::make('last_synced_at')->label('Đồng bộ lần cuối')->since()->placeholder('Chưa'),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    Action::make('sync')
                        ->label('Đồng bộ giá & số dư')
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->action(function (Provider $record) {
                            try {
                                $stats = app(ServiceSyncService::class)->sync($record);
                                Notification::make()->success()->title('Đã đồng bộ')
                                    ->body("Cập nhật {$stats['updated']} dịch vụ, tắt {$stats['disabled']} dịch vụ.")->send();
                            } catch (ProviderException $e) {
                                Notification::make()->danger()->title('Provider báo lỗi')->body($e->getMessage())->send();
                            }
                        }),
                    Action::make('import')
                        ->label('Import dịch vụ mới')
                        ->icon(Heroicon::OutlinedArrowDownTray)
                        ->schema([
                            TextInput::make('markup')->label('% lãi cộng vào giá vốn')->numeric()->minValue(0)->default(30)->required(),
                            Textarea::make('only')->label('Chỉ import các service ID (của provider)')
                                ->placeholder('Bỏ trống = import tất cả. Phân tách bằng dấu phẩy.'),
                        ])
                        ->action(function (Provider $record, array $data) {
                            $only = filled($data['only'] ?? null)
                                ? array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', $data['only']))))
                                : null;

                            try {
                                $created = app(ServiceSyncService::class)->import($record, (string) $data['markup'], $only);
                                Notification::make()->success()->title("Đã import {$created} dịch vụ")->send();
                            } catch (ProviderException $e) {
                                Notification::make()->danger()->title('Provider báo lỗi')->body($e->getMessage())->send();
                            }
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageProviders::route('/')];
    }
}
