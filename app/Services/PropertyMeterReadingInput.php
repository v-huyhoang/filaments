<?php

namespace App\Services;

use App\Models\MeterReading;
use App\Models\Property;
use App\Models\PropertyService;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class PropertyMeterReadingInput
{
    public function __construct(private MeterReadingInput $input) {}

    /** @return array<int, string> */
    public function properties(User $landlord): array
    {
        return Property::query()->where('landlord_id', $landlord->id)->orderBy('name')->pluck('name', 'id')->all();
    }

    /** @return array{rooms: array<int, array<string, mixed>>, services: array<int, string>, readings: array<string, array{previous_value: ?string, current_value: ?string}>} */
    public function load(User $landlord, int $propertyId, string $period): array
    {
        Gate::forUser($landlord)->authorize('create', MeterReading::class);
        Validator::make(['billing_period' => $period], ['billing_period' => ['required', 'date_format:Y-m']])->validate();
        $property = Property::query()->where('landlord_id', $landlord->id)->findOrFail($propertyId);
        Gate::forUser($landlord)->authorize('view', $property);

        $month = CarbonImmutable::createFromFormat('!Y-m', $period);
        $rooms = $property->rooms()->orderBy('room_number')->get();
        $configurations = PropertyService::query()->billingCandidates($landlord, $month->endOfMonth())->where('property_id', $property->id)->get();
        $metered = $this->input->meteredForRooms($rooms, $configurations, $month);
        $previousMetered = $this->input->meteredForRooms($rooms, $configurations, $month->subMonth());
        $history = MeterReading::query()->forLandlord($landlord)->whereIn('room_id', $rooms->modelKeys())
            ->where('billing_period', '<=', $period)
            ->whereNotExists(function (Builder $query) use ($period): void {
                $query->selectRaw('1')->from('meter_readings as newer')
                    ->whereColumn('newer.room_id', 'meter_readings.room_id')
                    ->whereColumn('newer.service_id', 'meter_readings.service_id')
                    ->whereColumn('newer.billing_period', '>', 'meter_readings.billing_period')
                    ->where('newer.billing_period', '<=', $period);
            })->get()
            ->groupBy(fn (MeterReading $reading): string => $reading->room_id.'_'.$reading->service_id);

        $result = ['rooms' => [], 'services' => [], 'readings' => []];
        foreach ($rooms as $room) {
            $meters = [];
            foreach ($metered->get($room->id) as $configuration) {
                $serviceId = $configuration->service_id;
                $key = $room->id.'_'.$serviceId;
                $latest = $history->get($key)?->first();
                $recorded = $latest?->billing_period === $period;
                $previous = $recorded ? $latest->previous_value : ($previousMetered->get($room->id)->contains('service_id', $serviceId) ? $latest?->current_value : null);
                $meters[$serviceId] = [
                    'key' => $key, 'recorded' => $recorded, 'previous_value' => $previous,
                    'current_value' => $recorded ? $latest->current_value : null,
                    'usage' => $recorded ? $latest->usage : null, 'unit' => $configuration->service->unit,
                ];
                $result['services'][$serviceId] = $configuration->service->name;
                if (! $recorded) {
                    $result['readings'][$key] = ['previous_value' => $previous, 'current_value' => null];
                }
            }
            $result['rooms'][$room->id] = ['room_number' => $room->room_number, 'meters' => $meters];
        }

        return $result;
    }
}
