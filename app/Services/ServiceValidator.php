<?php

namespace App\Services;

use App\Enums\BillingMethod;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ServiceValidator
{
    /**
     * @param  array{name: string, code: string, billing_method: string, unit: string, is_active?: bool}  $attributes
     * @return array{name: string, code: string, billing_method: string, unit: string, is_active: bool}
     */
    public function validate(User $landlord, array $attributes, ?Service $service = null): array
    {
        $validated = Validator::make($attributes, [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('services', 'code')->where('landlord_id', $landlord->id)->ignore($service)],
            'billing_method' => ['required', Rule::enum(BillingMethod::class)],
            'unit' => ['required', 'string', 'max:50'],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ])->validate();

        return [...$validated, 'is_active' => (bool) ($validated['is_active'] ?? true)];
    }
}
