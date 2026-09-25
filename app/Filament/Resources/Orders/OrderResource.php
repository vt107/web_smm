<?php

namespace App\Filament\Resources\Orders;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Exceptions\SmmException;
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
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::ORDERS;

    protected static ?string $modelLabel = 'đơn hàng';

    protected static ?string $pluralModelLabel = 'Đơn hàng';

    protected static ?int $navigationSort = 1;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')->label('ID')->sortable()->searchable(isIndividual: true, isGlobal: false),
                TextColumn::make('user.username')->label('Khách')->searchable(),
                TextColumn::make('service.name')->label('Dịch vụ')->limit(40)->wrap()
                    ->description(fn (Order $r) => "#{$r->service_id}"),
                TextColumn::make('link')->label('Link')->limit(30)->searchable()
                    ->url(fn (Order $r) => str_starts_with($r->link, 'http') ? $r->link : null, shouldOpenInNewTab: true),
                TextColumn::make('quantity')->label('Số lượng')->numeric()
                    ->description(fn (Order $r) => $r->remains !== null && $r->status !== OrderStatus::Completed ? "Còn {$r->remains}" : null),
                TextColumn::make('charge')->label('Tiền')->formatStateUsing(fn ($state) => Fmt::usd($state))
                    ->description(fn (Order $r) => Money::isPositive($r->refunded_amount) ? 'Hoàn '.Fmt::usd($r->refunded_amount) : null),
                TextColumn::make('status')->label('Trạng thái')->badge()
                    ->description(fn (Order $r) => $r->provider_error ? 'Lỗi provider' : ($r->cancel_requested_at ? 'Đang yêu cầu huỷ' : null)),
                TextColumn::make('provider.name')->label('Provider')->placeholder('Làm tay')
                    ->description(fn (Order $r) => $r->provider_order_id ? "#{$r->provider_order_id}" : null)
                    ->toggleable(),
                TextColumn::make('source')->label('Nguồn')->badge()->color('gray')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('Ngày đặt')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Trạng thái')->options(OrderStatus::class)->multiple(),
                SelectFilter::make('provider_id')->label('Provider')->relationship('provider', 'name'),
                SelectFilter::make('source')->label('Nguồn đặt')->options(OrderSource::class),
                Filter::make('needs_attention')->label('Cần xử lý (lỗi / chờ huỷ / làm tay)')
                    ->query(fn (Builder $query) => $query->whereIn('status', OrderStatus::active())->where(fn ($q) => $q
                        ->whereNotNull('provider_error')
                        ->orWhereNotNull('cancel_requested_at')
                        ->orWhereNull('provider_id'))),
                Filter::make('created_at')->schema([
                    DatePicker::make('from')->label('Từ ngày'),
                    DatePicker::make('until')->label('Đến ngày'),
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
                ->label('Cập nhật trạng thái')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->visible(fn (Order $record) => ! $record->status->isFinal())
                ->fillForm(fn (Order $record) => [
                    'status' => $record->status,
                    'start_count' => $record->start_count,
                    'remains' => $record->remains,
                ])
                ->schema([
                    Select::make('status')->label('Trạng thái')->options(OrderStatus::class)->required()
                        ->helperText('Partial → hoàn tiền phần còn thiếu; Canceled → hoàn toàn bộ.'),
                    TextInput::make('start_count')->label('Start count')->integer()->minValue(0),
                    TextInput::make('remains')->label('Còn thiếu (remains)')->integer()->minValue(0),
                ])
                ->action(function (Order $record, array $data) {
                    app(OrderService::class)->applyStatus(
                        $record,
                        OrderStatus::from($data['status'] instanceof OrderStatus ? $data['status']->value : $data['status']),
                        filled($data['start_count']) ? (int) $data['start_count'] : null,
                        filled($data['remains']) ? (int) $data['remains'] : null,
                    );
                    Notification::make()->success()->title('Đã cập nhật đơn #'.$record->id)->send();
                }),
            Action::make('resend')
                ->label('Gửi lại sang provider')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->visible(fn (Order $record) => $record->status === OrderStatus::Pending && ! $record->isSentToProvider() && $record->provider_id)
                ->requiresConfirmation()
                ->action(function (Order $record) {
                    try {
                        app(OrderService::class)->resend($record);
                        Notification::make()->success()->title('Đã đưa đơn vào hàng đợi gửi lại')->send();
                    } catch (SmmException $e) {
                        Notification::make()->danger()->title($e->localized())->send();
                    }
                }),
            Action::make('cancelRefund')
                ->label('Huỷ & hoàn tiền')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->visible(fn (Order $record) => ! $record->status->isFinal())
                ->requiresConfirmation()
                ->modalDescription('Đơn chuyển sang Canceled và hoàn phần tiền chưa hoàn. Nếu đơn đã gửi provider, hãy chắc chắn provider đã huỷ.')
                ->action(function (Order $record) {
                    app(OrderService::class)->applyStatus($record, OrderStatus::Canceled);
                    Notification::make()->success()->title('Đã huỷ và hoàn tiền đơn #'.$record->id)->send();
                }),
            Action::make('refund')
                ->label('Hoàn toàn bộ tiền')
                ->icon(Heroicon::OutlinedReceiptRefund)
                ->color('danger')
                ->visible(fn (Order $record) => $record->status->isFinal() && $record->status !== OrderStatus::Refunded)
                ->requiresConfirmation()
                ->modalDescription('Hoàn phần tiền chưa hoàn của đơn đã kết thúc và chuyển trạng thái sang Refunded.')
                ->action(function (Order $record) {
                    app(OrderService::class)->refundByAdmin($record);
                    Notification::make()->success()->title('Đã hoàn tiền đơn #'.$record->id)->send();
                }),
            Action::make('note')
                ->label('Ghi chú nội bộ')
                ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
                ->fillForm(fn (Order $record) => ['admin_note' => $record->admin_note])
                ->schema([Textarea::make('admin_note')->label('Ghi chú')->rows(4)])
                ->action(fn (Order $record, array $data) => $record->update(['admin_note' => $data['admin_note']])),
        ];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Đơn hàng')->columnSpan(2)->columns(2)->schema([
                TextEntry::make('service.name')->label('Dịch vụ')->columnSpanFull(),
                TextEntry::make('link')->label('Link')->copyable()->columnSpanFull(),
                TextEntry::make('status')->label('Trạng thái')->badge(),
                TextEntry::make('source')->label('Nguồn đặt')->badge()->color('gray'),
                TextEntry::make('quantity')->label('Số lượng')->numeric(),
                TextEntry::make('runs')->label('Drip-feed')->placeholder('Không')
                    ->formatStateUsing(fn (Order $record) => "{$record->runs} lần, cách {$record->interval} phút"),
                TextEntry::make('start_count')->label('Start count')->placeholder('—'),
                TextEntry::make('remains')->label('Còn thiếu')->placeholder('—'),
                KeyValueEntry::make('extra')->label('Tham số thêm')->columnSpanFull()->visible(fn (Order $r) => ! empty($r->extra)),
            ]),
            Section::make('Tiền')->columnSpan(1)->schema([
                TextEntry::make('user.username')->label('Khách')->helperText(fn (Order $r) => $r->user->email),
                TextEntry::make('rate')->label('Giá / 1000')->formatStateUsing(fn ($state) => Fmt::usd($state)),
                TextEntry::make('discount_percent')->label('Giảm theo cấp')->suffix('%'),
                TextEntry::make('charge')->label('Đã trừ')->formatStateUsing(fn ($state) => Fmt::usd($state)),
                TextEntry::make('refunded_amount')->label('Đã hoàn')->formatStateUsing(fn ($state) => Fmt::usd($state)),
                TextEntry::make('cost')->label('Giá vốn')->formatStateUsing(fn ($state) => Fmt::usd($state)),
            ]),
            Section::make('Provider')->columnSpan(2)->columns(2)->schema([
                TextEntry::make('provider.name')->label('Provider')->placeholder('Làm tay'),
                TextEntry::make('provider_order_id')->label('Order ID provider')->placeholder('Chưa gửi')->copyable(),
                TextEntry::make('send_attempts')->label('Số lần gửi'),
                TextEntry::make('last_checked_at')->label('Kiểm tra lần cuối')->since()->placeholder('—'),
                TextEntry::make('cancel_requested_at')->label('Yêu cầu huỷ lúc')->dateTime('d/m/Y H:i')->placeholder('—'),
                TextEntry::make('completed_at')->label('Hoàn thành lúc')->dateTime('d/m/Y H:i')->placeholder('—'),
                TextEntry::make('provider_error')->label('Lỗi provider')->color('danger')->placeholder('—')->columnSpanFull(),
            ]),
            Section::make('Ghi chú nội bộ')->columnSpan(1)->schema([
                TextEntry::make('admin_note')->hiddenLabel()->placeholder('Chưa có ghi chú'),
                TextEntry::make('created_at')->label('Ngày đặt')->dateTime('d/m/Y H:i'),
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
        return 'Đơn cần xử lý: lỗi provider, chờ huỷ hoặc dịch vụ làm tay';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
