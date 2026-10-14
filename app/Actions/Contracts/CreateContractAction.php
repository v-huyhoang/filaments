<?php

namespace App\Actions\Contracts;

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\Room;
use App\Models\RoomReservation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ContractValidator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateContractAction
{
    public function __construct(private ContractValidator $validator) {}

    /**
     * @param  array{room_id: int, tenant_id: int, start_date: string, end_date?: string|null, rent_amount: numeric, deposit_amount?: numeric, payment_due_day: int, status?: string, notes?: string|null}  $attributes
     */
    public function handle(User $landlord, array $attributes, ?RoomReservation $reservation = null): Contract
    {
        Gate::forUser($landlord)->authorize('create', Contract::class);

        $validated = $this->validator->validate($attributes);

        $status = ContractStatus::from($validated['status'] ?? ContractStatus::Draft->value);

        return DB::transaction(function () use ($landlord, $reservation, $status, $validated): Contract {
            $room = Room::query()
                ->whereKey($validated['room_id'])
                ->whereHas('property', fn (Builder $query) => $query->where('landlord_id', $landlord->id))
                ->lockForUpdate()
                ->first();

            if (! $room instanceof Room) {
                throw ValidationException::withMessages(['room_id' => __('messages.room_unavailable')]);
            }

            $tenant = Tenant::query()
                ->whereKey($validated['tenant_id'])
                ->where('landlord_id', $landlord->id)
                ->first();

            if (! $tenant instanceof Tenant) {
                throw ValidationException::withMessages(['tenant_id' => __('messages.tenant_unavailable')]);
            }

            if ($reservation instanceof RoomReservation && ($reservation->room_id !== $room->id || $reservation->tenant_id !== $tenant->id)) {
                throw ValidationException::withMessages(['room_id' => __('messages.reservation_mismatch')]);
            }

            if ($status === ContractStatus::Scheduled) {
                $this->validator->ensureRoomCanBeCommitted($room, $validated, $reservation);
            }

            return Contract::query()->create([
                ...$validated,
                'room_reservation_id' => $reservation?->id,
                'deposit_amount' => $validated['deposit_amount'] ?? 0,
                'status' => $status,
            ]);
        }, attempts: 3);
    }
}
