<?php

namespace Database\Factories;

use App\Enums\ContractStatus;
use App\Enums\InvoiceStatus;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
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
            'contract_id' => function (array $attributes): int {
                $property = Property::factory()->create(['landlord_id' => $attributes['landlord_id']]);
                $room = Room::factory()->for($property)->create();
                $tenant = Tenant::factory()->create(['landlord_id' => $attributes['landlord_id']]);

                return Contract::factory()->for($room)->for($tenant)->create([
                    'status' => ContractStatus::Active, 'start_date' => today()->startOfMonth(), 'end_date' => today()->addYear(),
                ])->id;
            },
            'billing_period' => today()->format('Y-m'),
            'issued_at' => now(),
            'due_date' => today()->endOfMonth(),
            'subtotal' => '3000000.00',
            'total' => '3000000.00',
            'status' => InvoiceStatus::Draft,
            'snapshot' => ['tenant_name' => 'Test tenant', 'property_name' => 'Test property', 'room_number' => 'A101', 'occupant_count' => 1],
            'notes' => null,
        ];
    }
}
