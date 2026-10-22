<?php

namespace Tests\Feature;

use App\Actions\MeterReadings\RecordMeterReadingAction;
use App\Actions\MeterReadings\RecordPropertyMeterReadingsAction;
use App\Enums\BillingMethod;
use App\Filament\Resources\MeterReadings\MeterReadingResource;
use App\Filament\Resources\MeterReadings\Pages\RecordPropertyMeterReadings;
use App\Models\Contract;
use App\Models\ContractService;
use App\Models\MeterReading;
use App\Models\Property;
use App\Models\PropertyService;
use App\Models\Room;
use App\Models\Service;
use App\Models\User;
use App\Services\PropertyMeterReadingInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PropertyMeterReadingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-22 10:00:00');
    }

    public function test_page_saves_multiple_rooms_and_services_with_exact_usage(): void
    {
        [$room, $configuration] = $this->configuredRoom();
        $otherRoom = Room::factory()->for($room->property)->create();
        $water = PropertyService::factory()->for($room->property)->create();
        $this->actingAs($room->property->landlord);
        $electricityKey = $room->id.'_'.$configuration->service_id;
        $waterKey = $otherRoom->id.'_'.$water->service_id;

        Livewire::test(RecordPropertyMeterReadings::class)
            ->fillForm(['property_id' => $room->property_id, 'billing_period' => '2026-10'])->call('loadReadings')
            ->set('readings.'.$electricityKey, ['previous_value' => '100.100', 'current_value' => '125.300'])
            ->set('readings.'.$waterKey, ['previous_value' => '5', 'current_value' => '8'])
            ->call('save')->assertHasNoErrors()
            ->assertSet('rooms.'.$room->id.'.meters.'.$configuration->service_id.'.recorded', true);

        $this->assertDatabaseCount('meter_readings', 2);
        $this->assertDatabaseHas('meter_readings', [
            'room_id' => $room->id, 'service_id' => $configuration->service_id,
            'billing_period' => '2026-10', 'usage' => '25.200', 'recorded_by' => $room->property->landlord_id,
        ]);
        $this->assertDatabaseHas('meter_readings', ['room_id' => $otherRoom->id, 'service_id' => $water->service_id, 'usage' => 3]);
    }

    public function test_invalid_row_rolls_back_entire_batch_and_keeps_inputs(): void
    {
        [$room, $configuration] = $this->configuredRoom();
        $otherRoom = Room::factory()->for($room->property)->create();
        $this->actingAs($room->property->landlord);
        $firstKey = $room->id.'_'.$configuration->service_id;
        $secondKey = $otherRoom->id.'_'.$configuration->service_id;

        Livewire::test(RecordPropertyMeterReadings::class)
            ->fillForm(['property_id' => $room->property_id, 'billing_period' => '2026-10'])->call('loadReadings')
            ->set('readings.'.$firstKey, ['previous_value' => '0', 'current_value' => '10'])
            ->set('readings.'.$secondKey, ['previous_value' => '20', 'current_value' => '10'])
            ->call('save')->assertHasErrors(['readings.'.$secondKey.'.current_value'])
            ->assertSee(__('meter_readings.errors.decreasing_value'))->assertSet('readings.'.$firstKey.'.current_value', '10');

        $this->assertDatabaseCount('meter_readings', 0);
    }

    public function test_existing_readings_are_displayed_without_being_overwritten(): void
    {
        [$room, $configuration] = $this->configuredRoom();
        $this->record($room, $configuration, '2026-10', '10', '20');
        $otherRoom = Room::factory()->for($room->property)->create();
        $this->actingAs($room->property->landlord);
        $key = $room->id.'_'.$configuration->service_id;
        $otherKey = $otherRoom->id.'_'.$configuration->service_id;

        Livewire::test(RecordPropertyMeterReadings::class)
            ->fillForm(['property_id' => $room->property_id, 'billing_period' => '2026-10'])->call('loadReadings')
            ->assertSet('rooms.'.$room->id.'.meters.'.$configuration->service_id.'.recorded', true)
            ->assertSee(__('meter_readings.bulk.recorded'))
            ->set('readings.'.$key, ['previous_value' => '0', 'current_value' => '999'])
            ->set('readings.'.$otherKey, ['previous_value' => '0', 'current_value' => '5'])->call('save')->assertHasNoErrors();

        $this->assertDatabaseCount('meter_readings', 2);
        $this->assertDatabaseHas('meter_readings', ['room_id' => $room->id, 'current_value' => 20]);
    }

    public function test_loader_carries_previous_value_and_honors_room_billing_overrides(): void
    {
        [$room, $configuration] = $this->configuredRoom();
        $otherRoom = Room::factory()->for($room->property)->create();
        $contract = Contract::factory()->for($otherRoom)->active()->create();
        ContractService::factory()->for($contract)->for($configuration->service)->create();
        $this->record($room, $configuration, '2026-09', '10', '30');
        $fixedService = Service::factory()->fixed()->create(['landlord_id' => $room->property->landlord_id]);
        PropertyService::factory()->for($room->property)->for($fixedService)->create();
        PropertyService::factory()->for($room->property)->disabled()->create();

        $data = app(PropertyMeterReadingInput::class)->load($room->property->landlord, $room->property_id, '2026-10');

        $this->assertSame([$configuration->service_id => $configuration->service->name], $data['services']);
        $this->assertSame('30.000', $data['readings'][$room->id.'_'.$configuration->service_id]['previous_value']);
        $this->assertSame([], $data['rooms'][$otherRoom->id]['meters']);
    }

    public function test_returning_to_metered_billing_requires_fresh_opening_value(): void
    {
        [$room, $configuration] = $this->configuredRoom();
        $contract = Contract::factory()->for($room)->active()->create(['start_date' => '2026-08-01']);
        $this->record($room, $configuration, '2026-08', '10', '20');
        ContractService::factory()->for($contract)->for($configuration->service)->create(['effective_from' => '2026-09-01']);
        ContractService::factory()->for($contract)->for($configuration->service)->create([
            'effective_from' => '2026-10-01', 'billing_method' => BillingMethod::Metered, 'metered_unit' => 'kWh',
        ]);

        $data = app(PropertyMeterReadingInput::class)->load($room->property->landlord, $room->property_id, '2026-10');

        $this->assertNull($data['readings'][$room->id.'_'.$configuration->service_id]['previous_value']);
    }

    public function test_changing_month_clears_loaded_rows_and_blocks_stale_save(): void
    {
        [$room] = $this->configuredRoom();
        $this->actingAs($room->property->landlord);

        Livewire::test(RecordPropertyMeterReadings::class)
            ->fillForm(['property_id' => $room->property_id, 'billing_period' => '2026-10'])->call('loadReadings')
            ->set('data.billing_period', '2026-11')->assertSet('rooms', [])->assertSet('loadedPeriod', null)
            ->call('save')->assertHasErrors(['readings'])->assertSee(__('meter_readings.bulk.reload'));

        $this->assertDatabaseCount('meter_readings', 0);
    }

    public function test_page_rejects_foreign_property_selection(): void
    {
        [$room] = $this->configuredRoom();
        $foreign = Property::factory()->create();
        $this->actingAs($room->property->landlord);

        Livewire::test(RecordPropertyMeterReadings::class)
            ->set('data.property_id', $foreign->id)->call('loadReadings')->assertHasFormErrors(['property_id']);
    }

    public function test_batch_rejects_room_from_another_property_of_same_landlord(): void
    {
        [$room, $configuration] = $this->configuredRoom();
        $otherProperty = Property::factory()->create(['landlord_id' => $room->property->landlord_id]);
        $otherRoom = Room::factory()->for($otherProperty)->create();

        try {
            app(RecordPropertyMeterReadingsAction::class)->handle($room->property->landlord, [
                'property_id' => $room->property_id, 'billing_period' => '2026-10',
                'readings' => ['foreign' => ['room_id' => $otherRoom->id, 'service_id' => $configuration->service_id, 'previous_value' => '0', 'current_value' => '1']],
            ]);
            $this->fail('Expected room validation to reject another property.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('readings.foreign.room_id', $exception->errors());
        }

        $this->assertDatabaseCount('meter_readings', 0);
    }

    #[TestWith(['-1', 'current_value'])]
    #[TestWith(['1.1234', 'current_value'])]
    #[TestWith(['invalid', 'current_value'])]
    #[TestWith(['1000000000', 'current_value'])]
    #[TestWith(['', 'readings'])]
    public function test_batch_rejects_invalid_or_empty_values(string $value, string $field): void
    {
        [$room, $configuration] = $this->configuredRoom();
        $this->actingAs($room->property->landlord);
        $key = $room->id.'_'.$configuration->service_id;

        Livewire::test(RecordPropertyMeterReadings::class)
            ->fillForm(['property_id' => $room->property_id, 'billing_period' => '2026-10'])->call('loadReadings')
            ->set('readings.'.$key, ['previous_value' => '0', 'current_value' => $value])
            ->call('save')->assertHasErrors([$field === 'readings' ? 'readings' : 'readings.'.$key.'.'.$field]);

        $this->assertDatabaseCount('meter_readings', 0);
    }

    public function test_batch_accepts_zero_closing_value(): void
    {
        [$room, $configuration] = $this->configuredRoom();
        $this->actingAs($room->property->landlord);
        $key = $room->id.'_'.$configuration->service_id;

        Livewire::test(RecordPropertyMeterReadings::class)
            ->fillForm(['property_id' => $room->property_id, 'billing_period' => '2026-10'])->call('loadReadings')
            ->set('readings.'.$key, ['previous_value' => '0', 'current_value' => '0'])->call('save')->assertHasNoErrors();

        $this->assertDatabaseHas('meter_readings', ['room_id' => $room->id, 'current_value' => 0, 'usage' => 0]);
    }

    public function test_concurrent_reading_is_not_overwritten_and_entire_batch_rolls_back(): void
    {
        [$room, $configuration] = $this->configuredRoom();
        $otherRoom = Room::factory()->for($room->property)->create();
        $this->actingAs($room->property->landlord);
        $key = $room->id.'_'.$configuration->service_id;
        $otherKey = $otherRoom->id.'_'.$configuration->service_id;
        $page = Livewire::test(RecordPropertyMeterReadings::class)
            ->fillForm(['property_id' => $room->property_id, 'billing_period' => '2026-10'])->call('loadReadings');
        $this->record($otherRoom, $configuration, '2026-10', '0', '7');

        $page->set('readings.'.$key, ['previous_value' => '0', 'current_value' => '10'])
            ->set('readings.'.$otherKey, ['previous_value' => '0', 'current_value' => '10'])
            ->call('save')->assertHasErrors(['readings.'.$otherKey.'.billing_period']);

        $this->assertDatabaseCount('meter_readings', 1);
        $this->assertDatabaseHas('meter_readings', ['room_id' => $otherRoom->id, 'current_value' => 7]);
    }

    public function test_loader_query_count_does_not_grow_with_room_count(): void
    {
        [$room] = $this->configuredRoom();
        Contract::factory()->for($room)->active()->create();
        $landlord = $room->property->landlord;
        $input = app(PropertyMeterReadingInput::class);
        DB::enableQueryLog();
        $input->load($landlord, $room->property_id, '2026-10');
        $initialCount = count(DB::getQueryLog());
        DB::disableQueryLog();
        $rooms = Room::factory()->count(5)->for($room->property)->create();
        foreach ($rooms as $additionalRoom) {
            Contract::factory()->for($additionalRoom)->active()->create();
        }
        DB::flushQueryLog();
        DB::enableQueryLog();

        $input->load($landlord, $room->property_id, '2026-10');
        $expandedCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($initialCount, $expandedCount);
    }

    public function test_page_requires_authentication(): void
    {
        $this->get(MeterReadingResource::getUrl('record-property'))->assertRedirect();
    }

    public function test_navigation_opens_property_entry_page_and_keeps_history_accessible(): void
    {
        [$room] = $this->configuredRoom();
        $this->actingAs($room->property->landlord);

        $this->assertSame(MeterReadingResource::getUrl('record-property'), MeterReadingResource::getNavigationUrl());
        Livewire::test(RecordPropertyMeterReadings::class)->assertSee(__('meter_readings.bulk.history'));
    }

    public function test_page_rejects_users_without_create_permission(): void
    {
        [$room] = $this->configuredRoom();
        $this->actingAs($room->property->landlord);
        Gate::before(fn (User $user, string $ability): ?bool => $ability === 'create' ? false : null);

        Livewire::test(RecordPropertyMeterReadings::class)->assertForbidden();
    }

    /** @return array{Room, PropertyService} */
    private function configuredRoom(): array
    {
        $room = Room::factory()->create();
        $configuration = PropertyService::factory()->for($room->property)->create(['effective_from' => '2026-01-01']);

        return [$room, $configuration];
    }

    private function record(Room $room, PropertyService $configuration, string $period, string $previous, string $current): MeterReading
    {
        return app(RecordMeterReadingAction::class)->handle($room->property->landlord, [
            'room_id' => $room->id, 'service_id' => $configuration->service_id,
            'billing_period' => $period, 'previous_value' => $previous, 'current_value' => $current,
        ]);
    }
}
