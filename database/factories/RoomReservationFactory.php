<?php

namespace Database\Factories;

use App\Enums\RoomReservationStatus;
use App\Models\Room;
use App\Models\RoomReservation;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomReservation>
 */
class RoomReservationFactory extends Factory
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
            'expected_move_in_date' => fake()->dateTimeBetween('+1 week', '+2 months')->format('Y-m-d'),
            'hold_amount' => fake()->numberBetween(100_000, 2_000_000),
            'paid_at' => now(),
            'payment_method' => 'bank_transfer',
            'payment_reference' => fake()->uuid(),
            'expires_at' => now()->addDays(3),
            'status' => RoomReservationStatus::Held,
        ];
    }
}
