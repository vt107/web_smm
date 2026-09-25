<?php

namespace App\Models;

use App\Enums\ActiveStatus;
use App\Enums\Platform;
use App\Observers\ServiceObserver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['platform', 'name', 'sort', 'status'];

    protected function casts(): array
    {
        return [
            'platform' => Platform::class,
            'status' => ActiveStatus::class,
        ];
    }

    protected static function booted(): void
    {
        $flush = fn () => ServiceObserver::flushCaches();
        static::saved($flush);
        static::deleted($flush);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }
}
