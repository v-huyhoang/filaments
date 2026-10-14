<?php

namespace Tests\Feature;

use App\Actions\Contracts\ActivateContractAction;
use App\Actions\Contracts\TerminateContractAction;
use App\Actions\Contracts\UpdateDraftContractAction;
use App\Actions\Rooms\SaveRoomAction;
use App\Enums\ContractStatus;
use App\Enums\RoomStatus;
use App\Filament\Resources\Contracts\Pages\EditContract;
use App\Filament\Resources\Rooms\Pages\EditRoom;
use App\Models\Contract;
use App\Models\Property;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class RentalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_edit_draft_schedule_it_and_check_in(): void
    {
        $this->freezeTime();
        $contract = $this->draft();
        $this->actingAs($contract->room->property->landlord);
        Livewire::test(EditContract::class, ['record' => $contract->id])->fillForm([
            'rent_amount' => 2500000, 'status' => 'scheduled',
        ])->call('save')->assertHasNoFormErrors();
        $this->assertDatabaseHas('contracts', ['id' => $contract->id, 'rent_amount' => 2500000, 'status' => 'scheduled']);
        app(ActivateContractAction::class)->handle($contract->room->property->landlord, $contract->fresh());
        $this->assertSame(RoomStatus::OCCUPIED, $contract->room->fresh()->status);
    }

    public function test_foreign_landlord_cannot_edit_draft(): void
    {
        $contract = $this->draft();
        $this->expectException(AuthorizationException::class);
        app(UpdateDraftContractAction::class)->handle(User::factory()->create(), $contract, $this->data($contract));
    }

    #[TestWith(['room_id'])]
    #[TestWith(['tenant_id'])]
    public function test_draft_cannot_be_reassigned_to_foreign_records(string $field): void
    {
        $contract = $this->draft();
        $foreignId = $field === 'room_id' ? Room::factory()->create()->id : Tenant::factory()->create()->id;
        try {
            app(UpdateDraftContractAction::class)->handle($contract->room->property->landlord, $contract, [...$this->data($contract), $field => $foreignId]);
            $this->fail('Foreign reference was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
        $this->assertSame(ContractStatus::Draft, $contract->fresh()->status);
    }

    public function test_scheduling_draft_rechecks_overlap_and_rolls_back(): void
    {
        $contract = $this->draft();
        Contract::factory()->active()->for($contract->room)->create();
        try {
            app(UpdateDraftContractAction::class)->handle($contract->room->property->landlord, $contract, [...$this->data($contract), 'status' => 'scheduled']);
            $this->fail('Overlap was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('start_date', $exception->errors());
        }
        $this->assertSame(ContractStatus::Draft, $contract->fresh()->status);
    }

    public function test_active_contract_cannot_be_edited_as_a_draft(): void
    {
        $contract = Contract::factory()->active()->create();
        $this->actingAs($contract->room->property->landlord);
        Livewire::test(EditContract::class, ['record' => $contract->id])->assertForbidden();
    }

    public function test_activation_on_inclusive_end_date_succeeds(): void
    {
        $this->freezeTime();
        $contract = $this->draft();
        $contract->update(['status' => 'scheduled', 'end_date' => today()]);
        app(ActivateContractAction::class)->handle($contract->room->property->landlord, $contract);
        $this->assertSame(ContractStatus::Active, $contract->fresh()->status);
    }

    public function test_previous_active_contract_must_be_checked_out_even_if_dates_do_not_overlap(): void
    {
        $this->freezeTime();
        $contract = $this->draft();
        $contract->update(['status' => 'scheduled']);
        Contract::factory()->active()->for($contract->room)->create(['start_date' => today()->subMonths(2), 'end_date' => today()->subDay()]);
        $this->expectException(ValidationException::class);
        app(ActivateContractAction::class)->handle($contract->room->property->landlord, $contract);
    }

    #[TestWith(['2026-10-15 12:00:00'])]
    #[TestWith(['2026-09-30 12:00:00'])]
    public function test_checkout_rejects_future_or_pre_tenancy_dates(string $date): void
    {
        $this->travelTo(now()->setDate(2026, 10, 14)->setTime(12, 0));
        $contract = Contract::factory()->active()->create();
        try {
            app(TerminateContractAction::class)->handle($contract->room->property->landlord, $contract, $date);
            $this->fail('Invalid check-out was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('terminated_at', $exception->errors());
        }
        $this->assertSame(ContractStatus::Active, $contract->fresh()->status);
    }

    #[TestWith(['available'])]
    #[TestWith(['maintenance'])]
    #[TestWith(['inactive'])]
    public function test_occupied_room_status_cannot_bypass_checkout(string $status): void
    {
        $contract = Contract::factory()->active()->create();
        $room = $contract->room;
        $room->update(['status' => 'occupied']);
        try {
            app(SaveRoomAction::class)->handle($room->property->landlord, [...$room->getAttributes(), 'status' => $status], $room);
            $this->fail('Occupancy was changed without check-out.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
        $this->assertSame(RoomStatus::OCCUPIED, $room->fresh()->status);
    }

    public function test_room_cannot_be_marked_occupied_without_an_active_contract(): void
    {
        $room = Room::factory()->create();
        $this->actingAs($room->property->landlord);
        Livewire::test(EditRoom::class, ['record' => $room->id])->fillForm(['status' => 'occupied'])->call('save')->assertHasFormErrors(['status']);
        $this->assertSame(RoomStatus::AVAILABLE, $room->fresh()->status);
    }

    public function test_room_with_rental_history_cannot_move_property(): void
    {
        $contract = $this->draft();
        $room = $contract->room;
        $other = Property::factory()->for($room->property->landlord, 'landlord')->create();
        $this->expectException(ValidationException::class);
        app(SaveRoomAction::class)->handle($room->property->landlord, [...$room->getAttributes(), 'property_id' => $other->id], $room);
    }

    public function test_foreign_landlord_cannot_update_room_through_action(): void
    {
        $room = Room::factory()->create();
        $this->expectException(AuthorizationException::class);
        app(SaveRoomAction::class)->handle(User::factory()->create(), $room->getAttributes(), $room);
    }

    private function draft(): Contract
    {
        return Contract::factory()->active()->create(['status' => ContractStatus::Draft, 'payment_due_day' => 5]);
    }

    /** @return array<string, mixed> */
    private function data(Contract $contract): array
    {
        return [...$contract->getAttributes(), 'start_date' => $contract->start_date->toDateString(), 'end_date' => $contract->end_date?->toDateString()];
    }
}
