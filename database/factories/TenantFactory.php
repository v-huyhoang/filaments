<?php

namespace Database\Factories;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'landlord_id' => User::factory(),
            'full_name' => fake()->name(),
            'phone' => fake()->numerify('0#########'),
            'email' => fake()->optional()->safeEmail(),
            'date_of_birth' => fake()->optional()->dateTimeBetween('-80 years', '-18 years'),
            'identity_number' => fake()->optional()->numerify('############'),
            'identity_issued_date' => fake()->optional()->dateTimeBetween('-20 years', 'now'),
            'identity_issued_place' => fake()->optional()->city(),
            'address' => fake()->optional()->address(),
            'emergency_contact_name' => fake()->optional()->name(),
            'emergency_contact_phone' => fake()->optional()->numerify('0#########'),
            'status' => TenantStatus::Active,
            'notes' => fake()->optional()->paragraph(),
        ];
    }
}
