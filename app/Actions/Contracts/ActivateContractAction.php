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
use Illuminate\Validation\ValidationException;

class ActivateContractAction
{
    public function handle(User $landlord, Contract $contract): Contract
    {
        Gate::forUser($landlord)->authorize('update', $contract);

        return DB::transaction(function () use ($landlord, $contract): Contract {
            $lockedContract = Contract::query()
                ->whereKey($contract->id)
                ->whereHas('room.property', fn (Builder $query) => $query->where('landlord_id', $landlord->id))
                ->lockForUpdate()
                ->first();

            if (! $lockedContract instanceof Contract) {
                throw ValidationException::withMessages(['contract' => __('messages.contract_unavailable')]);
            }

            if ($lockedContract->status !== ContractStatus::Scheduled) {
                throw ValidationException::withMessages(['status' => __('messages.only_scheduled_activate')]);
            }

            if ($lockedContract->start_date->greaterThan(today()) || $lockedContract->end_date?->lessThan(today())) {
                throw ValidationException::withMessages(['start_date' => __('messages.contract_outside_dates')]);
            }

            $room = Room::query()->whereKey($lockedContract->room_id)->lockForUpdate()->firstOrFail();

            if (Contract::query()->where('room_id', $room->id)->whereKeyNot($lockedContract->id)->where('status', ContractStatus::Active)->lockForUpdate()->first(['id']) !== null) {
                throw ValidationException::withMessages(['room_id' => __('workflow.close_previous_contract')]);
            }

            if (in_array($room->status, [RoomStatus::MAINTENANCE, RoomStatus::INACTIVE], true)) {
                throw ValidationException::withMessages(['room_id' => __('messages.room_cannot_activate')]);
            }

            $lockedContract->update([
                'status' => ContractStatus::Active,
                'signed_at' => $lockedContract->signed_at ?? now(),
            ]);

            $room->update(['status' => RoomStatus::OCCUPIED]);

            return $lockedContract->refresh();
        }, attempts: 3);
    }
}
