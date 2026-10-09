<?php

namespace Database\Factories;

use App\Enums\RoomStatus;
use App\Models\Property;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'room_number' => fake()->unique()->bothify('P-###??'),
            'floor' => fake()->numberBetween(1, 10),
            'area' => fake()->randomFloat(2, 10, 150),
            'base_rent' => fake()->numberBetween(1_000_000, 10_000_000),
            'deposit_amount' => fake()->numberBetween(1_000_000, 10_000_000),
            'max_occupants' => fake()->numberBetween(1, 6),
            'status' => RoomStatus::AVAILABLE,
            'description' => fake()->optional()->paragraph(),
        ];
    }
}
