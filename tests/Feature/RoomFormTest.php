<?php

namespace Tests\Feature;

use App\Filament\Resources\Rooms\Pages\CreateRoom;
use App\Filament\Resources\Rooms\Pages\EditRoom;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RoomFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_room_for_a_property_owned_by_the_authenticated_landlord(): void
    {
        $landlord = User::factory()->create();
        $property = Property::factory()->for($landlord, 'landlord')->create();

        $this->actingAs($landlord);

        Livewire::test(CreateRoom::class)
            ->fillForm($this->roomData($property))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('rooms', [
            'property_id' => $property->id,
            'room_number' => '101',
        ]);
    }

    public function test_it_rejects_a_property_owned_by_another_landlord(): void
    {
        $landlord = User::factory()->create();
        $anotherLandlord = User::factory()->create();
        $anotherLandlordProperty = Property::factory()->for($anotherLandlord, 'landlord')->create();

        $this->actingAs($landlord);

        Livewire::test(CreateRoom::class)
            ->fillForm($this->roomData($anotherLandlordProperty))
            ->call('create')
            ->assertHasFormErrors(['property_id']);

        $this->assertDatabaseMissing('rooms', [
            'property_id' => $anotherLandlordProperty->id,
            'room_number' => '101',
        ]);
    }

    public function test_it_rejects_a_soft_deleted_property(): void
    {
        $landlord = User::factory()->create();
        $property = Property::factory()->for($landlord, 'landlord')->create();
        $property->delete();

        $this->actingAs($landlord);

        Livewire::test(CreateRoom::class)
            ->fillForm($this->roomData($property))
            ->call('create')
            ->assertHasFormErrors(['property_id']);

        $this->assertDatabaseMissing('rooms', [
            'property_id' => $property->id,
            'room_number' => '101',
        ]);
    }

    public function test_it_rejects_a_duplicate_room_number_within_the_same_property(): void
    {
        $landlord = User::factory()->create();
        $property = Property::factory()->for($landlord, 'landlord')->create();
        Room::factory()->for($property)->create(['room_number' => '101']);

        $this->actingAs($landlord);

        Livewire::test(CreateRoom::class)
            ->fillForm($this->roomData($property))
            ->call('create')
            ->assertHasFormErrors(['room_number' => 'unique']);

        $this->assertDatabaseCount('rooms', 1);
    }

    public function test_it_allows_the_same_room_number_in_different_properties(): void
    {
        $landlord = User::factory()->create();
        $property = Property::factory()->for($landlord, 'landlord')->create();
        $anotherProperty = Property::factory()->for($landlord, 'landlord')->create();
        Room::factory()->for($property)->create(['room_number' => '101']);

        $this->actingAs($landlord);

        Livewire::test(CreateRoom::class)
            ->fillForm($this->roomData($anotherProperty))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('rooms', [
            'property_id' => $anotherProperty->id,
            'room_number' => '101',
        ]);
    }

    public function test_it_allows_a_room_to_keep_its_current_number_when_editing(): void
    {
        $landlord = User::factory()->create();
        $property = Property::factory()->for($landlord, 'landlord')->create();
        $room = Room::factory()->for($property)->create(['room_number' => '101']);

        $this->actingAs($landlord);

        Livewire::test(EditRoom::class, ['record' => $room->getKey()])
            ->fillForm($this->roomData($property))
            ->call('save')
            ->assertHasNoFormErrors();
    }

    public function test_it_rejects_invalid_room_values(): void
    {
        $landlord = User::factory()->create();
        $property = Property::factory()->for($landlord, 'landlord')->create();
        $roomData = [
            ...$this->roomData($property),
            'floor' => 0,
            'area' => -1,
            'base_rent' => -1,
            'deposit_amount' => -1,
            'max_occupants' => 0,
        ];

        $this->actingAs($landlord);

        Livewire::test(CreateRoom::class)
            ->fillForm($roomData)
            ->call('create')
            ->assertHasFormErrors([
                'floor' => 'min',
                'area' => 'min',
                'base_rent' => 'min',
                'deposit_amount' => 'min',
                'max_occupants' => 'min',
            ]);
    }

    /**
     * @return array<string, int|string>
     */
    private function roomData(Property $property): array
    {
        return [
            'property_id' => $property->id,
            'room_number' => '101',
            'floor' => 1,
            'area' => 25,
            'base_rent' => 3_000_000,
            'deposit_amount' => 3_000_000,
            'max_occupants' => 2,
            'status' => 'available',
        ];
    }
}
