<?php

namespace App\Policies;

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\User;

class ContractPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Contract $contract): bool
    {
        return $this->ownsContract($user, $contract);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Contract $contract): bool
    {
        return $this->ownsContract($user, $contract);
    }

    public function editDraft(User $user, Contract $contract): bool
    {
        return $this->ownsContract($user, $contract) && $contract->status === ContractStatus::Draft;
    }

    public function delete(User $user, Contract $contract): bool
    {
        return false;
    }

    public function restore(User $user, Contract $contract): bool
    {
        return false;
    }

    public function forceDelete(User $user, Contract $contract): bool
    {
        return false;
    }

    private function ownsContract(User $user, Contract $contract): bool
    {
        return $contract->room()->whereHas('property', fn ($query) => $query->where('landlord_id', $user->id))->exists();
    }
}
