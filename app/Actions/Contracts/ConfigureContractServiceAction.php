<?php

namespace App\Actions\Contracts;

use App\Enums\BillingMethod;
use App\Enums\ContractStatus;
use App\Enums\InvoiceStatus;
use App\Models\Contract;
use App\Models\ContractService;
use App\Models\Property;
use App\Models\User;
use App\Services\PropertyServicePricing;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ConfigureContractServiceAction
{
    /** @param array<string, mixed> $attributes */
    public function handle(User $landlord, Contract $contract, array $attributes): ContractService
    {
        Gate::forUser($landlord)->authorize('update', $contract);
        $validated = Validator::make($attributes, [
            'service_id' => ['required', 'integer'],
            'use_default' => ['required', 'boolean'],
            'billing_method' => ['required_unless:use_default,true', 'nullable', Rule::enum(BillingMethod::class)],
            'effective_from' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.today()->startOfMonth()->toDateString()],
            'metered_unit' => ['nullable', 'string', 'max:50'],
        ])->validate();
        $date = CarbonImmutable::parse($validated['effective_from']);
        if ($date->day !== 1) {
            throw ValidationException::withMessages(['effective_from' => __('occupancy.first_of_month')]);
        }

        return DB::transaction(function () use ($landlord, $contract, $attributes, $validated, $date): ContractService {
            $contract = Contract::query()->whereHas('room.property', fn (Builder $query) => $query->where('landlord_id', $landlord->id))
                ->whereKey($contract->id)->lockForUpdate()->firstOrFail();
            if ($contract->status === ContractStatus::Cancelled) {
                throw ValidationException::withMessages(['effective_from' => __('occupancy.contract_closed')]);
            }
            $property = Property::query()->where('landlord_id', $landlord->id)->whereKey($contract->room->property_id)->lockForUpdate()->firstOrFail();
            $service = $landlord->services()->whereKey($validated['service_id'])->lockForUpdate()->first();
            if ($service === null || ! $service->is_active || ! $property->propertyServices()->where('service_id', $service->id)->exists()) {
                throw ValidationException::withMessages(['service_id' => __('occupancy.service_unavailable')]);
            }
            $latest = $contract->contractServices()->where('service_id', $service->id)->orderByDesc('effective_from')->lockForUpdate()->first();
            if ($latest !== null && $date->lessThanOrEqualTo($latest->effective_from)) {
                throw ValidationException::withMessages(['effective_from' => __('services.errors.later_effective_date')]);
            }
            if ($latest !== null && $date->lessThan(today())) {
                throw ValidationException::withMessages(['effective_from' => __('occupancy.future_revision')]);
            }
            $invoices = $contract->invoices()->where('status', '!=', InvoiceStatus::Cancelled)
                ->where('billing_period', '>=', $date->format('Y-m'))->lockForUpdate()->get();
            if ($invoices->contains(function ($invoice) use ($date): bool {
                $servicePeriod = array_key_exists('service_billing_period', $invoice->snapshot)
                    ? $invoice->snapshot['service_billing_period'] : $invoice->billing_period;

                return $servicePeriod !== null && $servicePeriod >= $date->format('Y-m');
            })) {
                throw ValidationException::withMessages(['effective_from' => __('occupancy.invoice_locked')]);
            }
            $useDefault = (bool) $validated['use_default'];
            $method = $useDefault ? null : ($validated['billing_method'] instanceof BillingMethod ? $validated['billing_method'] : BillingMethod::from($validated['billing_method']));
            $prices = $useDefault ? ['unit_price' => 0, 'fixed_price' => 0, 'is_enabled' => true, 'effective_from' => $date->toDateString()] :
                app(PropertyServicePricing::class)->validate($method, $attributes, $latest === null);
            $unit = $method === BillingMethod::Metered ? trim($validated['metered_unit'] ?? '') : null;
            if ($method === BillingMethod::Metered && $unit === '') {
                throw ValidationException::withMessages(['metered_unit' => __('occupancy.metered_unit_required')]);
            }

            return $contract->contractServices()->create([
                ...$prices, 'service_id' => $service->id, 'use_default' => $useDefault, 'billing_method' => $method, 'metered_unit' => $unit,
            ]);
        }, attempts: 3);
    }
}
