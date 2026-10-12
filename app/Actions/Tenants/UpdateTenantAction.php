<?php

namespace App\Actions\Tenants;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class UpdateTenantAction
{
    /**
     * @param  array{full_name?: string, phone?: string, email?: string|null, date_of_birth?: string|null, identity_number?: string|null, identity_issued_date?: string|null, identity_issued_place?: string|null, address?: string|null, emergency_contact_name?: string|null, emergency_contact_phone?: string|null, status?: string, notes?: string|null}  $attributes
     */
    public function handle(User $landlord, Tenant $tenant, array $attributes): Tenant
    {
        Gate::forUser($landlord)->authorize('update', $tenant);

        $tenant->update($attributes);

        return $tenant->refresh();
    }
}
