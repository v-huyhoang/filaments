<?php

namespace App\Actions\Invoices;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CancelInvoiceAction
{
    public function handle(User $landlord, Invoice $invoice): Invoice
    {
        Gate::forUser($landlord)->authorize('cancel', $invoice);

        return DB::transaction(function () use ($landlord, $invoice): Invoice {
            $invoice = Invoice::query()->forLandlord($landlord)->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($landlord)->authorize('cancel', $invoice);
            $invoice->update(['status' => InvoiceStatus::Cancelled]);

            return $invoice;
        });
    }
}
