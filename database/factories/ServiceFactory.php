<?php

namespace Database\Factories;

use App\Enums\BillingMethod;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Service> */
class ServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'landlord_id' => User::factory(),
            'name' => fake()->word(),
            'code' => 'service_'.fake()->unique()->numerify('########'),
            'billing_method' => BillingMethod::Metered,
            'unit' => 'kWh',
            'is_active' => true,
        ];
    }

    public function fixed(): static
    {
        return $this->state(fn (): array => ['billing_method' => BillingMethod::Fixed, 'unit' => 'month']);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
