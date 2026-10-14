<?php

namespace App\Services;

use App\Enums\BillingMethod;
use App\Models\MeterReading;
use App\Models\PropertyService;
use App\Models\Room;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;

class MeterReadingInput
{
    /** @return array<int, string> */
    public function services(User $landlord, ?int $roomId, ?string $period): array
    {
        if (Validator::make(['period' => $period], ['period' => ['required', 'date_format:Y-m']])->fails()) {
            return [];
        }
        $room = Room::query()->whereHas('property', fn (Builder $query) => $query->where('landlord_id', $landlord->id))->find($roomId);
        if ($room === null) {
            return [];
        }

        return PropertyService::query()->billingCandidates($landlord, CarbonImmutable::createFromFormat('!Y-m', $period)->endOfMonth())
            ->where('property_id', $room->property_id)->whereHas('service', fn (Builder $query) => $query->where('billing_method', BillingMethod::Metered))
            ->get()->mapWithKeys(fn (PropertyService $configuration): array => [$configuration->service_id => $configuration->service->name])->all();
    }

    public function previous(User $landlord, ?int $roomId, ?int $serviceId, ?string $period): ?string
    {
        return MeterReading::query()->forLandlord($landlord)->where('room_id', $roomId)->where('service_id', $serviceId)
            ->where('billing_period', '<', $period ?? '')->orderByDesc('billing_period')->value('current_value');
    }
}
