<?php

namespace Database\Factories;

use App\Enums\ActiveStatus;
use App\Enums\Platform;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'platform' => Platform::Instagram,
            'name' => 'Instagram Followers '.fake()->unique()->numberBetween(1, 99999),
            'sort' => 0,
            'status' => ActiveStatus::Active,
        ];
    }
}
