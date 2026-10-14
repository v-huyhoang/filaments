<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Actions\Invoices\GenerateInvoiceAction;
use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    protected static bool $canCreateAnother = false;

    #[Locked]
    public ?array $invoicePreview = null;

    protected function afterFill(): void
    {
        if (request()->filled('replacement')) {
            $invoice = Invoice::query()->forLandlord(auth()->user())->findOrFail(request()->integer('replacement'));
            Gate::authorize('view', $invoice);
            abort_unless($invoice->status === InvoiceStatus::Cancelled, 422);
            $this->form->fill([
                'contract_id' => $invoice->contract_id, 'billing_period' => $invoice->billing_period,
                'occupant_count' => $invoice->snapshot['occupant_count'] ?? null, 'replaces_invoice_id' => $invoice->id,
            ]);
        } elseif (request()->filled('contract')) {
            $contract = Contract::query()->whereHas('room.property', fn (Builder $query) => $query->where('landlord_id', auth()->id()))
                ->whereHas('tenant', fn (Builder $query) => $query->where('landlord_id', auth()->id()))->findOrFail(request()->integer('contract'));
            Gate::authorize('view', $contract);
            $lastDate = collect([today(), $contract->end_date, $contract->terminated_at])->filter()->sort()->first();
            $this->form->fill(['contract_id' => $contract->id, 'billing_period' => $lastDate->format('Y-m')]);
        }
    }

    public function create(bool $another = false): void
    {
        try {
            $preview = app(GenerateInvoiceAction::class)->preview(auth()->user(), $this->form->getState());
        } catch (ValidationException $exception) {
            $this->invoicePreview = null;
            $this->throwFormValidation($exception);
        }
        if ($this->invoicePreview === null || ! hash_equals($this->invoicePreview['fingerprint'], $preview['fingerprint'])) {
            $this->invoicePreview = $preview;

            return;
        }
        parent::create(false);
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label(fn (): string => __($this->invoicePreview === null ? 'workflow.preview_invoice' : 'workflow.issue_invoice'));
    }

    private function throwFormValidation(ValidationException $exception): never
    {
        $messages = [];
        foreach ($exception->errors() as $field => $errors) {
            $field = $field === 'total' ? 'billing_period' : $field;
            $messages[str_starts_with($field, $this->form->getStatePath().'.') ? $field : $this->form->getStatePath().'.'.$field] = $errors;
        }
        throw ValidationException::withMessages($messages);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $landlord = auth()->user();
        abort_unless($landlord instanceof User, 403);

        try {
            abort_unless($this->invoicePreview !== null, 422);

            return app(GenerateInvoiceAction::class)->handle($landlord, $data, $this->invoicePreview['fingerprint']);
        } catch (ValidationException $exception) {
            $this->invoicePreview = null;
            $this->throwFormValidation($exception);
        }
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }
}
