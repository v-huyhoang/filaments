<?php

namespace Tests\Feature;

use App\Enums\BillingMethod;
use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PropertyService;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InvoiceResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_and_direct_id_exclude_foreign_invoices(): void
    {
        $owned = Invoice::factory()->create();
        $foreign = Invoice::factory()->create();
        $this->actingAs($owned->landlord);
        $this->assertSame([$owned->id], InvoiceResource::getEloquentQuery()->pluck('id')->all());
        $this->assertNull(InvoiceResource::getRecordRouteBindingEloquentQuery()->find($foreign->id));
        Livewire::test(ListInvoices::class)->assertCanSeeTableRecords([$owned])->assertCanNotSeeTableRecords([$foreign]);
        config(['app.env' => 'local']);
        $this->get(InvoiceResource::getUrl('view', ['record' => $foreign]))->assertNotFound();
    }

    public function test_create_page_generates_invoice_through_action(): void
    {
        $contract = Contract::factory()->active()->create();
        $landlord = $contract->room->property->landlord;
        $this->actingAs($landlord);
        $page = Livewire::test(CreateInvoice::class)->fillForm([
            'contract_id' => $contract->id, 'billing_period' => today()->format('Y-m'),
        ])->call('create')->assertHasNoFormErrors();
        $this->assertDatabaseCount('invoices', 0);
        $page->assertSeeHtml('<table class="invoice-items">')
            ->assertSee(__('invoices.fields.charge_from'))
            ->assertSee(__('invoices.fields.charge_to'))
            ->assertSee(__('invoices.fields.unit'))
            ->assertSee(__('invoices.calculation_details'));
        $page->call('create')->assertHasNoFormErrors();
        $this->assertDatabaseHas('invoices', ['contract_id' => $contract->id, 'landlord_id' => $landlord->id, 'status' => 'issued']);
        $this->assertDatabaseCount('invoice_items', 1);
    }

    public function test_create_page_rejects_foreign_contract(): void
    {
        $contract = Contract::factory()->active()->create();
        $foreign = Contract::factory()->active()->create();
        $this->actingAs($contract->room->property->landlord);
        Livewire::test(CreateInvoice::class)->fillForm([
            'contract_id' => $foreign->id, 'billing_period' => today()->format('Y-m'),
        ])->call('create')->assertHasFormErrors(['contract_id']);
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_view_shows_snapshot_items_and_totals(): void
    {
        $invoice = Invoice::factory()->create();
        InvoiceItem::factory()->for($invoice)->create([
            'name' => 'Snapshot rent',
            'metadata' => [
                'charge_from' => '2026-10-15', 'charge_to' => '2026-10-31',
                'previous_value' => '100', 'current_value' => '120',
                'person_days' => 17, 'days_in_month' => 31,
                'occupants' => [['full_name' => '<script>alert(1)</script>', 'from' => '2026-10-15', 'until' => '2026-11-01', 'days' => 17]],
            ],
        ]);
        $invoice->update(['status' => InvoiceStatus::Issued]);
        $this->actingAs($invoice->landlord);
        Livewire::test(ViewInvoice::class, ['record' => $invoice->id])->assertOk()
            ->assertSee('Snapshot rent')->assertSee('Test tenant')->assertSchemaStateSet(['total' => '3000000.00'])
            ->assertSeeHtml('<table class="invoice-items">')->assertSeeHtml('scope="col"')
            ->assertSee('15/10/2026')->assertSee('31/10/2026')
            ->assertSee(__('invoices.calculation_details'))->assertSee(__('meter_readings.fields.previous_value'))
            ->assertSee(__('occupancy.charge_until'))->assertSee('01/11/2026')
            ->assertSee('<script>alert(1)</script>')->assertDontSeeHtml('<script>alert(1)</script>');
    }

    public function test_cancel_action_preserves_financial_history(): void
    {
        $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Issued]);
        $this->actingAs($invoice->landlord);
        Livewire::test(ViewInvoice::class, ['record' => $invoice->id])->callAction('cancelInvoice')->assertHasNoActionErrors();
        $this->assertSame(InvoiceStatus::Cancelled, $invoice->fresh()->status);
        $this->assertSame('3000000.00', $invoice->fresh()->total);
    }

    public function test_snapshot_stays_accessible_after_contract_room_property_and_tenant_are_soft_deleted(): void
    {
        $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Issued]);
        $contract = $invoice->contract;
        $contract->tenant->delete();
        $contract->room->property->delete();
        $contract->room->delete();
        $contract->delete();
        $this->actingAs($invoice->landlord);
        Livewire::test(ViewInvoice::class, ['record' => $invoice->id])->assertOk()->assertSee('Test tenant')->assertSee('Test property');
    }

    public function test_list_filters_status_and_month(): void
    {
        $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Issued]);
        $other = Invoice::factory()->for($invoice->landlord, 'landlord')->create(['status' => InvoiceStatus::Cancelled, 'billing_period' => today()->subMonthNoOverflow()->format('Y-m')]);
        $this->actingAs($invoice->landlord);
        Livewire::test(ListInvoices::class)->filterTable('status', 'issued')->assertCanSeeTableRecords([$invoice])->assertCanNotSeeTableRecords([$other]);
        Livewire::test(ListInvoices::class)->filterTable('billing_period', ['billing_period' => $other->billing_period])->assertCanSeeTableRecords([$other])->assertCanNotSeeTableRecords([$invoice]);
    }

    public function test_guest_cannot_access_invoices(): void
    {
        $this->get(InvoiceResource::getUrl())->assertRedirect();
    }

    public function test_create_page_reports_missing_actual_occupant_count(): void
    {
        $contract = Contract::factory()->active()->create();
        $landlord = $contract->room->property->landlord;
        $service = Service::factory()->for($landlord, 'landlord')->create(['billing_method' => BillingMethod::PerPerson]);
        PropertyService::factory()->for($contract->room->property)->for($service)->create();
        $this->actingAs($landlord);
        Livewire::test(CreateInvoice::class)->fillForm(['contract_id' => $contract->id, 'billing_period' => today()->startOfMonth()->addMonth()->format('Y-m')])
            ->call('create')->assertHasFormErrors(['billing_period']);
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_create_page_reports_duplicate_billing_period(): void
    {
        $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Issued]);
        $this->actingAs($invoice->landlord);
        Livewire::test(CreateInvoice::class)->fillForm(['contract_id' => $invoice->contract_id, 'billing_period' => $invoice->billing_period])
            ->call('create')->assertHasFormErrors(['billing_period']);
        $this->assertDatabaseCount('invoices', 1);
    }
}
