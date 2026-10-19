<?php

namespace Tests\Feature;

use App\Actions\Contracts\ConfigureContractServiceAction;
use App\Actions\Contracts\SaveContractOccupantAction;
use App\Actions\Contracts\UpdateDraftContractAction;
use App\Actions\Invoices\GenerateInvoiceAction;
use App\Actions\MeterReadings\RecordMeterReadingAction;
use App\Actions\Services\UpdatePropertyServiceAction;
use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Contracts\Pages\ViewContract;
use App\Filament\Resources\Contracts\RelationManagers\ContractServicesRelationManager;
use App\Filament\Resources\Contracts\RelationManagers\OccupantsRelationManager;
use App\Filament\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Models\Contract;
use App\Models\ContractOccupant;
use App\Models\ContractService;
use App\Models\Invoice;
use App\Models\MeterReading;
use App\Models\PropertyService;
use App\Models\Room;
use App\Models\Service;
use App\Models\User;
use App\Services\MeterReadingInput;
use Carbon\CarbonImmutable;
use Database\Seeders\ContractOccupantSeeder;
use Database\Seeders\ContractServiceSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ContractOccupancyWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_saves_individual_and_ignores_client_contract_id(): void
    {
        $contract = $this->contract();
        $foreign = Contract::factory()->active()->create();

        $occupant = $this->save($contract, ['contract_id' => $foreign->id]);

        $this->assertSame($contract->id, $occupant->contract_id);
        $this->assertSame('Resident One', $occupant->full_name);
        $this->assertNull($occupant->move_out_date);
    }

    #[TestWith([['move_in_date' => '2026-09-30']])]
    #[TestWith([['move_out_date' => '2026-09-30']])]
    #[TestWith([['move_in_date' => 'not-a-date']])]
    #[TestWith([['full_name' => '   ']])]
    #[TestWith([['move_out_date' => '2028-01-01']])]
    public function test_rejects_invalid_residency_without_writing(array $changes): void
    {
        $contract = $this->contract();
        try {
            $this->save($contract, $changes);
            $this->fail('Invalid residency was saved.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
        $this->assertDatabaseCount('contract_occupants', 0);
    }

    public function test_capacity_uses_simultaneous_people_not_total_historical_people(): void
    {
        $contract = $this->contract();
        $contract->room->update(['max_occupants' => 1]);
        $this->save($contract, ['move_out_date' => '2026-10-17']);
        $this->save($contract, ['full_name' => 'Resident Two', 'move_in_date' => '2026-10-17']);
        $this->save($contract, ['full_name' => 'Same day', 'move_in_date' => '2026-10-17', 'move_out_date' => '2026-10-17']);

        try {
            $this->save($contract, ['full_name' => 'Extra', 'move_in_date' => '2026-10-20']);
            $this->fail('Capacity was exceeded.');
        } catch (ValidationException $exception) {
            $this->assertSame([__('occupancy.capacity_exceeded')], $exception->errors()['move_in_date']);
        }
        $this->assertDatabaseCount('contract_occupants', 3);
    }

    public function test_foreign_landlord_cannot_add_occupants(): void
    {
        $contract = $this->contract();
        $this->expectException(AuthorizationException::class);
        app(SaveContractOccupantAction::class)->handle(User::factory()->create(), $contract, ['full_name' => 'Intruder', 'move_in_date' => '2026-10-01']);
    }

    public function test_foreign_occupant_id_cannot_be_moved_to_owned_contract(): void
    {
        $contract = $this->contract();
        $foreign = ContractOccupant::factory()->create();
        $this->expectException(ValidationException::class);
        app(SaveContractOccupantAction::class)->handle($contract->room->property->landlord, $contract, ['full_name' => 'Intruder', 'move_in_date' => '2026-10-01'], $foreign);
    }

    public function test_denied_update_permission_blocks_business_action(): void
    {
        $contract = $this->contract();
        Gate::before(fn (User $user, string $ability): ?bool => $ability === 'update' ? false : null);
        $this->expectException(AuthorizationException::class);
        $this->save($contract);
    }

    public function test_issued_period_prevents_residency_correction_but_later_checkout_is_allowed(): void
    {
        $contract = $this->contract();
        $occupant = $this->save($contract);
        $invoice = Invoice::factory()->for($contract)->create(['landlord_id' => $contract->room->property->landlord_id, 'billing_period' => '2026-10']);
        app(SaveContractOccupantAction::class)->handle($invoice->landlord, $contract, ['full_name' => $occupant->full_name, 'move_in_date' => '2026-10-01', 'move_out_date' => '2026-11-10'], $occupant);
        try {
            app(SaveContractOccupantAction::class)->handle($invoice->landlord, $contract, ['full_name' => $occupant->full_name, 'move_in_date' => '2026-10-02'], $occupant);
            $this->fail('Billed history was modified.');
        } catch (ValidationException $exception) {
            $this->assertSame([__('occupancy.invoice_locked')], $exception->errors()['move_in_date']);
        }
        $this->assertSame('2026-10-01', $occupant->fresh()->move_in_date->toDateString());
        $this->assertSame('2026-11-10', $occupant->fresh()->move_out_date->toDateString());
    }

    public function test_per_person_override_needs_no_meter_and_other_room_keeps_metered_default(): void
    {
        $contract = $this->contract();
        $configuration = $this->water($contract);
        $this->configure($contract, $configuration->service, ['billing_method' => 'per_person', 'unit_price' => 90000]);
        $this->save($contract);
        $other = Contract::factory()->active()->create(['room_id' => Room::factory()->for($contract->room->property), 'rent_amount' => 3000000]);

        $invoice = $this->generate($contract, '2026-10');

        $this->assertSame('3090000.00', $invoice->total);
        $this->assertCount(2, $invoice->items);
        $this->assertNull($invoice->items->firstWhere('type', 'per_person')->meter_reading_id);
        $input = app(MeterReadingInput::class);
        $this->assertSame([], $input->services($invoice->landlord, $contract->room_id, '2026-10'));
        $this->assertSame([$configuration->service_id => $configuration->service->name], $input->services($invoice->landlord, $other->room_id, '2026-10'));
    }

    public function test_fixed_monthly_override_ignores_occupant_count_and_meter(): void
    {
        $contract = $this->contract();
        $configuration = $this->water($contract);
        $this->configure($contract, $configuration->service, ['billing_method' => 'fixed', 'fixed_price' => 100000]);

        $invoice = $this->generate($contract, '2026-10');

        $this->assertSame('3100000.00', $invoice->total);
        $this->assertSame('100000.00', $invoice->items->firstWhere('type', 'fixed')->amount);
    }

    public function test_next_month_price_does_not_change_earlier_month_and_can_restore_default(): void
    {
        $contract = $this->contract();
        $configuration = $this->water($contract);
        $this->configure($contract, $configuration->service, ['billing_method' => 'per_person', 'unit_price' => 90000]);
        $this->configure($contract, $configuration->service, ['billing_method' => 'per_person', 'unit_price' => 120000, 'effective_from' => '2026-11-01']);
        $this->configure($contract, $configuration->service, ['use_default' => true, 'effective_from' => '2026-12-01']);
        $this->save($contract);

        $this->assertSame('3090000.00', $this->generate($contract, '2026-10')->total);
        $this->assertSame('3120000.00', $this->generate($contract, '2026-11')->total);
        $this->assertSame([$configuration->service_id => $configuration->service->name], app(MeterReadingInput::class)->services($contract->room->property->landlord, $contract->room_id, '2026-12'));
        $this->assertDatabaseCount('contract_services', 3);
    }

    public function test_disabled_override_does_not_fall_back_to_default(): void
    {
        $contract = $this->contract();
        $configuration = $this->water($contract);
        $this->configure($contract, $configuration->service, ['billing_method' => 'per_person', 'unit_price' => 90000, 'is_enabled' => false]);

        $this->assertSame('3000000.00', $this->generate($contract, '2026-10')->total);
        $this->assertSame([], app(MeterReadingInput::class)->services($contract->room->property->landlord, $contract->room_id, '2026-10'));
    }

    public function test_record_reading_rejects_room_billed_per_person(): void
    {
        $contract = $this->contract();
        $configuration = $this->water($contract);
        $this->configure($contract, $configuration->service, ['billing_method' => 'per_person', 'unit_price' => 90000]);
        try {
            app(RecordMeterReadingAction::class)->handle($contract->room->property->landlord, [
                'room_id' => $contract->room_id, 'service_id' => $configuration->service_id,
                'billing_period' => '2026-10', 'previous_value' => 0, 'current_value' => 10,
            ]);
            $this->fail('Per-person room accepted a reading.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('service_id', $exception->errors());
        }
        $this->assertDatabaseCount('meter_readings', 0);
    }

    #[TestWith(['2026-10-02'])]
    #[TestWith(['2026-09-01'])]
    public function test_override_rejects_mid_month_or_retroactive_effective_date(string $date): void
    {
        $contract = $this->contract();
        $configuration = $this->water($contract);
        try {
            $this->configure($contract, $configuration->service, ['billing_method' => 'per_person', 'unit_price' => 90000, 'effective_from' => $date]);
            $this->fail('Invalid effective date was saved.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('effective_from', $exception->errors());
        }
        $this->assertDatabaseCount('contract_services', 0);
    }

    public function test_property_per_person_price_revision_rejects_mid_month(): void
    {
        $contract = $this->contract();
        $configuration = $this->water($contract);
        $configuration->service->update(['billing_method' => 'per_person']);
        $this->expectException(ValidationException::class);
        app(UpdatePropertyServiceAction::class)->handle($contract->room->property->landlord, $configuration, ['unit_price' => 90000, 'effective_from' => '2026-10-14']);
    }

    public function test_override_rejects_foreign_service_and_preserves_existing_versions(): void
    {
        $contract = $this->contract();
        $foreign = Service::factory()->create();
        $this->expectException(ValidationException::class);
        $this->configure($contract, $foreign, ['billing_method' => 'per_person', 'unit_price' => 90000]);
    }

    public function test_other_landlord_cannot_change_contract_service(): void
    {
        $contract = $this->contract();
        $configuration = $this->water($contract);
        $this->expectException(AuthorizationException::class);
        app(ConfigureContractServiceAction::class)->handle(User::factory()->create(), $contract, ['service_id' => $configuration->service_id]);
    }

    public function test_issued_invoice_prevents_adding_effective_override(): void
    {
        $contract = $this->contract();
        $configuration = $this->water($contract);
        Invoice::factory()->for($contract)->create(['landlord_id' => $contract->room->property->landlord_id, 'billing_period' => '2026-10']);
        $this->expectException(ValidationException::class);
        $this->configure($contract, $configuration->service, ['billing_method' => 'fixed', 'fixed_price' => 100000]);
    }

    public function test_contract_page_exposes_occupant_workflow_and_foreign_record_is_hidden(): void
    {
        $contract = $this->contract();
        $this->actingAs($contract->room->property->landlord);
        Livewire::test(ViewContract::class, ['record' => $contract->id])->assertSuccessful();
        $foreign = Contract::factory()->active()->create();

        config(['app.env' => 'local']);
        $this->get(ContractResource::getUrl('view', ['record' => $foreign]))->assertNotFound();
    }

    public function test_relation_manager_saves_and_shows_occupant(): void
    {
        $contract = $this->contract();
        $this->actingAs($contract->room->property->landlord);

        Livewire::test(OccupantsRelationManager::class, ['ownerRecord' => $contract, 'pageClass' => ViewContract::class])
            ->callAction(TestAction::make('addOccupant')->table(), ['full_name' => 'Resident One', 'move_in_date' => '2026-10-01'])
            ->assertHasNoActionErrors()->assertCanSeeTableRecords($contract->occupants()->get());

        $this->assertDatabaseHas('contract_occupants', ['contract_id' => $contract->id, 'full_name' => 'Resident One']);
    }

    public function test_relation_manager_reports_capacity_error_next_to_date(): void
    {
        $contract = $this->contract();
        $contract->room->update(['max_occupants' => 1]);
        $this->save($contract);
        $this->actingAs($contract->room->property->landlord);

        Livewire::test(OccupantsRelationManager::class, ['ownerRecord' => $contract, 'pageClass' => ViewContract::class])
            ->callAction(TestAction::make('addOccupant')->table(), ['full_name' => 'Extra', 'move_in_date' => '2026-10-01'])
            ->assertHasActionErrors(['move_in_date']);

        $this->assertDatabaseCount('contract_occupants', 1);
    }

    public function test_relation_manager_configures_per_person_water(): void
    {
        $contract = $this->contract();
        $configuration = $this->water($contract);
        $this->actingAs($contract->room->property->landlord);

        Livewire::test(ContractServicesRelationManager::class, ['ownerRecord' => $contract, 'pageClass' => ViewContract::class])
            ->callAction(TestAction::make('configureService')->table(), [
                'service_id' => $configuration->service_id, 'use_default' => false, 'billing_method' => 'per_person',
                'unit_price' => 90000, 'is_enabled' => true, 'effective_from' => '2026-10-01',
            ])->assertHasNoActionErrors();

        $this->assertDatabaseHas('contract_services', ['contract_id' => $contract->id, 'billing_method' => 'per_person', 'unit_price' => 90000]);
    }

    public function test_returning_to_metered_billing_uses_new_opening_value_not_old_consumption(): void
    {
        $contract = $this->contract();
        $configuration = $this->water($contract);
        MeterReading::factory()->for($contract->room)->for($configuration->service)->create([
            'billing_period' => '2026-09', 'previous_value' => 90, 'current_value' => 100, 'usage' => 10,
        ]);
        $this->configure($contract, $configuration->service, ['billing_method' => 'per_person', 'unit_price' => 90000]);
        $override = $this->configure($contract, $configuration->service, [
            'billing_method' => 'metered', 'unit_price' => 20000, 'metered_unit' => 'm3', 'effective_from' => '2026-11-01',
        ]);
        $landlord = $contract->room->property->landlord;
        $this->assertNull(app(MeterReadingInput::class)->previous($landlord, $contract->room_id, $configuration->service_id, '2026-11'));

        $reading = app(RecordMeterReadingAction::class)->handle($landlord, [
            'room_id' => $contract->room_id, 'service_id' => $configuration->service_id,
            'billing_period' => '2026-11', 'previous_value' => 150, 'current_value' => 158,
        ]);
        $invoice = $this->generate($contract, '2026-11');

        $this->assertSame('8.000', $reading->usage);
        $item = $invoice->items->firstWhere('type', 'metered');
        $this->assertSame('160000.00', $item->amount);
        $this->assertSame('m3', $item->unit);
        $this->assertSame($override->id, $item->metadata['contract_service_id']);
        $this->assertSame('2026-11-01', $item->metadata['contract_effective_from']);
    }

    public function test_returning_to_metered_without_fresh_opening_value_fails(): void
    {
        $contract = $this->contract();
        $configuration = $this->water($contract);
        MeterReading::factory()->for($contract->room)->for($configuration->service)->create(['billing_period' => '2026-09']);
        $this->configure($contract, $configuration->service, ['billing_method' => 'per_person', 'unit_price' => 90000]);
        $this->configure($contract, $configuration->service, ['use_default' => true, 'effective_from' => '2026-11-01']);
        try {
            app(RecordMeterReadingAction::class)->handle($contract->room->property->landlord, [
                'room_id' => $contract->room_id, 'service_id' => $configuration->service_id,
                'billing_period' => '2026-11', 'current_value' => 158,
            ]);
            $this->fail('Old reading was carried across non-metered month.');
        } catch (ValidationException $exception) {
            $this->assertSame([__('meter_readings.errors.initial_value_required')], $exception->errors()['previous_value']);
        }
        $this->assertDatabaseCount('meter_readings', 1);
    }

    public function test_initial_override_mid_month_can_start_current_month_but_revision_cannot(): void
    {
        $contract = $this->contract();
        $configuration = $this->water($contract);
        $this->travelTo(CarbonImmutable::parse('2026-10-14 10:00:00'));
        $first = $this->configure($contract, $configuration->service, ['billing_method' => 'per_person', 'unit_price' => 90000]);

        $this->assertSame('2026-10-01', $first->effective_from->toDateString());
        try {
            $this->configure($contract, $configuration->service, ['billing_method' => 'per_person', 'unit_price' => 120000]);
            $this->fail('Existing month was overwritten.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('effective_from', $exception->errors());
        }
        $this->assertDatabaseCount('contract_services', 1);
    }

    public function test_metered_override_requires_unit_without_writing(): void
    {
        $contract = $this->contract();
        $configuration = $this->water($contract);
        try {
            $this->configure($contract, $configuration->service, ['billing_method' => 'metered', 'unit_price' => 20000]);
            $this->fail('Metered configuration has no unit.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('metered_unit', $exception->errors());
        }
        $this->assertDatabaseCount('contract_services', 0);
    }

    public function test_relation_manager_can_record_departure_without_deleting_history(): void
    {
        $contract = $this->contract();
        $occupant = $this->save($contract);
        $this->actingAs($contract->room->property->landlord);

        Livewire::test(OccupantsRelationManager::class, ['ownerRecord' => $contract, 'pageClass' => ViewContract::class])
            ->callAction(TestAction::make('editOccupant')->table($occupant), ['full_name' => $occupant->full_name, 'move_in_date' => '2026-10-01', 'move_out_date' => '2026-10-17'])
            ->assertHasNoActionErrors();

        $this->assertSame('2026-10-17', $occupant->fresh()->move_out_date->toDateString());
        $this->assertDatabaseCount('contract_occupants', 1);
    }

    public function test_invoice_page_and_preview_show_snapshotted_people_and_day_formula(): void
    {
        $contract = $this->contract();
        $configuration = $this->water($contract);
        $this->configure($contract, $configuration->service, ['billing_method' => 'per_person', 'unit_price' => 90000]);
        $this->save($contract, ['full_name' => 'Resident Visible', 'move_in_date' => '2026-10-17']);
        $this->actingAs($contract->room->property->landlord);
        $page = Livewire::test(CreateInvoice::class)
            ->fillForm(['contract_id' => $contract->id, 'billing_period' => '2026-11'])->call('create')->assertHasNoFormErrors()
            ->assertSee('Resident Visible')->assertSee(__('occupancy.person_days'))->assertSee(__('occupancy.approximate_months'));
        $page->call('create')->assertHasNoFormErrors();
        $invoice = $contract->invoices()->firstOrFail();

        Livewire::test(ViewInvoice::class, ['record' => $invoice->id])
            ->assertSee('Resident Visible')->assertSee(__('occupancy.person_days'))->assertSee(__('occupancy.approximate_months'));

        $this->assertSame('3043548.00', $invoice->total);
    }

    public function test_draft_contract_with_occupancy_cannot_switch_room_or_invalidate_residency_dates(): void
    {
        $contract = $this->contract();
        $this->save($contract);
        $contract->update(['status' => 'draft']);
        $otherRoom = Room::factory()->for($contract->room->property)->create();
        $data = [
            'room_id' => $otherRoom->id, 'tenant_id' => $contract->tenant_id,
            'start_date' => '2026-10-01', 'end_date' => '2027-10-01', 'rent_amount' => 3000000,
            'payment_due_day' => 5, 'status' => 'draft',
        ];
        try {
            app(UpdateDraftContractAction::class)->handle($contract->room->property->landlord, $contract, $data);
            $this->fail('Contract moved with dependent occupancy.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('room_id', $exception->errors());
        }
        try {
            app(UpdateDraftContractAction::class)->handle($contract->room->property->landlord, $contract, [...$data, 'room_id' => $contract->room_id, 'start_date' => '2026-10-02']);
            $this->fail('Contract start invalidated occupancy.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('start_date', $exception->errors());
        }
        $this->assertSame('2026-10-01', $contract->fresh()->start_date->toDateString());
    }

    public function test_demo_occupant_seeder_is_idempotent_and_does_not_backfill_billed_contract(): void
    {
        $contract = $this->contract();
        $billed = Contract::factory()->active()->create();
        Invoice::factory()->for($billed)->create(['landlord_id' => $billed->room->property->landlord_id]);

        $this->seed(ContractOccupantSeeder::class);
        $this->seed(ContractOccupantSeeder::class);

        $this->assertDatabaseCount('contract_occupants', 1);
        $this->assertDatabaseHas('contract_occupants', ['contract_id' => $contract->id, 'full_name' => $contract->tenant->full_name]);
        $this->assertSame(0, $billed->occupants()->count());
    }

    public function test_default_service_seeder_is_idempotent_and_preserves_property_defaults(): void
    {
        $contract = $this->contract();
        $configuration = $this->water($contract);

        $this->seed(ContractServiceSeeder::class);
        $this->seed(ContractServiceSeeder::class);

        $this->assertDatabaseCount('contract_services', 1);
        $this->assertDatabaseHas('contract_services', ['contract_id' => $contract->id, 'service_id' => $configuration->service_id, 'use_default' => true]);
        $this->assertSame([$configuration->service_id => $configuration->service->name], app(MeterReadingInput::class)->services($contract->room->property->landlord, $contract->room_id, '2026-11'));
    }

    private function contract(): Contract
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-01 10:00:00'));
        $contract = Contract::factory()->active()->create(['rent_amount' => 3000000]);
        $contract->room->update(['max_occupants' => 4]);

        return $contract;
    }

    private function save(Contract $contract, array $changes = []): ContractOccupant
    {
        return app(SaveContractOccupantAction::class)->handle($contract->room->property->landlord, $contract, ['full_name' => 'Resident One', 'move_in_date' => '2026-10-01', ...$changes]);
    }

    private function water(Contract $contract): PropertyService
    {
        $service = Service::factory()->for($contract->room->property->landlord, 'landlord')->create(['billing_method' => 'metered', 'name' => 'Water', 'unit' => 'm3']);

        return PropertyService::factory()->for($contract->room->property)->for($service)->create(['effective_from' => '2026-10-01']);
    }

    private function configure(Contract $contract, Service $service, array $changes): ContractService
    {
        return app(ConfigureContractServiceAction::class)->handle($contract->room->property->landlord, $contract, [
            'service_id' => $service->id, 'use_default' => false, 'effective_from' => '2026-10-01', ...$changes,
        ]);
    }

    private function generate(Contract $contract, string $period): Invoice
    {
        $collectionPeriod = CarbonImmutable::createFromFormat('!Y-m', $period)->addMonth()->format('Y-m');

        return app(GenerateInvoiceAction::class)->handle($contract->room->property->landlord, ['contract_id' => $contract->id, 'billing_period' => $collectionPeriod]);
    }
}
