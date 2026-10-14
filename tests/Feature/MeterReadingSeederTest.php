<?php

namespace Tests\Feature;

use App\Models\MeterReading;
use App\Models\PropertyService;
use App\Models\Room;
use App\Models\Service;
use Database\Seeders\MeterReadingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeterReadingSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_owned_metered_services_and_can_run_twice_without_duplicates(): void
    {
        $room = Room::factory()->create();
        $configuration = PropertyService::factory()->for($room->property)->create();
        $this->seed(MeterReadingSeeder::class);
        $this->seed(MeterReadingSeeder::class);
        $this->assertDatabaseCount('meter_readings', 1);
        $this->assertDatabaseHas('meter_readings', [
            'room_id' => $room->id, 'service_id' => $configuration->service_id, 'billing_period' => today()->format('Y-m'),
            'previous_value' => 100, 'current_value' => 125, 'usage' => 25, 'recorded_by' => $room->property->landlord_id,
        ]);
    }

    public function test_continues_previous_reading_without_changing_history(): void
    {
        $room = Room::factory()->create();
        $configuration = PropertyService::factory()->for($room->property)->create();
        $previous = MeterReading::factory()->for($room)->for($configuration->service)->create([
            'billing_period' => today()->subMonthNoOverflow()->format('Y-m'), 'current_value' => '125.500',
        ]);
        $this->seed(MeterReadingSeeder::class);
        $this->assertSame('125.500', $previous->fresh()->current_value);
        $this->assertDatabaseHas('meter_readings', ['billing_period' => today()->format('Y-m'), 'previous_value' => '125.500', 'current_value' => '150.500']);
    }

    public function test_skips_ineligible_services_and_deleted_rooms(): void
    {
        $room = Room::factory()->create();
        PropertyService::factory()->for($room->property)->disabled()->create();
        $fixed = Service::factory()->for($room->property->landlord, 'landlord')->fixed()->create();
        PropertyService::factory()->for($room->property)->for($fixed)->create();
        $inactive = Service::factory()->for($room->property->landlord, 'landlord')->inactive()->create();
        PropertyService::factory()->for($room->property)->for($inactive)->create();
        PropertyService::factory()->for($room->property)->for(Service::factory()->create())->create();
        $deletedRoom = Room::factory()->create();
        PropertyService::factory()->for($deletedRoom->property)->create();
        $deletedRoom->delete();
        $this->seed(MeterReadingSeeder::class);
        $this->assertDatabaseCount('meter_readings', 0);
    }

    public function test_skips_room_service_with_later_recorded_period(): void
    {
        $room = Room::factory()->create();
        $configuration = PropertyService::factory()->for($room->property)->create();
        MeterReading::factory()->for($room)->for($configuration->service)->create(['billing_period' => today()->addMonthNoOverflow()->format('Y-m')]);
        $this->seed(MeterReadingSeeder::class);
        $this->assertDatabaseCount('meter_readings', 1);
    }

    public function test_refuses_demo_data_in_production(): void
    {
        $this->app['env'] = 'production';
        $this->expectException(\LogicException::class);
        $this->seed(MeterReadingSeeder::class);
    }
}
