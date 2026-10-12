<?php

namespace Database\Seeders;

use App\Actions\Reservations\CreateRoomReservationAction;
use App\Enums\RoomReservationStatus;
use App\Enums\RoomStatus;
use App\Enums\TenantStatus;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomReservation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RoomReservationSeeder extends Seeder
{
    public function run(): void
    {
        $landlord = User::query()->firstOrCreate(
            ['email' => 'reservation-seeder@example.test'],
            [
                'name' => 'Reservation Seeder Landlord',
                'password' => Hash::make('password'),
            ],
        );

        $property = Property::query()->firstOrCreate(
            [
                'landlord_id' => $landlord->id,
                'name' => 'Reservation Seeder Property',
            ],
            [
                'address' => '200 Reservation Seeder Street',
                'is_active' => true,
            ],
        );

        $tenant = Tenant::query()->firstOrCreate(
            [
                'landlord_id' => $landlord->id,
                'identity_number' => '123456789013',
            ],
            [
                'full_name' => 'Reservation Seeder Tenant',
                'phone' => '0900000002',
                'status' => TenantStatus::Active,
            ],
        );

        foreach (['R-101', 'R-102'] as $roomNumber) {
            $room = $this->room($property, $roomNumber);

            if (RoomReservation::query()
                ->where('room_id', $room->id)
                ->where('status', RoomReservationStatus::Held)
                ->where('payment_reference', "seeded-{$roomNumber}")
                ->exists()) {
                continue;
            }

            app(CreateRoomReservationAction::class)->handle($landlord, [
                'room_id' => $room->id,
                'tenant_id' => $tenant->id,
                'expected_move_in_date' => today()->addWeeks(2)->toDateString(),
                'hold_amount' => 500_000,
                'paid_at' => now()->toDateTimeString(),
                'payment_method' => 'bank_transfer',
                'payment_reference' => "seeded-{$roomNumber}",
                'expires_at' => now()->addDays(3)->toDateTimeString(),
                'notes' => 'Seeded paid room reservation.',
            ]);
        }
    }

    private function room(Property $property, string $roomNumber): Room
    {
        return Room::query()->firstOrCreate(
            [
                'property_id' => $property->id,
                'room_number' => $roomNumber,
            ],
            [
                'floor' => 1,
                'area' => 25,
                'base_rent' => 3_000_000,
                'deposit_amount' => 3_000_000,
                'max_occupants' => 2,
                'status' => RoomStatus::AVAILABLE,
            ],
        );
    }
}
