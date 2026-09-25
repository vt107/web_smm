<?php

namespace App\Enums;

enum TransactionType: string
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
}
