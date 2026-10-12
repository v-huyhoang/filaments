<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

class TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Tenant $tenant): bool
    {
        return $tenant->landlord_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $this->ownsTenant($user, $tenant);
    }

    public function delete(User $user, Tenant $tenant): bool
    {
        return $this->ownsTenant($user, $tenant);
    }

    public function restore(User $user, Tenant $tenant): bool
    {
        return $this->ownsTenant($user, $tenant);
    }

    public function forceDelete(User $user, Tenant $tenant): bool
    {
        return false;
    }

    private function ownsTenant(User $user, Tenant $tenant): bool
    {
        return $tenant->landlord_id === $user->id;
    }
}
