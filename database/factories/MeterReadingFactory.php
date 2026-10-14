<?php

namespace Database\Factories;

use App\Models\MeterReading;
use App\Models\Room;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeterReading>
 */
class MeterReadingFactory extends Factory
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
            'service_id' => fn (array $attributes): int => Service::factory()->create(['landlord_id' => Room::query()->findOrFail($attributes['room_id'])->property->landlord_id])->id,
            'billing_period' => today()->format('Y-m'),
            'previous_value' => '100.000',
            'current_value' => '125.500',
            'usage' => '25.500',
            'recorded_by' => fn (array $attributes): int => Room::query()->findOrFail($attributes['room_id'])->property->landlord_id,
            'recorded_at' => now(),
            'notes' => null,
        ];
    }
}
