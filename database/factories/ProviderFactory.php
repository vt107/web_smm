<?php

namespace Database\Factories;

use App\Enums\ActiveStatus;
use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Provider>
 */
class ProviderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Provider '.fake()->unique()->numberBetween(1, 9999),
            'api_url' => 'https://provider.test/api/v2',
            'api_key' => 'provider-secret-key',
            'driver' => 'perfectpanel',
            'status' => ActiveStatus::Active,
        ];
    }
}
