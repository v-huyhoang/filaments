<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyRoomRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_only_the_rooms_belonging_to_the_property(): void
    {
        $property = Property::factory()->create();
        $room = Room::factory()->for($property)->create();
        $anotherPropertyRoom = Room::factory()->create();

        $rooms = $property->fresh()->rooms;

        $this->assertCount(1, $rooms);
        $this->assertTrue($rooms->first()->is($room));
        $this->assertFalse($rooms->contains($anotherPropertyRoom));
    }
}
