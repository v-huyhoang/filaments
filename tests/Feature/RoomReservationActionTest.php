<?php

namespace Tests\Feature;

use App\Actions\Reservations\ConvertRoomReservationAction;
use App\Actions\Reservations\CreateRoomReservationAction;
use App\Enums\ContractStatus;
use App\Enums\RoomReservationStatus;
use App\Models\Property;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RoomReservationActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_paid_room_hold(): void
    {
        [$landlord, $room, $tenant] = $this->landlordRoomAndTenant();

        $reservation = app(CreateRoomReservationAction::class)->handle($landlord, $this->reservationData($room, $tenant));

        $this->assertDatabaseHas('room_reservations', [
            'id' => $reservation->id,
            'room_id' => $room->id,
            'tenant_id' => $tenant->id,
            'hold_amount' => 500_000,
            'status' => RoomReservationStatus::Held->value,
        ]);
    }

    public function test_it_requires_a_hold_payment_timestamp(): void
    {
        [$landlord, $room, $tenant] = $this->landlordRoomAndTenant();

        $this->expectException(ValidationException::class);

        app(CreateRoomReservationAction::class)->handle($landlord, $this->reservationData($room, $tenant, ['paid_at' => null]));
    }

    public function test_it_rejects_a_tenant_from_another_landlord(): void
    {
        [$landlord, $room] = $this->landlordRoomAndTenant();
        $anotherTenant = Tenant::factory()->for(User::factory(), 'landlord')->create();

        $this->expectException(ValidationException::class);

        app(CreateRoomReservationAction::class)->handle($landlord, $this->reservationData($room, $anotherTenant));
    }

    public function test_it_rejects_a_second_live_hold_for_the_same_room(): void
    {
        [$landlord, $room, $tenant] = $this->landlordRoomAndTenant();
        $anotherTenant = Tenant::factory()->for($landlord, 'landlord')->create();
        app(CreateRoomReservationAction::class)->handle($landlord, $this->reservationData($room, $tenant));

        $this->expectException(ValidationException::class);

        app(CreateRoomReservationAction::class)->handle($landlord, $this->reservationData($room, $anotherTenant));
    }

    public function test_it_converts_a_live_hold_into_a_scheduled_contract(): void
    {
        [$landlord, $room, $tenant] = $this->landlordRoomAndTenant();
        $reservation = app(CreateRoomReservationAction::class)->handle($landlord, $this->reservationData($room, $tenant));

        $contract = app(ConvertRoomReservationAction::class)->handle($landlord, $reservation, [
            'end_date' => today()->addYear()->toDateString(),
            'rent_amount' => 3_000_000,
            'deposit_amount' => 3_000_000,
            'payment_due_day' => 5,
        ]);

        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'room_reservation_id' => $reservation->id,
            'status' => ContractStatus::Scheduled->value,
        ]);
        $this->assertDatabaseHas('room_reservations', [
            'id' => $reservation->id,
            'status' => RoomReservationStatus::Converted->value,
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
    private function reservationData(Room $room, Tenant $tenant, array $overrides = []): array
    {
        return [
            'room_id' => $room->id,
            'tenant_id' => $tenant->id,
            'expected_move_in_date' => today()->addWeek()->toDateString(),
            'hold_amount' => 500_000,
            'paid_at' => now()->toDateTimeString(),
            'payment_method' => 'bank_transfer',
            'expires_at' => now()->addDays(3)->toDateTimeString(),
            ...$overrides,
        ];
    }
}
