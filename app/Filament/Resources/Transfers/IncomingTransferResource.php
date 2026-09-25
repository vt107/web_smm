<?php

namespace App\Filament\Resources\Transfers;

use App\Enums\TransferStatus;
use App\Exceptions\SmmException;
use App\Filament\NavGroup;
use App\Filament\Resources\Transfers\Pages\ManageIncomingTransfers;
use App\Models\IncomingTransfer;
use App\Models\User;
use App\Services\Payments\DepositService;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class IncomingTransferResource extends Resource
{
    protected static ?string $model = IncomingTransfer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownOnSquareStack;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::FINANCE;

    protected static ?string $slug = 'incoming-transfers';

    protected static ?string $modelLabel = 'incoming transfer';

    protected static ?string $pluralModelLabel = 'Incoming transfers';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('transferred_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['paymentMethod', 'deposit.user']))
            ->columns([
                TextColumn::make('paymentMethod.name')->label('Method'),
                TextColumn::make('amount')->label('Amount')
                    ->formatStateUsing(fn (IncomingTransfer $r) => Money::format($r->amount).' '.$r->paymentMethod->currency)
                    ->weight('bold'),
                TextColumn::make('external_id')->label('Transaction ID / tx hash')->limit(20)->copyable()->searchable(),
                TextColumn::make('content')->label('Description')->limit(40)->placeholder('—')->searchable()
                    ->description(fn (IncomingTransfer $r) => $r->sender ? str($r->sender)->limit(24) : null),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('deposit.code')->label('Deposit')->placeholder('—')
                    ->description(fn (IncomingTransfer $r) => $r->deposit?->user?->username),
                TextColumn::make('transferred_at')->label('Time')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(TransferStatus::class)->default(TransferStatus::Unmatched->value),
                SelectFilter::make('payment_method_id')->label('Method')->relationship('paymentMethod', 'name'),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('assign')
                        ->label('Assign to customer & credit')
                        ->icon(Heroicon::OutlinedUserPlus)
                        ->visible(fn (IncomingTransfer $r) => $r->status === TransferStatus::Unmatched)
                        ->schema([
                            Select::make('user_id')->label('Customers')->required()->searchable()
                                ->getSearchResultsUsing(fn (string $search) => User::query()
                                    ->where(fn ($q) => $q->where('username', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                                    ->limit(20)->pluck('username', 'id'))
                                ->getOptionLabelUsing(fn ($value) => User::query()->find($value)?->username),
                        ])
                        ->action(function (IncomingTransfer $record, array $data) {
                            try {
                                $deposit = app(DepositService::class)->assignToUser($record, User::query()->findOrFail($data['user_id']));
                                Notification::make()->success()->title("Credited via deposit {$deposit->code}")->send();
                            } catch (SmmException $e) {
                                Notification::make()->danger()->title($e->localized())->send();
                            }
                        }),
                    Action::make('rematch')
                        ->label('Retry auto-match')
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->visible(fn (IncomingTransfer $r) => $r->status === TransferStatus::Unmatched)
                        ->action(function (IncomingTransfer $record) {
                            $deposit = app(DepositService::class)->autoMatch($record);
                            $deposit
                                ? Notification::make()->success()->title("Matched deposit {$deposit->code}")->send()
                                : Notification::make()->warning()->title('No matching deposit found')->send();
                        }),
                    Action::make('ignore')
                        ->label('Ignored')
                        ->icon(Heroicon::OutlinedEyeSlash)
                        ->requiresConfirmation()
                        ->visible(fn (IncomingTransfer $r) => $r->status === TransferStatus::Unmatched)
                        ->action(fn (IncomingTransfer $r) => $r->update(['status' => TransferStatus::Ignored])),
                    Action::make('restore')
                        ->label('Move back to unmatched')
                        ->icon(Heroicon::OutlinedArrowUturnLeft)
                        ->visible(fn (IncomingTransfer $r) => $r->status === TransferStatus::Ignored)
                        ->action(fn (IncomingTransfer $r) => $r->update(['status' => TransferStatus::Unmatched])),
                ]),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        return IncomingTransfer::query()->where('status', TransferStatus::Unmatched)->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getPages(): array
    {
        return ['index' => ManageIncomingTransfers::route('/')];
    }
}
