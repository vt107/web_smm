<?php

namespace App\Models;

use App\Enums\ServiceUpdateType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceUpdate extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['service_id', 'type', 'old_value', 'new_value'];

    protected function casts(): array
    {
        return [
            'type' => ServiceUpdateType::class,
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withTrashed();
    }
}
