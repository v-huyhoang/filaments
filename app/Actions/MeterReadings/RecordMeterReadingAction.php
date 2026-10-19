<?php

namespace App\Actions\MeterReadings;

use App\Enums\BillingMethod;
use App\Models\MeterReading;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use App\Services\MeterReadingInput;
use App\Services\PropertyServicePricing;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RecordMeterReadingAction
{
    public function __construct(private PropertyServicePricing $pricing) {}

    /** @param array{room_id: int, service_id: int, billing_period: string, previous_value?: numeric|null, current_value: numeric, notes?: string|null} $attributes */
    public function handle(User $landlord, array $attributes): MeterReading
    {
        Gate::forUser($landlord)->authorize('create', MeterReading::class);
        $valueRules = ['numeric', 'decimal:0,3', 'min:0', 'max:999999999.999'];
        $validated = Validator::make($attributes, [
            'room_id' => ['required', 'integer'],
            'service_id' => ['required', 'integer'],
            'billing_period' => ['required', 'date_format:Y-m'],
            'previous_value' => ['nullable', ...$valueRules],
            'current_value' => ['required', ...$valueRules],
            'notes' => ['nullable', 'string', 'max:5000'],
        ])->validate();

        return DB::transaction(function () use ($landlord, $validated): MeterReading {
            $room = Room::query()->whereHas('property', fn (Builder $query) => $query->where('landlord_id', $landlord->id))->find($validated['room_id']);

            if ($room === null) {
                throw ValidationException::withMessages(['room_id' => __('meter_readings.errors.room_unavailable')]);
            }

            Gate::forUser($landlord)->authorize('update', $room);
            $property = Property::query()->where('landlord_id', $landlord->id)->whereKey($room->property_id)->lockForUpdate()->firstOrFail();
            $room = Room::query()->whereKey($room->id)->lockForUpdate()->firstOrFail();
            $periodEnd = CarbonImmutable::createFromFormat('!Y-m', $validated['billing_period'])->endOfMonth();
            $configurations = $this->pricing->billingCandidatesForUpdate($landlord, $property, $periodEnd);
            $configuration = app(MeterReadingInput::class)->metered($room, $configurations, $periodEnd->startOfMonth(), true)->firstWhere('service_id', (int) $validated['service_id']);
            $service = $configuration?->service;

            if ($service === null || $service->billing_method !== BillingMethod::Metered) {
                throw ValidationException::withMessages(['service_id' => __('meter_readings.errors.service_unavailable')]);
            }

            $latest = MeterReading::query()->where('room_id', $room->id)->where('service_id', $service->id)->orderByDesc('billing_period')->lockForUpdate()->first();

            if ($latest !== null && $validated['billing_period'] <= $latest->billing_period) {
                throw ValidationException::withMessages(['billing_period' => __('meter_readings.errors.period_order')]);
            }

            $continuesMetered = $latest !== null && app(MeterReadingInput::class)->metered($room, $configurations, $periodEnd->startOfMonth()->subMonth(), true)->contains('service_id', $service->id);
            if ($continuesMetered && $validated['billing_period'] !== CarbonImmutable::createFromFormat('!Y-m', $latest->billing_period)->addMonth()->format('Y-m')) {
                throw ValidationException::withMessages(['billing_period' => __('workflow.missing_period')]);
            }

            $previous = $continuesMetered ? $latest->current_value : ($validated['previous_value'] ?? null);

            if ($previous === null) {
                throw ValidationException::withMessages(['previous_value' => __('meter_readings.errors.initial_value_required')]);
            }

            if ($continuesMetered && isset($validated['previous_value']) && ! BigDecimal::of((string) $validated['previous_value'])->isEqualTo($previous)) {
                throw ValidationException::withMessages(['previous_value' => __('meter_readings.errors.previous_mismatch')]);
            }

            $current = BigDecimal::of((string) $validated['current_value']);

            if ($current->isLessThan((string) $previous)) {
                throw ValidationException::withMessages(['current_value' => __('meter_readings.errors.decreasing_value')]);
            }

            return MeterReading::query()->create([
                'room_id' => $room->id,
                'service_id' => $service->id,
                'billing_period' => $validated['billing_period'],
                'previous_value' => $previous,
                'current_value' => $validated['current_value'],
                'usage' => (string) $current->minus((string) $previous)->toScale(3),
                'recorded_by' => $landlord->id,
                'recorded_at' => now(),
                'notes' => $validated['notes'] ?? null,
            ]);
        }, attempts: 3);
    }
}
