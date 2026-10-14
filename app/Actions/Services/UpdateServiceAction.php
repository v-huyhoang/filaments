<?php

namespace App\Actions\Services;

use App\Models\Service;
use App\Models\User;
use App\Services\ServiceValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateServiceAction
{
    public function __construct(private ServiceValidator $validator) {}

    /** @param array{name: string, code: string, billing_method: string, unit: string, is_active?: bool} $attributes */
    public function handle(User $landlord, Service $service, array $attributes): Service
    {
        Gate::forUser($landlord)->authorize('update', $service);

        return DB::transaction(function () use ($landlord, $service, $attributes): Service {
            $service = $landlord->services()->whereKey($service->id)->lockForUpdate()->firstOrFail();
            $validated = $this->validator->validate($landlord, $attributes, $service);

            if ($service->propertyServices()->exists()) {
                foreach (['billing_method', 'unit'] as $field) {
                    if ($validated[$field] !== ($field === 'billing_method' ? $service->billing_method->value : $service->unit)) {
                        throw ValidationException::withMessages([$field => __('services.errors.definition_in_use')]);
                    }
                }
            }

            if ($validated['is_active'] !== $service->is_active) {
                DB::table('service_status_history')->insertOrIgnore([
                    'service_id' => $service->id, 'effective_from' => '1000-01-01', 'is_active' => $service->is_active,
                ]);
                DB::table('service_status_history')->updateOrInsert(
                    ['service_id' => $service->id, 'effective_from' => today()->toDateString()],
                    ['is_active' => $validated['is_active']],
                );
            }
            $service->update($validated);

            return $service;
        }, attempts: 3);
    }
}
