<?php

namespace App\Actions\Services;

use App\Models\Service;
use App\Models\User;
use App\Services\ServiceValidator;
use Illuminate\Support\Facades\Gate;

class CreateServiceAction
{
    public function __construct(private ServiceValidator $validator) {}

    /** @param array{name: string, code: string, billing_method: string, unit: string, is_active?: bool} $attributes */
    public function handle(User $landlord, array $attributes): Service
    {
        Gate::forUser($landlord)->authorize('create', Service::class);

        return $landlord->services()->create($this->validator->validate($landlord, $attributes));
    }
}
