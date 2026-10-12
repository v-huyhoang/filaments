<?php

namespace App\Actions\Contracts;

use App\Enums\ContractStatus;
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
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateContractAction
{
    /**
     * @param  array{room_id: int, tenant_id: int, start_date: string, end_date?: string|null, rent_amount: numeric, deposit_amount?: numeric, payment_due_day: int, status?: string, notes?: string|null}  $attributes
     */
    public function handle(User $landlord, array $attributes, ?RoomReservation $reservation = null): Contract
    {
        Gate::forUser($landlord)->authorize('create', Contract::class);

        $validated = Validator::make($attributes, [
            'room_id' => ['required', 'integer'],
            'tenant_id' => ['required', 'integer'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'rent_amount' => ['required', 'numeric', 'min:0'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_due_day' => ['required', 'integer', 'between:1,28'],
            'status' => ['nullable', Rule::in([ContractStatus::Draft->value, ContractStatus::Scheduled->value])],
            'notes' => ['nullable', 'string'],
        ])->validate();

        $status = ContractStatus::from($validated['status'] ?? ContractStatus::Draft->value);

        return DB::transaction(function () use ($landlord, $reservation, $status, $validated): Contract {
            $room = Room::query()
                ->whereKey($validated['room_id'])
                ->whereHas('property', fn (Builder $query) => $query->where('landlord_id', $landlord->id))
                ->lockForUpdate()
                ->first();

            if (! $room instanceof Room) {
                throw ValidationException::withMessages(['room_id' => 'Phòng đã chọn không khả dụng.']);
            }

            $tenant = Tenant::query()
                ->whereKey($validated['tenant_id'])
                ->where('landlord_id', $landlord->id)
                ->first();

            if (! $tenant instanceof Tenant) {
                throw ValidationException::withMessages(['tenant_id' => 'Người thuê đã chọn không khả dụng.']);
            }

            if ($reservation instanceof RoomReservation && ($reservation->room_id !== $room->id || $reservation->tenant_id !== $tenant->id)) {
                throw ValidationException::withMessages(['room_id' => 'Thông tin giữ chỗ không khớp với phòng và người thuê đã chọn.']);
            }

            if ($status === ContractStatus::Scheduled) {
                $this->ensureRoomCanBeCommitted($room, $validated, $reservation);
            }

            return Contract::query()->create([
                ...$validated,
                'room_reservation_id' => $reservation?->id,
                'deposit_amount' => $validated['deposit_amount'] ?? 0,
                'status' => $status,
            ]);
        });
    }

    /**
     * @param  array{start_date: string, end_date?: string|null}  $attributes
     */
    private function ensureRoomCanBeCommitted(Room $room, array $attributes, ?RoomReservation $reservation): void
    {
        if (in_array($room->status, [RoomStatus::MAINTENANCE, RoomStatus::INACTIVE], true)) {
            throw ValidationException::withMessages(['room_id' => 'Phòng đang bảo trì hoặc ngừng hoạt động không thể tạo hợp đồng.']);
        }

        $hasOverlap = Contract::query()
            ->where('room_id', $room->id)
            ->committed()
            ->whereDate('start_date', '<=', $attributes['end_date'] ?? '9999-12-31')
            ->where(fn (Builder $query) => $query
                ->whereNull('end_date')
                ->orWhereDate('end_date', '>=', $attributes['start_date']))
            ->exists();

        if ($hasOverlap) {
            throw ValidationException::withMessages(['start_date' => 'Phòng đã có hợp đồng trong khoảng thời gian này.']);
        }

        $hasAnotherLiveReservation = RoomReservation::query()
            ->liveHold()
            ->where('room_id', $room->id)
            ->when($reservation, fn (Builder $query) => $query->whereKeyNot($reservation->id))
            ->exists();

        if ($hasAnotherLiveReservation) {
            throw ValidationException::withMessages(['room_id' => 'Phòng hiện có giữ chỗ còn hiệu lực.']);
        }
    }
}
