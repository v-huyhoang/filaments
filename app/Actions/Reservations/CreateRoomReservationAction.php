<?php

namespace App\Actions\Reservations;

use App\Enums\ContractStatus;
use App\Enums\RoomReservationStatus;
use App\Enums\RoomStatus;
use App\Models\Contract;
use App\Models\Room;
use App\Models\RoomReservation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreateRoomReservationAction
{
    /**
     * @param  array{room_id: int, tenant_id: int, expected_move_in_date: string, hold_amount: numeric, paid_at: string, payment_method: string, payment_reference?: string|null, expires_at: string, notes?: string|null}  $attributes
     */
    public function handle(User $landlord, array $attributes): RoomReservation
    {
        Gate::forUser($landlord)->authorize('create', RoomReservation::class);

        $validated = Validator::make($attributes, [
            'room_id' => ['required', 'integer'],
            'tenant_id' => ['required', 'integer'],
            'expected_move_in_date' => ['required', 'date', 'after_or_equal:today'],
            'hold_amount' => ['required', 'numeric', 'gt:0'],
            'paid_at' => ['required', 'date'],
            'payment_method' => ['required', 'string', 'max:50'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'expires_at' => ['required', 'date', 'after:paid_at', 'after:now'],
            'notes' => ['nullable', 'string'],
        ])->validate();

        return DB::transaction(function () use ($landlord, $validated): RoomReservation {
            $room = Room::query()
                ->whereKey($validated['room_id'])
                ->whereHas('property', fn (Builder $query) => $query->where('landlord_id', $landlord->id))
                ->lockForUpdate()
                ->first();

            if (! $room instanceof Room) {
                throw ValidationException::withMessages(['room_id' => 'Phòng đã chọn không khả dụng.']);
            }

            $tenantExists = Tenant::query()
                ->whereKey($validated['tenant_id'])
                ->where('landlord_id', $landlord->id)
                ->exists();

            if (! $tenantExists) {
                throw ValidationException::withMessages(['tenant_id' => 'Người thuê đã chọn không khả dụng.']);
            }

            if (in_array($room->status, [RoomStatus::MAINTENANCE, RoomStatus::INACTIVE], true)) {
                throw ValidationException::withMessages(['room_id' => 'Phòng đang bảo trì hoặc ngừng hoạt động không thể giữ chỗ.']);
            }

            if (RoomReservation::query()->liveHold()->where('room_id', $room->id)->exists()) {
                throw ValidationException::withMessages(['room_id' => 'Phòng đã có giữ chỗ còn hiệu lực.']);
            }

            $hasCommittedContract = Contract::query()
                ->where('room_id', $room->id)
                ->whereIn('status', [ContractStatus::Scheduled, ContractStatus::Active])
                ->where(fn (Builder $query) => $query
                    ->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $validated['expected_move_in_date']))
                ->exists();

            if ($hasCommittedContract) {
                throw ValidationException::withMessages(['expected_move_in_date' => 'Phòng đã có hợp đồng vượt quá ngày dự kiến nhận phòng.']);
            }

            return RoomReservation::query()->create([
                ...$validated,
                'status' => RoomReservationStatus::Held,
            ]);
        });
    }
}
