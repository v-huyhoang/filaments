<?php

namespace Tests\Feature;

use App\Filament\Resources\MeterReadings\MeterReadingResource;
use App\Filament\Resources\MeterReadings\Pages\CreateMeterReading;
use App\Filament\Resources\MeterReadings\Pages\ListMeterReadings;
use App\Models\MeterReading;
use App\Models\PropertyService;
use App\Models\Room;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MeterReadingResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_page_loads_owned_rooms_without_searching(): void
    {
        $room = Room::factory()->create();
        Room::factory()->create();
        $this->actingAs($room->property->landlord);

        Livewire::test(CreateMeterReading::class)
            ->assertFormFieldExists('room_id', fn (Select $field): bool => $field->getOptions() === [
                $room->id => $room->property->name.' / '.$room->room_number,
            ]);
    }

    public function test_create_page_loads_metered_services_after_selecting_room(): void
    {
        $room = Room::factory()->create();
        $configuration = PropertyService::factory()->for($room->property)->create(['effective_from' => today()->startOfMonth()]);
        PropertyService::factory()->create();
        $this->actingAs($room->property->landlord);

        Livewire::test(CreateMeterReading::class)
            ->fillForm(['room_id' => $room->id])
            ->assertFormFieldExists('service_id', fn (Select $field): bool => $field->getOptions() === [
                $configuration->service_id => $configuration->service->name,
            ]);
    }

    public function test_list_and_route_binding_are_landlord_scoped(): void
    {
        $owned = MeterReading::factory()->create();
        $foreign = MeterReading::factory()->create();
        $this->actingAs($owned->room->property->landlord);
        $this->assertSame([$owned->id], MeterReadingResource::getEloquentQuery()->pluck('id')->all());
        $this->assertNull(MeterReadingResource::getRecordRouteBindingEloquentQuery()->find($foreign->id));
        Livewire::test(ListMeterReadings::class)->assertCanSeeTableRecords([$owned])->assertCanNotSeeTableRecords([$foreign]);
    }

    public function test_create_page_records_usage_with_authenticated_owner(): void
    {
        $room = Room::factory()->create();
        $configuration = PropertyService::factory()->for($room->property)->create(['effective_from' => today()->startOfMonth()]);
        $this->actingAs($room->property->landlord);
        Livewire::test(CreateMeterReading::class)->fillForm([
            'room_id' => $room->id, 'service_id' => $configuration->service_id, 'billing_period' => today()->format('Y-m'),
            'previous_value' => 100, 'current_value' => 125,
        ])->call('create')->assertHasNoFormErrors();
        $this->assertDatabaseHas('meter_readings', ['room_id' => $room->id, 'service_id' => $configuration->service_id, 'usage' => 25, 'recorded_by' => $room->property->landlord_id]);
    }

    public function test_create_page_rejects_foreign_room(): void
    {
        $room = Room::factory()->create();
        $foreign = Room::factory()->create();
        $configuration = PropertyService::factory()->for($room->property)->create();
        $this->actingAs($room->property->landlord);
        Livewire::test(CreateMeterReading::class)->fillForm([
            'room_id' => $foreign->id, 'service_id' => $configuration->service_id, 'billing_period' => today()->format('Y-m'),
            'previous_value' => 100, 'current_value' => 125,
        ])->call('create')->assertHasFormErrors(['room_id']);
        $this->assertDatabaseCount('meter_readings', 0);
    }

    public function test_guest_cannot_access_readings(): void
    {
        $this->get(MeterReadingResource::getUrl())->assertRedirect();
    }

    public function test_list_filters_by_billing_month(): void
    {
        $owned = MeterReading::factory()->create();
        $older = MeterReading::factory()->for($owned->room)->for($owned->service)->create(['billing_period' => today()->subMonthNoOverflow()->format('Y-m')]);
        $this->actingAs($owned->room->property->landlord);
        Livewire::test(ListMeterReadings::class)->filterTable('billing_period', ['billing_period' => $owned->billing_period])
            ->assertCanSeeTableRecords([$owned])->assertCanNotSeeTableRecords([$older]);
    }
}
