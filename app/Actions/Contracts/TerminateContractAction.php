<?php

namespace App\Actions\Contracts;

use App\Enums\ContractStatus;
use App\Enums\RoomStatus;
use App\Models\Contract;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TerminateContractAction
{
    public function handle(User $landlord, Contract $contract, ?string $terminatedAt = null): Contract
    {
        Gate::forUser($landlord)->authorize('update', $contract);

        $validated = Validator::make(['terminated_at' => $terminatedAt], [
            'terminated_at' => ['nullable', 'date'],
        ])->validate();

        return DB::transaction(function () use ($contract, $landlord, $validated): Contract {
            $lockedContract = Contract::query()
                ->whereKey($contract->id)
                ->whereHas('room.property', fn (Builder $query) => $query->where('landlord_id', $landlord->id))
                ->lockForUpdate()
                ->first();

            if (! $lockedContract instanceof Contract) {
                throw ValidationException::withMessages(['contract' => 'The selected contract is unavailable.']);
            }

            if ($lockedContract->status !== ContractStatus::Active) {
                throw ValidationException::withMessages(['status' => 'Only active contracts can be terminated.']);
            }

            $room = Room::query()->whereKey($lockedContract->room_id)->lockForUpdate()->firstOrFail();

            $lockedContract->update([
                'status' => ContractStatus::Terminated,
                'terminated_at' => $validated['terminated_at'] ?? now(),
            ]);

            $hasActiveContract = Contract::query()
                ->where('room_id', $room->id)
                ->where('status', ContractStatus::Active)
                ->exists();

            if (! $hasActiveContract && $room->status === RoomStatus::OCCUPIED) {
                $room->update(['status' => RoomStatus::AVAILABLE]);
            }

            return $lockedContract->refresh();
        });
    }
}
