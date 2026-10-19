<?php

namespace Tests\Feature;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Filament\Resources\Rooms\Pages\ListRooms;
use App\Filament\Resources\Rooms\Pages\ViewRoom;
use App\Filament\Resources\Rooms\RelationManagers\InvoicesRelationManager;
use App\Filament\Resources\Rooms\RelationManagers\MeterReadingsRelationManager;
use App\Filament\Resources\Rooms\RoomResource;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\MeterReading;
use App\Models\PropertyService;
use App\Models\Room;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RoomBillingWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_room_view_shows_billing_tabs_and_list_links_to_room_workspace(): void
    {
        $room = $this->room();

        Livewire::test(ViewRoom::class, ['record' => $room->id])->assertOk()
            ->assertSee(__('resources.room.meter_readings'))->assertSee(__('invoices.plural_label'));
        Livewire::test(ListRooms::class)->assertCanSeeTableRecords([$room])
            ->assertTableActionHasUrl('meterReadings', RoomResource::getUrl('view', ['record' => $room, 'relation' => 'meter-readings']), $room)
            ->assertTableActionHasUrl('invoices', RoomResource::getUrl('view', ['record' => $room, 'relation' => 'invoices']), $room);
    }

    public function test_foreign_room_view_is_not_found(): void
    {
        $room = $this->room();
        $this->actingAs(User::factory()->create());

        config(['app.env' => 'local']);
        $this->get(RoomResource::getUrl('view', ['record' => $room]))->assertNotFound();
    }

    public function test_guest_cannot_open_room_workspace(): void
    {
        $room = Room::factory()->create();

        $this->get(RoomResource::getUrl('view', ['record' => $room]))->assertRedirect();
    }

    public function test_reading_history_is_scoped_to_room_and_can_filter_usage_month(): void
    {
        $room = $this->room();
        $reading = MeterReading::factory()->for($room)->create(['billing_period' => '2026-09']);
        $older = MeterReading::factory()->for($room)->for($reading->service)->create(['billing_period' => '2026-08']);
        $otherRoom = Room::factory()->for($room->property)->create();
        $other = MeterReading::factory()->for($otherRoom)->for($reading->service)->create(['billing_period' => '2026-09']);
        $foreign = MeterReading::factory()->create();

        Livewire::test(MeterReadingsRelationManager::class, ['ownerRecord' => $room, 'pageClass' => ViewRoom::class])
            ->assertCanSeeTableRecords([$reading, $older])->assertCanNotSeeTableRecords([$other, $foreign])
            ->filterTable('billing_period', ['billing_period' => '2026-09'])
            ->assertCanSeeTableRecords([$reading])->assertCanNotSeeTableRecords([$older]);
    }

    public function test_record_reading_defaults_to_previous_month_and_binds_room_on_server(): void
    {
        $room = $this->room();
        $configuration = $this->metered($room);
        $otherRoom = Room::factory()->for($room->property)->create();

        Livewire::test(MeterReadingsRelationManager::class, ['ownerRecord' => $room, 'pageClass' => ViewRoom::class])
            ->callTableAction('recordReading', data: [
                'room_id' => $otherRoom->id, 'service_id' => $configuration->service_id,
                'previous_value' => 100, 'current_value' => 125,
            ])->assertHasNoTableActionErrors();

        $this->assertDatabaseCount('meter_readings', 1);
        $this->assertDatabaseHas('meter_readings', [
            'room_id' => $room->id, 'service_id' => $configuration->service_id, 'billing_period' => '2026-09',
            'usage' => 25, 'recorded_by' => $room->property->landlord_id,
        ]);
    }

    public function test_reading_validation_errors_remain_in_room_dialog(): void
    {
        $room = $this->room();
        $configuration = $this->metered($room);

        Livewire::test(MeterReadingsRelationManager::class, ['ownerRecord' => $room, 'pageClass' => ViewRoom::class])
            ->callTableAction('recordReading', data: [
                'service_id' => $configuration->service_id, 'previous_value' => 100, 'current_value' => 99,
            ])->assertHasTableActionErrors(['current_value']);

        $this->assertDatabaseCount('meter_readings', 0);
    }

    public function test_non_metered_service_cannot_be_recorded_from_room(): void
    {
        $room = $this->room();
        $service = Service::factory()->for($room->property->landlord, 'landlord')->create(['billing_method' => 'per_person']);
        PropertyService::factory()->for($room->property)->for($service)->create(['effective_from' => '2026-09-01']);

        Livewire::test(MeterReadingsRelationManager::class, ['ownerRecord' => $room, 'pageClass' => ViewRoom::class])
            ->callTableAction('recordReading', data: [
                'service_id' => $service->id, 'previous_value' => 100, 'current_value' => 125,
            ])->assertHasTableActionErrors(['service_id']);

        $this->assertDatabaseCount('meter_readings', 0);
    }

    public function test_latest_reading_can_be_corrected_with_audit_from_room(): void
    {
        $room = $this->room();
        $reading = MeterReading::factory()->for($room)->create(['previous_value' => 100, 'current_value' => 125]);

        Livewire::test(MeterReadingsRelationManager::class, ['ownerRecord' => $room, 'pageClass' => ViewRoom::class])
            ->callTableAction('correct', $reading, ['current_value' => 130, 'reason' => 'Correct room meter typo'])
            ->assertHasNoTableActionErrors();

        $this->assertSame('130.000', $reading->fresh()->current_value);
        $this->assertDatabaseHas('meter_reading_corrections', ['meter_reading_id' => $reading->id, 'reason' => 'Correct room meter typo']);
    }

    public function test_foreign_owner_cannot_mount_room_readings_or_invoices(): void
    {
        $room = $this->room();
        $this->actingAs(User::factory()->create());

        Livewire::test(MeterReadingsRelationManager::class, ['ownerRecord' => $room, 'pageClass' => ViewRoom::class])->assertForbidden();
        Livewire::test(InvoicesRelationManager::class, ['ownerRecord' => $room, 'pageClass' => ViewRoom::class])->assertForbidden();
    }

    public function test_invoice_history_includes_past_contracts_but_excludes_other_rooms_and_landlords(): void
    {
        $room = $this->room();
        $current = Contract::factory()->active()->for($room)->create();
        $past = Contract::factory()->active()->for($room)->create(['status' => 'terminated', 'start_date' => '2026-08-01', 'end_date' => '2026-08-31', 'terminated_at' => '2026-09-01']);
        $currentInvoice = Invoice::factory()->for($current)->create(['landlord_id' => $room->property->landlord_id, 'status' => 'issued']);
        $pastInvoice = Invoice::factory()->for($past)->create(['landlord_id' => $room->property->landlord_id, 'billing_period' => '2026-09', 'status' => 'paid']);
        $other = Invoice::factory()->create();
        $wrongLandlord = Invoice::factory()->for($current)->create(['billing_period' => '2026-11']);
        $past->delete();

        Livewire::test(InvoicesRelationManager::class, ['ownerRecord' => $room, 'pageClass' => ViewRoom::class])
            ->assertCanSeeTableRecords([$currentInvoice, $pastInvoice])->assertCanNotSeeTableRecords([$other, $wrongLandlord])
            ->filterTable('status', 'paid')->assertCanSeeTableRecords([$pastInvoice])->assertCanNotSeeTableRecords([$currentInvoice]);
    }

    public function test_create_invoice_from_room_opens_existing_preview_and_issue_flow(): void
    {
        $room = $this->room();
        $contract = Contract::factory()->active()->for($room)->create(['rent_amount' => 3000000]);

        Livewire::test(InvoicesRelationManager::class, ['ownerRecord' => $room, 'pageClass' => ViewRoom::class])
            ->callTableAction('createInvoice', data: ['contract_id' => $contract->id])->assertHasNoTableActionErrors()
            ->assertRedirect(InvoiceResource::getUrl('create', ['contract' => $contract->id]));
        $this->assertDatabaseCount('invoices', 0);

        $page = Livewire::withQueryParams(['contract' => $contract->id])->test(CreateInvoice::class)
            ->assertFormSet(['contract_id' => $contract->id, 'billing_period' => '2026-10'])
            ->call('create')->assertHasNoFormErrors()->assertSet('invoicePreview.attributes.total', '3000000.00');
        $this->assertDatabaseCount('invoices', 0);
        $page->call('create')->assertHasNoFormErrors();
        $this->assertDatabaseHas('invoices', ['contract_id' => $contract->id, 'billing_period' => '2026-10', 'status' => 'issued']);
    }

    public function test_room_invoice_action_rejects_contract_from_another_room(): void
    {
        $room = $this->room();
        $otherRoom = Room::factory()->for($room->property)->create();
        $otherContract = Contract::factory()->active()->for($otherRoom)->create();

        Livewire::test(InvoicesRelationManager::class, ['ownerRecord' => $room, 'pageClass' => ViewRoom::class])
            ->callTableAction('createInvoice', data: ['contract_id' => $otherContract->id])->assertHasTableActionErrors(['contract_id']);

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_invoice_details_link_back_to_room_invoice_tab(): void
    {
        $room = $this->room();
        $contract = Contract::factory()->active()->for($room)->create();
        $invoice = Invoice::factory()->for($contract)->create(['landlord_id' => $room->property->landlord_id, 'status' => 'issued']);

        Livewire::test(ViewInvoice::class, ['record' => $invoice->id])
            ->assertActionHasUrl('viewRoom', RoomResource::getUrl('view', ['record' => $room, 'relation' => 'invoices']));
    }

    public function test_room_list_summarizes_previous_month_readings_and_unpaid_invoices(): void
    {
        $room = $this->room();
        $contract = Contract::factory()->active()->for($room)->create();
        MeterReading::factory()->for($room)->create(['billing_period' => '2026-09']);
        MeterReading::factory()->for($room)->create(['billing_period' => '2026-10']);
        Invoice::factory()->for($contract)->create(['landlord_id' => $room->property->landlord_id, 'status' => 'partial']);
        Invoice::factory()->for($contract)->create(['landlord_id' => $room->property->landlord_id, 'status' => 'paid', 'billing_period' => '2026-09']);
        Invoice::factory()->for($contract)->create(['status' => 'issued', 'billing_period' => '2026-08']);

        Livewire::test(ListRooms::class)
            ->assertTableColumnStateSet('previous_month_readings_count', 1, $room)
            ->assertTableColumnStateSet('unpaid_invoices_count', 1, $room);
    }

    public function test_room_relation_manager_rechecks_authorization_on_later_requests(): void
    {
        $room = $this->room();
        $component = Livewire::test(MeterReadingsRelationManager::class, ['ownerRecord' => $room, 'pageClass' => ViewRoom::class])->assertOk();
        $room->property->landlord()->associate(User::factory()->create());
        $room->property->save();

        $component->call('$refresh')->assertForbidden();
    }

    private function room(): Room
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-16 12:00:00'));
        $room = Room::factory()->create();
        $this->actingAs($room->property->landlord);

        return $room;
    }

    private function metered(Room $room): PropertyService
    {
        return PropertyService::factory()->for($room->property)->create(['effective_from' => '2026-09-01']);
    }
}
