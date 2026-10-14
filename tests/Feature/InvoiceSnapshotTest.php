<?php

namespace Tests\Feature;

use App\Actions\Invoices\GenerateInvoiceAction;
use App\Actions\Services\UpdatePropertyServiceAction;
use App\Enums\InvoiceStatus;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\MeterReading;
use App\Models\PropertyService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class InvoiceSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_source_changes_do_not_recalculate_snapshots(): void
    {
        $contract = Contract::factory()->active()->create();
        $landlord = $contract->room->property->landlord;
        $configuration = PropertyService::factory()->for($contract->room->property)->create();
        MeterReading::factory()->for($contract->room)->for($configuration->service)->create();
        $invoice = app(GenerateInvoiceAction::class)->handle($landlord, ['contract_id' => $contract->id, 'billing_period' => today()->format('Y-m')]);
        $original = $invoice->fresh('items')->toArray();
        $contract->update(['rent_amount' => 1]);
        $contract->room->update(['base_rent' => 1, 'room_number' => 'Changed room']);
        $contract->tenant->update(['full_name' => 'Changed tenant']);
        $configuration->service->update(['name' => 'Changed service']);
        app(UpdatePropertyServiceAction::class)->handle($landlord, $configuration, ['unit_price' => 9999, 'effective_from' => today()->addDay()->toDateString()]);
        $this->assertSame($original, $invoice->fresh('items')->toArray());
    }

    #[TestWith(['total', '1.00'])]
    #[TestWith(['subtotal', '1.00'])]
    #[TestWith(['billing_period', '2000-01'])]
    #[TestWith(['snapshot', ['tenant_name' => 'Forged']])]
    public function test_invoice_snapshot_fields_cannot_be_changed(string $field, mixed $value): void
    {
        $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Issued]);
        $this->expectException(ValidationException::class);
        $invoice->update([$field => $value]);
    }

    #[TestWith(['update'])]
    #[TestWith(['delete'])]
    public function test_line_items_cannot_be_changed_or_deleted(string $operation): void
    {
        $item = InvoiceItem::factory()->create();
        $item->invoice->update(['status' => InvoiceStatus::Issued]);
        $this->expectException(ValidationException::class);
        if ($operation === 'update') {
            $item->update(['amount' => 1]);
        } else {
            $item->delete();
        }
    }

    public function test_no_items_can_be_added_to_issued_invoice(): void
    {
        $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Issued]);
        $this->expectException(ValidationException::class);
        InvoiceItem::factory()->for($invoice)->create();
    }

    public function test_invoice_cannot_be_hard_deleted(): void
    {
        $invoice = Invoice::factory()->create();
        $this->expectException(ValidationException::class);
        $invoice->delete();
    }

    public function test_issued_invoice_cannot_be_reopened_as_draft(): void
    {
        $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Issued]);
        $this->expectException(ValidationException::class);
        $invoice->update(['status' => InvoiceStatus::Draft]);
    }

    public function test_stale_draft_instance_cannot_append_to_issued_invoice(): void
    {
        $draft = Invoice::factory()->create();
        $draft->fresh()->update(['status' => InvoiceStatus::Issued]);
        $item = InvoiceItem::factory()->for($draft)->make();
        $item->invoice()->associate($draft);
        $this->expectException(ValidationException::class);
        $item->save();
    }

    public function test_policy_denies_snapshot_mutations_and_foreign_item_access(): void
    {
        $item = InvoiceItem::factory()->create();
        $owner = $item->invoice->landlord;
        $this->assertTrue(Gate::forUser($owner)->allows('view', $item));
        $this->assertFalse(Gate::forUser(User::factory()->create())->allows('view', $item));
        $this->assertFalse(Gate::forUser($owner)->allows('update', $item));
        $this->assertFalse(Gate::forUser($owner)->allows('delete', $item));
        $this->assertFalse(Gate::forUser($owner)->allows('update', $item->invoice));
        $this->assertFalse(Gate::forUser($owner)->allows('delete', $item->invoice));
    }
}
