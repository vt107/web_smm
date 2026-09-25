<?php

namespace Database\Factories;

use App\Enums\ActiveStatus;
use App\Enums\Fulfillment;
use App\Enums\ServiceType;
use App\Models\Category;
use App\Models\Provider;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'fulfillment' => Fulfillment::Api,
            'provider_id' => Provider::factory(),
            'provider_service_id' => (string) fake()->unique()->numberBetween(1, 99999),
            'name' => 'Instagram Followers [Real]',
            'type' => ServiceType::Default,
            'rate' => '2.000000',
            'provider_rate' => '1.500000',
            'min' => 10,
            'max' => 10000,
            'refill' => true,
            'refill_days' => 30,
            'cancel' => true,
            'dripfeed' => true,
            'status' => ActiveStatus::Active,
        ];
    }

    public function manual(): static
    {
        return $this->state(fn () => [
            'fulfillment' => Fulfillment::Manual,
            'provider_id' => null,
            'provider_service_id' => null,
        ]);
    }

    public function type(ServiceType $type): static
    {
        return $this->state(fn () => ['type' => $type]);
    }
}
