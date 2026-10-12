<?php

namespace Tests\Feature;

use App\Actions\Contracts\ActivateContractAction;
use App\Actions\Contracts\CreateContractAction;
use App\Actions\Contracts\TerminateContractAction;
use App\Enums\ContractStatus;
use App\Enums\RoomStatus;
use App\Models\Contract;
use App\Models\Property;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ContractActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_scheduled_contract_with_rent_snapshots(): void
    {
        [$landlord, $room, $tenant] = $this->landlordRoomAndTenant();

        $contract = app(CreateContractAction::class)->handle($landlord, $this->contractData($room, $tenant));

        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'room_id' => $room->id,
            'tenant_id' => $tenant->id,
            'rent_amount' => 3_000_000,
            'deposit_amount' => 3_000_000,
            'status' => ContractStatus::Scheduled->value,
        ]);
    }

    public function test_it_rejects_an_invalid_contract_date_range(): void
    {
        [$landlord, $room, $tenant] = $this->landlordRoomAndTenant();

        $this->expectException(ValidationException::class);

        app(CreateContractAction::class)->handle($landlord, $this->contractData($room, $tenant, [
            'start_date' => today()->addMonth()->toDateString(),
            'end_date' => today()->toDateString(),
        ]));
    }

    public function test_it_rejects_a_tenant_from_another_landlord(): void
    {
        [$landlord, $room] = $this->landlordRoomAndTenant();
        $anotherTenant = Tenant::factory()->for(User::factory(), 'landlord')->create();

        $this->expectException(ValidationException::class);

        app(CreateContractAction::class)->handle($landlord, $this->contractData($room, $anotherTenant));
    }

    public function test_it_rejects_an_overlapping_committed_contract(): void
    {
        [$landlord, $room, $tenant] = $this->landlordRoomAndTenant();
        $anotherTenant = Tenant::factory()->for($landlord, 'landlord')->create();

        Contract::factory()->create([
            'room_id' => $room->id,
            'tenant_id' => $tenant->id,
            'start_date' => today()->toDateString(),
            'end_date' => today()->addMonth()->toDateString(),
            'status' => ContractStatus::Active,
        ]);

        $this->expectException(ValidationException::class);

        app(CreateContractAction::class)->handle($landlord, $this->contractData($room, $anotherTenant));
    }

    public function test_activation_marks_the_room_as_occupied(): void
    {
        [$landlord, $room, $tenant] = $this->landlordRoomAndTenant();
        $contract = app(CreateContractAction::class)->handle($landlord, $this->contractData($room, $tenant));

        app(ActivateContractAction::class)->handle($landlord, $contract);

        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'status' => ContractStatus::Active->value,
        ]);
        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
            'status' => RoomStatus::OCCUPIED->value,
        ]);
    }

    public function test_termination_restores_room_availability_when_no_active_contract_remains(): void
    {
        [$landlord, $room, $tenant] = $this->landlordRoomAndTenant();
        $contract = app(CreateContractAction::class)->handle($landlord, $this->contractData($room, $tenant));
        app(ActivateContractAction::class)->handle($landlord, $contract);

        app(TerminateContractAction::class)->handle($landlord, $contract);

        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'status' => ContractStatus::Terminated->value,
        ]);
        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
            'status' => RoomStatus::AVAILABLE->value,
        ]);
    }

    /**
     * @return array{User, Room, Tenant}
     */
    private function landlordRoomAndTenant(): array
    {
        $landlord = User::factory()->create();
        $property = Property::factory()->for($landlord, 'landlord')->create();
        $room = Room::factory()->for($property)->create();
        $tenant = Tenant::factory()->for($landlord, 'landlord')->create();

        return [$landlord, $room, $tenant];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function contractData(Room $room, Tenant $tenant, array $overrides = []): array
    {
        return [
            'room_id' => $room->id,
            'tenant_id' => $tenant->id,
            'start_date' => today()->toDateString(),
            'end_date' => today()->addYear()->toDateString(),
            'rent_amount' => 3_000_000,
            'deposit_amount' => 3_000_000,
            'payment_due_day' => 5,
            'status' => ContractStatus::Scheduled->value,
            ...$overrides,
        ];
    }
}
