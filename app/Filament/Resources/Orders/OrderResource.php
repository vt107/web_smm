<?php

namespace App\Filament\Resources\Orders;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Exceptions\SmmException;
use App\Filament\Access;
use App\Filament\Concerns\StaffPermissions;
use App\Filament\NavGroup;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Support\Fmt;
use App\Models\Order;
use App\Services\OrderService;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class OrderResource extends Resource
{
    use StaffPermissions;

    /** Nhân viên Support được xem (không sửa). */
    protected static function supportAbilities(): array
    {
        return ['viewAny', 'view'];
    }

    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::ORDERS;

    protected static ?string $modelLabel = 'order';

    protected static ?string $pluralModelLabel = 'Orders';

    protected static ?int $navigationSort = 1;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')->label('ID')->sortable()->searchable(isIndividual: true, isGlobal: false),
                TextColumn::make('user.username')->label('Customer')->searchable(),
                TextColumn::make('service.name')->label('Services')->limit(40)->wrap()
                    ->description(fn (Order $r) => "#{$r->service_id}"),
                TextColumn::make('link')->label('Link')->limit(30)->searchable()
                    ->url(fn (Order $r) => str_starts_with($r->link, 'http') ? $r->link : null, shouldOpenInNewTab: true),
                TextColumn::make('quantity')->label('Quantity')->numeric()
                    ->description(fn (Order $r) => $r->remains !== null && $r->status !== OrderStatus::Completed ? "{$r->remains} remaining" : null),
                TextColumn::make('charge')->label('Charge')->formatStateUsing(fn ($state) => Fmt::usd($state))
                    ->description(fn (Order $r) => Money::isPositive($r->refunded_amount) ? 'Refunded '.Fmt::usd($r->refunded_amount) : null),
                TextColumn::make('status')->label('Status')->badge()
                    ->description(fn (Order $r) => $r->provider_error ? 'Provider error' : ($r->cancel_requested_at ? 'Cancel requested' : null)),
                TextColumn::make('provider.name')->label('Provider')->placeholder('Manual')
                    ->description(fn (Order $r) => $r->provider_order_id ? "#{$r->provider_order_id}" : null)
                    ->toggleable(),
                TextColumn::make('source')->label('Source')->badge()->color('gray')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('Created')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(OrderStatus::class)->multiple(),
                SelectFilter::make('provider_id')->label('Provider')->relationship('provider', 'name'),
                SelectFilter::make('source')->label('Source')->options(OrderSource::class),
                Filter::make('needs_attention')->label('Needs attention (errors / cancel requests / manual)')
                    ->query(fn (Builder $query) => $query->whereIn('status', OrderStatus::active())->where(fn ($q) => $q
                        ->whereNotNull('provider_error')
                        ->orWhereNotNull('cancel_requested_at')
                        ->orWhereNull('provider_id'))),
                Filter::make('created_at')->schema([
                    DatePicker::make('from')->label('From'),
                    DatePicker::make('until')->label('Until'),
                ])->query(fn (Builder $query, array $data) => $query
                    ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                    ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make(self::orderActions()),
            ]);
    }

    /** Thao tác xử lý đơn dùng chung cho bảng và trang chi tiết. */
    public static function orderActions(): array
    {
        return [
            Action::make('updateStatus')
                ->label('Update status')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->visible(fn (Order $record) => Access::isAdmin() && ! $record->status->isFinal())
                ->fillForm(fn (Order $record) => [
                    'status' => $record->status,
                    'start_count' => $record->start_count,
                    'remains' => $record->remains,
                ])
                ->schema([
                    Select::make('status')->label('Status')->options(OrderStatus::class)->required()
                        ->helperText('Partial → refunds the remains; Canceled → full refund.'),
                    TextInput::make('start_count')->label('Start count')->integer()->minValue(0),
                    TextInput::make('remains')->label('Remains')->integer()->minValue(0),
                ])
                ->action(function (Order $record, array $data) {
                    app(OrderService::class)->applyStatus(
                        $record,
                        OrderStatus::from($data['status'] instanceof OrderStatus ? $data['status']->value : $data['status']),
                        filled($data['start_count']) ? (int) $data['start_count'] : null,
                        filled($data['remains']) ? (int) $data['remains'] : null,
                    );
                    Notification::make()->success()->title('Updated order #'.$record->id)->send();
                }),
            Action::make('resend')
                ->label('Resend to provider')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->visible(fn (Order $record) => $record->status === OrderStatus::Pending && ! $record->isSentToProvider() && $record->provider_id)
                ->requiresConfirmation()
                ->action(function (Order $record) {
                    try {
                        app(OrderService::class)->resend($record);
                        Notification::make()->success()->title('Order queued for resending')->send();
                    } catch (SmmException $e) {
                        Notification::make()->danger()->title($e->localized())->send();
                    }
                }),
            Action::make('cancelRefund')
                ->label('Cancel & refund')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->visible(fn (Order $record) => Access::isAdmin() && ! $record->status->isFinal())
                ->requiresConfirmation()
                ->modalDescription('The order becomes Canceled and the unrefunded amount is returned. If it was sent to a provider, make sure the provider has canceled it.')
                ->action(function (Order $record) {
                    app(OrderService::class)->applyStatus($record, OrderStatus::Canceled);
                    Notification::make()->success()->title('Canceled and refunded order #'.$record->id)->send();
                }),
            Action::make('refund')
                ->label('Full refund')
                ->icon(Heroicon::OutlinedReceiptRefund)
                ->color('danger')
                ->visible(fn (Order $record) => Access::isAdmin() && $record->status->isFinal() && $record->status !== OrderStatus::Refunded)
                ->requiresConfirmation()
                ->modalDescription('Refund the remaining amount of this finished order and mark it as Refunded.')
                ->action(function (Order $record) {
                    app(OrderService::class)->refundByAdmin($record);
                    Notification::make()->success()->title('Refunded order #'.$record->id)->send();
                }),
            Action::make('note')
                ->label('Internal note')
                ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
                ->fillForm(fn (Order $record) => ['admin_note' => $record->admin_note])
                ->schema([Textarea::make('admin_note')->label('Note')->rows(4)])
                ->action(fn (Order $record, array $data) => $record->update(['admin_note' => $data['admin_note']])),
        ];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Orders')->columnSpan(2)->columns(2)->schema([
                TextEntry::make('service.name')->label('Services')->columnSpanFull(),
                TextEntry::make('link')->label('Link')->copyable()->columnSpanFull(),
                TextEntry::make('status')->label('Status')->badge(),
                TextEntry::make('source')->label('Source')->badge()->color('gray'),
                TextEntry::make('quantity')->label('Quantity')->numeric(),
                TextEntry::make('runs')->label('Drip-feed')->placeholder('No')
                    ->formatStateUsing(fn (Order $record) => "{$record->runs} runs, every {$record->interval} min"),
                TextEntry::make('start_count')->label('Start count')->placeholder('—'),
                TextEntry::make('remains')->label('Remains')->placeholder('—'),
                KeyValueEntry::make('extra')->label('Extra parameters')->columnSpanFull()->visible(fn (Order $r) => ! empty($r->extra)),
            ]),
            Section::make('Charge')->columnSpan(1)->schema([
                TextEntry::make('user.username')->label('Customer')->helperText(fn (Order $r) => $r->user->email),
                TextEntry::make('rate')->label('Rate / 1000')->formatStateUsing(fn ($state) => Fmt::usd($state)),
                TextEntry::make('discount_percent')->label('Level discount')->suffix('%'),
                TextEntry::make('charge')->label('Charged')->formatStateUsing(fn ($state) => Fmt::usd($state)),
                TextEntry::make('refunded_amount')->label('Refunded')->formatStateUsing(fn ($state) => Fmt::usd($state)),
                TextEntry::make('cost')->label('Cost')->formatStateUsing(fn ($state) => Fmt::usd($state)),
            ]),
            Section::make('Provider')->columnSpan(2)->columns(2)->schema([
                TextEntry::make('provider.name')->label('Provider')->placeholder('Manual'),
                TextEntry::make('provider_order_id')->label('Order ID provider')->placeholder('Not sent')->copyable(),
                TextEntry::make('send_attempts')->label('Send attempts'),
                TextEntry::make('last_checked_at')->label('Last checked')->since()->placeholder('—'),
                TextEntry::make('cancel_requested_at')->label('Cancel requested at')->dateTime('d/m/Y H:i')->placeholder('—'),
                TextEntry::make('completed_at')->label('Completed at')->dateTime('d/m/Y H:i')->placeholder('—'),
                TextEntry::make('provider_error')->label('Provider error')->color('danger')->placeholder('—')->columnSpanFull(),
            ]),
            Section::make('Internal note')->columnSpan(1)->schema([
                TextEntry::make('admin_note')->hiddenLabel()->placeholder('No notes yet'),
                TextEntry::make('created_at')->label('Created')->dateTime('d/m/Y H:i'),
            ]),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'service', 'provider']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Order::query()->whereIn('status', OrderStatus::active())->where(fn ($q) => $q
            ->whereNotNull('provider_error')
            ->orWhereNotNull('cancel_requested_at')
            ->orWhereNull('provider_id'))->count();

        return $count ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Orders needing attention: provider errors, cancel requests or manual services';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
