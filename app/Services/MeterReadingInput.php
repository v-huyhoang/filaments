<?php

namespace App\Services;

use App\Enums\BillingMethod;
use App\Enums\ContractStatus;
use App\Models\MeterReading;
use App\Models\PropertyService;
use App\Models\Room;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
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

        $month = CarbonImmutable::createFromFormat('!Y-m', $period);
        $configurations = PropertyService::query()->billingCandidates($landlord, $month->endOfMonth())->where('property_id', $room->property_id)->get();

        return $this->metered($room, $configurations, $month)->mapWithKeys(fn (PropertyService $configuration): array => [$configuration->service_id => $configuration->service->name])->all();
    }

    /** @param Collection<int, PropertyService> $configurations
     * @return Collection<int, PropertyService>
     */
    public function metered(Room $room, Collection $configurations, CarbonImmutable $period, bool $lock = false): Collection
    {
        $query = $room->contracts()->whereIn('status', [ContractStatus::Scheduled, ContractStatus::Active, ContractStatus::Terminated, ContractStatus::Expired])
            ->whereDate('start_date', '<=', $period->endOfMonth())
            ->where(fn (Builder $query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', $period->startOfMonth()))
            ->where(fn (Builder $query) => $query->whereNull('terminated_at')->orWhereDate('terminated_at', '>', $period->startOfMonth()));
        if ($lock) {
            $query->lockForUpdate();
        }
        $contracts = $query->with(['contractServices' => function (HasMany $versions) use ($period, $lock): void {
            $versions->whereDate('effective_from', '<=', $period->startOfMonth()->toDateString())->orderByDesc('effective_from')->orderByDesc('id');
            if ($lock) {
                $versions->lockForUpdate();
            }
        }])->get();
        $resolved = $contracts->isEmpty() ? $configurations : $contracts->flatMap(
            fn ($contract): Collection => app(ContractServicePricing::class)->resolve($contract, $configurations, $period, $lock, $contract->contractServices)
        );

        return $resolved->filter(fn (PropertyService $configuration): bool => $configuration->service->billing_method === BillingMethod::Metered)->unique('service_id')->values();
    }

    public function previous(User $landlord, ?int $roomId, ?int $serviceId, ?string $period): ?string
    {
        if (Validator::make(['period' => $period], ['period' => ['required', 'date_format:Y-m']])->fails()) {
            return null;
        }
        $room = Room::query()->whereHas('property', fn (Builder $query) => $query->where('landlord_id', $landlord->id))->find($roomId);
        if ($room === null) {
            return null;
        }
        $month = CarbonImmutable::createFromFormat('!Y-m', $period);
        $configurations = PropertyService::query()->billingCandidates($landlord, $month->endOfMonth())->where('property_id', $room->property_id)->get();
        if (! $this->metered($room, $configurations, $month->subMonth())->contains('service_id', $serviceId)) {
            return null;
        }

        return MeterReading::query()->forLandlord($landlord)->where('room_id', $roomId)->where('service_id', $serviceId)
            ->where('billing_period', '<', $period ?? '')->orderByDesc('billing_period')->value('current_value');
    }
}
