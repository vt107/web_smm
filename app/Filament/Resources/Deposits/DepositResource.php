<?php

namespace App\Filament\Resources\Deposits;

use App\Enums\DepositMatchedBy;
use App\Enums\DepositStatus;
use App\Filament\Concerns\StaffPermissions;
use App\Filament\NavGroup;
use App\Filament\Resources\Deposits\Pages\ManageDeposits;
use App\Filament\Support\Fmt;
use App\Models\Deposit;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class DepositResource extends Resource
{
    use StaffPermissions;

    protected static ?string $model = Deposit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::FINANCE;

    protected static ?string $modelLabel = 'deposit';

    protected static ?string $pluralModelLabel = 'Deposits';

    protected static ?int $navigationSort = 1;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'paymentMethod']))
            ->columns([
                TextColumn::make('code')->label('Deposit code')->searchable()->copyable()->weight('bold'),
                TextColumn::make('user.username')->label('Customer')->searchable(),
                TextColumn::make('paymentMethod.name')->label('Method'),
                TextColumn::make('paid_amount')->label('To pay')
                    ->formatStateUsing(fn (Deposit $r) => Money::format($r->paid_amount).' '.$r->paid_currency)
                    ->description(fn (Deposit $r) => '≈ '.Fmt::usd($r->requested_amount)),
                TextColumn::make('amount')->label('Credited')->placeholder('—')
                    ->formatStateUsing(fn ($state) => Fmt::usd($state))
                    ->description(fn (Deposit $r) => Money::isPositive($r->bonus) ? '+ bonus '.Fmt::usd($r->bonus) : null),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('matched_by')->label('Matched by')->placeholder('—')->toggleable(),
                TextColumn::make('created_at')->label('Created')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('paid_at')->label('Paid at')->dateTime('d/m/Y H:i')->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(DepositStatus::class),
                SelectFilter::make('payment_method_id')->label('Method')->relationship('paymentMethod', 'name'),
                SelectFilter::make('matched_by')->label('Matched by')->options(DepositMatchedBy::class),
            ])
            ->recordActions([
                Action::make('cancel')
                    ->label('Cancel deposit')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Deposit $r) => in_array($r->status, [DepositStatus::Pending, DepositStatus::Expired], true))
                    // Chỉ huỷ khi vẫn chưa thanh toán (tránh ghi đè phiếu vừa được webhook cộng tiền).
                    ->action(fn (Deposit $r) => Deposit::query()->whereKey($r->id)
                        ->whereIn('status', [DepositStatus::Pending, DepositStatus::Expired])
                        ->update(['status' => DepositStatus::Canceled])),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ManageDeposits::route('/')];
    }
}
