<?php

namespace App\Services;

use App\Enums\BillingMethod;
use App\Models\Contract;
use App\Models\ContractService;
use App\Models\PropertyService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ContractServicePricing
{
    /** @param Collection<int, PropertyService> $configurations
     * @param  Collection<int, ContractService>|null  $versions
     * @return Collection<int, PropertyService>
     */
    public function resolve(Contract $contract, Collection $configurations, CarbonImmutable $period, bool $lock = false, ?Collection $versions = null): Collection
    {
        $query = $contract->contractServices()->whereDate('effective_from', '<=', $period->startOfMonth()->toDateString())
            ->orderByDesc('effective_from')->orderByDesc('id');
        if ($lock) {
            $query->lockForUpdate();
        }
        $overrides = ($versions ?? $query->get())->unique('service_id')->keyBy('service_id');

        return $configurations->map(function (PropertyService $configuration) use ($overrides): PropertyService {
            $configuration = clone $configuration;
            $override = $overrides->get($configuration->service_id);
            if ($override !== null && ! $override->use_default) {
                $service = clone $configuration->service;
                $service->billing_method = $override->billing_method;
                $service->unit = match ($override->billing_method) {
                    BillingMethod::PerPerson => __('occupancy.person_month'),
                    BillingMethod::Fixed => __('occupancy.month'),
                    BillingMethod::Metered => $override->metered_unit ?? $service->unit,
                    default => $service->unit,
                };
                $configuration->setRelation('service', $service);
                $configuration->unit_price = $override->unit_price;
                $configuration->fixed_price = $override->fixed_price;
                $configuration->is_enabled = $override->is_enabled;
                $configuration->setAttribute('contract_service_id', $override->id);
                $configuration->setAttribute('contract_effective_from', $override->effective_from->toDateString());
            }

            return $configuration;
        })->filter(fn (PropertyService $configuration): bool => $configuration->is_enabled)->values();
    }
}
