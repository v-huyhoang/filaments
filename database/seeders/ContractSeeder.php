<?php

namespace Database\Seeders;

use App\Actions\Contracts\ActivateContractAction;
use App\Actions\Contracts\CreateContractAction;
use App\Enums\ContractStatus;
use App\Enums\RoomStatus;
use App\Enums\TenantStatus;
use App\Models\Contract;
use App\Models\Property;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ContractSeeder extends Seeder
{
    public function run(): void
    {
        $landlord = User::query()->firstOrCreate(
            ['email' => 'contract-seeder@example.test'],
            [
                'name' => 'Contract Seeder Landlord',
                'password' => Hash::make('password'),
            ],
        );

        $property = Property::query()->firstOrCreate(
            [
                'landlord_id' => $landlord->id,
                'name' => 'Contract Seeder Property',
            ],
            [
                'address' => '100 Contract Seeder Street',
                'is_active' => true,
            ],
        );

        $tenant = Tenant::query()->firstOrCreate(
            [
                'landlord_id' => $landlord->id,
                'identity_number' => '123456789012',
            ],
            [
                'full_name' => 'Contract Seeder Tenant',
                'phone' => '0900000001',
                'status' => TenantStatus::Active,
            ],
        );

        $activeRoom = $this->room($property, 'C-101');
        $scheduledRoom = $this->room($property, 'C-102');

        $this->seedActiveContract($landlord, $activeRoom, $tenant);
        $this->seedScheduledContract($landlord, $scheduledRoom, $tenant);
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

    private function seedActiveContract(User $landlord, Room $room, Tenant $tenant): void
    {
        $contract = Contract::query()
            ->where('room_id', $room->id)
            ->where('status', ContractStatus::Active)
            ->first();

        if (! $contract instanceof Contract) {
            $contract = app(CreateContractAction::class)->handle($landlord, [
                'room_id' => $room->id,
                'tenant_id' => $tenant->id,
                'start_date' => today()->toDateString(),
                'end_date' => today()->addYear()->toDateString(),
                'rent_amount' => 3_000_000,
                'deposit_amount' => 3_000_000,
                'payment_due_day' => 5,
                'status' => ContractStatus::Scheduled->value,
                'notes' => 'Seeded active contract.',
            ]);
        }

        if ($contract->status === ContractStatus::Scheduled) {
            app(ActivateContractAction::class)->handle($landlord, $contract);
        }
    }

    private function seedScheduledContract(User $landlord, Room $room, Tenant $tenant): void
    {
        if (Contract::query()->where('room_id', $room->id)->committed()->exists()) {
            return;
        }

        app(CreateContractAction::class)->handle($landlord, [
            'room_id' => $room->id,
            'tenant_id' => $tenant->id,
            'start_date' => today()->addMonth()->startOfMonth()->toDateString(),
            'end_date' => today()->addMonth()->startOfMonth()->addYear()->subDay()->toDateString(),
            'rent_amount' => 3_200_000,
            'deposit_amount' => 3_200_000,
            'payment_due_day' => 5,
            'status' => ContractStatus::Scheduled->value,
            'notes' => 'Seeded scheduled contract.',
        ]);
    }
}
