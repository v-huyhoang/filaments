<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\PropertyService;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PropertyService> */
class PropertyServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'service_id' => fn (array $attributes): int => Service::factory()->create([
                'landlord_id' => Property::query()->findOrFail($attributes['property_id'])->landlord_id,
            ])->id,
            'unit_price' => '3500.00',
            'fixed_price' => '0.00',
            'is_enabled' => true,
            'effective_from' => today()->toDateString(),
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => ['is_enabled' => false]);
    }
}
