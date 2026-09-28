<?php

namespace App\Models;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $attributes = [
        'status' => 'pending',
        'source' => 'web',
        'discount_percent' => '0',
        'refunded_amount' => '0',
        'send_attempts' => 0,
    ];

    protected $fillable = ['admin_note'];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'source' => OrderSource::class,
            'extra' => 'array',
            'quantity' => 'integer',
            'rate' => 'decimal:6',
            'discount_percent' => 'decimal:2',
            'charge' => 'decimal:6',
            'cost' => 'decimal:6',
            'refunded_amount' => 'decimal:6',
            'start_count' => 'integer',
            'remains' => 'integer',
            'runs' => 'integer',
            'interval' => 'integer',
            'send_attempts' => 'integer',
            'cancel_requested_at' => 'datetime',
            'next_send_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withTrashed();
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function refills(): HasMany
    {
        return $this->hasMany(OrderRefill::class);
    }

    public function isSentToProvider(): bool
    {
        return $this->provider_order_id !== null;
    }
}
