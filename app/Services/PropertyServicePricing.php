<?php

namespace App\Services;

use App\Enums\BillingMethod;
use App\Models\Property;
use App\Models\PropertyService;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PropertyServicePricing
{
    /** @return Collection<int, PropertyService> */
    public function billingCandidatesForUpdate(User $landlord, Property $property, CarbonInterface $date): Collection
    {
        $configurations = PropertyService::query()->forLandlord($landlord)->where('property_id', $property->id)
            ->whereDate('effective_from', '<=', $date->toDateString())->orderByDesc('effective_from')->orderByDesc('id')
            ->lockForUpdate()->get()->unique('service_id')->values();
        $services = $landlord->services()->whereKey($configurations->pluck('service_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $statuses = DB::table('service_status_history')->whereIn('service_id', $services->modelKeys())
            ->where('effective_from', '<=', $date->toDateString())->orderByDesc('effective_from')->lockForUpdate()->get()->unique('service_id')->keyBy('service_id');

        return $configurations->filter(function (PropertyService $configuration) use ($services, $statuses): bool {
            $service = $services->get($configuration->service_id);
            if ($service === null) {
                return false;
            }
            $configuration->setRelation('service', $service);

            return $configuration->is_enabled && (bool) ($statuses->get($service->id)?->is_active ?? $service->is_active);
        })->values();
    }

    /**
     * @param  array{unit_price?: numeric|null, fixed_price?: numeric|null, is_enabled?: bool, effective_from: string}  $attributes
     * @return array{unit_price: numeric, fixed_price: numeric, is_enabled: bool, effective_from: string}
     */
    public function validate(BillingMethod $method, array $attributes): array
    {
        $usesUnitPrice = in_array($method, [BillingMethod::Metered, BillingMethod::PerPerson], true);
        $usesFixedPrice = $method === BillingMethod::Fixed;

        $validated = Validator::make($attributes, [
            'unit_price' => $usesUnitPrice ? ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'] : ['prohibited'],
            'fixed_price' => $usesFixedPrice ? ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'] : ['prohibited'],
            'is_enabled' => ['sometimes', 'required', 'boolean'],
            'effective_from' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
        ])->validate();

        return [
            'unit_price' => $usesUnitPrice ? $validated['unit_price'] : 0,
            'fixed_price' => $usesFixedPrice ? $validated['fixed_price'] : 0,
            'is_enabled' => (bool) ($validated['is_enabled'] ?? true),
            'effective_from' => $validated['effective_from'],
        ];
    }
}
