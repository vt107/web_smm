<?php

namespace App\Filament\Widgets;

use App\Enums\DepositStatus;
use App\Enums\OrderStatus;
use App\Enums\TicketStatus;
use App\Enums\TransferStatus;
use App\Filament\Access;
use App\Filament\Support\Fmt;
use App\Models\Deposit;
use App\Models\IncomingTransfer;
use App\Models\Order;
use App\Models\Provider;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '60s';

    protected static ?int $sort = 1;

    /** Doanh thu / số dư: chỉ admin. */
    public static function canView(): bool
    {
        return Access::isAdmin();
    }

    protected function getStats(): array
    {
        $today = now()->startOfDay();

        $orders = Order::query()->where('created_at', '>=', $today);
        $revenue = Money::sub((string) (clone $orders)->sum('charge'), (string) (clone $orders)->sum('refunded_amount'));
        $cost = (string) (clone $orders)->whereNotIn('status', [OrderStatus::Canceled, OrderStatus::Refunded])->sum('cost');

        $deposits = Deposit::query()->where('status', DepositStatus::Completed)->where('paid_at', '>=', $today);

        $attention = Order::query()->whereIn('status', OrderStatus::active())
            ->where(fn ($q) => $q->whereNotNull('provider_error')->orWhereNotNull('cancel_requested_at')->orWhereNull('provider_id'))
            ->count();

        return [
            Stat::make('Revenue today', Fmt::usd($revenue))
                ->description((clone $orders)->count().' orders · est. profit '.Fmt::usd(Money::sub($revenue, $cost))),
            Stat::make('Deposits today', Fmt::usd((string) $deposits->sum('amount')))
                ->description($deposits->count().' deposits'),
            Stat::make('New customers today', (string) User::query()->where('created_at', '>=', $today)->count())
                ->description('Total customer balance: '.Fmt::usd((string) User::query()->sum('balance'))),
            Stat::make('Active orders', (string) Order::query()->whereIn('status', OrderStatus::active())->count())
                ->description("{$attention} need attention")
                ->color($attention ? 'danger' : 'success'),
            Stat::make('Pending', (string) Ticket::query()->whereIn('status', [TicketStatus::Open, TicketStatus::CustomerReply])->count().' ticket')
                ->description(IncomingTransfer::query()->where('status', TransferStatus::Unmatched)->count().' unmatched deposit transactions'),
            Stat::make('Provider balance', Fmt::usd((string) Provider::query()->sum('balance')))
                ->description(Provider::query()->count().' provider'),
        ];
    }
}
