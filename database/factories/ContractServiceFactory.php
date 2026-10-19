<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\ContractService;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractService>
 */
class ContractServiceFactory extends Factory
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
            'service_id' => fn (array $attributes): int => Service::factory()->create(['landlord_id' => Contract::findOrFail($attributes['contract_id'])->room->property->landlord_id])->id,
            'use_default' => false,
            'billing_method' => 'per_person',
            'unit_price' => 90000,
            'fixed_price' => 0,
            'is_enabled' => true,
            'effective_from' => today()->startOfMonth(),
        ];
    }
}
