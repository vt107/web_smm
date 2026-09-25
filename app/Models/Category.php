<?php

namespace App\Models;

use App\Enums\ActiveStatus;
use App\Enums\Platform;
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

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }
}
