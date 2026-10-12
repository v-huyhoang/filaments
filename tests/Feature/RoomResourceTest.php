<?php

namespace Tests\Feature;

use App\Filament\Resources\Rooms\RoomResource;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_only_lists_rooms_belonging_to_the_authenticated_landlord(): void
    {
        $landlord = User::factory()->create();
        $anotherLandlord = User::factory()->create();
        $ownedRoom = Room::factory()
            ->for(Property::factory()->for($landlord, 'landlord'))
            ->create();
        $anotherLandlordRoom = Room::factory()
            ->for(Property::factory()->for($anotherLandlord, 'landlord'))
            ->create();

        $this->actingAs($landlord);

        $roomIds = RoomResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($ownedRoom->id, $roomIds);
        $this->assertNotContains($anotherLandlordRoom->id, $roomIds);
    }

    public function test_it_cannot_resolve_another_landlords_room_from_the_record_route_query(): void
    {
        $landlord = User::factory()->create();
        $anotherLandlord = User::factory()->create();
        $anotherLandlordRoom = Room::factory()
            ->for(Property::factory()->for($anotherLandlord, 'landlord'))
            ->create();

        $this->actingAs($landlord);

        $room = RoomResource::getRecordRouteBindingEloquentQuery()
            ->find($anotherLandlordRoom->id);

        $this->assertNull($room);
    }
}
