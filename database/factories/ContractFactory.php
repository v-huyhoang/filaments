<?php

namespace Database\Factories;

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\Room;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'room_id' => Room::factory(),
            'tenant_id' => Tenant::factory(),
            'start_date' => fake()->dateTimeBetween('-1 month', '+1 month')->format('Y-m-d'),
            'end_date' => fake()->dateTimeBetween('+2 months', '+12 months')->format('Y-m-d'),
            'rent_amount' => fake()->numberBetween(1_000_000, 10_000_000),
            'deposit_amount' => fake()->numberBetween(0, 10_000_000),
            'payment_due_day' => fake()->numberBetween(1, 28),
            'status' => ContractStatus::Draft,
        ];
    }
}
