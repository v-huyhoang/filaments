<?php

namespace Tests\Feature;

use App\Filament\Resources\Contracts\Pages\CreateContract;
use App\Filament\Resources\Contracts\Pages\EditContract;
use App\Models\Contract;
use App\Models\Room;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContractRentFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_selecting_room_prefills_rent_and_switching_room_updates_suggestion(): void
    {
        $room = Room::factory()->create(['base_rent' => 3000000]);
        $other = Room::factory()->for($room->property)->create(['base_rent' => 3500000]);
        $this->actingAs($room->property->landlord);

        Livewire::test(CreateContract::class)
            ->fillForm(['room_id' => $room->id])->assertFormSet(['rent_amount' => '3000000.00'])
            ->fillForm(['room_id' => $other->id])->assertFormSet(['rent_amount' => '3500000.00'])
            ->fillForm(['room_id' => null])->assertFormSet(['rent_amount' => null]);
    }

    public function test_negotiated_rent_is_saved_without_changing_room_price(): void
    {
        $room = Room::factory()->create(['base_rent' => 3000000]);
        $tenant = Tenant::factory()->create(['landlord_id' => $room->property->landlord_id]);
        $this->actingAs($room->property->landlord);

        Livewire::test(CreateContract::class)->fillForm(['room_id' => $room->id])
            ->fillForm([
                'tenant_id' => $tenant->id, 'start_date' => today()->toDateString(),
                'rent_amount' => 2800000, 'payment_due_day' => 5, 'status' => 'draft',
            ])->call('create')->assertHasNoFormErrors();

        $this->assertDatabaseHas('contracts', ['room_id' => $room->id, 'rent_amount' => 2800000]);
        $this->assertDatabaseHas('rooms', ['id' => $room->id, 'base_rent' => 3000000]);
    }

    public function test_foreign_room_price_is_not_exposed_or_accepted(): void
    {
        $room = Room::factory()->create();
        $foreign = Room::factory()->create(['base_rent' => 9876543]);
        $tenant = Tenant::factory()->create(['landlord_id' => $room->property->landlord_id]);
        $this->actingAs($room->property->landlord);

        Livewire::test(CreateContract::class)->fillForm(['room_id' => $foreign->id])
            ->assertFormSet(['rent_amount' => null])
            ->fillForm([
                'tenant_id' => $tenant->id, 'start_date' => today()->toDateString(),
                'rent_amount' => 2800000, 'payment_due_day' => 5, 'status' => 'draft',
            ])->call('create')->assertHasFormErrors(['room_id']);

        $this->assertDatabaseCount('contracts', 0);
    }

    public function test_editing_draft_preserves_agreed_rent_even_when_room_price_changes(): void
    {
        $room = Room::factory()->create(['base_rent' => 3000000]);
        $other = Room::factory()->for($room->property)->create(['base_rent' => 3500000]);
        $tenant = Tenant::factory()->create(['landlord_id' => $room->property->landlord_id]);
        $contract = Contract::factory()->for($room)->for($tenant)->create(['rent_amount' => 2800000]);
        $room->update(['base_rent' => 3200000]);
        $this->actingAs($room->property->landlord);

        Livewire::test(EditContract::class, ['record' => $contract->id])
            ->assertFormSet(['rent_amount' => '2800000.00'])
            ->fillForm(['room_id' => $other->id])->assertFormSet(['rent_amount' => '2800000.00'])
            ->call('save')->assertHasNoFormErrors();

        $this->assertSame('2800000.00', $contract->fresh()->rent_amount);
    }

    public function test_zero_room_price_remains_valid_suggestion(): void
    {
        $room = Room::factory()->create(['base_rent' => 0]);
        $this->actingAs($room->property->landlord);

        Livewire::test(CreateContract::class)->fillForm(['room_id' => $room->id])
            ->assertFormSet(['rent_amount' => '0.00']);
    }
}
