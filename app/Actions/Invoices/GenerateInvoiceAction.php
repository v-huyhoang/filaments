<?php

namespace App\Actions\Invoices;

use App\Enums\ContractStatus;
use App\Enums\InvoiceStatus;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\MeterReading;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use App\Services\ContractServicePricing;
use App\Services\InvoiceCalculator;
use App\Services\PropertyServicePricing;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class GenerateInvoiceAction
{
    public function __construct(private InvoiceCalculator $calculator, private PropertyServicePricing $pricing) {}

    /** @param array{contract_id: int, billing_period: string, notes?: string|null, replaces_invoice_id?: int|null, replacement_reason?: string|null} $attributes */
    public function handle(User $landlord, array $attributes, ?string $expectedFingerprint = null): Invoice
    {
        return $this->run($landlord, $attributes, false, $expectedFingerprint);
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function preview(User $landlord, array $attributes): array
    {
        return $this->run($landlord, $attributes, true);
    }

    /** @param array<string, mixed> $attributes
     * @return Invoice|array<string, mixed>
     */
    private function run(User $landlord, array $attributes, bool $previewOnly, ?string $expectedFingerprint = null): Invoice|array
    {
        Gate::forUser($landlord)->authorize('create', Invoice::class);
        $validated = Validator::make($attributes, [
            'contract_id' => ['required', 'integer'],
            'billing_period' => ['required', 'date_format:Y-m'],
            'occupant_count' => ['prohibited'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'replaces_invoice_id' => ['nullable', 'integer'],
            'replacement_reason' => ['required_with:replaces_invoice_id', 'nullable', 'string', 'max:5000'],
        ])->validate();

        try {
            return DB::transaction(function () use ($landlord, $validated, $previewOnly, $expectedFingerprint): Invoice|array {
                $contract = Contract::query()
                    ->whereHas('room.property', fn (Builder $query) => $query->where('landlord_id', $landlord->id))
                    ->whereHas('tenant', fn (Builder $query) => $query->where('landlord_id', $landlord->id))
                    ->whereKey($validated['contract_id'])->lockForUpdate()->first();

                if ($contract === null) {
                    throw ValidationException::withMessages(['contract_id' => __('invoices.errors.contract_unavailable')]);
                }

                Gate::forUser($landlord)->authorize('view', $contract);
                $contract->load(['room.property', 'tenant']);
                $property = Property::query()->where('landlord_id', $landlord->id)->whereKey($contract->room->property_id)->lockForUpdate()->firstOrFail();
                $room = Room::query()->whereKey($contract->room_id)->lockForUpdate()->firstOrFail();
                $period = CarbonImmutable::createFromFormat('!Y-m', $validated['billing_period']);
                $servicePeriod = $period->subMonth();
                $rentBillable = $this->overlapsTenancy($contract, $period);
                $servicesBillable = $this->overlapsTenancy($contract, $servicePeriod);

                $billableStatus = $contract->status === ContractStatus::Active ||
                    ($contract->status === ContractStatus::Terminated && $contract->terminated_at !== null) ||
                    ($contract->status === ContractStatus::Expired && $contract->end_date?->lessThan(today()));
                if (! $billableStatus || (! $rentBillable && ! $servicesBillable)) {
                    throw ValidationException::withMessages(['contract_id' => __('invoices.errors.contract_not_billable')]);
                }

                $latestInvoice = $contract->invoices()->where('billing_period', $validated['billing_period'])->orderByDesc('revision')->lockForUpdate()->first();
                $replacementId = $validated['replaces_invoice_id'] ?? null;
                if ($latestInvoice !== null && ($latestInvoice->status !== InvoiceStatus::Cancelled || (int) $replacementId !== $latestInvoice->id)) {
                    throw ValidationException::withMessages(['billing_period' => __('invoices.errors.duplicate')]);
                }
                if ($replacementId !== null && ($latestInvoice === null || (int) $replacementId !== $latestInvoice->id)) {
                    throw ValidationException::withMessages(['replaces_invoice_id' => __('workflow.invalid_replacement')]);
                }

                $configurations = $servicesBillable
                    ? $this->pricing->billingCandidatesForUpdate($landlord, $property, $servicePeriod->endOfMonth())->sortBy('service_id')->values()
                    : collect();
                $configurations = app(ContractServicePricing::class)->resolve($contract, $configurations, $servicePeriod, true);
                $previousInvoice = $contract->invoices()->where('billing_period', $servicePeriod->format('Y-m'))
                    ->where('status', '!=', InvoiceStatus::Cancelled)->lockForUpdate()->first();
                if ($previousInvoice !== null && ! isset($previousInvoice->snapshot['billing_policy'])) {
                    $alreadyBilled = $previousInvoice->items()->where('type', '!=', 'rent')->get()->pluck('metadata.service_id');
                    $configurations = $configurations->reject(fn ($configuration): bool => $alreadyBilled->contains($configuration->service_id));
                }
                $contract->setRelation('occupants', $contract->occupants()->orderBy('id')->lockForUpdate()->get());
                $readings = MeterReading::query()->forLandlord($landlord)->where('room_id', $room->id)
                    ->whereIn('service_id', $configurations->pluck('service_id'))->where('billing_period', $servicePeriod->format('Y-m'))->lockForUpdate()->get();
                $calculation = $this->calculator->calculate($contract, $configurations, $readings, $period);
                if ($calculation['items'] === []) {
                    throw ValidationException::withMessages(['contract_id' => __('invoices.errors.contract_not_billable')]);
                }
                $dueDate = $period->day(min($contract->payment_due_day, $period->daysInMonth))->max($contract->start_date);

                $invoiceAttributes = [
                    'landlord_id' => $landlord->id,
                    'contract_id' => $contract->id,
                    'billing_period' => $validated['billing_period'],
                    'due_date' => $dueDate->toDateString(),
                    'subtotal' => $calculation['subtotal'],
                    'total' => $calculation['total'],
                    'status' => InvoiceStatus::Draft,
                    'snapshot' => [
                        'billing_policy' => 'rent_in_advance_services_in_arrears',
                        'service_billing_period' => $servicesBillable ? $servicePeriod->format('Y-m') : null,
                        'tenant_name' => $contract->tenant->full_name,
                        'property_name' => $contract->room->property->name,
                        'room_number' => $room->room_number,
                        'occupants' => $contract->occupants->map(fn ($occupant): array => $occupant->only(['id', 'full_name', 'move_in_date', 'move_out_date']))->all(),
                    ],
                    'notes' => $validated['notes'] ?? null,
                    'revision' => ($latestInvoice?->revision ?? 0) + 1,
                    'replaces_invoice_id' => $latestInvoice?->id,
                    'replacement_reason' => $replacementId !== null ? $validated['replacement_reason'] : null,
                ];
                $preview = ['attributes' => $invoiceAttributes, 'items' => $calculation['items']];
                $preview['fingerprint'] = hash('sha256', json_encode($preview, JSON_THROW_ON_ERROR));
                if ($previewOnly) {
                    return $preview;
                }
                if ($expectedFingerprint !== null && ! hash_equals($expectedFingerprint, $preview['fingerprint'])) {
                    throw ValidationException::withMessages(['billing_period' => __('workflow.preview_changed')]);
                }
                $invoice = Invoice::query()->create([...$invoiceAttributes, 'issued_at' => now()]);

                foreach ($calculation['items'] as $attributes) {
                    $item = new InvoiceItem($attributes);
                    $item->invoice()->associate($invoice);
                    $item->save();
                }

                $invoice->update(['status' => InvoiceStatus::Issued]);

                return $invoice->load('items');
            }, attempts: 3);
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['billing_period' => __('invoices.errors.duplicate')]);
        }
    }

    private function overlapsTenancy(Contract $contract, CarbonImmutable $period): bool
    {
        return $contract->start_date->lessThanOrEqualTo($period->endOfMonth()) &&
            ($contract->end_date === null || $contract->end_date->greaterThanOrEqualTo($period)) &&
            ($contract->terminated_at === null || $contract->terminated_at->copy()->startOfDay()->greaterThan($period));
    }
}
