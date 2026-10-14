<?php

namespace App\Actions\Services;

use App\Models\Property;
use App\Models\PropertyService;
use App\Models\User;
use App\Services\PropertyServicePricing;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdatePropertyServiceAction
{
    public function __construct(private PropertyServicePricing $pricing) {}

    /** @param array{unit_price?: numeric|null, fixed_price?: numeric|null, is_enabled?: bool, effective_from: string} $attributes */
    public function handle(User $landlord, PropertyService $configuration, array $attributes): PropertyService
    {
        Gate::forUser($landlord)->authorize('update', $configuration);

        return DB::transaction(function () use ($landlord, $configuration, $attributes): PropertyService {
            $property = Property::query()->where('landlord_id', $landlord->id)->whereKey($configuration->property_id)->lockForUpdate()->firstOrFail();
            $configuration = $property->propertyServices()->whereKey($configuration->id)->firstOrFail();
            $service = $landlord->services()->whereKey($configuration->service_id)->lockForUpdate()->firstOrFail();
            $validated = $this->pricing->validate($service->billing_method, $attributes);
            $latest = $property->propertyServices()->where('service_id', $service->id)->latest('effective_from')->firstOrFail();

            if ($latest->id !== $configuration->id) {
                throw ValidationException::withMessages(['effective_from' => __('services.errors.stale_version')]);
            }

            if (CarbonImmutable::parse($validated['effective_from'])->lessThanOrEqualTo($latest->effective_from)) {
                throw ValidationException::withMessages(['effective_from' => __('services.errors.later_effective_date')]);
            }

            if (! $service->is_active && $validated['is_enabled']) {
                throw ValidationException::withMessages(['is_enabled' => __('services.errors.service_unavailable')]);
            }

            return $property->propertyServices()->create(['service_id' => $service->id, ...$validated]);
        });
    }
}
