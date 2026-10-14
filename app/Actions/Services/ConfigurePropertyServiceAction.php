<?php

namespace App\Actions\Services;

use App\Models\Property;
use App\Models\PropertyService;
use App\Models\User;
use App\Services\PropertyServicePricing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ConfigurePropertyServiceAction
{
    public function __construct(private PropertyServicePricing $pricing) {}

    /** @param array{service_id: int, unit_price?: numeric|null, fixed_price?: numeric|null, is_enabled?: bool, effective_from: string} $attributes */
    public function handle(User $landlord, Property $property, array $attributes): PropertyService
    {
        Gate::forUser($landlord)->authorize('update', $property);
        Gate::forUser($landlord)->authorize('create', PropertyService::class);
        $validated = Validator::make($attributes, ['service_id' => ['required', 'integer']])->validate();

        return DB::transaction(function () use ($landlord, $property, $attributes, $validated): PropertyService {
            $property = Property::query()->where('landlord_id', $landlord->id)->whereKey($property->id)->lockForUpdate()->firstOrFail();
            $service = $landlord->services()->whereKey($validated['service_id'])->where('is_active', true)->lockForUpdate()->first();

            if ($service === null) {
                throw ValidationException::withMessages(['service_id' => __('services.errors.service_unavailable')]);
            }

            if ($property->propertyServices()->where('service_id', $service->id)->exists()) {
                throw ValidationException::withMessages(['service_id' => __('services.errors.already_configured')]);
            }

            return $property->propertyServices()->create([
                'service_id' => $service->id,
                ...$this->pricing->validate($service->billing_method, $attributes),
            ]);
        });
    }
}
