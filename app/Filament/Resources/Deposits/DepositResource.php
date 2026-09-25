<?php

namespace App\Filament\Resources\Deposits;

use App\Enums\DepositMatchedBy;
use App\Enums\DepositStatus;
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
    protected static ?string $model = Deposit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::FINANCE;

    protected static ?string $modelLabel = 'phiếu nạp';

    protected static ?string $pluralModelLabel = 'Phiếu nạp';

    protected static ?int $navigationSort = 1;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'paymentMethod']))
            ->columns([
                TextColumn::make('code')->label('Mã phiếu')->searchable()->copyable()->weight('bold'),
                TextColumn::make('user.username')->label('Khách')->searchable(),
                TextColumn::make('paymentMethod.name')->label('Kênh'),
                TextColumn::make('paid_amount')->label('Cần chuyển')
                    ->formatStateUsing(fn (Deposit $r) => Money::format($r->paid_amount).' '.$r->paid_currency)
                    ->description(fn (Deposit $r) => '≈ '.Fmt::usd($r->requested_amount)),
                TextColumn::make('amount')->label('Đã cộng')->placeholder('—')
                    ->formatStateUsing(fn ($state) => Fmt::usd($state))
                    ->description(fn (Deposit $r) => Money::isPositive($r->bonus) ? '+ bonus '.Fmt::usd($r->bonus) : null),
                TextColumn::make('status')->label('Trạng thái')->badge(),
                TextColumn::make('matched_by')->label('Khớp bởi')->placeholder('—')->toggleable(),
                TextColumn::make('created_at')->label('Tạo lúc')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('paid_at')->label('Thanh toán lúc')->dateTime('d/m/Y H:i')->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Trạng thái')->options(DepositStatus::class),
                SelectFilter::make('payment_method_id')->label('Kênh')->relationship('paymentMethod', 'name'),
                SelectFilter::make('matched_by')->label('Khớp bởi')->options(DepositMatchedBy::class),
            ])
            ->recordActions([
                Action::make('cancel')
                    ->label('Huỷ phiếu')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Deposit $r) => in_array($r->status, [DepositStatus::Pending, DepositStatus::Expired], true))
                    ->action(fn (Deposit $r) => $r->update(['status' => DepositStatus::Canceled])),
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
