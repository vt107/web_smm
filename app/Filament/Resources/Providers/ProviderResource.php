<?php

namespace App\Filament\Resources\Providers;

use App\Enums\ActiveStatus;
use App\Exceptions\ProviderException;
use App\Filament\Concerns\StaffPermissions;
use App\Filament\NavGroup;
use App\Filament\Resources\Providers\Pages\ManageProviders;
use App\Filament\Support\Fmt;
use App\Models\Provider;
use App\Services\Providers\ServiceSyncService;
use App\Support\Demo\DemoMode;
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
    use StaffPermissions;

    protected static ?string $model = Provider::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::CATALOG;

    protected static ?string $modelLabel = 'provider';

    protected static ?string $pluralModelLabel = 'Providers';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Name')->required()->maxLength(100),
            Select::make('driver')->label('API standard')->options(['perfectpanel' => 'PerfectPanel API v2'])
                ->required()->default('perfectpanel'),
            TextInput::make('api_url')->label('API URL')->url()->required()->placeholder('https://provider.com/api/v2'),
            TextInput::make('api_key')->label('API key')->password()->revealable()
                // Không đổ key cũ ra form; chỉ lưu khi nhập key mới.
                ->formatStateUsing(fn () => null)
                ->dehydrated(fn ($state) => filled($state))
                ->required(fn (string $operation) => $operation === 'create')
                ->helperText(fn (string $operation) => $operation === 'edit' ? 'Leave empty to keep the current key.' : null),
            Select::make('status')->label('Status')->options(ActiveStatus::class)->required()->default(ActiveStatus::Active),
            Textarea::make('note')->label('Note')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Name')->weight('bold')->description(fn (Provider $r) => $r->api_url),
                TextColumn::make('balance')->label('Balance')->formatStateUsing(fn ($state) => Fmt::usd($state)),
                TextColumn::make('services_count')->label('Services')->counts('services'),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('last_synced_at')->label('Last synced')->since()->placeholder('Never'),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    Action::make('sync')
                        ->label('Sync prices & balance')
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->action(function (Provider $record) {
                            DemoMode::abortIfEnabled(); // gọi API provider

                            try {
                                $stats = app(ServiceSyncService::class)->sync($record);
                                Notification::make()->success()->title('Synced')
                                    ->body("Updated {$stats['updated']} services, disabled {$stats['disabled']} services.")->send();
                            } catch (ProviderException $e) {
                                Notification::make()->danger()->title('Provider error')->body($e->getMessage())->send();
                            }
                        }),
                    Action::make('import')
                        ->label('Import new services')
                        ->icon(Heroicon::OutlinedArrowDownTray)
                        ->schema([
                            TextInput::make('markup')->label('Markup % over cost')->numeric()->minValue(0)->default(30)->required(),
                            Textarea::make('only')->label('Only import these provider service IDs')
                                ->placeholder('Leave empty to import all. Comma separated.'),
                        ])
                        ->action(function (Provider $record, array $data) {
                            DemoMode::abortIfEnabled(); // gọi API provider

                            $only = filled($data['only'] ?? null)
                                ? array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', $data['only']))))
                                : null;

                            try {
                                $created = app(ServiceSyncService::class)->import($record, (string) $data['markup'], $only);
                                Notification::make()->success()->title("Imported {$created} services")->send();
                            } catch (ProviderException $e) {
                                Notification::make()->danger()->title('Provider error')->body($e->getMessage())->send();
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
