<?php

namespace App\Models;

use App\Enums\TransferStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Mọi giao dịch tiền vào quét được (SePay webhook / quét ví crypto), kể cả chưa khớp phiếu nạp. */
class IncomingTransfer extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => TransferStatus::class,
            'amount' => 'decimal:8',
            'block_number' => 'integer',
            'confirmations' => 'integer',
            'transferred_at' => 'datetime',
            'raw' => 'array',
        ];
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function deposit(): BelongsTo
    {
        return $this->belongsTo(Deposit::class);
    }
}
