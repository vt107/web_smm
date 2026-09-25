<?php

namespace App\Models;

use App\Enums\ActiveStatus;
use App\Enums\Fulfillment;
use App\Enums\ServiceType;
use App\Observers\ServiceObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(ServiceObserver::class)]
class Service extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id', 'fulfillment', 'provider_id', 'provider_service_id', 'name', 'description', 'type',
        'rate', 'provider_rate', 'min', 'max', 'refill', 'refill_days', 'cancel', 'dripfeed',
        'average_time', 'sort', 'status',
    ];

    protected function casts(): array
    {
        return [
            'fulfillment' => Fulfillment::class,
            'type' => ServiceType::class,
            'status' => ActiveStatus::class,
            'rate' => 'decimal:6',
            'provider_rate' => 'decimal:6',
            'min' => 'integer',
            'max' => 'integer',
            'refill' => 'boolean',
            'refill_days' => 'integer',
            'cancel' => 'boolean',
            'dripfeed' => 'boolean',
            'average_time' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function updates(): HasMany
    {
        return $this->hasMany(ServiceUpdate::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where($query->qualifyColumn('status'), ActiveStatus::Active);
    }

    public function isActive(): bool
    {
        return $this->status === ActiveStatus::Active;
    }

    public function isManual(): bool
    {
        return $this->fulfillment === Fulfillment::Manual;
    }
}
