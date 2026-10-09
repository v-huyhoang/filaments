<?php

namespace Tests\Feature;

use App\Enums\RoomStatus;
use App\Models\Property;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_room_for_the_given_property(): void
    {
        $property = Property::factory()->create();

        $room = Room::factory()
            ->for($property)
            ->create();

        $this->assertModelExists($room);
        $this->assertSame($property->id, $room->property_id);
        $this->assertSame(RoomStatus::AVAILABLE, $room->status);

        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
            'property_id' => $property->id,
        ]);
    }
}
