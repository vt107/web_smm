<?php

namespace App\Filament\Widgets;

use App\Enums\DepositStatus;
use App\Enums\OrderStatus;
use App\Enums\TicketStatus;
use App\Enums\TransferStatus;
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
            Stat::make('Doanh thu hôm nay', Fmt::usd($revenue))
                ->description((clone $orders)->count().' đơn · lãi ước tính '.Fmt::usd(Money::sub($revenue, $cost))),
            Stat::make('Nạp tiền hôm nay', Fmt::usd((string) $deposits->sum('amount')))
                ->description($deposits->count().' phiếu'),
            Stat::make('Khách mới hôm nay', (string) User::query()->where('created_at', '>=', $today)->count())
                ->description('Tổng số dư khách: '.Fmt::usd((string) User::query()->sum('balance'))),
            Stat::make('Đơn đang chạy', (string) Order::query()->whereIn('status', OrderStatus::active())->count())
                ->description("{$attention} đơn cần xử lý")
                ->color($attention ? 'danger' : 'success'),
            Stat::make('Chờ xử lý', (string) Ticket::query()->whereIn('status', [TicketStatus::Open, TicketStatus::CustomerReply])->count().' ticket')
                ->description(IncomingTransfer::query()->where('status', TransferStatus::Unmatched)->count().' giao dịch nạp chưa khớp'),
            Stat::make('Số dư provider', Fmt::usd((string) Provider::query()->sum('balance')))
                ->description(Provider::query()->count().' provider'),
        ];
    }
}
