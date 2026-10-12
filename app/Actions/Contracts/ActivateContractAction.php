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
                throw ValidationException::withMessages(['contract' => 'Hợp đồng đã chọn không khả dụng.']);
            }

            if ($lockedContract->status !== ContractStatus::Scheduled) {
                throw ValidationException::withMessages(['status' => 'Chỉ hợp đồng đã lên lịch mới có thể kích hoạt.']);
            }

            if ($lockedContract->start_date->isFuture() || $lockedContract->end_date?->isPast()) {
                throw ValidationException::withMessages(['start_date' => 'Hợp đồng nằm ngoài thời hạn hiệu lực.']);
            }

            $room = Room::query()->whereKey($lockedContract->room_id)->lockForUpdate()->firstOrFail();

            if (in_array($room->status, [RoomStatus::MAINTENANCE, RoomStatus::INACTIVE], true)) {
                throw ValidationException::withMessages(['room_id' => 'Phòng đang bảo trì hoặc ngừng hoạt động không thể kích hoạt hợp đồng.']);
            }

            $lockedContract->update([
                'status' => ContractStatus::Active,
                'signed_at' => $lockedContract->signed_at ?? now(),
            ]);

            $room->update(['status' => RoomStatus::OCCUPIED]);

            return $lockedContract->refresh();
        });
    }
}
