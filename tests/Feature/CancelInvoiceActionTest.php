<?php

namespace Tests\Feature;

use App\Actions\Invoices\CancelInvoiceAction;
use App\Actions\Invoices\GenerateInvoiceAction;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class CancelInvoiceActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancellation_preserves_items_and_reserves_business_key(): void
    {
        $invoice = Invoice::factory()->create();
        $item = InvoiceItem::factory()->for($invoice)->create();
        $invoice->update(['status' => InvoiceStatus::Issued]);
        app(CancelInvoiceAction::class)->handle($invoice->landlord, $invoice);
        $this->assertSame(InvoiceStatus::Cancelled, $invoice->fresh()->status);
        $this->assertSame('3000000.00', $item->fresh()->amount);
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseCount('invoice_items', 1);
        $this->expectException(ValidationException::class);
        app(GenerateInvoiceAction::class)->handle($invoice->landlord, ['contract_id' => $invoice->contract_id, 'billing_period' => $invoice->billing_period]);
    }

    public function test_foreign_landlord_cannot_cancel_invoice(): void
    {
        $this->expectException(AuthorizationException::class);
        app(CancelInvoiceAction::class)->handle(User::factory()->create(), Invoice::factory()->create(['status' => InvoiceStatus::Issued]));
    }

    #[TestWith(['paid'])]
    #[TestWith(['partial'])]
    #[TestWith(['cancelled'])]
    public function test_paid_partially_paid_or_cancelled_invoice_cannot_be_cancelled(string $status): void
    {
        $invoice = Invoice::factory()->create(['status' => $status]);
        $this->expectException(AuthorizationException::class);
        app(CancelInvoiceAction::class)->handle($invoice->landlord, $invoice);
    }
}
