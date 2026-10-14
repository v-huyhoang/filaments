<?php

namespace App\Actions\Contracts;

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ContractValidator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateDraftContractAction
{
    public function __construct(private ContractValidator $validator) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $landlord, Contract $contract, array $attributes): Contract
    {
        Gate::forUser($landlord)->authorize('editDraft', $contract);
        $validated = $this->validator->validate($attributes);

        return DB::transaction(function () use ($landlord, $contract, $validated): Contract {
            $contract = Contract::query()->whereHas('room.property', fn (Builder $query) => $query->where('landlord_id', $landlord->id))->lockForUpdate()->findOrFail($contract->id);
            Gate::forUser($landlord)->authorize('editDraft', $contract);
            $room = Room::query()->whereHas('property', fn (Builder $query) => $query->where('landlord_id', $landlord->id))->whereKey($validated['room_id'])->lockForUpdate()->first();
            if ($room === null) {
                throw ValidationException::withMessages(['room_id' => __('messages.room_unavailable')]);
            }
            if (! Tenant::query()->where('landlord_id', $landlord->id)->whereKey($validated['tenant_id'])->exists()) {
                throw ValidationException::withMessages(['tenant_id' => __('messages.tenant_unavailable')]);
            }
            if (($validated['status'] ?? 'draft') === ContractStatus::Scheduled->value) {
                $this->validator->ensureRoomCanBeCommitted($room, $validated, null, $contract);
            }
            $contract->update($validated);

            return $contract->refresh();
        }, attempts: 3);
    }
}
