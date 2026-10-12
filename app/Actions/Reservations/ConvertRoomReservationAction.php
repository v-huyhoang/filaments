<?php

namespace App\Actions\Reservations;

use App\Actions\Contracts\CreateContractAction;
use App\Enums\ContractStatus;
use App\Enums\RoomReservationStatus;
use App\Models\Contract;
use App\Models\RoomReservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ConvertRoomReservationAction
{
    /**
     * @param  array{end_date?: string|null, rent_amount: numeric, deposit_amount?: numeric, payment_due_day: int, notes?: string|null}  $attributes
     */
    public function handle(User $landlord, RoomReservation $reservation, array $attributes): Contract
    {
        Gate::forUser($landlord)->authorize('update', $reservation);

        return DB::transaction(function () use ($attributes, $landlord, $reservation): Contract {
            $lockedReservation = RoomReservation::query()
                ->whereKey($reservation->id)
                ->whereHas('room.property', fn (Builder $query) => $query->where('landlord_id', $landlord->id))
                ->lockForUpdate()
                ->first();

            if (! $lockedReservation instanceof RoomReservation) {
                throw ValidationException::withMessages(['reservation' => 'The selected reservation is unavailable.']);
            }

            if ($lockedReservation->status !== RoomReservationStatus::Held || $lockedReservation->expires_at->isPast()) {
                throw ValidationException::withMessages(['status' => 'Only an unexpired hold can be converted into a contract.']);
            }

            $contract = app(CreateContractAction::class)->handle($landlord, [
                ...$attributes,
                'room_id' => $lockedReservation->room_id,
                'tenant_id' => $lockedReservation->tenant_id,
                'start_date' => $lockedReservation->expected_move_in_date->toDateString(),
                'status' => ContractStatus::Scheduled->value,
            ], $lockedReservation);

            $lockedReservation->update(['status' => RoomReservationStatus::Converted]);

            return $contract;
        });
    }
}
