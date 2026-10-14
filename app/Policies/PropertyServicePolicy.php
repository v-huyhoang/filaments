<?php

namespace App\Policies;

use App\Models\PropertyService;
use App\Models\User;

class PropertyServicePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PropertyService $configuration): bool
    {
        return $configuration->property->landlord_id === $user->id
            && $configuration->service->landlord_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, PropertyService $configuration): bool
    {
        return $this->view($user, $configuration);
    }

    public function delete(User $user, PropertyService $configuration): bool
    {
        return false;
    }
}
