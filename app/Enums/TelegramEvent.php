<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Các loại thông báo quan trọng gửi về Telegram (admin chọn bật ở Website settings → Telegram). */
enum TelegramEvent: string implements HasLabel
{
    case DepositCompleted = 'deposit_completed';
    case UnmatchedTransfer = 'unmatched_transfer';
    case Ticket = 'ticket';
    case OrderFailed = 'order_failed';
    case ProviderLowBalance = 'provider_low_balance';
    case ProviderError = 'provider_error';
    case NewUser = 'new_user';

    public function getLabel(): string
    {
        return match ($this) {
            self::DepositCompleted => 'New deposit received',
            self::UnmatchedTransfer => 'Incoming payment not matched to any deposit (customer paid but was not credited)',
            self::Ticket => 'New support ticket or customer reply',
            self::OrderFailed => 'Order canceled because the provider rejected it (auto-refunded)',
            self::ProviderLowBalance => 'Provider balance is low',
            self::ProviderError => 'Provider API error during sync',
            self::NewUser => 'New customer registered',
        };
    }
}
