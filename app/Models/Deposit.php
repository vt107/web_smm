<?php

namespace App\Models;

use App\Enums\DepositMatchedBy;
use App\Enums\DepositStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Deposit extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => DepositStatus::class,
            'matched_by' => DepositMatchedBy::class,
            'requested_amount' => 'decimal:6',
            'paid_amount' => 'decimal:6',
            'exchange_rate' => 'decimal:6',
            'received_amount' => 'decimal:6',
            'amount' => 'decimal:6',
            'bonus' => 'decimal:6',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function transfer(): HasOne
    {
        return $this->hasOne(IncomingTransfer::class);
    }
}
