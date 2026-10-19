<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\ContractOccupant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractOccupant>
 */
class ContractOccupantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory()->active(),
            'full_name' => fake()->name(),
            'move_in_date' => fn (array $attributes): string => Contract::findOrFail($attributes['contract_id'])->start_date->toDateString(),
            'move_out_date' => null,
        ];
    }
}
