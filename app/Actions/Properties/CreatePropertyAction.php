<?php

namespace App\Actions\Properties;

use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class CreatePropertyAction
{
    /**
     * @param  array{name: string, address: string, description?: string|null, is_active?: bool}  $attributes
     */
    public function handle(User $landlord, array $attributes): Property
    {
        Gate::forUser($landlord)->authorize('create', Property::class);

        return $landlord->properties()->create($attributes);
    }
}
