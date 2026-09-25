<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TransactionType: string implements HasColor, HasLabel
{
    case Deposit = 'deposit';
    case DepositBonus = 'deposit_bonus';
    case Order = 'order';
    case Refund = 'refund';
    case PartialRefund = 'partial_refund';
    case AdminAdd = 'admin_add';
    case AdminDeduct = 'admin_deduct';
    case Referral = 'referral';

    /** Giao dịch được tính vào users.total_spent (dùng để xét cấp). */
    public function affectsSpent(): bool
    {
        return in_array($this, [self::Order, self::Refund, self::PartialRefund], true);
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Deposit => 'Deposit',
            self::DepositBonus => 'Deposit bonus',
            self::Order => 'Order',
            self::Refund => 'Refund',
            self::PartialRefund => 'Partial refund',
            self::AdminAdd => 'Admin credit',
            self::AdminDeduct => 'Admin debit',
            self::Referral => 'Referral commission',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Deposit => 'success',
            self::DepositBonus => 'success',
            self::Order => 'gray',
            self::Refund => 'info',
            self::PartialRefund => 'info',
            self::AdminAdd => 'warning',
            self::AdminDeduct => 'danger',
            self::Referral => 'success',
        };
    }
}
